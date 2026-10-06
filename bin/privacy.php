<?php

declare(strict_types=1);

/*
 * Personal data tools (SPEC §32). Command line only.
 *
 *   php bin/privacy.php export <email>                      prints everything held about the person (JSON)
 *   php bin/privacy.php erase <email> [--apply] [--include-active]
 *                                                           anonymises that person's requests and bookings
 *   php bin/privacy.php purge [--months=N] [--apply]        anonymises stays that ended more than N months ago
 *                                                           (N defaults to DATA_RETENTION_MONTHS; without either, nothing is done)
 *
 * erase and purge only REPORT what they would do unless --apply is given. Anonymising keeps the
 * dates and the apartment of each stay (they stay occupied) and removes name, e-mail, phone and notes.
 * Pending requests and stays not yet over are skipped unless --include-active is given (erase only).
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

/** @var App\App $app */
$app = require dirname(__DIR__) . '/app/bootstrap.php';

$args = array_slice($argv, 1);
$command = array_shift($args) ?? '';
$apply = in_array('--apply', $args, true);
$includeActive = in_array('--include-active', $args, true);
$positional = array_values(array_filter($args, static fn (string $a): bool => !str_starts_with($a, '--')));
$months = null;
foreach ($args as $arg) {
    if (preg_match('/^--months=(\d{1,3})$/', $arg, $m)) {
        $months = (int) $m[1];
    }
}

$service = new App\Service\PersonalDataService($app->db());

$report = static function (array $result, bool $apply): void {
    echo ($apply ? 'ESEGUITO' : 'SIMULAZIONE (nessuna modifica: aggiungi --apply per eseguire)') . "\n";
    printf("Richieste: %d · Prenotazioni: %d · Testi di email: %d\n", $result['requests'], $result['bookings'], $result['emails']);
    foreach ($result['skipped'] as $line) {
        echo "Saltata, " . $line . "\n";
    }
};

switch ($command) {
    case 'export':
        $email = $positional[0] ?? '';
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            fwrite(STDERR, "Indica un indirizzo email valido.\n");
            exit(1);
        }
        echo json_encode($service->export($email), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
        break;

    case 'erase':
        $email = $positional[0] ?? '';
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            fwrite(STDERR, "Indica un indirizzo email valido.\n");
            exit(1);
        }
        $report($service->erase($email, $apply, $includeActive), $apply);
        break;

    case 'purge':
        $months ??= $app->config->int('DATA_RETENTION_MONTHS', 0);
        if ($months < 1) {
            fwrite(STDERR, "Nessun periodo di conservazione: indica --months=N oppure imposta DATA_RETENTION_MONTHS. Non è stato fatto nulla.\n");
            exit(1);
        }
        printf("Soggiorni terminati da più di %d mesi.\n", $months);
        $report($service->purgeOlderThan($months, $apply), $apply);
        break;

    default:
        fwrite(STDERR, "Uso: php bin/privacy.php export <email> | erase <email> [--apply] [--include-active] | purge [--months=N] [--apply]\n");
        exit(1);
}
