<?php

declare(strict_types=1);

namespace App\Database;

use PDO;
use RuntimeException;

/**
 * Applies the versioned SQL files in migrations/ in filename order, once each.
 *
 * Migrations are forward-only: MySQL/MariaDB commit DDL implicitly, so a failed
 * migration cannot be rolled back automatically. Take a backup before migrating
 * a database that holds real data. If a file fails halfway, its earlier statements
 * stay applied and the version is NOT recorded: see docs/COMMANDS.md ("Migrazione
 * interrotta a metà") before running it again.
 *
 * Only files named NNNN_name.sql (four digits, lowercase name) are migrations; any other
 * file in the folder is ignored. migrations/CHECKSUMS lists the hash of every released
 * migration, and a test fails if one of them changes after release.
 */
final class Migrator
{
    public const LOCK_NAME = 'agri_la_volta_migrate';
    public const FILE_PATTERN = '/^\d{4}_[a-z0-9_]+\.sql$/';

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
     * Applies the pending migrations. Two runs at the same time are refused: the second one
     * fails at once instead of repeating half-applied statements.
     *
     * @param callable(string): void|null $onApply called before each migration
     * @return list<string> applied versions
     * @throws RuntimeException when another migration run holds the lock
     */
    public function migrate(?callable $onApply = null): array
    {
        $this->acquireLock();
        try {
            $done = array_flip($this->applied());
            $ran = [];
            foreach ($this->files() as $version => $file) {
                if (isset($done[$version])) {
                    continue;
                }
                // Data that would break the migration is reported BEFORE any statement runs.
                $problems = MigrationPreconditions::problems($version, $this->db);
                if ($problems !== []) {
                    throw new MigrationBlockedException($version, $problems);
                }
                if ($onApply !== null) {
                    $onApply($version);
                }
                foreach (self::splitStatements((string) file_get_contents($file)) as $statement) {
                    // query() + closeCursor(): a statement that returns rows (e.g. a SELECT) must not
                    // leave an open result that breaks the next statement.
                    $result = $this->db->query($statement);
                    if ($result !== false) {
                        $result->closeCursor();
                    }
                }
                $this->db->prepare('INSERT IGNORE INTO schema_migrations (version) VALUES (?)')->execute([$version]);
                $ran[] = $version;
            }
            return $ran;
        } finally {
            $this->releaseLock();
        }
    }

    /**
     * Splits a migration file into statements. A statement ends at a ";" that is outside
     * quoted text, backtick names and comments, so a ";" or a line starting with "--" inside a
     * string is part of the text. Comments ("-- ...", "# ..." and block comments) are dropped.
     *
     * @return list<string>
     */
    public static function splitStatements(string $sql): array
    {
        $statements = [];
        $current = '';
        $length = strlen($sql);
        $i = 0;
        while ($i < $length) {
            $char = $sql[$i];
            $next = $sql[$i + 1] ?? '';

            if ($char === "'" || $char === '"' || $char === '`') {
                $end = self::quotedEnd($sql, $i, $length);
                $current .= substr($sql, $i, $end - $i);
                $i = $end;
                continue;
            }
            if (($char === '-' && $next === '-' && self::isCommentSpace($sql[$i + 2] ?? "\n")) || $char === '#') {
                $newline = strpos($sql, "\n", $i);
                $i = $newline === false ? $length : $newline;
                continue;
            }
            if ($char === '/' && $next === '*') {
                $close = strpos($sql, '*/', $i + 2);
                $i = $close === false ? $length : $close + 2;
                $current .= ' ';
                continue;
            }
            if ($char === ';') {
                $statements[] = $current;
                $current = '';
                $i++;
                continue;
            }
            $current .= $char;
            $i++;
        }
        $statements[] = $current;

        return array_values(array_filter(
            array_map('trim', $statements),
            static fn (string $statement): bool => $statement !== '',
        ));
    }

    /** Hash of a migration file, independent of the line endings used by the checkout. */
    public static function checksum(string $path): string
    {
        return hash('sha256', str_replace("\r\n", "\n", (string) file_get_contents($path)));
    }

    /** @return array<string, string> version => sha256 of every migration file, sorted */
    public function checksums(): array
    {
        $checksums = [];
        foreach ($this->files() as $version => $path) {
            $checksums[$version] = self::checksum($path);
        }
        return $checksums;
    }

    /**
     * Reads migrations/CHECKSUMS ("<sha256>  <version>" per line, "#" comments).
     *
     * @return array<string, string> version => sha256
     */
    public static function recordedChecksums(string $file): array
    {
        $recorded = [];
        foreach (file($file, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }
            if (preg_match('/^([0-9a-f]{64})\s+(\S+)$/', $line, $m) === 1) {
                $recorded[$m[2]] = $m[1];
            }
        }
        return $recorded;
    }

    /**
     * Compares the migration files of a folder with its CHECKSUMS file.
     *
     * @return list<string> problems found (empty when every released migration is unchanged)
     */
    public static function checksumProblems(string $directory): array
    {
        $file = $directory . '/CHECKSUMS';
        if (!is_file($file)) {
            return ['CHECKSUMS is missing'];
        }
        $recorded = self::recordedChecksums($file);
        $problems = [];
        $seen = [];
        foreach (glob($directory . '/*.sql') ?: [] as $path) {
            $name = basename($path);
            if (preg_match(self::FILE_PATTERN, $name) !== 1) {
                $problems[] = "{$name} does not follow the NNNN_name.sql pattern";
                continue;
            }
            $version = basename($name, '.sql');
            $seen[$version] = true;
            if (!isset($recorded[$version])) {
                $problems[] = "{$version} is not listed in CHECKSUMS (run php bin/migration-checksums.php)";
            } elseif ($recorded[$version] !== self::checksum($path)) {
                $problems[] = "{$version} changed after it was released: restore it and add a new migration";
            }
        }
        foreach (array_keys($recorded) as $version) {
            if (!isset($seen[$version])) {
                $problems[] = "{$version} is listed in CHECKSUMS but the file is missing";
            }
        }
        return $problems;
    }

    /** Index just after the closing quote of the quoted text that starts at $start. */
    private static function quotedEnd(string $sql, int $start, int $length): int
    {
        $quote = $sql[$start];
        $i = $start + 1;
        while ($i < $length) {
            $char = $sql[$i];
            if ($char === '\\' && $quote !== '`') {
                $i += 2; // backslash escape inside a string
                continue;
            }
            if ($char === $quote) {
                if (($sql[$i + 1] ?? '') === $quote) {
                    $i += 2; // doubled quote
                    continue;
                }
                return $i + 1;
            }
            $i++;
        }
        return $length; // unterminated text: the database will report the error
    }

    private static function isCommentSpace(string $char): bool
    {
        return $char === ' ' || $char === "\t" || $char === "\n" || $char === "\r";
    }

    private function acquireLock(): void
    {
        $stmt = $this->db->prepare('SELECT GET_LOCK(?, 0)');
        $stmt->execute([self::LOCK_NAME]);
        if ((int) $stmt->fetchColumn() !== 1) {
            throw new RuntimeException('Another migration run is in progress: wait for it to finish, then retry.');
        }
    }

    private function releaseLock(): void
    {
        $stmt = $this->db->prepare('SELECT RELEASE_LOCK(?)');
        $stmt->execute([self::LOCK_NAME]);
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
            if (preg_match(self::FILE_PATTERN, basename($path)) !== 1) {
                continue;
            }
            $files[basename($path, '.sql')] = $path;
        }
        ksort($files, SORT_STRING);
        return $files;
    }
}
