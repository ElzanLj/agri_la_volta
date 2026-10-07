<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Config;
use App\Http\InvisibleChars;
use App\Http\Request;
use App\Support\DeferredWork;
use App\Support\Logger;
use PHPUnit\Framework\TestCase;

/** Small hardening fixes of phase 15: APP_ENV, logs, deferred work, invisible characters, client address. */
final class HardeningUnitTest extends TestCase
{
    private ?string $savedEnv = null;

    protected function setUp(): void
    {
        $saved = getenv('APP_ENV');
        $this->savedEnv = $saved === false ? null : $saved;
        putenv('APP_ENV'); // the test runner sets it: only the values given to Config count here
    }

    protected function tearDown(): void
    {
        $this->savedEnv === null ? putenv('APP_ENV') : putenv('APP_ENV=' . $this->savedEnv);
        DeferredWork::reset();
    }

    // === APP_ENV ===============================================================

    /** @return array<string, array{string}> */
    public static function notProductionValues(): array
    {
        return ['development' => ['development'], 'testing' => ['testing']];
    }

    /** @dataProvider notProductionValues */
    #[\PHPUnit\Framework\Attributes\DataProvider('notProductionValues')]
    public function testOnlyTheExactAllowedValuesAreNotProduction(string $value): void
    {
        $config = new Config(['APP_ENV' => $value]);

        self::assertFalse($config->isProduction());
        self::assertSame($value, $config->environment());
        self::assertTrue($config->environmentIsRecognised());
    }

    /** @return array<string, array{string}> */
    public static function productionValues(): array
    {
        return [
            'production' => ['production'],
            'typo' => ['prod'],
            'upper case' => ['PRODUCTION'],
            'upper case development' => ['Development'],
            'leading space' => [' production'],
            'space after dev' => ['development '],
            'staging' => ['staging'],
            'local' => ['local'],
            'digit' => ['0'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('productionValues')]
    public function testAnythingElseIsProductionSoProtectionsStayOn(string $value): void
    {
        $config = new Config(['APP_ENV' => $value, 'APP_DEBUG' => 'true']);

        self::assertTrue($config->isProduction(), json_encode($value));
        self::assertSame('production', $config->environment());
        self::assertFalse($config->isDebug(), 'debug output is never enabled in production');
    }

    public function testAnUnsetOrEmptyEnvironmentIsProduction(): void
    {
        foreach ([new Config([]), new Config(['APP_ENV' => ''])] as $config) {
            self::assertTrue($config->isProduction());
            self::assertTrue($config->environmentIsRecognised(), 'nothing set is not a mistake');
        }
    }

    public function testAMistypedEnvironmentIsReportedAsUnrecognised(): void
    {
        self::assertFalse((new Config(['APP_ENV' => 'prod']))->environmentIsRecognised());
        self::assertFalse((new Config(['APP_ENV' => 'PRODUCTION']))->environmentIsRecognised());
        self::assertTrue((new Config(['APP_ENV' => 'production']))->environmentIsRecognised());
    }

    // === Logs ===================================================================

    private function logDir(): string
    {
        $dir = sys_get_temp_dir() . '/lavolta-logger-test-' . bin2hex(random_bytes(5));
        mkdir($dir);
        register_shutdown_function(static function () use ($dir): void {
            foreach (glob($dir . '/*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($dir);
        });
        return $dir;
    }

    private function logContents(string $dir): string
    {
        return implode("\n", array_map(static fn (string $f): string => (string) file_get_contents($f), glob($dir . '/*.log') ?: []));
    }

    public function testADatabaseExceptionIsLoggedWithSqlstateAndCodeOnly(): void
    {
        $dir = $this->logDir();
        $e = new \PDOException("SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry 'mario.rossi@example.test' for key 'uq_x'; connection to db.internal.example with user app_user");
        $e->errorInfo = ['23000', 1062, "Duplicate entry 'mario.rossi@example.test' for key 'uq_x'"];

        (new Logger($dir))->error('Unhandled exception', ['exception' => $e, 'path' => '/x']);

        $log = $this->logContents($dir);
        self::assertStringContainsString('PDOException: SQLSTATE 23000, driver code 1062', $log);
        self::assertStringNotContainsString('mario.rossi', $log);
        self::assertStringNotContainsString('example.test', $log);
        self::assertStringNotContainsString('db.internal', $log);
        self::assertStringNotContainsString('app_user', $log);
        self::assertStringNotContainsString('Duplicate entry', $log);
    }

    public function testADatabaseExceptionWithoutErrorInfoStillLogsNoMessage(): void
    {
        $dir = $this->logDir();

        (new Logger($dir))->error('x', ['exception' => new \PDOException('could not connect to secret-host as secret-user', 2002)]);

        $log = $this->logContents($dir);
        self::assertStringContainsString('PDOException: SQLSTATE unknown', $log);
        self::assertStringNotContainsString('secret-host', $log);
        self::assertStringNotContainsString('secret-user', $log);
    }

    public function testOtherExceptionsKeepTheirMessage(): void
    {
        $dir = $this->logDir();

        (new Logger($dir))->error('x', ['exception' => new \RuntimeException('something specific broke')]);

        self::assertStringContainsString('RuntimeException: something specific broke', $this->logContents($dir));
    }

    public function testAnUnwritableLogFolderFallsBackToThePhpErrorLog(): void
    {
        $target = sys_get_temp_dir() . '/lavolta-error-log-' . bin2hex(random_bytes(5));
        $previous = ini_set('error_log', $target);
        try {
            (new Logger('/nonexistent/folder/for/logs'))->warning('Fallback works', ['path' => '/x']);
        } finally {
            ini_set('error_log', $previous === false ? '' : $previous);
        }

        self::assertFileExists($target);
        self::assertStringContainsString('Fallback works', (string) file_get_contents($target));
        @unlink($target);
    }

    public function testALogFileThatCannotBeWrittenNeverBreaksThePage(): void
    {
        // The folder is writable but today's file is not (for example it was created by another user,
        // such as root running the tests or a command line script). A directory with that name fails
        // for every user, root included. Writing a log line must never turn into a fatal error.
        $dir = $this->logDir();
        mkdir($dir . '/app-' . gmdate('Y-m-d') . '.log');
        $target = sys_get_temp_dir() . '/lavolta-error-log-' . bin2hex(random_bytes(5));
        $previous = ini_set('error_log', $target);
        try {
            $logger = new Logger($dir);
            $logger->warning('Admin login failed');
            $logger->error('Unhandled exception', ['exception' => new \RuntimeException('boom')]);
        } finally {
            ini_set('error_log', $previous === false ? '' : $previous);
        }

        self::assertFileExists($target);
        $fallback = (string) file_get_contents($target);
        self::assertStringContainsString('Admin login failed', $fallback);
        self::assertStringContainsString('Unhandled exception', $fallback);
        @unlink($target);
        @rmdir($dir . '/app-' . gmdate('Y-m-d') . '.log');
    }

    // === Deferred work ==========================================================

    public function testTheFinishFunctionIsFoundForPhpFpmOrLiteSpeed(): void
    {
        self::assertSame('fastcgi_finish_request', DeferredWork::finishFunction(static fn (string $f): bool => $f === 'fastcgi_finish_request'));
        self::assertSame('litespeed_finish_request', DeferredWork::finishFunction(static fn (string $f): bool => $f === 'litespeed_finish_request'));
        self::assertSame('fastcgi_finish_request', DeferredWork::finishFunction(static fn (string $f): bool => true), 'FPM wins when both exist');
        self::assertNull(DeferredWork::finishFunction(static fn (string $f): bool => false));
    }

    public function testJobsRunEvenIfTheVisitorLeavesAndEvenIfClosingTheConnectionFails(): void
    {
        $previous = ignore_user_abort(false);
        try {
            $ran = [];
            DeferredWork::defer(static function () use (&$ran): void {
                $ran[] = 'first';
            });
            DeferredWork::defer(static function () use (&$ran): void {
                $ran[] = 'second';
            });

            DeferredWork::flush(static function (): void {
                throw new \RuntimeException('connection already closed');
            });

            self::assertSame(['first', 'second'], $ran);
            self::assertSame(1, ignore_user_abort(), 'a closed page must not stop the e-mail that is being sent');
        } finally {
            ignore_user_abort((bool) $previous);
        }
    }

    // === Invisible characters ===================================================

    public function testInvisibleAndDirectionControlCharactersAreRemoved(): void
    {
        $dirty = "Ro\u{200B}ssi\u{202E}\u{200F}\u{FEFF}\u{2066}x\u{2069}\u{202A}";

        self::assertSame('Rossix', InvisibleChars::strip($dirty));
    }

    public function testVisibleTextAccentsEmojiAndNewlinesAreKept(): void
    {
        $text = "Zoë Müller – “Città”\n😀 Nuova riga\tcon tab";

        self::assertSame($text, InvisibleChars::strip($text));
        self::assertSame('', InvisibleChars::strip(''));
    }

    public function testTextThatIsNotValidUtf8IsLeftAsItIs(): void
    {
        self::assertSame("abc\xFF", InvisibleChars::strip("abc\xFF"));
    }

    public function testRequestInputIsCleanedButPasswordsAreNot(): void
    {
        $request = new Request('POST', '/x', ['first_name' => "Ma\u{202E}rio\u{200B}", 'password' => "pa\u{200B}ss", 'list' => ['a']], [], ['q' => "a\u{200B}b"]);

        self::assertSame('Mario', $request->input('first_name'));
        self::assertSame("pa\u{200B}ss", $request->rawInput('password'), 'a password must reach the check exactly as typed');
        self::assertSame('', $request->input('list'));
        self::assertSame('ab', $request->query('q'));
    }

    // === Client address =========================================================

    public function testTheClientAddressIsAlwaysTheConnectionAddress(): void
    {
        $server = [
            'REMOTE_ADDR' => '203.0.113.5',
            'HTTP_X_FORWARDED_FOR' => '198.51.100.99',
            'HTTP_CF_CONNECTING_IP' => '198.51.100.98',
            'HTTP_X_REAL_IP' => '198.51.100.97',
            'HTTP_FORWARDED' => 'for=198.51.100.96',
            'HTTP_CLIENT_IP' => '198.51.100.95',
        ];

        self::assertSame('203.0.113.5', (new Request('GET', '/', [], $server))->ip());
    }
}
