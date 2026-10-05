<?php

declare(strict_types=1);

/*
 * Sends the e-mails that are waiting in the queue (new, or failed and due for a retry).
 * Optional: for hosting that offers cron. Without cron, retries also happen after each
 * admin action and from the "Email" page of the admin area.
 *
 *   php bin/send-queued-mail.php [--limit=20]
 *
 * Example crontab (every 10 minutes):  *​/10 * * * * php /path/to/site/bin/send-queued-mail.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

/** @var App\App $app */
$app = require dirname(__DIR__) . '/app/bootstrap.php';

$limit = 20;
foreach ($argv as $arg) {
    if (preg_match('/^--limit=(\d{1,3})$/', $arg, $m)) {
        $limit = max(1, (int) $m[1]);
    }
}

$outcomes = $app->services()->notifications()->dispatchDue($limit, 50.0);

$summary = array_count_values($outcomes);
ksort($summary);
echo $outcomes === []
    ? "Nessuna email da inviare.\n"
    : 'Email elaborate: ' . count($outcomes) . ' (' . implode(', ', array_map(static fn (string $k, int $v): string => "$k: $v", array_keys($summary), $summary)) . ")\n";
