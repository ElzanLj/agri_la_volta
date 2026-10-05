<?php

declare(strict_types=1);

namespace App\Domain;

/**
 * Pure, deterministic price calculation: no database, no clock, no randomness.
 *
 * Conventions (confirmed with the owner, docs/DECISIONS.md):
 * - the base rate is per apartment per night; each night uses the rate period containing it;
 * - guest and supplement charges are additive, with a fixed set of kinds;
 * - the minimum stay is the one of the rate period containing the CHECK-IN night;
 * - a per-stay charge with a validity window applies when the check-in date is inside it;
 * - a night without a rate never gets an invented price: the quote becomes incomplete.
 */
final class PriceCalculator
{
    /** Totals must fit the INT UNSIGNED column that stores them. */
    private const MAX_TOTAL_CENTS = 4_294_967_295;

    /**
     * @param list<RatePeriod> $periods any periods; inactive ones and other apartments' are ignored
     * @param list<ChargeRule> $rules   any rules; inactive ones and other apartments' are ignored
     */
    public function calculate(int $apartmentId, StayDates $stay, GuestCounts $guests, array $periods, array $rules): PriceQuote
    {
        $periods = array_values(array_filter(
            $periods,
            static fn (RatePeriod $p): bool => $p->isActive && $p->apartmentId === $apartmentId,
        ));
        $rules = array_values(array_filter(
            $rules,
            static fn (ChargeRule $r): bool => $r->isActive && ($r->apartmentId === null || $r->apartmentId === $apartmentId),
        ));
        usort($rules, static fn (ChargeRule $a, ChargeRule $b): int => [$a->sortOrder, $a->id] <=> [$b->sortOrder, $b->id]);

        $nights = $stay->nightDates();
        $lines = [];
        $issues = [];
        $total = 0;
        $complete = true;

        // --- base rate, one line per run of nights in the same period -----------------------
        $segments = [];
        $uncovered = [];
        $ambiguous = [];
        $current = null;

        foreach ($nights as $night) {
            $matches = array_values(array_filter($periods, static fn (RatePeriod $p): bool => $p->coversNight($night)));
            if ($matches === []) {
                $uncovered[] = $night;
                $current = null;
                continue;
            }
            if (count($matches) > 1) {
                $ambiguous[] = $night;
                $current = null;
                continue;
            }
            $period = $matches[0];
            if ($current !== null && $segments[$current]['period']->id === $period->id) {
                $segments[$current]['nights']++;
            } else {
                $segments[] = ['period' => $period, 'nights' => 1, 'from' => $night];
                $current = array_key_last($segments);
            }
        }

        foreach ($segments as $segment) {
            /** @var RatePeriod $period */
            $period = $segment['period'];
            $lineTotal = $segment['nights'] * $period->nightlyRateCents;
            $lines[] = [
                'kind' => 'base',
                'label_it' => $period->labelIt,
                'label_en' => $period->labelEnOrIt(),
                'quantity' => 1,
                'nights' => $segment['nights'],
                'unit_cents' => $period->nightlyRateCents,
                'total_cents' => $lineTotal,
                'basis' => 'per_night',
                'start_date' => $segment['from'],
                'end_date' => DateRanges::addDays($segment['from'], $segment['nights']),
            ];
            $total += $lineTotal;
        }
        if ($uncovered !== []) {
            $issues[] = ['code' => 'no_rate_for_night', 'severity' => 'info', 'ranges' => DateRanges::fromNights($uncovered)];
            $complete = false;
        }
        if ($ambiguous !== []) {
            $issues[] = ['code' => 'ambiguous_rate', 'severity' => 'info', 'ranges' => DateRanges::fromNights($ambiguous)];
            $complete = false;
        }

        // --- minimum stay: decided by the period of the check-in night ----------------------
        $minNights = null;
        $checkInPeriods = array_values(array_filter($periods, static fn (RatePeriod $p): bool => $p->coversNight($stay->checkIn)));
        if (count($checkInPeriods) === 1 && $checkInPeriods[0]->minNights !== null) {
            $minNights = $checkInPeriods[0]->minNights;
            if (count($nights) < $minNights) {
                $issues[] = ['code' => 'below_min_nights', 'severity' => 'blocking', 'min_nights' => $minNights, 'nights' => count($nights)];
            }
        }

        // --- guest charges and supplements -------------------------------------------------
        foreach ($rules as $rule) {
            $units = match ($rule->appliesTo) {
                'adult' => $guests->adults,
                'child' => $guests->children,
                'pet' => $guests->pets,
                default => 1,
            };
            $chargeable = max(0, $units - $rule->freeUnits);

            if ($rule->chargeBasis === 'per_night') {
                $billedNights = count(array_filter($nights, static fn (string $n): bool => $rule->coversNight($n)));
            } else {
                $billedNights = $rule->coversNight($stay->checkIn) ? 1 : 0;
            }
            if ($chargeable === 0 || $billedNights === 0 || $rule->amountCents === 0) {
                continue;
            }

            $lineTotal = $chargeable * $rule->amountCents * ($rule->chargeBasis === 'per_night' ? $billedNights : 1);
            $lines[] = [
                'kind' => $rule->appliesTo === 'stay' ? 'supplement' : $rule->appliesTo,
                'label_it' => $rule->labelIt,
                'label_en' => $rule->labelEnOrIt(),
                'quantity' => $chargeable,
                'nights' => $rule->chargeBasis === 'per_night' ? $billedNights : null,
                'unit_cents' => $rule->amountCents,
                'total_cents' => $lineTotal,
                'basis' => $rule->chargeBasis,
            ];
            $total += $lineTotal;
        }

        if ($total > self::MAX_TOTAL_CENTS) {
            $issues[] = ['code' => 'total_out_of_range', 'severity' => 'info'];
            $complete = false;
        }

        return new PriceQuote($complete ? $total : null, $lines, $issues, $minNights, count($nights));
    }
}
