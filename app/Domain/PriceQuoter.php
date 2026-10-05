<?php

declare(strict_types=1);

namespace App\Domain;

/**
 * Computes the price of a stay server-side. Never throws for missing price data:
 * an incomplete quote (totalCents null) describes what is missing instead.
 */
interface PriceQuoter
{
    public function quote(int $apartmentId, StayDates $stay, GuestCounts $guests): PriceQuote;
}
