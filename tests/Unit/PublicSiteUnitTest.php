<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\App;
use App\Config;
use App\Domain\GuestCounts;
use App\Domain\StayDates;
use App\Site\Contacts;
use App\Site\Format;
use App\Site\FormToken;
use App\Site\Locale;
use App\Site\Routes;
use App\Site\Text;
use PHPUnit\Framework\TestCase;

/** URL table, texts, form tokens and formatting of the public site: no database, no HTTP. */
final class PublicSiteUnitTest extends TestCase
{
    // === Routes and languages ================================================

    public function testEveryRouteHasAnItalianAndAnEnglishPath(): void
    {
        foreach (Routes::PATHS as $key => $paths) {
            self::assertArrayHasKey('it', $paths, $key);
            self::assertArrayHasKey('en', $paths, $key);
        }
    }

    public function testEnglishUrlsLiveUnderEnAndItalianOnesDoNot(): void
    {
        foreach (Routes::PATHS as $key => $paths) {
            self::assertTrue($paths['en'] === '/en' || str_starts_with($paths['en'], '/en/'), "EN path of $key: " . $paths['en']);
            self::assertFalse(str_starts_with($paths['it'], '/en'), "IT path of $key: " . $paths['it']);
        }
    }

    public function testNoPathIsUsedTwiceAndNoneIsUnderAdmin(): void
    {
        $all = [];
        foreach (Routes::PATHS as $paths) {
            foreach ($paths as $path) {
                self::assertStringStartsNotWith('/admin', $path);
                $all[] = $path;
            }
        }
        self::assertSame(count($all), count(array_unique($all)));
    }

    public function testNoTrailingSlashExceptTheItalianHome(): void
    {
        foreach (Routes::PATHS as $key => $paths) {
            foreach ($paths as $locale => $path) {
                if ($path !== '/') {
                    self::assertStringEndsNotWith('/', $path, "$key/$locale");
                }
            }
        }
    }

    public function testApartmentSlugIsInsertedAndEncoded(): void
    {
        self::assertSame('/appartamenti/rosa', Routes::path('apartment', 'it', ['slug' => 'rosa']));
        self::assertSame('/en/apartments/rosa', Routes::path('apartment', 'en', ['slug' => 'rosa']));
        self::assertSame('/appartamenti/a%2Fb', Routes::path('apartment', 'it', ['slug' => 'a/b']));
    }

    public function testLocaleIsDerivedFromThePath(): void
    {
        self::assertSame('it', Locale::fromPath('/'));
        self::assertSame('it', Locale::fromPath('/appartamenti/rosa'));
        self::assertSame('en', Locale::fromPath('/en'));
        self::assertSame('en', Locale::fromPath('/en/apartments'));
        self::assertSame('it', Locale::fromPath('/english'), 'only the exact /en prefix switches language');
        self::assertSame('it', Locale::fromPath('/enigma'));
    }

    // === Texts ===========================================================

    public function testItalianAndEnglishTextsHaveTheSameKeys(): void
    {
        $it = array_keys(Text::all('it'));
        $en = array_keys(Text::all('en'));

        self::assertSame([], array_values(array_diff($it, $en)), 'keys missing in en.php');
        self::assertSame([], array_values(array_diff($en, $it)), 'keys missing in it.php');
    }

    public function testNoTextIsEmptyAndPlaceholdersMatchBetweenLanguages(): void
    {
        foreach (Text::all('it') as $key => $italian) {
            $english = Text::all('en')[$key];
            self::assertNotSame('', trim($italian), "it: $key");
            self::assertNotSame('', trim($english), "en: $key");
            preg_match_all('/\{[a-z_]+\}/', $italian, $a);
            preg_match_all('/\{[a-z_]+\}/', $english, $b);
            $placeholdersIt = $a[0];
            $placeholdersEn = $b[0];
            sort($placeholdersIt);
            sort($placeholdersEn);
            self::assertSame($placeholdersIt, $placeholdersEn, "placeholders of $key differ between languages");
        }
    }

    public function testEveryTextKeyUsedInTheCodeExists(): void
    {
        $root = dirname(__DIR__, 2);
        $files = array_merge(
            glob($root . '/templates/*.php') ?: [],
            glob($root . '/templates/public/*.php') ?: [],
            glob($root . '/templates/public/request/*.php') ?: [],
            glob($root . '/app/Http/Controllers/Site/*.php') ?: [],
            glob($root . '/app/Site/*.php') ?: [],
        );
        $keys = [];
        foreach ($files as $file) {
            $source = (string) file_get_contents($file);
            // t('key'), Text::get('key', ...), Text::has('key', ...): complete literal keys only,
            // a dynamic key such as t('nav.' . $key) is not matched.
            preg_match_all("/(?<![A-Za-z_>])t\\('([a-z0-9_.]+)'[,)]|Text::(?:get|has)\\('([a-z0-9_.]+)'[,)]/", $source, $m);
            foreach (array_merge($m[1], $m[2]) as $key) {
                if ($key !== '') {
                    $keys[$key] = basename($file);
                }
            }
        }

        self::assertGreaterThan(80, count($keys), 'the scan should find the keys used by the templates');
        foreach ($keys as $key => $file) {
            self::assertTrue(Text::has($key, 'it'), "$file uses the missing text key $key");
        }
    }

    public function testEveryRouteKeyUsedInTemplatesExists(): void
    {
        $root = dirname(__DIR__, 2);
        $files = array_merge(glob($root . '/templates/*.php') ?: [], glob($root . '/templates/public/*.php') ?: [], glob($root . '/templates/public/request/*.php') ?: []);
        $count = 0;
        foreach ($files as $file) {
            preg_match_all("/lurl\\('([a-z.]+)'/", (string) file_get_contents($file), $m);
            foreach ($m[1] as $key) {
                $count++;
                self::assertArrayHasKey($key, Routes::PATHS, basename($file) . " links to the unknown route $key");
            }
        }
        self::assertGreaterThan(15, $count);
    }

    public function testEveryValidationCodeHasAMessageInBothLanguages(): void
    {
        $codes = [
            'invalid_date', 'check_out_not_after_check_in', 'stay_too_long', 'check_in_in_past', 'check_in_too_far',
            'invalid_adults', 'invalid_children', 'invalid_pets', 'invalid_first_name', 'invalid_last_name', 'invalid_email',
            'invalid_phone', 'notes_too_long', 'invalid_locale', 'privacy_required', 'invalid_apartment', 'over_capacity',
            'too_many_children', 'pets_not_allowed', 'too_many_pets', 'below_minimum_stay',
        ];
        foreach ($codes as $code) {
            foreach (['it', 'en'] as $locale) {
                self::assertTrue(Text::has('err.' . $code, $locale), "$locale: no message for $code");
            }
        }
        self::assertSame(Text::get('err.generic', 'it'), Text::error('something_unknown', 'it'));
    }

    public function testPlaceholdersAreReplacedWithoutTouchingOthers(): void
    {
        self::assertSame('Fino a 4 ospiti', Text::get('fact.guests', 'it', ['n' => '4']));
        self::assertSame('Up to 4 guests', Text::get('fact.guests', 'en', ['n' => '4']));
        self::assertSame('no.such.key', Text::get('no.such.key', 'it'), 'a missing key shows itself, which makes the gap obvious');
    }

    // === Form token =============================================================

    public function testAFreshTokenIsAccepted(): void
    {
        self::assertSame('ok', FormToken::check(FormToken::issue('p'), 'p'));
    }

    public function testATokenForAnotherPurposeOrTamperedOrMalformedIsRejected(): void
    {
        $token = FormToken::issue('purpose-a');
        self::assertSame('invalid', FormToken::check($token, 'purpose-b'));

        [$time, $mac] = explode('.', $token);
        self::assertSame('invalid', FormToken::check(($time + 1) . '.' . $mac, 'purpose-a'), 'changing the time breaks the signature');
        self::assertSame('invalid', FormToken::check($time . '.' . strrev($mac), 'purpose-a'));
        foreach (['', 'abc', $time, $time . '.', '.' . $mac, $time . '.' . substr($mac, 1), $token . "\n", $token . 'x'] as $bad) {
            self::assertSame('invalid', FormToken::check($bad, 'purpose-a'), 'malformed: ' . json_encode($bad));
        }
    }

    public function testATokenExpiresAndCanBeRequiredToBeOldEnough(): void
    {
        $issued = 1_800_000_000;
        $token = FormToken::issue('p', $issued);

        self::assertSame('ok', FormToken::check($token, 'p', 0, $issued));
        self::assertSame('too_fast', FormToken::check($token, 'p', 3, $issued + 2));
        self::assertSame('ok', FormToken::check($token, 'p', 3, $issued + 3));
        self::assertSame('ok', FormToken::check($token, 'p', 0, $issued + FormToken::MAX_AGE_SECONDS));
        self::assertSame('expired', FormToken::check($token, 'p', 0, $issued + FormToken::MAX_AGE_SECONDS + 1));
        self::assertSame('expired', FormToken::check($token, 'p', 0, $issued - 5), 'a token from the future is not valid');
    }

    public function testTheTokenDependsOnTheServerSecret(): void
    {
        $token = FormToken::issue('p', 1_800_000_000);
        [$time] = explode('.', $token);
        $forged = $time . '.' . hash_hmac('sha256', 'p|' . $time, 'a secret the attacker guessed');

        self::assertSame('invalid', FormToken::check($forged, 'p', 0, 1_800_000_001));
    }

    // === Formatting and contacts ===============================================

    public function testDatesAndGuestsAreFormattedPerLanguage(): void
    {
        self::assertSame('05/06/2027', Format::date('2027-06-05', 'it'));
        self::assertSame('5 June 2027', Format::date('2027-06-05', 'en'));
        self::assertSame('not a date', Format::date('not a date', 'it'));

        $stay = StayDates::fromStrings('2027-06-05', '2027-06-06');
        self::assertSame('1 notte', Format::nights($stay, 'it'));
        self::assertSame('4 nights', Format::nights(StayDates::fromStrings('2027-06-05', '2027-06-09'), 'en'));

        self::assertSame('1 adulto', Format::guests(GuestCounts::from(1, 0, 0), 'it'));
        self::assertSame('2 adulti, 1 bambino, 2 animali', Format::guests(GuestCounts::from(2, 1, 2), 'it'));
        self::assertSame('2 adults, 1 child, 1 pet', Format::guests(GuestCounts::from(2, 1, 1), 'en'));
    }

    public function testContactsAreEmptyUntilConfiguredAndNeverInvented(): void
    {
        $contacts = Contacts::fromConfig(new Config([]));

        self::assertTrue($contacts->isEmpty());
        self::assertNull($contacts->phoneHref());
        self::assertNull($contacts->whatsappLink('hello'));
    }

    public function testConfiguredContactsProduceSafeLinks(): void
    {
        $config = new Config([
            'PUBLIC_PHONE' => '+39 0521 123456',
            'PUBLIC_EMAIL' => 'info@example.test',
            'PUBLIC_ADDRESS' => "Via Esempio 1\n43000 Parma",
            'WHATSAPP_NUMBER' => '393331234567',
        ]);
        $contacts = Contacts::fromConfig($config);

        self::assertFalse($contacts->isEmpty());
        self::assertSame('tel:+390521123456', $contacts->phoneHref());
        self::assertSame('https://wa.me/393331234567?text=Ciao', $contacts->whatsappLink('Ciao'));
    }

    public function testInvalidContactValuesAreIgnored(): void
    {
        $contacts = Contacts::fromConfig(new Config(['PUBLIC_EMAIL' => 'not an email', 'WHATSAPP_NUMBER' => 'call me', 'PUBLIC_PHONE' => 'abc']));

        self::assertSame('', $contacts->email);
        self::assertNull($contacts->whatsappDigits);
        self::assertNull($contacts->phoneHref(), 'a phone without digits gets no tel: link');
    }

    public function testTheApplicationSingletonIsAvailableForTheSigningSecret(): void
    {
        self::assertInstanceOf(App::class, App::current());
    }
}
