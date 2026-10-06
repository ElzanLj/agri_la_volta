<?php

declare(strict_types=1);

/*
 * Read-only readiness check for a production installation (docs/RELEASE_GUIDE.md).
 *
 *   php bin/check-production.php            exit 1 if something blocking is wrong
 *   php bin/check-production.php --strict   exit 1 on warnings too
 *
 * Looks at configuration, files and the configured database only. It does NOT contact SMTP or any
 * other external service, writes nothing, and never prints a password or secret.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

/** @var App\App $app */
$app = require dirname(__DIR__) . '/app/bootstrap.php';

$keys = [
    'APP_ENV', 'APP_DEBUG', 'APP_URL', 'APP_SECRET', 'HSTS_MAX_AGE', 'DATA_RETENTION_MONTHS',
    'DB_NAME', 'MAIL_TRANSPORT', 'SMTP_HOST', 'SMTP_PORT', 'SMTP_ENCRYPTION', 'SMTP_USERNAME', 'SMTP_PASSWORD',
    'MAIL_FROM_ADDRESS', 'MAIL_ADMIN_ADDRESS', 'PUBLIC_PHONE', 'PUBLIC_EMAIL', 'PUBLIC_ADDRESS', 'WHATSAPP_NUMBER',
];
$env = [];
foreach ($keys as $key) {
    $env[$key] = $app->config->string($key);
}
// Unlike the application (which defaults to production), an unset APP_ENV is reported as it is.
$env['APP_ENV'] = $app->config->string('APP_ENV', '');

$db = null;
$problem = null;
try {
    $db = $app->db();
} catch (Throwable $e) {
    $problem = 'codice ' . (string) $e->getCode(); // never the server's message: it may contain host and user names
}

$results = (new App\Support\ProductionCheck($env, dirname(__DIR__), $db, $problem))->run();

$labels = [
    App\Support\ProductionCheck::OK => '[ OK ]      ',
    App\Support\ProductionCheck::WARN => '[ATTENZIONE]',
    App\Support\ProductionCheck::FAIL => '[ERRORE]    ',
];
foreach ($results as $r) {
    printf("%s %-30s %s\n", $labels[$r['level']], $r['check'], $r['message']);
}

$fails = App\Support\ProductionCheck::count($results, App\Support\ProductionCheck::FAIL);
$warns = App\Support\ProductionCheck::count($results, App\Support\ProductionCheck::WARN);
printf("\nRisultato: %d errori, %d avvisi, %d controlli superati.\n", $fails, $warns, count($results) - $fails - $warns);
echo $fails > 0
    ? "NON pronto: correggere gli errori prima della pubblicazione.\n"
    : ($warns > 0 ? "Nessun blocco; valutare gli avvisi.\n" : "Pronto dal punto di vista tecnico.\n");

$strict = in_array('--strict', $argv, true);
exit($fails > 0 || ($strict && $warns > 0) ? 1 : 0);
