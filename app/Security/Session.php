<?php

declare(strict_types=1);

namespace App\Security;

use App\App;

/**
 * Native PHP sessions with hardened cookie settings. Sessions start only when a
 * page needs one (admin, forms), so plain public pages set no cookies.
 */
final class Session
{
    private const NAME = 'lavolta_session';
    private const MAX_LIFETIME = 43200;

    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        $app = App::current();

        // Private directory instead of the host's shared temp dir; it needs its own GC.
        $savePath = BASE_PATH . '/storage/sessions';
        if (is_dir($savePath) && is_writable($savePath)) {
            session_save_path($savePath);
            ini_set('session.gc_probability', '1');
            ini_set('session.gc_divisor', '100');
        }
        ini_set('session.gc_maxlifetime', (string) self::MAX_LIFETIME);
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_trans_sid', '0');

        session_name(self::NAME);
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => $app->basePath() === '' ? '/' : $app->basePath() . '/',
            'secure' => $app->isHttps(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }

    /** Issues a new session id; call after a privilege change such as login. */
    public static function regenerate(): void
    {
        self::start();
        session_regenerate_id(true);
    }

    public static function destroy(): void
    {
        self::start();
        $_SESSION = [];
        $params = session_get_cookie_params();
        setcookie(self::NAME, '', [
            'expires' => time() - 3600,
            'path' => $params['path'],
            'secure' => $params['secure'],
            'httponly' => true,
            'samesite' => $params['samesite'],
        ]);
        session_destroy();
    }
}
