<?php

declare(strict_types=1);

namespace Tests\Integration;

use Tests\Support\DatabaseTestCase;

/**
 * Migration 0007: the database itself refuses impossible states, and the migration refuses to start
 * when existing rows would already break the new rules.
 */
final class DataConstraintsTest extends DatabaseTestCase
{
    private function expectRefusal(callable $statement, string $constraint): void
    {
        try {
            $statement();
            self::fail("The database accepted a row that breaks $constraint.");
        } catch (\PDOException $e) {
            self::assertSame(23000, (int) $e->getCode() ?: (int) ($e->errorInfo[0] ?? 0) ?: 23000);
            self::assertStringContainsString($constraint, $e->getMessage());
        }
    }

    private function insertBooking(string $status, string $cancelledAt, int $adults = 2, int $children = 0, int $pets = 0): void
    {
        $this->db->prepare(
            'INSERT INTO bookings (apartment_id, origin, status, check_in, check_out, adults, children, pets, guest_name, cancelled_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ' . $cancelledAt . ')'
        )->execute([$this->apartmentId(), 'phone', $status, '2027-06-10', '2027-06-15', $adults, $children, $pets, 'Prova']);
    }

    private function insertRequest(string $status, string $decidedAt, int $adults = 2, int $children = 0, int $pets = 0): void
    {
        $this->db->prepare(
            'INSERT INTO booking_requests (reference, apartment_id, check_in, check_out, adults, children, pets, first_name, last_name, email, phone, status, privacy_accepted_at, decided_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP(), ' . $decidedAt . ')'
        )->execute(['LV-' . strtoupper(bin2hex(random_bytes(4))), $this->apartmentId(), '2027-06-10', '2027-06-15', $adults, $children, $pets, 'Prova', 'Prova', 'p@example.test', '+39 333 1234567', $status]);
    }

    public function testACancelledBookingNeedsItsDateAndAnActiveOneMustNotHaveIt(): void
    {
        $this->insertBooking('cancelled', 'UTC_TIMESTAMP()'); // valid
        $this->insertBooking('confirmed', 'NULL'); // valid
        $this->expectRefusal(fn () => $this->insertBooking('cancelled', 'NULL'), 'chk_bookings_cancelled_at');
        $this->expectRefusal(fn () => $this->insertBooking('confirmed', 'UTC_TIMESTAMP()'), 'chk_bookings_cancelled_at');
    }

    public function testBookingGuestCountsStayWithinTheLimits(): void
    {
        $this->insertBooking('confirmed', 'NULL', 20, 20, 10); // valid, at the limits
        foreach ([[0, 0, 0], [21, 0, 0], [2, 21, 0], [2, 0, 11]] as [$adults, $children, $pets]) {
            $this->expectRefusal(fn () => $this->insertBooking('confirmed', 'NULL', $adults, $children, $pets), 'chk_bookings_guests');
        }
    }

    public function testARequestIsDecidedExactlyWhenItIsNotPending(): void
    {
        $this->insertRequest('pending', 'NULL'); // valid
        foreach (['confirmed', 'rejected', 'cancelled'] as $status) {
            $this->insertRequest($status, 'UTC_TIMESTAMP()'); // valid
            $this->expectRefusal(fn () => $this->insertRequest($status, 'NULL'), 'chk_booking_requests_decided_at');
        }
        $this->expectRefusal(fn () => $this->insertRequest('pending', 'UTC_TIMESTAMP()'), 'chk_booking_requests_decided_at');
    }

    public function testRequestGuestCountsStayWithinTheLimits(): void
    {
        $this->insertRequest('pending', 'NULL', 20, 20, 10);
        foreach ([[0, 0, 0], [21, 0, 0], [2, 21, 0], [2, 0, 11]] as [$adults, $children, $pets]) {
            $this->expectRefusal(fn () => $this->insertRequest('pending', 'NULL', $adults, $children, $pets), 'chk_booking_requests_'); // 0 adults is also covered by the older rule
        }
    }

    public function testAnEmailMarkedSentNeedsItsSendDate(): void
    {
        $this->db->exec("INSERT INTO email_outbox (type, status, sent_at) VALUES ('new_request_admin', 'sent', UTC_TIMESTAMP())"); // valid
        $this->db->exec("INSERT INTO email_outbox (type, status) VALUES ('new_request_admin', 'pending')"); // valid
        $this->expectRefusal(fn () => $this->db->exec("INSERT INTO email_outbox (type, status) VALUES ('new_request_admin', 'sent')"), 'chk_email_outbox_sent_at');
    }

    public function testTheNormalFlowsStillWorkWithTheConstraints(): void
    {
        $request = $this->service->createRequest($this->requestInput('2027-06-10', '2027-06-15'));
        $second = $this->service->createRequest($this->requestInput('2027-07-10', '2027-07-15'));
        $booking = $this->service->confirmRequest($request['id'])['booking_id'];
        $this->service->rejectRequest($second['id']);
        $this->service->cancelBooking($booking, 'prova');
        $this->service->createManualBooking($this->manualInput('2027-08-01', '2027-08-05', ['adults' => 20, 'children' => 20, 'pets' => 10]));

        self::assertSame('cancelled', $this->scalar('SELECT status FROM booking_requests WHERE id = ?', [$request['id']]));
        self::assertNotNull($this->scalar('SELECT decided_at FROM booking_requests WHERE id = ?', [$request['id']]));
    }

    public function testThePrivacyToolsStillWorkWithTheConstraints(): void
    {
        $request = $this->service->createRequest($this->requestInput('2027-06-10', '2027-06-15', ['email' => 'chi@example.test']));
        $this->service->confirmRequest($request['id']);

        $result = (new \App\Service\PersonalDataService($this->db, '2030-01-01'))->erase('chi@example.test', true);

        self::assertSame(1, $result['requests']);
    }
}
