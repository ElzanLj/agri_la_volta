<?php

declare(strict_types=1);

/*
 * Appends to migrations/CHECKSUMS the hash of every migration that is not listed yet.
 * Existing entries are never rewritten: a released migration must not change.
 *
 *   php bin/migration-checksums.php
 *
 * It exits with an error when a listed migration no longer matches its hash.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/app/Database/Migrator.php';

use App\Database\Migrator;

$directory = dirname(__DIR__) . '/migrations';
$file = $directory . '/CHECKSUMS';
$recorded = is_file($file) ? Migrator::recordedChecksums($file) : [];

$added = 0;
$lines = '';
foreach (glob($directory . '/*.sql') ?: [] as $path) {
    $version = basename($path, '.sql');
    if (preg_match(Migrator::FILE_PATTERN, basename($path)) !== 1) {
        continue;
    }
    $hash = Migrator::checksum($path);
    if (isset($recorded[$version])) {
        if ($recorded[$version] !== $hash) {
            fwrite(STDERR, "ERROR: {$version} changed after it was released. Restore the file and add a new migration instead.\n");
            exit(1);
        }
        continue;
    }
    $lines .= "{$hash}  {$version}\n";
    $added++;
}

if ($lines !== '') {
    file_put_contents($file, $lines, FILE_APPEND);
}
echo $added === 0 ? "CHECKSUMS is up to date.\n" : "Added {$added} entr" . ($added === 1 ? 'y' : 'ies') . " to migrations/CHECKSUMS.\n";
