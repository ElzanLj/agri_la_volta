<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Database\Migrator;
use PHPUnit\Framework\TestCase;

final class MigratorTest extends TestCase
{
    public function testSplitsStatementsAtSemicolons(): void
    {
        self::assertSame(
            ['CREATE TABLE a (id INT)', 'INSERT INTO a VALUES (1)'],
            Migrator::splitStatements("CREATE TABLE a (id INT);\nINSERT INTO a VALUES (1);\n"),
        );
    }

    public function testASemicolonInsideTextDoesNotSplit(): void
    {
        $statements = Migrator::splitStatements("INSERT INTO t (v) VALUES ('uno; due;\ntre');\nSELECT 1;");
        self::assertCount(2, $statements);
        self::assertSame("INSERT INTO t (v) VALUES ('uno; due;\ntre')", $statements[0]);
    }

    public function testALineStartingWithDashesInsideTextIsKept(): void
    {
        $sql = "INSERT INTO t (v) VALUES ('prima\n-- non è un commento\ndopo');";
        self::assertSame(["INSERT INTO t (v) VALUES ('prima\n-- non è un commento\ndopo')"], Migrator::splitStatements($sql));
    }

    public function testQuotesInsideTextAreHandled(): void
    {
        $sql = "INSERT INTO t (a, b, c) VALUES ('l''altro; giorno', \"dice \\\"sì\\\"; ok\", 'a\\'b;c');SELECT 2;";
        $statements = Migrator::splitStatements($sql);
        self::assertCount(2, $statements);
        self::assertStringContainsString("'l''altro; giorno'", $statements[0]);
        self::assertStringContainsString("'a\\'b;c'", $statements[0]);
        self::assertSame('SELECT 2', $statements[1]);
    }

    public function testBackticksAreHandled(): void
    {
        self::assertCount(1, Migrator::splitStatements('SELECT `a;b` FROM t'));
    }

    public function testCommentsAreDroppedAndTheirTextIsIgnored(): void
    {
        $sql = "-- uno; due 'non chiuso\nSELECT 1; -- coda; con punto e virgola\n# altro; commento\n/* blocco; con ' apice */SELECT 2;";
        self::assertSame(['SELECT 1', 'SELECT 2'], Migrator::splitStatements($sql));
    }

    public function testDoubleDashWithoutASpaceIsNotAComment(): void
    {
        self::assertSame(['SELECT 5--3'], Migrator::splitStatements('SELECT 5--3;'));
    }

    public function testWindowsLineEndingsAreAccepted(): void
    {
        self::assertSame(['SELECT 1', 'SELECT 2'], Migrator::splitStatements("-- c\r\nSELECT 1;\r\nSELECT 2;\r\n"));
    }

    public function testEmptyInputGivesNoStatements(): void
    {
        self::assertSame([], Migrator::splitStatements("-- solo commento\n\n"));
    }

    public function testChecksumDoesNotDependOnLineEndings(): void
    {
        $dir = $this->tempDir();
        file_put_contents($dir . '/a.sql', "SELECT 1;\nSELECT 2;\n");
        file_put_contents($dir . '/b.sql', "SELECT 1;\r\nSELECT 2;\r\n");
        self::assertSame(Migrator::checksum($dir . '/a.sql'), Migrator::checksum($dir . '/b.sql'));
    }

    public function testReleasedMigrationsMatchTheirChecksums(): void
    {
        // Guardian: a migration that was released must never change; add a new file instead.
        self::assertSame([], Migrator::checksumProblems(dirname(__DIR__, 2) . '/migrations'));
    }

    public function testTheGuardianNoticesAChangedMigration(): void
    {
        $dir = $this->tempDir();
        file_put_contents($dir . '/0001_first.sql', "SELECT 1;\n");
        file_put_contents($dir . '/CHECKSUMS', Migrator::checksum($dir . '/0001_first.sql') . "  0001_first\n");
        self::assertSame([], Migrator::checksumProblems($dir));

        file_put_contents($dir . '/0001_first.sql', "SELECT 2;\n"); // deliberate violation
        $problems = Migrator::checksumProblems($dir);
        self::assertCount(1, $problems);
        self::assertStringContainsString('changed after it was released', $problems[0]);
    }

    public function testTheGuardianNoticesAnUnlistedMissingOrMisnamedMigration(): void
    {
        $dir = $this->tempDir();
        file_put_contents($dir . '/0001_first.sql', "SELECT 1;\n");
        file_put_contents($dir . '/CHECKSUMS', Migrator::checksum($dir . '/0001_first.sql') . "  0001_first\n" . str_repeat('a', 64) . "  0002_gone\n");
        file_put_contents($dir . '/0003_new.sql', "SELECT 3;\n");
        file_put_contents($dir . '/Fix Things.sql', "SELECT 4;\n");

        $text = implode("\n", Migrator::checksumProblems($dir));
        self::assertStringContainsString('0003_new is not listed', $text);
        self::assertStringContainsString('0002_gone is listed in CHECKSUMS but the file is missing', $text);
        self::assertStringContainsString('Fix Things.sql does not follow', $text);
    }

    public function testEveryMigrationHasTheNextNumber(): void
    {
        $numbers = [];
        foreach (glob(dirname(__DIR__, 2) . '/migrations/*.sql') ?: [] as $path) {
            self::assertMatchesRegularExpression(Migrator::FILE_PATTERN, basename($path));
            $numbers[] = (int) substr(basename($path), 0, 4);
        }
        sort($numbers);
        self::assertSame(range(1, count($numbers)), $numbers, 'migration numbers must be consecutive, without duplicates');
    }

    private function tempDir(): string
    {
        $dir = sys_get_temp_dir() . '/migrator-test-' . bin2hex(random_bytes(6));
        mkdir($dir);
        register_shutdown_function(static function () use ($dir): void {
            foreach (glob($dir . '/*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($dir);
        });
        return $dir;
    }
}
