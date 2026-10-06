<?php

declare(strict_types=1);

namespace App\Site;

use App\Security\AppSecret;

/**
 * CSRF token for the public forms that does NOT need a session (so anonymous visitors never
 * receive a cookie): "<issued-at>.<HMAC-SHA256(purpose|issued-at, secret)>".
 *
 * It proves the form was served by this site (a forged cross-site POST cannot compute it) and
 * carries the time it was issued, which gives a cheap "submitted too fast" bot check and an expiry.
 * It is not single-use: replays are bounded by the rate limit and by server-side validation.
 */
final class FormToken
{
    public const MAX_AGE_SECONDS = 7200;

    public static function issue(string $purpose, ?int $now = null): string
    {
        $issuedAt = (string) ($now ?? time());
        return $issuedAt . '.' . self::sign($purpose, $issuedAt);
    }

    /** @return 'ok'|'invalid'|'expired'|'too_fast' */
    public static function check(string $token, string $purpose, int $minAgeSeconds = 0, ?int $now = null): string
    {
        if (!preg_match('/^(\d{9,12})\.([0-9a-f]{64})\z/', $token, $m)) {
            return 'invalid';
        }
        if (!hash_equals(self::sign($purpose, $m[1]), $m[2])) {
            return 'invalid';
        }
        $age = ($now ?? time()) - (int) $m[1];
        if ($age < 0 || $age > self::MAX_AGE_SECONDS) {
            return 'expired';
        }
        return $age < $minAgeSeconds ? 'too_fast' : 'ok';
    }

    private static function sign(string $purpose, string $issuedAt): string
    {
        return hash_hmac('sha256', $purpose . '|' . $issuedAt, AppSecret::value());
    }
}
