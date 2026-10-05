<?php

declare(strict_types=1);

namespace App\Domain;

/** Quoter that never prices anything: used by tests that do not care about prices. */
final class NullPriceQuoter implements PriceQuoter
{
    public function quote(int $apartmentId, StayDates $stay, GuestCounts $guests): PriceQuote
    {
        return new PriceQuote(null, [], [['code' => 'pricing_not_configured', 'severity' => 'info']], null, $stay->nights());
    }
}
