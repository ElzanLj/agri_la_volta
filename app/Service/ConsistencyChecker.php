<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\OutboxRepository;
use PDO;

/**
 * Looks for data that must never exist together, independently of the application code that writes
 * it: a second line of defence behind the lock on the apartment row. READ ONLY: it never changes
 * anything, it only reports.
 *
 * The admin page and the dashboard alarm built on top of this arrive in the system-tools phase;
 * today it is used by bin/check-consistency.php and by the tests.
 *
 * Nothing personal is reported: only ids, references and dates.
 */
final class ConsistencyChecker
{
    public function __construct(private PDO $db)
    {
    }

    /**
     * @return list<array{code: string, message: string, items: list<string>}> one entry per kind of problem found
     */
    public function run(): array
    {
        $checks = [
            'bookings_overlap' => ['Prenotazioni confermate sovrapposte nello stesso appartamento', $this->bookingsOverlap()],
            'booking_over_block' => ['Prenotazioni confermate sovrapposte a un blocco di date', $this->bookingOverBlock()],
            'confirmed_request_without_booking' => ['Richieste "confermate" senza una prenotazione confermata', $this->confirmedRequestWithoutBooking()],
            'booking_of_unconfirmed_request' => ['Prenotazioni confermate collegate a una richiesta non confermata', $this->bookingOfUnconfirmedRequest()],
            'stuck_emails' => ['Email rimaste in invio oltre il tempo consentito', $this->stuckEmails()],
        ];

        $problems = [];
        foreach ($checks as $code => [$message, $items]) {
            if ($items !== []) {
                $problems[] = ['code' => $code, 'message' => $message, 'items' => $items];
            }
        }
        return $problems;
    }

    /** @return list<string> */
    private function bookingsOverlap(): array
    {
        return $this->lines(
            "SELECT CONCAT('prenotazioni n. ', a.id, ' e n. ', b.id, ' (appartamento ', a.apartment_id, ')')
               FROM bookings a JOIN bookings b
                 ON a.apartment_id = b.apartment_id AND a.id < b.id
                AND a.status = 'confirmed' AND b.status = 'confirmed'
                AND a.check_in < b.check_out AND a.check_out > b.check_in
              ORDER BY a.id, b.id LIMIT 200"
        );
    }

    /** @return list<string> */
    private function bookingOverBlock(): array
    {
        return $this->lines(
            "SELECT CONCAT('prenotazione n. ', a.id, ' e blocco n. ', k.id, ' (appartamento ', a.apartment_id, ')')
               FROM bookings a JOIN availability_blocks k
                 ON a.apartment_id = k.apartment_id AND a.status = 'confirmed'
                AND a.check_in < k.end_date AND a.check_out > k.start_date
              ORDER BY a.id, k.id LIMIT 200"
        );
    }

    /** @return list<string> */
    private function confirmedRequestWithoutBooking(): array
    {
        return $this->lines(
            "SELECT CONCAT('richiesta ', r.reference)
               FROM booking_requests r
              WHERE r.status = 'confirmed'
                AND NOT EXISTS (SELECT 1 FROM bookings b WHERE b.booking_request_id = r.id AND b.status = 'confirmed')
              ORDER BY r.id LIMIT 200"
        );
    }

    /** @return list<string> */
    private function bookingOfUnconfirmedRequest(): array
    {
        return $this->lines(
            "SELECT CONCAT('prenotazione n. ', b.id, ' della richiesta ', r.reference, ' (', r.status, ')')
               FROM bookings b JOIN booking_requests r ON r.id = b.booking_request_id
              WHERE b.status = 'confirmed' AND r.status <> 'confirmed'
              ORDER BY b.id LIMIT 200"
        );
    }

    /** @return list<string> */
    private function stuckEmails(): array
    {
        return $this->lines(
            "SELECT CONCAT('email n. ', id, ' (', type, ')')
               FROM email_outbox
              WHERE status = 'sending' AND locked_at < UTC_TIMESTAMP() - INTERVAL " . OutboxRepository::LEASE_MINUTES . " MINUTE
              ORDER BY id LIMIT 200"
        );
    }

    /** @return list<string> */
    private function lines(string $sql): array
    {
        return array_map('strval', $this->db->query($sql)->fetchAll(PDO::FETCH_COLUMN));
    }
}
