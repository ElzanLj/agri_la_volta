<?php

declare(strict_types=1);

namespace App\Database;

use PDO;

/**
 * Applies the versioned SQL files in migrations/ in filename order, once each.
 *
 * Migrations are forward-only: MySQL/MariaDB commit DDL implicitly, so a failed
 * migration cannot be rolled back automatically. Take a backup before migrating
 * a database that holds real data.
 */
final class Migrator
{
    public function __construct(private PDO $db, private string $directory)
    {
    }

    /** @return array<string, bool> version => applied */
    public function status(): array
    {
        $applied = array_flip($this->applied());
        $status = [];
        foreach (array_keys($this->files()) as $version) {
            $status[$version] = isset($applied[$version]);
        }
        return $status;
    }

    /**
     * @param callable(string): void|null $onApply called before each migration
     * @return list<string> applied versions
     */
    public function migrate(?callable $onApply = null): array
    {
        $done = array_flip($this->applied());
        $ran = [];
        foreach ($this->files() as $version => $file) {
            if (isset($done[$version])) {
                continue;
            }
            if ($onApply !== null) {
                $onApply($version);
            }
            foreach (self::splitStatements((string) file_get_contents($file)) as $statement) {
                $this->db->exec($statement);
            }
            $this->db->prepare('INSERT IGNORE INTO schema_migrations (version) VALUES (?)')->execute([$version]);
            $ran[] = $version;
        }
        return $ran;
    }

    /**
     * Splits a migration file into statements. Statements end with ";" at the end
     * of a line; full-line "--" comments are ignored.
     *
     * @return list<string>
     */
    public static function splitStatements(string $sql): array
    {
        $lines = array_filter(
            preg_split('/\R/', $sql) ?: [],
            static fn (string $line): bool => !str_starts_with(ltrim($line), '--'),
        );
        $statements = preg_split('/;\s*$/m', implode("\n", $lines)) ?: [];
        return array_values(array_filter(array_map('trim', $statements), static fn (string $s): bool => $s !== ''));
    }

    /** @return list<string> */
    private function applied(): array
    {
        $this->db->exec(
            'CREATE TABLE IF NOT EXISTS schema_migrations (
                version VARCHAR(100) NOT NULL,
                applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (version)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
        return $this->db->query('SELECT version FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);
    }

    /** @return array<string, string> version => path, sorted */
    private function files(): array
    {
        $files = [];
        foreach (glob($this->directory . '/*.sql') ?: [] as $path) {
            $files[basename($path, '.sql')] = $path;
        }
        ksort($files, SORT_STRING);
        return $files;
    }
}
