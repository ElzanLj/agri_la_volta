<?php

declare(strict_types=1);

namespace App\Mail;

use App\Config;
use App\Domain\Money;
use App\Domain\PriceQuote;
use DateTimeImmutable;
use PDO;
use RuntimeException;

/**
 * Renders the e-mail for a queued row at SEND time (never inside a booking transaction).
 *
 * The wording here is a neutral PROVISIONAL text: the owner must approve the final Italian and
 * English versions (docs/MISSING_DATA.md). Nothing in it invents business information: arrival
 * and departure times appear only when they are configured for the apartment.
 */
final class MessageBuilder
{
    public function __construct(private PDO $db, private Config $config)
    {
    }

    /**
     * @param array<string, mixed> $row an email_outbox row
     * @throws NoRecipientException|MailTransportException|RuntimeException
     */
    public function build(array $row): MailMessage
    {
        return match ($row['type']) {
            'new_request_admin' => $this->newRequestForAdmin((int) $row['booking_request_id']),
            'request_confirmed' => $this->requestConfirmed((int) $row['booking_request_id'], (int) $row['booking_id']),
            'request_rejected' => $this->requestRejected((int) $row['booking_request_id']),
            'cancellation' => $this->cancellation($row),
            default => throw new RuntimeException('Unknown e-mail type.'),
        };
    }

    // === Manager: new request ====================================================

    private function newRequestForAdmin(int $requestId): MailMessage
    {
        $admin = $this->config->string('MAIL_ADMIN_ADDRESS');
        if ($admin === '') {
            throw new MailTransportException(MailTransportException::NOT_CONFIGURED, 'MAIL_ADMIN_ADDRESS non configurato.', false);
        }
        $r = $this->request($requestId);

        $summary = is_array($snapshot = json_decode((string) $r['price_breakdown'], true))
            ? PriceQuote::fromSnapshot($snapshot)->summary('it')
            : null;

        $price = [];
        if ($summary !== null && $summary['total'] !== null) {
            foreach ($summary['lines'] as $line) {
                $price[] = '  ' . $line['label'] . ': ' . $line['detail'] . ' = ' . $line['amount'];
            }
            $price[] = '  Totale: ' . $summary['total'];
        } else {
            $price[] = '  Prezzo da confermare (il listino non copre tutte le notti).';
        }

        $link = $this->adminLink('/admin/richieste/' . $r['id']);
        $nights = $this->nights($r);

        $body = "È arrivata una nuova richiesta di soggiorno dal sito.\n\n"
            . 'Riferimento: ' . $r['reference'] . "\n"
            . 'Appartamento: ' . $r['apartment_name'] . "\n"
            . 'Arrivo: ' . $this->date(false, $r['check_in']) . "\n"
            . 'Partenza: ' . $this->date(false, $r['check_out']) . ' (' . $nights . ' ' . ($nights === 1 ? 'notte' : 'notti') . ")\n"
            . 'Ospiti: ' . $this->guests('it', (int) $r['adults'], (int) $r['children'], (int) $r['pets']) . "\n\n"
            . 'Cliente: ' . MailMessage::oneLine($r['first_name'] . ' ' . $r['last_name']) . "\n"
            . 'Email: ' . MailMessage::oneLine($r['email']) . "\n"
            . 'Telefono: ' . MailMessage::oneLine($r['phone']) . "\n"
            . 'Lingua: ' . $r['locale'] . "\n\n"
            . "Prezzo calcolato dal sito:\n" . implode("\n", $price) . "\n\n"
            . "Note del cliente:\n  " . (($r['notes'] ?? '') === '' ? '(nessuna)' : str_replace("\n", "\n  ", (string) $r['notes'])) . "\n\n"
            . ($link === null ? '' : "Apri la richiesta: {$link}\n")
            . "Attenzione: questa è una richiesta, non una prenotazione. Va confermata o rifiutata dall'area amministrativa.\n";

        return new MailMessage(
            $admin,
            'Nuova richiesta di soggiorno ' . $r['reference'],
            $body,
            $r['email'],
            MailMessage::oneLine($r['first_name'] . ' ' . $r['last_name']),
        );
    }

    // === Guest: confirmation and rejection =========================================

    private function requestConfirmed(int $requestId, int $bookingId): MailMessage
    {
        $r = $this->request($requestId);
        $to = $this->guestAddress($r['email']);
        $booking = $this->booking($bookingId);
        $apartment = $this->apartment((int) $r['apartment_id']);
        $en = $r['locale'] === 'en';

        $arrival = $this->date($en, $r['check_in']) . $this->window($en, $apartment['check_in_from'], $apartment['check_in_until'], false);
        $departure = $this->date($en, $r['check_out']) . $this->window($en, null, $apartment['check_out_until'], true);
        $total = $booking['total_cents'] === null ? null : Money::format((int) $booking['total_cents'], $en ? 'en' : 'it');
        $guests = $this->guests($en ? 'en' : 'it', (int) $r['adults'], (int) $r['children'], (int) $r['pets']);
        $name = MailMessage::oneLine($r['first_name'] . ' ' . $r['last_name']);

        if ($en) {
            $body = "Dear {$name},\n\n"
                . "we are pleased to confirm your request {$r['reference']} at Agriturismo La Volta.\n\n"
                . "Apartment: {$apartment['name']}\nArrival: {$arrival}\nDeparture: {$departure}\nGuests: {$guests}\n"
                . ($total === null ? "The final price will be communicated to you directly.\n" : "Total: {$total}\n")
                . "\nPlease reply to this email if you need any information.\n\nKind regards\nAgriturismo La Volta\n";
            return new MailMessage($to, 'Booking confirmed – Agriturismo La Volta (' . $r['reference'] . ')', $body);
        }

        $body = "Gentile {$name},\n\n"
            . "siamo lieti di confermare la sua richiesta {$r['reference']} presso Agriturismo La Volta.\n\n"
            . "Appartamento: {$apartment['name']}\nArrivo: {$arrival}\nPartenza: {$departure}\nOspiti: {$guests}\n"
            . ($total === null ? "Il prezzo definitivo le sarà comunicato direttamente.\n" : "Totale: {$total}\n")
            . "\nPer qualsiasi informazione può rispondere a questa email.\n\nCordiali saluti\nAgriturismo La Volta\n";
        return new MailMessage($to, 'Prenotazione confermata – Agriturismo La Volta (' . $r['reference'] . ')', $body);
    }

    private function requestRejected(int $requestId): MailMessage
    {
        $r = $this->request($requestId);
        $to = $this->guestAddress($r['email']);
        $en = $r['locale'] === 'en';
        $name = MailMessage::oneLine($r['first_name'] . ' ' . $r['last_name']);

        if ($en) {
            $body = "Dear {$name},\n\n"
                . "thank you for your interest in Agriturismo La Volta.\n"
                . "Unfortunately we are not able to accept request {$r['reference']} for apartment {$r['apartment_name']} "
                . 'from ' . $this->date(true, $r['check_in']) . ' to ' . $this->date(true, $r['check_out']) . ".\n\n"
                . "If you would like to consider other dates, please reply to this email.\n\nKind regards\nAgriturismo La Volta\n";
            return new MailMessage($to, 'About your request – Agriturismo La Volta (' . $r['reference'] . ')', $body);
        }

        $body = "Gentile {$name},\n\n"
            . "la ringraziamo per l'interesse per Agriturismo La Volta.\n"
            . "Purtroppo non possiamo accettare la richiesta {$r['reference']} per l'appartamento {$r['apartment_name']} "
            . 'dal ' . $this->date(false, $r['check_in']) . ' al ' . $this->date(false, $r['check_out']) . ".\n\n"
            . "Se desidera valutare altre date può rispondere a questa email.\n\nCordiali saluti\nAgriturismo La Volta\n";
        return new MailMessage($to, 'La sua richiesta – Agriturismo La Volta (' . $r['reference'] . ')', $body);
    }

    // === Cancellation: only the text the admin chose to send =======================

    /** @param array<string, mixed> $row */
    private function cancellation(array $row): MailMessage
    {
        $booking = $this->booking((int) $row['booking_id']);
        $to = $this->guestAddress($booking['email']);

        if (trim((string) $row['subject']) === '' || trim((string) $row['body']) === '') {
            throw new RuntimeException('Cancellation message has no text.');
        }
        return new MailMessage($to, (string) $row['subject'], (string) $row['body']);
    }

    // === Data access and formatting ============================================

    /** @return array<string, mixed> */
    private function request(int $id): array
    {
        $stmt = $this->db->prepare('SELECT r.*, a.name AS apartment_name FROM booking_requests r JOIN apartments a ON a.id = r.apartment_id WHERE r.id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: throw new RuntimeException('Request not found.');
    }

    /** @return array<string, mixed> */
    private function booking(int $id): array
    {
        $stmt = $this->db->prepare('SELECT * FROM bookings WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: throw new RuntimeException('Booking not found.');
    }

    /** @return array<string, mixed> */
    private function apartment(int $id): array
    {
        $stmt = $this->db->prepare('SELECT * FROM apartments WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: throw new RuntimeException('Apartment not found.');
    }

    private function guestAddress(mixed $address): string
    {
        $address = is_string($address) ? trim($address) : '';
        if ($address === '') {
            throw new NoRecipientException('No e-mail address.');
        }
        return $address;
    }

    private function adminLink(string $path): ?string
    {
        $base = rtrim($this->config->string('APP_URL'), '/');
        return $base === '' ? null : $base . $path;
    }

    /** @param array<string, mixed> $r */
    private function nights(array $r): int
    {
        return (int) (new DateTimeImmutable((string) $r['check_in']))->diff(new DateTimeImmutable((string) $r['check_out']))->days;
    }

    private function date(bool $english, string $ymd): string
    {
        $d = DateTimeImmutable::createFromFormat('!Y-m-d', $ymd);
        if ($d === false) {
            return $ymd;
        }
        return $english ? $d->format('j F Y') : $d->format('d/m/Y');
    }

    /** " (from 15:00 to 19:30)" / " (dalle 15:00 alle 19:30)" — only with configured times. */
    private function window(bool $en, ?string $from, ?string $until, bool $checkOut): string
    {
        $from = $from === null ? null : substr($from, 0, 5);
        $until = $until === null ? null : substr($until, 0, 5);

        if ($checkOut) {
            return $until === null ? '' : ($en ? " (by {$until})" : " (entro le {$until})");
        }
        if ($from !== null && $until !== null) {
            return $en ? " (from {$from} to {$until})" : " (dalle {$from} alle {$until})";
        }
        if ($from !== null) {
            return $en ? " (from {$from})" : " (dalle {$from})";
        }
        return $until === null ? '' : ($en ? " (until {$until})" : " (fino alle {$until})");
    }

    private function guests(string $locale, int $adults, int $children, int $pets): string
    {
        $en = $locale === 'en';
        $parts = [$adults . ' ' . ($en ? ($adults === 1 ? 'adult' : 'adults') : ($adults === 1 ? 'adulto' : 'adulti'))];
        if ($children > 0) {
            $parts[] = $children . ' ' . ($en ? ($children === 1 ? 'child' : 'children') : ($children === 1 ? 'bambino' : 'bambini'));
        }
        if ($pets > 0) {
            $parts[] = $pets . ' ' . ($en ? ($pets === 1 ? 'pet' : 'pets') : ($pets === 1 ? 'animale' : 'animali'));
        }
        return implode(', ', $parts);
    }
}
