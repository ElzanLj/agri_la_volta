<?php

declare(strict_types=1);

namespace App\Domain;

/** Used while no rates are configured: no invented amounts. */
final class NullPriceQuoter implements PriceQuoter
{
    public function quote(int $apartmentId, StayDates $stay, GuestCounts $guests): ?PriceQuote
    {
        return null;
    }
}
