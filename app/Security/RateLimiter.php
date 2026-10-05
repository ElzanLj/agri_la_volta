<?php

declare(strict_types=1);

namespace App\Security;

use PDO;

/**
 * Counts events per bucket and client in a sliding window, stored in the database
 * so it works on shared hosting without extra services. Client keys (e.g. IP
 * addresses) are stored only as hashes and purged after a day.
 */
final class RateLimiter
{
    private const RETENTION_SECONDS = 86400;

    public function __construct(private PDO $db)
    {
    }

    public function tooManyAttempts(string $bucket, string $clientKey, int $maxAttempts, int $windowSeconds): bool
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM rate_limit_hits
             WHERE bucket = ? AND key_hash = ? AND created_at > UTC_TIMESTAMP() - INTERVAL ? SECOND'
        );
        $stmt->execute([$bucket, self::hash($clientKey), $windowSeconds]);
        return (int) $stmt->fetchColumn() >= $maxAttempts;
    }

    public function hit(string $bucket, string $clientKey): void
    {
        $this->db->prepare('INSERT INTO rate_limit_hits (bucket, key_hash) VALUES (?, ?)')
            ->execute([$bucket, self::hash($clientKey)]);

        if (random_int(1, 100) === 1) {
            $this->db->prepare('DELETE FROM rate_limit_hits WHERE created_at < UTC_TIMESTAMP() - INTERVAL ? SECOND')
                ->execute([self::RETENTION_SECONDS]);
        }
    }

    public function clear(string $bucket, string $clientKey): void
    {
        $this->db->prepare('DELETE FROM rate_limit_hits WHERE bucket = ? AND key_hash = ?')
            ->execute([$bucket, self::hash($clientKey)]);
    }

    private static function hash(string $clientKey): string
    {
        return hash('sha256', $clientKey);
    }
}
