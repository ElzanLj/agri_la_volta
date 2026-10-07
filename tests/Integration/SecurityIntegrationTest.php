<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Security\AppSecret;
use App\Security\RateLimiter;
use Tests\Support\InMemoryTransport;
use Tests\Support\MailTestHelpers;
use Tests\Support\DatabaseTestCase;

/** Keyed rate-limit hashes and header injection through customer-supplied text. */
final class SecurityIntegrationTest extends DatabaseTestCase
{
    use MailTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setMailEnv();
    }

    protected function tearDown(): void
    {
        $this->restoreMailEnv();
    }

    public function testRateLimitStoresAKeyedHashOfTheClientNotAReversibleOne(): void
    {
        $limiter = new RateLimiter($this->db);
        $limiter->hit('bucket', '203.0.113.7');

        $stored = (string) $this->scalar("SELECT key_hash FROM rate_limit_hits WHERE bucket = 'bucket'");

        self::assertSame(hash_hmac('sha256', '203.0.113.7', AppSecret::value()), $stored);
        self::assertNotSame(hash('sha256', '203.0.113.7'), $stored, 'an unkeyed hash of an IPv4 address could be reversed by trying every address');
        self::assertStringNotContainsString('203.0.113.7', $stored);
    }

    public function testTheKeyedHashStillCountsPerClient(): void
    {
        $limiter = new RateLimiter($this->db);
        // The attempt is recorded first and then counted: the third is the last allowed one.
        self::assertTrue($limiter->attempt('bucket', '198.51.100.1', 3, 60));
        self::assertTrue($limiter->attempt('bucket', '198.51.100.1', 3, 60));
        self::assertTrue($limiter->attempt('bucket', '198.51.100.1', 3, 60));
        self::assertFalse($limiter->attempt('bucket', '198.51.100.1', 3, 60), 'the fourth attempt is over the limit');
        self::assertTrue($limiter->attempt('bucket', '198.51.100.2', 3, 60), 'another client is not affected');
        $limiter->clear('bucket', '198.51.100.1');
        self::assertTrue($limiter->attempt('bucket', '198.51.100.1', 3, 60), 'clearing starts a new window');
    }

    public function testCountRecentCountsEveryClientOfABucketOnly(): void
    {
        $limiter = new RateLimiter($this->db);
        $limiter->hit('failures', '198.51.100.1');
        $limiter->hit('failures', '198.51.100.2');
        $limiter->hit('other', '198.51.100.1');

        self::assertSame(2, $limiter->countRecent('failures', 86400));
        self::assertSame(1, $limiter->countRecent('other', 86400));
        $this->db->exec("UPDATE rate_limit_hits SET created_at = UTC_TIMESTAMP() - INTERVAL 2 DAY WHERE bucket = 'failures'");
        self::assertSame(0, $limiter->countRecent('failures', 86400 * 3), 'the window never goes beyond the 24 hours that are kept');
    }

    public function testLineBreaksInCustomerTextNeverBecomeMailHeaders(): void
    {
        $transport = new InMemoryTransport();
        $service = $this->bookingServiceWithMail($transport);
        $evil = "Mario\r\nBcc: spia@example.test\r\nSubject: hacked";

        $request = $service->createRequest($this->requestInput('2027-06-10', '2027-06-14', [
            'first_name' => $evil, 'last_name' => "Rossi\nX-Injected: 1", 'notes' => "ciao\r\nBcc: altro@example.test",
        ]));
        $service->confirmRequest($request['id']);

        self::assertNotEmpty($transport->sent);
        foreach ($transport->sent as $message) {
            foreach ([$message->to, $message->subject, (string) $message->replyTo, (string) $message->replyToName] as $header) {
                self::assertDoesNotMatchRegularExpression('/[\r\n]/', $header, 'a header field must be one line');
            }
            self::assertSame($message->to, trim($message->to));
            self::assertStringNotContainsString('Bcc:', $message->to . $message->subject, 'nothing foreign reaches the recipient or subject');
        }
    }

    public function testAnEmailAddressWithLineBreaksIsRefusedAsInvalid(): void
    {
        foreach (["a@example.test\r\nBcc: x@example.test", "a@example.test\nBcc: x@example.test", "a@example.test, b@example.test", 'a@example.test <b@example.test>'] as $email) {
            try {
                $this->service->createRequest($this->requestInput('2027-06-10', '2027-06-14', ['email' => $email]));
                self::fail('must be refused: ' . json_encode($email));
            } catch (\App\Domain\ValidationException $e) {
                self::assertArrayHasKey('email', $e->errors(), json_encode($email));
            }
        }
        self::assertSame(0, (int) $this->scalar('SELECT COUNT(*) FROM booking_requests'));
    }
}
