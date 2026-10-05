<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\StayDates;
use App\Domain\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class StayDatesTest extends TestCase
{
    /** @return array<string, array{string}> */
    public static function invalidDates(): array
    {
        return [
            'empty' => [''],
            'text' => ['abc'],
            'day-month-year' => ['10-06-2027'],
            'slashes' => ['2027/06/10'],
            'no zero padding' => ['2027-6-1'],
            'two digit year' => ['27-06-10'],
            'month 13' => ['2027-13-01'],
            'day 32' => ['2027-01-32'],
            'day zero' => ['2027-06-00'],
            '30 February' => ['2027-02-30'],
            '29 February non-leap year' => ['2027-02-29'],
            '31 April' => ['2027-04-31'],
            'trailing newline' => ["2027-06-10\n"],
            'trailing space' => ['2027-06-10 '],
            'with time' => ['2027-06-10 12:00'],
            'before supported range' => ['1999-12-31'],
            'after supported range' => ['2101-01-01'],
        ];
    }

    #[DataProvider('invalidDates')]
    public function testInvalidCheckInIsRejected(string $invalid): void
    {
        try {
            StayDates::fromStrings($invalid, '2027-06-15');
            self::fail('Expected a ValidationException');
        } catch (ValidationException $e) {
            self::assertSame(['check_in' => 'invalid_date'], $e->errors());
        }
    }

    #[DataProvider('invalidDates')]
    public function testInvalidCheckOutIsRejected(string $invalid): void
    {
        try {
            StayDates::fromStrings('2027-06-10', $invalid);
            self::fail('Expected a ValidationException');
        } catch (ValidationException $e) {
            self::assertSame(['check_out' => 'invalid_date'], $e->errors());
        }
    }

    public function testBothDatesInvalidReportsBothFields(): void
    {
        try {
            StayDates::fromStrings('x', 'y');
            self::fail('Expected a ValidationException');
        } catch (ValidationException $e) {
            self::assertSame(['check_in' => 'invalid_date', 'check_out' => 'invalid_date'], $e->errors());
        }
    }

    public function testLeapDayIsAcceptedInLeapYear(): void
    {
        self::assertSame(1, StayDates::fromStrings('2028-02-29', '2028-03-01')->nights());
    }

    public function testCheckOutEqualToCheckInIsRejected(): void
    {
        $this->expectValidation(['check_out' => 'check_out_not_after_check_in'], '2027-06-10', '2027-06-10');
    }

    public function testCheckOutBeforeCheckInIsRejected(): void
    {
        $this->expectValidation(['check_out' => 'check_out_not_after_check_in'], '2027-06-15', '2027-06-10');
    }

    /** @return array<string, array{string, string, int}> */
    public static function nightCounts(): array
    {
        return [
            'one night' => ['2027-06-10', '2027-06-11', 1],
            'five nights' => ['2027-06-10', '2027-06-15', 5],
            'across month end' => ['2027-01-30', '2027-02-02', 3],
            'non-leap February end' => ['2027-02-28', '2027-03-01', 1],
            'leap February end' => ['2028-02-28', '2028-03-01', 2],
            'across new year' => ['2027-12-30', '2028-01-02', 3],
            'across DST end (Europe, 31 Oct 2027)' => ['2027-10-30', '2027-11-02', 3],
            'across DST start (Europe, 28 Mar 2027)' => ['2027-03-27', '2027-03-30', 3],
            'exactly 60 nights' => ['2027-06-01', '2027-07-31', 60],
        ];
    }

    #[DataProvider('nightCounts')]
    public function testNights(string $in, string $out, int $expected): void
    {
        self::assertSame($expected, StayDates::fromStrings($in, $out)->nights());
    }

    public function testStayLongerThanTechnicalLimitIsRejected(): void
    {
        $this->expectValidation(['check_out' => 'stay_too_long'], '2027-06-01', '2027-08-01'); // 61 nights
    }

    public function testBlocksAllowLongerIntervals(): void
    {
        self::assertSame(366, StayDates::fromStrings('2027-01-01', '2028-01-02', StayDates::MAX_NIGHTS_BLOCK)->nights());
        $this->expectValidation(['check_out' => 'stay_too_long'], '2027-01-01', '2028-01-03', StayDates::MAX_NIGHTS_BLOCK);
    }

    /**
     * Reference interval A = [10 June, 15 June). Each case is B and whether it shares a night with A.
     *
     * @return array<string, array{string, string, bool}>
     */
    public static function overlapCases(): array
    {
        return [
            'same-day turnover: B starts on A check-out' => ['2027-06-15', '2027-06-18', false],
            'same-day turnover: B ends on A check-in' => ['2027-06-08', '2027-06-10', false],
            'B entirely after A' => ['2027-06-16', '2027-06-20', false],
            'B entirely before A' => ['2027-06-01', '2027-06-09', false],
            'one-night B right after A' => ['2027-06-15', '2027-06-16', false],
            'one-night B right before A' => ['2027-06-09', '2027-06-10', false],
            'partial: B overlaps the end of A' => ['2027-06-12', '2027-06-18', true],
            'partial: B overlaps the start of A' => ['2027-06-08', '2027-06-12', true],
            'partial: only the last night' => ['2027-06-14', '2027-06-16', true],
            'partial: only the first night' => ['2027-06-09', '2027-06-11', true],
            'total: B inside A' => ['2027-06-11', '2027-06-13', true],
            'total: B contains A' => ['2027-06-05', '2027-06-20', true],
            'total: identical' => ['2027-06-10', '2027-06-15', true],
            'one-night B inside A' => ['2027-06-12', '2027-06-13', true],
            'one-night B on first night' => ['2027-06-10', '2027-06-11', true],
            'one-night B on last night' => ['2027-06-14', '2027-06-15', true],
        ];
    }

    #[DataProvider('overlapCases')]
    public function testOverlap(string $bIn, string $bOut, bool $expected): void
    {
        $a = StayDates::fromStrings('2027-06-10', '2027-06-15');
        $b = StayDates::fromStrings($bIn, $bOut);

        self::assertSame($expected, $a->overlaps($b));
        self::assertSame($expected, $b->overlaps($a), 'overlap must be symmetric');
    }

    public function testPublicRequestsMustNotStartInThePast(): void
    {
        $this->expectPublicError('check_in_in_past', '2027-01-09');
    }

    public function testPublicRequestsMayStartToday(): void
    {
        StayDates::fromStrings('2027-01-10', '2027-01-12')->assertBookableFromPublic('2027-01-10');
        $this->addToAssertionCount(1);
    }

    public function testPublicRequestsAreLimitedToTwoYearsAhead(): void
    {
        StayDates::fromStrings('2029-01-10', '2029-01-12')->assertBookableFromPublic('2027-01-10');
        $this->addToAssertionCount(1);
        $this->expectPublicError('check_in_too_far', '2029-01-11');
    }

    private function expectPublicError(string $code, string $checkIn): void
    {
        $stay = StayDates::fromStrings($checkIn, date('Y-m-d', strtotime($checkIn . ' +2 days')));
        try {
            $stay->assertBookableFromPublic('2027-01-10');
            self::fail('Expected a ValidationException');
        } catch (ValidationException $e) {
            self::assertSame(['check_in' => $code], $e->errors());
        }
    }

    /** @param array<string, string> $expected */
    private function expectValidation(array $expected, string $in, string $out, int $maxNights = StayDates::MAX_NIGHTS_STAY): void
    {
        try {
            StayDates::fromStrings($in, $out, $maxNights);
            self::fail('Expected a ValidationException');
        } catch (ValidationException $e) {
            self::assertSame($expected, $e->errors());
        }
    }
}
