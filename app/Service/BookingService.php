<?php

declare(strict_types=1);

namespace App\Service;

use App\Domain\BusyException;
use App\Domain\ConflictException;
use App\Domain\GuestCounts;
use App\Domain\NullPriceQuoter;
use App\Domain\PriceQuoter;
use App\Domain\StateException;
use App\Domain\StayDates;
use App\Domain\ValidationException;
use App\Repository\ApartmentRepository;
use App\Repository\AvailabilityRepository;
use App\Repository\BookingRepository;
use App\Support\AuditLog;
use DateTimeImmutable;
use DateTimeZone;
use LogicException;
use PDO;
use PDOException;
use Throwable;

/**
 * Every write that can change the occupation of an apartment goes through this class.
 *
 * Concurrency (decision P6): each such operation runs in a READ COMMITTED transaction
 * and starts with `SELECT ... FOR UPDATE` on the apartment row, which serialises all
 * writers of that apartment. The overlap check is repeated under that lock, so two
 * confirmed bookings (or a booking and a block) can never share a night. Each
 * transaction takes that one lock first, so there is no lock-ordering deadlock.
 *
 * Only confirmed bookings and blocks occupy dates; pending requests never do.
 */
final class BookingService
{
    /** `website` is reserved for approved public requests. */
    public const MANUAL_ORIGINS = ['phone', 'email', 'agency', 'novasol', 'other'];

    private const LOCK_WAIT_SECONDS = 10;
    private const MYSQL_LOCK_WAIT_TIMEOUT = 1205;
    private const MYSQL_DEADLOCK = 1213;
    private const MYSQL_DUPLICATE_ENTRY = 1062;
    private const REFERENCE_ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    private ApartmentRepository $apartments;
    private AvailabilityRepository $availability;
    private BookingRepository $bookings;
    private AuditLog $audit;
    private PriceQuoter $quoter;
    /** @var callable(): string */
    private $today;

    /** @param (callable(): string)|null $today returns today's date as "YYYY-MM-DD" in the business time zone */
    public function __construct(private PDO $db, ?PriceQuoter $quoter = null, ?callable $today = null)
    {
        $this->apartments = new ApartmentRepository($db);
        $this->availability = new AvailabilityRepository($db);
        $this->bookings = new BookingRepository($db);
        $this->audit = new AuditLog($db);
        $this->quoter = $quoter ?? new NullPriceQuoter();
        $this->today = $today ?? static fn (): string => (new DateTimeImmutable('now', new DateTimeZone(date_default_timezone_get())))->format('Y-m-d');
    }

    // === Public request =====================================================

    /**
     * Stores a public request as `pending`. Dates already unavailable are refused up front;
     * the binding check happens when the admin confirms.
     *
     * @param array<string, mixed> $input
     * @return array{id: int, reference: string}
     * @throws ValidationException|ConflictException|StateException
     */
    public function createRequest(array $input): array
    {
        $errors = [];
        $stay = $guests = null;

        try {
            $stay = StayDates::fromStrings(self::str($input, 'check_in'), self::str($input, 'check_out'));
            $stay->assertBookableFromPublic(($this->today)());
        } catch (ValidationException $e) {
            $errors += $e->errors();
        }
        try {
            $guests = GuestCounts::from($input['adults'] ?? null, $input['children'] ?? 0, $input['pets'] ?? 0);
        } catch (ValidationException $e) {
            $errors += $e->errors();
        }

        $firstName = trim(self::str($input, 'first_name'));
        $lastName = trim(self::str($input, 'last_name'));
        $email = trim(self::str($input, 'email'));
        $phone = trim(self::str($input, 'phone'));
        $notes = trim(self::str($input, 'notes'));
        $locale = self::str($input, 'locale') ?: 'it';

        if (!self::lengthBetween($firstName, 1, 100)) {
            $errors['first_name'] = 'invalid_first_name';
        }
        if (!self::lengthBetween($lastName, 1, 100)) {
            $errors['last_name'] = 'invalid_last_name';
        }
        if (!self::isValidEmail($email)) {
            $errors['email'] = 'invalid_email';
        }
        if (!preg_match('/^\+?[0-9][0-9\s().\-]{4,39}$/', $phone)) {
            $errors['phone'] = 'invalid_phone';
        }
        if (mb_strlen($notes) > 2000) {
            $errors['notes'] = 'notes_too_long';
        }
        if (!in_array($locale, ['it', 'en'], true)) {
            $errors['locale'] = 'invalid_locale';
        }
        if (filter_var($input['privacy_accepted'] ?? false, FILTER_VALIDATE_BOOLEAN) !== true) {
            $errors['privacy_accepted'] = 'privacy_required';
        }

        $apartmentId = self::intOrNull($input['apartment_id'] ?? null);
        if ($apartmentId === null) {
            $errors['apartment_id'] = 'invalid_apartment';
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $apartment = $this->apartments->find($apartmentId);
        if ($apartment === null || !(int) $apartment['is_active'] || !(int) $apartment['accepts_online_requests']) {
            throw new StateException('apartment_not_bookable');
        }
        if ($guests->exceedsCapacity(self::intOrNull($apartment['max_guests']))) {
            throw new ValidationException(['guests' => 'over_capacity']);
        }

        $conflicts = $this->availability->conflicts($apartmentId, $stay);
        if ($conflicts !== []) {
            throw new ConflictException($conflicts);
        }

        $quote = $this->quoter->quote($apartmentId, $stay, $guests);

        return $this->transactional(function () use ($apartmentId, $stay, $guests, $firstName, $lastName, $email, $phone, $notes, $locale, $quote): array {
            $row = [
                'apartment_id' => $apartmentId,
                'check_in' => $stay->checkIn,
                'check_out' => $stay->checkOut,
                'adults' => $guests->adults,
                'children' => $guests->children,
                'pets' => $guests->pets,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $email,
                'phone' => $phone,
                'notes' => $notes === '' ? null : $notes,
                'locale' => $locale,
                'quoted_total_cents' => $quote?->totalCents,
                'price_breakdown' => $quote === null ? null : json_encode($quote->breakdown, JSON_UNESCAPED_UNICODE),
            ];

            for ($attempt = 0; ; $attempt++) {
                $row['reference'] = self::newReference();
                try {
                    $id = $this->bookings->insertRequest($row);
                    break;
                } catch (PDOException $e) {
                    if (($e->errorInfo[1] ?? 0) !== self::MYSQL_DUPLICATE_ENTRY || $attempt >= 4) {
                        throw $e;
                    }
                }
            }

            // No personal data in the audit trail.
            $this->audit->record('booking_request', $id, 'created', 'Richiesta ' . $row['reference'] . ' ricevuta', null, [
                'status' => 'pending',
                'apartment_id' => $apartmentId,
                'check_in' => $stay->checkIn,
                'check_out' => $stay->checkOut,
                'adults' => $guests->adults,
                'children' => $guests->children,
                'pets' => $guests->pets,
            ]);

            return ['id' => $id, 'reference' => $row['reference']];
        });
    }

    // === Admin: request decisions ===========================================

    /**
     * Turns a pending request into a confirmed booking.
     *
     * @return array{booking_id: int}
     * @throws StateException|ConflictException|BusyException
     */
    public function confirmRequest(int $requestId): array
    {
        $request = $this->bookings->request($requestId) ?? throw new StateException('request_not_found');
        $apartmentId = (int) $request['apartment_id'];

        return $this->withApartmentLock($apartmentId, function () use ($requestId, $apartmentId): array {
            // Re-read under the lock: another admin action may have decided it meanwhile.
            $request = $this->bookings->request($requestId, true) ?? throw new StateException('request_not_found');
            if ($request['status'] !== 'pending') {
                throw new StateException('request_not_pending');
            }

            $stay = StayDates::fromStrings((string) $request['check_in'], (string) $request['check_out'], 36500);
            $this->assertFree($apartmentId, $stay);

            $bookingId = $this->bookings->insertBooking([
                'apartment_id' => $apartmentId,
                'booking_request_id' => $requestId,
                'origin' => 'website',
                'check_in' => $stay->checkIn,
                'check_out' => $stay->checkOut,
                'adults' => $request['adults'],
                'children' => $request['children'],
                'pets' => $request['pets'],
                'guest_name' => trim($request['first_name'] . ' ' . $request['last_name']),
                'email' => $request['email'],
                'phone' => $request['phone'],
                'total_cents' => $request['quoted_total_cents'],
                'notes' => $request['notes'],
            ]);
            $this->bookings->setRequestStatus($requestId, 'confirmed', true);

            $this->audit->record('booking_request', $requestId, 'status_changed', 'Richiesta pending → confirmed',
                ['status' => 'pending'], ['status' => 'confirmed', 'booking_id' => $bookingId]);
            $this->audit->record('booking', $bookingId, 'created', 'Prenotazione creata da richiesta', null, [
                'status' => 'confirmed', 'origin' => 'website', 'apartment_id' => $apartmentId,
                'check_in' => $stay->checkIn, 'check_out' => $stay->checkOut, 'booking_request_id' => $requestId,
            ]);

            return ['booking_id' => $bookingId];
        });
    }

    /**
     * @throws StateException|BusyException
     */
    public function rejectRequest(int $requestId): void
    {
        // Rejecting never changes occupation, so it needs only the request row lock.
        $this->transactional(function () use ($requestId): void {
            $request = $this->bookings->request($requestId, true) ?? throw new StateException('request_not_found');
            if ($request['status'] !== 'pending') {
                throw new StateException('request_not_pending');
            }
            $this->bookings->setRequestStatus($requestId, 'rejected', true);
            $this->audit->record('booking_request', $requestId, 'status_changed', 'Richiesta pending → rejected',
                ['status' => 'pending'], ['status' => 'rejected']);
        });
    }

    // === Admin: manual bookings, cancellation, blocks ======================

    /**
     * Records a confirmed booking received through another channel.
     *
     * @param array<string, mixed> $input
     * @return int booking id
     * @throws ValidationException|ConflictException|StateException|BusyException
     */
    public function createManualBooking(array $input): int
    {
        $errors = [];
        $stay = $guests = null;

        try {
            $stay = StayDates::fromStrings(self::str($input, 'check_in'), self::str($input, 'check_out'));
        } catch (ValidationException $e) {
            $errors += $e->errors();
        }
        try {
            $guests = GuestCounts::from($input['adults'] ?? 1, $input['children'] ?? 0, $input['pets'] ?? 0);
        } catch (ValidationException $e) {
            $errors += $e->errors();
        }

        $origin = self::str($input, 'origin');
        $guestName = trim(self::str($input, 'guest_name'));
        $email = trim(self::str($input, 'email'));
        $phone = trim(self::str($input, 'phone'));
        $notes = trim(self::str($input, 'notes'));
        $total = $input['total_cents'] ?? null;

        if (!in_array($origin, self::MANUAL_ORIGINS, true)) {
            $errors['origin'] = 'invalid_origin';
        }
        if (!self::lengthBetween($guestName, 1, 200)) {
            $errors['guest_name'] = 'invalid_guest_name';
        }
        if ($email !== '' && !self::isValidEmail($email)) {
            $errors['email'] = 'invalid_email';
        }
        if (mb_strlen($phone) > 40) {
            $errors['phone'] = 'invalid_phone';
        }
        if (mb_strlen($notes) > 2000) {
            $errors['notes'] = 'notes_too_long';
        }
        if ($total !== null && $total !== '' && (self::intOrNull($total) === null || (int) $total < 0 || (int) $total > 100_000_000)) {
            $errors['total_cents'] = 'invalid_total';
        }
        $apartmentId = self::intOrNull($input['apartment_id'] ?? null);
        if ($apartmentId === null) {
            $errors['apartment_id'] = 'invalid_apartment';
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        return $this->withApartmentLock($apartmentId, function (array $apartment) use ($apartmentId, $stay, $guests, $origin, $guestName, $email, $phone, $notes, $total): int {
            if ($guests->exceedsCapacity(self::intOrNull($apartment['max_guests']))) {
                throw new ValidationException(['guests' => 'over_capacity']);
            }
            $this->assertFree($apartmentId, $stay);

            $bookingId = $this->bookings->insertBooking([
                'apartment_id' => $apartmentId,
                'booking_request_id' => null,
                'origin' => $origin,
                'check_in' => $stay->checkIn,
                'check_out' => $stay->checkOut,
                'adults' => $guests->adults,
                'children' => $guests->children,
                'pets' => $guests->pets,
                'guest_name' => $guestName,
                'email' => $email === '' ? null : $email,
                'phone' => $phone === '' ? null : $phone,
                'total_cents' => ($total === null || $total === '') ? null : (int) $total,
                'notes' => $notes === '' ? null : $notes,
            ]);
            $this->audit->record('booking', $bookingId, 'created', 'Prenotazione manuale (' . $origin . ')', null, [
                'status' => 'confirmed', 'origin' => $origin, 'apartment_id' => $apartmentId,
                'check_in' => $stay->checkIn, 'check_out' => $stay->checkOut,
            ]);
            return $bookingId;
        });
    }

    /**
     * Cancels a confirmed booking; its dates become available again. The cancellation
     * email is only a draft (Phase 4) and is never sent here.
     *
     * @throws StateException|ValidationException|BusyException
     */
    public function cancelBooking(int $bookingId, ?string $reason = null): void
    {
        $reason = $reason === null ? null : trim($reason);
        if ($reason !== null && mb_strlen($reason) > 1000) {
            throw new ValidationException(['reason' => 'reason_too_long']);
        }
        $reason = $reason === '' ? null : $reason;

        $booking = $this->bookings->booking($bookingId) ?? throw new StateException('booking_not_found');

        $this->withApartmentLock((int) $booking['apartment_id'], function () use ($bookingId, $reason): void {
            $booking = $this->bookings->booking($bookingId, true) ?? throw new StateException('booking_not_found');
            if ($booking['status'] !== 'confirmed') {
                throw new StateException('booking_not_confirmed');
            }

            $this->bookings->markBookingCancelled($bookingId, $reason);

            if ($booking['booking_request_id'] !== null) {
                $request = $this->bookings->request((int) $booking['booking_request_id'], true);
                if ($request !== null && $request['status'] === 'confirmed') {
                    $this->bookings->setRequestStatus((int) $request['id'], 'cancelled', false);
                    $this->audit->record('booking_request', (int) $request['id'], 'status_changed', 'Richiesta confirmed → cancelled',
                        ['status' => 'confirmed'], ['status' => 'cancelled']);
                }
            }

            $this->audit->record('booking', $bookingId, 'status_changed', 'Prenotazione confirmed → cancelled',
                ['status' => 'confirmed'], ['status' => 'cancelled', 'reason' => $reason]);
        });
    }

    /**
     * Closes dates for an apartment. Refused when a confirmed booking is in the way;
     * overlapping blocks are harmless and allowed.
     *
     * @return int block id
     * @throws ValidationException|ConflictException|StateException|BusyException
     */
    public function createBlock(int $apartmentId, string $startDate, string $endDate, ?string $reason = null): int
    {
        $stay = StayDates::fromStrings($startDate, $endDate, StayDates::MAX_NIGHTS_BLOCK);
        $reason = $reason === null ? null : trim($reason);
        if ($reason !== null && mb_strlen($reason) > 255) {
            throw new ValidationException(['reason' => 'reason_too_long']);
        }
        $reason = $reason === '' ? null : $reason;

        return $this->withApartmentLock($apartmentId, function () use ($apartmentId, $stay, $reason): int {
            $this->assertFree($apartmentId, $stay, includeBlocks: false);

            $blockId = $this->bookings->insertBlock($apartmentId, $stay->checkIn, $stay->checkOut, $reason);
            $this->audit->record('availability_block', $blockId, 'created', 'Blocco disponibilità creato', null, [
                'apartment_id' => $apartmentId, 'start_date' => $stay->checkIn, 'end_date' => $stay->checkOut, 'reason' => $reason,
            ]);
            return $blockId;
        });
    }

    /** @throws StateException|BusyException */
    public function removeBlock(int $blockId): void
    {
        $block = $this->bookings->block($blockId) ?? throw new StateException('block_not_found');

        $this->withApartmentLock((int) $block['apartment_id'], function () use ($blockId): void {
            $block = $this->bookings->block($blockId) ?? throw new StateException('block_not_found');
            $this->bookings->deleteBlock($blockId);
            $this->audit->record('availability_block', $blockId, 'deleted', 'Blocco disponibilità rimosso', [
                'apartment_id' => (int) $block['apartment_id'],
                'start_date' => (string) $block['start_date'],
                'end_date' => (string) $block['end_date'],
                'reason' => $block['reason'],
            ], null);
        });
    }

    // === Internals ==========================================================

    /**
     * Runs $fn in a transaction that first locks the apartment row. $fn receives the locked row.
     *
     * @template T
     * @param callable(array<string, mixed>): T $fn
     * @return T
     */
    private function withApartmentLock(int $apartmentId, callable $fn): mixed
    {
        return $this->transactional(function () use ($apartmentId, $fn) {
            $apartment = $this->apartments->lockForUpdate($apartmentId) ?? throw new StateException('apartment_not_found');
            return $fn($apartment);
        });
    }

    /**
     * @template T
     * @param callable(): T $fn
     * @return T
     */
    private function transactional(callable $fn): mixed
    {
        if ($this->db->inTransaction()) {
            throw new LogicException('BookingService operations cannot be nested in another transaction.');
        }

        // READ COMMITTED: every statement sees the latest committed data, also after the lock wait.
        $this->db->exec('SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED');
        $this->db->exec('SET SESSION innodb_lock_wait_timeout = ' . self::LOCK_WAIT_SECONDS);
        $this->db->beginTransaction();

        try {
            $result = $fn();
            $this->db->commit();
            return $result;
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            if ($e instanceof PDOException && in_array($e->errorInfo[1] ?? 0, [self::MYSQL_LOCK_WAIT_TIMEOUT, self::MYSQL_DEADLOCK], true)) {
                throw new BusyException('The database is busy, retry shortly.', 0, $e);
            }
            throw $e;
        }
    }

    /** @throws ConflictException */
    private function assertFree(int $apartmentId, StayDates $stay, bool $includeBlocks = true): void
    {
        $conflicts = $this->availability->conflicts($apartmentId, $stay, $includeBlocks);
        if ($conflicts !== []) {
            throw new ConflictException($conflicts);
        }
    }

    private static function newReference(): string
    {
        $reference = 'LV-';
        for ($i = 0; $i < 8; $i++) {
            $reference .= self::REFERENCE_ALPHABET[random_int(0, strlen(self::REFERENCE_ALPHABET) - 1)];
        }
        return $reference;
    }

    /** @param array<string, mixed> $input */
    private static function str(array $input, string $key): string
    {
        $value = $input[$key] ?? '';
        return is_string($value) ? $value : '';
    }

    private static function intOrNull(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }
        return is_string($value) && preg_match('/^\d{1,10}$/', $value) ? (int) $value : null;
    }

    private static function lengthBetween(string $value, int $min, int $max): bool
    {
        $length = mb_strlen($value);
        return $length >= $min && $length <= $max;
    }

    private static function isValidEmail(string $email): bool
    {
        return $email !== '' && strlen($email) <= 254 && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
}
