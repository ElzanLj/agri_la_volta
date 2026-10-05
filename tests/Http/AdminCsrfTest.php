<?php

declare(strict_types=1);

namespace Tests\Http;

use Tests\Support\HttpClient;
use Tests\Support\HttpTestCase;

/**
 * A logged-in admin whose browser is tricked (or a script that is not the admin's page):
 * every state-changing request must carry the session's CSRF token and come from this site.
 */
final class AdminCsrfTest extends HttpTestCase
{
    /** @return list<array{method: string, pattern: string, path: string}> */
    private function postRoutes(): array
    {
        return array_values(array_filter($this->adminRoutes(), static fn (array $r): bool => $r['method'] === 'POST'));
    }

    /** A real request and booking that the attacks below would love to change. @return array{request: int, booking: int, block: int} */
    private function seed(): array
    {
        $request = $this->makeRequest('2027-06-10', '2027-06-15');
        $booking = $this->book('2027-08-01', '2027-08-05');
        $block = $this->service->createBlock($this->apartmentId('rosa'), '2027-09-01', '2027-09-05', 'test');
        return ['request' => $request['id'], 'booking' => $booking, 'block' => $block];
    }

    /** Valid payload fields for a route, so that a request with a good token would really act. @return array<string, string> */
    private function payloadFor(string $pattern, array $ids): array
    {
        return match (true) {
            str_contains($pattern, '/blocchi/') => [],
            $pattern === '/admin/blocchi' => ['apartment_id' => (string) $this->apartmentId('viola'), 'start_date' => '2027-10-01', 'end_date' => '2027-10-05'],
            $pattern === '/admin/prenotazioni' => ['apartment_id' => (string) $this->apartmentId('viola'), 'origin' => 'phone', 'check_in' => '2027-10-01', 'check_out' => '2027-10-05', 'adults' => '2', 'guest_name' => 'Attaccante'],
            $pattern === '/admin/logout' => [],
            default => ['label_it' => 'Hacked', 'name' => 'Hacked', 'amount' => '1', 'nightly_rate' => '1'],
        };
    }

    private function idFor(string $pattern, array $ids): string
    {
        return (string) match (true) {
            str_contains($pattern, '/richieste/') => $ids['request'],
            str_contains($pattern, '/prenotazioni/') => $ids['booking'],
            str_contains($pattern, '/blocchi/') => $ids['block'],
            str_contains($pattern, '/appartamenti/') => $this->apartmentId(),
            default => 1,
        };
    }

    public function testEveryPostWithoutAValidTokenIsForbiddenAndChangesNothing(): void
    {
        $ids = $this->seed();
        $client = $this->loggedInClient();
        $foreignToken = $this->tokenFor($this->loggedInClient()); // a valid token, but of another session
        $before = $this->snapshot();

        $variants = [
            'no token' => fn (array $p): array => $p,
            'empty token' => fn (array $p): array => $p + ['_csrf' => ''],
            'wrong token' => fn (array $p): array => $p + ['_csrf' => str_repeat('a', 64)],
            'token of another session' => fn (array $p): array => $p + ['_csrf' => $foreignToken],
            'token as an array' => fn (array $p): array => $p + ['_csrf' => [$foreignToken]],
        ];

        foreach ($this->postRoutes() as $route) {
            if ($route['pattern'] === '/admin/login') {
                continue; // covered separately
            }
            $path = str_replace('1', $this->idFor($route['pattern'], $ids), $route['path']);
            $payload = $this->payloadFor($route['pattern'], $ids);

            foreach ($variants as $name => $build) {
                $response = $client->post($path, $build($payload));
                self::assertSame(403, $response->status, "POST {$route['pattern']} with $name");
            }
        }

        $this->assertDatabaseUnchanged($before, 'a request without a valid CSRF token must not change the database');
        self::assertSame(200, $client->get('/admin')->status, 'the refused logout attempts must not have ended the session');
    }

    public function testTheTokenInTheQueryStringIsIgnored(): void
    {
        $ids = $this->seed();
        $client = $this->loggedInClient();
        $token = $this->tokenFor($client);
        $before = $this->snapshot();

        $response = $client->post('/admin/richieste/' . $ids['request'] . '/conferma?_csrf=' . $token);

        self::assertSame(403, $response->status);
        $this->assertDatabaseUnchanged($before, 'a token in the URL must not authorise a change');
    }

    public function testRequestsFromAnotherSiteAreForbiddenEvenWithAValidToken(): void
    {
        $ids = $this->seed();
        $client = $this->loggedInClient();
        $token = $this->tokenFor($client);
        $before = $this->snapshot();
        $path = '/admin/richieste/' . $ids['request'] . '/conferma';

        $foreign = [
            ['Origin' => 'https://evil.example'],
            ['Origin' => 'null'],
            ['Origin' => 'http://127.0.0.1.evil.example'],
            ['Origin' => str_replace('http://', 'https://', self::$server->baseUrl())], // right host, wrong scheme
            ['Origin' => 'http://127.0.0.1:1'],                                          // right host, wrong port
            ['Referer' => 'https://evil.example/page'],
        ];
        foreach ($foreign as $headers) {
            self::assertSame(403, $client->post($path, ['_csrf' => $token], $headers)->status, json_encode($headers));
        }

        $this->assertDatabaseUnchanged($before, 'cross-origin requests must not change the database');
    }

    public function testTheSameSiteOriginOrNoBrowserHintReachesTheHandler(): void
    {
        $ids = $this->seed();
        $client = $this->loggedInClient();
        $token = $this->tokenFor($client);
        $base = self::$server->baseUrl();

        $withOrigin = $client->post('/admin/richieste/' . $ids['request'] . '/rifiuta', ['_csrf' => $token], ['Origin' => $base]);
        self::assertSame(303, $withOrigin->status, 'same-site Origin + valid token must work');
        self::assertSame('rejected', $this->scalar('SELECT status FROM booking_requests WHERE id = ?', [$ids['request']]));

        $second = $this->makeRequest('2027-07-10', '2027-07-12');
        $withReferer = $client->post('/admin/richieste/' . $second['id'] . '/rifiuta', ['_csrf' => $token], ['Referer' => $base . '/admin/richieste']);
        self::assertSame(303, $withReferer->status, 'same-site Referer + valid token must work');

        $third = $this->makeRequest('2027-07-20', '2027-07-22');
        $noHints = $client->post('/admin/richieste/' . $third['id'] . '/rifiuta', ['_csrf' => $token]);
        self::assertSame(303, $noHints->status, 'no Origin/Referer + valid token must work');
    }

    public function testAValidTokenReachesTheHandlerOfEveryPostRoute(): void
    {
        $ids = $this->seed();
        $client = $this->loggedInClient();
        $token = $this->tokenFor($client);

        foreach ($this->postRoutes() as $route) {
            if (in_array($route['pattern'], ['/admin/login', '/admin/logout'], true)) {
                continue; // login/logout end or start sessions: covered in AdminSessionTest
            }
            $path = str_replace('1', $this->idFor($route['pattern'], $ids), $route['path']);
            $response = $client->post($path, $this->payloadFor($route['pattern'], $ids) + ['_csrf' => $token]);

            self::assertNotContains($response->status, [401, 403, 405, 500], "POST {$route['pattern']} must reach its handler with a valid token (got {$response->status})");
        }
    }

    public function testGetRequestsNeverChangeTheDatabase(): void
    {
        $ids = $this->seed();
        $client = $this->loggedInClient();
        $before = $this->snapshot();

        foreach ($this->adminRoutes() as $route) {
            if ($route['method'] !== 'GET' || $route['pattern'] === '/admin/login') {
                continue;
            }
            $path = str_replace('1', $this->idFor($route['pattern'], $ids), $route['path']);
            $response = $client->get($path);
            self::assertLessThan(500, $response->status, "GET {$route['pattern']}");
        }

        $this->assertDatabaseUnchanged($before, 'GET requests must be read-only');
    }

    public function testActionUrlsCannotBeTriggeredWithGet(): void
    {
        $ids = $this->seed();
        $client = $this->loggedInClient();
        $before = $this->snapshot();

        foreach ([
            '/admin/richieste/' . $ids['request'] . '/conferma',
            '/admin/richieste/' . $ids['request'] . '/rifiuta',
            '/admin/blocchi/' . $ids['block'] . '/rimuovi',
            '/admin/listino/tariffe/1/elimina',
            '/admin/listino/regole/1/elimina',
            '/admin/logout',
        ] as $path) {
            $response = $client->get($path);
            self::assertSame(405, $response->status, "GET $path");
            self::assertSame('POST', $response->header('Allow'));
        }

        $this->assertDatabaseUnchanged($before, 'GET on an action URL must do nothing');
        self::assertSame(200, $client->get('/admin')->status, 'GET /admin/logout must not log out');
    }

    public function testMethodOverrideTricksDoNotWork(): void
    {
        $ids = $this->seed();
        $client = $this->loggedInClient();
        $before = $this->snapshot();

        $response = $client->get('/admin/richieste/' . $ids['request'] . '/conferma?_method=POST&_csrf=x');
        self::assertSame(405, $response->status);
        $response = $client->send('PUT', '/admin/richieste/' . $ids['request'] . '/conferma', http_build_query(['_csrf' => $this->tokenFor($client)]), ['Content-Type' => 'application/x-www-form-urlencoded']);
        self::assertContains($response->status, [403, 405]);

        $this->assertDatabaseUnchanged($before, 'method override must not work');
    }

    public function testLoginIsAlsoCsrfProtectedAndTheTokenRotatesAtLogin(): void
    {
        $client = $this->client();
        $before = $client->get('/admin/login');
        $preLoginToken = $before->csrfToken();
        $preLoginSession = $client->sessionId();

        $login = $client->post('/admin/login', ['_csrf' => $preLoginToken, 'username' => self::ADMIN_USER, 'password' => self::ADMIN_PASSWORD]);
        self::assertSame(303, $login->status);

        $postLoginToken = $this->tokenFor($client);
        self::assertNotSame($preLoginToken, $postLoginToken, 'the CSRF token must change at login');
        self::assertNotSame($preLoginSession, $client->sessionId(), 'the session id must change at login');

        $this->makeRequest();
        $stale = $client->post('/admin/richieste/1/rifiuta', ['_csrf' => $preLoginToken]);
        self::assertSame(403, $stale->status, 'the token issued before login is no longer valid');
    }

    public function testRefusedRequestsAreLoggedWithoutSecrets(): void
    {
        $client = $this->loggedInClient();
        $client->post('/admin/blocchi', ['_csrf' => 'secret-looking-token-value', 'reason' => 'password123']);

        $logs = '';
        foreach (glob(dirname(__DIR__, 2) . '/storage/logs/app-*.log') ?: [] as $file) {
            $logs .= (string) file_get_contents($file);
        }
        self::assertStringContainsString('CSRF check failed', $logs);
        self::assertStringNotContainsString('secret-looking-token-value', $logs);
        self::assertStringNotContainsString('password123', $logs);
        self::assertStringNotContainsString(self::ADMIN_PASSWORD, $logs);
    }

    public function testTokenCannotBeReusedByAnotherClientSession(): void
    {
        $this->seed();
        $alice = $this->loggedInClient();
        $bob = $this->loggedInClient();
        $aliceToken = $this->tokenFor($alice);
        $before = $this->snapshot();

        $response = $bob->post('/admin/blocchi', ['_csrf' => $aliceToken, 'apartment_id' => (string) $this->apartmentId(), 'start_date' => '2027-12-01', 'end_date' => '2027-12-03']);

        self::assertSame(403, $response->status);
        $this->assertDatabaseUnchanged($before, 'a stolen token only works in its own session');
    }
}
