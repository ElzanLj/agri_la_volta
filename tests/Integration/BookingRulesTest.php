<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Domain\ConflictException;
use App\Domain\StateException;
use App\Domain\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\DatabaseTestCase;

/** Manual bookings: overlap rules, origins, validation. Reference interval: [10 June, 15 June) 2027. */
final class BookingRulesTest extends DatabaseTestCase
{
    private function bookingCount(): int
    {
        return (int) $this->scalar("SELECT COUNT(*) FROM bookings WHERE status = 'confirmed'");
    }

    /** @return array<string, array{string, string}> */
    public static function overlappingStays(): array
    {
        return [
            'identical' => ['2027-06-10', '2027-06-15'],
            'partial: overlaps the end' => ['2027-06-12', '2027-06-18'],
            'partial: overlaps the start' => ['2027-06-08', '2027-06-12'],
            'partial: last night only' => ['2027-06-14', '2027-06-16'],
            'partial: first night only' => ['2027-06-09', '2027-06-11'],
            'total: inside' => ['2027-06-11', '2027-06-13'],
            'total: contains' => ['2027-06-05', '2027-06-20'],
            'one night inside' => ['2027-06-12', '2027-06-13'],
        ];
    }

    #[DataProvider('overlappingStays')]
    public function testOverlappingBookingIsRefused(string $in, string $out): void
    {
        $existing = $this->book('2027-06-10', '2027-06-15');

        try {
            $this->book($in, $out);
            self::fail('Expected a ConflictException');
        } catch (ConflictException $e) {
            self::assertSame([['type' => 'booking', 'id' => $existing, 'start_date' => '2027-06-10', 'end_date' => '2027-06-15']], $e->conflicts());
        }

        self::assertSame(1, $this->bookingCount());
        self::assertSame(0, $this->overlapViolations());
    }

    public function testCheckOutAndCheckInOnTheSameDayAreCompatible(): void
    {
        $this->book('2027-06-10', '2027-06-15');
        $next = $this->book('2027-06-15', '2027-06-18');   // arrives the day the previous guest leaves
        $before = $this->book('2027-06-08', '2027-06-10'); // leaves the day the next guest arrives

        self::assertGreaterThan(0, $next);
        self::assertGreaterThan(0, $before);
        self::assertSame(3, $this->bookingCount());
        self::assertSame(0, $this->overlapViolations());
    }

    public function testConsecutiveSingleNightStays(): void
    {
        foreach (['2027-06-10', '2027-06-11', '2027-06-12', '2027-06-13'] as $night) {
            $this->book($night, date('Y-m-d', strtotime($night . ' +1 day')));
        }

        self::assertSame(4, $this->bookingCount());
        self::assertSame(0, $this->overlapViolations());
    }

    public function testSameDatesOnAnotherApartmentAreAllowed(): void
    {
        $this->book('2027-06-10', '2027-06-15', 'margherita');
        $this->book('2027-06-10', '2027-06-15', 'girasole');

        self::assertSame(2, $this->bookingCount());
    }

    public function testAdjacentBookingAfterABlockIsAllowedButOverlapIsNot(): void
    {
        $this->service->createBlock($this->apartmentId(), '2027-06-10', '2027-06-15');

        $this->book('2027-06-15', '2027-06-17'); // starts on the block's end day
        $this->expectException(ConflictException::class);
        $this->book('2027-06-14', '2027-06-16');
    }

    public function testBookingOverABlockReportsTheBlock(): void
    {
        $blockId = $this->service->createBlock($this->apartmentId(), '2027-06-10', '2027-06-15');

        try {
            $this->book('2027-06-12', '2027-06-14');
            self::fail('Expected a ConflictException');
        } catch (ConflictException $e) {
            self::assertSame('block', $e->conflicts()[0]['type']);
            self::assertSame($blockId, $e->conflicts()[0]['id']);
        }
        self::assertSame(0, $this->bookingCount());
    }

    /** @return array<string, array{string}> */
    public static function validOrigins(): array
    {
        return ['phone' => ['phone'], 'email' => ['email'], 'agency' => ['agency'], 'novasol' => ['novasol'], 'other' => ['other']];
    }

    #[DataProvider('validOrigins')]
    public function testManualBookingAcceptsEachConfiguredOrigin(string $origin): void
    {
        $id = $this->service->createManualBooking($this->manualInput('2027-06-10', '2027-06-15', ['origin' => $origin]));

        $row = $this->row('SELECT origin, status, booking_request_id FROM bookings WHERE id = ?', [$id]);
        self::assertSame($origin, $row['origin']);
        self::assertSame('confirmed', $row['status']);
        self::assertNull($row['booking_request_id']);
    }

    /** @return array<string, array{string}> */
    public static function invalidOrigins(): array
    {
        return ['website is reserved for approved requests' => ['website'], 'unknown' => ['airbnb'], 'empty' => [''], 'case differs' => ['Phone']];
    }

    #[DataProvider('invalidOrigins')]
    public function testManualBookingRejectsOtherOrigins(string $origin): void
    {
        try {
            $this->service->createManualBooking($this->manualInput('2027-06-10', '2027-06-15', ['origin' => $origin]));
            self::fail('Expected a ValidationException');
        } catch (ValidationException $e) {
            self::assertSame('invalid_origin', $e->errors()['origin']);
        }
        self::assertSame(0, $this->bookingCount());
    }

    public function testInvalidDatesAreRefusedAndNothingIsStored(): void
    {
        $cases = [
            ['2027-02-30', '2027-03-05', 'check_in'],
            ['2027-06-10', 'not-a-date', 'check_out'],
            ['2027-06-10', '2027-06-10', 'check_out'],
            ['2027-06-15', '2027-06-10', 'check_out'],
            ['2027-06-01', '2027-08-15', 'check_out'],
        ];
        foreach ($cases as [$in, $out, $field]) {
            try {
                $this->book($in, $out);
                self::fail("Expected a ValidationException for $in -> $out");
            } catch (ValidationException $e) {
                self::assertArrayHasKey($field, $e->errors(), "$in -> $out");
            }
        }
        self::assertSame(0, $this->bookingCount());
        self::assertSame(0, (int) $this->scalar('SELECT COUNT(*) FROM audit_log'));
    }

    public function testAdminMayRecordPastStays(): void
    {
        $id = $this->book('2026-08-01', '2026-08-05');
        self::assertGreaterThan(0, $id);
    }

    public function testManualBookingValidatesGuestAndContactFields(): void
    {
        $bad = [
            'guest_name' => '   ',
            'email' => 'not-an-email',
            'adults' => 0,
            'total_cents' => -5,
            'apartment_id' => 'abc',
        ];
        try {
            $this->service->createManualBooking($this->manualInput('2027-06-10', '2027-06-15', $bad));
            self::fail('Expected a ValidationException');
        } catch (ValidationException $e) {
            self::assertEqualsCanonicalizing(['guest_name', 'email', 'adults', 'total_cents', 'apartment_id'], array_keys($e->errors()));
        }
    }

    public function testUnknownApartmentIsRefused(): void
    {
        try {
            $this->service->createManualBooking($this->manualInput('2027-06-10', '2027-06-15', ['apartment_id' => 999999]));
            self::fail('Expected a StateException');
        } catch (StateException $e) {
            self::assertSame('apartment_not_found', $e->reason);
        }
    }

    public function testCapacityIsEnforcedOnlyWhenConfigured(): void
    {
        // Not configured (NULL): no limit applies.
        $this->service->createManualBooking($this->manualInput('2027-06-10', '2027-06-15', ['adults' => 8, 'children' => 4]));

        $this->db->exec("UPDATE apartments SET max_guests = 4 WHERE slug = 'margherita'");
        $this->service->createManualBooking($this->manualInput('2027-07-10', '2027-07-15', ['adults' => 2, 'children' => 2, 'pets' => 2]));

        $this->expectException(ValidationException::class);
        $this->service->createManualBooking($this->manualInput('2027-08-10', '2027-08-15', ['adults' => 3, 'children' => 2]));
    }

    public function testManualBookingStoresOptionalFieldsAsNull(): void
    {
        $id = $this->book('2027-06-10', '2027-06-15');
        $row = $this->row('SELECT email, phone, total_cents, notes FROM bookings WHERE id = ?', [$id]);

        self::assertSame([null, null, null, null], array_values($row));
    }

    public function testManualBookingWritesAnAuditEntry(): void
    {
        $id = $this->book('2027-06-10', '2027-06-15');
        $audit = $this->row("SELECT * FROM audit_log WHERE entity_type = 'booking' AND entity_id = ?", [$id]);

        self::assertSame('created', $audit['action']);
        self::assertNull($audit['old_values']);
        self::assertSame('confirmed', json_decode($audit['new_values'], true)['status']);
        self::assertSame('phone', json_decode($audit['new_values'], true)['origin']);
    }
}
