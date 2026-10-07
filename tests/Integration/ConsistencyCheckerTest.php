<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Service\ConsistencyChecker;
use Tests\Support\DatabaseTestCase;

/** The read-only consistency check: silent on healthy data, one finding per kind of problem. */
final class ConsistencyCheckerTest extends DatabaseTestCase
{
    private function problems(): array
    {
        return (new ConsistencyChecker($this->db))->run();
    }

    /** @return list<string> */
    private function codes(): array
    {
        return array_map(static fn (array $p): string => $p['code'], $this->problems());
    }

    private function insertBooking(string $in, string $out, string $status = 'confirmed', ?int $requestId = null, string $slug = 'margherita'): int
    {
        $this->db->prepare(
            "INSERT INTO bookings (apartment_id, booking_request_id, origin, status, check_in, check_out, adults, guest_name, cancelled_at)
             VALUES (?, ?, ?, ?, ?, ?, 2, 'Ospite di prova', " . ($status === 'cancelled' ? 'UTC_TIMESTAMP()' : 'NULL') . ')'
        )->execute([$this->apartmentId($slug), $requestId, $requestId === null ? 'phone' : 'website', $status, $in, $out]);
        return (int) $this->db->lastInsertId();
    }

    private function insertRequest(string $status, string $reference = 'LV-TESTTEST'): int
    {
        $this->db->prepare(
            "INSERT INTO booking_requests (reference, apartment_id, check_in, check_out, adults, first_name, last_name, email, phone, status, privacy_accepted_at, decided_at)
             VALUES (?, ?, '2027-06-10', '2027-06-15', 2, 'Prova', 'Prova', 'prova@example.test', '+39 333 1234567', ?, UTC_TIMESTAMP(), " . ($status === 'pending' ? 'NULL' : 'UTC_TIMESTAMP()') . ')'
        )->execute([$reference, $this->apartmentId(), $status]);
        return (int) $this->db->lastInsertId();
    }

    public function testAnEmptyDatabaseIsConsistent(): void
    {
        self::assertSame([], $this->problems());
    }

    public function testNormalActivityLeavesNothingToReport(): void
    {
        $first = $this->service->createRequest($this->requestInput('2027-06-10', '2027-06-15'));
        $second = $this->service->createRequest($this->requestInput('2027-07-10', '2027-07-15'));
        $third = $this->service->createRequest($this->requestInput('2027-08-10', '2027-08-15'));
        $booking = $this->service->confirmRequest($first['id'])['booking_id'];
        $this->service->confirmRequest($second['id']);
        $this->service->rejectRequest($third['id']);
        $this->service->cancelBooking($booking, 'cambio di programma');
        $this->service->createBlock($this->apartmentId('rosa'), '2027-09-01', '2027-09-05', 'lavori');
        $this->service->createManualBooking($this->manualInput('2027-10-01', '2027-10-05'));

        self::assertSame([], $this->problems());
    }

    public function testBackToBackStaysAreNotAnOverlap(): void
    {
        $this->insertBooking('2027-06-10', '2027-06-15');
        $this->insertBooking('2027-06-15', '2027-06-20'); // check-out day is free for the next guest

        self::assertSame([], $this->problems());
    }

    public function testOverlappingConfirmedBookingsAreFound(): void
    {
        $a = $this->insertBooking('2027-06-10', '2027-06-15');
        $b = $this->insertBooking('2027-06-14', '2027-06-18');
        $this->insertBooking('2027-06-14', '2027-06-18', 'cancelled'); // a cancelled stay occupies nothing
        $this->insertBooking('2027-06-14', '2027-06-18', 'confirmed', null, 'rosa'); // another apartment

        $problems = $this->problems();

        self::assertSame(['bookings_overlap'], $this->codes());
        self::assertSame(["prenotazioni n. $a e n. $b (appartamento " . $this->apartmentId() . ')'], $problems[0]['items']);
    }

    public function testABookingOverABlockIsFound(): void
    {
        $this->db->prepare("INSERT INTO availability_blocks (apartment_id, start_date, end_date, reason) VALUES (?, '2027-06-12', '2027-06-14', 'x')")->execute([$this->apartmentId()]);
        $this->insertBooking('2027-06-10', '2027-06-15');

        self::assertSame(['booking_over_block'], $this->codes());
    }

    public function testAConfirmedRequestWithoutABookingIsFoundAndOneWithABookingIsNot(): void
    {
        $good = $this->insertRequest('confirmed', 'LV-GOODGOOD');
        $this->insertBooking('2027-06-10', '2027-06-15', 'confirmed', $good);
        self::assertSame([], $this->problems());

        $this->insertRequest('confirmed', 'LV-LOSTLOST');

        $problems = $this->problems();
        self::assertSame(['confirmed_request_without_booking'], $this->codes());
        self::assertSame(['richiesta LV-LOSTLOST'], $problems[0]['items']);
    }

    public function testAConfirmedRequestWhoseBookingWasCancelledIsFound(): void
    {
        $request = $this->insertRequest('confirmed');
        $this->insertBooking('2027-06-10', '2027-06-15', 'cancelled', $request);

        self::assertSame(['confirmed_request_without_booking'], $this->codes());
    }

    public function testAnActiveBookingOfARejectedOrPendingRequestIsFound(): void
    {
        foreach (['rejected', 'cancelled', 'pending'] as $i => $status) {
            $this->resetDatabase();
            $request = $this->insertRequest($status);
            $this->insertBooking('2027-06-10', '2027-06-15', 'confirmed', $request);

            self::assertSame(['booking_of_unconfirmed_request'], $this->codes(), $status);
        }
    }

    public function testAnEmailLeftSendingBeyondTheLeaseIsFound(): void
    {
        $this->db->exec("INSERT INTO email_outbox (type, status, locked_at) VALUES ('new_request_admin', 'sending', UTC_TIMESTAMP())");
        self::assertSame([], $this->problems(), 'a message being sent right now is normal');

        $this->db->exec("INSERT INTO email_outbox (type, status, locked_at) VALUES ('new_request_admin', 'sending', UTC_TIMESTAMP() - INTERVAL 20 MINUTE)");
        self::assertSame(['stuck_emails'], $this->codes());
    }

    public function testSeveralProblemsAreAllReportedAndNothingPersonalIsPrinted(): void
    {
        $this->insertBooking('2027-06-10', '2027-06-15');
        $this->insertBooking('2027-06-12', '2027-06-16');
        $this->insertRequest('confirmed', 'LV-LOSTLOST');

        $problems = $this->problems();

        self::assertEqualsCanonicalizing(['bookings_overlap', 'confirmed_request_without_booking'], $this->codes());
        $text = json_encode($problems, JSON_UNESCAPED_UNICODE);
        self::assertStringNotContainsString('Ospite di prova', $text);
        self::assertStringNotContainsString('prova@example.test', $text);
    }

    public function testTheCheckNeverWritesAnything(): void
    {
        $this->insertBooking('2027-06-10', '2027-06-15');
        $this->insertBooking('2027-06-12', '2027-06-16');
        $before = $this->db->query('CHECKSUM TABLE bookings, booking_requests, availability_blocks, email_outbox, audit_log')->fetchAll();

        $this->problems();

        self::assertSame($before, $this->db->query('CHECKSUM TABLE bookings, booking_requests, availability_blocks, email_outbox, audit_log')->fetchAll());
    }

    public function testTheCommandReportsAndSetsTheExitCode(): void
    {
        $run = function (): array {
            exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(dirname(__DIR__, 2) . '/bin/check-consistency.php') . ' 2>&1', $output, $code);
            return [$code, implode("\n", $output)];
        };
        putenv('DB_NAME=' . (getenv('TEST_DB_NAME') ?: 'agriturismo_test'));

        [$ok, $text] = $run();
        self::assertSame(0, $ok);
        self::assertStringContainsString('Nessuna incoerenza trovata', $text);

        $this->insertBooking('2027-06-10', '2027-06-15');
        $this->insertBooking('2027-06-12', '2027-06-16');
        [$bad, $text] = $run();
        self::assertSame(1, $bad);
        self::assertStringContainsString('sovrapposte', $text);
    }
}
