<?php

declare(strict_types=1);

namespace App\Mail;

use RuntimeException;

/** A delivery failure with a machine-readable category and a retry hint. */
final class MailTransportException extends RuntimeException
{
    /** Misconfiguration (no SMTP host, ...): fix the settings, then retry manually. */
    public const NOT_CONFIGURED = 'not_configured';
    /** Cannot reach the server (refused, unreachable, dropped): worth retrying. */
    public const CONNECTION = 'connection';
    /** The server did not answer in time: worth retrying. */
    public const TIMEOUT = 'timeout';
    /** 4xx answer: the server asked to try again later. */
    public const TEMPORARY = 'temporary_rejection';
    /** 5xx answer for the recipient (mailbox unknown, ...): retrying will not help. */
    public const RECIPIENT_REJECTED = 'recipient_rejected';
    /** Wrong credentials: retrying will not help until they are fixed. */
    public const AUTH_FAILED = 'auth_failed';
    /** Any other permanent 5xx answer. */
    public const REJECTED = 'rejected';
    /** Anything not understood: retried a few times. */
    public const UNKNOWN = 'unknown';

    public function __construct(
        public readonly string $category,
        string $safeMessage,
        public readonly bool $retryable,
    ) {
        parent::__construct($safeMessage);
    }
}
