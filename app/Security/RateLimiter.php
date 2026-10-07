<?php

declare(strict_types=1);

namespace App\Security;

use PDO;

/**
 * Counts events per bucket and client in a sliding window, stored in the database
 * so it works on shared hosting without extra services. Client keys (e.g. IP
 * addresses) are stored only as keyed hashes (HMAC) and purged after a day.
 *
 * attempt() records the event FIRST and counts afterwards, so the current attempt counts at once:
 * with many requests arriving together, no more than the limit can ever be let through (a
 * "count, then record" sequence lets all of them see the same low count).
 */
final class RateLimiter
{
    private const RETENTION_SECONDS = 86400;

    public function __construct(private PDO $db)
    {
    }

    /**
     * Records one attempt and tells whether it is within the limit.
     *
     * @return bool true when this attempt may go on (at most $maxAttempts in the window), false when the limit is exceeded
     */
    public function attempt(string $bucket, string $clientKey, int $maxAttempts, int $windowSeconds): bool
    {
        $id = $this->record($bucket, $clientKey);

        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM rate_limit_hits
             WHERE bucket = ? AND key_hash = ? AND created_at > UTC_TIMESTAMP() - INTERVAL ? SECOND'
        );
        $stmt->execute([$bucket, self::hash($clientKey), $windowSeconds]);
        if ((int) $stmt->fetchColumn() <= $maxAttempts) {
            return true;
        }
        // A refused attempt is not kept: it must neither stretch the block nor fill the table while someone keeps trying.
        $this->db->prepare('DELETE FROM rate_limit_hits WHERE id = ?')->execute([$id]);
        return false;
    }

    /** Records an event without checking any limit. */
    public function hit(string $bucket, string $clientKey): void
    {
        $this->record($bucket, $clientKey);
    }

    private function record(string $bucket, string $clientKey): int
    {
        $this->db->prepare('INSERT INTO rate_limit_hits (bucket, key_hash) VALUES (?, ?)')
            ->execute([$bucket, self::hash($clientKey)]);
        $id = (int) $this->db->lastInsertId();

        if (random_int(1, 100) === 1) {
            $this->db->prepare('DELETE FROM rate_limit_hits WHERE created_at < UTC_TIMESTAMP() - INTERVAL ? SECOND')
                ->execute([self::RETENTION_SECONDS]);
        }
        return $id;
    }

    /** Events of a bucket from every client in the last $windowSeconds (at most 24 hours are kept). */
    public function countRecent(string $bucket, int $windowSeconds): int
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM rate_limit_hits WHERE bucket = ? AND created_at > UTC_TIMESTAMP() - INTERVAL ? SECOND'
        );
        $stmt->execute([$bucket, min($windowSeconds, self::RETENTION_SECONDS)]);
        return (int) $stmt->fetchColumn();
    }

    public function clear(string $bucket, string $clientKey): void
    {
        $this->db->prepare('DELETE FROM rate_limit_hits WHERE bucket = ? AND key_hash = ?')
            ->execute([$bucket, self::hash($clientKey)]);
    }

    private static function hash(string $clientKey): string
    {
        // Keyed hash: an unkeyed SHA-256 of an IP address can be reversed by trying every address.
        return hash_hmac('sha256', $clientKey, AppSecret::value());
    }
}
