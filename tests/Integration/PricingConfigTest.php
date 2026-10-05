<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Domain\StateException;
use App\Domain\ValidationException;
use App\Service\PricingConfigService;
use Tests\Support\DatabaseTestCase;
use Tests\Support\PricingFixtures as F;

/** Consistency checks and audit trail of the price-list editor. FICTITIOUS test data only. */
final class PricingConfigTest extends DatabaseTestCase
{
    private PricingConfigService $config;

    protected function setUp(): void
    {
        parent::setUp();
        $this->config = new PricingConfigService($this->db);
    }

    private function rate(string $start, string $end, int $cents = 1000, string $slug = 'margherita', bool $active = true, ?int $min = null): int
    {
        return $this->config->createRate(F::rateInput($this->apartmentId($slug), '[TEST] Periodo', $start, $end, $cents, $min, $active));
    }

    /** @param array<string, mixed> $input @param array<string, string> $expected */
    private function assertRateRejected(array $input, array $expected): void
    {
        try {
            $this->config->createRate($input);
            self::fail('Expected a ValidationException');
        } catch (ValidationException $e) {
            foreach ($expected as $field => $code) {
                self::assertSame($code, $e->errors()[$field] ?? null, "field $field");
            }
        }
        self::assertSame(0, (int) $this->scalar('SELECT COUNT(*) FROM seasonal_rates'));
    }

    /** @param array<string, mixed> $input @param array<string, string> $expected */
    private function assertRuleRejected(array $input, array $expected): void
    {
        try {
            $this->config->createRule($input);
            self::fail('Expected a ValidationException');
        } catch (ValidationException $e) {
            foreach ($expected as $field => $code) {
                self::assertSame($code, $e->errors()[$field] ?? null, "field $field");
            }
        }
        self::assertSame(0, (int) $this->scalar('SELECT COUNT(*) FROM pricing_rules'));
    }

    // === Rate periods ============================================================

    public function testCreateRateStoresItAndAuditsIt(): void
    {
        $id = $this->config->createRate(['label_en' => '[TEST] Low'] + F::rateInput($this->apartmentId(), '  [TEST] Bassa ', '2027-06-01', '2027-06-15', 1000, 3));

        $row = $this->row('SELECT * FROM seasonal_rates WHERE id = ?', [$id]);
        self::assertSame(['[TEST] Bassa', '[TEST] Low', '2027-06-01', '2027-06-15', 1000, 3, 1], [
            $row['label'], $row['label_en'], $row['start_date'], $row['end_date'], (int) $row['nightly_rate_cents'], (int) $row['min_nights'], (int) $row['is_active'],
        ]);
        $audit = $this->row("SELECT * FROM audit_log WHERE entity_type = 'seasonal_rate' AND action = 'created' AND entity_id = ?", [$id]);
        self::assertNull($audit['old_values']);
        self::assertSame(1000, json_decode($audit['new_values'], true)['nightly_rate_cents']);
    }

    /** @return array<string, array{string, string}> every one of these shares a night with [10 Jun, 20 Jun) */
    public static function overlappingPeriods(): array
    {
        return [
            'identical' => ['2027-06-10', '2027-06-20'],
            'overlaps the start' => ['2027-06-05', '2027-06-12'],
            'overlaps the end' => ['2027-06-18', '2027-06-25'],
            'inside' => ['2027-06-12', '2027-06-15'],
            'containing' => ['2027-06-01', '2027-06-30'],
            'one night at the end' => ['2027-06-19', '2027-06-21'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('overlappingPeriods')]
    public function testOverlappingActivePeriodsOfTheSameApartmentAreRefused(string $start, string $end): void
    {
        $existing = $this->rate('2027-06-10', '2027-06-20');

        try {
            $this->rate($start, $end);
            self::fail('Expected a ValidationException');
        } catch (ValidationException $e) {
            self::assertSame('overlaps_existing_period', $e->errors()['start_date']);
            self::assertSame([$existing], $e->context()['overlapping_rate_ids']);
        }
        self::assertSame(1, (int) $this->scalar('SELECT COUNT(*) FROM seasonal_rates'));
    }

    public function testAdjacentPeriodsDifferentApartmentsAndInactivePeriodsDoNotConflict(): void
    {
        $this->rate('2027-06-10', '2027-06-20');

        $this->rate('2027-06-20', '2027-06-30'); // starts on the previous end day
        $this->rate('2027-06-01', '2027-06-10'); // ends on the previous start day
        $this->rate('2027-06-10', '2027-06-20', slug: 'girasole'); // another apartment
        $this->rate('2027-06-12', '2027-06-18', active: false);    // inactive: kept as a draft

        self::assertSame(4, (int) $this->scalar('SELECT COUNT(*) FROM seasonal_rates WHERE apartment_id = ?', [$this->apartmentId()]));
        self::assertSame(5, (int) $this->scalar('SELECT COUNT(*) FROM seasonal_rates'));
    }

    public function testUpdateRateKeepsItselfOutOfTheOverlapCheckAndAuditsOldAndNewValues(): void
    {
        $id = $this->rate('2027-06-10', '2027-06-20', 1000);

        $this->config->updateRate($id, F::rateInput($this->apartmentId(), '[TEST] Modificata', '2027-06-08', '2027-06-22', 1500, 2));

        $row = $this->row('SELECT * FROM seasonal_rates WHERE id = ?', [$id]);
        self::assertSame(['[TEST] Modificata', '2027-06-08', '2027-06-22', 1500, 2], [$row['label'], $row['start_date'], $row['end_date'], (int) $row['nightly_rate_cents'], (int) $row['min_nights']]);
        $audit = $this->row("SELECT * FROM audit_log WHERE entity_type = 'seasonal_rate' AND action = 'updated' AND entity_id = ?", [$id]);
        self::assertSame(1000, json_decode($audit['old_values'], true)['nightly_rate_cents']);
        self::assertSame(1500, json_decode($audit['new_values'], true)['nightly_rate_cents']);
    }

    public function testUpdateCannotCreateAnOverlapNorChangeTheApartment(): void
    {
        $first = $this->rate('2027-06-01', '2027-06-10');
        $this->rate('2027-06-10', '2027-06-20');

        try {
            $this->config->updateRate($first, F::rateInput($this->apartmentId(), '[TEST] X', '2027-06-01', '2027-06-12', 1000));
            self::fail('Expected a ValidationException');
        } catch (ValidationException $e) {
            self::assertSame('overlaps_existing_period', $e->errors()['start_date']);
        }
        self::assertSame('2027-06-10', $this->scalar('SELECT end_date FROM seasonal_rates WHERE id = ?', [$first]), 'the failed update changed nothing');

        $this->config->updateRate($first, F::rateInput($this->apartmentId('viola'), '[TEST] Y', '2027-06-01', '2027-06-10', 1000));
        self::assertSame($this->apartmentId(), (int) $this->scalar('SELECT apartment_id FROM seasonal_rates WHERE id = ?', [$first]), 'the apartment is immutable');
    }

    public function testReactivatingAnOverlappingDraftIsRefused(): void
    {
        $this->rate('2027-06-10', '2027-06-20');
        $draft = $this->rate('2027-06-15', '2027-06-25', active: false);

        $this->expectException(ValidationException::class);
        $this->config->updateRate($draft, F::rateInput($this->apartmentId(), '[TEST] Bozza', '2027-06-15', '2027-06-25', 1000, null, true));
    }

    public function testDeleteRateAuditsTheOldValues(): void
    {
        $id = $this->rate('2027-06-10', '2027-06-20', 1234);

        $this->config->deleteRate($id);

        self::assertFalse($this->row('SELECT id FROM seasonal_rates WHERE id = ?', [$id]));
        $audit = $this->row("SELECT * FROM audit_log WHERE entity_type = 'seasonal_rate' AND action = 'deleted' AND entity_id = ?", [$id]);
        self::assertSame(1234, json_decode($audit['old_values'], true)['nightly_rate_cents']);
        self::assertNull($audit['new_values']);
    }

    public function testUnknownIdsAndApartmentsAreReported(): void
    {
        $refused = 0;
        foreach ([
            fn () => $this->config->updateRate(999999, F::rateInput($this->apartmentId(), 'x', '2027-06-01', '2027-06-10', 1)),
            fn () => $this->config->deleteRate(999999),
            fn () => $this->config->createRate(F::rateInput(999999, 'x', '2027-06-01', '2027-06-10', 1)),
            fn () => $this->config->updateRule(999999, F::ruleInput('stay', 'per_stay', 1)),
            fn () => $this->config->deleteRule(999999),
            fn () => $this->config->createRule(F::ruleInput('stay', 'per_stay', 1, 0, 999999)),
        ] as $attempt) {
            try {
                $attempt();
                self::fail('Expected a StateException');
            } catch (StateException) {
                $refused++;
            }
        }
        self::assertSame(6, $refused);
    }

    public function testRateValidation(): void
    {
        $a = $this->apartmentId();
        $this->assertRateRejected(F::rateInput($a, '[TEST] x', '2027-06-20', '2027-06-10', 1000), ['end_date' => 'end_not_after_start']);
        $this->assertRateRejected(F::rateInput($a, '[TEST] x', '2027-06-10', '2027-06-10', 1000), ['end_date' => 'end_not_after_start']);
        $this->assertRateRejected(F::rateInput($a, '[TEST] x', '2027-02-30', '2027-03-10', 1000), ['start_date' => 'invalid_date']);
        $this->assertRateRejected(F::rateInput($a, '[TEST] x', '2027-06-10', 'nope', 1000), ['end_date' => 'invalid_date']);
        $this->assertRateRejected(F::rateInput($a, '[TEST] x', '2027-01-01', '2040-01-01', 1000), ['end_date' => 'stay_too_long']);
        $this->assertRateRejected(['nightly_rate_cents' => -1] + F::rateInput($a, '[TEST] x', '2027-06-10', '2027-06-20', 1000), ['nightly_rate_cents' => 'invalid_amount']);
        $this->assertRateRejected(['nightly_rate_cents' => 'abc'] + F::rateInput($a, '[TEST] x', '2027-06-10', '2027-06-20', 1000), ['nightly_rate_cents' => 'invalid_amount']);
        $this->assertRateRejected(['nightly_rate_cents' => 10_000_001] + F::rateInput($a, '[TEST] x', '2027-06-10', '2027-06-20', 1000), ['nightly_rate_cents' => 'invalid_amount']);
        $this->assertRateRejected(['nightly_rate_cents' => '12.5'] + F::rateInput($a, '[TEST] x', '2027-06-10', '2027-06-20', 1000), ['nightly_rate_cents' => 'invalid_amount']);
        $this->assertRateRejected(F::rateInput($a, '[TEST] x', '2027-06-10', '2027-06-20', 1000, 0), ['min_nights' => 'invalid_min_nights']);
        $this->assertRateRejected(F::rateInput($a, '[TEST] x', '2027-06-10', '2027-06-20', 1000, 61), ['min_nights' => 'invalid_min_nights']);
        $this->assertRateRejected(['min_nights' => 'x'] + F::rateInput($a, '[TEST] x', '2027-06-10', '2027-06-20', 1000), ['min_nights' => 'invalid_min_nights']);
        $this->assertRateRejected(F::rateInput($a, '   ', '2027-06-10', '2027-06-20', 1000), ['label_it' => 'invalid_label']);
        $this->assertRateRejected(F::rateInput($a, str_repeat('x', 101), '2027-06-10', '2027-06-20', 1000), ['label_it' => 'invalid_label']);
        $this->assertRateRejected(['apartment_id' => null] + F::rateInput($a, '[TEST] x', '2027-06-10', '2027-06-20', 1000), ['apartment_id' => 'invalid_apartment']);
    }

    public function testZeroRateAndEmptyMinimumAreAccepted(): void
    {
        $id = $this->config->createRate(['min_nights' => '', 'nightly_rate_cents' => '0'] + F::rateInput($this->apartmentId(), '[TEST] Gratis', '2027-06-10', '2027-06-20', 0));

        $row = $this->row('SELECT nightly_rate_cents, min_nights FROM seasonal_rates WHERE id = ?', [$id]);
        self::assertSame([0, null], [(int) $row['nightly_rate_cents'], $row['min_nights']]);
    }

    // === Charge rules ============================================================

    public function testCreateUpdateDeleteRuleWithAudit(): void
    {
        $id = $this->config->createRule(F::ruleInput('adult', 'per_night', 500, 2) + ['label_en' => '[TEST] Extra adult', 'valid_from' => '2027-06-01', 'valid_to' => '2027-09-01', 'sort_order' => '-3']);

        $row = $this->row('SELECT * FROM pricing_rules WHERE id = ?', [$id]);
        self::assertSame(['adult', 'per_night', 2, 500, '2027-06-01', '2027-09-01', -3, null], [
            $row['applies_to'], $row['charge_basis'], (int) $row['free_units'], (int) $row['amount_cents'], $row['valid_from'], $row['valid_to'], (int) $row['sort_order'], $row['apartment_id'],
        ]);

        $this->config->updateRule($id, F::ruleInput('adult', 'per_night', 700, 2));
        $updated = $this->row("SELECT * FROM audit_log WHERE entity_type = 'pricing_rule' AND action = 'updated' AND entity_id = ?", [$id]);
        self::assertSame(500, json_decode($updated['old_values'], true)['amount_cents']);
        self::assertSame(700, json_decode($updated['new_values'], true)['amount_cents']);

        $this->config->deleteRule($id);
        self::assertFalse($this->row('SELECT id FROM pricing_rules WHERE id = ?', [$id]));
        self::assertNotFalse($this->row("SELECT id FROM audit_log WHERE entity_type = 'pricing_rule' AND action = 'deleted' AND entity_id = ?", [$id]));
        self::assertNotFalse($this->row("SELECT id FROM audit_log WHERE entity_type = 'pricing_rule' AND action = 'created' AND entity_id = ?", [$id]));
    }

    public function testRuleMayBeGlobalOrForOneApartment(): void
    {
        $global = $this->config->createRule(F::ruleInput('stay', 'per_stay', 100) + ['apartment_id' => '']);
        $specific = $this->config->createRule(F::ruleInput('stay', 'per_stay', 100, 0, $this->apartmentId('rosa')));

        self::assertNull($this->scalar('SELECT apartment_id FROM pricing_rules WHERE id = ?', [$global]));
        self::assertSame($this->apartmentId('rosa'), (int) $this->scalar('SELECT apartment_id FROM pricing_rules WHERE id = ?', [$specific]));
    }

    public function testRuleValidation(): void
    {
        $base = F::ruleInput('adult', 'per_night', 500, 2);

        $this->assertRuleRejected(['applies_to' => 'dog'] + $base, ['applies_to' => 'invalid_applies_to']);
        $this->assertRuleRejected(['charge_basis' => 'per_week'] + $base, ['charge_basis' => 'invalid_charge_basis']);
        $this->assertRuleRejected(['free_units' => -1] + $base, ['free_units' => 'invalid_free_units']);
        $this->assertRuleRejected(['free_units' => 21] + $base, ['free_units' => 'invalid_free_units']);
        $this->assertRuleRejected(['applies_to' => 'stay', 'free_units' => 1] + $base, ['free_units' => 'free_units_not_allowed_for_stay']);
        $this->assertRuleRejected(['amount_cents' => -5] + $base, ['amount_cents' => 'invalid_amount']);
        $this->assertRuleRejected(['amount_cents' => '1.5'] + $base, ['amount_cents' => 'invalid_amount']);
        $this->assertRuleRejected(['amount_cents' => 10_000_001] + $base, ['amount_cents' => 'invalid_amount']);
        $this->assertRuleRejected(['valid_from' => '2027-06-20', 'valid_to' => '2027-06-10'] + $base, ['valid_to' => 'end_not_after_start']);
        $this->assertRuleRejected(['valid_from' => '2027-06-10', 'valid_to' => '2027-06-10'] + $base, ['valid_to' => 'end_not_after_start']);
        $this->assertRuleRejected(['valid_from' => '2027-02-30'] + $base, ['valid_from' => 'invalid_date']);
        $this->assertRuleRejected(['valid_to' => 'domani'] + $base, ['valid_to' => 'invalid_date']);
        $this->assertRuleRejected(['label_it' => ''] + $base, ['label_it' => 'invalid_label']);
        $this->assertRuleRejected(['sort_order' => 5000] + $base, ['sort_order' => 'invalid_sort_order']);
        $this->assertRuleRejected(['apartment_id' => 'abc'] + $base, ['apartment_id' => 'invalid_apartment']);
    }

    public function testRuleWindowMayBeOpenEnded(): void
    {
        $from = $this->config->createRule(F::ruleInput('stay', 'per_night', 100) + ['valid_from' => '2027-06-01']);
        $to = $this->config->createRule(F::ruleInput('stay', 'per_night', 100) + ['valid_to' => '2027-06-01']);

        self::assertNull($this->scalar('SELECT valid_to FROM pricing_rules WHERE id = ?', [$from]));
        self::assertNull($this->scalar('SELECT valid_from FROM pricing_rules WHERE id = ?', [$to]));
    }

    public function testDatabaseItselfRejectsInconsistentRules(): void
    {
        $this->expectException(\PDOException::class);
        $this->db->exec("INSERT INTO pricing_rules (applies_to, charge_basis, amount_cents, label_it) VALUES ('goat', 'per_night', 1, 'x')");
    }

    // === Coverage report =========================================================

    public function testCoverageGapsListTheDatesThatCannotBePriced(): void
    {
        $this->rate('2027-06-01', '2027-06-15');
        $this->rate('2027-07-01', '2027-08-01');
        $this->rate('2027-06-15', '2027-07-01', 2000, 'margherita', false); // inactive: does not cover
        $this->rate('2027-06-01', '2027-09-01', 9999, 'girasole');           // other apartment: does not cover

        $gaps = $this->config->coverageGaps($this->apartmentId(), '2027-05-25', '2027-08-05');

        self::assertSame([
            ['start_date' => '2027-05-25', 'end_date' => '2027-06-01'],
            ['start_date' => '2027-06-15', 'end_date' => '2027-07-01'],
            ['start_date' => '2027-08-01', 'end_date' => '2027-08-05'],
        ], $gaps);
        self::assertSame([], $this->config->coverageGaps($this->apartmentId(), '2027-06-03', '2027-06-10'));
    }

    public function testCoverageGapsValidateTheirRange(): void
    {
        $this->expectException(ValidationException::class);
        $this->config->coverageGaps($this->apartmentId(), '2027-06-10', '2027-06-01');
    }
}
