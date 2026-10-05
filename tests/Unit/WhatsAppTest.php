<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\WhatsApp;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class WhatsAppTest extends TestCase
{
    /** @return array<string, array{string, ?string}> default country code 39 */
    public static function numbers(): array
    {
        return [
            'international with plus and spaces' => ['+39 333 123 4567', '393331234567'],
            'international compact' => ['+393331234567', '393331234567'],
            'double zero prefix' => ['0039 333 1234567', '393331234567'],
            'national mobile' => ['333 1234567', '393331234567'],
            'national with dashes and dots' => ['333-123.4567', '393331234567'],
            'national with brackets' => ['(333) 123 4567', '393331234567'],
            'country code typed without plus' => ['39 333 1234567', '393331234567'],
            'country code already in the digits' => ['393331234567', '393331234567'],
            'Italian landline keeps its leading zero' => ['0524 587057', '390524587057'],
            'Italian landline international' => ['+39 0524 587057', '390524587057'],
            'UK number' => ['+44 7700 900123', '447700900123'],
            'UK number with 00' => ['0044 7700 900123', '447700900123'],
            'US number' => ['+1 (415) 555-2671', '14155552671'],
            'slashes' => ['333/1234567', '393331234567'],
            'trailing newline' => ["+39 333 1234567\r\n", '393331234567'],
            'empty' => ['', null],
            'blanks' => ['   ', null],
            'text' => ['abc', null],
            'extension note' => ['333 1234567 int. 5', null],
            'too short' => ['+39 333', null],
            'only a plus' => ['+', null],
            'double plus' => ['++39 333 1234567', null],
            'plus in the middle' => ['39+333 1234567', null],
            'starts with zero after the plus' => ['+0 333 1234567', null],
            'too long' => ['+39 333 123 456 789 012', null],
            'html injection' => ['+39 333 1234567"><script>', null],
            'query injection' => ['+39 333 1234567?text=spam', null],
            'javascript url' => ['javascript:alert(1)', null],
            'non ASCII digits' => ['٣٣٣١٢٣٤٥٦٧', null],
            'only zeros' => ['00', null],
            'very long garbage' => [str_repeat('1', 60), null],
        ];
    }

    #[DataProvider('numbers')]
    public function testNormalize(string $raw, ?string $expected): void
    {
        self::assertSame($expected, WhatsApp::normalize($raw));
    }

    public function testAnotherDefaultCountryCode(): void
    {
        self::assertSame('447700900123', WhatsApp::normalize('07700 900123', '44'), 'the UK trunk zero is dropped');
        self::assertSame('447700900123', WhatsApp::normalize('7700 900123', '44'));
        self::assertSame('447700900123', WhatsApp::normalize('44 7700 900123', '44'));
        self::assertSame('393331234567', WhatsApp::normalize('+39 333 1234567', '44'), 'an explicit country code always wins');
        self::assertSame('393331234567', WhatsApp::normalize('333 1234567', '+39'), 'a plus in the setting is ignored');
        self::assertSame('393331234567', WhatsApp::normalize('333 1234567', ''), 'an empty setting falls back to 39');
    }

    public function testLinkIsAValidWaMeUrl(): void
    {
        $url = WhatsApp::link('393331234567', "Buongiorno, vorrei informazioni sull'appartamento Mimosa & più");

        self::assertMatchesRegularExpression('#^https://wa\.me/[1-9]\d{7,14}\?text=[A-Za-z0-9\-_.~%]+$#', $url);
        self::assertStringNotContainsString(' ', $url);
        self::assertStringNotContainsString('+', substr($url, 20), 'spaces are %20, not +');
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        self::assertSame("Buongiorno, vorrei informazioni sull'appartamento Mimosa & più", $query['text'], 'the text round-trips unchanged');
        self::assertSame("Buongiorno, vorrei informazioni sull'appartamento Mimosa & più", rawurldecode((string) substr((string) parse_url($url, PHP_URL_QUERY), 5)));
    }

    public function testLinkWithoutTextAndInvalidNumbers(): void
    {
        self::assertSame('https://wa.me/393331234567', WhatsApp::link('393331234567'));
        self::assertSame('https://wa.me/393331234567', WhatsApp::link('393331234567', ''));

        foreach (['0123456789', '12345', 'abc', '', '39 333', "393331234567\n", '393331234567/../x', '1234567890123456'] as $bad) {
            try {
                WhatsApp::link($bad, 'x');
                self::fail("link() accepted: " . json_encode($bad));
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testNothingInTheTextCanBreakOutOfTheUrl(): void
    {
        $nasty = "x&text=evil#frag?a=b\r\nLocation: http://evil.example/ \" ' < > %";

        $url = WhatsApp::link('393331234567', $nasty);

        self::assertSame(1, substr_count($url, '?'));
        self::assertSame(0, substr_count($url, '&'));
        self::assertSame(0, substr_count($url, '#'));
        self::assertDoesNotMatchRegularExpression('/[\s"\'<>]/', $url);
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        self::assertSame($nasty, $query['text']);
    }

    public function testLinkForPhone(): void
    {
        self::assertSame('https://wa.me/393331234567?text=Ciao', WhatsApp::linkForPhone('333 1234567', 'Ciao'));
        self::assertNull(WhatsApp::linkForPhone('not a number', 'Ciao'));
        self::assertNull(WhatsApp::linkForPhone(null, 'Ciao'));
        self::assertNull(WhatsApp::linkForPhone('', 'Ciao'));
    }

    // === Messages ==================================================================

    public function testBusinessMessageMatchesTheSpecExample(): void
    {
        self::assertSame(
            "Buongiorno, vorrei informazioni sull'appartamento Mimosa dal 12/06 al 16/06 per 2 adulti e 1 bambino.",
            WhatsApp::businessMessage('it', 'Mimosa', '2027-06-12', '2027-06-16', 2, 1),
        );
    }

    /** @return array<string, array{string, ?string, ?string, ?string, ?int, ?int, string}> */
    public static function businessMessages(): array
    {
        return [
            'english' => ['en', 'Mimosa', '2027-06-12', '2027-06-16', 2, 1, 'Hello, I would like some information about apartment Mimosa from 12 Jun to 16 Jun for 2 adults and 1 child.'],
            'no data at all' => ['it', null, null, null, null, null, 'Buongiorno, vorrei informazioni.'],
            'only the apartment' => ['it', 'Rosa', null, null, null, null, "Buongiorno, vorrei informazioni sull'appartamento Rosa."],
            'only dates' => ['it', null, '2027-06-12', '2027-06-16', null, null, 'Buongiorno, vorrei informazioni dal 12/06 al 16/06.'],
            'one adult, no children' => ['it', 'Viola', '2027-07-01', '2027-07-05', 1, 0, "Buongiorno, vorrei informazioni sull'appartamento Viola dal 01/07 al 05/07 per 1 adulto."],
            'several children' => ['it', 'Viola', '2027-07-01', '2027-07-05', 2, 3, "Buongiorno, vorrei informazioni sull'appartamento Viola dal 01/07 al 05/07 per 2 adulti e 3 bambini."],
            'english without children' => ['en', 'Viola', '2027-07-01', '2027-07-05', 2, 0, 'Hello, I would like some information about apartment Viola from 1 Jul to 5 Jul for 2 adults.'],
            'dates need both ends' => ['it', 'Viola', '2027-07-01', null, 2, 0, "Buongiorno, vorrei informazioni sull'appartamento Viola per 2 adulti."],
        ];
    }

    #[DataProvider('businessMessages')]
    public function testBusinessMessages(string $locale, ?string $apartment, ?string $in, ?string $out, ?int $adults, ?int $children, string $expected): void
    {
        self::assertSame($expected, WhatsApp::businessMessage($locale, $apartment, $in, $out, $adults, $children));
    }

    public function testCustomerMessages(): void
    {
        $data = ['first_name' => 'Mario', 'reference' => 'LV-ABC23456', 'apartment' => 'Margherita', 'check_in' => '2027-06-10', 'check_out' => '2027-06-15', 'adults' => 2, 'children' => 1, 'pets' => 0];

        self::assertSame(
            "Buongiorno Mario, la contatto in merito alla sua richiesta LV-ABC23456 per l'appartamento Margherita dal 10/06 al 15/06 per 2 adulti e 1 bambino.",
            WhatsApp::customerMessage('it', $data),
        );
        self::assertSame(
            'Hello Mario, I am contacting you about your request LV-ABC23456 for apartment Margherita from 10 Jun to 15 Jun for 2 adults and 1 child.',
            WhatsApp::customerMessage('en', $data),
        );
        self::assertSame(
            "Buongiorno Mario, la contatto in merito alla sua richiesta LV-ABC23456 per l'appartamento Margherita dal 10/06 al 15/06 per 2 adulti, 1 bambino e 2 animali.",
            WhatsApp::customerMessage('it', ['children' => 1, 'pets' => 2] + $data),
        );
        self::assertSame(
            "Buongiorno Mario, la contatto in merito alla sua richiesta per l'appartamento Margherita dal 10/06 al 15/06 per 1 adulto.",
            WhatsApp::customerMessage('it', ['reference' => null, 'adults' => 1, 'children' => 0] + $data),
            'a manual booking has no reference',
        );
    }
}
