<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Domain\GuestCounts;
use App\Domain\StayDates;
use App\Repository\AvailabilityRepository;
use App\Service\AvailabilityService;
use Tests\Support\DatabaseTestCase;

final class AvailabilityTest extends DatabaseTestCase
{
    private AvailabilityService $availability;

    protected function setUp(): void
    {
        parent::setUp();
        $this->availability = new AvailabilityService(new AvailabilityRepository($this->db));
    }

    private function free(string $in, string $out, string $slug = 'margherita'): bool
    {
        return $this->availability->isAvailable($this->apartmentId($slug), StayDates::fromStrings($in, $out));
    }

    /** @return list<string> */
    private function availableSlugs(string $in, string $out, ?GuestCounts $guests = null, bool $onlineOnly = true): array
    {
        return array_column($this->availability->availableApartments(StayDates::fromStrings($in, $out), $guests, $onlineOnly), 'slug');
    }

    public function testEmptyCalendarIsAvailable(): void
    {
        self::assertTrue($this->free('2027-06-10', '2027-06-15'));
        self::assertCount(6, $this->availableSlugs('2027-06-10', '2027-06-15'));
    }

    public function testConfirmedBookingOccupiesItsNightsOnly(): void
    {
        $this->book('2027-06-10', '2027-06-15');

        self::assertFalse($this->free('2027-06-10', '2027-06-15'));
        self::assertFalse($this->free('2027-06-14', '2027-06-16'), 'last night is occupied');
        self::assertFalse($this->free('2027-06-09', '2027-06-11'), 'first night is occupied');
        self::assertTrue($this->free('2027-06-15', '2027-06-18'), 'check-out day is free for the next guest');
        self::assertTrue($this->free('2027-06-08', '2027-06-10'), 'a stay ending on the check-in day is compatible');
    }

    public function testOtherApartmentsAreNotAffected(): void
    {
        $this->book('2027-06-10', '2027-06-15', 'margherita');

        self::assertTrue($this->free('2027-06-10', '2027-06-15', 'girasole'));
        self::assertNotContains('margherita', $this->availableSlugs('2027-06-10', '2027-06-15'));
        self::assertContains('girasole', $this->availableSlugs('2027-06-10', '2027-06-15'));
        self::assertCount(5, $this->availableSlugs('2027-06-10', '2027-06-15'));
    }

    public function testPendingRequestDoesNotOccupyDates(): void
    {
        $this->service->createRequest($this->requestInput('2027-06-10', '2027-06-15'));

        self::assertTrue($this->free('2027-06-10', '2027-06-15'));
    }

    public function testRejectedRequestDoesNotOccupyDates(): void
    {
        $request = $this->service->createRequest($this->requestInput('2027-06-10', '2027-06-15'));
        $this->service->rejectRequest($request['id']);

        self::assertTrue($this->free('2027-06-10', '2027-06-15'));
    }

    public function testCancelledBookingDoesNotOccupyDates(): void
    {
        $id = $this->book('2027-06-10', '2027-06-15');
        $this->service->cancelBooking($id);

        self::assertTrue($this->free('2027-06-10', '2027-06-15'));
    }

    public function testBlockOccupiesItsNightsAndItsEndDayIsFree(): void
    {
        $this->service->createBlock($this->apartmentId(), '2027-06-10', '2027-06-15', 'manutenzione');

        self::assertFalse($this->free('2027-06-12', '2027-06-13'));
        self::assertFalse($this->free('2027-06-14', '2027-06-16'));
        self::assertTrue($this->free('2027-06-15', '2027-06-18'));
        self::assertTrue($this->free('2027-06-08', '2027-06-10'));
        self::assertNotContains('margherita', $this->availableSlugs('2027-06-12', '2027-06-14'));
    }

    public function testRemovedBlockFreesTheDates(): void
    {
        $blockId = $this->service->createBlock($this->apartmentId(), '2027-06-10', '2027-06-15');
        self::assertFalse($this->free('2027-06-10', '2027-06-15'));

        $this->service->removeBlock($blockId);

        self::assertTrue($this->free('2027-06-10', '2027-06-15'));
    }

    public function testInactiveApartmentIsNeverListed(): void
    {
        $this->db->exec("UPDATE apartments SET is_active = 0 WHERE slug = 'viola'");

        self::assertNotContains('viola', $this->availableSlugs('2027-06-10', '2027-06-15'));
        self::assertNotContains('viola', $this->availableSlugs('2027-06-10', '2027-06-15', null, false));
        self::assertCount(5, $this->availableSlugs('2027-06-10', '2027-06-15'));
    }

    public function testApartmentNotAcceptingOnlineRequestsIsListedOnlyForAdmin(): void
    {
        $this->db->exec("UPDATE apartments SET accepts_online_requests = 0 WHERE slug = 'rosa'");

        self::assertNotContains('rosa', $this->availableSlugs('2027-06-10', '2027-06-15'));
        self::assertContains('rosa', $this->availableSlugs('2027-06-10', '2027-06-15', null, false));
    }

    public function testCapacityFilterAppliesOnlyWhenCapacityIsConfigured(): void
    {
        $this->db->exec("UPDATE apartments SET max_guests = 2 WHERE slug = 'mimosa'");

        $four = GuestCounts::from(3, 1);
        self::assertNotContains('mimosa', $this->availableSlugs('2027-06-10', '2027-06-15', $four));
        self::assertContains('margherita', $this->availableSlugs('2027-06-10', '2027-06-15', $four), 'NULL capacity = not configured = not filtered');
        self::assertContains('mimosa', $this->availableSlugs('2027-06-10', '2027-06-15', GuestCounts::from(2)));
    }

    public function testConflictsDescribeWhatIsInTheWay(): void
    {
        $bookingId = $this->book('2027-06-10', '2027-06-15');
        $blockId = $this->service->createBlock($this->apartmentId(), '2027-06-20', '2027-06-25');

        $conflicts = (new AvailabilityRepository($this->db))->conflicts($this->apartmentId(), StayDates::fromStrings('2027-06-14', '2027-06-22'));

        self::assertSame([
            ['type' => 'booking', 'id' => $bookingId, 'start_date' => '2027-06-10', 'end_date' => '2027-06-15'],
            ['type' => 'block', 'id' => $blockId, 'start_date' => '2027-06-20', 'end_date' => '2027-06-25'],
        ], $conflicts);
    }
}
