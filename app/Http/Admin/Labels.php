<?php

declare(strict_types=1);

namespace App\Http\Admin;

use App\Domain\Money;
use DateTimeImmutable;
use DateTimeZone;

/** Display helpers for the admin pages (Italian). */
final class Labels
{
    public const REQUEST_STATUSES = ['pending' => 'In attesa', 'confirmed' => 'Confermata', 'rejected' => 'Rifiutata', 'cancelled' => 'Cancellata'];
    public const BOOKING_STATUSES = ['confirmed' => 'Confermata', 'cancelled' => 'Cancellata'];
    public const ORIGINS = [
        'website' => 'Sito web', 'phone' => 'Telefono', 'email' => 'Email', 'agency' => 'Agenzia', 'novasol' => 'Novasol', 'other' => 'Altro',
    ];
    public const APPLIES_TO = ['adult' => 'Adulto', 'child' => 'Bambino', 'pet' => 'Animale', 'stay' => 'Supplemento fisso'];
    public const BASES = ['per_night' => 'per notte', 'per_stay' => 'per soggiorno'];
    public const ENTITIES = [
        'booking_request' => 'Richiesta', 'booking' => 'Prenotazione', 'availability_block' => 'Blocco',
        'seasonal_rate' => 'Tariffa', 'pricing_rule' => 'Regola di prezzo', 'apartment' => 'Appartamento', 'admin' => 'Amministratore',
    ];

    public static function requestStatus(string $s): string
    {
        return self::REQUEST_STATUSES[$s] ?? $s;
    }

    public static function bookingStatus(string $s): string
    {
        return self::BOOKING_STATUSES[$s] ?? $s;
    }

    public static function origin(string $s): string
    {
        return self::ORIGINS[$s] ?? $s;
    }

    public static function entity(string $s): string
    {
        return self::ENTITIES[$s] ?? $s;
    }

    /** "2027-06-10" => "10/06/2027" */
    public static function date(?string $ymd): string
    {
        if ($ymd === null || $ymd === '') {
            return '—';
        }
        $d = DateTimeImmutable::createFromFormat('!Y-m-d', $ymd);
        return $d === false ? $ymd : $d->format('d/m/Y');
    }

    /** UTC database timestamp => local time for display. */
    public static function dateTime(?string $utc): string
    {
        if ($utc === null || $utc === '') {
            return '—';
        }
        try {
            return (new DateTimeImmutable($utc, new DateTimeZone('UTC')))
                ->setTimezone(new DateTimeZone(date_default_timezone_get()))
                ->format('d/m/Y H:i');
        } catch (\Exception) {
            return $utc;
        }
    }

    public static function money(?int $cents): string
    {
        return $cents === null ? '—' : Money::format($cents, 'it');
    }

    public static function time(?string $time): string
    {
        return $time === null || $time === '' ? '' : substr($time, 0, 5);
    }
}
