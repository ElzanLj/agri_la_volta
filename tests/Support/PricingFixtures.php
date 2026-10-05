<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Domain\ChargeRule;
use App\Domain\RatePeriod;

/**
 * FICTITIOUS price-list data used only by tests. The labels are marked "[TEST]" and the amounts
 * are deliberately round and artificial: they are NOT the owner's prices and must never be
 * copied into migrations, seeds or production data (docs/MISSING_DATA.md).
 *
 * Fixture amounts (cents): LOW 1000, HIGH 2000, PEAK 3000 per night.
 */
final class PricingFixtures
{
    public const APARTMENT = 1;
    public const OTHER_APARTMENT = 2;

    public static function period(
        int $id,
        string $label,
        string $start,
        string $end,
        int $rateCents,
        ?int $minNights = null,
        bool $active = true,
        int $apartmentId = self::APARTMENT,
        ?string $labelEn = null,
    ): RatePeriod {
        return new RatePeriod($id, $apartmentId, $label, $labelEn, $start, $end, $rateCents, $minNights, $active);
    }

    /** Three consecutive seasons: [1 Jun, 15 Jun) LOW, [15 Jun, 1 Jul) HIGH, [1 Jul, 1 Aug) PEAK (2027). */
    public static function seasons(?int $lowMin = null, ?int $highMin = null, int $apartmentId = self::APARTMENT): array
    {
        return [
            self::period(1, '[TEST] Bassa', '2027-06-01', '2027-06-15', 1000, $lowMin, true, $apartmentId, '[TEST] Low'),
            self::period(2, '[TEST] Alta', '2027-06-15', '2027-07-01', 2000, $highMin, true, $apartmentId, '[TEST] High'),
            self::period(3, '[TEST] Picco', '2027-07-01', '2027-08-01', 3000, null, true, $apartmentId, '[TEST] Peak'),
        ];
    }

    public static function rule(
        int $id,
        string $appliesTo,
        string $basis,
        int $amountCents,
        int $freeUnits = 0,
        ?int $apartmentId = null,
        ?string $validFrom = null,
        ?string $validTo = null,
        bool $active = true,
        int $sortOrder = 0,
        string $label = '[TEST] Regola',
        ?string $labelEn = null,
    ): ChargeRule {
        return new ChargeRule($id, $apartmentId, $appliesTo, $basis, $freeUnits, $amountCents, $validFrom, $validTo, $active, $sortOrder, $label, $labelEn);
    }

    /** @return array<string, mixed> input for PricingConfigService::createRate */
    public static function rateInput(int $apartmentId, string $label, string $start, string $end, int $rateCents, ?int $minNights = null, bool $active = true): array
    {
        return [
            'apartment_id' => $apartmentId,
            'label_it' => $label,
            'label_en' => null,
            'start_date' => $start,
            'end_date' => $end,
            'nightly_rate_cents' => $rateCents,
            'min_nights' => $minNights,
            'is_active' => $active,
        ];
    }

    /** @return array<string, mixed> input for PricingConfigService::createRule */
    public static function ruleInput(string $appliesTo, string $basis, int $amountCents, int $freeUnits = 0, ?int $apartmentId = null, string $label = '[TEST] Regola'): array
    {
        return [
            'apartment_id' => $apartmentId,
            'applies_to' => $appliesTo,
            'charge_basis' => $basis,
            'free_units' => $freeUnits,
            'amount_cents' => $amountCents,
            'label_it' => $label,
        ];
    }
}
