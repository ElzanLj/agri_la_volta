<?php

declare(strict_types=1);

namespace Tests\Http;

use Tests\Support\PublicSiteTestCase;
use Tests\Support\TestServer;

/**
 * Protection of the public form: signed token (no session cookie), Origin check, "too fast" check,
 * honeypot and rate limit. This class runs its own server with the timing check switched on.
 */
final class PublicAntispamTest extends PublicSiteTestCase
{
    private const MIN_SECONDS = 2;

    public static function setUpBeforeClass(): void
    {
        self::$server = TestServer::start(['PUBLIC_FORM_MIN_SECONDS' => (string) self::MIN_SECONDS]);
    }

    /** A token the test server accepts, issued $secondsAgo seconds ago (signed with the test secret). */
    private function token(int $secondsAgo = 100, ?string $secret = null): string
    {
        $time = (string) (time() - $secondsAgo);
        return $time . '.' . hash_hmac('sha256', 'public-request|' . $time, $secret ?? TestServer::APP_SECRET);
    }

    /** @return array<string, string> fields of a valid summary-step POST, apart from the token */
    private function stepThreeFields(): array
    {
        [$in, $out] = $this->stay();
        return $this->searchParams($in, $out) + ['apartment' => 'margherita'] + self::CUSTOMER;
    }

    private function prepare(): void
    {
        [$in, $out] = $this->stay();
        $this->equip('margherita', 4, [$in, $out]);
    }

    public function testSubmittingTheCustomerFormTooFastIsRefusedAndTheDelayThenPasses(): void
    {
        $this->prepare();
        [$in, $out] = $this->stay();
        $client = $this->client();

        $fast = $this->toSummary($client, 'margherita', $in, $out);
        self::assertSame(422, $fast->status);
        self::assertStringContainsString('inviato troppo in fretta', $fast->body);
        self::assertStringContainsString('value="Mario"', $fast->body, 'what was typed is kept');

        sleep(self::MIN_SECONDS + 1);
        $details = $client->get(self::path('request.details') . '?' . http_build_query($this->searchParams($in, $out) + ['apartment' => 'margherita']));
        sleep(self::MIN_SECONDS + 1);
        $slow = $client->post(self::path('request.summary'), $this->hiddenFields($details->body) + self::CUSTOMER);
        self::assertSame(200, $slow->status, 'after the minimum time the same data is accepted');
    }

    public function testTheSummaryAndSendStepsRejectMissingTamperedForeignAndOtherPurposeTokens(): void
    {
        $this->prepare();
        $client = $this->client();
        $fields = $this->stepThreeFields() + ['privacy_accepted' => '1'];
        $before = $this->snapshot();

        $bad = [
            'missing' => [],
            'empty' => ['_form' => ''],
            'garbage' => ['_form' => 'not-a-token'],
            'wrong secret' => ['_form' => $this->token(100, 'another secret')],
            'tampered time' => ['_form' => (time() - 50) . '.' . explode('.', $this->token(100))[1]],
            'admin csrf style' => ['_csrf' => bin2hex(random_bytes(32))],
            'array' => ['_form' => [$this->token()]],
        ];
        foreach (['request.summary', 'request.submit'] as $step) {
            foreach ($bad as $name => $extra) {
                $response = $client->post(self::path($step), $fields + $extra);

                self::assertSame(403, $response->status, "$step / $name");
                self::assertStringContainsString('Richiesta non consentita', $response->body, "$step / $name");
            }
            // The token is never read from the query string.
            $viaQuery = $client->post(self::path($step) . '?_form=' . urlencode($this->token()), $fields);
            self::assertSame(403, $viaQuery->status, "$step / token in the query string");
        }
        $this->assertDatabaseUnchanged($before, 'a refused form must change nothing');
        self::assertSame(0, $this->countRows('rate_limit_hits'), 'refused forms are not counted as sends');
        self::assertSame([], $client->cookies, 'no cookie even on refusal');
    }

    public function testAValidTokenIsAccepted(): void
    {
        $this->prepare();
        $client = $this->client();

        $summary = $client->post(self::path('request.summary'), $this->stepThreeFields() + ['_form' => $this->token()]);
        self::assertSame(200, $summary->status);

        $sent = $client->post(self::path('request.submit'), $this->hiddenFields($summary->body) + ['privacy_accepted' => '1', '_form' => $this->token(100)]);
        self::assertSame(303, $sent->status);
        self::assertSame(1, $this->requestCount());
    }

    public function testAnExpiredTokenAsksToSendTheFormAgain(): void
    {
        $this->prepare();
        $client = $this->client();

        $response = $client->post(self::path('request.summary'), $this->stepThreeFields() + ['_form' => $this->token(7300)]);

        self::assertSame(422, $response->status);
        self::assertStringContainsString('Il modulo è scaduto', $response->body);
        self::assertStringContainsString('name="_form"', $response->body, 'a fresh token is offered');
        self::assertSame(0, $this->requestCount());
    }

    public function testPostsFromAnotherSiteAreRefusedEvenWithAValidToken(): void
    {
        $this->prepare();
        $client = $this->client();
        $fields = $this->stepThreeFields() + ['privacy_accepted' => '1', '_form' => $this->token()];

        foreach ([['Origin' => 'http://evil.example'], ['Origin' => 'null'], ['Origin' => 'http://127.0.0.1:1'], ['Referer' => 'https://evil.example/form']] as $headers) {
            foreach (['request.summary', 'request.submit', 'request.details'] as $step) {
                $response = $client->post(self::path($step), $fields, $headers);
                self::assertSame(403, $response->status, $step . ' ' . json_encode($headers));
            }
        }
        self::assertSame(0, $this->requestCount());

        $own = $client->post(self::path('request.summary'), $fields, ['Origin' => self::$server->baseUrl()]);
        self::assertSame(200, $own->status, 'the site itself is accepted');
    }

    public function testTheHoneypotDropsTheRequestSilently(): void
    {
        $this->prepare();
        $client = $this->client();

        $atSummary = $client->post(self::path('request.summary'), $this->stepThreeFields() + ['_form' => $this->token(), 'contact_website' => 'http://spam.example']);
        self::assertSame(303, $atSummary->status, 'the bot is told nothing went wrong');
        self::assertStringEndsWith('/richiedi-disponibilita/ricevuta', (string) $atSummary->location(), 'and receives no reference');

        $atSubmit = $client->post(self::path('request.submit'), $this->stepThreeFields() + ['privacy_accepted' => '1', '_form' => $this->token(), 'contact_website' => 'x']);
        self::assertSame(303, $atSubmit->status);
        self::assertStringEndsWith('/richiedi-disponibilita/ricevuta', (string) $atSubmit->location());

        self::assertSame(0, $this->requestCount());
        self::assertSame(0, $this->countRows('email_outbox'));
    }

    public function testTheHoneypotFieldIsHiddenFromPeopleAndAssistiveTechnology(): void
    {
        $this->prepare();
        [$in, $out] = $this->stay();
        $details = $this->client()->get(self::path('request.details') . '?' . http_build_query($this->searchParams($in, $out) + ['apartment' => 'margherita']));

        self::assertStringContainsString('class="hp" aria-hidden="true"', $details->body);
        self::assertStringContainsString('name="contact_website"', $details->body);
        self::assertStringContainsString('tabindex="-1"', $details->body);
        self::assertStringContainsString('autocomplete="off"', $details->body);
    }

    public function testTooManySendsFromOneClientAreRefusedWithoutStoringAnyIpAddress(): void
    {
        $this->prepare();
        $client = $this->client();
        $fields = $this->stepThreeFields() + ['_form' => $this->token()]; // no privacy consent: every send fails validation but counts

        for ($i = 1; $i <= 6; $i++) {
            self::assertSame(422, $client->post(self::path('request.submit'), $fields)->status, "attempt $i");
        }
        $seventh = $client->post(self::path('request.submit'), $fields + ['privacy_accepted' => '1']);

        self::assertSame(429, $seventh->status);
        self::assertStringContainsString('troppe richieste', $seventh->body);
        self::assertSame(0, $this->requestCount(), 'a rate-limited send stores nothing even when valid');

        $stored = $this->db->query("SELECT key_hash FROM rate_limit_hits WHERE bucket = 'public_request'")->fetchAll(\PDO::FETCH_COLUMN);
        self::assertCount(6, $stored);
        foreach ($stored as $hash) {
            self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $hash);
            self::assertStringNotContainsString('127.0.0.1', $hash);
        }
    }

    public function testTheRateLimitDoesNotAffectBrowsingOrTheAdminLogin(): void
    {
        $this->prepare();
        $client = $this->client();
        $fields = $this->stepThreeFields() + ['_form' => $this->token()];
        for ($i = 0; $i < 7; $i++) {
            $client->post(self::path('request.submit'), $fields);
        }

        self::assertSame(200, $client->get(self::path('home'))->status);
        self::assertSame(200, $client->get(self::path('request'))->status);
        $this->loggedInClient(); // the admin can still sign in
    }
}
