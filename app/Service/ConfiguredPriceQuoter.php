<?php

declare(strict_types=1);

namespace App\Service;

use App\Domain\GuestCounts;
use App\Domain\PriceCalculator;
use App\Domain\PriceQuote;
use App\Domain\PriceQuoter;
use App\Domain\StayDates;
use App\Repository\PricingRepository;

/** Quoter backed by the price list stored in the database (admin-editable, no code changes). */
final class ConfiguredPriceQuoter implements PriceQuoter
{
    public function __construct(
        private PricingRepository $pricing,
        private PriceCalculator $calculator = new PriceCalculator(),
    ) {
    }

    public function quote(int $apartmentId, StayDates $stay, GuestCounts $guests): PriceQuote
    {
        return $this->calculator->calculate(
            $apartmentId,
            $stay,
            $guests,
            $this->pricing->ratePeriodsFor($apartmentId, $stay),
            $this->pricing->rulesFor($apartmentId, $stay),
        );
    }
}
