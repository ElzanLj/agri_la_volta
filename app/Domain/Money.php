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

    /** Plain amount for form fields, e.g. 1050 => "10,50". */
    public static function plain(int $cents): string
    {
        return intdiv($cents, 100) . ',' . sprintf('%02d', $cents % 100);
    }

    /**
     * Parses an amount typed by the admin ("80", "80,5", "80.50") into cents.
     * Returns null when it is not a plain non-negative amount with at most 2 decimals.
     */
    public static function parse(string $text): ?int
    {
        if (!preg_match('/^(\d{1,7})(?:[.,](\d{1,2}))?$/', trim($text), $m)) {
            return null;
        }
        $decimals = isset($m[2]) ? (int) str_pad($m[2], 2, '0') : 0;
        return (int) $m[1] * 100 + $decimals;
    }
}
