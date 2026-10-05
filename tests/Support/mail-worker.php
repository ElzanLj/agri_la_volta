<?php

declare(strict_types=1);

/*
 * Mail worker for concurrency tests: a separate OS process with its own database connection that
 * tries to send queued messages, writing each message it sends to a shared folder (LogTransport).
 * Counting the files afterwards shows how many times every message really left.
 *
 *   php tests/Support/mail-worker.php '<json>'
 *   json: {"action": "dispatch"|"due", "id": 5, "limit": 10, "dir": "/tmp/x", "start_at": <unix time float>}
 */

require dirname(__DIR__, 2) . '/tests/bootstrap.php';

use App\App;
use App\Mail\LogTransport;
use App\Mail\MessageBuilder;
use App\Mail\NotificationService;

$job = json_decode($argv[1] ?? '', true, 512, JSON_THROW_ON_ERROR);
$app = App::current();
$db = $app->db();

$notifications = new NotificationService($db, new LogTransport($job['dir']), new MessageBuilder($db, $app->config), $app->logger);

while (microtime(true) < (float) $job['start_at']) {
    usleep(200);
}

try {
    $result = $job['action'] === 'due'
        ? $notifications->dispatchDue((int) $job['limit'])
        : $notifications->dispatch((int) $job['id']);
    echo json_encode(['ok' => true, 'result' => $result]), "\n";
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'error' => $e::class . ': ' . $e->getMessage()]), "\n";
}
