<?php

declare(strict_types=1);

namespace Tests\Http;

use Tests\Support\HttpClient;
use Tests\Support\HttpTestCase;
use Tests\Support\TestServer;

/** Session lifecycle: logout, fixation, expiry, credential changes, cookie flags. */
final class AdminSessionTest extends HttpTestCase
{
    private function sessionFile(HttpClient $client): string
    {
        return dirname(__DIR__, 2) . '/storage/sessions/sess_' . $client->sessionId();
    }

    public function testLogoutEndsTheSessionEvenIfTheOldCookieIsReplayed(): void
    {
        $client = $this->loggedInClient();
        $stolenCookie = $client->cookies;
        $token = $this->tokenFor($client);

        $logout = $client->post('/admin/logout', ['_csrf' => $token]);

        self::assertSame(303, $logout->status);
        self::assertStringEndsWith('/admin/login', (string) $logout->location());
        self::assertSame(303, $client->get('/admin')->status, 'logged out');

        $attacker = $this->client();
        $attacker->cookies = $stolenCookie;
        self::assertSame(303, $attacker->get('/admin')->status, 'the old cookie must be useless after logout');
        self::assertSame(401, $attacker->post('/admin/richieste/1/rifiuta', ['_csrf' => $token])->status);
    }

    public function testSessionFixationIsDefeated(): void
    {
        $victim = $this->client();
        $victim->get('/admin/login');
        $plantedSession = $victim->sessionId();
        self::assertNotNull($plantedSession);
        $token = $victim->get('/admin/login')->csrfToken();

        $victim->post('/admin/login', ['_csrf' => $token, 'username' => self::ADMIN_USER, 'password' => self::ADMIN_PASSWORD]);
        self::assertNotSame($plantedSession, $victim->sessionId(), 'a new session id must be issued at login');

        $attacker = $this->client();
        $attacker->cookies = ['lavolta_session' => $plantedSession];
        self::assertSame(303, $attacker->get('/admin')->status, 'the id known before login must not be authenticated');
    }

    public function testUnknownSessionIdsAreNotAdopted(): void
    {
        $client = $this->client();
        $client->cookies = ['lavolta_session' => 'attackerchosenid1234567890abcdef'];

        $response = $client->get('/admin/login');

        self::assertSame(200, $response->status);
        self::assertNotSame('attackerchosenid1234567890abcdef', $client->sessionId(), 'strict mode must replace an id the server never issued');
    }

    public function testSessionsExpireAfterBeingIdle(): void
    {
        $client = $this->loggedInClient();
        self::assertSame(200, $client->get('/admin')->status);

        $file = $this->sessionFile($client);
        $content = (string) file_get_contents($file);
        $stale = preg_replace('/s:7:"seen_at";i:\d+;/', 's:7:"seen_at";i:' . (time() - 7300) . ';', $content, -1, $count);
        self::assertSame(1, $count, 'could not age the session file');
        file_put_contents($file, $stale);

        self::assertSame(303, $client->get('/admin')->status, 'idle for more than 2 hours: logged out');
    }

    public function testSessionsExpireAfterTheAbsoluteLifetime(): void
    {
        $client = $this->loggedInClient();
        $file = $this->sessionFile($client);
        $content = (string) file_get_contents($file);
        $old = preg_replace('/s:8:"login_at";i:\d+;/', 's:8:"login_at";i:' . (time() - 43300) . ';', $content, -1, $count);
        self::assertSame(1, $count);
        file_put_contents($file, $old);

        self::assertSame(303, $client->get('/admin')->status, 'older than 12 hours: logged out even if active');
    }

    public function testActivityKeepsTheSessionAlive(): void
    {
        $client = $this->loggedInClient();
        $file = $this->sessionFile($client);
        $content = (string) file_get_contents($file);
        file_put_contents($file, preg_replace('/s:7:"seen_at";i:\d+;/', 's:7:"seen_at";i:' . (time() - 3600) . ';', $content));

        self::assertSame(200, $client->get('/admin')->status, 'one hour idle is still fine');
        self::assertGreaterThan(time() - 60, $this->seenAt($file), 'activity renews the idle timer');
    }

    private function seenAt(string $file): int
    {
        return preg_match('/s:7:"seen_at";i:(\d+);/', (string) file_get_contents($file), $m) === 1 ? (int) $m[1] : 0;
    }

    public function testChangingThePasswordEndsEverySession(): void
    {
        $first = $this->loggedInClient();
        $second = $this->loggedInClient();
        self::assertSame(200, $first->get('/admin')->status);
        self::assertSame(200, $second->get('/admin')->status);

        // The real command-line tool, against the test database, as the owner would run it.
        $root = dirname(__DIR__, 2);
        $process = proc_open(
            [PHP_BINARY, $root . '/bin/create-admin.php', self::ADMIN_USER],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            $root,
            array_merge(getenv(), ['DB_NAME' => 'agriturismo_test', 'APP_ENV' => 'development']),
        );
        fwrite($pipes[0], "another long password 12345\nanother long password 12345\n");
        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
        proc_close($process);
        self::assertStringContainsString('aggiornate', $output);

        self::assertSame(303, $first->get('/admin')->status, 'the old session must end when the password changes');
        self::assertSame(303, $second->get('/admin')->status);
        self::assertSame(401, $first->post('/admin/blocchi', ['_csrf' => 'x'])->status);

        // The new password works, the old one does not.
        $fresh = $this->client();
        $token = $fresh->get('/admin/login')->csrfToken();
        self::assertSame(422, $fresh->post('/admin/login', ['_csrf' => $token, 'username' => self::ADMIN_USER, 'password' => self::ADMIN_PASSWORD])->status);
        self::assertSame(303, $fresh->post('/admin/login', ['_csrf' => $token, 'username' => self::ADMIN_USER, 'password' => 'another long password 12345'])->status);
        self::assertSame(200, $fresh->get('/admin')->status);
    }

    public function testDeletingTheAccountEndsTheSession(): void
    {
        $client = $this->loggedInClient();
        self::assertSame(200, $client->get('/admin')->status);

        $this->db->exec('DELETE FROM admin');

        self::assertSame(303, $client->get('/admin')->status);
        self::assertSame(401, $client->post('/admin/logout', ['_csrf' => 'x'])->status);
    }

    public function testASessionOfADifferentAdminRowDoesNotSurviveAnIdSwap(): void
    {
        $client = $this->loggedInClient();

        // Same username re-created: a different account row and password hash.
        $this->db->exec('DELETE FROM admin');
        $this->db->prepare('INSERT INTO admin (username, password_hash) VALUES (?, ?)')
            ->execute([self::ADMIN_USER, password_hash('somebody else', PASSWORD_DEFAULT)]);

        self::assertSame(303, $client->get('/admin')->status);
    }

    public function testSessionCookieFlags(): void
    {
        $response = $this->client()->get('/admin/login');
        $cookie = implode("\n", $response->setCookies());

        self::assertStringContainsString('lavolta_session=', $cookie);
        self::assertMatchesRegularExpression('/;\s*httponly\b/i', $cookie);
        self::assertMatchesRegularExpression('/;\s*samesite=lax\b/i', $cookie);
        self::assertDoesNotMatchRegularExpression('/;\s*secure\b/i', $cookie, 'plain HTTP in development: no Secure flag');
        self::assertStringContainsString('path=/', $cookie);
    }

    public function testSessionCookieIsSecureWhenTheSiteRunsOverHttps(): void
    {
        $https = TestServer::start(['APP_URL' => 'https://shop.example.test']);
        try {
            $client = new HttpClient($https->baseUrl());
            $cookie = implode("\n", $client->get('/admin/login')->setCookies());

            self::assertMatchesRegularExpression('/;\s*secure\b/i', $cookie, 'HTTPS site: the cookie must be Secure');
            self::assertMatchesRegularExpression('/;\s*httponly\b/i', $cookie);
            self::assertMatchesRegularExpression('/;\s*samesite=lax\b/i', $cookie);
        } finally {
            $https->stop();
        }
    }

    public function testTheSessionCookieIsNotReadableAsAPlainValueInPages(): void
    {
        $client = $this->loggedInClient();

        self::assertStringNotContainsString((string) $client->sessionId(), $client->get('/admin')->body);
    }
}
