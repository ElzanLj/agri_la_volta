<?php

declare(strict_types=1);

/*
 * Applies pending SQL migrations.
 *
 *   php bin/migrate.php           apply pending migrations
 *   php bin/migrate.php --status  list migrations and whether they are applied
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

/** @var App\App $app */
$app = require dirname(__DIR__) . '/app/bootstrap.php';

$migrator = new App\Database\Migrator($app->db(), BASE_PATH . '/migrations');

if (in_array('--status', $argv, true)) {
    foreach ($migrator->status() as $version => $applied) {
        printf("%s  %s\n", $applied ? '[x]' : '[ ]', $version);
    }
    exit(0);
}

try {
    $ran = $migrator->migrate(static function (string $version): void {
        echo "Applying {$version}\n";
    });
} catch (Throwable $e) {
    fwrite(STDERR, 'Migration failed: ' . $e->getMessage() . "\n");
    fwrite(STDERR, "DDL is committed statement by statement: check the database state before retrying.\n");
    exit(1);
}

echo $ran === [] ? "Nothing to migrate.\n" : sprintf("Applied %d migration(s).\n", count($ran));
