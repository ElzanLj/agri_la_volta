<?php

declare(strict_types=1);

/*
 * Creates (if needed) the test database, grants the application user access to it
 * and applies all migrations. Safe to run repeatedly. Uses DB_ROOT_PASSWORD from .env.
 *
 *   php tests/prepare-db.php        (also run by `composer test`)
 */

if (PHP_SAPI !== 'cli') {
    exit(1);
}

putenv('APP_ENV=development');
putenv('DB_NAME=' . (getenv('TEST_DB_NAME') ?: 'agriturismo_test'));

/** @var App\App $app */
$app = require dirname(__DIR__) . '/app/bootstrap.php';
$config = $app->config;

$database = $config->string('DB_NAME');
$user = $config->string('DB_USER');
if (!str_ends_with($database, '_test') || !preg_match('/^[A-Za-z0-9_]+$/', $database) || !preg_match('/^[A-Za-z0-9_]+$/', $user)) {
    fwrite(STDERR, "Refusing to continue: unsafe database or user name.\n");
    exit(1);
}

$root = new PDO(
    sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $config->string('DB_HOST', 'localhost'), $config->int('DB_PORT', 3306)),
    'root',
    $config->string('DB_ROOT_PASSWORD'),
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
);
$root->exec("CREATE DATABASE IF NOT EXISTS `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$root->exec("GRANT ALL PRIVILEGES ON `{$database}`.* TO '{$user}'@'%'");

$ran = (new App\Database\Migrator($app->db(), BASE_PATH . '/migrations'))->migrate();
echo sprintf("Test database '%s' ready (%d migration(s) applied).\n", $database, count($ran));
