<?php

declare(strict_types=1);

/*
 * Data consistency check. READ ONLY: it changes nothing.
 *
 *   php bin/check-consistency.php
 *
 * Looks for confirmed bookings that overlap, bookings over a block, "confirmed" requests without a
 * booking (and the other way round), and e-mails stuck in "sending". Prints only ids and references.
 * Exit code 0 when everything is consistent, 1 when something is wrong, 2 when the check could not run.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

/** @var App\App $app */
$app = require dirname(__DIR__) . '/app/bootstrap.php';

try {
    $problems = (new App\Service\ConsistencyChecker($app->db()))->run();
} catch (Throwable $e) {
    fwrite(STDERR, "Il controllo non è riuscito (database non raggiungibile o non aggiornato).\n");
    exit(2);
}

if ($problems === []) {
    echo "Nessuna incoerenza trovata.\n";
    exit(0);
}

foreach ($problems as $problem) {
    echo $problem['message'] . ":\n";
    foreach ($problem['items'] as $item) {
        echo '  - ' . $item . "\n";
    }
}
echo "\nQuesto controllo non corregge nulla: serve capire la causa prima di intervenire.\n";
exit(1);
