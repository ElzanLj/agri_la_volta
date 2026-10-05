<?php

declare(strict_types=1);

namespace App\Mail;

/**
 * Development transport: writes each message as a readable .eml file instead of sending it
 * (MAIL_TRANSPORT=log). The files are git-ignored and contain real message text: never use
 * this on a production site (the factory refuses it there).
 */
final class LogTransport implements MailTransport
{
    public function __construct(private string $directory)
    {
    }

    public function send(MailMessage $message): void
    {
        if (!is_dir($this->directory) && !@mkdir($this->directory, 0775, true) && !is_dir($this->directory)) {
            throw new MailTransportException(MailTransportException::NOT_CONFIGURED, 'Cartella dei messaggi di sviluppo non scrivibile.', false);
        }

        $lines = [
            'Date: ' . gmdate('D, d M Y H:i:s') . ' +0000',
            'To: ' . $message->to,
            $message->replyTo !== null ? 'Reply-To: ' . ($message->replyToName ? $message->replyToName . ' <' . $message->replyTo . '>' : $message->replyTo) : null,
            'Subject: ' . $message->subject,
            'Content-Type: text/plain; charset=UTF-8',
            '',
            $message->body,
        ];
        $file = sprintf('%s/%s-%s.eml', rtrim($this->directory, '/\\'), gmdate('Ymd-His'), bin2hex(random_bytes(4)));

        if (@file_put_contents($file, implode("\r\n", array_filter($lines, static fn (?string $l): bool => $l !== null))) === false) {
            throw new MailTransportException(MailTransportException::CONNECTION, 'Impossibile scrivere il messaggio di sviluppo.', true);
        }
    }
}
