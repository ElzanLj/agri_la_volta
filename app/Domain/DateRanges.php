<?php

declare(strict_types=1);

namespace App\Domain;

use DateTimeImmutable;
use DateTimeZone;

final class DateRanges
{
    /**
     * Groups sorted "YYYY-MM-DD" nights into contiguous half-open ranges.
     *
     * @param list<string> $nights
     * @return list<array{start_date: string, end_date: string}>
     */
    public static function fromNights(array $nights): array
    {
        $ranges = [];
        $start = null;
        $previous = null;

        foreach ($nights as $night) {
            if ($previous === null || self::addDays($previous, 1) !== $night) {
                if ($start !== null) {
                    $ranges[] = ['start_date' => $start, 'end_date' => self::addDays($previous, 1)];
                }
                $start = $night;
            }
            $previous = $night;
        }
        if ($start !== null) {
            $ranges[] = ['start_date' => $start, 'end_date' => self::addDays($previous, 1)];
        }

        return $ranges;
    }

    public static function addDays(string $date, int $days): string
    {
        return (new DateTimeImmutable($date, new DateTimeZone('UTC')))->modify(($days >= 0 ? '+' : '') . $days . ' days')->format('Y-m-d');
    }
}
