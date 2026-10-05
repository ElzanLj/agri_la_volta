<?php

declare(strict_types=1);

/*
 * Shared bootstrap for the web front controller and the CLI scripts in bin/.
 * Returns the App instance.
 */

define('BASE_PATH', dirname(__DIR__));

// PSR-4 style autoloader: App\Foo\Bar -> app/Foo/Bar.php (no Composer required).
spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }
    $file = __DIR__ . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

// Third-party libraries (PHPMailer). In production the vendor/ folder is built with
// `composer install --no-dev` and uploaded; the application itself needs no Composer.
if (is_file(BASE_PATH . '/vendor/autoload.php')) {
    require BASE_PATH . '/vendor/autoload.php';
}

require __DIR__ . '/helpers.php';

$config = App\Config::load(BASE_PATH . '/.env');

date_default_timezone_set($config->string('APP_TIMEZONE', 'Europe/Rome'));
error_reporting(E_ALL);
ini_set('display_errors', $config->isDebug() ? '1' : '0');
ini_set('log_errors', '1');

set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

return new App\App($config, new App\Support\Logger(BASE_PATH . '/storage/logs'));
