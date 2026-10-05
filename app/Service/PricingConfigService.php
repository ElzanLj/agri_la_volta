<?php

declare(strict_types=1);

namespace App\Service;

use App\Database\TransactionRunner;
use App\Domain\BusyException;
use App\Domain\ChargeRule;
use App\Domain\DateRanges;
use App\Domain\StateException;
use App\Domain\StayDates;
use App\Domain\ValidationException;
use App\Repository\ApartmentRepository;
use App\Repository\PricingRepository;
use App\Support\AuditLog;
use PDO;

/**
 * Admin-side editing of the price list, with consistency checks and an audit trail
 * (SPEC §15: "prezzo modificato"). Prices are data, never code.
 *
 * Rate periods of one apartment must not overlap while active; writes lock the apartment row
 * (like BookingService) so two simultaneous edits cannot both pass the overlap check.
 */
final class PricingConfigService
{
    /** Technical anti-abuse limits (not business rules). */
    private const MAX_AMOUNT_CENTS = 10_000_000;
    private const MAX_FREE_UNITS = 20;
    private const MAX_PERIOD_NIGHTS = 3660;

    private PricingRepository $pricing;
    private ApartmentRepository $apartments;
    private AuditLog $audit;
    private TransactionRunner $tx;

    public function __construct(PDO $db)
    {
        $this->pricing = new PricingRepository($db);
        $this->apartments = new ApartmentRepository($db);
        $this->audit = new AuditLog($db);
        $this->tx = new TransactionRunner($db);
    }

    // === Rate periods ========================================================

    /**
     * @param array<string, mixed> $input apartment_id, label_it, label_en?, start_date, end_date, nightly_rate_cents, min_nights?, is_active?
     * @throws ValidationException|StateException|BusyException
     */
    public function createRate(array $input): int
    {
        $apartmentId = self::intOrNull($input['apartment_id'] ?? null);
        if ($apartmentId === null) {
            throw new ValidationException(['apartment_id' => 'invalid_apartment']);
        }

        return $this->tx->run(function () use ($apartmentId, $input): int {
            $this->apartments->lockForUpdate($apartmentId) ?? throw new StateException('apartment_not_found');
            $values = $this->validateRate($input, $apartmentId, null);

            $id = $this->pricing->insertRate($values);
            $this->audit->record('seasonal_rate', $id, 'created', 'Tariffa creata', null, $values);
            return $id;
        });
    }

    /**
     * Replaces the editable fields of a rate period (the apartment cannot be changed).
     *
     * @param array<string, mixed> $input
     * @throws ValidationException|StateException|BusyException
     */
    public function updateRate(int $id, array $input): void
    {
        $existing = $this->pricing->rate($id) ?? throw new StateException('rate_not_found');
        $apartmentId = (int) $existing['apartment_id'];

        $this->tx->run(function () use ($id, $apartmentId, $input): void {
            $this->apartments->lockForUpdate($apartmentId);
            $old = $this->pricing->rate($id) ?? throw new StateException('rate_not_found');
            $values = $this->validateRate($input, $apartmentId, $id);

            $this->pricing->updateRate($id, $values);
            $this->audit->record('seasonal_rate', $id, 'updated', 'Tariffa modificata', self::rateValues($old), $values);
        });
    }

    /** @throws StateException|BusyException */
    public function deleteRate(int $id): void
    {
        $existing = $this->pricing->rate($id) ?? throw new StateException('rate_not_found');

        $this->tx->run(function () use ($id, $existing): void {
            $this->apartments->lockForUpdate((int) $existing['apartment_id']);
            $old = $this->pricing->rate($id) ?? throw new StateException('rate_not_found');

            $this->pricing->deleteRate($id);
            $this->audit->record('seasonal_rate', $id, 'deleted', 'Tariffa eliminata', self::rateValues($old), null);
        });
    }

    /**
     * Ranges of $from..$to (half-open) with no active rate: dates for which a stay cannot be priced.
     *
     * @return list<array{start_date: string, end_date: string}>
     * @throws ValidationException
     */
    public function coverageGaps(int $apartmentId, string $from, string $to): array
    {
        $range = StayDates::fromStrings($from, $to, self::MAX_PERIOD_NIGHTS);
        $periods = $this->pricing->activeRatesBetween($apartmentId, $range->checkIn, $range->checkOut);

        $uncovered = array_values(array_filter(
            $range->nightDates(),
            static function (string $night) use ($periods): bool {
                foreach ($periods as $period) {
                    if ($period->coversNight($night)) {
                        return false;
                    }
                }
                return true;
            },
        ));
        return DateRanges::fromNights($uncovered);
    }

    // === Charge rules ========================================================

    /**
     * @param array<string, mixed> $input apartment_id (null = all), applies_to, charge_basis, free_units?, amount_cents,
     *                                    valid_from?, valid_to?, is_active?, sort_order?, label_it, label_en?
     * @throws ValidationException|StateException|BusyException
     */
    public function createRule(array $input): int
    {
        return $this->tx->run(function () use ($input): int {
            $values = $this->validateRule($input);
            $id = $this->pricing->insertRule($values);
            $this->audit->record('pricing_rule', $id, 'created', 'Regola di prezzo creata', null, $values);
            return $id;
        });
    }

    /**
     * @param array<string, mixed> $input
     * @throws ValidationException|StateException|BusyException
     */
    public function updateRule(int $id, array $input): void
    {
        $this->tx->run(function () use ($id, $input): void {
            $old = $this->pricing->rule($id) ?? throw new StateException('rule_not_found');
            $values = $this->validateRule($input);

            $this->pricing->updateRule($id, $values);
            $this->audit->record('pricing_rule', $id, 'updated', 'Regola di prezzo modificata', self::ruleValues($old), $values);
        });
    }

    /** @throws StateException|BusyException */
    public function deleteRule(int $id): void
    {
        $this->tx->run(function () use ($id): void {
            $old = $this->pricing->rule($id) ?? throw new StateException('rule_not_found');
            $this->pricing->deleteRule($id);
            $this->audit->record('pricing_rule', $id, 'deleted', 'Regola di prezzo eliminata', self::ruleValues($old), null);
        });
    }

    // === Validation ==========================================================

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed> normalised values
     * @throws ValidationException
     */
    private function validateRate(array $input, int $apartmentId, ?int $excludeId): array
    {
        $errors = [];
        $context = [];
        $start = $end = null;

        try {
            $period = StayDates::fromStrings(self::str($input, 'start_date'), self::str($input, 'end_date'), self::MAX_PERIOD_NIGHTS);
            $start = $period->checkIn;
            $end = $period->checkOut;
        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $code) {
                $errors[$field === 'check_in' ? 'start_date' : 'end_date'] = $code === 'check_out_not_after_check_in' ? 'end_not_after_start' : $code;
            }
        }

        $labelIt = trim(self::str($input, 'label_it'));
        $labelEn = trim(self::str($input, 'label_en'));
        if (mb_strlen($labelIt) < 1 || mb_strlen($labelIt) > 100) {
            $errors['label_it'] = 'invalid_label';
        }
        if (mb_strlen($labelEn) > 100) {
            $errors['label_en'] = 'invalid_label';
        }

        $amount = self::intOrNull($input['nightly_rate_cents'] ?? null);
        if ($amount === null || $amount < 0 || $amount > self::MAX_AMOUNT_CENTS) {
            $errors['nightly_rate_cents'] = 'invalid_amount';
        }

        $minRaw = $input['min_nights'] ?? null;
        $minNights = ($minRaw === null || $minRaw === '') ? null : self::intOrNull($minRaw);
        if ($minRaw !== null && $minRaw !== '' && ($minNights === null || $minNights < 1 || $minNights > StayDates::MAX_NIGHTS_STAY)) {
            $errors['min_nights'] = 'invalid_min_nights';
        }

        $isActive = filter_var($input['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN);

        if ($errors === [] && $isActive) {
            $overlapping = $this->pricing->overlappingActiveRateIds($apartmentId, $start, $end, $excludeId);
            if ($overlapping !== []) {
                $errors['start_date'] = 'overlaps_existing_period';
                $context['overlapping_rate_ids'] = $overlapping;
            }
        }
        if ($errors !== []) {
            throw new ValidationException($errors, $context);
        }

        return [
            'apartment_id' => $apartmentId,
            'label_it' => $labelIt,
            'label_en' => $labelEn === '' ? null : $labelEn,
            'start_date' => $start,
            'end_date' => $end,
            'nightly_rate_cents' => $amount,
            'min_nights' => $minNights,
            'is_active' => $isActive,
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed> normalised values
     * @throws ValidationException|StateException
     */
    private function validateRule(array $input): array
    {
        $errors = [];

        $apartmentRaw = $input['apartment_id'] ?? null;
        $apartmentId = ($apartmentRaw === null || $apartmentRaw === '') ? null : self::intOrNull($apartmentRaw);
        if ($apartmentRaw !== null && $apartmentRaw !== '' && $apartmentId === null) {
            $errors['apartment_id'] = 'invalid_apartment';
        }

        $appliesTo = self::str($input, 'applies_to');
        if (!in_array($appliesTo, ChargeRule::APPLIES_TO, true)) {
            $errors['applies_to'] = 'invalid_applies_to';
        }
        $basis = self::str($input, 'charge_basis');
        if (!in_array($basis, ChargeRule::BASES, true)) {
            $errors['charge_basis'] = 'invalid_charge_basis';
        }

        $freeUnits = self::intOrNull($input['free_units'] ?? 0);
        if ($freeUnits === null || $freeUnits < 0 || $freeUnits > self::MAX_FREE_UNITS) {
            $errors['free_units'] = 'invalid_free_units';
        } elseif ($appliesTo === 'stay' && $freeUnits !== 0) {
            $errors['free_units'] = 'free_units_not_allowed_for_stay';
        }

        $amount = self::intOrNull($input['amount_cents'] ?? null);
        if ($amount === null || $amount < 0 || $amount > self::MAX_AMOUNT_CENTS) {
            $errors['amount_cents'] = 'invalid_amount';
        }

        $validFrom = $this->optionalDate($input, 'valid_from', $errors);
        $validTo = $this->optionalDate($input, 'valid_to', $errors);
        if ($validFrom !== null && $validTo !== null && $validTo <= $validFrom) {
            $errors['valid_to'] = 'end_not_after_start';
        }

        $sortOrder = self::intOrNull($input['sort_order'] ?? 0, allowNegative: true);
        if ($sortOrder === null || $sortOrder < -1000 || $sortOrder > 1000) {
            $errors['sort_order'] = 'invalid_sort_order';
        }

        $labelIt = trim(self::str($input, 'label_it'));
        $labelEn = trim(self::str($input, 'label_en'));
        if (mb_strlen($labelIt) < 1 || mb_strlen($labelIt) > 100) {
            $errors['label_it'] = 'invalid_label';
        }
        if (mb_strlen($labelEn) > 100) {
            $errors['label_en'] = 'invalid_label';
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }
        if ($apartmentId !== null && $this->apartments->find($apartmentId) === null) {
            throw new StateException('apartment_not_found');
        }

        return [
            'apartment_id' => $apartmentId,
            'applies_to' => $appliesTo,
            'charge_basis' => $basis,
            'free_units' => $freeUnits,
            'amount_cents' => $amount,
            'valid_from' => $validFrom,
            'valid_to' => $validTo,
            'is_active' => filter_var($input['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'sort_order' => $sortOrder,
            'label_it' => $labelIt,
            'label_en' => $labelEn === '' ? null : $labelEn,
        ];
    }

    /** @param array<string, mixed> $input @param array<string, string> $errors */
    private function optionalDate(array $input, string $key, array &$errors): ?string
    {
        $value = $input[$key] ?? null;
        if ($value === null || $value === '') {
            return null;
        }
        if (is_string($value) && StayDates::isValidDate($value)) {
            return $value;
        }
        $errors[$key] = 'invalid_date';
        return null;
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private static function rateValues(array $row): array
    {
        return [
            'apartment_id' => (int) $row['apartment_id'],
            'label_it' => $row['label'],
            'label_en' => $row['label_en'],
            'start_date' => (string) $row['start_date'],
            'end_date' => (string) $row['end_date'],
            'nightly_rate_cents' => (int) $row['nightly_rate_cents'],
            'min_nights' => $row['min_nights'] === null ? null : (int) $row['min_nights'],
            'is_active' => (bool) $row['is_active'],
        ];
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private static function ruleValues(array $row): array
    {
        return [
            'apartment_id' => $row['apartment_id'] === null ? null : (int) $row['apartment_id'],
            'applies_to' => $row['applies_to'],
            'charge_basis' => $row['charge_basis'],
            'free_units' => (int) $row['free_units'],
            'amount_cents' => (int) $row['amount_cents'],
            'valid_from' => $row['valid_from'] === null ? null : (string) $row['valid_from'],
            'valid_to' => $row['valid_to'] === null ? null : (string) $row['valid_to'],
            'is_active' => (bool) $row['is_active'],
            'sort_order' => (int) $row['sort_order'],
            'label_it' => $row['label_it'],
            'label_en' => $row['label_en'],
        ];
    }

    /** @param array<string, mixed> $input */
    private static function str(array $input, string $key): string
    {
        $value = $input[$key] ?? '';
        return is_string($value) ? $value : '';
    }

    private static function intOrNull(mixed $value, bool $allowNegative = false): ?int
    {
        if (is_int($value)) {
            return $value;
        }
        $pattern = $allowNegative ? '/^-?\d{1,10}$/' : '/^\d{1,10}$/';
        return is_string($value) && preg_match($pattern, $value) ? (int) $value : null;
    }
}
