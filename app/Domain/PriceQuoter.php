<?php

declare(strict_types=1);

namespace App\Domain;

/**
 * Prices are computed server-side. The real implementation arrives with the
 * pricing phase (docs/PLAN.md, Phase 2B); until then NullPriceQuoter is used.
 */
interface PriceQuoter
{
    /** Returns null when no price can be computed (e.g. no rates configured). */
    public function quote(int $apartmentId, StayDates $stay, GuestCounts $guests): ?PriceQuote;
}
