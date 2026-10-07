<?php

declare(strict_types=1);

/*
 * Runs the migrator against a scratch database in a separate process (concurrency tests).
 *
 *   php tests/Support/migrate-worker.php '<json>'
 *
 * JSON: {"database": "...", "directory": "...", "start_at": <unix time float>}
 * Prints one JSON line: {"status": "applied", "versions": [...]} or {"status": "locked"} / {"status": "error"}.
 */

require dirname(__DIR__, 2) . '/tests/bootstrap.php';

use App\App;
use App\Database\Migrator;

$job = json_decode($argv[1] ?? '', true, 512, JSON_THROW_ON_ERROR);
$config = App::current()->config;

$db = new PDO(
    sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $config->string('DB_HOST', 'localhost'), $config->int('DB_PORT', 3306), $job['database']),
    'root',
    $config->string('DB_ROOT_PASSWORD'),
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
);

while (microtime(true) < (float) $job['start_at']) {
    usleep(200);
}

try {
    $versions = (new Migrator($db, $job['directory']))->migrate();
    echo json_encode(['status' => 'applied', 'versions' => $versions]), "\n";
} catch (RuntimeException $e) {
    echo json_encode(['status' => str_contains($e->getMessage(), 'in progress') ? 'locked' : 'error', 'message' => $e->getMessage()]), "\n";
} catch (Throwable $e) {
    echo json_encode(['status' => 'error', 'class' => $e::class]), "\n";
}
