<?php

declare(strict_types=1);

namespace App\Mail;

use App\Repository\OutboxRepository;
use App\Support\Logger;
use PDO;
use Throwable;

/**
 * Sends the queued messages. It runs AFTER the business transaction has committed, so whatever
 * happens here (SMTP down, timeout, a bug in a template, even a PHP Error) can only change the
 * outbox row: bookings and requests are never touched, and no exception ever escapes dispatch().
 *
 * Delivery is at-least-once: a worker that dies after the server accepted the message but before
 * the row was marked "sent" will be retried after the lease expires, which may send a duplicate
 * rather than lose an e-mail.
 */
final class NotificationService
{
    public const MAX_ATTEMPTS = 4;
    /** Minutes to wait after the 1st, 2nd and 3rd failed attempt; after that only a manual retry. */
    public const BACKOFF_MINUTES = [5, 30, 120];

    private OutboxRepository $outbox;
    private MessageBuilder $builder;

    /** @param list<string> $secrets values that must never be stored or logged (e.g. the SMTP password) */
    public function __construct(
        PDO $db,
        private MailTransport $transport,
        MessageBuilder $builder,
        private Logger $logger,
        private array $secrets = [],
    ) {
        $this->outbox = new OutboxRepository($db);
        $this->builder = $builder;
    }

    /**
     * Tries to send one queued message.
     *
     * @return string sent | failed | skipped | busy (claimed by someone else or already done) | error (even recording failed)
     */
    public function dispatch(int $outboxId): string
    {
        try {
            if (!$this->outbox->claim($outboxId)) {
                return 'busy';
            }
            $row = $this->outbox->find($outboxId);
            if ($row === null) {
                return 'busy';
            }

            try {
                $message = $this->builder->build($row);
            } catch (NoRecipientException) {
                $this->outbox->markSkipped($outboxId, 'Nessun indirizzo email a cui scrivere.');
                return 'skipped';
            } catch (MailTransportException $e) {
                return $this->fail($outboxId, $row, $e->category, $e->getMessage(), $e->retryable);
            } catch (Throwable $e) {
                return $this->fail($outboxId, $row, 'render_error', 'Errore nella preparazione del messaggio (' . $e::class . ').', false);
            }

            try {
                $this->transport->send($message);
            } catch (MailTransportException $e) {
                return $this->fail($outboxId, $row, $e->category, $e->getMessage(), $e->retryable);
            } catch (Throwable $e) {
                // Anything else (a library bug, a PHP Error): same outcome, nothing else is affected.
                return $this->fail($outboxId, $row, MailTransportException::UNKNOWN, 'Errore imprevisto durante l\'invio (' . $e::class . ').', true);
            }

            $this->outbox->markSent($outboxId);
            $this->logger->info('Mail sent', ['outbox_id' => $outboxId, 'type' => $row['type']]);
            return 'sent';
        } catch (Throwable $e) {
            // Even recording the outcome failed (e.g. the database went away). The row stays
            // claimed; it becomes due again when the lease expires. Never rethrow.
            $this->logger->error('Mail dispatch could not record its outcome', ['outbox_id' => $outboxId, 'exception' => $e::class]);
            return 'error';
        }
    }

    /**
     * Sends a batch, skipping the rest when the time budget is used up.
     *
     * @param list<int> $ids
     * @return array<int, string> id => outcome
     */
    public function dispatchMany(array $ids, float $budgetSeconds = 25.0): array
    {
        $started = microtime(true);
        $outcomes = [];
        foreach ($ids as $id) {
            if (microtime(true) - $started > $budgetSeconds) {
                break;
            }
            $outcomes[$id] = $this->dispatch($id);
        }
        return $outcomes;
    }

    /**
     * Automatic retries for rows that are due. Used after commits and by bin/send-queued-mail.php.
     *
     * @return array<int, string> id => outcome
     */
    public function dispatchDue(int $limit = 3, float $budgetSeconds = 25.0): array
    {
        try {
            return $this->dispatchMany($this->outbox->dueIds($limit), $budgetSeconds);
        } catch (Throwable $e) {
            $this->logger->error('Mail queue could not be read', ['exception' => $e::class]);
            return [];
        }
    }

    /** Manual retry from the admin page. Same rules as an automatic attempt. */
    public function retry(int $outboxId): string
    {
        return $this->dispatch($outboxId);
    }

    /** @param array<string, mixed> $row */
    private function fail(int $id, array $row, string $code, string $message, bool $retryable): string
    {
        $attempt = (int) $row['attempts']; // the row was read after claim(), which already counted this attempt
        $retryIn = ($retryable && $attempt < self::MAX_ATTEMPTS) ? self::BACKOFF_MINUTES[min($attempt, count(self::BACKOFF_MINUTES)) - 1] : null;
        $safe = ErrorSanitizer::clean($message, $this->secrets);

        $this->outbox->markFailed($id, $code, $safe, $retryable, $retryIn);
        $this->logger->warning('Mail not delivered', [
            'outbox_id' => $id, 'type' => $row['type'], 'code' => $code, 'attempt' => $attempt, 'retry_in_minutes' => $retryIn,
        ]);
        return 'failed';
    }
}
