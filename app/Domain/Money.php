<?php

declare(strict_types=1);

namespace App\Domain;

/** Formats integer cents for display. No floating point is used for amounts. */
final class Money
{
    public static function format(int $cents, string $locale = 'it'): string
    {
        $sign = $cents < 0 ? '-' : '';
        $abs = abs($cents);
        $euros = intdiv($abs, 100);
        $rest = sprintf('%02d', $abs % 100);

        if ($locale === 'en') {
            return $sign . '€' . number_format($euros, 0, '.', ',') . '.' . $rest;
        }
        return $sign . '€ ' . number_format($euros, 0, ',', '.') . ',' . $rest;
    }
}
