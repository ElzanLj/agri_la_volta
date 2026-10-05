<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;

/** SQL for the e-mail outbox. Times are UTC. */
final class OutboxRepository
{
    /** A row claimed longer ago than this is considered abandoned (the worker died) and can be reclaimed. */
    public const LEASE_MINUTES = 5;

    public function __construct(private PDO $db)
    {
    }

    /**
     * Queues a message. Called INSIDE the business transaction: a plain INSERT that cannot depend
     * on SMTP, so the state change and its notification commit (or roll back) together.
     */
    public function enqueue(string $type, ?int $requestId, ?int $bookingId, string $locale, ?string $subject = null, ?string $body = null): int
    {
        $this->db->prepare(
            'INSERT INTO email_outbox (type, booking_request_id, booking_id, locale, subject, body) VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([$type, $requestId, $bookingId, $locale, $subject, $body]);
        return (int) $this->db->lastInsertId();
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM email_outbox WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Atomic compare-and-set: only one worker can move a row to "sending". This is what prevents
     * a double send when the page, the cron script and a manual retry run at the same time.
     */
    public function claim(int $id): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE email_outbox
             SET status = 'sending', locked_at = UTC_TIMESTAMP(), attempts = attempts + 1
             WHERE id = ? AND (status IN ('pending', 'failed')
                               OR (status = 'sending' AND locked_at < UTC_TIMESTAMP() - INTERVAL " . self::LEASE_MINUTES . ' MINUTE))'
        );
        $stmt->execute([$id]);
        return $stmt->rowCount() === 1;
    }

    public function markSent(int $id): void
    {
        $this->db->prepare(
            "UPDATE email_outbox SET status = 'sent', sent_at = UTC_TIMESTAMP(), error_code = NULL, error_message = NULL,
                    retryable = 1, next_attempt_at = NULL, locked_at = NULL, subject = NULL, body = NULL
             WHERE id = ?"
        )->execute([$id]);
    }

    /** @param int|null $retryInMinutes null = no automatic retry */
    public function markFailed(int $id, string $code, string $message, bool $retryable, ?int $retryInMinutes): void
    {
        $this->db->prepare(
            "UPDATE email_outbox
             SET status = 'failed', error_code = ?, error_message = ?, retryable = ?, locked_at = NULL,
                 next_attempt_at = " . ($retryInMinutes === null ? 'NULL' : 'UTC_TIMESTAMP() + INTERVAL ' . (int) $retryInMinutes . ' MINUTE') . '
             WHERE id = ?'
        )->execute([$code, mb_substr($message, 0, 255), (int) $retryable, $id]);
    }

    public function markSkipped(int $id, string $reason): void
    {
        $this->db->prepare(
            "UPDATE email_outbox SET status = 'skipped', error_code = 'no_recipient', error_message = ?, locked_at = NULL,
                    next_attempt_at = NULL, subject = NULL, body = NULL
             WHERE id = ?"
        )->execute([mb_substr($reason, 0, 255), $id]);
    }

    /** @return list<int> rows ready for an automatic attempt now */
    public function dueIds(int $limit): array
    {
        $stmt = $this->db->query(
            "SELECT id FROM email_outbox
             WHERE (status = 'pending' AND (next_attempt_at IS NULL OR next_attempt_at <= UTC_TIMESTAMP()))
                OR (status = 'failed' AND retryable = 1 AND next_attempt_at IS NOT NULL AND next_attempt_at <= UTC_TIMESTAMP())
                OR (status = 'sending' AND locked_at < UTC_TIMESTAMP() - INTERVAL " . self::LEASE_MINUTES . ' MINUTE)
             ORDER BY id LIMIT ' . max(1, $limit)
        );
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /** @return list<array<string, mixed>> */
    public function forRequest(int $requestId): array
    {
        return $this->rows('SELECT * FROM email_outbox WHERE booking_request_id = ? ORDER BY id', [$requestId]);
    }

    /** @return list<array<string, mixed>> */
    public function forBooking(int $bookingId): array
    {
        return $this->rows('SELECT * FROM email_outbox WHERE booking_id = ? ORDER BY id', [$bookingId]);
    }

    /** @return list<array<string, mixed>> */
    public function list(?string $status, int $limit, int $offset): array
    {
        return $this->rows(
            'SELECT * FROM email_outbox' . ($status === null ? '' : ' WHERE status = ?') . ' ORDER BY id DESC LIMIT ' . $limit . ' OFFSET ' . $offset,
            $status === null ? [] : [$status],
        );
    }

    public function count(?string $status): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM email_outbox' . ($status === null ? '' : ' WHERE status = ?'));
        $stmt->execute($status === null ? [] : [$status]);
        return (int) $stmt->fetchColumn();
    }

    /** @return array<string, int> status => number of rows */
    public function countsByStatus(): array
    {
        $rows = $this->db->query('SELECT status, COUNT(*) AS n FROM email_outbox GROUP BY status')->fetchAll();
        return array_map('intval', array_column($rows, 'n', 'status'));
    }

    /** @return list<array<string, mixed>> */
    private function rows(string $sql, array $params): array
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
