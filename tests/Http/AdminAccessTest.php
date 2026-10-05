<?php

declare(strict_types=1);

namespace Tests\Http;

use Tests\Support\HttpTestCase;

/**
 * Anonymous visitors: they must not read or change anything under /admin. Every route is
 * enumerated from the real router (not hand-written) and exercised over real HTTP.
 */
final class AdminAccessTest extends HttpTestCase
{
    /** Routes reachable without logging in (the login form itself). */
    private const PUBLIC_ADMIN_ROUTES = ['GET /admin/login', 'POST /admin/login'];

    /** @return list<array{method: string, pattern: string, path: string}> */
    private function protectedRoutes(): array
    {
        return array_values(array_filter(
            $this->adminRoutes(),
            fn (array $r): bool => !in_array($r['method'] . ' ' . $r['pattern'], self::PUBLIC_ADMIN_ROUTES, true),
        ));
    }

    public function testRouteEnumerationIsNotVacuous(): void
    {
        $routes = $this->protectedRoutes();

        self::assertGreaterThanOrEqual(30, count($routes));
        self::assertNotEmpty(array_filter($routes, static fn (array $r): bool => $r['method'] === 'POST'));
        self::assertNotEmpty(array_filter($routes, static fn (array $r): bool => $r['method'] === 'GET'));
    }

    public function testAnonymousGetIsRedirectedToTheLoginPageWithoutAnyContent(): void
    {
        $this->makeRequest(); // there is data an anonymous visitor must never see
        $client = $this->client();

        foreach ($this->protectedRoutes() as $route) {
            if ($route['method'] !== 'GET') {
                continue;
            }
            $response = $client->get($route['path']);

            self::assertSame(303, $response->status, "GET {$route['path']}");
            self::assertStringEndsWith('/admin/login', (string) $response->location(), "GET {$route['path']}");
            self::assertStringNotContainsString('Mario', $response->body, "GET {$route['path']} leaked data");
            self::assertStringNotContainsString('Margherita', $response->body, "GET {$route['path']} leaked data");
        }
    }

    public function testAnonymousPostGets401AndChangesNothing(): void
    {
        $request = $this->makeRequest();
        $this->book('2027-08-01', '2027-08-05');
        $before = $this->snapshot();
        $client = $this->client();

        $payloads = [
            [],
            ['apartment_id' => $this->apartmentId(), 'origin' => 'phone', 'check_in' => '2027-09-01', 'check_out' => '2027-09-05', 'adults' => '2', 'guest_name' => 'Intruso'],
            ['apartment_id' => $this->apartmentId(), 'start_date' => '2027-09-01', 'end_date' => '2027-09-05'],
            ['name' => 'Hacked', 'is_active' => '0', 'label_it' => 'x', 'nightly_rate' => '1', 'amount' => '1', 'applies_to' => 'stay', 'charge_basis' => 'per_stay'],
        ];

        foreach ($this->protectedRoutes() as $route) {
            if ($route['method'] !== 'POST') {
                continue;
            }
            foreach ($payloads as $payload) {
                $response = $client->post($route['path'], $payload);
                self::assertSame(401, $response->status, "POST {$route['path']}");
                self::assertStringNotContainsString('Intruso', $response->body);
            }
        }

        self::assertSame('pending', $this->scalar('SELECT status FROM booking_requests WHERE id = ?', [$request['id']]));
        $this->assertDatabaseUnchanged($before, 'an anonymous POST must not change the database');
    }

    public function testAnonymousPostWithAValidCsrfTokenOfAnonymousSessionIsStill401(): void
    {
        $client = $this->client();
        $token = $client->get('/admin/login')->csrfToken(); // a real session and token, but nobody is logged in
        $request = $this->makeRequest();
        $before = $this->snapshot();

        $response = $client->post('/admin/richieste/' . $request['id'] . '/conferma', ['_csrf' => $token]);

        self::assertSame(401, $response->status);
        $this->assertDatabaseUnchanged($before, 'authentication is checked before anything else');
    }

    public function testUnknownAdminUrlsRevealNothingToAnonymousVisitors(): void
    {
        $client = $this->client();

        foreach (['/admin/non-esiste', '/admin/richieste/abc', '/admin/../admin/richieste', '/admin/.env', '/admin/export/x.csv'] as $path) {
            $response = $client->get($path);
            self::assertSame(303, $response->status, "GET $path must behave like any other protected URL");
            self::assertStringEndsWith('/admin/login', (string) $response->location());
        }
        self::assertSame(401, $client->post('/admin/non-esiste')->status);
        self::assertSame(401, $client->send('DELETE', '/admin/richieste/1')->status);
        self::assertSame(401, $client->send('PUT', '/admin/prenotazioni/1', 'x=1')->status);
        self::assertSame(401, $client->send('OPTIONS', '/admin')->status);
        self::assertSame(303, $client->head('/admin/prenotazioni')->status);
    }

    public function testUrlsThatMerelyStartWithAdminAreNotPartOfTheAdminArea(): void
    {
        $client = $this->client();

        foreach (['/administrator', '/adminx', '/admin-panel'] as $path) {
            $response = $client->get($path);
            self::assertSame(404, $response->status, $path);
            self::assertNull($response->location());
        }
    }

    public function testEveryAdminResponseIsPrivate(): void
    {
        $client = $this->client();
        $responses = [
            $client->get('/admin'),                         // 303
            $client->post('/admin/richieste/1/conferma'),   // 401
            $client->get('/admin/login'),                   // 200
            $client->post('/admin/login'),                  // 403 (no token)
            $client->get('/admin/non-esiste'),              // 303
        ];

        foreach ($responses as $i => $response) {
            self::assertSame('no-store', $response->header('Cache-Control'), "response #$i");
            self::assertSame('noindex, nofollow', $response->header('X-Robots-Tag'), "response #$i");
            self::assertSame('nosniff', $response->header('X-Content-Type-Options'), "response #$i");
            self::assertSame('DENY', $response->header('X-Frame-Options'), "response #$i");
            self::assertStringContainsString("frame-ancestors 'none'", (string) $response->header('Content-Security-Policy'), "response #$i");
        }
    }

    public function testAnonymousTrafficCreatesNoSessionAndNoCookie(): void
    {
        $client = $this->client();

        foreach ([$client->get('/'), $client->get('/admin'), $client->post('/admin/richieste/1/conferma'), $client->get('/admin/export/richieste.csv')] as $response) {
            self::assertSame([], $response->setCookies());
        }
        self::assertSame([], $client->cookies);
    }

    public function testThePublicSiteDoesNotLinkToTheAdminArea(): void
    {
        $html = $this->client()->get('/')->body;

        self::assertStringNotContainsString('/admin', $html);
        self::assertStringNotContainsString('Amministrazione', $html);
    }

    public function testLoginPageIsReachableAndHasAFormWithAToken(): void
    {
        $response = $this->client()->get('/admin/login');

        self::assertSame(200, $response->status);
        self::assertNotNull($response->csrfToken());
        self::assertStringContainsString('<input type="password"', $response->body);
        self::assertStringNotContainsString('registr', strtolower($response->body), 'there is no public sign-up');
    }

    public function testThereIsNoPublicWayToCreateAnAdmin(): void
    {
        $client = $this->client();

        foreach (['/admin/registrazione', '/admin/register', '/admin/utenti/nuovo', '/register', '/signup'] as $path) {
            self::assertContains($client->get($path)->status, [303, 404], $path);
            self::assertContains($client->post($path, ['username' => 'evil', 'password' => 'evil-password-123'])->status, [401, 404, 405], $path);
        }
        self::assertSame(1, $this->countRows('admin'));
    }

    // --- login ---------------------------------------------------------------

    public function testLoginRejectsWrongCredentialsWithAGenericMessage(): void
    {
        $client = $this->client();
        $token = $client->get('/admin/login')->csrfToken();

        $wrongPassword = $client->post('/admin/login', ['_csrf' => $token, 'username' => self::ADMIN_USER, 'password' => 'wrong']);
        $unknownUser = $client->post('/admin/login', ['_csrf' => $token, 'username' => 'nobody', 'password' => 'wrong']);

        self::assertSame(422, $wrongPassword->status);
        self::assertSame(422, $unknownUser->status);
        self::assertStringContainsString('Nome utente o password non corretti', $wrongPassword->body);
        self::assertSame(
            preg_replace('/value="[^"]*"/', '', preg_replace('/name="_csrf" value="[^"]*"/', '', $wrongPassword->body)),
            preg_replace('/value="[^"]*"/', '', preg_replace('/name="_csrf" value="[^"]*"/', '', $unknownUser->body)),
            'unknown user and wrong password must look identical',
        );
        self::assertSame(303, $client->get('/admin')->status, 'still not logged in');
    }

    public function testLoginWithoutCsrfTokenIsRefused(): void
    {
        $client = $this->client();
        $client->get('/admin/login');

        $response = $client->post('/admin/login', ['username' => self::ADMIN_USER, 'password' => self::ADMIN_PASSWORD]);

        self::assertSame(403, $response->status);
        self::assertSame(303, $client->get('/admin')->status, 'a correct password without a token must not log in');
    }

    public function testLoginIsRateLimitedAfterFiveFailures(): void
    {
        $client = $this->client();
        $token = $client->get('/admin/login')->csrfToken();

        $statuses = [];
        for ($i = 1; $i <= 7; $i++) {
            $statuses[] = $client->post('/admin/login', ['_csrf' => $token, 'username' => self::ADMIN_USER, 'password' => "wrong$i"])->status;
        }
        self::assertSame([422, 422, 422, 422, 422, 429, 429], $statuses);

        $correct = $client->post('/admin/login', ['_csrf' => $token, 'username' => self::ADMIN_USER, 'password' => self::ADMIN_PASSWORD]);
        self::assertSame(429, $correct->status, 'the right password is also refused while the limit is active');
        self::assertSame(303, $client->get('/admin')->status);
    }

    public function testSuccessfulLoginGrantsAccess(): void
    {
        $client = $this->loggedInClient();

        $page = $client->get('/admin');
        self::assertSame(200, $page->status);
        self::assertStringContainsString('Area amministrativa', $page->body);
        self::assertSame(303, $client->get('/admin/login')->status, 'a logged-in admin does not need the login form');
    }

    public function testOversizedRequestsAreRefusedBeforeAnythingElse(): void
    {
        $response = $this->client()->send('POST', '/admin/login', str_repeat('a=b&', 400_000), ['Content-Type' => 'application/x-www-form-urlencoded']);

        self::assertSame(413, $response->status);
    }
}
