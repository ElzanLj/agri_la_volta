<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\App;
use App\Database\MigrationBlockedException;
use App\Database\Migrator;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * The migration itself, on a scratch database: it must refuse before changing anything when the
 * existing rows already break a rule, and go through once they are fixed.
 */
final class DataConstraintsMigrationTest extends TestCase
{
    private const DATABASE = 'agriturismo_constraints_test';

    private PDO $root;
    private PDO $scratch;
    private string $dir;

    protected function setUp(): void
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

        // A folder with the migrations before 0007; 0007 is added later.
        $this->dir = sys_get_temp_dir() . '/constraints-test-' . bin2hex(random_bytes(6));
        mkdir($this->dir);
        foreach (glob(dirname(__DIR__, 2) . '/migrations/*.sql') ?: [] as $path) {
            if (basename($path) !== '0007_data_constraints.sql') {
                copy($path, $this->dir . '/' . basename($path));
            }
        }
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

    private function constraintCount(): int
    {
        return (int) $this->scratch->query(
            "SELECT COUNT(*) FROM information_schema.table_constraints WHERE constraint_schema = '" . self::DATABASE . "' AND constraint_name IN
             ('chk_bookings_cancelled_at', 'chk_bookings_guests', 'chk_booking_requests_decided_at', 'chk_booking_requests_guests', 'chk_email_outbox_sent_at')"
        )->fetchColumn();
    }

    public function testExistingRowsThatBreakARuleStopTheMigrationBeforeAnythingChanges(): void
    {
        (new Migrator($this->scratch, $this->dir))->migrate();
        copy(dirname(__DIR__, 2) . '/migrations/0007_data_constraints.sql', $this->dir . '/0007_data_constraints.sql');

        // Data an older version could have left behind: a cancelled booking without a date, a sent e-mail without a date.
        $this->scratch->exec("INSERT INTO bookings (apartment_id, origin, status, check_in, check_out, adults, guest_name) VALUES (1, 'phone', 'cancelled', '2027-06-10', '2027-06-15', 2, 'Prova')");
        $this->scratch->exec("INSERT INTO email_outbox (type, status) VALUES ('new_request_admin', 'sent')");

        try {
            (new Migrator($this->scratch, $this->dir))->migrate();
            self::fail('The migration should have been refused.');
        } catch (MigrationBlockedException $e) {
            self::assertSame('0007_data_constraints', $e->version);
            self::assertCount(2, $e->problems);
            self::assertStringContainsString('1 prenotazioni cancellate senza data', $e->getMessage());
            self::assertStringContainsString('1 email segnate come inviate senza data di invio', $e->getMessage());
            self::assertStringContainsString('Non è stato modificato nulla', $e->getMessage());
        }
        self::assertSame(0, $this->constraintCount(), 'not a single constraint was added');
        self::assertSame(['0007_data_constraints' => false], array_slice((new Migrator($this->scratch, $this->dir))->status(), -1, 1, true));

        // Once the data is fixed the same migration goes through.
        $this->scratch->exec("UPDATE bookings SET cancelled_at = UTC_TIMESTAMP() WHERE status = 'cancelled'");
        $this->scratch->exec("UPDATE email_outbox SET sent_at = UTC_TIMESTAMP() WHERE status = 'sent'");
        self::assertSame(['0007_data_constraints'], (new Migrator($this->scratch, $this->dir))->migrate());
        self::assertSame(5, $this->constraintCount());
    }

    public function testACleanDatabaseMigratesStraightThrough(): void
    {
        (new Migrator($this->scratch, $this->dir))->migrate();
        copy(dirname(__DIR__, 2) . '/migrations/0007_data_constraints.sql', $this->dir . '/0007_data_constraints.sql');

        self::assertSame(['0007_data_constraints'], (new Migrator($this->scratch, $this->dir))->migrate());
        self::assertSame(5, $this->constraintCount());
    }
}
