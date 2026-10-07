<?php

declare(strict_types=1);

namespace Tests\Http;

use Tests\Support\HttpClient;
use Tests\Support\PublicSiteTestCase;
use Tests\Support\TestServer;

/**
 * Fixes of phase 15 seen from the outside, over real HTTP: one address for the site, the "received"
 * page, a form sent twice, client addresses that cannot be forged, invisible characters, failed logins.
 */
final class ExistingFixesTest extends PublicSiteTestCase
{
    private function prepare(): array
    {
        $stay = $this->stay();
        $this->equip('margherita', 4, $stay);
        return $stay;
    }

    // === One address for the site ================================================

    public function testAnotherHostNameIsSentToTheConfiguredAddressKeepingPathAndQuery(): void
    {
        $base = self::$server->baseUrl();

        $response = $this->client()->get('/appartamenti?x=1', ['Host' => 'www.altro.example.test']);

        self::assertSame(301, $response->status);
        self::assertSame($base . '/appartamenti?x=1', $response->location());
        self::assertSame('', $response->body);
        self::assertSame(301, $this->client()->head('/', ['Host' => 'altro.example.test'])->status);
    }

    public function testTheTargetNeverFollowsAHostileHostHeaderOrAddress(): void
    {
        $base = self::$server->baseUrl();

        foreach (['evil.example', 'evil.example:8080', 'user@evil.example'] as $host) {
            self::assertSame($base . '/', $this->client()->get('/', ['Host' => $host])->location(), $host);
        }
        self::assertSame($base . '/', $this->client()->get('//evil.example/x', ['Host' => 'altro.example.test'])->location());
    }

    public function testTheConfiguredHostIsServedAndFormsAreNotRedirected(): void
    {
        $client = $this->client();

        self::assertSame(200, $client->get('/')->status);
        self::assertSame(200, $client->get('/', ['Host' => parse_url(self::$server->baseUrl(), PHP_URL_HOST) . ':' . parse_url(self::$server->baseUrl(), PHP_URL_PORT)])->status);

        $token = $client->get('/admin/login')->csrfToken();
        $post = $client->post('/admin/login', ['_csrf' => $token, 'username' => 'x', 'password' => 'y'], ['Host' => 'altro.example.test']);
        self::assertNotSame(301, $post->status, 'a POST is never turned into a redirect');
        self::assertNotSame(303, $this->client()->get('/admin/login')->status);
    }

    public function testBehindAProxyThatSendsTheOriginalHostNothingIsRedirected(): void
    {
        $response = $this->client()->get('/', ['Host' => 'interno.local', 'X-Forwarded-Host' => 'www.sito.example']);

        self::assertSame(200, $response->status);
    }

    public function testWithoutAppUrlThereIsNoRedirectAtAll(): void
    {
        // A value without a host is as good as none (an empty variable is dropped by the server, so .env would win).
        $server = TestServer::start(['APP_URL' => 'senza-indirizzo']);
        try {
            $client = new HttpClient($server->baseUrl());

            self::assertSame(200, $client->get('/', ['Host' => 'qualsiasi.example'])->status);
            self::assertSame(200, $client->get('/appartamenti')->status);
        } finally {
            $server->stop();
        }
    }

    // === The "received" page ====================================================

    public function testTheReceivedPageShowsOnlyARealRecentReference(): void
    {
        [$in, $out] = $this->prepare();
        $client = $this->client();
        $response = $this->sendFromSummary($client, $this->toSummary($client, 'margherita', $in, $out));
        self::assertSame(303, $response->status);
        $reference = (string) $this->scalar('SELECT reference FROM booking_requests');

        $own = $client->get((string) $response->location());
        self::assertSame(200, $own->status);
        self::assertStringContainsString($reference, $own->body, 'the real reference is shown right after sending');

        foreach (['LV-ZZZZZZZZ', 'LV-ABCD2345', 'LV-HACKED-BY'] as $made) {
            $page = $client->get(self::path('request.received') . '?rif=' . $made);
            self::assertSame(200, $page->status);
            self::assertStringNotContainsString($made, $page->body, "a reference that does not exist is not echoed: $made");
        }
        $markup = $client->get(self::path('request.received') . '?rif=' . rawurlencode('LV-X<b>x</b>'));
        self::assertStringNotContainsString('<b>x</b>', $markup->body);

        $this->db->exec("UPDATE booking_requests SET created_at = UTC_TIMESTAMP() - INTERVAL 2 HOUR");
        self::assertStringNotContainsString($reference, $client->get((string) $response->location())->body, 'an old reference is not shown any more');
        self::assertSame(200, $client->get(self::path('request.received'))->status, 'and the page without reference still works');
    }

    // === A form sent twice ======================================================

    public function testTheSameFormSentTwiceIsOneRequestWithTheSameReferenceAndOneEmail(): void
    {
        [$in, $out] = $this->prepare();
        $client = $this->client();
        $summary = $this->toSummary($client, 'margherita', $in, $out);

        $first = $this->sendFromSummary($client, $summary);
        $second = $this->sendFromSummary($client, $summary); // double click, or a reload of the page

        self::assertSame(303, $first->status);
        self::assertSame(303, $second->status);
        self::assertSame($first->location(), $second->location(), 'the same "received" page, with the same reference');
        self::assertSame(1, $this->requestCount());
        self::assertSame(1, $this->countRows('email_outbox', "type = 'new_request_admin'"));
        self::assertSame(1, $this->countRows('audit_log', "entity_type = 'booking_request' AND action = 'created'"));
    }

    public function testAFreshFormWithTheSameDataIsAGenuinelyNewRequest(): void
    {
        [$in, $out] = $this->prepare();
        $client = $this->client();

        $this->sendFromSummary($client, $this->toSummary($client, 'margherita', $in, $out));
        // The token carries the time in seconds: a page loaded again within the same second would get the
        // same token. A person cannot do that, so the test waits one second like a person would.
        sleep(1);
        $this->sendFromSummary($client, $this->toSummary($client, 'margherita', $in, $out)); // new page load, new token

        self::assertSame(2, $this->requestCount());
        self::assertSame(2, $this->countRows('email_outbox', "type = 'new_request_admin'"));
    }

    public function testChangingTheDataOfTheSameFormIsANewRequestNotAReplay(): void
    {
        [$in, $out] = $this->prepare();
        $client = $this->client();
        $summary = $this->toSummary($client, 'margherita', $in, $out);
        $fields = $this->hiddenFields($summary->body) + ['privacy_accepted' => '1'];

        $client->post(self::path('request.submit'), $fields);
        $client->post(self::path('request.submit'), ['notes' => 'Un messaggio diverso'] + $fields);

        self::assertSame(2, $this->requestCount(), 'the key covers the data: different data is not "the same form sent twice"');
    }

    public function testAnInvalidSecondSendDoesNotReplaceTheStoredRequest(): void
    {
        [$in, $out] = $this->prepare();
        $client = $this->client();
        $summary = $this->toSummary($client, 'margherita', $in, $out);
        $this->sendFromSummary($client, $summary);

        $withoutConsent = $client->post(self::path('request.submit'), $this->hiddenFields($summary->body));

        self::assertSame(422, $withoutConsent->status);
        self::assertSame(1, $this->requestCount());
    }

    // === Client addresses cannot be forged ======================================

    public function testForgedForwardingHeadersDoNotHelpToGetAroundThePublicLimit(): void
    {
        [$in, $out] = $this->prepare();
        $client = $this->client();
        $fields = $this->hiddenFields($this->toSummary($client, 'margherita', $in, $out)->body); // no consent: each send fails but counts

        $statuses = [];
        for ($i = 1; $i <= 7; $i++) {
            $statuses[] = $client->post(self::path('request.submit'), $fields, [
                'X-Forwarded-For' => "198.51.100.$i", 'CF-Connecting-IP' => "198.51.100.1$i", 'X-Real-IP' => "198.51.100.2$i", 'Forwarded' => "for=198.51.100.3$i",
            ])->status;
        }

        self::assertSame([422, 422, 422, 422, 422, 422, 429], $statuses);
    }

    public function testForgedForwardingHeadersDoNotHelpToGetAroundTheLoginLimit(): void
    {
        $client = $this->client();
        $token = $client->get('/admin/login')->csrfToken();

        $statuses = [];
        for ($i = 1; $i <= 7; $i++) {
            $statuses[] = $client->post('/admin/login', ['_csrf' => $token, 'username' => self::ADMIN_USER, 'password' => "wrong$i"], ['X-Forwarded-For' => "198.51.100.$i"])->status;
        }

        self::assertSame([422, 422, 422, 422, 422, 429, 429], $statuses);
    }

    // === Invisible characters ===================================================

    public function testInvisibleAndDirectionCharactersInANameDoNotReachTheDatabase(): void
    {
        [$in, $out] = $this->prepare();
        $client = $this->client();
        $customer = ['first_name' => "Ma\u{202E}rio\u{200B}", 'last_name' => "\u{FEFF}Ro\u{2066}ssi", 'notes' => "ciao\u{200F}"] + self::CUSTOMER;

        $this->sendFromSummary($client, $this->toSummary($client, 'margherita', $in, $out, $customer));

        $row = $this->db->query('SELECT first_name, last_name, notes FROM booking_requests')->fetch();
        self::assertSame(['first_name' => 'Mario', 'last_name' => 'Rossi', 'notes' => 'ciao'], $row);
    }

    // === Failed logins on the dashboard =========================================

    public function testTheDashboardCountsTheFailedLoginsOfTheLast24Hours(): void
    {
        $visitor = $this->client();
        $token = $visitor->get('/admin/login')->csrfToken();
        foreach (['uno', 'due', 'tre'] as $wrong) {
            self::assertSame(422, $visitor->post('/admin/login', ['_csrf' => $token, 'username' => self::ADMIN_USER, 'password' => $wrong])->status);
        }
        $this->db->exec("INSERT INTO rate_limit_hits (bucket, key_hash, created_at) VALUES ('admin_login_failed', REPEAT('a', 64), UTC_TIMESTAMP() - INTERVAL 2 DAY)");

        $admin = $this->loggedInClient();
        $dashboard = $admin->get('/admin')->body;

        self::assertStringContainsString('Accessi falliti nelle ultime 24 ore', $dashboard);
        self::assertMatchesRegularExpression('#Accessi falliti nelle ultime 24 ore</dt>\s*<dd>3</dd>#', $dashboard, 'three failures, not the one from two days ago');
        self::assertStringNotContainsString('molti tentativi di accesso falliti', $dashboard);
    }

    public function testTheDashboardWarnsAfterManyFailedLogins(): void
    {
        $this->db->exec("INSERT INTO rate_limit_hits (bucket, key_hash) SELECT 'admin_login_failed', REPEAT('b', 64) FROM information_schema.columns LIMIT 12");

        $dashboard = $this->loggedInClient()->get('/admin')->body;

        self::assertStringContainsString('molti tentativi di accesso falliti', $dashboard);
    }
}
