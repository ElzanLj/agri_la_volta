<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\App;
use App\Database\Connection;
use App\Mail\MailMessage;
use App\Mail\MailTransport;
use App\Mail\MailTransportException;
use App\Mail\SmtpTransport;
use App\Service\BookingService;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\DatabaseTestCase;
use Tests\Support\FakeSmtpServer;
use Tests\Support\InMemoryTransport;
use Tests\Support\MailTestHelpers;

/**
 * THE guarantee of this phase: the database change is committed BEFORE any e-mail is attempted,
 * and nothing that goes wrong while sending (network, SMTP, a bug, a PHP Error) can ever roll
 * back, undo or block a confirmed booking or a request state.
 */
final class MailResilienceTest extends DatabaseTestCase
{
    use MailTestHelpers;

    private const PASSWORD = 'Sup3r-Secret-Passw0rd!';
    private const GUEST_EMAIL = 'mario.rossi@example.com';

    private ?FakeSmtpServer $server = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setMailEnv();
    }

    protected function tearDown(): void
    {
        $this->restoreMailEnv();
        $this->server?->stop();
        $this->server = null;
        $this->db->exec('DROP TRIGGER IF EXISTS test_fail_outbox');
    }

    /** A second, independent connection to the test database: it only sees COMMITTED data. */
    private function otherConnection(): \PDO
    {
        return Connection::connect(App::current()->config);
    }

    /** Every observable consequence of a confirmed request must still be there. */
    private function assertConfirmedAndIntact(int $requestId, int $bookingId): void
    {
        self::assertSame('confirmed', $this->scalar('SELECT status FROM booking_requests WHERE id = ?', [$requestId]));
        self::assertSame(['confirmed', 'website'], array_values($this->row('SELECT status, origin FROM bookings WHERE id = ?', [$bookingId])));
        self::assertSame(1, (int) $this->scalar("SELECT COUNT(*) FROM audit_log WHERE entity_type = 'booking_request' AND action = 'status_changed' AND entity_id = ?", [$requestId]));
        self::assertSame(1, (int) $this->scalar("SELECT COUNT(*) FROM audit_log WHERE entity_type = 'booking' AND action = 'created' AND entity_id = ?", [$bookingId]));
        self::assertSame(0, $this->overlapViolations());
        self::assertFalse($this->db->inTransaction(), 'no transaction may be left open');
    }

    // === 1. Commit comes first =======================================================

    public function testTheRequestIsAlreadyCommittedWhenTheManagerMessageIsSent(): void
    {
        $seen = [];
        $transport = new InMemoryTransport(function (MailMessage $m) use (&$seen): void {
            $other = $this->otherConnection(); // a different connection sees only committed rows
            $seen = [
                'requests' => (int) $other->query("SELECT COUNT(*) FROM booking_requests WHERE status = 'pending'")->fetchColumn(),
                'outbox_status' => $other->query("SELECT status FROM email_outbox LIMIT 1")->fetchColumn(),
                'in_transaction' => $this->db->inTransaction(),
            ];
        });

        $this->bookingServiceWithMail($transport)->createRequest($this->requestInput('2027-06-10', '2027-06-15'));

        self::assertSame(['requests' => 1, 'outbox_status' => 'sending', 'in_transaction' => false], $seen, 'the request was committed (and the queue row claimed) before the send started');
    }

    public function testTheConfirmationIsAlreadyCommittedWhenTheGuestMessageIsSent(): void
    {
        $service = $this->service;
        $request = $service->createRequest($this->requestInput('2027-06-10', '2027-06-15'));
        $seen = null;
        $transport = new InMemoryTransport(function (MailMessage $m) use (&$seen, $request): void {
            if ($m->to !== self::GUEST_EMAIL) {
                return;
            }
            $other = $this->otherConnection();
            $seen = [
                'request' => $other->query('SELECT status FROM booking_requests WHERE id = ' . $request['id'])->fetchColumn(),
                'booking' => $other->query("SELECT status FROM bookings WHERE booking_request_id = {$request['id']}")->fetchColumn(),
                'audit' => (int) $other->query("SELECT COUNT(*) FROM audit_log WHERE entity_type = 'booking' AND action = 'created'")->fetchColumn(),
                'in_transaction' => $this->db->inTransaction(),
            ];
        });

        $this->bookingServiceWithMail($transport)->confirmRequest($request['id']);

        self::assertSame(['request' => 'confirmed', 'booking' => 'confirmed', 'audit' => 1, 'in_transaction' => false], $seen);
    }

    public function testTheRejectionIsAlreadyCommittedWhenTheGuestMessageIsSent(): void
    {
        $request = $this->service->createRequest($this->requestInput('2027-06-10', '2027-06-15'));
        $seen = null;
        $transport = new InMemoryTransport(function (MailMessage $m) use (&$seen, $request): void {
            if ($m->to === self::GUEST_EMAIL) {
                $seen = $this->otherConnection()->query('SELECT status FROM booking_requests WHERE id = ' . $request['id'])->fetchColumn();
            }
        });

        $this->bookingServiceWithMail($transport)->rejectRequest($request['id']);

        self::assertSame('rejected', $seen);
    }

    // === 2. Whatever the failure, the booking survives ===============================

    /** @return array<string, array{callable(): \Throwable}> */
    public static function failures(): array
    {
        return [
            'connection refused' => [static fn () => new MailTransportException(MailTransportException::CONNECTION, 'Connessione al server SMTP non riuscita.', true)],
            'timeout' => [static fn () => new MailTransportException(MailTransportException::TIMEOUT, 'Il server SMTP non ha risposto entro 10 secondi.', true)],
            'temporary 4xx' => [static fn () => new MailTransportException(MailTransportException::TEMPORARY, 'Riprova più tardi (codice 451).', true)],
            'recipient rejected 5xx' => [static fn () => new MailTransportException(MailTransportException::RECIPIENT_REJECTED, 'Destinatario rifiutato (codice 550).', false)],
            'authentication failed' => [static fn () => new MailTransportException(MailTransportException::AUTH_FAILED, 'Autenticazione rifiutata (codice 535).', false)],
            'not configured' => [static fn () => new MailTransportException(MailTransportException::NOT_CONFIGURED, 'SMTP_HOST non configurato.', false)],
            'runtime exception' => [static fn () => new RuntimeException('boom ' . self::GUEST_EMAIL . ' ' . self::PASSWORD)],
            'logic exception' => [static fn () => new \LogicException('library bug')],
            'type error' => [static fn () => new \TypeError('wrong type')],
            'arithmetic error' => [static fn () => new \DivisionByZeroError('division by zero')],
            'php warning turned into an exception' => [static fn () => new \ErrorException('fwrite(): Send of 6 bytes failed with errno=32 Broken pipe')],
            'out of memory style error' => [static fn () => new \Error('Allowed memory size exhausted')],
            'database error inside the transport' => [static fn () => new \PDOException('SQLSTATE[HY000]: General error')],
        ];
    }

    /** @param callable(): \Throwable $makeFailure */
    #[DataProvider('failures')]
    public function testNoFailureWhileSendingEverUndoesAnythingThatWasCommitted(callable $makeFailure): void
    {
        $transport = new InMemoryTransport(static function (MailMessage $m) use ($makeFailure): void {
            throw $makeFailure();
        });
        $service = $this->bookingServiceWithMail($transport, [self::PASSWORD]);

        // 1. The request is saved even though the manager message fails.
        $request = $service->createRequest($this->requestInput('2027-06-10', '2027-06-15'));
        self::assertSame('pending', $this->scalar('SELECT status FROM booking_requests WHERE id = ?', [$request['id']]));

        // 2. The confirmation stays confirmed even though the guest message fails.
        $confirmed = $service->confirmRequest($request['id']);
        $this->assertConfirmedAndIntact($request['id'], $confirmed['booking_id']);

        // 3. A rejection stays rejected.
        $other = $service->createRequest($this->requestInput('2027-07-10', '2027-07-15'));
        $service->rejectRequest($other['id']);
        self::assertSame('rejected', $this->scalar('SELECT status FROM booking_requests WHERE id = ?', [$other['id']]));

        // Every message was attempted and recorded as failed, with a clean error text.
        self::assertGreaterThanOrEqual(4, $transport->attempts);
        $rows = $this->outboxRows();
        self::assertCount(4, $rows);
        foreach ($rows as $row) {
            self::assertSame('failed', $row['status']);
            self::assertSame(1, (int) $row['attempts']);
            self::assertNotNull($row['error_code']);
            foreach ([self::PASSWORD, self::GUEST_EMAIL, '@'] as $forbidden) {
                self::assertStringNotContainsString($forbidden, (string) $row['error_message'], 'the stored error must be sanitised');
            }
        }
        $log = $this->loggedText();
        self::assertStringNotContainsString(self::PASSWORD, $log);
        self::assertStringNotContainsString(self::GUEST_EMAIL, $log);
        self::assertStringNotContainsString('Mario', $log, 'no personal data in the logs');
    }

    public function testTheCallerNeverSeesAnExceptionWhateverTheTransportThrows(): void
    {
        $transport = new InMemoryTransport(static function (): void {
            throw new \Error('fatal-like');
        });
        $service = $this->bookingServiceWithMail($transport);

        $request = $service->createRequest($this->requestInput('2027-06-10', '2027-06-15'));
        $booking = $service->confirmRequest($request['id']);

        self::assertIsInt($request['id']);
        self::assertIsInt($booking['booking_id']);
        self::assertSame('confirmed', $this->scalar('SELECT status FROM bookings WHERE id = ?', [$booking['booking_id']]));
    }

    public function testASlowFailingTransportStillLeavesTheBookingCommitted(): void
    {
        $transport = new InMemoryTransport(static function (): void {
            usleep(300_000); // a server that takes a while and then gives up
            throw new MailTransportException(MailTransportException::TIMEOUT, 'Il server SMTP non ha risposto entro 10 secondi.', true);
        });
        $service = $this->bookingServiceWithMail($transport);
        $request = $service->createRequest($this->requestInput('2027-06-10', '2027-06-15'));

        $booking = $service->confirmRequest($request['id']);

        $this->assertConfirmedAndIntact($request['id'], $booking['booking_id']);
        self::assertSame(['timeout'], array_unique(array_column($this->outboxRows(), 'error_code')));
    }

    public function testFailedMessagesAreSentAfterTheProblemIsFixed(): void
    {
        $broken = true;
        $transport = new InMemoryTransport(static function () use (&$broken): void {
            if ($broken) {
                throw new MailTransportException(MailTransportException::CONNECTION, 'Connessione al server SMTP non riuscita.', true);
            }
        });
        $service = $this->bookingServiceWithMail($transport);
        $request = $service->createRequest($this->requestInput('2027-06-10', '2027-06-15'));
        $service->confirmRequest($request['id']);
        self::assertSame(['failed'], array_unique(array_column($this->outboxRows(), 'status')));

        $broken = false;
        foreach ($this->outboxRows() as $row) {
            self::assertSame('sent', $this->notifications($transport)->retry((int) $row['id']));
        }

        self::assertCount(2, $transport->sent, 'each message was delivered exactly once after the repair');
        self::assertSame(['sent'], array_unique(array_column($this->outboxRows(), 'status')));
        self::assertSame('confirmed', $this->scalar('SELECT status FROM booking_requests WHERE id = ?', [$request['id']]));
    }

    // === 3. Even recording the outcome may fail; the state still survives ============

    public function testIfTheOutcomeCannotBeRecordedNothingBreaksAndTheMessageIsRetriedLater(): void
    {
        $transport = new InMemoryTransport();
        $service = $this->bookingServiceWithMail($transport);
        $request = $service->createRequest($this->requestInput('2027-06-10', '2027-06-15'));
        $this->db->exec('UPDATE email_outbox SET status = \'pending\', locked_at = NULL, attempts = 0, sent_at = NULL');
        $transport->sent = [];
        // From now on updating a row to "sent" or "failed" is impossible (the database misbehaves).
        $this->db->exec(
            "CREATE TRIGGER test_fail_outbox BEFORE UPDATE ON email_outbox FOR EACH ROW
             IF NEW.status IN ('sent', 'failed') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'cannot record'; END IF"
        );

        $confirmed = $service->confirmRequest($request['id']); // must not throw

        $this->assertConfirmedAndIntact($request['id'], $confirmed['booking_id']);
        self::assertStringContainsString('could not record its outcome', $this->loggedText());
        foreach ($this->outboxRows() as $row) {
            self::assertSame('sending', $row['status'], 'left claimed: it becomes due again when the claim expires');
        }

        // The database recovers and the claims expire: the next run delivers them.
        $this->db->exec('DROP TRIGGER test_fail_outbox');
        $this->db->exec("UPDATE email_outbox SET locked_at = UTC_TIMESTAMP() - INTERVAL 10 MINUTE WHERE status = 'sending'");
        $outcomes = $this->notifications($transport)->dispatchDue(10);

        self::assertNotEmpty($outcomes);
        self::assertSame(['sent'], array_values(array_unique($outcomes)));
    }

    public function testABrokenOutboxNeverBlocksARequestOrADecision(): void
    {
        $transport = new InMemoryTransport();
        $service = $this->bookingServiceWithMail($transport);
        // Inserting into the queue now fails (a bug, a full disk, a wrong constraint...).
        $this->db->exec(
            "CREATE TRIGGER test_fail_outbox BEFORE INSERT ON email_outbox FOR EACH ROW
             SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'outbox broken'"
        );

        $request = $service->createRequest($this->requestInput('2027-06-10', '2027-06-15')); // must not throw
        $booking = $service->confirmRequest($request['id']);
        $other = $service->createRequest($this->requestInput('2027-07-10', '2027-07-15'));
        $service->rejectRequest($other['id']);

        $this->assertConfirmedAndIntact($request['id'], $booking['booking_id']);
        self::assertSame('rejected', $this->scalar('SELECT status FROM booking_requests WHERE id = ?', [$other['id']]));
        self::assertSame([], $this->outboxRows(), 'no message could be queued, and that is all that was lost');
        self::assertSame(0, $transport->attempts);
        self::assertStringContainsString('could not be queued', $this->loggedText());
    }

    public function testAMissingOutboxTableNeverBlocksEither(): void
    {
        // E.g. the code was deployed but migration 0004 was not applied on the server yet.
        $service = $this->bookingServiceWithMail(new InMemoryTransport());
        $this->db->exec('RENAME TABLE email_outbox TO email_outbox_hidden');
        try {
            $request = $service->createRequest($this->requestInput('2027-06-10', '2027-06-15'));
            $booking = $service->confirmRequest($request['id']);
            $other = $service->createRequest($this->requestInput('2027-07-10', '2027-07-15'));
            $service->rejectRequest($other['id']);
        } finally {
            $this->db->exec('RENAME TABLE email_outbox_hidden TO email_outbox');
        }

        $this->assertConfirmedAndIntact($request['id'], $booking['booking_id']);
        self::assertSame('rejected', $this->scalar('SELECT status FROM booking_requests WHERE id = ?', [$other['id']]));
        self::assertStringContainsString('could not be queued', $this->loggedText());
    }

    public function testQueueingProblemsAreReportedEvenWithoutALogger(): void
    {
        $this->db->exec(
            "CREATE TRIGGER test_fail_outbox BEFORE INSERT ON email_outbox FOR EACH ROW
             SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'outbox broken'"
        );

        $request = $this->service->createRequest($this->requestInput('2027-06-10', '2027-06-15')); // plain service, no logger

        self::assertSame('pending', $this->scalar('SELECT status FROM booking_requests WHERE id = ?', [$request['id']]));
    }

    public function testAFailingAfterCommitHookIsContainedAndLogged(): void
    {
        $service = new BookingService($this->db, null, static fn (): string => '2027-01-10');
        $service->afterCommit(static function (array $ids): void {
            throw new \Error('the hook itself is broken');
        }, $this->testLogger());

        $request = $service->createRequest($this->requestInput('2027-06-10', '2027-06-15'));
        $booking = $service->confirmRequest($request['id']);
        $service->rejectRequest($service->createRequest($this->requestInput('2027-08-10', '2027-08-15'))['id']);

        $this->assertConfirmedAndIntact($request['id'], $booking['booking_id']);
        self::assertStringContainsString('After-commit hook failed', $this->loggedText());
        self::assertSame(['pending'], array_unique(array_column($this->outboxRows(), 'status')), 'the messages stay queued for a later retry');
    }

    public function testWithNoHookRegisteredNothingIsSentAndNothingBreaks(): void
    {
        $request = $this->service->createRequest($this->requestInput('2027-06-10', '2027-06-15'));
        $booking = $this->service->confirmRequest($request['id']);

        $this->assertConfirmedAndIntact($request['id'], $booking['booking_id']);
        self::assertSame(['pending'], array_unique(array_column($this->outboxRows(), 'status')));
    }

    // === 4. Real SMTP conversation, every kind of network failure ====================

    private function realSmtp(int $port, int $timeout = 2): SmtpTransport
    {
        return new SmtpTransport('127.0.0.1', $port, 'none', 'smtp-user', self::PASSWORD, $timeout, 'info@example.test', 'Agriturismo La Volta');
    }

    /** @return array<string, array{string, string, bool}> */
    public static function smtpScenarios(): array
    {
        return [
            'recipient mailbox unknown (550)' => ['rcpt_550', 'recipient_rejected', false],
            'recipient temporarily unavailable (450)' => ['rcpt_450', 'temporary_rejection', true],
            'authentication refused (535)' => ['auth_535', 'auth_failed', false],
            'message refused after DATA (554)' => ['data_554', 'rejected', false],
            'message deferred after DATA (451)' => ['data_451', 'temporary_rejection', true],
            'connection lost in the middle of the message' => ['drop_mid_data', 'connection', true],
            'connection lost before the verdict' => ['drop_after_data', 'connection', true],
            'server never answers' => ['hang', 'timeout', true],
            'service unavailable at greeting (421)' => ['greeting_421', 'temporary_rejection', true],
        ];
    }

    #[DataProvider('smtpScenarios')]
    public function testARealSmtpFailureNeverTouchesTheBookingAndAFixedServerDelivers(string $scenario, string $code, bool $retryable): void
    {
        $this->server = FakeSmtpServer::start($scenario);
        $transport = $this->realSmtp($this->server->port);
        $service = $this->bookingServiceWithMail($transport, [self::PASSWORD, 'smtp-user']);

        $started = microtime(true);
        $request = $service->createRequest($this->requestInput('2027-06-10', '2027-06-15'));
        $booking = $service->confirmRequest($request['id']);
        $elapsed = microtime(true) - $started;

        // The state is committed and complete.
        $this->assertConfirmedAndIntact($request['id'], $booking['booking_id']);

        // Both messages are recorded as failed, correctly classified, with a clean message.
        $rows = $this->outboxRows();
        self::assertCount(2, $rows);
        foreach ($rows as $row) {
            self::assertSame(['failed', $code, (int) $retryable], [$row['status'], $row['error_code'], (int) $row['retryable']], $scenario);
            foreach ([self::PASSWORD, 'smtp-user', self::GUEST_EMAIL, 'gestore@example.test', '@'] as $forbidden) {
                self::assertStringNotContainsString($forbidden, (string) $row['error_message'], $scenario);
            }
        }
        self::assertSame(2, count(array_filter($rows, static fn (array $r): bool => $retryable ? $r['next_attempt_at'] !== null : $r['next_attempt_at'] === null)), 'retryable errors are scheduled, permanent ones are not');
        if ($scenario === 'hang') {
            self::assertLessThan(15.0, $elapsed, 'two timeouts of 2 seconds must not stall the request for long');
        }
        self::assertStringNotContainsString(self::PASSWORD, $this->loggedText());
        self::assertStringNotContainsString(self::GUEST_EMAIL, $this->loggedText());

        // The server is repaired; a manual retry delivers each message exactly once.
        $this->server->setScenario('ok');
        $notifications = $this->notifications($transport);
        foreach ($rows as $row) {
            self::assertSame('sent', $notifications->retry((int) $row['id']), $scenario);
        }
        $recipients = array_merge(...array_column($this->server->messages(), 'rcpt_to'));
        self::assertContains('gestore@example.test', $recipients);
        self::assertContains(self::GUEST_EMAIL, $recipients);
        self::assertSame(['sent'], array_unique(array_column($this->outboxRows(), 'status')));
        self::assertSame('confirmed', $this->scalar('SELECT status FROM booking_requests WHERE id = ?', [$request['id']]));
    }

    public function testADeadPortAndAnUnconfiguredServerAreContainedToo(): void
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        $name = (string) stream_socket_get_name($socket, false);
        fclose($socket);
        $closedPort = (int) substr($name, (int) strrpos($name, ':') + 1);

        foreach ([$this->realSmtp($closedPort, 2), new SmtpTransport('', 587, 'tls', '', '', 5, 'info@example.test', 'La Volta')] as $transport) {
            $this->db->exec('DELETE FROM email_outbox');
            $this->db->exec('DELETE FROM bookings');
            $this->db->exec('DELETE FROM booking_requests');
            $this->db->exec('DELETE FROM audit_log');
            $service = $this->bookingServiceWithMail($transport);

            $request = $service->createRequest($this->requestInput('2027-06-10', '2027-06-15'));
            $booking = $service->confirmRequest($request['id']);

            $this->assertConfirmedAndIntact($request['id'], $booking['booking_id']);
            self::assertSame(['failed'], array_unique(array_column($this->outboxRows(), 'status')));
        }
    }

    public function testSuccessfulDeliveryThroughTheRealTransport(): void
    {
        $this->server = FakeSmtpServer::start('ok');
        $service = $this->bookingServiceWithMail($this->realSmtp($this->server->port));

        $request = $service->createRequest($this->requestInput('2027-06-10', '2027-06-15'));
        $service->confirmRequest($request['id']);

        $messages = $this->server->messages();
        self::assertCount(2, $messages);
        self::assertSame(['gestore@example.test'], $messages[0]['rcpt_to']);
        self::assertSame([self::GUEST_EMAIL], $messages[1]['rcpt_to']);
        self::assertSame(['sent', 'sent'], array_column($this->outboxRows(), 'status'));
        self::assertStringNotContainsString(self::PASSWORD, implode("\n", $this->server->commands()));
    }

    public function testTheSendIsBoundedByTheConfiguredTimeout(): void
    {
        $this->server = FakeSmtpServer::start('hang');
        $service = $this->bookingServiceWithMail($this->realSmtp($this->server->port, 1));

        $started = microtime(true);
        $service->createRequest($this->requestInput('2027-06-10', '2027-06-15'));
        $elapsed = microtime(true) - $started;

        self::assertGreaterThanOrEqual(0.8, $elapsed);
        self::assertLessThan(4.0, $elapsed, 'a hanging server costs at most about the timeout');
        self::assertSame('timeout', $this->outboxRows()[0]['error_code']);
    }

    public function testAnUnknownTransportImplementationIsContainedLikeAnyOther(): void
    {
        $transport = new class implements MailTransport {
            public function send(MailMessage $message): void
            {
                throw new \RuntimeException('anything');
            }
        };
        $service = $this->bookingServiceWithMail($transport);

        $request = $service->createRequest($this->requestInput('2027-06-10', '2027-06-15'));
        $booking = $service->confirmRequest($request['id']);

        $this->assertConfirmedAndIntact($request['id'], $booking['booking_id']);
        self::assertSame('unknown', $this->outboxRows()[0]['error_code']);
    }
}
