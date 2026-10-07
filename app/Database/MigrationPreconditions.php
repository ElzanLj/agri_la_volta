<?php

declare(strict_types=1);

namespace App\Database;

use PDO;

/**
 * Checks that must pass BEFORE a migration changes anything. A migration that adds a constraint
 * would fail halfway if existing rows break it (and MySQL/MariaDB cannot undo schema changes), so the
 * rows are counted first: with offending rows the migration is refused with a message that says what to fix.
 * Read only.
 */
final class MigrationPreconditions
{
    /**
     * @return list<string> problems that stop the migration (empty when it can go ahead)
     */
    public static function problems(string $version, PDO $db): array
    {
        return match ($version) {
            '0007_data_constraints' => self::dataConstraints($db),
            default => [],
        };
    }

    /** @return list<string> */
    private static function dataConstraints(PDO $db): array
    {
        $rules = [
            'prenotazioni cancellate senza data di cancellazione, o con una data pur non essendo cancellate'
                => "SELECT COUNT(*) FROM bookings WHERE NOT ((status = 'cancelled' AND cancelled_at IS NOT NULL) OR (status <> 'cancelled' AND cancelled_at IS NULL))",
            'prenotazioni con un numero di ospiti fuori dai limiti (adulti 1-20, bambini fino a 20, animali fino a 10)'
                => 'SELECT COUNT(*) FROM bookings WHERE NOT (adults BETWEEN 1 AND 20 AND children <= 20 AND pets <= 10)',
            'richieste con data di decisione incoerente con lo stato'
                => "SELECT COUNT(*) FROM booking_requests WHERE NOT ((status = 'pending' AND decided_at IS NULL) OR (status <> 'pending' AND decided_at IS NOT NULL))",
            'richieste con un numero di ospiti fuori dai limiti (adulti 1-20, bambini fino a 20, animali fino a 10)'
                => 'SELECT COUNT(*) FROM booking_requests WHERE NOT (adults BETWEEN 1 AND 20 AND children <= 20 AND pets <= 10)',
            'email segnate come inviate senza data di invio'
                => "SELECT COUNT(*) FROM email_outbox WHERE status = 'sent' AND sent_at IS NULL",
        ];

        $problems = [];
        foreach ($rules as $description => $sql) {
            $count = (int) $db->query($sql)->fetchColumn();
            if ($count > 0) {
                $problems[] = $count . ' ' . $description;
            }
        }
        return $problems;
    }
}
