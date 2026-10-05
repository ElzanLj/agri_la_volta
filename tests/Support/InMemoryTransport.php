<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Mail\MailMessage;
use App\Mail\MailTransport;

/**
 * Test transport: records messages in memory. `$behaviour` runs on every send and may throw
 * (to simulate any failure) or inspect the world at the exact moment of sending.
 */
final class InMemoryTransport implements MailTransport
{
    /** @var list<MailMessage> */
    public array $sent = [];
    public int $attempts = 0;
    /** @var (callable(MailMessage): void)|null */
    public $behaviour = null;

    /** @param (callable(MailMessage): void)|null $behaviour */
    public function __construct(?callable $behaviour = null)
    {
        $this->behaviour = $behaviour;
    }

    public function send(MailMessage $message): void
    {
        $this->attempts++;
        if ($this->behaviour !== null) {
            ($this->behaviour)($message);
        }
        $this->sent[] = $message;
    }
}
