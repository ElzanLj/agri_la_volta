<?php

declare(strict_types=1);

/*
 * Creates the single shared admin account, or changes its username/password.
 *
 *   php bin/create-admin.php [username]
 *
 * The password is read from the terminal (hidden where supported) or from two
 * lines on standard input. It is never accepted as a command-line argument.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

/** @var App\App $app */
$app = require dirname(__DIR__) . '/app/bootstrap.php';

const MIN_PASSWORD_LENGTH = 12;

// Checked once, before any read: the check is unreliable after STDIN has been buffered.
define('INTERACTIVE', DIRECTORY_SEPARATOR === '/' && stream_isatty(STDIN));

function fail(string $message): never
{
    fwrite(STDERR, $message . "\n");
    exit(1);
}

function ask(string $prompt, bool $hidden = false): string
{
    echo $prompt;
    $hide = $hidden && INTERACTIVE;
    if ($hide) {
        shell_exec('stty -echo');
    }
    $line = fgets(STDIN);
    if ($hide) {
        shell_exec('stty echo');
        echo "\n";
    }
    return $line === false ? '' : rtrim($line, "\r\n");
}

$username = trim($argv[1] ?? ask('Nome utente: '));
if (!preg_match('/^[A-Za-z0-9._@-]{3,100}$/', $username)) {
    fail('Nome utente non valido: 3-100 caratteri tra lettere, numeri e . _ @ -');
}

$password = ask('Password: ', true);
if (strlen($password) < MIN_PASSWORD_LENGTH) {
    fail(sprintf('La password deve avere almeno %d caratteri.', MIN_PASSWORD_LENGTH));
}
if (ask('Conferma password: ', true) !== $password) {
    fail('Le password non coincidono.');
}

$db = $app->db();
$hash = password_hash($password, PASSWORD_DEFAULT);

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
