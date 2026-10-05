<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Config;
use App\Mail\CancellationDraft;
use App\Mail\ErrorSanitizer;
use App\Mail\LogTransport;
use App\Mail\MailMessage;
use App\Mail\MailTransportException;
use App\Mail\MailTransportFactory;
use App\Mail\NotConfiguredTransport;
use App\Mail\SmtpTransport;
use App\Support\DeferredWork;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MailUnitTest extends TestCase
{
    protected function tearDown(): void
    {
        DeferredWork::reset();
    }

    // === Error sanitiser =======================================================

    public function testEmailAddressesAreRemoved(): void
    {
        $clean = ErrorSanitizer::clean('550 5.1.1 <mario.rossi@example.com>: Recipient address rejected; contact postmaster@mail.provider.it');

        self::assertStringNotContainsString('@', $clean);
        self::assertStringContainsString('[email]', $clean);
        self::assertStringContainsString('Recipient address rejected', $clean);
    }

    public function testSecretsAreRemovedWhereverTheyAppear(): void
    {
        $clean = ErrorSanitizer::clean('AUTH failed for user smtp-user with password Sup3r-Secret!', ['Sup3r-Secret!', 'smtp-user']);

        self::assertStringNotContainsString('Sup3r-Secret', $clean);
        self::assertStringNotContainsString('smtp-user', $clean);
    }

    public function testVeryShortSecretsAreNotUsedToRewriteTheText(): void
    {
        self::assertSame('a b c', ErrorSanitizer::clean('a b c', ['a', '']));
    }

    public function testCredentialLookingTokensAreHidden(): void
    {
        self::assertStringNotContainsString('dXNlcm5hbWVwYXNzd29yZDEyMzQ1Njc4OQ', ErrorSanitizer::clean('334 dXNlcm5hbWVwYXNzd29yZDEyMzQ1Njc4OQ=='));
        self::assertStringNotContainsString('hunter2', ErrorSanitizer::clean('login with password=hunter2 failed'));
        self::assertStringNotContainsString('hunter2', ErrorSanitizer::clean('login with pass: hunter2 failed'));
    }

    public function testControlCharactersAreFlattenedAndLengthIsBounded(): void
    {
        $clean = ErrorSanitizer::clean("line one\r\nline two\0\t" . str_repeat('x ', 300));

        self::assertDoesNotMatchRegularExpression('/[\x00-\x1F]/', $clean);
        self::assertLessThanOrEqual(200, mb_strlen($clean));
        self::assertStringEndsWith('…', $clean);
        self::assertSame('', ErrorSanitizer::clean(''));
        self::assertSame('Connessione non riuscita àèì', ErrorSanitizer::clean('Connessione non riuscita àèì'), 'ordinary Italian text is untouched');
    }

    // === MailMessage ===========================================================

    /** @return array<string, array{string, string}> */
    public static function lines(): array
    {
        return [
            'crlf' => ["a\r\nBcc: x@y.z", 'a Bcc: x@y.z'],
            'lf only' => ["a\nb", 'a b'],
            'nul byte' => ["a\0b", 'a b'],
            'tab' => ["a\tb", 'a b'],
            'surrounding blanks' => ["  a  ", 'a'],
            'plain' => ['Normale', 'Normale'],
        ];
    }

    #[DataProvider('lines')]
    public function testHeaderValuesAreFlattenedToOneLine(string $input, string $expected): void
    {
        $m = new MailMessage($input, $input, 'body', $input, $input);

        self::assertSame($expected, $m->to);
        self::assertSame($expected, $m->subject);
        self::assertSame($expected, $m->replyTo);
        self::assertSame($expected, $m->replyToName);
        self::assertSame('body', $m->body);
    }

    // === Deferred work ==========================================================

    public function testDeferredWorkRunsAfterTheResponseIsClosedAndNeverThrows(): void
    {
        $order = [];
        DeferredWork::defer(function () use (&$order): void {
            $order[] = 'first';
        });
        DeferredWork::defer(static function (): void {
            throw new \RuntimeException('a failing job');
        });
        DeferredWork::defer(function () use (&$order): void {
            $order[] = 'third';
        });

        DeferredWork::flush(function () use (&$order): void {
            $order[] = 'response closed';
        });

        self::assertSame(['response closed', 'first', 'third'], $order, 'the response is closed first; a failing job does not stop the others');
    }

    public function testFlushWithNothingQueuedDoesNotTouchTheConnection(): void
    {
        $called = false;
        DeferredWork::flush(function () use (&$called): void {
            $called = true;
        });

        self::assertFalse($called);
    }

    public function testJobsAreRunOnlyOnce(): void
    {
        $runs = 0;
        DeferredWork::defer(function () use (&$runs): void {
            $runs++;
        });

        DeferredWork::flush(static fn () => null);
        DeferredWork::flush(static fn () => null);

        self::assertSame(1, $runs);
    }

    public function testAFailingConnectionCloserDoesNotPreventTheWork(): void
    {
        $ran = false;
        DeferredWork::defer(function () use (&$ran): void {
            $ran = true;
        });

        DeferredWork::flush(static function (): void {
            throw new \RuntimeException('cannot close');
        });

        self::assertTrue($ran);
    }

    public function testWithoutFpmTheWorkIsNotDeferred(): void
    {
        self::assertSame(function_exists('fastcgi_finish_request'), DeferredWork::available());
        self::assertFalse(DeferredWork::available(), 'the CLI test runner is not PHP-FPM');
    }

    // === Transport selection ===================================================

    /** @param array<string, string> $values */
    private function config(array $values): Config
    {
        return new Config($values);
    }

    public function testSmtpWithoutAHostIsNotConfiguredAndNeverThrowsAtBuildTime(): void
    {
        $transport = MailTransportFactory::fromConfig($this->config(['MAIL_TRANSPORT' => 'smtp', 'SMTP_HOST' => '']), sys_get_temp_dir());

        self::assertInstanceOf(NotConfiguredTransport::class, $transport);
        $this->expectException(MailTransportException::class);
        $this->expectExceptionMessage('SMTP_HOST non configurato');
        $transport->send(new MailMessage('a@example.com', 's', 'b'));
    }

    public function testSmtpIsTheDefault(): void
    {
        self::assertInstanceOf(NotConfiguredTransport::class, MailTransportFactory::fromConfig($this->config([]), sys_get_temp_dir()));
        self::assertInstanceOf(SmtpTransport::class, MailTransportFactory::fromConfig($this->config(['SMTP_HOST' => 'smtp.example.test']), sys_get_temp_dir()));
        self::assertInstanceOf(SmtpTransport::class, MailTransportFactory::fromConfig($this->config(['MAIL_TRANSPORT' => 'SMTP', 'SMTP_HOST' => 'smtp.example.test']), sys_get_temp_dir()));
    }

    public function testTheLogTransportIsRefusedInProduction(): void
    {
        // Real environment variables win over file values, and the test runner sets APP_ENV: control it here.
        $previous = getenv('APP_ENV');
        try {
            putenv('APP_ENV=development');
            $dev = MailTransportFactory::fromConfig($this->config(['MAIL_TRANSPORT' => 'log']), sys_get_temp_dir());
            putenv('APP_ENV=production');
            $production = MailTransportFactory::fromConfig($this->config(['MAIL_TRANSPORT' => 'log']), sys_get_temp_dir());
            putenv('APP_ENV');
            $defaultEnv = MailTransportFactory::fromConfig($this->config(['MAIL_TRANSPORT' => 'log']), sys_get_temp_dir()); // APP_ENV unset: production
        } finally {
            $previous === false ? putenv('APP_ENV') : putenv('APP_ENV=' . $previous);
        }

        self::assertInstanceOf(LogTransport::class, $dev);
        self::assertInstanceOf(NotConfiguredTransport::class, $production, 'a production site must never silently drop e-mails into a folder');
        self::assertInstanceOf(NotConfiguredTransport::class, $defaultEnv);
    }

    public function testAnUnknownTransportNameIsNotConfigured(): void
    {
        self::assertInstanceOf(NotConfiguredTransport::class, MailTransportFactory::fromConfig($this->config(['MAIL_TRANSPORT' => 'sendmail']), sys_get_temp_dir()));
    }

    public function testNotConfiguredFailuresArePermanentUntilTheSettingsChange(): void
    {
        try {
            (new NotConfiguredTransport('manca qualcosa'))->send(new MailMessage('a@example.com', 's', 'b'));
            self::fail('Expected an exception');
        } catch (MailTransportException $e) {
            self::assertSame(MailTransportException::NOT_CONFIGURED, $e->category);
            self::assertFalse($e->retryable);
        }
    }

    public function testTheLogTransportWritesAReadableMessageFile(): void
    {
        $dir = sys_get_temp_dir() . '/lavolta-logmail-' . bin2hex(random_bytes(4));
        try {
            (new LogTransport($dir))->send(new MailMessage('cliente@example.com', 'Oggetto àèì', "Corpo\nsu due righe", 'mario@example.com', 'Mario'));

            $files = glob($dir . '/*.eml') ?: [];
            self::assertCount(1, $files);
            $content = (string) file_get_contents($files[0]);
            self::assertStringContainsString('To: cliente@example.com', $content);
            self::assertStringContainsString('Subject: Oggetto àèì', $content);
            self::assertStringContainsString('Reply-To: Mario <mario@example.com>', $content);
            self::assertStringContainsString("Corpo\nsu due righe", $content);
        } finally {
            foreach (glob($dir . '/*') ?: [] as $f) {
                unlink($f);
            }
            @rmdir($dir);
        }
    }

    public function testTheLogTransportReportsAnUnwritableFolder(): void
    {
        $this->expectException(MailTransportException::class);
        (new LogTransport('/proc/definitely/not/writable'))->send(new MailMessage('a@example.com', 's', 'b'));
    }

    // === Cancellation draft ======================================================

    /** @return array<string, mixed> */
    private function booking(): array
    {
        return ['guest_name' => "Famiglia Bianchi\r\nBcc: x@y.z", 'apartment_name' => 'Mimosa', 'check_in' => '2027-06-10', 'check_out' => '2027-06-15'];
    }

    public function testTheDraftIsEditableAndNeverReadyToSendAsIs(): void
    {
        $it = CancellationDraft::build($this->booking(), 'it');
        $en = CancellationDraft::build($this->booking(), 'en');

        self::assertStringContainsString('Cancellazione della prenotazione', $it['subject']);
        self::assertStringContainsString("appartamento Mimosa dal 10/06/2027 al 15/06/2027", $it['body']);
        self::assertStringContainsString('Gentile Famiglia Bianchi Bcc: x@y.z', $it['body'], 'line breaks in the name are flattened');
        self::assertTrue(CancellationDraft::hasPlaceholder($it['body']));
        self::assertTrue(CancellationDraft::hasPlaceholder($en['body']));
        self::assertStringContainsString('apartment Mimosa from 10 June 2027 to 15 June 2027', $en['body']);
        self::assertStringContainsString('Dear Famiglia Bianchi', $en['body']);

        $edited = str_replace(CancellationDraft::PLACEHOLDER, 'Ci dispiace, per un imprevisto non possiamo ospitarvi.', $it['body']);
        self::assertFalse(CancellationDraft::hasPlaceholder($edited));
        self::assertFalse(CancellationDraft::hasPlaceholder(str_replace(CancellationDraft::PLACEHOLDER, '', $it['body'])), 'removing the placeholder is also fine');
    }
}
