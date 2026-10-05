<?php

declare(strict_types=1);

/*
 * Test bootstrap. Tests always run against a dedicated database whose name ends
 * with "_test"; they never touch the development or production database.
 */

require dirname(__DIR__) . '/vendor/autoload.php';

putenv('APP_ENV=development');
putenv('DB_NAME=' . (getenv('TEST_DB_NAME') ?: 'agriturismo_test'));

/** @var App\App $app */
$app = require dirname(__DIR__) . '/app/bootstrap.php';

if (!str_ends_with($app->config->string('DB_NAME'), '_test')) {
    fwrite(STDERR, "Refusing to run: DB_NAME must end with _test.\n");
    exit(1);
}
