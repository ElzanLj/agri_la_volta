<?php

declare(strict_types=1);

/*
 * Creates the single shared admin account, or changes its username/password.
 *
 *   php bin/create-admin.php [username]               writes to the database configured in .env
 *   php bin/create-admin.php [username] --print-sql   writes NOTHING: prints the SQL to import by hand
 *
 * --print-sql is for hosting without SSH. Run it on your own computer (PHP or Docker are enough; no database
 * is needed), then import the SQL in phpMyAdmin on the site's database: see docs/COMMANDS.md, "Amministratore".
 * It works both to create the account and to recover a forgotten password, and it never needs the old one.
 *
 * The password is read from the terminal (hidden where supported) or from two lines on standard input.
 * It is never accepted as a command-line argument. The SQL contains the hash, never the password. The rules
 * are the same as on the Account page: at least 12 characters, not a common password, not the user name
 * or the name of the farm.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

/** @var App\App $app */
$app = require dirname(__DIR__) . '/app/bootstrap.php';

use App\Security\PasswordPolicy;

// Checked once, before any read: the check is unreliable after STDIN has been buffered.
define('INTERACTIVE', DIRECTORY_SEPARATOR === '/' && stream_isatty(STDIN));

$printSql = in_array('--print-sql', $argv, true);
$positional = array_values(array_filter(array_slice($argv, 1), static fn (string $a): bool => !str_starts_with($a, '--')));
foreach (array_slice($argv, 1) as $argument) {
    if (str_starts_with($argument, '--') && $argument !== '--print-sql') {
        fwrite(STDERR, "Opzione sconosciuta: $argument\nUso: php bin/create-admin.php [nome-utente] [--print-sql]\n");
        exit(1);
    }
}

if (count($positional) > 1) {
    // The only thing that may follow the command is the user name. A password typed here would stay in the shell history.
    fwrite(STDERR, "Troppi argomenti: la password non si scrive mai nel comando, viene chiesta subito dopo.
Uso: php bin/create-admin.php [nome-utente] [--print-sql]
");
    exit(1);
}

function fail(string $message): never
{
    fwrite(STDERR, $message . "\n");
    exit(1);
}

/** Questions go to the error stream with --print-sql, so that the output is only SQL (and can be redirected to a file). */
function ask(string $prompt, bool $hidden = false): string
{
    global $printSql;
    fwrite($printSql ? STDERR : STDOUT, $prompt);
    $hide = $hidden && INTERACTIVE;
    if ($hide) {
        shell_exec('stty -echo');
    }
    $line = fgets(STDIN);
    if ($hide) {
        shell_exec('stty echo');
        fwrite($printSql ? STDERR : STDOUT, "\n");
    }
    return $line === false ? '' : rtrim($line, "\r\n");
}

/** A value as an SQL string literal. */
function sqlString(string $value): string
{
    return "'" . str_replace(['\\', "'"], ['\\\\', "''"], $value) . "'";
}

$username = trim($positional[0] ?? ask('Nome utente: '));
if (!preg_match('/^[A-Za-z0-9._@-]{3,100}$/', $username)) {
    fail('Nome utente non valido: 3-100 caratteri tra lettere, numeri e . _ @ -');
}

$password = ask('Password: ', true);
$problems = PasswordPolicy::problems($password, $username);
if ($problems !== []) {
    fail(implode("\n", $problems));
}
if (ask('Conferma password: ', true) !== $password) {
    fail('Le password non coincidono.');
}

$hash = password_hash($password, PASSWORD_DEFAULT);

if ($printSql) {
    // No database access at all: this runs on any computer. One account only: the first statement updates it
    // when it exists, the second creates it only when the table is empty.
    $user = sqlString($username);
    $hashSql = sqlString($hash);
    echo "-- Agriturismo La Volta: credenziali dell'amministratore.\n";
    echo "-- Importa questo testo in phpMyAdmin (scheda \"SQL\") sul database del sito, poi cancella il file.\n";
    echo "-- Contiene l'impronta (hash) della password, non la password; custodiscilo comunque con cura.\n";
    echo "-- Le sessioni aperte si chiudono da sole al cambio dell'hash.\n";
    echo "UPDATE admin SET username = $user, password_hash = $hashSql ORDER BY id LIMIT 1;\n";
    echo "INSERT INTO admin (username, password_hash) SELECT $user, $hashSql FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM admin);\n";
    echo "INSERT INTO audit_log (entity_type, entity_id, action, summary) VALUES ('admin', NULL, 'credentials_changed', 'Credenziali amministratore impostate con un comando SQL');\n";
    fwrite(STDERR, "SQL generato. Importalo sul database del sito (vedi docs/COMMANDS.md, \"Amministratore\").\n");
    exit(0);
}

$db = $app->db();

$db->beginTransaction();
$existingId = $db->query('SELECT id FROM admin ORDER BY id LIMIT 1 FOR UPDATE')->fetchColumn();
if ($existingId === false) {
    $db->prepare('INSERT INTO admin (username, password_hash) VALUES (?, ?)')->execute([$username, $hash]);
    $id = (int) $db->lastInsertId();
    $action = 'created';
} else {
    $id = (int) $existingId;
    $db->prepare('UPDATE admin SET username = ?, password_hash = ? WHERE id = ?')->execute([$username, $hash, $id]);
    $action = 'credentials_changed';
}
(new App\Support\AuditLog($db))->record('admin', $id, $action, 'Credenziali amministratore impostate da riga di comando');
$db->commit();

echo $action === 'created' ? "Amministratore creato.\n" : "Credenziali amministratore aggiornate.\n";
