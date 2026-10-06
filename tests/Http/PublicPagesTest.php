<?php

declare(strict_types=1);

namespace Tests\Http;

use App\Site\Routes;
use Tests\Support\HttpResponse;
use Tests\Support\PublicSiteTestCase;
use Tests\Support\TestServer;

/** The informational public pages, in Italian and English, over real HTTP. */
final class PublicPagesTest extends PublicSiteTestCase
{
    private const INFO_PAGES = ['home', 'farm', 'apartments', 'around', 'contact', 'privacy', 'cookies', 'request'];

    /** @return list<array{string, string}> route key, locale */
    private function infoPages(): array
    {
        $pages = [];
        foreach (['it', 'en'] as $locale) {
            foreach (self::INFO_PAGES as $key) {
                $pages[] = [$key, $locale];
            }
        }
        return $pages;
    }

    private function assertNoRawTextKeys(HttpResponse $response, string $where): void
    {
        $visible = strip_tags(preg_replace('#<(script|style)\b.*?</\1>#s', '', $response->body) ?? '');
        self::assertSame(0, preg_match('/\b(?:nav|flow|err|form|unit|fact|apartment|apartments|contact|summary|page|photo|legal|privacy|cookies|home|farm|around|request|footer|a11y)\.[a-z_]+\b/', $visible, $m), "$where shows a raw text key: " . ($m[0] ?? ''));
    }

    public function testEveryInformationalPageAnswers200WithItsLanguage(): void
    {
        $client = $this->client();
        foreach ($this->infoPages() as [$key, $locale]) {
            $path = self::path($key, $locale);
            $response = $client->get($path);

            self::assertSame(200, $response->status, $path);
            self::assertStringContainsString('<html lang="' . $locale . '">', $response->body, $path);
            self::assertStringContainsString('<h1', $response->body, $path);
            self::assertStringContainsString('id="main"', $response->body, "$path has no main landmark");
            self::assertStringContainsString('class="skip-link"', $response->body, $path);
            $this->assertNoRawTextKeys($response, $path);
        }
    }

    public function testEveryApartmentHasItsOwnPageInBothLanguages(): void
    {
        $client = $this->client();
        foreach (['margherita', 'girasole', 'rosa', 'mimosa', 'ciclamino', 'viola'] as $slug) {
            foreach (['it', 'en'] as $locale) {
                $path = self::path('apartment', $locale, ['slug' => $slug]);
                $response = $client->get($path);

                self::assertSame(200, $response->status, $path);
                self::assertStringContainsString('<h1>' . ucfirst($slug) . '</h1>', $response->body, $path);
                $this->assertNoRawTextKeys($response, $path);
            }
        }
    }

    public function testTitlesAreUniqueAndEachPageHasCanonicalAndHreflang(): void
    {
        $client = $this->client();
        $titles = [];
        foreach ($this->infoPages() as [$key, $locale]) {
            if ($key === 'request') {
                continue; // the form is noindex (checked below)
            }
            $path = self::path($key, $locale);
            $body = $client->get($path)->body;

            self::assertSame(1, preg_match('#<title>([^<]+)</title>#', $body, $m), $path);
            $titles[$locale . ':' . $key] = $m[1];
            self::assertMatchesRegularExpression('#<link rel="canonical" href="http://127\.0\.0\.1:\d+' . preg_quote($path, '#') . '">#', $body, $path);
            self::assertMatchesRegularExpression('#<link rel="alternate" hreflang="it" href="[^"]+">#', $body, $path);
            self::assertMatchesRegularExpression('#<link rel="alternate" hreflang="en" href="[^"]+">#', $body, $path);
            self::assertStringContainsString('hreflang="x-default"', $body, $path);
            self::assertMatchesRegularExpression('#<meta name="description" content="[^"]+">#', $body, "$path has no description");
        }
        // Unique within each language (the brand-only home title is the same in both).
        foreach (['it', 'en'] as $locale) {
            $ofLanguage = array_filter($titles, static fn (string $key): bool => str_starts_with($key, $locale . ':'), ARRAY_FILTER_USE_KEY);
            self::assertSame(count($ofLanguage), count(array_unique($ofLanguage)), "titles must be unique ({$locale}): " . json_encode($ofLanguage));
        }
    }

    public function testTheLanguageSwitchLeadsToTheEquivalentPage(): void
    {
        $client = $this->client();
        foreach (self::INFO_PAGES as $key) {
            $it = $client->get(self::path($key, 'it'))->body;
            $en = $client->get(self::path($key, 'en'))->body;

            self::assertStringContainsString('<a href="' . self::path($key, 'en') . '" lang="en" hreflang="en">English</a>', $it, $key);
            self::assertStringContainsString('<a href="' . self::path($key, 'it') . '" lang="it" hreflang="it">Italiano</a>', $en, $key);
        }
        $apartment = $client->get(self::path('apartment', 'it', ['slug' => 'rosa']))->body;
        self::assertStringContainsString('href="/en/apartments/rosa" lang="en"', $apartment);
    }

    public function testPublicPagesNeverSetACookieAndNeverLinkToTheAdminArea(): void
    {
        $client = $this->client();
        $paths = [];
        foreach ($this->infoPages() as [$key, $locale]) {
            $paths[] = self::path($key, $locale);
        }
        $paths[] = self::path('apartment', 'it', ['slug' => 'viola']);
        $paths[] = self::path('request.apartments', 'it') . '?' . http_build_query($this->searchParams(...$this->stay()));
        $paths[] = '/pagina-che-non-esiste';

        foreach ($paths as $path) {
            $response = $client->get($path);

            self::assertSame([], $response->setCookies(), "$path must not set a cookie");
            self::assertStringNotContainsString('/admin', $response->body, "$path links to the admin area");
        }
        self::assertSame([], $client->cookies);
    }

    public function testPagesCarryNoScriptAndNoExternalResources(): void
    {
        $client = $this->client();
        foreach ($this->infoPages() as [$key, $locale]) {
            $body = $client->get(self::path($key, $locale))->body;

            self::assertStringNotContainsString('<script', $body, "$key/$locale");
            self::assertStringNotContainsString('<img', $body, "$key/$locale: no photograph until its origin is verified");
            self::assertDoesNotMatchRegularExpression('#(?:src|href)="https?://(?!wa\.me|127\.0\.0\.1)#', $body, "$key/$locale loads something from another site");
        }
    }

    public function testPhotographsAreMarkedPlaceholders(): void
    {
        $body = $this->client()->get(self::path('apartments'))->body;

        self::assertStringContainsString('class="photo-placeholder', $body);
        self::assertStringContainsString('role="img"', $body);
        self::assertStringContainsString('Fotografia in arrivo', $body);
    }

    public function testUnknownUrlsGetAProper404InTheRightLanguage(): void
    {
        $client = $this->client();

        $it = $client->get('/pagina-che-non-esiste');
        self::assertSame(404, $it->status);
        self::assertStringContainsString('<html lang="it">', $it->body);
        self::assertStringContainsString('Pagina non trovata', $it->body);

        $en = $client->get('/en/no-such-page');
        self::assertSame(404, $en->status);
        self::assertStringContainsString('<html lang="en">', $en->body);
        self::assertStringContainsString('Page not found', $en->body);
        self::assertStringContainsString('Back to the home page', $en->body);

        foreach (['/appartamenti/non-esiste', '/en/apartments/does-not-exist', '/appartamenti/ROSA', '/appartamenti/ro%20sa', '/appartamenti/..%2Fadmin'] as $path) {
            self::assertSame(404, $client->get($path)->status, $path);
        }
    }

    public function testInactiveApartmentsAreNotPublic(): void
    {
        $this->db->exec("UPDATE apartments SET is_active = 0 WHERE slug = 'rosa'");
        $client = $this->client();

        self::assertSame(404, $client->get(self::path('apartment', 'it', ['slug' => 'rosa']))->status);
        self::assertStringNotContainsString('Rosa', $client->get(self::path('apartments'))->body);
        self::assertStringNotContainsString('Rosa', $client->get(self::path('home'))->body);
        self::assertStringContainsString('Margherita', $client->get(self::path('apartments'))->body);
    }

    public function testTrailingSlashRedirectsToTheCanonicalUrl(): void
    {
        $response = $this->client()->get('/appartamenti/');

        self::assertSame(301, $response->status);
        self::assertStringEndsWith('/appartamenti', (string) $response->location());
    }

    public function testPagesRefuseWritingMethodsAndAcceptHead(): void
    {
        $client = $this->client();
        $before = $this->snapshot();

        foreach (['home', 'apartments', 'contact', 'privacy'] as $key) {
            $response = $client->post(self::path($key), ['x' => 'y']);
            self::assertSame(405, $response->status, $key);
            self::assertStringContainsString('GET', (string) $response->header('Allow'));
        }
        self::assertSame(200, $client->head(self::path('apartments'))->status);
        $this->assertDatabaseUnchanged($before, 'static pages must not write anything');
    }

    public function testApartmentPageShowsOnlyWhatTheAdminFilledIn(): void
    {
        $id = $this->apartmentId('rosa');
        $client = $this->client();

        $empty = $client->get(self::path('apartment', 'it', ['slug' => 'rosa']))->body;
        foreach (['Descrizione', 'Orari', 'Regole della casa', 'Fino a', 'camer', 'Da €', 'posti letto'] as $label) {
            self::assertStringNotContainsString($label, $empty, "an empty field must not render: $label");
        }

        $this->db->prepare("UPDATE apartments SET max_guests = 5, bedrooms = 2, beds = 4, check_in_from = '15:00', check_in_until = '19:30', check_out_until = '10:00', indicative_price_cents = 9000 WHERE id = ?")->execute([$id]);
        $this->db->prepare("INSERT INTO apartment_translations (apartment_id, locale, description, rules, meta_title, meta_description) VALUES (?, 'it', 'Testo italiano', 'Regole italiane', 'Titolo SEO', 'Descrizione SEO')")->execute([$id]);
        $this->db->prepare("INSERT INTO apartment_translations (apartment_id, locale, description) VALUES (?, 'en', 'English text')")->execute([$id]);

        $it = $client->get(self::path('apartment', 'it', ['slug' => 'rosa']))->body;
        self::assertStringContainsString('Fino a 5 ospiti', $it);
        self::assertStringContainsString('2 camere da letto', $it);
        self::assertStringContainsString('4 posti letto', $it);
        self::assertStringContainsString('Testo italiano', $it);
        self::assertStringContainsString('Regole italiane', $it);
        self::assertStringContainsString('Arrivo dalle 15:00 alle 19:30', $it);
        self::assertStringContainsString('Partenza entro le 10:00', $it);
        self::assertStringContainsString('90,00', $it);
        self::assertStringContainsString('<title>Titolo SEO – Agriturismo La Volta</title>', $it);
        self::assertStringContainsString('<meta name="description" content="Descrizione SEO">', $it);

        $en = $client->get(self::path('apartment', 'en', ['slug' => 'rosa']))->body;
        self::assertStringContainsString('English text', $en);
        self::assertStringNotContainsString('Testo italiano', $en, 'each language shows its own text');
        self::assertStringNotContainsString('House rules', $en, 'the English rules are empty, so the section is omitted');
    }

    public function testAdminTextIsEscapedOnPublicPages(): void
    {
        $id = $this->apartmentId('viola');
        $payload = '<script>alert(1)</script><img src=x onerror=alert(2)>';
        $this->db->prepare('UPDATE apartments SET name = ? WHERE id = ?')->execute([$payload, $id]);
        $this->db->prepare("INSERT INTO apartment_translations (apartment_id, locale, description, rules, meta_title, meta_description) VALUES (?, 'it', ?, ?, ?, ?)")
            ->execute([$id, $payload, $payload, $payload, $payload]);
        $client = $this->client();

        foreach ([self::path('apartment', 'it', ['slug' => 'viola']), self::path('apartments'), self::path('home')] as $path) {
            $body = $client->get($path)->body;
            self::assertStringNotContainsString('<script>alert', $body, $path);
            self::assertStringNotContainsString('<img src=x', $body, $path);
            self::assertStringContainsString('&lt;script&gt;', $body, $path);
        }
    }

    public function testContactDetailsAreOmittedUntilConfiguredAndNoWhatsAppButtonWithoutNumber(): void
    {
        $client = $this->client();
        foreach (['home', 'contact', 'apartments'] as $key) {
            $body = $client->get(self::path($key))->body;

            self::assertStringNotContainsString('wa.me', $body, $key);
            self::assertStringNotContainsString('tel:', $body, $key);
            self::assertStringNotContainsString('mailto:', $body, $key);
        }
        self::assertStringContainsString('saranno pubblicati', $client->get(self::path('contact'))->body);
    }

    public function testConfiguredContactDetailsAppearWithSafeLinks(): void
    {
        $server = TestServer::start([
            'PUBLIC_PHONE' => '+39 0521 123456',
            'PUBLIC_EMAIL' => 'info@example.test',
            'PUBLIC_ADDRESS' => "Via Esempio 1\n43000 Parma",
            'WHATSAPP_NUMBER' => '393331234567',
        ]);
        try {
            $client = new \Tests\Support\HttpClient($server->baseUrl());
            $contact = $client->get(self::path('contact'))->body;
            $apartment = $client->get(self::path('apartment', 'it', ['slug' => 'mimosa']))->body;
            $english = $client->get(self::path('contact', 'en'))->body;

            self::assertStringContainsString('href="tel:+390521123456"', $contact);
            self::assertStringContainsString('href="mailto:info@example.test"', $contact);
            self::assertStringContainsString('Via Esempio 1<br />', $contact);
            self::assertStringContainsString('href="https://wa.me/393331234567?text=Buongiorno%2C%20vorrei%20informazioni', $contact);
            self::assertStringContainsString('rel="noopener noreferrer"', $contact);
            self::assertStringContainsString("sull%27appartamento%20Mimosa", $apartment, 'the apartment page pre-fills the apartment name');
            self::assertStringContainsString('Hello%2C%20I%20would%20like%20some%20information', $english);
            self::assertStringContainsString('href="tel:+390521123456"', $client->get('/')->body, 'phone and email are reachable from every page');
        } finally {
            $server->stop();
        }
    }

    public function testRequestFlowPagesAreNoindexAndNotCacheable(): void
    {
        $client = $this->client();
        [$in, $out] = $this->stay();
        $this->equip('margherita', 4, [$in, $out]);

        foreach ([
            self::path('request'),
            self::path('request.apartments') . '?' . http_build_query($this->searchParams($in, $out)),
            self::path('request.received') . '?rif=ABC234',
        ] as $path) {
            $response = $client->get($path);
            self::assertSame(200, $response->status, $path);
            self::assertStringContainsString('<meta name="robots" content="noindex">', $response->body, $path);
            self::assertSame('no-store', $response->header('Cache-Control'), $path);
        }
    }

    public function testSecurityHeadersAreSentOnPublicPages(): void
    {
        $response = $this->client()->get(self::path('home'));

        self::assertSame('nosniff', $response->header('X-Content-Type-Options'));
        self::assertSame('DENY', $response->header('X-Frame-Options'));
        self::assertStringContainsString("default-src 'self'", (string) $response->header('Content-Security-Policy'));
        self::assertStringContainsString('<meta name="viewport"', $response->body);
    }

    public function testNoPublicPageWritesToTheDatabase(): void
    {
        [$in, $out] = $this->stay();
        $this->equip('margherita', 4, [$in, $out]);
        (new \App\Service\BookingService($this->db))->createRequest($this->requestInput($in, $out));
        $before = $this->snapshot();
        $client = $this->client();

        foreach (['home', 'farm', 'apartments', 'around', 'contact', 'privacy', 'cookies', 'request'] as $key) {
            foreach (['it', 'en'] as $locale) {
                $client->get(self::path($key, $locale));
            }
        }
        $client->get(self::path('request.apartments') . '?' . http_build_query($this->searchParams($in, $out)));
        $client->get(self::path('request.details') . '?' . http_build_query($this->searchParams($in, $out) + ['apartment' => 'margherita']));
        $client->get(self::path('request.received') . '?rif=ABC234');
        $client->get(self::path('apartment', 'it', ['slug' => 'rosa']));

        $this->assertDatabaseUnchanged($before, 'GET requests on public pages must never write');
        self::assertSame(1, $this->requestCount());
    }

    public function testRoutesTableMatchesWhatTheRouterServes(): void
    {
        $client = $this->client();
        foreach (Routes::PATHS as $key => $paths) {
            if (in_array($key, ['request.summary', 'request.submit'], true)) {
                continue; // POST-only steps
            }
            foreach ($paths as $locale => $path) {
                if ($key === 'apartment') {
                    $path = str_replace('{slug}', 'margherita', $path);
                }
                $response = $client->get($path);
                self::assertContains($response->status, [200, 303], "$key/$locale -> $path");
            }
        }
    }
}
