<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Support\ProductionCheck;
use Tests\Support\DatabaseTestCase;

/** The read-only production readiness check: every rule, with a good setup and with each thing broken. */
final class ProductionCheckTest extends DatabaseTestCase
{
    private const SECRET_VALUE = 'Sup3r-Segreto-Da-Non-Stampare-123';
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = sys_get_temp_dir() . '/lavolta-check-' . bin2hex(random_bytes(4));
        $this->makeRoot($this->root);
        $this->db->exec('DELETE FROM admin');
        $this->db->prepare('INSERT INTO admin (username, password_hash) VALUES (?, ?)')->execute(['admin-test', password_hash('una password abbastanza lunga', PASSWORD_DEFAULT)]);
        $this->db->exec("INSERT INTO seasonal_rates (apartment_id, label, start_date, end_date, nightly_rate_cents) SELECT id, '[TEST]', '2030-01-01', '2030-02-01', 1000 FROM apartments LIMIT 1");
        $this->db->exec("INSERT INTO apartment_translations (apartment_id, locale, description) SELECT id, 'it', 'Testo di prova' FROM apartments");
    }

    protected function tearDown(): void
    {
        $this->db->exec('DELETE FROM admin');
        $this->removeTree($this->root);
    }

    private function makeRoot(string $root): void
    {
        foreach (['vendor/phpmailer/phpmailer', 'storage/logs', 'storage/sessions', 'migrations', 'public'] as $dir) {
            mkdir($root . '/' . $dir, 0755, true);
        }
        file_put_contents($root . '/vendor/autoload.php', '<?php');
        foreach (glob(dirname(__DIR__, 2) . '/migrations/*.sql') ?: [] as $file) {
            touch($root . '/migrations/' . basename($file));
        }
    }

    private function removeTree(string $path): void
    {
        if (is_link($path) || is_file($path)) {
            @unlink($path);
            return;
        }
        foreach (glob($path . '/{,.}*', GLOB_BRACE) ?: [] as $child) {
            if (!in_array(basename($child), ['.', '..'], true)) {
                $this->removeTree($child);
            }
        }
        @rmdir($path);
    }

    /** @return array<string, string> a configuration that passes every check */
    private function goodEnv(): array
    {
        return [
            'APP_ENV' => 'production', 'APP_DEBUG' => 'false', 'APP_URL' => 'https://www.agriturismolavolta.com',
            'APP_SECRET' => str_repeat('a1b2c3d4', 8), 'HSTS_MAX_AGE' => '15552000', 'DATA_RETENTION_MONTHS' => '24',
            'DB_NAME' => 'agriturismo', 'MAIL_TRANSPORT' => 'smtp', 'SMTP_HOST' => 'smtp.example.test', 'SMTP_PORT' => '587',
            'SMTP_ENCRYPTION' => 'tls', 'SMTP_USERNAME' => 'info@agriturismolavolta.com', 'SMTP_PASSWORD' => self::SECRET_VALUE,
            'MAIL_FROM_ADDRESS' => 'info@agriturismolavolta.com', 'MAIL_ADMIN_ADDRESS' => 'info@agriturismolavolta.com',
            'PUBLIC_PHONE' => '+39 0521 000000', 'PUBLIC_EMAIL' => 'info@agriturismolavolta.com', 'PUBLIC_ADDRESS' => 'Via Esempio 1', 'WHATSAPP_NUMBER' => '390000000000',
        ];
    }

    /** @param array<string, string> $override @return array<string, array{level: string, check: string, message: string}> by check name */
    private function check(array $override = [], ?array $php = null, bool $withDatabase = true, ?string $root = null): array
    {
        $check = new ProductionCheck($override + $this->goodEnv(), $root ?? $this->root, $withDatabase ? $this->db : null, $withDatabase ? null : 'codice 2002', $php);
        $byName = [];
        foreach ($check->run() as $result) {
            $byName[$result['check']] = $result;
        }
        return $byName;
    }

    private function assertLevel(string $level, string $check, array $results, string $why = ''): void
    {
        self::assertArrayHasKey($check, $results, "no result for $check");
        self::assertSame($level, $results[$check]['level'], "$check $why: " . $results[$check]['message']);
    }

    // === A good installation ====================================================

    public function testAFullyReadyInstallationHasNoErrorsAndNoWarnings(): void
    {
        $results = $this->check();

        foreach ($results as $name => $result) {
            self::assertSame(ProductionCheck::OK, $result['level'], "$name: " . $result['message']);
        }
        self::assertGreaterThan(20, count($results));
    }

    public function testNoSecretValueIsEverPrinted(): void
    {
        $env = ['APP_SECRET' => self::SECRET_VALUE . '-app', 'SMTP_PASSWORD' => self::SECRET_VALUE];
        $text = json_encode($this->check($env), JSON_THROW_ON_ERROR);
        $broken = json_encode($this->check($env + ['APP_ENV' => 'development', 'SMTP_HOST' => '', 'MAIL_TRANSPORT' => 'log'], null, false), JSON_THROW_ON_ERROR);

        foreach ([$text, $broken] as $output) {
            self::assertStringNotContainsString(self::SECRET_VALUE, $output);
            self::assertStringNotContainsString('a1b2c3d4', $output);
        }
    }

    // === Blocking errors ========================================================

    public function testEachBlockingProblemIsReportedAsAnError(): void
    {
        $cases = [
            'wrong environment' => [['APP_ENV' => 'development'], 'APP_ENV'],
            'unset environment' => [['APP_ENV' => ''], 'APP_ENV'],
            'plain http' => [['APP_URL' => 'http://www.agriturismolavolta.com'], 'APP_URL'],
            'localhost' => [['APP_URL' => 'https://localhost'], 'APP_URL'],
            'test domain' => [['APP_URL' => 'https://sito.test'], 'APP_URL'],
            'trailing slash' => [['APP_URL' => 'https://www.agriturismolavolta.com/'], 'APP_URL'],
            'missing url' => [['APP_URL' => ''], 'APP_URL'],
            'url with a query' => [['APP_URL' => 'https://www.agriturismolavolta.com?x=1'], 'APP_URL'],
            'log transport' => [['MAIL_TRANSPORT' => 'log'], 'MAIL_TRANSPORT'],
            'no smtp host' => [['SMTP_HOST' => ''], 'SMTP_HOST'],
            'bad smtp port' => [['SMTP_PORT' => '99999'], 'SMTP_PORT'],
            'no sender' => [['MAIL_FROM_ADDRESS' => ''], 'MAIL_FROM_ADDRESS'],
            'invalid recipient' => [['MAIL_ADMIN_ADDRESS' => 'not an address'], 'MAIL_ADMIN_ADDRESS'],
            'test database' => [['DB_NAME' => 'agriturismo_test'], 'Database'],
        ];
        foreach ($cases as $name => [$override, $check]) {
            $this->assertLevel(ProductionCheck::FAIL, $check, $this->check($override), $name);
        }
    }

    public function testPhpVersionAndExtensionsAreChecked(): void
    {
        $extensions = ['pdo_mysql', 'mbstring', 'ctype', 'json', 'session', 'openssl', 'hash', 'filter'];

        $this->assertLevel(ProductionCheck::OK, 'PHP', $this->check([], ['version' => '8.1.0', 'extensions' => $extensions]));
        $this->assertLevel(ProductionCheck::FAIL, 'PHP', $this->check([], ['version' => '8.0.30', 'extensions' => $extensions]));
        $results = $this->check([], ['version' => '8.2.0', 'extensions' => array_diff($extensions, ['mbstring', 'openssl'])]);
        $this->assertLevel(ProductionCheck::FAIL, 'Estensioni PHP', $results);
        self::assertStringContainsString('mbstring, openssl', $results['Estensioni PHP']['message']);
    }

    public function testMissingFilesAndFoldersAreErrors(): void
    {
        $noVendor = $this->root . '-novendor';
        $this->makeRoot($noVendor);
        $this->removeTree($noVendor . '/vendor');
        $this->assertLevel(ProductionCheck::FAIL, 'vendor/', $this->check([], null, true, $noVendor));
        $this->removeTree($noVendor);

        $noLogs = $this->root . '-nologs';
        $this->makeRoot($noLogs);
        $this->removeTree($noLogs . '/storage/logs');
        $this->assertLevel(ProductionCheck::FAIL, 'storage/logs', $this->check([], null, true, $noLogs));
        $this->assertLevel(ProductionCheck::OK, 'storage/sessions', $this->check([], null, true, $noLogs));
        $this->removeTree($noLogs);

        file_put_contents($this->root . '/public/.env', 'X=1');
        $this->assertLevel(ProductionCheck::FAIL, 'public/', $this->check());
    }

    public function testDatabaseProblemsAreErrorsWithoutLeakingTheServerMessage(): void
    {
        $unreachable = $this->check([], null, false);
        $this->assertLevel(ProductionCheck::FAIL, 'Database', $unreachable);
        self::assertStringContainsString('codice 2002', $unreachable['Database']['message']);
        self::assertArrayNotHasKey('Migrazioni', $unreachable, 'nothing else is attempted without a database');

        touch($this->root . '/migrations/9999_not_applied_yet.sql');
        $pending = $this->check();
        $this->assertLevel(ProductionCheck::FAIL, 'Migrazioni', $pending);
        self::assertStringContainsString('9999_not_applied_yet', $pending['Migrazioni']['message']);
    }

    public function testAdminAccountAndApartmentsAreRequired(): void
    {
        $this->db->exec('DELETE FROM admin');
        $this->assertLevel(ProductionCheck::FAIL, 'Amministratore', $this->check(), 'no admin');

        $this->db->exec("INSERT INTO admin (username, password_hash) VALUES ('x', 'not-a-hash')");
        $this->assertLevel(ProductionCheck::FAIL, 'Amministratore', $this->check(), 'not a password hash');

        $this->db->exec('UPDATE apartments SET is_active = 0');
        $this->assertLevel(ProductionCheck::FAIL, 'Appartamenti', $this->check());
    }

    // === Warnings ===============================================================

    public function testEachAdvisoryProblemIsAWarningNotAnError(): void
    {
        $cases = [
            'debug on' => [['APP_DEBUG' => 'true'], 'APP_DEBUG'],
            'no secret' => [['APP_SECRET' => ''], 'APP_SECRET'],
            'short secret' => [['APP_SECRET' => 'abc'], 'APP_SECRET'],
            'hsts off' => [['HSTS_MAX_AGE' => '0'], 'HSTS_MAX_AGE'],
            'no retention' => [['DATA_RETENTION_MONTHS' => ''], 'DATA_RETENTION_MONTHS'],
            'no encryption' => [['SMTP_ENCRYPTION' => ''], 'SMTP_ENCRYPTION'],
            'no smtp login' => [['SMTP_PASSWORD' => ''], 'SMTP_USERNAME / SMTP_PASSWORD'],
            'no public details' => [['PUBLIC_PHONE' => '', 'WHATSAPP_NUMBER' => ''], 'Recapiti pubblici'],
        ];
        foreach ($cases as $name => [$override, $check]) {
            $results = $this->check($override);
            $this->assertLevel(ProductionCheck::WARN, $check, $results, $name);
            self::assertSame(0, ProductionCheck::count(array_values($results), ProductionCheck::FAIL), "$name must not block");
        }
    }

    public function testContentWarnings(): void
    {
        $this->db->exec('DELETE FROM seasonal_rates');
        $this->db->exec('DELETE FROM apartment_translations');
        $results = $this->check();

        $this->assertLevel(ProductionCheck::WARN, 'Listino', $results);
        $this->assertLevel(ProductionCheck::WARN, 'Descrizioni', $results);
        self::assertStringContainsString('6 appartamenti', $results['Descrizioni']['message']);
    }

    public function testDevelopmentLeftoversAndPhpunitInVendorAreWarnings(): void
    {
        mkdir($this->root . '/tests');
        mkdir($this->root . '/vendor/phpunit');
        $results = $this->check();

        $this->assertLevel(ProductionCheck::WARN, 'File di sviluppo', $results);
        self::assertStringContainsString('tests', $results['File di sviluppo']['message']);
        $this->assertLevel(ProductionCheck::WARN, 'vendor/', $results);
    }

    public function testEnvFileReadableByEveryoneIsAWarning(): void
    {
        if (DIRECTORY_SEPARATOR !== '/') {
            self::markTestSkipped('POSIX permissions only');
        }
        file_put_contents($this->root . '/.env', 'X=1');
        chmod($this->root . '/.env', 0644);
        $this->assertLevel(ProductionCheck::WARN, '.env', $this->check());

        chmod($this->root . '/.env', 0640);
        $this->assertLevel(ProductionCheck::OK, '.env', $this->check());
    }

    public function testCountsAddUp(): void
    {
        $results = array_values($this->check(['APP_ENV' => 'development', 'APP_SECRET' => '']));

        self::assertSame(1, ProductionCheck::count($results, ProductionCheck::FAIL));
        self::assertGreaterThanOrEqual(1, ProductionCheck::count($results, ProductionCheck::WARN));
        self::assertSame(count($results), ProductionCheck::count($results, ProductionCheck::OK) + ProductionCheck::count($results, ProductionCheck::WARN) + ProductionCheck::count($results, ProductionCheck::FAIL));
    }

    // === The command ============================================================

    /** @param array<string, string> $env @param list<string> $args @return array{int, string, string} */
    private function runCommand(array $env, array $args = []): array
    {
        $process = proc_open(
            [PHP_BINARY, dirname(__DIR__, 2) . '/bin/check-production.php', ...$args],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            null,
            array_merge(getenv(), ['DB_NAME' => getenv('TEST_DB_NAME') ?: 'agriturismo_test'], $env),
        );
        $out = (string) stream_get_contents($pipes[1]);
        $err = (string) stream_get_contents($pipes[2]);
        return [proc_close($process), $out, $err];
    }

    public function testTheCommandReportsAndSetsTheExitCode(): void
    {
        // Pointed at the test database (whose name ends in _test), so the command must refuse it.
        [$code, $out] = $this->runCommand(array_diff_key($this->goodEnv(), ['DB_NAME' => 1]));

        self::assertSame(1, $code);
        self::assertStringContainsString('[ERRORE]', $out);
        self::assertStringContainsString('database dei test', $out, 'the test database is recognised');
        self::assertStringContainsString('NON pronto', $out);
        self::assertStringNotContainsString(self::SECRET_VALUE, $out);
        $dbPassword = (string) getenv('DB_PASSWORD');
        if ($dbPassword !== '') {
            self::assertStringNotContainsString($dbPassword, $out, 'the database password is never printed');
        }

        [$devCode, $devOut] = $this->runCommand(['APP_ENV' => 'development']);
        self::assertSame(1, $devCode);
        self::assertStringContainsString('deve essere "production"', $devOut);
    }
}
