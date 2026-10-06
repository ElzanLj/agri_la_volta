<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Config;
use App\Site\Amenities;
use App\Site\Contacts;
use PHPUnit\Framework\TestCase;

/** Amenities (one per line) and the Google Maps link built from the configured address. */
final class AmenitiesAndMapsTest extends TestCase
{
    public function testLinesAreTrimmedAndEmptyOnesDropped(): void
    {
        self::assertSame(['Piscina', 'Posto auto', 'Wi-Fi'], Amenities::lines("  Piscina \r\n\r\nPosto auto\n\n   \nWi-Fi  "));
        self::assertSame([], Amenities::lines(null));
        self::assertSame([], Amenities::lines(''));
        self::assertSame([], Amenities::lines("  \n \r\n"));
    }

    public function testNormaliseKeepsOneEntryPerLineOrNull(): void
    {
        self::assertSame("A\nB", Amenities::normalise("A\r\n\r\n  B  "));
        self::assertNull(Amenities::normalise("   \n  "));
    }

    public function testValidationLimitsLineLengthAndCount(): void
    {
        self::assertTrue(Amenities::isValid(''));
        self::assertTrue(Amenities::isValid(str_repeat('a', Amenities::MAX_LINE_LENGTH)));
        self::assertFalse(Amenities::isValid(str_repeat('a', Amenities::MAX_LINE_LENGTH + 1)));
        self::assertTrue(Amenities::isValid(implode("\n", array_fill(0, Amenities::MAX_LINES, 'x'))));
        self::assertFalse(Amenities::isValid(implode("\n", array_fill(0, Amenities::MAX_LINES + 1, 'x'))));
        self::assertTrue(Amenities::isValid(implode("\n", array_fill(0, 50, '')) . "x"), 'blank lines do not count');
        self::assertTrue(Amenities::isValid(str_repeat('è', Amenities::MAX_LINE_LENGTH)), 'length counts characters, not bytes');
    }

    public function testNoMapsLinkWithoutAnAddress(): void
    {
        self::assertNull(Contacts::fromConfig(new Config([]))->mapsLink());
        self::assertNull(Contacts::fromConfig(new Config(['PUBLIC_ADDRESS' => " \n "]))->mapsLink());
    }

    public function testMapsLinkIsAPlainSearchLinkWithTheAddressOnOneEncodedLine(): void
    {
        $link = Contacts::fromConfig(new Config(['PUBLIC_ADDRESS' => "Via Esempio 1\n43000 Parma & dintorni"]))->mapsLink();

        self::assertSame('https://www.google.com/maps/search/?api=1&query=Via%20Esempio%201%2C%2043000%20Parma%20%26%20dintorni', $link);
        self::assertStringNotContainsString("\n", (string) $link);
    }
}
