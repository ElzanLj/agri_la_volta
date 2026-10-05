<?php

declare(strict_types=1);

/*
 * A small SMTP server for tests. It speaks the real protocol (greeting, EHLO, AUTH PLAIN/LOGIN,
 * MAIL FROM, RCPT TO, DATA, RSET, NOOP, QUIT) and can misbehave on purpose, so the real
 * SmtpTransport (PHPMailer) is exercised exactly as it would be against a provider.
 *
 *   php tests/Support/fake-smtp-server.php <port> <spool-directory>
 *
 * The scenario is read from <spool>/scenario at the start of every connection:
 *   ok               everything succeeds
 *   greeting_421     "421 service not available" instead of the greeting
 *   hang             accepts the connection and never answers (client timeout)
 *   slow             waits 1 s before the greeting, then behaves
 *   auth_535         rejects AUTH with 535
 *   auth_required    answers 530 to MAIL FROM until AUTH succeeded
 *   rcpt_550         rejects the recipient with 550 (permanent)
 *   rcpt_450         rejects the recipient with 450 (temporary)
 *   data_554         rejects the message after DATA with 554 (permanent)
 *   data_451         rejects the message after DATA with 451 (temporary)
 *   drop_mid_data    closes the connection while the message is being received
 *   drop_after_data  closes the connection after receiving the message, without a reply
 *
 * Received messages are saved as <spool>/message-N.json (envelope, raw data, AUTH user name only:
 * the password is never stored). Every connection appends its commands to <spool>/commands.log.
 */

if (PHP_SAPI !== 'cli') {
    exit(1);
}

[$script, $port, $spool] = $argv + [null, null, null];
if (!ctype_digit((string) $port) || !is_dir((string) $spool)) {
    fwrite(STDERR, "usage: fake-smtp-server.php <port> <spool-directory>\n");
    exit(2);
}

$server = stream_socket_server('tcp://127.0.0.1:' . $port, $errno, $error);
if ($server === false) {
    fwrite(STDERR, "cannot listen: $error\n");
    exit(1);
}

// Tells the test that the port is open (no probe connection needed).
file_put_contents($spool . '/ready', '1');

while (true) {
    $connection = @stream_socket_accept($server, 3600);
    if ($connection === false) {
        continue;
    }
    stream_set_timeout($connection, 30);
    try {
        handleConnection($connection, $spool);
    } catch (Throwable $e) {
        file_put_contents($spool . '/errors.log', $e->getMessage() . "\n", FILE_APPEND);
    }
    @fclose($connection);
}

function scenario(string $spool): string
{
    $file = $spool . '/scenario';
    return is_file($file) ? trim((string) file_get_contents($file)) : 'ok';
}

function say($connection, string $line): void
{
    @fwrite($connection, $line . "\r\n");
}

function logCommand(string $spool, string $line): void
{
    file_put_contents($spool . '/commands.log', $line . "\n", FILE_APPEND | LOCK_EX);
}

function handleConnection($connection, string $spool): void
{
    $scenario = scenario($spool);
    logCommand($spool, '--- connection (' . $scenario . ')');

    if ($scenario === 'hang') {
        sleep(6);
        return;
    }
    if ($scenario === 'greeting_421') {
        say($connection, '421 4.3.2 Service not available, closing channel');
        return;
    }
    if ($scenario === 'slow') {
        sleep(1);
    }
    say($connection, '220 fake.smtp ESMTP ready');

    $from = null;
    $recipients = [];
    $authUser = null;

    while (($line = fgets($connection)) !== false) {
        $line = rtrim($line, "\r\n");
        $verb = strtoupper(strtok($line, ' ') ?: '');

        // Log the verb and, for envelope commands, the argument; AUTH payloads are never logged.
        logCommand($spool, $verb === 'AUTH' ? 'AUTH' : $line);

        switch ($verb) {
            case 'EHLO':
                say($connection, '250-fake.smtp');
                say($connection, '250-AUTH PLAIN LOGIN');
                say($connection, '250 8BITMIME');
                break;
            case 'HELO':
                say($connection, '250 fake.smtp');
                break;
            case 'AUTH':
                if ($scenario === 'auth_535') {
                    say($connection, '535 5.7.8 Authentication credentials invalid');
                    break;
                }
                $mechanism = strtoupper(explode(' ', $line)[1] ?? '');
                if ($mechanism === 'PLAIN') {
                    $payload = explode(' ', $line)[2] ?? null;
                    if ($payload === null) {
                        say($connection, '334 ');
                        $payload = rtrim((string) fgets($connection), "\r\n");
                    }
                    $parts = explode("\0", (string) base64_decode($payload, true));
                    $authUser = $parts[1] ?? null;
                    say($connection, '235 2.7.0 Authentication successful');
                } elseif ($mechanism === 'LOGIN') {
                    say($connection, '334 VXNlcm5hbWU6');
                    $authUser = (string) base64_decode(rtrim((string) fgets($connection), "\r\n"), true);
                    say($connection, '334 UGFzc3dvcmQ6');
                    fgets($connection); // the password is read and discarded
                    say($connection, '235 2.7.0 Authentication successful');
                } else {
                    say($connection, '504 5.5.4 Unrecognized authentication type');
                }
                break;
            case 'MAIL':
                if ($scenario === 'auth_required' && $authUser === null) {
                    say($connection, '530 5.7.0 Authentication required');
                    break;
                }
                $from = extractAddress($line);
                $recipients = [];
                say($connection, '250 2.1.0 Ok');
                break;
            case 'RCPT':
                $to = extractAddress($line);
                if ($scenario === 'rcpt_550') {
                    say($connection, '550 5.1.1 <' . $to . '>: Recipient address rejected: User unknown in virtual mailbox table');
                } elseif ($scenario === 'rcpt_450') {
                    say($connection, '450 4.2.0 <' . $to . '>: Recipient address temporarily unavailable, try again later');
                } else {
                    $recipients[] = $to;
                    say($connection, '250 2.1.5 Ok');
                }
                break;
            case 'DATA':
                if ($recipients === []) {
                    say($connection, '503 5.5.1 Error: need RCPT command');
                    break;
                }
                say($connection, '354 End data with <CR><LF>.<CR><LF>');
                $data = '';
                $received = 0;
                while (($row = fgets($connection)) !== false) {
                    if ($row === ".\r\n") {
                        break;
                    }
                    $data .= str_starts_with($row, '..') ? substr($row, 1) : $row;
                    if ($scenario === 'drop_mid_data' && ++$received >= 3) {
                        return; // vanish in the middle of the message
                    }
                }
                if ($scenario === 'data_554') {
                    say($connection, '554 5.7.1 Message rejected: content refused');
                } elseif ($scenario === 'data_451') {
                    say($connection, '451 4.3.0 Temporary local problem, please try again later');
                } elseif ($scenario === 'drop_after_data') {
                    saveMessage($spool, $from, $recipients, $data, $authUser);
                    return; // the message arrived but the client never hears the verdict
                } else {
                    saveMessage($spool, $from, $recipients, $data, $authUser);
                    say($connection, '250 2.0.0 Ok: queued as FAKE' . random_int(1000, 9999));
                }
                break;
            case 'RSET':
                $from = null;
                $recipients = [];
                say($connection, '250 2.0.0 Ok');
                break;
            case 'NOOP':
                say($connection, '250 2.0.0 Ok');
                break;
            case 'QUIT':
                say($connection, '221 2.0.0 Bye');
                return;
            default:
                say($connection, '502 5.5.2 Error: command not recognized');
        }
    }
}

function extractAddress(string $line): string
{
    return preg_match('/<([^>]*)>/', $line, $m) === 1 ? $m[1] : '';
}

/** @param list<string> $recipients */
function saveMessage(string $spool, ?string $from, array $recipients, string $data, ?string $authUser): void
{
    $files = glob($spool . '/message-*.json') ?: [];
    $number = count($files) + 1;
    file_put_contents($spool . '/message-' . $number . '.json', json_encode([
        'mail_from' => $from,
        'rcpt_to' => $recipients,
        'auth_user' => $authUser,
        'data' => $data,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}
