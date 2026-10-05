<?php

declare(strict_types=1);

namespace App\Service;

use App\Domain\GuestCounts;
use App\Domain\StayDates;
use App\Repository\AvailabilityRepository;

/**
 * Non-binding availability reads for the public pages and the admin calendar.
 * The binding check happens inside BookingService, under the apartment lock.
 */
final class AvailabilityService
{
    public function __construct(private AvailabilityRepository $availability)
    {
    }

    public function isAvailable(int $apartmentId, StayDates $stay): bool
    {
        return $this->availability->conflicts($apartmentId, $stay) === [];
    }

    /** @return list<array<string, mixed>> */
    public function availableApartments(StayDates $stay, ?GuestCounts $guests = null, bool $onlineRequestsOnly = true): array
    {
        return $this->availability->availableApartments($stay, $guests, $onlineRequestsOnly);
    }
}
