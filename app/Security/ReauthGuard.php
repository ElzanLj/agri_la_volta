<?php

declare(strict_types=1);

namespace App\Security;

/**
 * "Enter your password again" for sensitive actions, reusable by every later phase.
 *
 * A confirmation is remembered in the session for five minutes. It ends with the session, with a password
 * change and with "close all devices" (AdminAuth::refreshCurrentSession), and it is never valid in another
 * session. Wrong passwords are counted by their own rate limit and also appear in the failed accesses.
 *
 * How a new sensitive action uses it (in its controller, before doing anything):
 *
 *     if ($redirect = $this->needsReauth('/admin/pagina-con-il-modulo')) {
 *         return $redirect;
 *     }
 *
 * The argument is the page where the action's form lives: after confirming, the person lands there (the
 * action itself is never replayed automatically). A form that already asks for the current password can
 * call confirm() directly instead.
 */
final class ReauthGuard
{
    public const VALID_SECONDS = 300;
    public const BUCKET = 'admin_reauth';
    private const MAX_ATTEMPTS = 5;
    private const WINDOW_SECONDS = 900;

    public const OK = 'ok';
    public const WRONG = 'wrong';
    public const LIMITED = 'limited';

    /** @var callable(): int */
    private $now;

    /** @param (callable(): int)|null $now injectable clock for tests */
    public function __construct(private AdminAuth $auth, private RateLimiter $limiter, ?callable $now = null)
    {
        $this->now = $now ?? static fn (): int => time();
    }

    /** True when the logged-in admin confirmed the password in the last five minutes, in THIS session. */
    public function isFresh(): bool
    {
        $at = $_SESSION['reauth_at'] ?? null;
        if (!is_int($at) || !isset($_SESSION['admin']['id'])) {
            return false;
        }
        $age = ($this->now)() - $at;
        return $age >= 0 && $age <= self::VALID_SECONDS;
    }

    /**
     * Checks the password and, if it is right, remembers the confirmation.
     *
     * @param string $client the client key of the rate limit (the connection address)
     * @return string self::OK, self::WRONG or self::LIMITED
     */
    public function confirm(string $password, string $client): string
    {
        if (!$this->limiter->attempt(self::BUCKET, $client, self::MAX_ATTEMPTS, self::WINDOW_SECONDS)) {
            return self::LIMITED;
        }
        if (!$this->auth->confirmPassword($password)) {
            $this->limiter->hit(AdminAuth::FAILED_BUCKET, $client);
            unset($_SESSION['reauth_at']);
            return self::WRONG;
        }
        $this->limiter->clear(self::BUCKET, $client);
        $_SESSION['reauth_at'] = ($this->now)();
        return self::OK;
    }

    public function clear(): void
    {
        unset($_SESSION['reauth_at']);
    }

    /**
     * An internal admin path that is safe to send the person back to, or "/admin". Only plain paths under
     * /admin are accepted: no scheme, no host, no "//", no "..", no query, no control characters.
     */
    public static function safeTarget(string $target): string
    {
        if (preg_match('#^/admin(?:/[A-Za-z0-9._~-]+)*/?$#', $target) !== 1 || str_contains($target, '..') || str_contains($target, '//')) {
            return '/admin';
        }
        return $target;
    }
}
