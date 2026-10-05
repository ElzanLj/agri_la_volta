<?php

declare(strict_types=1);

namespace App\Security;

/** Synchronizer token stored in the session; one token per session. */
final class Csrf
{
    public static function token(): string
    {
        Session::start();
        if (!is_string($_SESSION['_csrf'] ?? null)) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf'];
    }

    public static function isValid(string $token): bool
    {
        Session::start();
        $expected = $_SESSION['_csrf'] ?? null;
        return is_string($expected) && $token !== '' && hash_equals($expected, $token);
    }
}
