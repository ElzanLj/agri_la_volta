<?php

declare(strict_types=1);

namespace App\Http\Admin;

use App\Security\Session;

/** One-shot messages shown on the next admin page (stored in the session). */
final class Flash
{
    public static function ok(string $message): void
    {
        self::add('ok', $message);
    }

    public static function error(string $message): void
    {
        self::add('error', $message);
    }

    /** @return list<array{type: string, message: string}> */
    public static function pull(): array
    {
        if (!Session::hasCookie()) {
            return [];
        }
        Session::start();
        $messages = is_array($_SESSION['_flash'] ?? null) ? $_SESSION['_flash'] : [];
        unset($_SESSION['_flash']);
        return $messages;
    }

    private static function add(string $type, string $message): void
    {
        Session::start();
        $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
    }
}
