<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Mail\MailMessage;
use App\Mail\MailTransportException;
use App\Mail\SmtpTransport;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\FakeSmtpServer;

/**
 * The REAL SmtpTransport (PHPMailer) talking SMTP to a fake server that can fail in every
 * way a network or a provider can. Limit: the fake server has no TLS, so STARTTLS/SMTPS with a
 * real certificate are not exercised (see docs/TEST_REPORT.md).
 */
final class SmtpTransportTest extends TestCase
{
    private const PASSWORD = 'Sup3r-Secret-Passw0rd!';

    private ?FakeSmtpServer $server = null;

    protected function tearDown(): void
    {
        $this->server?->stop();
        $this->server = null;
    }

    private function start(string $scenario): FakeSmtpServer
    {
        return $this->server = FakeSmtpServer::start($scenario);
    }

    private function transport(int $port, int $timeout = 5, string $user = 'smtp-user', string $encryption = 'none', string $host = '127.0.0.1'): SmtpTransport
    {
        return new SmtpTransport($host, $port, $encryption, $user, self::PASSWORD, $timeout, 'info@agriturismolavolta.com', 'Agriturismo La Volta');
    }

    private function message(string $to = 'cliente@example.com'): MailMessage
    {
        return new MailMessage($to, 'Prenotazione confermata – àèìòù', "Gentile Mario,\nla sua richiesta è confermata.\n.\nUna riga che inizia con un punto.\n", 'mario@example.com', 'Mario Rossi');
    }

    private function expectFailure(SmtpTransport $transport, ?MailMessage $message = null): MailTransportException
    {
        try {
            $transport->send($message ?? $this->message());
        } catch (MailTransportException $e) {
            return $e;
        }
        self::fail('Expected a MailTransportException');
    }

    /** Decodes the quoted-printable body of a captured message. */
    private function bodyOf(string $data): string
    {
        [, $body] = explode("\r\n\r\n", $data, 2) + [1 => ''];
        return quoted_printable_decode($body);
    }

    private function headerOf(string $data, string $name): ?string
    {
        [$headers] = explode("\r\n\r\n", $data, 2);
        $headers = (string) preg_replace("/\r\n[ \t]+/", ' ', $headers); // unfold
        return preg_match('/^' . preg_quote($name, '/') . ': (.*)$/mi', $headers, $m) === 1 ? iconv_mime_decode(trim($m[1]), 0, 'UTF-8') : null;
    }

    // === Success ===============================================================

    public function testDeliversTheMessageWithTheRightEnvelopeHeadersAndBody(): void
    {
        $server = $this->start('ok');

        $this->transport($server->port)->send($this->message());

        $messages = $server->messages();
        self::assertCount(1, $messages);
        self::assertSame('info@agriturismolavolta.com', $messages[0]['mail_from']);
        self::assertSame(['cliente@example.com'], $messages[0]['rcpt_to']);
        self::assertSame('smtp-user', $messages[0]['auth_user']);
        self::assertSame('Prenotazione confermata – àèìòù', $this->headerOf($messages[0]['data'], 'Subject'));
        self::assertStringContainsString('Agriturismo La Volta', (string) $this->headerOf($messages[0]['data'], 'From'));
        self::assertStringContainsString('info@agriturismolavolta.com', (string) $this->headerOf($messages[0]['data'], 'From'));
        self::assertStringContainsString('mario@example.com', (string) $this->headerOf($messages[0]['data'], 'Reply-To'));
        self::assertStringContainsString('cliente@example.com', (string) $this->headerOf($messages[0]['data'], 'To'));
        self::assertStringContainsString('text/plain', (string) $this->headerOf($messages[0]['data'], 'Content-Type'));
        self::assertStringContainsString('utf-8', strtolower((string) $this->headerOf($messages[0]['data'], 'Content-Type')));
        self::assertNull($this->headerOf($messages[0]['data'], 'X-Mailer'), 'no software banner');
        self::assertSame("Gentile Mario,\nla sua richiesta è confermata.\n.\nUna riga che inizia con un punto.", str_replace("\r\n", "\n", trim($this->bodyOf($messages[0]['data']))), 'accents and a line made of a single dot survive');
    }

    public function testFollowsTheSmtpConversation(): void
    {
        $server = $this->start('ok');

        $this->transport($server->port)->send($this->message());

        $verbs = array_map(static fn (string $l): string => strtoupper(strtok($l, ' ')), array_filter($server->commands(), static fn (string $l): bool => !str_starts_with($l, '---')));
        self::assertSame(['EHLO', 'AUTH', 'MAIL', 'RCPT', 'DATA', 'QUIT'], array_values($verbs));
    }

    public function testNoAuthenticationIsAttemptedWithoutCredentials(): void
    {
        $server = $this->start('ok');

        $this->transport($server->port, user: '')->send($this->message());

        self::assertCount(1, $server->messages());
        self::assertNotContains('AUTH', $server->commands());
        self::assertNull($server->messages()[0]['auth_user']);
    }

    public function testThePasswordNeverAppearsInTheServerSideCommandLog(): void
    {
        $server = $this->start('ok');
        $this->transport($server->port)->send($this->message());

        self::assertStringNotContainsString(self::PASSWORD, implode("\n", $server->commands()));
    }

    public function testASlowButAnsweringServerStillWorks(): void
    {
        $server = $this->start('slow');

        $this->transport($server->port, timeout: 5)->send($this->message());

        self::assertCount(1, $server->messages());
    }

    public function testLineBreaksInSubjectOrNamesCannotInjectHeaders(): void
    {
        $server = $this->start('ok');
        $evil = new MailMessage('cliente@example.com', "Ciao\r\nBcc: spy@example.com\r\nX-Evil: 1", "testo\n", 'mario@example.com', "Mario\r\nBcc: spy@example.com");

        $this->transport($server->port)->send($evil);

        $messages = $server->messages();
        self::assertCount(1, $messages);
        self::assertSame(['cliente@example.com'], $messages[0]['rcpt_to'], 'no extra recipient');
        self::assertDoesNotMatchRegularExpression('/^Bcc:/mi', $messages[0]['data']);
        self::assertDoesNotMatchRegularExpression('/^X-Evil:/mi', $messages[0]['data']);
    }

    public function testLineBreaksInTheRecipientMakeItInvalidInsteadOfAddingARecipient(): void
    {
        $server = $this->start('ok');
        $evil = new MailMessage("cliente@example.com\r\nBcc: spy@example.com", 'Oggetto', "testo\n");

        $e = $this->expectFailure($this->transport($server->port), $evil);

        self::assertSame(MailTransportException::RECIPIENT_REJECTED, $e->category);
        self::assertSame([], $server->messages());
    }

    // === Failures: category, retry hint, clean message ===============================

    /** @return array<string, array{string, string, bool}> scenario => category, retryable */
    public static function failures(): array
    {
        return [
            'recipient rejected (550)' => ['rcpt_550', MailTransportException::RECIPIENT_REJECTED, false],
            'recipient busy (450)' => ['rcpt_450', MailTransportException::TEMPORARY, true],
            'authentication refused (535)' => ['auth_535', MailTransportException::AUTH_FAILED, false],
            'message refused after DATA (554)' => ['data_554', MailTransportException::REJECTED, false],
            'message deferred after DATA (451)' => ['data_451', MailTransportException::TEMPORARY, true],
            'connection dropped mid-message' => ['drop_mid_data', MailTransportException::CONNECTION, true],
            'connection dropped before the verdict' => ['drop_after_data', MailTransportException::CONNECTION, true],
        ];
    }

    #[DataProvider('failures')]
    public function testEveryServerFailureIsClassifiedAndSafe(string $scenario, string $category, bool $retryable): void
    {
        $server = $this->start($scenario);

        $e = $this->expectFailure($this->transport($server->port));

        self::assertSame($category, $e->category, $scenario . ': ' . $e->getMessage());
        self::assertSame($retryable, $e->retryable, $scenario);
        self::assertStringNotContainsString(self::PASSWORD, $e->getMessage());
        self::assertStringNotContainsString('smtp-user', $e->getMessage());
        self::assertStringNotContainsString('cliente@example.com', $e->getMessage(), 'recipient addresses are not echoed');
        self::assertDoesNotMatchRegularExpression('/[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[a-z]{2,}/i', $e->getMessage());
        self::assertLessThan(200, strlen($e->getMessage()));
    }

    public function testServiceUnavailableGreetingIsRetryable(): void
    {
        $server = $this->start('greeting_421');

        $e = $this->expectFailure($this->transport($server->port));

        self::assertTrue($e->retryable);
        self::assertContains($e->category, [MailTransportException::TEMPORARY, MailTransportException::CONNECTION]);
    }

    public function testAuthenticationRequiredWithoutCredentialsIsAConfigurationProblem(): void
    {
        $server = $this->start('auth_required');

        $e = $this->expectFailure($this->transport($server->port, user: ''));

        self::assertSame(MailTransportException::AUTH_FAILED, $e->category);
        self::assertFalse($e->retryable);
        self::assertSame([], $server->messages());
    }

    public function testAServerThatNeverAnswersTimesOutInBoundedTime(): void
    {
        $server = $this->start('hang');
        $started = microtime(true);

        $e = $this->expectFailure($this->transport($server->port, timeout: 2));
        $elapsed = microtime(true) - $started;

        self::assertSame(MailTransportException::TIMEOUT, $e->category, $e->getMessage());
        self::assertTrue($e->retryable);
        self::assertGreaterThanOrEqual(1.5, $elapsed);
        self::assertLessThan(5.0, $elapsed, 'the timeout must bound the wait');
    }

    public function testAClosedPortIsAConnectionError(): void
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        $name = (string) stream_socket_get_name($socket, false);
        fclose($socket); // nobody listens on this port any more
        $port = (int) substr($name, (int) strrpos($name, ':') + 1);

        $started = microtime(true);
        $e = $this->expectFailure($this->transport($port, timeout: 3));

        self::assertSame(MailTransportException::CONNECTION, $e->category, $e->getMessage());
        self::assertTrue($e->retryable);
        self::assertLessThan(4.0, microtime(true) - $started);
    }

    public function testAnUnknownHostIsAConnectionError(): void
    {
        $e = $this->expectFailure($this->transport(25, timeout: 3, host: 'no-such-host.invalid'));

        self::assertContains($e->category, [MailTransportException::CONNECTION, MailTransportException::TIMEOUT]);
        self::assertTrue($e->retryable);
    }

    public function testTlsIsNeverSilentlyDowngradedToPlainText(): void
    {
        $server = $this->start('ok'); // advertises no STARTTLS

        $e = $this->expectFailure($this->transport($server->port, encryption: 'tls'));

        self::assertSame([], $server->messages(), 'with TLS requested the message must not travel in clear text');
        self::assertFalse(in_array('MAIL', array_map(static fn (string $l): string => strtoupper(strtok($l, ' ')), $server->commands()), true), 'no MAIL FROM before TLS: ' . $e->getMessage());
    }

    // === Configuration problems ====================================================

    public function testMissingConfigurationIsReportedWithoutTryingToConnect(): void
    {
        $noHost = new SmtpTransport('', 587, 'tls', '', '', 5, 'info@agriturismolavolta.com', 'La Volta');
        $noFrom = new SmtpTransport('smtp.example.test', 587, 'tls', '', '', 5, '', 'La Volta');

        foreach ([$noHost, $noFrom] as $transport) {
            $e = $this->expectFailure($transport);
            self::assertSame(MailTransportException::NOT_CONFIGURED, $e->category);
            self::assertFalse($e->retryable);
        }
    }

    public function testAnInvalidRecipientAddressIsAPermanentError(): void
    {
        $server = $this->start('ok');

        $e = $this->expectFailure($this->transport($server->port), new MailMessage('not-an-address', 'Oggetto', 'testo'));

        self::assertSame(MailTransportException::RECIPIENT_REJECTED, $e->category);
        self::assertFalse($e->retryable);
        self::assertSame([], $server->messages());
    }
}
