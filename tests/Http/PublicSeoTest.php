<?php

declare(strict_types=1);

namespace Tests\Http;

use Tests\Support\HttpClient;
use Tests\Support\PublicSiteTestCase;
use Tests\Support\TestServer;

/** robots.txt, sitemap, social metadata, structured data, breadcrumbs and accessibility structure. */
final class PublicSeoTest extends PublicSiteTestCase
{
    private const PAGES = ['home', 'farm', 'apartments', 'around', 'contact', 'privacy', 'cookies', 'request'];

    // === robots.txt and sitemap ===============================================

    public function testRobotsTxtBlocksTheAdminAndTheFlowStepsAndPointsToTheSitemap(): void
    {
        $client = $this->client();
        $response = $client->get('/robots.txt');

        self::assertSame(200, $response->status);
        self::assertStringStartsWith('text/plain', (string) $response->header('Content-Type'));
        self::assertStringContainsString("User-agent: *\n", $response->body);
        self::assertStringContainsString("Disallow: /admin\n", $response->body);
        self::assertStringContainsString("Disallow: /richiedi-disponibilita/\n", $response->body);
        self::assertStringContainsString("Disallow: /en/request-availability/\n", $response->body);
        self::assertMatchesRegularExpression('#^Sitemap: http://127\.0\.0\.1:\d+/sitemap\.xml$#m', $response->body);
        self::assertStringNotContainsString('Disallow: /\n', $response->body, 'the public site must not be blocked');
        self::assertSame([], $response->setCookies());
    }

    public function testSitemapListsEveryPublicPageOnceWithLanguageAlternates(): void
    {
        $response = $this->client()->get('/sitemap.xml');

        self::assertSame(200, $response->status);
        self::assertStringStartsWith('application/xml', (string) $response->header('Content-Type'));
        $xml = simplexml_load_string($response->body);
        self::assertNotFalse($xml, 'the sitemap must be well-formed XML');

        $xml->registerXPathNamespace('s', 'http://www.sitemaps.org/schemas/sitemap/0.9');
        $xml->registerXPathNamespace('x', 'http://www.w3.org/1999/xhtml');
        $locs = array_map('strval', $xml->xpath('//s:url/s:loc'));

        // 7 informational pages + 6 apartments, in 2 languages.
        self::assertCount(26, $locs);
        self::assertSame($locs, array_values(array_unique($locs)), 'no URL is listed twice');
        foreach (['/', '/en', '/agriturismo', '/en/the-farm', '/appartamenti', '/en/apartments', '/appartamenti/rosa', '/en/apartments/rosa', '/privacy', '/en/cookies'] as $path) {
            self::assertContains(rtrim(self::$server->baseUrl() . $path, '/') === self::$server->baseUrl() ? self::$server->baseUrl() . '/' : self::$server->baseUrl() . $path, $locs, $path);
        }
        foreach ($xml->xpath('//s:url') as $url) {
            $url->registerXPathNamespace('x', 'http://www.w3.org/1999/xhtml');
            $hreflangs = array_map(static fn ($a): string => (string) $a['hreflang'], $url->xpath('x:link'));
            self::assertSame(['it', 'en', 'x-default'], $hreflangs, (string) $url->loc);
        }
    }

    public function testSitemapLeavesOutFlowStepsAdminAndInactiveApartments(): void
    {
        $this->db->exec("UPDATE apartments SET is_active = 0 WHERE slug = 'rosa'");
        $body = $this->client()->get('/sitemap.xml')->body;

        self::assertStringNotContainsString('/admin', $body);
        self::assertStringNotContainsString('richiedi-disponibilita', $body);
        self::assertStringNotContainsString('request-availability', $body);
        self::assertStringNotContainsString('/appartamenti/rosa', $body);
        self::assertStringNotContainsString('/en/apartments/rosa', $body);
        self::assertStringContainsString('/appartamenti/viola', $body);
    }

    public function testRobotsAndSitemapAreNotLinkedOrServedByTheAdminGuards(): void
    {
        $client = $this->client();

        self::assertSame(405, $client->post('/robots.txt', ['x' => 'y'])->status);
        self::assertSame(405, $client->post('/sitemap.xml', ['x' => 'y'])->status);
        self::assertSame(303, $client->get('/admin')->status, 'the admin is still protected');
    }

    // === Social metadata, favicon, assets =========================================

    public function testEveryIndexablePageHasSocialMetadataInItsLanguage(): void
    {
        $client = $this->client();
        foreach (['it' => 'it_IT', 'en' => 'en_GB'] as $locale => $og) {
            foreach (array_diff(self::PAGES, ['request']) as $key) {
                $path = self::path($key, $locale);
                $body = $client->get($path)->body;

                self::assertStringContainsString('<meta property="og:type" content="website">', $body, $path);
                self::assertStringContainsString('<meta property="og:site_name" content="Agriturismo La Volta">', $body, $path);
                self::assertStringContainsString('<meta property="og:locale" content="' . $og . '">', $body, $path);
                self::assertMatchesRegularExpression('#<meta property="og:title" content="[^"]+">#', $body, $path);
                self::assertMatchesRegularExpression('#<meta property="og:description" content="[^"]+">#', $body, $path);
                self::assertMatchesRegularExpression('#<meta property="og:url" content="http://127\.0\.0\.1:\d+' . preg_quote($path === '/' ? '/' : $path, '#') . '">#', $body, $path);
                self::assertStringContainsString('<meta name="twitter:card" content="summary">', $body, $path);
                self::assertStringNotContainsString('og:image', $body, "$path: no image until a verified photograph exists");
            }
        }
    }

    public function testFaviconAndStylesheetAreServedWithAVersionForLongCaching(): void
    {
        $client = $this->client();
        $body = $client->get('/')->body;

        self::assertSame(1, preg_match('#<link rel="icon" href="(/assets/favicon\.svg\?v=\d+)" type="image/svg\+xml">#', $body, $icon));
        self::assertSame(1, preg_match('#<link rel="stylesheet" href="(/assets/css/site\.css\?v=\d+)">#', $body, $css));

        $favicon = $client->get($icon[1]);
        self::assertSame(200, $favicon->status);
        self::assertStringContainsString('image/svg+xml', (string) $favicon->header('Content-Type'));
        self::assertStringContainsString('<svg', $favicon->body);
        self::assertSame(200, $client->get($css[1])->status);
    }

    // === Breadcrumbs and structured data ==========================================

    public function testBreadcrumbsAppearOnInnerPagesNotOnTheHomeOrTheFlow(): void
    {
        $client = $this->client();

        $apartment = $client->get(self::path('apartment', 'it', ['slug' => 'rosa']))->body;
        self::assertSame(1, preg_match('#<nav class="breadcrumb" aria-label="Percorso">(.*?)</nav>#s', $apartment, $m));
        self::assertSame(3, substr_count($m[1], '<li>'));
        self::assertStringContainsString('<span aria-current="page">Rosa</span>', $m[1]);
        self::assertStringContainsString('href="/appartamenti"', $m[1]);

        $english = $client->get(self::path('apartment', 'en', ['slug' => 'rosa']))->body;
        self::assertStringContainsString('aria-label="Breadcrumb"', $english);
        self::assertStringContainsString('href="/en/apartments"', $english);

        self::assertStringNotContainsString('class="breadcrumb"', $client->get('/')->body);
        self::assertStringNotContainsString('class="breadcrumb"', $client->get(self::path('request'))->body);
        self::assertSame(1, substr_count($client->get(self::path('privacy'))->body, 'class="breadcrumb"'));
    }

    /** @return list<array<string, mixed>> */
    private function jsonLd(string $html): array
    {
        preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $m);
        $blocks = [];
        foreach ($m[1] as $json) {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
            self::assertIsArray($data);
            $blocks[] = $data;
        }
        return $blocks;
    }

    public function testBreadcrumbStructuredDataMirrorsTheVisibleBreadcrumb(): void
    {
        $all = $this->jsonLd($this->client()->get(self::path('apartment', 'it', ['slug' => 'rosa']))->body);
        self::assertSame(['BreadcrumbList', 'Apartment'], array_column($all, '@type'), 'an apartment page carries its breadcrumb and its Apartment data');
        $blocks = array_values(array_filter($all, static fn (array $b): bool => $b['@type'] === 'BreadcrumbList'));

        self::assertCount(1, $blocks);
        self::assertSame('BreadcrumbList', $blocks[0]['@type']);
        self::assertSame(['Home', 'Appartamenti', 'Rosa'], array_column($blocks[0]['itemListElement'], 'name'));
        self::assertSame([1, 2, 3], array_column($blocks[0]['itemListElement'], 'position'));
        self::assertArrayNotHasKey('item', $blocks[0]['itemListElement'][2], 'the current page has no link');
    }

    public function testNoBusinessStructuredDataWithoutRealContactDetails(): void
    {
        $blocks = $this->jsonLd($this->client()->get('/')->body);

        self::assertSame([], $blocks, 'the home page has no breadcrumb and, without contact details, no business data');
    }

    public function testBusinessStructuredDataUsesOnlyConfiguredDetailsAndIsInjectionSafe(): void
    {
        $server = TestServer::start(['PUBLIC_PHONE' => '+39 0521 123456', 'PUBLIC_ADDRESS' => 'Via </script><script>alert(1)</script> 1']);
        try {
            $body = (new HttpClient($server->baseUrl()))->get('/')->body;
            self::assertDoesNotMatchRegularExpression('#<script(?![^>]*ld\+json)#', $body);
            $blocks = $this->jsonLd($body);
            $business = array_values(array_filter($blocks, static fn (array $b): bool => $b['@type'] === 'LodgingBusiness'))[0];

            self::assertSame('Agriturismo La Volta', $business['name']);
            self::assertSame('+39 0521 123456', $business['telephone']);
            self::assertSame('Via </script><script>alert(1)</script> 1', $business['address']);
            self::assertArrayNotHasKey('email', $business, 'a detail that is not configured is not invented');
            self::assertStringNotContainsString('</script><script>alert', $body);
        } finally {
            $server->stop();
        }
    }

    // === Accessibility structure ===================================================

    /** @return list<string> every public page, flow steps included */
    private function allPages(): array
    {
        [$in, $out] = $this->stay();
        $this->equip('margherita', 4, [$in, $out]);
        $this->equip('girasole', 4, [$in, $out]);
        $pages = [];
        foreach (['it', 'en'] as $locale) {
            foreach (self::PAGES as $key) {
                $pages[] = self::path($key, $locale);
            }
            $pages[] = self::path('apartment', $locale, ['slug' => 'margherita']);
            $pages[] = self::path('request.apartments', $locale) . '?' . http_build_query($this->searchParams($in, $out));
            $pages[] = self::path('request.details', $locale) . '?' . http_build_query($this->searchParams($in, $out) + ['apartment' => 'margherita']);
            $pages[] = self::path('request.received', $locale);
        }
        return $pages;
    }

    public function testEachPageHasOneH1AndHeadingsNeverSkipALevel(): void
    {
        $client = $this->client();
        foreach ($this->allPages() as $path) {
            $body = $client->get($path)->body;
            self::assertSame(200, $client->get($path)->status, $path);
            preg_match_all('#<h([1-6])[ >]#', $body, $m);
            $levels = array_map('intval', $m[1]);

            self::assertSame(1, count(array_filter($levels, static fn (int $l): bool => $l === 1)), "$path must have exactly one h1");
            self::assertSame(1, $levels[0], "$path: the first heading is the h1");
            for ($i = 1; $i < count($levels); $i++) {
                self::assertLessThanOrEqual($levels[$i - 1] + 1, $levels[$i], "$path: heading level jumps from h{$levels[$i - 1]} to h{$levels[$i]}");
            }
        }
    }

    public function testLandmarksAreUniqueAndLabelled(): void
    {
        $client = $this->client();
        foreach ($this->allPages() as $path) {
            $body = $client->get($path)->body;

            self::assertSame(1, substr_count($body, '<main '), "$path: one main");
            self::assertSame(1, substr_count($body, '<header '), "$path: one header");
            self::assertSame(1, substr_count($body, '<footer '), "$path: one footer");
            preg_match_all('#<nav\b([^>]*)>#', $body, $navs);
            foreach ($navs[1] as $attributes) {
                self::assertStringContainsString('aria-label="', $attributes, "$path: every nav is labelled");
            }
            self::assertStringContainsString('<a class="skip-link" href="#main">', $body, $path);
            self::assertStringContainsString('id="main"', $body, $path);
        }
    }

    public function testEveryFormControlHasALabelAndErrorsPointToExistingElements(): void
    {
        $client = $this->client();
        foreach ($this->allPages() as $path) {
            $body = $client->get($path)->body;
            preg_match_all('#<(?:input|select|textarea)\b([^>]*)>#', $body, $controls);
            foreach ($controls[1] as $attributes) {
                if (str_contains($attributes, 'type="hidden"')) {
                    continue;
                }
                self::assertSame(1, preg_match('#\bid="([^"]+)"#', $attributes, $id), "$path: a control has no id: $attributes");
                self::assertMatchesRegularExpression('#<label for="' . preg_quote($id[1], '#') . '">#', $body, "$path: no label for {$id[1]}");
            }
        }

        // Errors: every aria-describedby target exists, and the page announces them.
        [$in, $out] = $this->stay();
        $bad = $client->get(self::path('request.apartments') . '?' . http_build_query($this->searchParams($out, $in)))->body;
        self::assertStringContainsString('role="alert"', $bad);
        preg_match_all('#aria-describedby="([^"]+)"#', $bad, $refs);
        self::assertNotEmpty($refs[1]);
        foreach ($refs[1] as $target) {
            self::assertStringContainsString('id="' . $target . '"', $bad, "aria-describedby target $target");
        }
    }

    public function testNoDuplicateIdsOnAnyPage(): void
    {
        $client = $this->client();
        foreach ($this->allPages() as $path) {
            preg_match_all('#\sid="([^"]+)"#', $client->get($path)->body, $m);
            self::assertSame(array_values(array_unique($m[1])), $m[1], "$path has duplicate ids: " . implode(', ', array_diff_assoc($m[1], array_unique($m[1]))));
        }
    }

    public function testLinksHaveAccessibleNamesAndExternalOnesAreSafe(): void
    {
        $client = $this->client();
        foreach ($this->allPages() as $path) {
            $body = $client->get($path)->body;
            preg_match_all('#<a\b([^>]*)>(.*?)</a>#s', $body, $links, PREG_SET_ORDER);
            foreach ($links as $link) {
                self::assertNotSame('', trim(strip_tags($link[2])), "$path: a link has no text: " . $link[0]);
                if (str_contains($link[1], 'target="_blank"')) {
                    self::assertStringContainsString('noopener', $link[1], "$path: " . $link[0]);
                }
            }
        }
    }

    public function testTheCssMeetsTheTouchTargetAndMotionRules(): void
    {
        $css = $this->client()->get('/assets/css/site.css')->body;

        self::assertMatchesRegularExpression('#\.site-nav a \{[^}]*min-height: 2\.75rem#s', $css);
        self::assertMatchesRegularExpression('#\.button \{[^}]*min-height: 2\.75rem#s', $css);
        self::assertStringContainsString(':focus-visible', $css);
        self::assertStringContainsString('prefers-reduced-motion: reduce', $css);
        self::assertStringNotContainsString('@import', $css, 'no external fonts or stylesheets');
        self::assertStringNotContainsString('url(http', $css);
    }

    public function testPublicPagesAreLightweight(): void
    {
        $client = $this->client();
        foreach ([self::path('home'), self::path('apartments'), self::path('apartment', 'it', ['slug' => 'rosa'])] as $path) {
            self::assertLessThan(20_000, strlen($client->get($path)->body), "$path HTML size");
        }
        self::assertLessThan(30_000, strlen($client->get('/assets/css/site.css')->body), 'one small stylesheet, no JavaScript, no web fonts');
    }
}
