<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Domain\ConflictException;
use App\Domain\StateException;
use App\Domain\StayDates;
use App\Domain\ValidationException;
use App\Repository\AvailabilityRepository;
use PDOException;
use Tests\Support\DatabaseTestCase;

final class BlocksCancellationTest extends DatabaseTestCase
{
    private function isFree(string $in, string $out, string $slug = 'margherita'): bool
    {
        return (new AvailabilityRepository($this->db))->conflicts($this->apartmentId($slug), StayDates::fromStrings($in, $out)) === [];
    }

    // --- blocks ------------------------------------------------------------

    public function testBlockOverAConfirmedBookingIsRefusedWithTheConflict(): void
    {
        $bookingId = $this->book('2027-06-10', '2027-06-15');

        try {
            $this->service->createBlock($this->apartmentId(), '2027-06-12', '2027-06-20');
            self::fail('Expected a ConflictException');
        } catch (ConflictException $e) {
            self::assertSame([['type' => 'booking', 'id' => $bookingId, 'start_date' => '2027-06-10', 'end_date' => '2027-06-15']], $e->conflicts());
        }
        self::assertSame(0, (int) $this->scalar('SELECT COUNT(*) FROM availability_blocks'));
        self::assertSame(0, $this->overlapViolations());
    }

    public function testBlockAdjacentToABookingIsAllowed(): void
    {
        $this->book('2027-06-10', '2027-06-15');

        $this->service->createBlock($this->apartmentId(), '2027-06-15', '2027-06-20'); // starts on check-out day
        $this->service->createBlock($this->apartmentId(), '2027-06-01', '2027-06-10'); // ends on check-in day

        self::assertSame(2, (int) $this->scalar('SELECT COUNT(*) FROM availability_blocks'));
        self::assertSame(0, $this->overlapViolations());
    }

    public function testOverlappingBlocksAreHarmlessAndAllowed(): void
    {
        $this->service->createBlock($this->apartmentId(), '2027-06-10', '2027-06-15');
        $this->service->createBlock($this->apartmentId(), '2027-06-12', '2027-06-20');

        self::assertSame(2, (int) $this->scalar('SELECT COUNT(*) FROM availability_blocks'));
    }

    public function testBlockOnOneApartmentDoesNotAffectAnother(): void
    {
        $this->service->createBlock($this->apartmentId('rosa'), '2027-06-10', '2027-06-15');

        self::assertFalse($this->isFree('2027-06-10', '2027-06-15', 'rosa'));
        self::assertTrue($this->isFree('2027-06-10', '2027-06-15', 'margherita'));
    }

    public function testBlockValidation(): void
    {
        $cases = [['2027-06-15', '2027-06-10'], ['2027-06-10', '2027-06-10'], ['x', '2027-06-10'], ['2027-01-01', '2028-01-03']];
        foreach ($cases as [$start, $end]) {
            try {
                $this->service->createBlock($this->apartmentId(), $start, $end);
                self::fail("Expected a ValidationException for $start -> $end");
            } catch (ValidationException) {
            }
        }
        $this->expectException(ValidationException::class);
        $this->service->createBlock($this->apartmentId(), '2027-06-10', '2027-06-15', str_repeat('x', 256));
    }

    public function testBlockOnUnknownApartmentIsRefused(): void
    {
        $this->expectException(StateException::class);
        $this->service->createBlock(999999, '2027-06-10', '2027-06-15');
    }

    public function testRemovingABlockFreesTheDatesAndIsAudited(): void
    {
        $blockId = $this->service->createBlock($this->apartmentId(), '2027-06-10', '2027-06-15', 'manutenzione');

        $this->service->removeBlock($blockId);

        self::assertTrue($this->isFree('2027-06-10', '2027-06-15'));
        self::assertFalse($this->row('SELECT id FROM availability_blocks WHERE id = ?', [$blockId]));
        $created = $this->row("SELECT * FROM audit_log WHERE entity_type = 'availability_block' AND action = 'created' AND entity_id = ?", [$blockId]);
        $deleted = $this->row("SELECT * FROM audit_log WHERE entity_type = 'availability_block' AND action = 'deleted' AND entity_id = ?", [$blockId]);
        self::assertSame('manutenzione', json_decode($created['new_values'], true)['reason']);
        self::assertSame('2027-06-10', json_decode($deleted['old_values'], true)['start_date']);
        self::assertNull($deleted['new_values']);
    }

    public function testRemovingAnUnknownBlockIsReported(): void
    {
        try {
            $this->service->removeBlock(999999);
            self::fail('Expected a StateException');
        } catch (StateException $e) {
            self::assertSame('block_not_found', $e->reason);
        }
    }

    public function testAfterRemovingABlockABookingFitsAgain(): void
    {
        $blockId = $this->service->createBlock($this->apartmentId(), '2027-06-10', '2027-06-15');
        $this->service->removeBlock($blockId);

        self::assertGreaterThan(0, $this->book('2027-06-10', '2027-06-15'));
    }

    // --- cancellation ------------------------------------------------------

    public function testCancellationMarksTheBookingAndReopensTheDates(): void
    {
        $id = $this->book('2027-06-10', '2027-06-15');
        self::assertFalse($this->isFree('2027-06-10', '2027-06-15'));

        $this->service->cancelBooking($id, 'Il cliente ha annullato');

        $row = $this->row('SELECT status, cancelled_at, cancellation_reason FROM bookings WHERE id = ?', [$id]);
        self::assertSame('cancelled', $row['status']);
        self::assertNotNull($row['cancelled_at']);
        self::assertSame('Il cliente ha annullato', $row['cancellation_reason']);
        self::assertTrue($this->isFree('2027-06-10', '2027-06-15'));
        self::assertSame(1, (int) $this->scalar('SELECT COUNT(*) FROM bookings'), 'history is kept, the row is not deleted');
    }

    public function testDatesFreedByCancellationCanBeBookedAgain(): void
    {
        $id = $this->book('2027-06-10', '2027-06-15');
        $this->service->cancelBooking($id);

        $new = $this->book('2027-06-10', '2027-06-15');

        self::assertNotSame($id, $new);
        self::assertSame(1, (int) $this->scalar("SELECT COUNT(*) FROM bookings WHERE status = 'confirmed'"));
        self::assertSame(0, $this->overlapViolations());
    }

    public function testCancellingTwiceIsRefused(): void
    {
        $id = $this->book('2027-06-10', '2027-06-15');
        $this->service->cancelBooking($id);

        try {
            $this->service->cancelBooking($id);
            self::fail('Expected a StateException');
        } catch (StateException $e) {
            self::assertSame('booking_not_confirmed', $e->reason);
        }
    }

    public function testCancellingAnUnknownBookingIsReported(): void
    {
        try {
            $this->service->cancelBooking(999999);
            self::fail('Expected a StateException');
        } catch (StateException $e) {
            self::assertSame('booking_not_found', $e->reason);
        }
    }

    public function testCancellationDoesNotTouchOtherBookings(): void
    {
        $a = $this->book('2027-06-10', '2027-06-15');
        $b = $this->book('2027-06-15', '2027-06-20');
        $c = $this->book('2027-06-10', '2027-06-15', 'girasole');

        $this->service->cancelBooking($a);

        self::assertSame('confirmed', $this->scalar('SELECT status FROM bookings WHERE id = ?', [$b]));
        self::assertSame('confirmed', $this->scalar('SELECT status FROM bookings WHERE id = ?', [$c]));
        self::assertFalse($this->isFree('2027-06-15', '2027-06-20'));
    }

    public function testCancellingABookingFromARequestMarksTheRequestCancelledToo(): void
    {
        $request = $this->service->createRequest($this->requestInput('2027-06-10', '2027-06-15'));
        $booking = $this->service->confirmRequest($request['id']);

        $this->service->cancelBooking($booking['booking_id']);

        self::assertSame('cancelled', $this->scalar('SELECT status FROM booking_requests WHERE id = ?', [$request['id']]));
        self::assertTrue($this->isFree('2027-06-10', '2027-06-15'));
    }

    public function testCancellationIsAuditedWithOldAndNewValues(): void
    {
        $id = $this->book('2027-06-10', '2027-06-15');
        $this->service->cancelBooking($id, 'motivo');

        $audit = $this->row("SELECT * FROM audit_log WHERE entity_type = 'booking' AND action = 'status_changed' AND entity_id = ?", [$id]);
        self::assertSame(['status' => 'confirmed'], json_decode($audit['old_values'], true));
        self::assertSame(['status' => 'cancelled', 'reason' => 'motivo'], json_decode($audit['new_values'], true));
    }

    public function testCancellationReasonIsLengthLimited(): void
    {
        $id = $this->book('2027-06-10', '2027-06-15');

        try {
            $this->service->cancelBooking($id, str_repeat('x', 1001));
            self::fail('Expected a ValidationException');
        } catch (ValidationException) {
        }
        self::assertSame('confirmed', $this->scalar('SELECT status FROM bookings WHERE id = ?', [$id]));
    }

    // --- atomicity ---------------------------------------------------------

    /** Makes every audit row for `$entityType` fail, to prove a failure mid-operation rolls everything back. */
    private function failAuditFor(string $entityType): void
    {
        $this->db->exec('DROP TRIGGER IF EXISTS test_fail_audit');
        $this->db->exec(
            "CREATE TRIGGER test_fail_audit BEFORE INSERT ON audit_log FOR EACH ROW
             IF NEW.entity_type = '{$entityType}' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'forced failure'; END IF"
        );
    }

    protected function tearDown(): void
    {
        $this->db->exec('DROP TRIGGER IF EXISTS test_fail_audit');
    }

    public function testConfirmRollsBackCompletelyWhenALaterStepFails(): void
    {
        $request = $this->service->createRequest($this->requestInput('2027-06-10', '2027-06-15'));
        $this->failAuditFor('booking'); // fails after the booking row and the request update were written

        try {
            $this->service->confirmRequest($request['id']);
            self::fail('Expected the forced failure');
        } catch (PDOException) {
        }

        self::assertFalse($this->db->inTransaction());
        self::assertSame('pending', $this->scalar('SELECT status FROM booking_requests WHERE id = ?', [$request['id']]));
        self::assertSame(0, (int) $this->scalar('SELECT COUNT(*) FROM bookings'));
        self::assertTrue($this->isFree('2027-06-10', '2027-06-15'));

        // Locks were released: the same operation succeeds once the fault is gone.
        $this->db->exec('DROP TRIGGER test_fail_audit');
        $this->service->confirmRequest($request['id']);
        self::assertSame(1, (int) $this->scalar("SELECT COUNT(*) FROM bookings WHERE status = 'confirmed'"));
    }

    public function testManualBookingAndCancellationRollBackWhenAuditFails(): void
    {
        $this->failAuditFor('booking');
        try {
            $this->book('2027-06-10', '2027-06-15');
            self::fail('Expected the forced failure');
        } catch (PDOException) {
        }
        self::assertSame(0, (int) $this->scalar('SELECT COUNT(*) FROM bookings'));

        $this->db->exec('DROP TRIGGER test_fail_audit');
        $id = $this->book('2027-06-10', '2027-06-15');
        $this->failAuditFor('booking');
        try {
            $this->service->cancelBooking($id);
            self::fail('Expected the forced failure');
        } catch (PDOException) {
        }
        self::assertSame('confirmed', $this->scalar('SELECT status FROM bookings WHERE id = ?', [$id]), 'a failed cancellation must not free the dates');
    }
}
