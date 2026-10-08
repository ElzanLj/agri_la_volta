<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\App;
use App\Database\Migrator;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * bin/create-admin.php --print-sql: the way to create or recover the admin account on hosting without SSH.
 * It must not touch any database, never take the password from the arguments, print only SQL and the
 * SQL must work both on an empty account table and when an admin already exists.
 */
final class CreateAdminCommandTest extends TestCase
{
    private const PASSWORD = 'Il-gatto-dorme-sul-divano-blu-77';
    private const DATABASE = 'agriturismo_adminsql_test';

    private ?PDO $root = null;
    private ?PDO $scratch = null;

    protected function tearDown(): void
    {
        if ($this->root !== null) {
            $this->root->exec('DROP DATABASE IF EXISTS `' . self::DATABASE . '`');
        }
    }

    /**
     * @param list<string> $arguments
     * @param list<string> $lines what is typed after the arguments (password and confirmation)
     * @param array<string, string> $env
     * @return array{int, string, string} exit code, stdout, stderr
     */
    private function runCommand(array $arguments, array $lines, array $env = []): array
    {
        $command = [PHP_BINARY, dirname(__DIR__, 2) . '/bin/create-admin.php', ...$arguments];
        $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__, 2), array_merge(getenv(), $env));
        self::assertIsResource($process);
        fwrite($pipes[0], implode("\n", $lines) . "\n");
        fclose($pipes[0]);
        $out = (string) stream_get_contents($pipes[1]);
        $err = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        return [proc_close($process), $out, $err];
    }

    /** A database that cannot be reached: a command that tries to use it fails. */
    private function unreachableDatabase(): array
    {
        return ['DB_HOST' => '127.0.0.1', 'DB_PORT' => '1', 'DB_NAME' => 'does_not_exist', 'APP_ENV' => 'development'];
    }

    private function scratchDatabase(): PDO
    {
        $config = App::current()->config;
        if ($config->string('DB_ROOT_PASSWORD') === '') {
            self::markTestSkipped('DB_ROOT_PASSWORD is not configured.');
        }
        $dsn = static fn (string $db): string => sprintf('mysql:host=%s;port=%d;%scharset=utf8mb4', $config->string('DB_HOST', 'localhost'), $config->int('DB_PORT', 3306), $db === '' ? '' : "dbname=$db;");
        $options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC];
        $this->root = new PDO($dsn(''), 'root', $config->string('DB_ROOT_PASSWORD'), $options);
        $this->root->exec('DROP DATABASE IF EXISTS `' . self::DATABASE . '`');
        $this->root->exec('CREATE DATABASE `' . self::DATABASE . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $this->scratch = new PDO($dsn(self::DATABASE), 'root', $config->string('DB_ROOT_PASSWORD'), $options);
        (new Migrator($this->scratch, dirname(__DIR__, 2) . '/migrations'))->migrate();
        return $this->scratch;
    }

    /** Imports SQL the way phpMyAdmin does: statement by statement. */
    private function import(PDO $db, string $sql): void
    {
        foreach (Migrator::splitStatements($sql) as $statement) {
            $db->exec($statement);
        }
    }

    public function testPrintSqlNeverTouchesTheDatabase(): void
    {
        [$code, $out, $err] = $this->runCommand(['gestore', '--print-sql'], [self::PASSWORD, self::PASSWORD], $this->unreachableDatabase());

        self::assertSame(0, $code, $err);
        self::assertStringContainsString('UPDATE admin', $out);
    }

    public function testThePasswordIsReadFromTheTerminalAndNeverFromTheArguments(): void
    {
        [$code, $out, $err] = $this->runCommand(['gestore', '--print-sql', self::PASSWORD], [self::PASSWORD, self::PASSWORD], $this->unreachableDatabase());

        self::assertSame(1, $code, 'an extra argument is not a password');
        self::assertStringNotContainsString(self::PASSWORD, $out);
        self::assertStringContainsString('Troppi argomenti', $err);

        [$code2, $out2] = $this->runCommand(['gestore', self::PASSWORD, '--print-sql'], [self::PASSWORD, self::PASSWORD], $this->unreachableDatabase());
        self::assertSame(1, $code2, 'a second positional argument is the password typed on the command line: refused');
        self::assertSame('', trim($out2));
    }

    public function testOnlySqlGoesToTheOutputAndTheQuestionsGoToTheErrorStream(): void
    {
        [$code, $out, $err] = $this->runCommand(['gestore', '--print-sql'], [self::PASSWORD, self::PASSWORD], $this->unreachableDatabase());

        self::assertSame(0, $code);
        foreach (explode("\n", trim($out)) as $line) {
            self::assertMatchesRegularExpression('/^(--|UPDATE |INSERT )/', $line, 'every output line is a comment or a statement: ' . substr($line, 0, 40));
        }
        self::assertStringContainsString('Password:', $err);
        self::assertStringNotContainsString('Password:', $out);
        self::assertStringNotContainsString(self::PASSWORD, $out . $err, 'the password is never printed');
    }

    public function testTheHashInTheSqlVerifiesTheChosenPassword(): void
    {
        [, $out] = $this->runCommand(['gestore', '--print-sql'], [self::PASSWORD, self::PASSWORD], $this->unreachableDatabase());

        self::assertSame(1, preg_match("/password_hash = '(\\\$2y\\\$[^']+)'/", $out, $m), $out);
        self::assertTrue(password_verify(self::PASSWORD, $m[1]));
        self::assertFalse(password_verify(self::PASSWORD . 'x', $m[1]));
    }

    public function testTheSqlCreatesTheAccountOnAnEmptyTable(): void
    {
        $db = $this->scratchDatabase();
        [, $out] = $this->runCommand(['gestore', '--print-sql'], [self::PASSWORD, self::PASSWORD], $this->unreachableDatabase());

        $this->import($db, $out);

        $rows = $db->query('SELECT username, password_hash, session_version FROM admin')->fetchAll();
        self::assertCount(1, $rows);
        self::assertSame('gestore', $rows[0]['username']);
        self::assertTrue(password_verify(self::PASSWORD, $rows[0]['password_hash']));
        self::assertSame(1, (int) $db->query("SELECT COUNT(*) FROM audit_log WHERE entity_type = 'admin' AND action = 'credentials_changed'")->fetchColumn());
        self::assertStringNotContainsString(self::PASSWORD, (string) $db->query('SELECT GROUP_CONCAT(CONCAT_WS(" ", summary, old_values, new_values)) FROM audit_log')->fetchColumn());
    }

    public function testTheSameSqlRecoversAForgottenPasswordAndKeepsASingleAccount(): void
    {
        $db = $this->scratchDatabase();
        $db->prepare('INSERT INTO admin (username, password_hash) VALUES (?, ?)')->execute(['vecchio-nome', password_hash('la-vecchia-password-12', PASSWORD_DEFAULT)]);
        $idBefore = (int) $db->query('SELECT id FROM admin')->fetchColumn();

        [, $out] = $this->runCommand(['gestore', '--print-sql'], [self::PASSWORD, self::PASSWORD], $this->unreachableDatabase());
        $this->import($db, $out);
        $this->import($db, $out); // importing twice must not create a second account

        $rows = $db->query('SELECT id, username, password_hash FROM admin')->fetchAll();
        self::assertCount(1, $rows, 'there is still one account');
        self::assertSame($idBefore, (int) $rows[0]['id']);
        self::assertSame('gestore', $rows[0]['username']);
        self::assertTrue(password_verify(self::PASSWORD, $rows[0]['password_hash']));
        self::assertFalse(password_verify('la-vecchia-password-12', $rows[0]['password_hash']), 'the old password no longer works');
    }

    public function testTheSameRulesAsTheAccountPageApply(): void
    {
        foreach ([
            'too short' => 'Corta1!',
            'a common one' => 'Password123456',
            'the example' => 'Agriturismo2026!',
            'the user name' => 'xx-gestore-Qk7z!9-yy',
            'repetitive' => 'aaaaaaaaaaaaaaaa',
        ] as $why => $weak) {
            [$code, $out, $err] = $this->runCommand(['gestore', '--print-sql'], [$weak, $weak], $this->unreachableDatabase());

            self::assertSame(1, $code, $why);
            self::assertSame('', trim($out), "$why: no SQL is printed for a refused password");
            self::assertNotSame('', trim($err), "$why: a reason is given");
        }
    }

    public function testDifferentConfirmationAndUnknownOptionsAreRefused(): void
    {
        [$code, $out, $err] = $this->runCommand(['gestore', '--print-sql'], [self::PASSWORD, self::PASSWORD . 'x'], $this->unreachableDatabase());
        self::assertSame(1, $code);
        self::assertSame('', trim($out));
        self::assertStringContainsString('non coincidono', $err);

        [$code2, , $err2] = $this->runCommand(['gestore', '--print-slq'], [self::PASSWORD, self::PASSWORD], $this->unreachableDatabase());
        self::assertSame(1, $code2, 'a mistyped option must not silently write to the database');
        self::assertStringContainsString('Opzione sconosciuta', $err2);
    }

    public function testAnInvalidUserNameIsRefused(): void
    {
        foreach (["ab", "con spazio", "x'; DROP TABLE admin; --", str_repeat('a', 101)] as $name) {
            [$code, $out] = $this->runCommand([$name, '--print-sql'], [self::PASSWORD, self::PASSWORD], $this->unreachableDatabase());

            self::assertSame(1, $code, $name);
            self::assertSame('', trim($out));
        }
    }
}
