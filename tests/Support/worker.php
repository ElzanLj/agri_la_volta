<?php

declare(strict_types=1);

/*
 * Concurrency worker, started as a separate OS process by the tests. Each worker has
 * its own database connection. It connects first, then waits until the common start
 * time so that all workers hit the database at (almost) the same instant.
 *
 *   php tests/Support/worker.php '<json>'
 *
 * JSON: {"action": "...", "params": {...}, "start_at": <unix time float>}
 * Prints one JSON line: {"ok": true, "result": ...} or {"ok": false, "error": "<type>", ...}
 */

require dirname(__DIR__, 2) . '/tests/bootstrap.php';

use App\App;
use App\Domain\BusyException;
use App\Domain\ConflictException;
use App\Domain\StateException;
use App\Domain\ValidationException;
use App\Service\BookingService;

$job = json_decode($argv[1] ?? '', true, 512, JSON_THROW_ON_ERROR);
$params = $job['params'];

$db = App::current()->db();
$service = new BookingService($db, null, static fn (): string => '2027-01-10');

while (microtime(true) < (float) $job['start_at']) {
    usleep(200);
}

try {
    $result = match ($job['action']) {
        'confirm' => $service->confirmRequest((int) $params['request_id']),
        'reject' => $service->rejectRequest((int) $params['request_id']),
        'manual' => $service->createManualBooking($params['input']),
        'block' => $service->createBlock((int) $params['apartment_id'], $params['start'], $params['end'], $params['reason'] ?? null),
        'cancel' => $service->cancelBooking((int) $params['booking_id'], $params['reason'] ?? null),
        default => throw new InvalidArgumentException('unknown action'),
    };
    echo json_encode(['ok' => true, 'result' => $result]), "\n";
} catch (ConflictException) {
    echo json_encode(['ok' => false, 'error' => 'conflict']), "\n";
} catch (StateException $e) {
    echo json_encode(['ok' => false, 'error' => 'state', 'reason' => $e->reason]), "\n";
} catch (BusyException) {
    echo json_encode(['ok' => false, 'error' => 'busy']), "\n";
} catch (ValidationException $e) {
    echo json_encode(['ok' => false, 'error' => 'validation', 'errors' => $e->errors()]), "\n";
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'error' => 'exception', 'message' => $e::class . ': ' . $e->getMessage()]), "\n";
}
