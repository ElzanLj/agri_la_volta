<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\App;
use App\Database\Migrator;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * The migrator against a scratch database (never the application database).
 */
final class MigratorDatabaseTest extends TestCase
{
    private const DATABASE = 'agriturismo_migrator_test';

    private PDO $root;
    private PDO $scratch;
    private string $dir;

    protected function setUp(): void
    {
        $config = App::current()->config;
        $rootPassword = $config->string('DB_ROOT_PASSWORD');
        if ($rootPassword === '') {
            self::markTestSkipped('DB_ROOT_PASSWORD is not configured.');
        }
        $this->root = new PDO(
            sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $config->string('DB_HOST', 'localhost'), $config->int('DB_PORT', 3306)),
            'root',
            $rootPassword,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
        );
        $this->root->exec('DROP DATABASE IF EXISTS `' . self::DATABASE . '`');
        $this->root->exec('CREATE DATABASE `' . self::DATABASE . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $this->scratch = $this->connect();

        $this->dir = sys_get_temp_dir() . '/migrator-db-test-' . bin2hex(random_bytes(6));
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        if (isset($this->root)) {
            $this->root->exec('DROP DATABASE IF EXISTS `' . self::DATABASE . '`');
        }
        foreach (glob($this->dir . '/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->dir);
    }

    public function testTextWithSemicolonsDashesAndQuotesIsStoredUnchanged(): void
    {
        $text = "Prima riga; seconda riga\n-- non è un commento\nl'altro; \"citazione\" e \\ backslash";
        file_put_contents($this->dir . '/0001_text.sql', "-- intestazione; con punto e virgola\n"
            . "CREATE TABLE notes (id INT PRIMARY KEY, body TEXT);\n"
            . 'INSERT INTO notes (id, body) VALUES (1, ' . $this->scratch->quote($text) . ");\n"
            . "INSERT INTO notes (id, body) VALUES (2, 'a;\n-- b;\nc');\n");

        $ran = (new Migrator($this->scratch, $this->dir))->migrate();

        self::assertSame(['0001_text'], $ran);
        self::assertSame($text, $this->scratch->query('SELECT body FROM notes WHERE id = 1')->fetchColumn());
        self::assertSame("a;\n-- b;\nc", $this->scratch->query('SELECT body FROM notes WHERE id = 2')->fetchColumn());
    }

    public function testFilesThatAreNotNumberedMigrationsAreIgnored(): void
    {
        file_put_contents($this->dir . '/0001_ok.sql', "CREATE TABLE ok_table (id INT);\n");
        file_put_contents($this->dir . '/notes.sql', "CREATE TABLE should_not_exist (id INT);\n");
        file_put_contents($this->dir . '/01_short.sql', "CREATE TABLE should_not_exist_2 (id INT);\n");

        $ran = (new Migrator($this->scratch, $this->dir))->migrate();

        self::assertSame(['0001_ok'], $ran);
        self::assertSame(0, $this->tableCount('should_not_exist%'));
    }

    public function testRunningTwiceAppliesOnce(): void
    {
        file_put_contents($this->dir . '/0001_once.sql', "CREATE TABLE once_table (id INT);\n");
        $migrator = new Migrator($this->scratch, $this->dir);

        self::assertSame(['0001_once'], $migrator->migrate());
        self::assertSame([], $migrator->migrate());
    }

    public function testAMigrationIsRefusedWhileAnotherRunHoldsTheLock(): void
    {
        file_put_contents($this->dir . '/0001_locked.sql', "CREATE TABLE locked_table (id INT);\n");
        $other = $this->connect();
        $other->prepare('SELECT GET_LOCK(?, 0)')->execute([Migrator::LOCK_NAME]);

        try {
            (new Migrator($this->scratch, $this->dir))->migrate();
            self::fail('The migration should have been refused.');
        } catch (RuntimeException $e) {
            self::assertStringContainsString('in progress', $e->getMessage());
        }
        self::assertSame(0, $this->tableCount('locked_table'));

        $other->prepare('SELECT RELEASE_LOCK(?)')->execute([Migrator::LOCK_NAME]);
        self::assertSame(['0001_locked'], (new Migrator($this->scratch, $this->dir))->migrate());
    }

    public function testTheLockIsReleasedAfterAFailedMigration(): void
    {
        file_put_contents($this->dir . '/0001_broken.sql', "CREATE TABLE half (id INT);\nTHIS IS NOT SQL;\n");
        try {
            (new Migrator($this->scratch, $this->dir))->migrate();
            self::fail('The broken migration should have failed.');
        } catch (\PDOException) {
            // expected: the first statement stays applied, the version is not recorded
        }
        self::assertSame(['0001_broken' => false], (new Migrator($this->scratch, $this->dir))->status());

        $free = $this->connect()->query('SELECT IS_FREE_LOCK(' . $this->scratch->quote(Migrator::LOCK_NAME) . ')')->fetchColumn();
        self::assertSame(1, (int) $free);
    }

    public function testThreeMigrationsStartedAtTheSameTimeApplyOnlyOnce(): void
    {
        file_put_contents($this->dir . '/0001_slow.sql', "CREATE TABLE slow_table (id INT);\nSELECT SLEEP(1);\nINSERT INTO slow_table (id) VALUES (1);\n");

        $startAt = microtime(true) + 1.0;
        $processes = [];
        foreach ([0, 1, 2] as $i) {
            $job = json_encode(['database' => self::DATABASE, 'directory' => $this->dir, 'start_at' => $startAt], JSON_THROW_ON_ERROR);
            $process = proc_open([PHP_BINARY, dirname(__DIR__) . '/Support/migrate-worker.php', $job], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            self::assertIsResource($process);
            $processes[$i] = [$process, $pipes];
        }

        $results = [];
        foreach ($processes as $i => [$process, $pipes]) {
            $stdout = stream_get_contents($pipes[1]);
            $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($process);
            $decoded = json_decode(trim((string) $stdout), true);
            self::assertIsArray($decoded, "worker $i output: [$stdout] [$stderr]");
            $results[] = $decoded;
        }

        $applied = array_filter($results, static fn (array $r): bool => $r['status'] === 'applied' && $r['versions'] !== []);
        self::assertCount(1, $applied, 'exactly one run applies the migration: ' . json_encode($results));
        foreach ($results as $result) {
            self::assertContains($result['status'], ['applied', 'locked'], 'no run may fail otherwise: ' . json_encode($results));
        }
        self::assertSame(1, (int) $this->scratch->query('SELECT COUNT(*) FROM slow_table')->fetchColumn());
    }

    private function tableCount(string $like): int
    {
        $stmt = $this->scratch->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = ? AND table_name LIKE ?');
        $stmt->execute([self::DATABASE, $like]);
        return (int) $stmt->fetchColumn();
    }

    private function connect(): PDO
    {
        $config = App::current()->config;
        return new PDO(
            sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $config->string('DB_HOST', 'localhost'), $config->int('DB_PORT', 3306), self::DATABASE),
            'root',
            $config->string('DB_ROOT_PASSWORD'),
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
        );
    }
}
