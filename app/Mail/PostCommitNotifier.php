<?php

declare(strict_types=1);

namespace App\Mail;

use App\Support\DeferredWork;
use App\Support\Logger;
use Throwable;

/**
 * The hook BookingService calls right after a transaction has committed, with the ids of the
 * messages that transaction queued. It sends them (plus a few overdue retries), either after the
 * response was closed (PHP-FPM) or inline. It can never throw.
 */
final class PostCommitNotifier
{
    public function __construct(private NotificationService $notifications, private Logger $logger, private bool $allowDeferral = true)
    {
    }

    /** @param list<int> $outboxIds */
    public function __invoke(array $outboxIds): void
    {
        $work = function () use ($outboxIds): void {
            $this->notifications->dispatchMany($outboxIds);
            $this->notifications->dispatchDue(2); // a couple of earlier failures get another chance
        };

        try {
            if ($this->allowDeferral && DeferredWork::available()) {
                DeferredWork::defer($work);
            } else {
                $work();
            }
        } catch (Throwable $e) {
            $this->logger->error('Post-commit notification failed', ['exception' => $e::class]);
        }
    }
}
