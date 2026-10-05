<?php

declare(strict_types=1);

namespace Tests\Support;

use App\App;
use App\Service\BookingService;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Base class for tests that use the (dedicated) test database. Every test starts from
 * the seeded apartments with no requests, bookings, blocks or audit rows.
 */
abstract class DatabaseTestCase extends TestCase
{
    /** Fixed "today" for the public-request date rules. */
    protected const TODAY = '2027-01-10';

    protected PDO $db;
    protected BookingService $service;

    protected function setUp(): void
    {
        $this->db = App::current()->db();
        $this->resetDatabase();
        $this->service = new BookingService($this->db, null, static fn (): string => self::TODAY);
    }

    /** Empties all booking data and restores the apartments to their seeded state. */
    protected function resetDatabase(): void
    {
        foreach (['audit_log', 'bookings', 'booking_requests', 'availability_blocks', 'seasonal_rates', 'pricing_rules', 'rate_limit_hits'] as $table) {
            $this->db->exec("DELETE FROM {$table}");
        }
        $this->db->exec('UPDATE apartments SET is_active = 1, accepts_online_requests = 1, max_guests = NULL, max_children = NULL, max_pets = NULL');
    }

    protected function apartmentId(string $slug = 'margherita'): int
    {
        $stmt = $this->db->prepare('SELECT id FROM apartments WHERE slug = ?');
        $stmt->execute([$slug]);
        return (int) $stmt->fetchColumn();
    }

    /** @param array<string, mixed> $overrides @return array<string, mixed> */
    protected function requestInput(string $checkIn, string $checkOut, array $overrides = []): array
    {
        return $overrides + [
            'apartment_id' => $this->apartmentId(),
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'adults' => 2,
            'children' => 0,
            'pets' => 0,
            'first_name' => 'Mario',
            'last_name' => 'Rossi',
            'email' => 'mario.rossi@example.com',
            'phone' => '+39 333 1234567',
            'notes' => '',
            'locale' => 'it',
            'privacy_accepted' => true,
        ];
    }

    /** @param array<string, mixed> $overrides @return array<string, mixed> */
    protected function manualInput(string $checkIn, string $checkOut, array $overrides = []): array
    {
        return $overrides + [
            'apartment_id' => $this->apartmentId(),
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'adults' => 2,
            'origin' => 'phone',
            'guest_name' => 'Cliente Telefono',
        ];
    }

    /** Creates a confirmed manual booking and returns its id. */
    protected function book(string $checkIn, string $checkOut, string $slug = 'margherita'): int
    {
        return $this->service->createManualBooking($this->manualInput($checkIn, $checkOut, ['apartment_id' => $this->apartmentId($slug)]));
    }

    /** Number of overlapping pairs among confirmed bookings of the same apartment, plus bookings overlapping blocks. */
    protected function overlapViolations(): int
    {
        return Invariants::overlapViolations($this->db);
    }

    /** @return array<string, mixed>|false */
    protected function row(string $sql, array $params = []): array|false
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch();
    }

    protected function scalar(string $sql, array $params = []): mixed
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }
}
