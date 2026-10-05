<?php

declare(strict_types=1);

namespace App\Http\Admin;

use App\Repository\OutboxRepository;
use PDO;

/** Human sentence describing what happened to an e-mail, for flash messages. */
final class EmailStatusText
{
    /** @param array<string, mixed>|null $row an email_outbox row */
    public static function forRow(?array $row, string $what): string
    {
        if ($row === null) {
            return '';
        }
        return match ($row['status']) {
            'sent' => $what . ' inviata.',
            'pending', 'sending' => $what . ' in coda: verrà inviata a breve.',
            'skipped' => 'Nessuna ' . strtolower($what) . ': manca l\'indirizzo del destinatario.',
            default => '⚠ ' . $what . ' NON inviata (' . ($row['error_message'] ?: 'errore sconosciuto') . ')'
                . ((int) $row['retryable'] === 1 && $row['next_attempt_at'] !== null
                    ? '. Verrà ritentata automaticamente; puoi anche riprovare dalla pagina Email.'
                    : '. Controlla le impostazioni e riprova dalla pagina Email.'),
        };
    }

    /** Status of the latest e-mail of a given type queued for a request. */
    public static function forRequest(PDO $db, int $requestId, string $type): string
    {
        $rows = array_values(array_filter(
            (new OutboxRepository($db))->forRequest($requestId),
            static fn (array $r): bool => $r['type'] === $type,
        ));
        $last = $rows === [] ? null : $rows[count($rows) - 1];
        return self::forRow($last, $type === 'new_request_admin' ? 'Email al gestore' : 'Email al cliente');
    }
}
