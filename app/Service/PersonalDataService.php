<?php

declare(strict_types=1);

namespace App\Service;

use App\Support\AuditLog;
use PDO;

/**
 * Technical means for the owner to honour data-subject requests and a retention period
 * (SPEC §32): export what is held about a person, anonymise it, or anonymise old stays.
 *
 * Anonymising keeps the stay itself (apartment, dates, number of guests, price): the dates stay
 * occupied and the statistics stay coherent, but nothing identifies the person any more.
 * Rows are never deleted. Pending requests and stays that have not ended yet are left alone
 * unless explicitly included, because the owner still has to answer or host those guests.
 *
 * Every operation is a dry run unless $apply is true.
 */
final class PersonalDataService
{
    public const ANONYMOUS_NAME = '[eliminato]';

    public function __construct(private PDO $db, private ?string $today = null)
    {
    }

    /**
     * Everything stored about a person, found by e-mail address (case-insensitive).
     *
     * @return array{requests: list<array<string, mixed>>, bookings: list<array<string, mixed>>, emails: list<array<string, mixed>>}
     */
    public function export(string $email): array
    {
        $requests = $this->fetch('SELECT r.id, r.reference, a.name AS apartment, r.check_in, r.check_out, r.adults, r.children, r.pets,
                    r.first_name, r.last_name, r.email, r.phone, r.notes, r.locale, r.quoted_total_cents, r.status,
                    r.privacy_accepted_at, r.decided_at, r.created_at
                FROM booking_requests r JOIN apartments a ON a.id = r.apartment_id
                WHERE r.email = ? ORDER BY r.id', [$email]);

        $bookings = $this->fetch('SELECT b.id, b.booking_request_id, a.name AS apartment, b.origin, b.status, b.check_in, b.check_out,
                    b.adults, b.children, b.pets, b.guest_name, b.email, b.phone, b.total_cents, b.notes,
                    b.cancelled_at, b.cancellation_reason, b.created_at
                FROM bookings b JOIN apartments a ON a.id = b.apartment_id
                WHERE b.email = ? OR b.booking_request_id IN (SELECT id FROM booking_requests WHERE email = ?)
                ORDER BY b.id', [$email, $email]);

        $emails = $this->fetch('SELECT o.id, o.type, o.status, o.subject, o.body, o.sent_at, o.created_at
                FROM email_outbox o
                WHERE o.booking_request_id IN (SELECT id FROM booking_requests WHERE email = ?)
                   OR o.booking_id IN (SELECT id FROM bookings WHERE email = ? OR booking_request_id IN (SELECT id FROM booking_requests WHERE email = ?))
                ORDER BY o.id', [$email, $email, $email]);

        return ['requests' => $requests, 'bookings' => $bookings, 'emails' => $emails];
    }

    /**
     * Anonymises the data of one person.
     *
     * @return array{requests: int, bookings: int, emails: int, skipped: list<string>}
     */
    public function erase(string $email, bool $apply, bool $includeActive = false): array
    {
        $requestIds = array_map('intval', $this->column('SELECT id FROM booking_requests WHERE email = ?', [$email]));
        $bookingIds = array_map('intval', $this->column(
            'SELECT id FROM bookings WHERE email = ? OR booking_request_id IN (SELECT id FROM booking_requests WHERE email = ?)',
            [$email, $email],
        ));
        return $this->anonymise($requestIds, $bookingIds, $apply, $includeActive, 'erase');
    }

    /**
     * Anonymises the personal data of stays that ended more than $months months ago.
     *
     * @return array{requests: int, bookings: int, emails: int, skipped: list<string>}
     */
    public function purgeOlderThan(int $months, bool $apply): array
    {
        if ($months < 1) {
            throw new \InvalidArgumentException('The retention period must be at least 1 month.');
        }
        $limit = (new \DateTimeImmutable($this->today ?? 'today'))->modify('-' . $months . ' months')->format('Y-m-d');

        $requestIds = array_map('intval', $this->column(
            "SELECT id FROM booking_requests WHERE check_out < ? AND status <> 'pending' AND first_name <> ?",
            [$limit, self::ANONYMOUS_NAME],
        ));
        $bookingIds = array_map('intval', $this->column(
            'SELECT id FROM bookings WHERE check_out < ? AND guest_name <> ?',
            [$limit, self::ANONYMOUS_NAME],
        ));
        return $this->anonymise($requestIds, $bookingIds, $apply, false, 'retention');
    }

    /**
     * @param list<int> $requestIds
     * @param list<int> $bookingIds
     * @return array{requests: int, bookings: int, emails: int, skipped: list<string>}
     */
    private function anonymise(array $requestIds, array $bookingIds, bool $apply, bool $includeActive, string $reason): array
    {
        $today = (new \DateTimeImmutable($this->today ?? 'today'))->format('Y-m-d');
        $skipped = [];

        // Pending requests await an answer; confirmed stays not yet over still need contacting the guest.
        if (!$includeActive) {
            foreach ($this->fetch('SELECT id, reference FROM booking_requests WHERE id IN (' . self::in($requestIds) . ") AND status = 'pending'", $requestIds) as $row) {
                $skipped[] = 'richiesta ' . $row['reference'] . ': ancora in attesa di risposta';
                $requestIds = array_values(array_diff($requestIds, [(int) $row['id']]));
            }
            foreach ($this->fetch('SELECT id FROM bookings WHERE id IN (' . self::in($bookingIds) . ") AND status = 'confirmed' AND check_out >= ?", [...$bookingIds, $today]) as $row) {
                $skipped[] = 'prenotazione n. ' . $row['id'] . ': il soggiorno non è ancora finito';
                $bookingIds = array_values(array_diff($bookingIds, [(int) $row['id']]));
            }
        }

        $emailIds = $this->column(
            'SELECT id FROM email_outbox WHERE (subject IS NOT NULL OR body IS NOT NULL) AND (booking_request_id IN (' . self::in($requestIds) . ') OR booking_id IN (' . self::in($bookingIds) . '))',
            [...$requestIds, ...$bookingIds],
        );
        $counts = ['requests' => count($requestIds), 'bookings' => count($bookingIds), 'emails' => count($emailIds), 'skipped' => $skipped];
        if (!$apply || ($requestIds === [] && $bookingIds === [])) {
            return $counts;
        }

        $this->db->beginTransaction();
        try {
            if ($requestIds !== []) {
                $this->db->prepare(
                    "UPDATE booking_requests SET first_name = ?, last_name = ?, email = CONCAT('eliminato-', id, '@invalid.invalid'), phone = '-', notes = NULL WHERE id IN (" . self::in($requestIds) . ')'
                )->execute([self::ANONYMOUS_NAME, self::ANONYMOUS_NAME, ...$requestIds]);
            }
            if ($bookingIds !== []) {
                $this->db->prepare(
                    'UPDATE bookings SET guest_name = ?, email = NULL, phone = NULL, notes = NULL, cancellation_reason = NULL WHERE id IN (' . self::in($bookingIds) . ')'
                )->execute([self::ANONYMOUS_NAME, ...$bookingIds]);
            }
            if ($emailIds !== []) {
                $this->db->prepare('UPDATE email_outbox SET subject = NULL, body = NULL WHERE id IN (' . self::in($emailIds) . ')')->execute($emailIds);
            }
            // The audit trail records that it happened and how much, never who.
            (new AuditLog($this->db))->record('personal_data', null, 'anonymized', 'Dati personali anonimizzati (' . $reason . ')', null, [
                'requests' => count($requestIds), 'bookings' => count($bookingIds), 'emails' => count($emailIds),
            ]);
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
        return $counts;
    }

    /** "?, ?, ?" for an IN list; "NULL" for an empty list so the query stays valid and matches nothing. @param list<mixed> $values */
    private static function in(array $values): string
    {
        return $values === [] ? 'NULL' : implode(', ', array_fill(0, count($values), '?'));
    }

    /** @param list<mixed> $params @return list<array<string, mixed>> */
    private function fetch(string $sql, array $params): array
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** @param list<mixed> $params @return list<mixed> */
    private function column(string $sql, array $params): array
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}
