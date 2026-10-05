<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\GuestCounts;
use App\Domain\PriceCalculator;
use App\Domain\PriceQuote;
use App\Domain\StayDates;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\PricingFixtures as F;

/**
 * Pure calculator tests. All prices come from tests/Support/PricingFixtures.php and are FICTITIOUS.
 * Fixture seasons (2027): LOW [1 Jun, 15 Jun) 1000, HIGH [15 Jun, 1 Jul) 2000, PEAK [1 Jul, 1 Aug) 3000 cents/night.
 */
final class PriceCalculatorTest extends TestCase
{
    /** @param list<\App\Domain\RatePeriod>|null $periods @param list<\App\Domain\ChargeRule> $rules */
    private function quote(string $in, string $out, int $adults = 2, int $children = 0, int $pets = 0, ?array $periods = null, array $rules = []): PriceQuote
    {
        return (new PriceCalculator())->calculate(
            F::APARTMENT,
            StayDates::fromStrings($in, $out),
            GuestCounts::from($adults, $children, $pets),
            $periods ?? F::seasons(),
            $rules,
        );
    }

    /** @return list<string> */
    private function labels(PriceQuote $quote): array
    {
        return array_column($quote->lines, 'label_it');
    }

    // === Base rate and seasons ===============================================

    public function testStayInsideASingleSeason(): void
    {
        $quote = $this->quote('2027-06-03', '2027-06-06');

        self::assertTrue($quote->isComplete());
        self::assertSame(3000, $quote->totalCents);
        self::assertCount(1, $quote->lines);
        self::assertSame(3, $quote->lines[0]['nights']);
        self::assertSame(1000, $quote->lines[0]['unit_cents']);
        self::assertSame(3, $quote->nights);
    }

    public function testStayAcrossTwoSeasonsIsSplitPerSeason(): void
    {
        $quote = $this->quote('2027-06-13', '2027-06-17'); // nights 13,14 LOW; 15,16 HIGH

        self::assertSame(2 * 1000 + 2 * 2000, $quote->totalCents);
        self::assertSame(['[TEST] Bassa', '[TEST] Alta'], $this->labels($quote));
        self::assertSame([2, 2], array_column($quote->lines, 'nights'));
        self::assertSame(['2027-06-13', '2027-06-15'], array_column($quote->lines, 'start_date'));
        self::assertSame(['2027-06-15', '2027-06-17'], array_column($quote->lines, 'end_date'));
    }

    /** @return array<string, array{string, string, int, int}> */
    public static function seasonBoundaries(): array
    {
        return [
            'checkout on the boundary does not bill the next season' => ['2027-06-13', '2027-06-15', 2000, 1],
            'checkin on the boundary is all next season' => ['2027-06-15', '2027-06-17', 4000, 1],
            'last night of LOW' => ['2027-06-14', '2027-06-15', 1000, 1],
            'first night of HIGH' => ['2027-06-15', '2027-06-16', 2000, 1],
            'one night each side of the boundary' => ['2027-06-14', '2027-06-16', 3000, 2],
            'across three seasons' => ['2027-06-14', '2027-07-02', 1000 + 16 * 2000 + 3000, 3],
            'HIGH into PEAK' => ['2027-06-30', '2027-07-02', 2000 + 3000, 2],
        ];
    }

    #[DataProvider('seasonBoundaries')]
    public function testSeasonBoundaries(string $in, string $out, int $expectedTotal, int $expectedLines): void
    {
        $quote = $this->quote($in, $out);

        self::assertSame($expectedTotal, $quote->totalCents);
        self::assertCount($expectedLines, $quote->lines);
    }

    public function testStayAcrossTheNewYear(): void
    {
        $periods = [
            F::period(1, '[TEST] Natale', '2027-12-20', '2028-01-05', 1500),
            F::period(2, '[TEST] Inverno', '2028-01-05', '2028-02-01', 800),
        ];

        $quote = $this->quote('2027-12-30', '2028-01-07', periods: $periods); // 6 nights at 1500, 2 at 800

        self::assertSame(6 * 1500 + 2 * 800, $quote->totalCents);
    }

    public function testNightsWithoutARateMakeTheQuoteIncompleteInsteadOfInventingAPrice(): void
    {
        $quote = $this->quote('2027-05-30', '2027-06-03'); // 30 and 31 May have no rate

        self::assertFalse($quote->isComplete());
        self::assertNull($quote->totalCents);
        self::assertTrue($quote->hasIssue('no_rate_for_night'));
        self::assertSame([['start_date' => '2027-05-30', 'end_date' => '2027-06-01']], $quote->issues[0]['ranges']);
        self::assertSame(2000, $quote->lines[0]['total_cents'], 'the covered nights are still itemised for the admin');
    }

    public function testGapInTheMiddleAndAtTheEnd(): void
    {
        $periods = [
            F::period(1, '[TEST] A', '2027-06-01', '2027-06-10', 1000),
            F::period(2, '[TEST] B', '2027-06-12', '2027-06-20', 2000),
        ];
        $middle = $this->quote('2027-06-08', '2027-06-14', periods: $periods);
        self::assertSame([['start_date' => '2027-06-10', 'end_date' => '2027-06-12']], $middle->issues[0]['ranges']);
        self::assertCount(2, $middle->lines);

        $end = $this->quote('2027-07-31', '2027-08-02'); // PEAK ends 1 August
        self::assertSame([['start_date' => '2027-08-01', 'end_date' => '2027-08-02']], $end->issues[0]['ranges']);
    }

    public function testNoRatesAtAll(): void
    {
        $quote = $this->quote('2027-06-03', '2027-06-06', periods: []);

        self::assertNull($quote->totalCents);
        self::assertSame([], $quote->lines);
        self::assertSame([['start_date' => '2027-06-03', 'end_date' => '2027-06-06']], $quote->issues[0]['ranges']);
    }

    public function testInactivePeriodsAndOtherApartmentsAreIgnored(): void
    {
        $periods = [
            F::period(1, '[TEST] Disattiva', '2027-06-01', '2027-06-15', 1000, null, false),
            F::period(2, '[TEST] Altro', '2027-06-01', '2027-06-15', 9999, null, true, F::OTHER_APARTMENT),
        ];

        $quote = $this->quote('2027-06-03', '2027-06-06', periods: $periods);

        self::assertNull($quote->totalCents);
        self::assertTrue($quote->hasIssue('no_rate_for_night'));
    }

    public function testOverlappingActivePeriodsAreReportedNotGuessed(): void
    {
        $periods = [
            F::period(1, '[TEST] A', '2027-06-01', '2027-06-10', 1000),
            F::period(2, '[TEST] B', '2027-06-05', '2027-06-15', 2000),
        ];

        $quote = $this->quote('2027-06-04', '2027-06-08', periods: $periods); // nights 5,6,7 are covered twice

        self::assertNull($quote->totalCents);
        self::assertTrue($quote->hasIssue('ambiguous_rate'));
    }

    public function testBaseRateMayBeZeroForPerPersonPricing(): void
    {
        $periods = [F::period(1, '[TEST] A persona', '2027-06-01', '2027-06-15', 0)];
        $rules = [F::rule(1, 'adult', 'per_night', 500)];

        self::assertSame(2 * 500 * 3, $this->quote('2027-06-03', '2027-06-06', periods: $periods, rules: $rules)->totalCents);
    }

    // === Adults, children, pets ================================================

    /** @return array<string, array{int, int}> adults => expected total (3 nights = 3000 base; 2 adults included, 500/night each extra) */
    public static function adultCases(): array
    {
        return ['1 adult' => [1, 3000], 'exactly the included' => [2, 3000], 'one extra' => [3, 3000 + 1500], 'two extra' => [4, 3000 + 3000]];
    }

    #[DataProvider('adultCases')]
    public function testExtraAdultsAboveTheIncludedOnesAreCharged(int $adults, int $expected): void
    {
        $quote = $this->quote('2027-06-03', '2027-06-06', adults: $adults, rules: [F::rule(1, 'adult', 'per_night', 500, 2)]);

        self::assertSame($expected, $quote->totalCents);
    }

    /** @return array<string, array{int, int}> children => expected (first child free, 300/night each other) */
    public static function childCases(): array
    {
        return ['none' => [0, 3000], 'first is free' => [1, 3000], 'second pays' => [2, 3000 + 900], 'third pays too' => [3, 3000 + 1800]];
    }

    #[DataProvider('childCases')]
    public function testChildrenAboveTheFreeOnesAreCharged(int $children, int $expected): void
    {
        $quote = $this->quote('2027-06-03', '2027-06-06', children: $children, rules: [F::rule(1, 'child', 'per_night', 300, 1)]);

        self::assertSame($expected, $quote->totalCents);
    }

    public function testAdultAndChildRulesCombine(): void
    {
        $rules = [F::rule(1, 'adult', 'per_night', 500, 2), F::rule(2, 'child', 'per_night', 300, 1)];

        $quote = $this->quote('2027-06-03', '2027-06-06', adults: 3, children: 2, rules: $rules);

        self::assertSame(3000 + 1 * 500 * 3 + 1 * 300 * 3, $quote->totalCents);
        self::assertSame(['base', 'adult', 'child'], array_column($quote->lines, 'kind'));
    }

    public function testPetsPerStayAndPerNight(): void
    {
        $perStay = [F::rule(1, 'pet', 'per_stay', 700)];
        self::assertSame(3000, $this->quote('2027-06-03', '2027-06-06', pets: 0, rules: $perStay)->totalCents);
        self::assertSame(3000 + 700, $this->quote('2027-06-03', '2027-06-06', pets: 1, rules: $perStay)->totalCents);
        self::assertSame(3000 + 1400, $this->quote('2027-06-03', '2027-06-06', pets: 2, rules: $perStay)->totalCents);

        $perNight = [F::rule(1, 'pet', 'per_night', 200)];
        self::assertSame(3000 + 2 * 200 * 3, $this->quote('2027-06-03', '2027-06-06', pets: 2, rules: $perNight)->totalCents);
    }

    public function testFirstPetCanBeFree(): void
    {
        $rules = [F::rule(1, 'pet', 'per_stay', 700, 1)];

        self::assertSame(3000, $this->quote('2027-06-03', '2027-06-06', pets: 1, rules: $rules)->totalCents);
        self::assertSame(3000 + 700, $this->quote('2027-06-03', '2027-06-06', pets: 2, rules: $rules)->totalCents);
    }

    public function testZeroAmountRulesProduceNoLine(): void
    {
        $quote = $this->quote('2027-06-03', '2027-06-06', adults: 5, rules: [F::rule(1, 'adult', 'per_night', 0)]);

        self::assertCount(1, $quote->lines);
        self::assertSame(3000, $quote->totalCents);
    }

    // === Supplements ============================================================

    public function testFlatSupplementIsChargedOncePerStay(): void
    {
        $quote = $this->quote('2027-06-03', '2027-06-06', rules: [F::rule(1, 'stay', 'per_stay', 5000, 0, null, null, null, true, 0, '[TEST] Pulizia')]);

        self::assertSame(3000 + 5000, $quote->totalCents);
        $line = $quote->lines[1];
        self::assertSame('supplement', $line['kind']);
        self::assertSame(1, $line['quantity']);
        self::assertNull($line['nights']);
        self::assertSame('[TEST] Pulizia', $line['label_it']);
    }

    public function testPerNightSupplementScalesWithTheNights(): void
    {
        $quote = $this->quote('2027-06-03', '2027-06-06', rules: [F::rule(1, 'stay', 'per_night', 100)]);

        self::assertSame(3000 + 3 * 100, $quote->totalCents);
    }

    public function testPerNightSupplementCountsOnlyTheNightsInsideItsWindow(): void
    {
        // Window [15 Jun, 20 Jun); the stay 13-17 Jun has nights 15 and 16 inside it.
        $rules = [F::rule(1, 'stay', 'per_night', 400, 0, null, '2027-06-15', '2027-06-20')];

        $quote = $this->quote('2027-06-13', '2027-06-17', rules: $rules);

        self::assertSame(6000 + 2 * 400, $quote->totalCents);
        self::assertSame(2, $quote->lines[2]['nights']);
    }

    public function testSupplementWindowEndsBeforeOrStartsAfterTheStay(): void
    {
        $after = [F::rule(1, 'stay', 'per_night', 400, 0, null, '2027-06-20', '2027-06-25')];
        $before = [F::rule(1, 'stay', 'per_night', 400, 0, null, '2027-05-01', '2027-06-03')]; // window ends the day the stay starts

        self::assertSame(3000, $this->quote('2027-06-03', '2027-06-06', rules: $after)->totalCents);
        self::assertSame(3000, $this->quote('2027-06-03', '2027-06-06', rules: $before)->totalCents);
    }

    /** @return array<string, array{string, string, bool}> */
    public static function perStayWindowCases(): array
    {
        return [
            'check-in inside the window' => ['2027-06-13', '2027-06-16', true],
            'check-in on the window end day (half-open)' => ['2027-06-14', '2027-06-16', false],
            'check-in before the window even if the stay enters it' => ['2027-06-09', '2027-06-12', false],
            'check-in on the window start day' => ['2027-06-10', '2027-06-12', true],
        ];
    }

    #[DataProvider('perStayWindowCases')]
    public function testPerStaySupplementWithAWindowFollowsTheCheckInDate(string $in, string $out, bool $applies): void
    {
        $rules = [F::rule(1, 'stay', 'per_stay', 5000, 0, null, '2027-06-10', '2027-06-14')];

        $quote = $this->quote($in, $out, rules: $rules);
        $base = $this->quote($in, $out);

        self::assertSame($base->totalCents + ($applies ? 5000 : 0), $quote->totalCents);
    }

    public function testGlobalAndApartmentRulesAreAdditiveAndOthersAreIgnored(): void
    {
        $rules = [
            F::rule(1, 'stay', 'per_stay', 1000, 0, null),                   // every apartment
            F::rule(2, 'stay', 'per_stay', 2000, 0, F::APARTMENT),           // this apartment
            F::rule(3, 'stay', 'per_stay', 4000, 0, F::OTHER_APARTMENT),     // another apartment
            F::rule(4, 'stay', 'per_stay', 8000, 0, null, null, null, false), // inactive
        ];

        self::assertSame(3000 + 1000 + 2000, $this->quote('2027-06-03', '2027-06-06', rules: $rules)->totalCents);
    }

    public function testRuleLinesFollowSortOrderThenId(): void
    {
        $rules = [
            F::rule(10, 'stay', 'per_stay', 100, 0, null, null, null, true, 5, '[TEST] dieci'),
            F::rule(11, 'stay', 'per_stay', 100, 0, null, null, null, true, 1, '[TEST] undici'),
            F::rule(9, 'stay', 'per_stay', 100, 0, null, null, null, true, 5, '[TEST] nove'),
        ];

        self::assertSame(['[TEST] Bassa', '[TEST] undici', '[TEST] nove', '[TEST] dieci'], $this->labels($this->quote('2027-06-03', '2027-06-06', rules: $rules)));
    }

    // === Minimum stay ===========================================================

    public function testStayBelowTheMinimumIsAnIssueAndMeetingItIsNot(): void
    {
        $periods = F::seasons(lowMin: 3);

        $short = $this->quote('2027-06-03', '2027-06-05', periods: $periods);
        self::assertSame(3, $short->minNights);
        self::assertCount(1, $short->blockingIssues());
        self::assertSame(['code' => 'below_min_nights', 'severity' => 'blocking', 'min_nights' => 3, 'nights' => 2], $short->blockingIssues()[0]);
        self::assertSame(2000, $short->totalCents, 'the price is still computed');

        self::assertSame([], $this->quote('2027-06-03', '2027-06-06', periods: $periods)->blockingIssues());
        self::assertSame([], $this->quote('2027-06-03', '2027-06-10', periods: $periods)->blockingIssues());
    }

    public function testNoMinimumConfiguredMeansNoRestriction(): void
    {
        $quote = $this->quote('2027-06-03', '2027-06-04');

        self::assertNull($quote->minNights);
        self::assertSame([], $quote->issues);
    }

    public function testTheMinimumIsTheOneOfThePeriodOfTheCheckInNight(): void
    {
        $periods = F::seasons(lowMin: 2, highMin: 7);

        // Check-in in LOW (min 2): a 3-night stay is fine although it enters HIGH (min 7).
        self::assertSame([], $this->quote('2027-06-13', '2027-06-16', periods: $periods)->blockingIssues());
        // Check-in in HIGH (min 7): the same length is too short.
        $short = $this->quote('2027-06-15', '2027-06-18', periods: $periods);
        self::assertSame(7, $short->blockingIssues()[0]['min_nights']);
        // One night in LOW is below its minimum of 2.
        self::assertSame(2, $this->quote('2027-06-14', '2027-06-15', periods: $periods)->blockingIssues()[0]['min_nights']);
    }

    public function testNoMinimumAppliesWhenTheCheckInNightHasNoRate(): void
    {
        $quote = $this->quote('2027-05-31', '2027-06-03', periods: F::seasons(lowMin: 10));

        self::assertNull($quote->minNights);
        self::assertSame([], $quote->blockingIssues());
    }

    // === Robustness, determinism, output ========================================

    public function testHugeAmountsNeverOverflowTheStoredTotal(): void
    {
        $periods = [F::period(1, '[TEST] Lungo', '2027-06-01', '2027-12-01', 1000)];
        $rules = [F::rule(1, 'adult', 'per_night', 10_000_000)];

        $quote = $this->quote('2027-06-01', '2027-07-31', adults: 20, periods: $periods, rules: $rules); // 20 x 10M x 60 nights

        self::assertNull($quote->totalCents);
        self::assertTrue($quote->hasIssue('total_out_of_range'));
    }

    public function testIntegerArithmeticIsExactForOddCents(): void
    {
        $periods = [F::period(1, '[TEST] Dispari', '2027-06-01', '2027-06-15', 1)];
        $rules = [F::rule(1, 'adult', 'per_night', 7, 0), F::rule(2, 'stay', 'per_stay', 3)];

        self::assertSame(5 * 1 + 2 * 7 * 5 + 3, $this->quote('2027-06-03', '2027-06-08', adults: 2, periods: $periods, rules: $rules)->totalCents);
    }

    public function testSameInputGivesIdenticalResults(): void
    {
        $rules = [F::rule(1, 'adult', 'per_night', 500, 2), F::rule(2, 'stay', 'per_stay', 5000)];

        $a = $this->quote('2027-06-13', '2027-06-17', adults: 3, rules: $rules);
        $b = $this->quote('2027-06-13', '2027-06-17', adults: 3, rules: array_reverse($rules));

        self::assertSame($a->toSnapshot(), $b->toSnapshot());
    }

    public function testSnapshotIsStableJsonWithTheDocumentedShape(): void
    {
        $snapshot = $this->quote('2027-06-13', '2027-06-17')->toSnapshot();

        self::assertSame(1, $snapshot['version']);
        self::assertSame('EUR', $snapshot['currency']);
        self::assertSame('quoted', $snapshot['status']);
        self::assertSame(4, $snapshot['nights']);
        self::assertSame(6000, $snapshot['total_cents']);
        self::assertJson(json_encode($snapshot, JSON_THROW_ON_ERROR));

        $unquoted = $this->quote('2027-05-30', '2027-06-03')->toSnapshot();
        self::assertSame('unquoted', $unquoted['status']);
        self::assertNull($unquoted['total_cents']);
    }

    public function testSummaryInItalianAndEnglish(): void
    {
        $rules = [F::rule(1, 'adult', 'per_night', 500, 2, null, null, null, true, 0, '[TEST] Adulto extra', '[TEST] Extra adult')];
        $quote = $this->quote('2027-06-13', '2027-06-17', adults: 3, rules: $rules);

        $it = $quote->summary('it');
        self::assertSame(['label' => '[TEST] Bassa', 'detail' => '2 notti × € 10,00', 'amount' => '€ 20,00'], $it['lines'][0]);
        self::assertSame(['label' => '[TEST] Adulto extra', 'detail' => '1 × € 5,00 × 4 notti', 'amount' => '€ 20,00'], $it['lines'][2]);
        self::assertSame('€ 80,00', $it['total']);
        self::assertNull($it['note']);

        $en = $quote->summary('en');
        self::assertSame(['label' => '[TEST] Low', 'detail' => '2 nights × €10.00', 'amount' => '€20.00'], $en['lines'][0]);
        self::assertSame('[TEST] Extra adult', $en['lines'][2]['label']);
        self::assertSame('€80.00', $en['total']);
    }

    public function testSummaryUsesSingularForOneNightAndFallsBackToItalianLabels(): void
    {
        $periods = [F::period(1, '[TEST] Solo italiano', '2027-06-01', '2027-06-15', 1234)];

        $quote = $this->quote('2027-06-03', '2027-06-04', periods: $periods);

        self::assertSame('1 notte × € 12,34', $quote->summary('it')['lines'][0]['detail']);
        self::assertSame('1 night × €12.34', $quote->summary('en')['lines'][0]['detail']);
        self::assertSame('[TEST] Solo italiano', $quote->summary('en')['lines'][0]['label'], 'missing English label falls back to Italian');
    }

    public function testIncompleteQuoteIsPresentedAsPriceToBeConfirmed(): void
    {
        $quote = $this->quote('2027-05-30', '2027-06-03');

        self::assertNull($quote->summary('it')['total']);
        self::assertSame('Prezzo da confermare', $quote->summary('it')['note']);
        self::assertSame('Price to be confirmed', $quote->summary('en')['note']);
    }
}
