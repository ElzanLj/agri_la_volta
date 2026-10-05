<?php

declare(strict_types=1);

namespace App\Support;

use DateTimeImmutable;

/**
 * WhatsApp "click to chat" links (SPEC §19): a plain https://wa.me/<number>?text=<message> URL.
 * No API is involved; the text is only pre-filled and the sender can edit it before sending.
 */
final class WhatsApp
{
    private const MIN_DIGITS = 8;
    private const MAX_DIGITS = 15; // E.164

    /**
     * Turns a phone number typed by a person into the digits wa.me expects: country code
     * included, no "+", no "00", no spaces. Returns null when it cannot be a valid number.
     *
     * "+39 333 123 4567", "0039 333 1234567", "333-123.4567" (national, default country code),
     * "39 333 1234567" (country code typed without "+") all become 393331234567.
     */
    public static function normalize(string $raw, string $defaultCountryCode = '39'): ?string
    {
        $raw = trim($raw);
        if ($raw === '' || strlen($raw) > 40 || preg_match('/[^0-9+()\-.\/\s]/', $raw)) {
            return null; // letters (extensions, notes) or exotic characters: not a plain number
        }
        $plus = substr_count($raw, '+');
        if ($plus > 1 || ($plus === 1 && !str_starts_with($raw, '+'))) {
            return null;
        }

        $digits = (string) preg_replace('/\D/', '', $raw);
        $cc = (string) preg_replace('/\D/', '', $defaultCountryCode);
        $cc = $cc === '' ? '39' : $cc;

        if ($plus === 1) {
            // already international
        } elseif (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        } else {
            // National format, or the country code typed without "+" (recognised by its length).
            $alreadyHasCountryCode = str_starts_with($digits, $cc) && strlen($digits) >= strlen($cc) + 9;
            if (!$alreadyHasCountryCode) {
                if ($cc !== '39' && str_starts_with($digits, '0')) {
                    $digits = substr($digits, 1); // trunk prefix; Italian numbers keep their leading 0
                }
                $digits = $cc . $digits;
            }
        }

        if ($digits === '' || $digits[0] === '0' || strlen($digits) < self::MIN_DIGITS || strlen($digits) > self::MAX_DIGITS) {
            return null;
        }
        return $digits;
    }

    /** @param string $digits the output of normalize() */
    public static function link(string $digits, ?string $text = null): string
    {
        if (!preg_match('/^[1-9]\d{7,14}\z/', $digits)) {
            throw new \InvalidArgumentException('WhatsApp number must be 8-15 digits without a leading zero.');
        }
        $url = 'https://wa.me/' . $digits;
        return ($text === null || $text === '') ? $url : $url . '?text=' . rawurlencode($text);
    }

    /** Link for a phone number as stored in the database, or null when it is not usable. */
    public static function linkForPhone(?string $phone, string $text, string $defaultCountryCode = '39'): ?string
    {
        $digits = self::normalize((string) $phone, $defaultCountryCode);
        return $digits === null ? null : self::link($digits, $text);
    }

    /**
     * Message the admin sends to a guest about a request or booking.
     *
     * @param array{first_name: string, reference: ?string, apartment: string, check_in: string, check_out: string, adults: int, children: int, pets: int} $d
     */
    public static function customerMessage(string $locale, array $d): string
    {
        $dates = self::dateRange($locale, $d['check_in'], $d['check_out']);
        $guests = self::guests($locale, $d['adults'], $d['children'], $d['pets']);

        if ($locale === 'en') {
            return 'Hello ' . $d['first_name'] . ', I am contacting you about your request'
                . ($d['reference'] ? ' ' . $d['reference'] : '') . ' for apartment ' . $d['apartment']
                . ' ' . $dates . ' for ' . $guests . '.';
        }
        return 'Buongiorno ' . $d['first_name'] . ', la contatto in merito alla sua richiesta'
            . ($d['reference'] ? ' ' . $d['reference'] : '') . ' per l\'appartamento ' . $d['apartment']
            . ' ' . $dates . ' per ' . $guests . '.';
    }

    /**
     * Message a visitor sends to the farm (public WhatsApp button). Every part is optional.
     * Example (SPEC §19): "Buongiorno, vorrei informazioni sull'appartamento Mimosa dal 12/06 al 16/06 per 2 adulti e 1 bambino."
     */
    public static function businessMessage(string $locale, ?string $apartment = null, ?string $checkIn = null, ?string $checkOut = null, ?int $adults = null, ?int $children = null): string
    {
        $en = $locale === 'en';
        $text = $en ? 'Hello, I would like some information' : 'Buongiorno, vorrei informazioni';

        if ($apartment !== null && $apartment !== '') {
            $text .= $en ? ' about apartment ' . $apartment : ' sull\'appartamento ' . $apartment;
        }
        if ($checkIn !== null && $checkOut !== null && $checkIn !== '' && $checkOut !== '') {
            $text .= ' ' . self::dateRange($locale, $checkIn, $checkOut);
        }
        if ($adults !== null && $adults > 0) {
            $text .= ($en ? ' for ' : ' per ') . self::guests($locale, $adults, $children ?? 0, 0);
        }
        return $text . '.';
    }

    /** "dal 12/06 al 16/06" / "from 12 Jun to 16 Jun" */
    private static function dateRange(string $locale, string $checkIn, string $checkOut): string
    {
        $en = $locale === 'en';
        $format = static function (string $ymd) use ($en): string {
            $d = DateTimeImmutable::createFromFormat('!Y-m-d', $ymd);
            return $d === false ? $ymd : $d->format($en ? 'j M' : 'd/m');
        };
        return ($en ? 'from ' : 'dal ') . $format($checkIn) . ($en ? ' to ' : ' al ') . $format($checkOut);
    }

    /** "2 adulti e 1 bambino" / "2 adults, 1 child and 1 pet" */
    private static function guests(string $locale, int $adults, int $children, int $pets): string
    {
        $en = $locale === 'en';
        $parts = [];
        if ($adults > 0) {
            $parts[] = $adults . ' ' . ($en ? ($adults === 1 ? 'adult' : 'adults') : ($adults === 1 ? 'adulto' : 'adulti'));
        }
        if ($children > 0) {
            $parts[] = $children . ' ' . ($en ? ($children === 1 ? 'child' : 'children') : ($children === 1 ? 'bambino' : 'bambini'));
        }
        if ($pets > 0) {
            $parts[] = $pets . ' ' . ($en ? ($pets === 1 ? 'pet' : 'pets') : ($pets === 1 ? 'animale' : 'animali'));
        }
        if ($parts === []) {
            return $en ? 'the stay' : 'il soggiorno';
        }
        $last = array_pop($parts);
        return $parts === [] ? $last : implode(', ', $parts) . ($en ? ' and ' : ' e ') . $last;
    }
}
