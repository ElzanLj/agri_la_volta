<?php

declare(strict_types=1);

namespace App\Security;

use App\App;

/**
 * The server-side secret behind every keyed hash of the application (public form tokens,
 * rate-limit client keys). APP_SECRET when set; otherwise derived from the database credentials,
 * which never leave the server. Set APP_SECRET to a long random value in production.
 */
final class AppSecret
{
    public static function value(): string
    {
        $config = App::current()->config;
        $secret = $config->string('APP_SECRET');
        if ($secret !== '') {
            return $secret;
        }
        return hash('sha256', 'lavolta-app-secret|' . $config->string('DB_NAME') . '|' . $config->string('DB_USER') . '|' . $config->string('DB_PASSWORD'));
    }
}
