<?php

declare(strict_types=1);

namespace Tests\Http;

use App\App;
use Tests\Support\HttpClient;
use Tests\Support\PublicSiteTestCase;
use Tests\Support\TestServer;

/**
 * Security review of the running application: response headers, error leakage, hostile input,
 * request size, and personal data in logs. (Admin access, CSRF and sessions have their own suites.)
 */
final class SecurityTest extends PublicSiteTestCase
{
    // === Headers ================================================================

    public function testSecurityHeadersAreOnEveryKindOfResponseAndPhpIsNotAdvertised(): void
    {
        $client = $this->client();
        $responses = [
            'home' => $client->get('/'),
            'apartment' => $client->get('/appartamenti/rosa'),
            '404' => $client->get('/pagina-che-non-esiste'),
            '404 en' => $client->get('/en/nothing'),
            '405' => $client->post('/appartamenti', ['x' => 'y']),
            '403 form' => $client->post('/richiedi-disponibilita/invia', ['x' => 'y']),
            'login' => $client->get('/admin/login'),
            'admin redirect' => $client->get('/admin'),
            'admin 404' => $client->get('/admin/non-esiste'),
            'robots' => $client->get('/robots.txt'),
            'sitemap' => $client->get('/sitemap.xml'),
        ];

        foreach ($responses as $name => $response) {
            $name = (string) $name;
            self::assertSame('nosniff', $response->header('X-Content-Type-Options'), $name);
            self::assertSame('DENY', $response->header('X-Frame-Options'), $name);
            self::assertSame('strict-origin-when-cross-origin', $response->header('Referrer-Policy'), $name);
            self::assertSame('same-origin', $response->header('Cross-Origin-Opener-Policy'), $name);
            self::assertSame('none', $response->header('X-Permitted-Cross-Domain-Policies'), $name);
            self::assertStringContainsString('camera=()', (string) $response->header('Permissions-Policy'), $name);
            self::assertStringContainsString('geolocation=()', (string) $response->header('Permissions-Policy'), $name);
            $csp = (string) $response->header('Content-Security-Policy');
            self::assertStringContainsString("default-src 'self'", $csp, $name);
            self::assertStringContainsString("frame-ancestors 'none'", $csp, $name);
            self::assertStringContainsString("object-src 'none'", $csp, $name);
            self::assertStringNotContainsString("'unsafe-inline'", $csp, $name);
            self::assertStringNotContainsString("'unsafe-eval'", $csp, $name);
            self::assertNull($response->header('X-Powered-By'), "$name must not advertise PHP");
        }
    }

    public function testStrictTransportSecurityOnlyWhenTheSiteIsConfiguredForHttps(): void
    {
        self::assertNull($this->client()->get('/')->header('Strict-Transport-Security'), 'a plain HTTP installation must never send HSTS');

        $cases = [
            'https' => [['APP_URL' => 'https://sito.example.test'], 'max-age=15552000'],
            'custom age' => [['APP_URL' => 'https://sito.example.test', 'HSTS_MAX_AGE' => '600'], 'max-age=600'],
            'disabled' => [['APP_URL' => 'https://sito.example.test', 'HSTS_MAX_AGE' => '0'], null],
            'http url' => [['APP_URL' => 'http://sito.example.test'], null],
        ];
        foreach ($cases as $name => [$env, $expected]) {
            $server = TestServer::start($env);
            try {
                $header = (new HttpClient($server->baseUrl()))->get('/')->header('Strict-Transport-Security');
                self::assertSame($expected, $header, $name);
                if ($header !== null) {
                    self::assertStringNotContainsString('includeSubDomains', $header, 'subdomains are not ours to decide');
                    self::assertStringNotContainsString('preload', $header);
                }
            } finally {
                $server->stop();
            }
        }
    }

    // === Error handling ============================================================

    public function testServerErrorsShowNothingEvenWithDebugSwitchedOnInProduction(): void
    {
        $password = App::current()->config->string('DB_PASSWORD');
        // Production settings plus APP_DEBUG=true (ignored in production) and a database that does not exist.
        $server = TestServer::start(['APP_ENV' => 'production', 'APP_DEBUG' => 'true', 'DB_NAME' => 'no_such_database_test']);
        try {
            $client = new HttpClient($server->baseUrl());
            foreach (['/', '/appartamenti', '/appartamenti/rosa', '/en/apartments', '/sitemap.xml'] as $path) {
                $response = $client->get($path);

                self::assertSame(500, $response->status, $path);
                self::assertStringContainsString(str_starts_with($path, '/en') ? 'Server error' : 'Errore del server', $response->body, $path);
                foreach (['SQLSTATE', 'PDOException', 'Stack trace', 'no_such_database', '/var/www', '.php', 'vendor/', 'Connection.php', 'mysql', 'mariadb'] as $leak) {
                    self::assertStringNotContainsStringIgnoringCase($leak, $response->body, "$path leaks: $leak");
                }
                if ($password !== '') {
                    self::assertStringNotContainsString($password, $response->body, $path);
                }
                self::assertSame('nosniff', $response->header('X-Content-Type-Options'), 'error pages carry the security headers too');
                self::assertNull($response->header('X-Powered-By'));
            }
        } finally {
            $server->stop();
        }
    }

    // === Hostile input ===============================================================

    /** @return list<string> */
    private function payloads(): array
    {
        return [
            "' OR '1'='1", "1; DROP TABLE apartments; --", "' UNION SELECT password_hash FROM admin --", '" OR ""="',
            '<script>alert(1)</script>', '"><img src=x onerror=alert(1)>', 'javascript:alert(1)',
            '../../../../etc/passwd', '..%2f..%2f.env', '{{7*7}}', '${7*7}', '%00', "a\0b",
            "x\r\nSet-Cookie: pwned=1", "x\r\nX-Injected: 1", str_repeat('A', 5000), '😀', '%', '\\', "\xC3\x28",
        ];
    }

    public function testHostileInputInEveryPublicParameterIsHarmless(): void
    {
        [$in, $out] = $this->stay();
        $this->equip('margherita', 4, [$in, $out]);
        $client = $this->client();
        $before = $this->snapshot();
        $apartments = (int) $this->scalar('SELECT COUNT(*) FROM apartments');
        $admins = (int) $this->scalar('SELECT COUNT(*) FROM admin');
        $base = $this->searchParams($in, $out) + ['apartment' => 'margherita'];

        foreach ($this->payloads() as $payload) {
            $urls = [
                self::path('request.apartments') . '?' . http_build_query(['check_in' => $payload] + $base),
                self::path('request.apartments') . '?' . http_build_query(['adults' => $payload] + $base),
                self::path('request.details') . '?' . http_build_query(['apartment' => $payload] + $base),
                self::path('request') . '?' . http_build_query(['check_out' => $payload] + $base),
                self::path('request.received') . '?rif=' . rawurlencode($payload),
                '/appartamenti/' . rawurlencode($payload),
                '/en/apartments/' . rawurlencode($payload),
                '/' . rawurlencode($payload),
            ];
            foreach ($urls as $url) {
                $response = $client->get($url);

                self::assertLessThan(500, $response->status, "$url -> " . $response->status);
                self::assertSame([], $response->setCookies(), $url);
                self::assertNull($response->header('X-Injected'), "header injection through $url");
                self::assertStringNotContainsString('<script>alert(1)</script>', $response->body, $url);
                self::assertStringNotContainsString('<img src=x', $response->body, $url);
                self::assertStringNotContainsString('root:x:0', $response->body, $url);
                self::assertStringNotContainsString('SQLSTATE', $response->body, $url);
            }
        }
        $this->assertDatabaseUnchanged($before, 'hostile GET parameters must not change anything');
        self::assertSame($apartments, (int) $this->scalar('SELECT COUNT(*) FROM apartments'));
        self::assertSame($admins, (int) $this->scalar('SELECT COUNT(*) FROM admin'));
    }

    public function testHostileInputInTheFormFieldsIsStoredAsTextAndNeverExecuted(): void
    {
        [$in, $out] = $this->stay();
        $this->equip('margherita', 4, [$in, $out]);
        $payload = "'); DROP TABLE booking_requests; -- <script>alert(1)</script>";
        $client = $this->client();

        $summary = $this->toSummary($client, 'margherita', $in, $out, ['first_name' => $payload, 'last_name' => '"><b>x</b>', 'notes' => $payload] + self::CUSTOMER);
        self::assertSame(200, $summary->status);
        self::assertStringNotContainsString('<script>alert(1)</script>', $summary->body);
        $sent = $this->sendFromSummary($client, $summary);

        self::assertSame(303, $sent->status);
        self::assertSame($payload, $this->row('SELECT first_name FROM booking_requests')['first_name']);
        self::assertSame(1, $this->requestCount(), 'the table still exists and holds exactly the new request');
        $admin = $this->loggedInClient()->get('/admin/richieste');
        self::assertStringNotContainsString('<script>alert(1)</script>', $admin->body, 'the admin area escapes it too');
    }

    public function testRequestBodiesAboveTheLimitAreRefused(): void
    {
        $client = $this->client();
        $big = 'a=' . str_repeat('x', 1_200_000);

        foreach (['/richiedi-disponibilita/riepilogo', '/richiedi-disponibilita/invia', '/admin/login', '/appartamenti'] as $path) {
            $response = $client->send('POST', $path, $big, ['Content-Type' => 'application/x-www-form-urlencoded']);

            self::assertSame(413, $response->status, $path);
            self::assertSame([], $response->setCookies(), $path);
        }
        self::assertSame(0, $this->requestCount());
    }

    // === Personal data in logs ======================================================

    private function logSize(): array
    {
        $sizes = [];
        foreach (glob(dirname(__DIR__, 2) . '/storage/logs/*.log') ?: [] as $file) {
            $sizes[$file] = filesize($file);
        }
        return $sizes;
    }

    /** What was appended to the log files since $before. @param array<string, int> $before */
    private function logSince(array $before): string
    {
        clearstatcache();
        $text = '';
        foreach (glob(dirname(__DIR__, 2) . '/storage/logs/*.log') ?: [] as $file) {
            $text .= (string) file_get_contents($file, false, null, $before[$file] ?? 0);
        }
        return $text;
    }

    public function testNoPersonalDataOrSecretReachesTheLogs(): void
    {
        [$in, $out] = $this->stay();
        $this->equip('margherita', 4, [$in, $out]);
        $before = $this->logSize();
        $customer = ['first_name' => 'ZxqNome', 'last_name' => 'ZxqCognome', 'email' => 'zxq.cliente@example.test', 'phone' => '+39 333 7770001', 'notes' => 'ZxqNota riservata'];
        $client = $this->client();

        // A validation error, then a complete request, then its confirmation by the admin.
        $this->toSummary($this->client(), 'margherita', $in, $out, ['email' => 'ZxqMailSbagliata'] + $customer);
        $summary = $this->toSummary($client, 'margherita', $in, $out, $customer);
        $this->sendFromSummary($client, $summary);
        $admin = $this->loggedInClient();
        $id = (int) $this->scalar('SELECT id FROM booking_requests');
        $admin->post('/admin/richieste/' . $id . '/conferma', ['_csrf' => $this->tokenFor($admin, '/admin/richieste/' . $id)]);

        // Things that go wrong: bad token, honeypot, rate limit, failed login, CSRF failure.
        $fields = $this->hiddenFields($summary->body) + ['privacy_accepted' => '1'];
        $this->client()->post(self::path('request.submit'), ['_form' => 'ZxqTokenFalso'] + $fields);
        $this->client()->post(self::path('request.submit'), ['contact_website' => 'ZxqSpam'] + $fields);
        $limited = $this->client();
        for ($i = 0; $i < 8; $i++) {
            $limited->post(self::path('request.submit'), $fields + ['privacy_accepted' => '0']);
        }
        $this->client()->post('/admin/login', ['username' => 'ZxqUtente', 'password' => 'ZxqPassword!42', '_csrf' => 'x']);
        $login = $this->client();
        $login->post('/admin/login', ['_csrf' => $login->get('/admin/login')->csrfToken(), 'username' => 'ZxqUtente', 'password' => 'ZxqPassword!42']);

        $log = $this->logSince($before);
        self::assertNotSame('', $log, 'the scenario should have produced log lines (otherwise this test proves nothing)');
        self::assertStringContainsString('Admin login failed', $log);
        self::assertStringContainsString('invalid token', $log);
        foreach (['ZxqNome', 'ZxqCognome', 'zxq.cliente', '7770001', 'ZxqNota', 'ZxqMailSbagliata', 'ZxqTokenFalso', 'ZxqSpam', 'ZxqUtente', 'ZxqPassword', self::ADMIN_PASSWORD, '127.0.0.1'] as $secret) {
            self::assertStringNotContainsString($secret, $log, "the log contains: $secret");
        }
    }
}
