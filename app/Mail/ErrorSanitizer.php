<?php

declare(strict_types=1);

namespace App\Mail;

/**
 * Makes an error text safe to store and log: no e-mail addresses, no credentials, one short line.
 * Mail libraries often echo the recipient address or server banners in their messages.
 */
final class ErrorSanitizer
{
    private const MAX_LENGTH = 200;

    /** @param list<string> $secrets exact values that must never appear (e.g. the SMTP password) */
    public static function clean(string $text, array $secrets = []): string
    {
        foreach ($secrets as $secret) {
            if (strlen($secret) >= 3) {
                $text = str_replace($secret, '[hidden]', $text);
            }
        }
        $text = (string) preg_replace('/[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}/', '[email]', $text);
        // Long base64-looking tokens (AUTH exchanges, keys) and anything after "password"/"pass=".
        $text = (string) preg_replace('/\b[A-Za-z0-9+\/=]{24,}\b/', '[hidden]', $text);
        $text = (string) preg_replace('/(pass(?:word)?\s*[=:]\s*)\S+/i', '$1[hidden]', $text);
        $text = (string) preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $text);
        $text = trim((string) preg_replace('/\s+/', ' ', $text));

        return mb_strlen($text) > self::MAX_LENGTH ? mb_substr($text, 0, self::MAX_LENGTH - 1) . '…' : $text;
    }
}
