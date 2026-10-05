<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\DateRanges;
use App\Domain\Money;
use App\Domain\NullPriceQuoter;
use App\Domain\GuestCounts;
use App\Domain\StayDates;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MoneyAndRangesTest extends TestCase
{
    /** @return array<string, array{int, string, string}> */
    public static function amounts(): array
    {
        return [
            'zero' => [0, '€ 0,00', '€0.00'],
            'cents only' => [5, '€ 0,05', '€0.05'],
            'one euro' => [100, '€ 1,00', '€1.00'],
            'thousands' => [123450, '€ 1.234,50', '€1,234.50'],
            'millions' => [123456789, '€ 1.234.567,89', '€1,234,567.89'],
            'negative' => [-250, '-€ 2,50', '-€2.50'],
        ];
    }

    #[DataProvider('amounts')]
    public function testFormat(int $cents, string $it, string $en): void
    {
        self::assertSame($it, Money::format($cents, 'it'));
        self::assertSame($en, Money::format($cents, 'en'));
        self::assertSame($it, Money::format($cents), 'Italian is the default');
    }

    public function testNightsAreGroupedIntoContiguousRanges(): void
    {
        self::assertSame([], DateRanges::fromNights([]));
        self::assertSame(
            [['start_date' => '2027-06-10', 'end_date' => '2027-06-11']],
            DateRanges::fromNights(['2027-06-10']),
        );
        self::assertSame(
            [
                ['start_date' => '2027-06-29', 'end_date' => '2027-07-02'],
                ['start_date' => '2027-07-05', 'end_date' => '2027-07-06'],
                ['start_date' => '2027-07-08', 'end_date' => '2027-07-10'],
            ],
            DateRanges::fromNights(['2027-06-29', '2027-06-30', '2027-07-01', '2027-07-05', '2027-07-08', '2027-07-09']),
        );
    }

    public function testNullQuoterNeverInventsAPrice(): void
    {
        $quote = (new NullPriceQuoter())->quote(1, StayDates::fromStrings('2027-06-10', '2027-06-13'), GuestCounts::from(2));

        self::assertNull($quote->totalCents);
        self::assertTrue($quote->hasIssue('pricing_not_configured'));
        self::assertSame(3, $quote->nights);
    }
}
