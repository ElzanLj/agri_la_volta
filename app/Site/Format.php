<?php

declare(strict_types=1);

namespace App\Site;

use App\Domain\GuestCounts;
use App\Domain\StayDates;
use DateTimeImmutable;

/** Dates and guest counts as text, in the language of the page. */
final class Format
{
    /** "12/06/2027" (IT) / "12 June 2027" (EN) from "YYYY-MM-DD". */
    public static function date(string $ymd, string $locale): string
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $ymd);
        if ($date === false) {
            return $ymd;
        }
        return $locale === 'en' ? $date->format('j F Y') : $date->format('d/m/Y');
    }

    public static function nights(StayDates $stay, string $locale): string
    {
        $n = $stay->nights();
        return Text::get($n === 1 ? 'unit.night' : 'unit.nights', $locale, ['n' => (string) $n]);
    }

    /** "2 adulti, 1 bambino, 1 animale"; zero children or pets are left out. */
    public static function guests(GuestCounts $guests, string $locale): string
    {
        $parts = [Text::get($guests->adults === 1 ? 'unit.adult' : 'unit.adults', $locale, ['n' => (string) $guests->adults])];
        if ($guests->children > 0) {
            $parts[] = Text::get($guests->children === 1 ? 'unit.child' : 'unit.children', $locale, ['n' => (string) $guests->children]);
        }
        if ($guests->pets > 0) {
            $parts[] = Text::get($guests->pets === 1 ? 'unit.pet' : 'unit.pets', $locale, ['n' => (string) $guests->pets]);
        }
        return implode(', ', $parts);
    }
}
