<?php

declare(strict_types=1);

namespace App\Mail;

/**
 * The cancellation e-mail is never sent automatically (SPEC §16-17): this builds an editable
 * draft; the admin reads it, edits it and sends it explicitly (or copies it).
 */
final class CancellationDraft
{
    /** Text the admin must replace before the draft can be sent. */
    public const PLACEHOLDER = '[Scrivi qui il motivo e le eventuali indicazioni]';
    public const PLACEHOLDER_EN = '[Write here the reason and any instructions]';

    /**
     * @param array<string, mixed> $booking a bookings row joined with apartment_name
     * @return array{subject: string, body: string}
     */
    public static function build(array $booking, string $locale): array
    {
        $en = $locale === 'en';
        $dates = $en
            ? 'from ' . self::date((string) $booking['check_in'], true) . ' to ' . self::date((string) $booking['check_out'], true)
            : 'dal ' . self::date((string) $booking['check_in'], false) . ' al ' . self::date((string) $booking['check_out'], false);

        if ($en) {
            return [
                'subject' => 'Cancellation of your booking – Agriturismo La Volta',
                'body' => "Dear " . MailMessage::oneLine((string) $booking['guest_name']) . ",\n\n"
                    . "we are writing to let you know that your booking for apartment " . $booking['apartment_name'] . " " . $dates . " has been cancelled.\n\n"
                    . self::PLACEHOLDER_EN . "\n\n"
                    . "Please reply to this email if you need any information.\n\n"
                    . "Kind regards\nAgriturismo La Volta\n",
            ];
        }
        return [
            'subject' => 'Cancellazione della prenotazione – Agriturismo La Volta',
            'body' => 'Gentile ' . MailMessage::oneLine((string) $booking['guest_name']) . ",\n\n"
                . "la informiamo che la prenotazione per l'appartamento " . $booking['apartment_name'] . ' ' . $dates . " è stata cancellata.\n\n"
                . self::PLACEHOLDER . "\n\n"
                . "Per qualsiasi informazione può rispondere a questa email.\n\n"
                . "Cordiali saluti\nAgriturismo La Volta\n",
        ];
    }

    /** True while the text still contains an unedited placeholder. */
    public static function hasPlaceholder(string $body): bool
    {
        return str_contains($body, self::PLACEHOLDER) || str_contains($body, self::PLACEHOLDER_EN);
    }

    private static function date(string $ymd, bool $english): string
    {
        $d = \DateTimeImmutable::createFromFormat('!Y-m-d', $ymd);
        return $d === false ? $ymd : $d->format($english ? 'j F Y' : 'd/m/Y');
    }
}
