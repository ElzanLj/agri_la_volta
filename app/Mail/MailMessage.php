<?php

declare(strict_types=1);

namespace App\Mail;

/** A plain-text e-mail ready to be handed to a transport. */
final class MailMessage
{
    public readonly string $to;
    public readonly string $subject;
    public readonly ?string $replyTo;
    public readonly ?string $replyToName;

    public function __construct(
        string $to,
        string $subject,
        public readonly string $body,
        ?string $replyTo = null,
        ?string $replyToName = null,
    ) {
        // Header injection: line breaks never survive in an address, a name or a subject.
        $this->to = self::oneLine($to);
        $this->subject = self::oneLine($subject);
        $this->replyTo = $replyTo === null ? null : self::oneLine($replyTo);
        $this->replyToName = $replyToName === null ? null : self::oneLine($replyToName);
    }

    public static function oneLine(string $value): string
    {
        return trim((string) preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value));
    }
}
