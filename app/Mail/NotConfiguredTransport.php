<?php

declare(strict_types=1);

namespace App\Mail;

/** Used when mail is misconfigured: every send fails clearly and the queue keeps the messages. */
final class NotConfiguredTransport implements MailTransport
{
    public function __construct(private string $reason)
    {
    }

    public function send(MailMessage $message): void
    {
        throw new MailTransportException(MailTransportException::NOT_CONFIGURED, $this->reason, false);
    }
}
