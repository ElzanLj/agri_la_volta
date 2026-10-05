<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Domain\ConflictException;
use App\Domain\StateException;
use App\Domain\ValidationException;
use App\Repository\AvailabilityRepository;
use App\Domain\StayDates;
use Tests\Support\DatabaseTestCase;

/** Public requests (always pending) and the admin decisions on them. TODAY = 2027-01-10. */
final class RequestFlowTest extends DatabaseTestCase
{
    private function requestStatus(int $requestId): string
    {
        return (string) $this->scalar('SELECT status FROM booking_requests WHERE id = ?', [$requestId]);
    }

    private function requestCount(): int
    {
        return (int) $this->scalar('SELECT COUNT(*) FROM booking_requests');
    }

    private function confirmedBookings(): int
    {
        return (int) $this->scalar("SELECT COUNT(*) FROM bookings WHERE status = 'confirmed'");
    }

    /** @param array<string, string> $expectedErrors */
    private function assertRequestRejected(array $input, array $expectedErrors): void
    {
        try {
            $this->service->createRequest($input);
            self::fail('Expected a ValidationException');
        } catch (ValidationException $e) {
            foreach ($expectedErrors as $field => $code) {
                self::assertSame($code, $e->errors()[$field] ?? null, "field $field");
            }
        }
        self::assertSame(0, $this->requestCount(), 'a rejected request must not be stored');
    }

    // --- creating requests -------------------------------------------------

    public function testPublicRequestIsStoredAsPendingNeverConfirmed(): void
    {
        $created = $this->service->createRequest($this->requestInput('2027-06-10', '2027-06-15'));

        self::assertMatchesRegularExpression('/^LV-[A-HJKMNP-Z2-9]{8}$/', $created['reference']);
        $row = $this->row('SELECT * FROM booking_requests WHERE id = ?', [$created['id']]);
        self::assertSame('pending', $row['status']);
        self::assertNull($row['decided_at']);
        self::assertNull($row['quoted_total_cents'], 'no pricing yet: no invented amounts');
        self::assertNotNull($row['privacy_accepted_at']);
        self::assertSame(0, $this->confirmedBookings(), 'a request is not a booking');
    }

    public function testReferencesAreUnique(): void
    {
        $refs = [];
        for ($i = 0; $i < 25; $i++) {
            $refs[] = $this->service->createRequest($this->requestInput('2027-06-10', '2027-06-15'))['reference'];
        }
        self::assertCount(25, array_unique($refs));
    }

    public function testPendingRequestsDoNotBlockEachOther(): void
    {
        $this->service->createRequest($this->requestInput('2027-06-10', '2027-06-15'));
        $this->service->createRequest($this->requestInput('2027-06-12', '2027-06-18'));

        self::assertSame(2, $this->requestCount());
    }

    public function testRequestOnDatesAlreadyConfirmedIsRefusedAtSubmission(): void
    {
        $bookingId = $this->book('2027-06-10', '2027-06-15');

        try {
            $this->service->createRequest($this->requestInput('2027-06-12', '2027-06-14'));
            self::fail('Expected a ConflictException');
        } catch (ConflictException $e) {
            self::assertSame($bookingId, $e->conflicts()[0]['id']);
        }
        self::assertSame(0, $this->requestCount());
    }

    public function testRequestOnBlockedDatesIsRefusedAtSubmission(): void
    {
        $this->service->createBlock($this->apartmentId(), '2027-06-10', '2027-06-15');

        $this->expectException(ConflictException::class);
        $this->service->createRequest($this->requestInput('2027-06-14', '2027-06-16'));
    }

    public function testRequestStartingOnTheCheckOutDayOfAnExistingBookingIsAccepted(): void
    {
        $this->book('2027-06-10', '2027-06-15');
        $created = $this->service->createRequest($this->requestInput('2027-06-15', '2027-06-18'));

        self::assertSame('pending', $this->requestStatus($created['id']));
    }

    public function testServerSideValidationOfDates(): void
    {
        $this->assertRequestRejected($this->requestInput('2027-02-30', '2027-03-05'), ['check_in' => 'invalid_date']);
        $this->assertRequestRejected($this->requestInput('2027-06-10', 'x'), ['check_out' => 'invalid_date']);
        $this->assertRequestRejected($this->requestInput('2027-06-10', '2027-06-10'), ['check_out' => 'check_out_not_after_check_in']);
        $this->assertRequestRejected($this->requestInput('2027-06-15', '2027-06-10'), ['check_out' => 'check_out_not_after_check_in']);
        $this->assertRequestRejected($this->requestInput('2027-01-09', '2027-01-12'), ['check_in' => 'check_in_in_past']);
        $this->assertRequestRejected($this->requestInput('2029-01-11', '2029-01-13'), ['check_in' => 'check_in_too_far']);
        $this->assertRequestRejected($this->requestInput('2027-06-01', '2027-08-01'), ['check_out' => 'stay_too_long']);
    }

    public function testRequestMayStartToday(): void
    {
        $created = $this->service->createRequest($this->requestInput('2027-01-10', '2027-01-12'));
        self::assertSame('pending', $this->requestStatus($created['id']));
    }

    public function testServerSideValidationOfGuestsAndContact(): void
    {
        $this->assertRequestRejected($this->requestInput('2027-06-10', '2027-06-15', ['adults' => 0]), ['adults' => 'invalid_adults']);
        $this->assertRequestRejected($this->requestInput('2027-06-10', '2027-06-15', ['children' => -1]), ['children' => 'invalid_children']);
        $this->assertRequestRejected($this->requestInput('2027-06-10', '2027-06-15', ['pets' => 'many']), ['pets' => 'invalid_pets']);
        $this->assertRequestRejected($this->requestInput('2027-06-10', '2027-06-15', ['first_name' => '  ']), ['first_name' => 'invalid_first_name']);
        $this->assertRequestRejected($this->requestInput('2027-06-10', '2027-06-15', ['last_name' => str_repeat('a', 101)]), ['last_name' => 'invalid_last_name']);
        $this->assertRequestRejected($this->requestInput('2027-06-10', '2027-06-15', ['email' => 'nope']), ['email' => 'invalid_email']);
        $this->assertRequestRejected($this->requestInput('2027-06-10', '2027-06-15', ['email' => "a@example.com\r\nBcc: x@example.com"]), ['email' => 'invalid_email']);
        $this->assertRequestRejected($this->requestInput('2027-06-10', '2027-06-15', ['phone' => 'abc']), ['phone' => 'invalid_phone']);
        $this->assertRequestRejected($this->requestInput('2027-06-10', '2027-06-15', ['notes' => str_repeat('x', 2001)]), ['notes' => 'notes_too_long']);
        $this->assertRequestRejected($this->requestInput('2027-06-10', '2027-06-15', ['locale' => 'fr']), ['locale' => 'invalid_locale']);
        $this->assertRequestRejected($this->requestInput('2027-06-10', '2027-06-15', ['apartment_id' => 'x']), ['apartment_id' => 'invalid_apartment']);
    }

    public function testPrivacyConsentIsMandatory(): void
    {
        $this->assertRequestRejected($this->requestInput('2027-06-10', '2027-06-15', ['privacy_accepted' => false]), ['privacy_accepted' => 'privacy_required']);
        $input = $this->requestInput('2027-06-10', '2027-06-15');
        unset($input['privacy_accepted']);
        $this->assertRequestRejected($input, ['privacy_accepted' => 'privacy_required']);
    }

    public function testAllValidationErrorsAreReportedTogether(): void
    {
        try {
            $this->service->createRequest($this->requestInput('bad', 'bad', ['adults' => 0, 'email' => '', 'privacy_accepted' => false]));
            self::fail('Expected a ValidationException');
        } catch (ValidationException $e) {
            self::assertEqualsCanonicalizing(
                ['check_in', 'check_out', 'adults', 'email', 'privacy_accepted'],
                array_keys($e->errors()),
            );
        }
    }

    public function testInactiveOrOfflineApartmentCannotReceiveRequests(): void
    {
        $this->db->exec("UPDATE apartments SET is_active = 0 WHERE slug = 'margherita'");
        $this->assertNotBookable($this->requestInput('2027-06-10', '2027-06-15'));

        $this->db->exec("UPDATE apartments SET is_active = 1, accepts_online_requests = 0 WHERE slug = 'margherita'");
        $this->assertNotBookable($this->requestInput('2027-06-10', '2027-06-15'));

        $this->assertNotBookable($this->requestInput('2027-06-10', '2027-06-15', ['apartment_id' => 999999]));
    }

    /** @param array<string, mixed> $input */
    private function assertNotBookable(array $input): void
    {
        try {
            $this->service->createRequest($input);
            self::fail('Expected a StateException');
        } catch (StateException $e) {
            self::assertSame('apartment_not_bookable', $e->reason);
        }
        self::assertSame(0, $this->requestCount());
    }

    public function testRequestOverCapacityIsRefusedWhenCapacityIsConfigured(): void
    {
        $this->db->exec("UPDATE apartments SET max_guests = 3 WHERE slug = 'margherita'");

        $this->assertRequestRejected($this->requestInput('2027-06-10', '2027-06-15', ['adults' => 3, 'children' => 1]), ['guests' => 'over_capacity']);
        $ok = $this->service->createRequest($this->requestInput('2027-06-10', '2027-06-15', ['adults' => 3, 'pets' => 2]));
        self::assertSame('pending', $this->requestStatus($ok['id']));
    }

    public function testRequestAuditEntryContainsNoPersonalData(): void
    {
        $created = $this->service->createRequest($this->requestInput('2027-06-10', '2027-06-15', ['notes' => 'allergia ai latticini']));
        $audit = $this->row("SELECT * FROM audit_log WHERE entity_type = 'booking_request' AND entity_id = ?", [$created['id']]);

        self::assertSame('created', $audit['action']);
        $dump = json_encode($audit);
        foreach (['Mario', 'Rossi', 'mario.rossi@example.com', '333 1234567', 'allergia'] as $personal) {
            self::assertStringNotContainsString($personal, $dump);
        }
    }

    // --- admin decisions ---------------------------------------------------

    public function testConfirmCreatesAConfirmedWebsiteBooking(): void
    {
        $created = $this->service->createRequest($this->requestInput('2027-06-10', '2027-06-15', ['adults' => 2, 'children' => 1, 'pets' => 1]));

        $result = $this->service->confirmRequest($created['id']);

        self::assertSame('confirmed', $this->requestStatus($created['id']));
        self::assertNotNull($this->scalar('SELECT decided_at FROM booking_requests WHERE id = ?', [$created['id']]));
        $booking = $this->row('SELECT * FROM bookings WHERE id = ?', [$result['booking_id']]);
        self::assertSame('confirmed', $booking['status']);
        self::assertSame('website', $booking['origin']);
        self::assertSame($created['id'], (int) $booking['booking_request_id']);
        self::assertSame(['2027-06-10', '2027-06-15', 2, 1, 1, 'Mario Rossi'], [
            $booking['check_in'], $booking['check_out'], (int) $booking['adults'], (int) $booking['children'], (int) $booking['pets'], $booking['guest_name'],
        ]);
        self::assertFalse((new AvailabilityRepository($this->db))->conflicts($this->apartmentId(), StayDates::fromStrings('2027-06-10', '2027-06-15')) === []);
    }

    public function testConfirmWritesAuditEntriesWithOldAndNewValues(): void
    {
        $created = $this->service->createRequest($this->requestInput('2027-06-10', '2027-06-15'));
        $result = $this->service->confirmRequest($created['id']);

        $change = $this->row("SELECT * FROM audit_log WHERE entity_type = 'booking_request' AND action = 'status_changed' AND entity_id = ?", [$created['id']]);
        self::assertSame(['status' => 'pending'], json_decode($change['old_values'], true));
        self::assertSame(['status' => 'confirmed', 'booking_id' => $result['booking_id']], json_decode($change['new_values'], true));
        self::assertNotFalse($this->row("SELECT id FROM audit_log WHERE entity_type = 'booking' AND action = 'created' AND entity_id = ?", [$result['booking_id']]));
    }

    public function testConfirmingTwiceDoesNotDuplicateTheBooking(): void
    {
        $created = $this->service->createRequest($this->requestInput('2027-06-10', '2027-06-15'));
        $this->service->confirmRequest($created['id']);

        try {
            $this->service->confirmRequest($created['id']);
            self::fail('Expected a StateException');
        } catch (StateException $e) {
            self::assertSame('request_not_pending', $e->reason);
        }
        self::assertSame(1, $this->confirmedBookings());
    }

    public function testRejectChangesStatusAndFreesNothingBecauseNothingWasHeld(): void
    {
        $created = $this->service->createRequest($this->requestInput('2027-06-10', '2027-06-15'));

        $this->service->rejectRequest($created['id']);

        self::assertSame('rejected', $this->requestStatus($created['id']));
        self::assertNotNull($this->scalar('SELECT decided_at FROM booking_requests WHERE id = ?', [$created['id']]));
        self::assertSame(0, $this->confirmedBookings());
        $audit = $this->row("SELECT * FROM audit_log WHERE entity_type = 'booking_request' AND action = 'status_changed' AND entity_id = ?", [$created['id']]);
        self::assertSame(['status' => 'rejected'], json_decode($audit['new_values'], true));
    }

    public function testRejectedRequestCannotBeConfirmedAndConfirmedCannotBeRejected(): void
    {
        $rejected = $this->service->createRequest($this->requestInput('2027-06-10', '2027-06-15'));
        $this->service->rejectRequest($rejected['id']);
        $confirmed = $this->service->createRequest($this->requestInput('2027-07-10', '2027-07-15'));
        $this->service->confirmRequest($confirmed['id']);

        foreach ([fn () => $this->service->confirmRequest($rejected['id']), fn () => $this->service->rejectRequest($confirmed['id']), fn () => $this->service->rejectRequest($rejected['id'])] as $attempt) {
            try {
                $attempt();
                self::fail('Expected a StateException');
            } catch (StateException $e) {
                self::assertSame('request_not_pending', $e->reason);
            }
        }
        self::assertSame('rejected', $this->requestStatus($rejected['id']));
        self::assertSame('confirmed', $this->requestStatus($confirmed['id']));
        self::assertSame(1, $this->confirmedBookings());
    }

    public function testUnknownRequestIsReported(): void
    {
        foreach ([fn () => $this->service->confirmRequest(999999), fn () => $this->service->rejectRequest(999999)] as $attempt) {
            try {
                $attempt();
                self::fail('Expected a StateException');
            } catch (StateException $e) {
                self::assertSame('request_not_found', $e->reason);
            }
        }
    }

    public function testConfirmIsRefusedWhenDatesWereTakenMeanwhileAndRequestStaysPending(): void
    {
        $created = $this->service->createRequest($this->requestInput('2027-06-10', '2027-06-15'));
        $this->book('2027-06-12', '2027-06-14'); // a phone booking arrives after the request

        try {
            $this->service->confirmRequest($created['id']);
            self::fail('Expected a ConflictException');
        } catch (ConflictException $e) {
            self::assertSame('booking', $e->conflicts()[0]['type']);
        }

        self::assertSame('pending', $this->requestStatus($created['id']));
        self::assertSame(1, $this->confirmedBookings());
        self::assertSame(0, $this->overlapViolations());
    }

    public function testConfirmIsRefusedWhenABlockWasCreatedMeanwhile(): void
    {
        $created = $this->service->createRequest($this->requestInput('2027-06-10', '2027-06-15'));
        $this->service->createBlock($this->apartmentId(), '2027-06-14', '2027-06-20');

        $this->expectException(ConflictException::class);
        $this->service->confirmRequest($created['id']);
    }

    public function testOnlyOneOfTwoOverlappingPendingRequestsCanBeConfirmed(): void
    {
        $first = $this->service->createRequest($this->requestInput('2027-06-10', '2027-06-15'));
        $second = $this->service->createRequest($this->requestInput('2027-06-12', '2027-06-17', ['first_name' => 'Anna']));

        $this->service->confirmRequest($first['id']);
        try {
            $this->service->confirmRequest($second['id']);
            self::fail('Expected a ConflictException');
        } catch (ConflictException) {
        }

        self::assertSame('pending', $this->requestStatus($second['id']));
        $this->service->rejectRequest($second['id']); // the admin can still decline it
        self::assertSame('rejected', $this->requestStatus($second['id']));
        self::assertSame(0, $this->overlapViolations());
    }

    public function testConsecutiveRequestsCanBothBeConfirmed(): void
    {
        $first = $this->service->createRequest($this->requestInput('2027-06-10', '2027-06-15'));
        $second = $this->service->createRequest($this->requestInput('2027-06-15', '2027-06-20', ['first_name' => 'Anna']));

        $this->service->confirmRequest($first['id']);
        $this->service->confirmRequest($second['id']);

        self::assertSame(2, $this->confirmedBookings());
        self::assertSame(0, $this->overlapViolations());
    }
}
