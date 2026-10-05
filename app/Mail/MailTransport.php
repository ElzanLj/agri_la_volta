<?php

declare(strict_types=1);

namespace App\Mail;

/**
 * How a message leaves the application. The business code never talks to SMTP: it only queues
 * messages (email_outbox); NotificationService hands them to a transport after the commit.
 */
interface MailTransport
{
    /** @throws MailTransportException when the message could not be handed over */
    public function send(MailMessage $message): void;
}
