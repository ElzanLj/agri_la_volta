<?php

declare(strict_types=1);

namespace Tests\Http;

use Tests\Support\HttpClient;
use Tests\Support\HttpResponse;
use Tests\Support\HttpTestCase;

/**
 * The Account page (password, "close all devices", recent accesses) and the "confirm your password" page,
 * over real HTTP with real sessions.
 */
final class AccountTest extends HttpTestCase
{
    private const NEW_PASSWORD = 'Il-gatto-dorme-sul-divano-blu-77';

    /** A fresh browser logged in with the given password. */
    private function login(string $password = self::ADMIN_PASSWORD): HttpClient
    {
        $client = $this->client();
        $token = $client->get('/admin/login')->csrfToken();
        $response = $client->post('/admin/login', ['_csrf' => $token, 'username' => self::ADMIN_USER, 'password' => $password]);
        self::assertSame(303, $response->status, 'login failed');
        return $client;
    }

    /** @param array<string, string> $fields */
    private function post(HttpClient $client, string $path, array $fields, string $page = '/admin/account'): HttpResponse
    {
        return $client->post($path, $fields + ['_csrf' => $this->tokenFor($client, $page)]);
    }

    /** @return array<string, string> */
    private function change(string $current, string $new, ?string $repeat = null): array
    {
        return ['current_password' => $current, 'new_password' => $new, 'new_password_confirm' => $repeat ?? $new];
    }

    private function canLogIn(string $password): bool
    {
        $client = $this->client();
        $token = $client->get('/admin/login')->csrfToken();
        return $client->post('/admin/login', ['_csrf' => $token, 'username' => self::ADMIN_USER, 'password' => $password])->status === 303;
    }

    private function sessionFile(HttpClient $client): string
    {
        return dirname(__DIR__, 2) . '/storage/sessions/sess_' . $client->sessionId();
    }

    private function ageReauth(HttpClient $client, int $secondsAgo): void
    {
        $file = $this->sessionFile($client);
        $content = (string) file_get_contents($file);
        $aged = preg_replace('/reauth_at\|i:\d+;/', 'reauth_at|i:' . (time() - $secondsAgo) . ';', $content, -1, $count);
        self::assertSame(1, $count, 'the session holds no confirmation to age');
        file_put_contents($file, $aged);
    }

    // === Access ===============================================================

    public function testTheAccountPageIsForTheLoggedInAdminOnly(): void
    {
        $anonymous = $this->client();

        self::assertSame(303, $anonymous->get('/admin/account')->status);
        self::assertSame(401, $anonymous->post('/admin/account/password', $this->change(self::ADMIN_PASSWORD, self::NEW_PASSWORD))->status);
        self::assertSame(401, $anonymous->post('/admin/account/esci-ovunque', ['close_current_password' => self::ADMIN_PASSWORD])->status);
        self::assertSame(303, $anonymous->get('/admin/conferma-password?to=/admin/account')->status);
        self::assertSame(401, $anonymous->post('/admin/conferma-password', ['password' => self::ADMIN_PASSWORD])->status);
        self::assertTrue($this->canLogIn(self::ADMIN_PASSWORD), 'nothing changed');
    }

    public function testPostsWithoutATokenOrFromAnotherSiteAreRefusedAndChangeNothing(): void
    {
        $client = $this->login();
        $token = $this->tokenFor($client, '/admin/account');
        $fields = $this->change(self::ADMIN_PASSWORD, self::NEW_PASSWORD);

        self::assertSame(403, $client->post('/admin/account/password', $fields)->status, 'no token');
        self::assertSame(403, $client->post('/admin/account/password', $fields + ['_csrf' => 'x' . $token])->status, 'wrong token');
        self::assertSame(403, $client->post('/admin/account/password', $fields + ['_csrf' => $token], ['Origin' => 'https://evil.example'])->status, 'foreign origin');
        self::assertSame(403, $client->post('/admin/account/esci-ovunque', ['close_current_password' => self::ADMIN_PASSWORD])->status);
        self::assertSame(403, $client->post('/admin/conferma-password', ['password' => self::ADMIN_PASSWORD])->status);

        self::assertTrue($this->canLogIn(self::ADMIN_PASSWORD));
        self::assertFalse($this->canLogIn(self::NEW_PASSWORD));
    }

    public function testThePageShowsTheUserTheLastAccessAndTheFailedAttempts(): void
    {
        $first = $this->login(); // a first access, so that the next one has a "previous" one
        $this->canLogIn('sbagliata-uno-xxxx');
        $this->canLogIn('sbagliata-due-xxxx');
        $second = $this->login();

        $body = $second->get('/admin/account')->body;

        self::assertStringContainsString(self::ADMIN_USER, $body);
        self::assertStringContainsString('Ultimo accesso riuscito prima di questo', $body);
        self::assertStringNotContainsString('nessuno (è il primo accesso registrato)', $body, 'the first login is remembered');
        self::assertMatchesRegularExpression('#Tentativi di accesso falliti nelle ultime 24 ore</dt>\s*<dd>2</dd>#', $body);
        self::assertStringContainsString('Cambia la password', $body);
        self::assertStringContainsString('Esci da tutti i dispositivi', $body);
        self::assertStringNotContainsString('<script', $body);
        self::assertSame(200, $first->get('/admin/account')->status);
    }

    public function testTheVeryFirstAccessSaysThereIsNoPreviousOne(): void
    {
        $this->db->exec('UPDATE admin SET last_login_at = NULL');

        $body = $this->login()->get('/admin/account')->body;

        self::assertStringContainsString('nessuno (è il primo accesso registrato)', $body);
    }

    public function testTheMenuHasTheAccountEntry(): void
    {
        self::assertStringContainsString('href="/admin/account"', $this->login()->get('/admin')->body);
    }

    // === Changing the password ================================================

    public function testAWrongCurrentPasswordChangesNothingAndIsCounted(): void
    {
        $client = $this->login();

        $response = $this->post($client, '/admin/account/password', $this->change('questa-non-e-la-password', self::NEW_PASSWORD));

        self::assertSame(422, $response->status);
        self::assertStringContainsString('La password attuale non è corretta.', $response->body);
        self::assertStringContainsString('aria-invalid="true"', $response->body);
        self::assertSame(1, $this->countRows('rate_limit_hits', "bucket = 'admin_login_failed'"), 'one failed check from the wrong current password');
        self::assertSame(1, $this->countRows('rate_limit_hits', "bucket = 'admin_reauth'"), 'and one attempt used');
        self::assertSame(0, $this->countRows('audit_log', "action = 'password_changed'"));
        self::assertTrue($this->canLogIn(self::ADMIN_PASSWORD));
        self::assertFalse($this->canLogIn(self::NEW_PASSWORD));
    }

    /** @return array<string, array{string, string, ?string, string}> */
    public static function badNewPasswords(): array
    {
        return [
            'too short' => [self::ADMIN_PASSWORD, 'Corta1!', null, 'almeno 12 caratteri'],
            'empty' => [self::ADMIN_PASSWORD, '', null, 'Scrivi la nuova password'],
            'different repeat' => [self::ADMIN_PASSWORD, self::NEW_PASSWORD, self::NEW_PASSWORD . 'x', 'Le due password non coincidono'],
            'a common one' => [self::ADMIN_PASSWORD, 'Agriturismo2026!', null, 'nome dell\'agriturismo'],
            'common without the name' => [self::ADMIN_PASSWORD, 'Password123456', null, 'troppo comune o prevedibile'],
            'the user name' => [self::ADMIN_PASSWORD, 'xx-admin-test-Qk7z!9', null, 'non può contenere il nome utente'],
            'the same as now' => [self::ADMIN_PASSWORD, self::ADMIN_PASSWORD, null, 'uguale a quella attuale'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('badNewPasswords')]
    public function testAnUnacceptableNewPasswordChangesNothingAndSaysWhy(string $current, string $new, ?string $repeat, string $message): void
    {
        $client = $this->login();

        $response = $this->post($client, '/admin/account/password', $this->change($current, $new, $repeat));

        self::assertSame(422, $response->status);
        self::assertStringContainsString($message, $response->body);
        self::assertTrue($this->canLogIn(self::ADMIN_PASSWORD), 'the old password still works');
        self::assertSame(0, $this->countRows('audit_log', "action = 'password_changed'"));
        self::assertSame(0, $this->countRows('rate_limit_hits', "bucket = 'admin_reauth'"), 'a form with an obvious mistake does not use up an attempt');
    }

    public function testChangingThePasswordWorksAndClosesTheOtherSessionsButNotThisOne(): void
    {
        $here = $this->login();
        $phone = $this->login(); // another device
        $idBefore = $here->sessionId();
        self::assertSame(200, $phone->get('/admin')->status);

        $response = $this->post($here, '/admin/account/password', $this->change(self::ADMIN_PASSWORD, self::NEW_PASSWORD));

        self::assertSame(303, $response->status, $response->body);
        self::assertStringEndsWith('/admin/account', (string) $response->location());
        self::assertNotSame($idBefore, $here->sessionId(), 'a new session id is issued');
        self::assertSame(200, $here->get('/admin')->status, 'this session stays valid');
        self::assertSame(303, $phone->get('/admin')->status, 'the other device has to log in again');
        self::assertStringEndsWith('/admin/login', (string) $phone->get('/admin')->location());

        self::assertFalse($this->canLogIn(self::ADMIN_PASSWORD), 'the old password is refused');
        self::assertTrue($this->canLogIn(self::NEW_PASSWORD), 'the new one works');
    }

    public function testTheSuccessMessageIsShownOnTheNextPage(): void
    {
        $client = $this->login();

        $response = $this->post($client, '/admin/account/password', $this->change(self::ADMIN_PASSWORD, self::NEW_PASSWORD));

        self::assertStringContainsString('Password cambiata', $client->get((string) $response->location())->body);
    }

    public function testThePasswordIsNeverWrittenAnywhere(): void
    {
        $client = $this->login();
        $this->post($client, '/admin/account/password', $this->change(self::ADMIN_PASSWORD, self::NEW_PASSWORD));
        $this->post($client, '/admin/account/password', $this->change('sbagliata-xxxxxxxx-1', 'Un-Altra-Frase-Lunga-e-Casuale-9'));

        $history = $this->db->query("SELECT CONCAT_WS(' ', summary, old_values, new_values) FROM audit_log WHERE entity_type = 'admin'")->fetchAll(\PDO::FETCH_COLUMN);
        self::assertCount(1, $history, 'only the change that happened');
        self::assertStringContainsString('Password cambiata', implode(' ', $history));
        $everything = implode("\n", $history);
        foreach ([self::ADMIN_PASSWORD, self::NEW_PASSWORD, 'sbagliata-xxxxxxxx-1', 'Un-Altra-Frase-Lunga-e-Casuale-9', '$2y$'] as $secret) {
            self::assertStringNotContainsString($secret, $everything, 'history');
        }
        $log = (string) @file_get_contents(dirname(__DIR__, 2) . '/storage/logs/app-' . gmdate('Y-m-d') . '.log');
        self::assertNotSame('', $log, 'the log exists');
        foreach ([self::ADMIN_PASSWORD, self::NEW_PASSWORD, 'sbagliata-xxxxxxxx-1', 'Un-Altra-Frase-Lunga-e-Casuale-9'] as $secret) {
            self::assertStringNotContainsString($secret, $log, 'log');
        }
        self::assertStringContainsString('Admin password changed', $log);
    }

    public function testThePasswordCannotBeGuessedWithUnlimitedTries(): void
    {
        $client = $this->login();

        $statuses = [];
        for ($i = 1; $i <= 7; $i++) {
            $statuses[] = $this->post($client, '/admin/account/password', $this->change("sbagliata-$i-xxxxx", self::NEW_PASSWORD))->status;
        }
        self::assertSame([422, 422, 422, 422, 422, 429, 429], $statuses);

        $right = $this->post($client, '/admin/account/password', $this->change(self::ADMIN_PASSWORD, self::NEW_PASSWORD));
        self::assertSame(429, $right->status, 'the right password is refused while the limit is active');
        self::assertTrue($this->canLogIn(self::ADMIN_PASSWORD), 'and nothing changed');
    }

    // === Close all devices ====================================================

    public function testClosingAllDevicesEndsTheOtherSessionsKeepsThisOneAndKeepsThePassword(): void
    {
        $here = $this->login();
        $phone = $this->login();
        $tablet = $this->login();
        $before = $this->scalar('SELECT session_version FROM admin');

        $response = $this->post($here, '/admin/account/esci-ovunque', ['close_current_password' => self::ADMIN_PASSWORD]);

        self::assertSame(303, $response->status, $response->body);
        self::assertStringContainsString('La password non è cambiata', $here->get((string) $response->location())->body, 'the message');
        self::assertSame(200, $here->get('/admin')->status, 'this session stays valid');
        self::assertSame(303, $phone->get('/admin')->status);
        self::assertSame(303, $tablet->get('/admin')->status);
        self::assertSame((int) $before + 1, (int) $this->scalar('SELECT session_version FROM admin'));
        self::assertTrue($this->canLogIn(self::ADMIN_PASSWORD), 'the password did not change');
        self::assertSame(1, $this->countRows('audit_log', "action = 'sessions_closed'"));
    }

    public function testClosingAllDevicesNeedsTheCurrentPassword(): void
    {
        $here = $this->login();
        $phone = $this->login();

        $wrong = $this->post($here, '/admin/account/esci-ovunque', ['close_current_password' => 'sbagliata-xxxxxxx']);
        $empty = $this->post($here, '/admin/account/esci-ovunque', ['close_current_password' => '']);

        self::assertSame(422, $wrong->status);
        self::assertStringContainsString('La password attuale non è corretta.', $wrong->body);
        self::assertSame(422, $empty->status);
        self::assertSame(200, $phone->get('/admin')->status, 'nobody was disconnected');
        self::assertSame(0, $this->countRows('audit_log', "action = 'sessions_closed'"));
    }

    public function testASessionOpenedAfterClosingWorksNormally(): void
    {
        $here = $this->login();
        $this->post($here, '/admin/account/esci-ovunque', ['close_current_password' => self::ADMIN_PASSWORD]);

        $later = $this->login();

        self::assertSame(200, $later->get('/admin')->status);
        self::assertSame(200, $here->get('/admin')->status);
    }

    // === Confirm the password for a sensitive action ==========================

    public function testTheConfirmationPageAsksForThePasswordAndRemembersWhereToGo(): void
    {
        $client = $this->login();

        $page = $client->get('/admin/conferma-password?to=' . rawurlencode('/admin/impostazioni'));

        self::assertSame(200, $page->status);
        self::assertStringContainsString('Conferma la tua password', $page->body);
        self::assertStringContainsString('name="to" value="/admin/impostazioni"', $page->body);
        self::assertStringContainsString('vale 5 minuti', $page->body);
    }

    /** @return array<string, array{string}> */
    public static function hostileTargets(): array
    {
        return [
            'another site' => ['https://evil.example/admin'],
            'protocol relative' => ['//evil.example'],
            'parent directory' => ['/admin/../etc'],
            'not under admin' => ['/'],
            'javascript' => ['javascript:alert(1)'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('hostileTargets')]
    public function testAHostileReturnAddressIsReplacedByTheAdminHome(string $target): void
    {
        $client = $this->login();

        $page = $client->get('/admin/conferma-password?to=' . rawurlencode($target));
        self::assertStringContainsString('name="to" value="/admin"', $page->body);

        $post = $this->post($client, '/admin/conferma-password', ['password' => self::ADMIN_PASSWORD, 'to' => $target], '/admin/conferma-password');
        self::assertSame(303, $post->status);
        self::assertStringEndsWith('/admin', (string) $post->location(), 'the person is never sent anywhere else');
        self::assertStringNotContainsString('evil', (string) $post->location());
    }

    public function testAWrongPasswordConfirmsNothingAndIsCounted(): void
    {
        $client = $this->login();
        $to = '/admin/impostazioni';

        $wrong = $this->post($client, '/admin/conferma-password', ['password' => 'sbagliata-xxxxxxx', 'to' => $to], '/admin/conferma-password');

        self::assertSame(422, $wrong->status);
        self::assertStringContainsString('La password non è corretta.', $wrong->body);
        self::assertStringContainsString('name="to" value="/admin/impostazioni"', $wrong->body, 'the destination is kept');
        self::assertSame(200, $client->get('/admin/conferma-password?to=' . rawurlencode($to))->status, 'still not confirmed');
        self::assertSame(1, $this->countRows('rate_limit_hits', "bucket = 'admin_reauth'"));
        self::assertSame(1, $this->countRows('rate_limit_hits', "bucket = 'admin_login_failed'"));
        self::assertSame(422, $this->post($client, '/admin/conferma-password', ['password' => '', 'to' => $to], '/admin/conferma-password')->status);
    }

    public function testARightPasswordConfirmsAndTheConfirmationIsRemembered(): void
    {
        $client = $this->login();
        $to = '/admin/impostazioni';

        $ok = $this->post($client, '/admin/conferma-password', ['password' => self::ADMIN_PASSWORD, 'to' => $to], '/admin/conferma-password');

        self::assertSame(303, $ok->status);
        self::assertStringEndsWith($to, (string) $ok->location());
        $again = $client->get('/admin/conferma-password?to=' . rawurlencode($to));
        self::assertSame(303, $again->status, 'no new question while the confirmation is valid');
        self::assertStringEndsWith($to, (string) $again->location());
    }

    public function testTheConfirmationLastsFiveMinutes(): void
    {
        $client = $this->login();
        $this->post($client, '/admin/conferma-password', ['password' => self::ADMIN_PASSWORD, 'to' => '/admin'], '/admin/conferma-password');

        $this->ageReauth($client, 290);
        self::assertSame(303, $client->get('/admin/conferma-password?to=/admin')->status, 'after 4 minutes 50 seconds');

        $this->ageReauth($client, 310);
        self::assertSame(200, $client->get('/admin/conferma-password?to=/admin')->status, 'after 5 minutes 10 seconds the question comes back');
    }

    public function testAConfirmationIsNotValidInAnotherSession(): void
    {
        $one = $this->login();
        $two = $this->login();

        $this->post($one, '/admin/conferma-password', ['password' => self::ADMIN_PASSWORD, 'to' => '/admin'], '/admin/conferma-password');

        self::assertSame(303, $one->get('/admin/conferma-password?to=/admin')->status);
        self::assertSame(200, $two->get('/admin/conferma-password?to=/admin')->status, 'the other browser still has to confirm');
    }

    public function testChangingThePasswordClearsTheConfirmation(): void
    {
        $client = $this->login();
        $this->post($client, '/admin/conferma-password', ['password' => self::ADMIN_PASSWORD, 'to' => '/admin'], '/admin/conferma-password');
        self::assertSame(303, $client->get('/admin/conferma-password?to=/admin')->status);

        $this->post($client, '/admin/account/password', $this->change(self::ADMIN_PASSWORD, self::NEW_PASSWORD));

        self::assertSame(200, $client->get('/admin/conferma-password?to=/admin')->status, 'the confirmation did not survive the change');
    }

    public function testClosingAllDevicesClearsTheConfirmation(): void
    {
        $client = $this->login();
        $this->post($client, '/admin/conferma-password', ['password' => self::ADMIN_PASSWORD, 'to' => '/admin'], '/admin/conferma-password');

        $this->post($client, '/admin/account/esci-ovunque', ['close_current_password' => self::ADMIN_PASSWORD]);

        self::assertSame(200, $client->get('/admin/conferma-password?to=/admin')->status);
    }

    public function testTheConfirmationHasItsOwnLimitOfAttempts(): void
    {
        $client = $this->login();

        $statuses = [];
        for ($i = 1; $i <= 6; $i++) {
            $statuses[] = $this->post($client, '/admin/conferma-password', ['password' => "sbagliata-$i-xxxxx", 'to' => '/admin'], '/admin/conferma-password')->status;
        }
        self::assertSame([422, 422, 422, 422, 422, 429], $statuses);

        $right = $this->post($client, '/admin/conferma-password', ['password' => self::ADMIN_PASSWORD, 'to' => '/admin'], '/admin/conferma-password');
        self::assertSame(429, $right->status, 'refused while the limit is active, whatever the password');
        self::assertSame(200, $client->get('/admin/conferma-password?to=/admin')->status);
    }
}
