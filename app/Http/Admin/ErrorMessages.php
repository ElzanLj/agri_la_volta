<?php

declare(strict_types=1);

namespace App\Http\Admin;

use App\Domain\ConflictException;

/** Italian texts for the validation and state codes raised by the services. */
final class ErrorMessages
{
    private const MESSAGES = [
        // dates and stays
        'invalid_date' => 'Data non valida.',
        'check_out_not_after_check_in' => 'La data di partenza deve essere successiva a quella di arrivo.',
        'end_not_after_start' => 'La data finale deve essere successiva a quella iniziale.',
        'stay_too_long' => 'Il periodo è troppo lungo.',
        'check_in_in_past' => 'La data di arrivo è nel passato.',
        'check_in_too_far' => 'La data di arrivo è troppo lontana.',
        // guests and people
        'invalid_adults' => 'Numero di adulti non valido (almeno 1).',
        'invalid_children' => 'Numero di bambini non valido.',
        'invalid_pets' => 'Numero di animali non valido.',
        'over_capacity' => 'Supera la capienza dell\'appartamento.',
        'invalid_origin' => 'Origine non valida.',
        'invalid_guest_name' => 'Indica il nome dell\'ospite (massimo 200 caratteri).',
        'invalid_email' => 'Indirizzo email non valido.',
        'invalid_phone' => 'Numero di telefono non valido.',
        'notes_too_long' => 'Le note sono troppo lunghe (massimo 2000 caratteri).',
        'invalid_total' => 'Importo totale non valido.',
        'reason_too_long' => 'Il testo è troppo lungo.',
        'invalid_apartment' => 'Appartamento non valido.',
        // pricing
        'overlaps_existing_period' => 'Si sovrappone a un altro periodo attivo dello stesso appartamento.',
        'invalid_label' => 'Etichetta non valida (da 1 a 100 caratteri).',
        'invalid_amount' => 'Importo non valido (es. 80 oppure 80,50).',
        'invalid_min_nights' => 'Soggiorno minimo non valido (da 1 a 60 notti).',
        'invalid_applies_to' => 'Tipo di voce non valido.',
        'invalid_charge_basis' => 'Modalità di addebito non valida.',
        'invalid_free_units' => 'Quantità gratuita non valida.',
        'free_units_not_allowed_for_stay' => 'Un supplemento fisso non può avere quantità gratuite.',
        'invalid_sort_order' => 'Ordine non valido (da -1000 a 1000).',
        // apartments
        'invalid_name' => 'Nome non valido (da 1 a 100 caratteri).',
        'invalid_management_mode' => 'Modalità di gestione non valida.',
        'invalid_agency' => 'Nome agenzia troppo lungo.',
        'invalid_number' => 'Numero non valido.',
        'invalid_time' => 'Orario non valido (HH:MM).',
        'text_too_long' => 'Testo troppo lungo.',
        // states
        'request_not_found' => 'Richiesta non trovata.',
        'request_not_pending' => 'La richiesta è già stata gestita.',
        'booking_not_found' => 'Prenotazione non trovata.',
        'booking_not_confirmed' => 'La prenotazione non è confermata (forse è già stata cancellata).',
        'block_not_found' => 'Blocco non trovato.',
        'apartment_not_found' => 'Appartamento non trovato.',
        'rate_not_found' => 'Tariffa non trovata.',
        'rule_not_found' => 'Regola non trovata.',
    ];

    public static function forCode(string $code): string
    {
        return self::MESSAGES[$code] ?? 'Valore non valido.';
    }

    /**
     * @param array<string, string> $errors field => code
     * @return array<string, string> field => Italian message
     */
    public static function forFields(array $errors): array
    {
        return array_map(self::forCode(...), $errors);
    }

    public static function conflict(ConflictException $e): string
    {
        $parts = [];
        foreach ($e->conflicts() as $c) {
            $what = $c['type'] === 'booking' ? 'prenotazione n. ' . $c['id'] : 'blocco n. ' . $c['id'];
            $parts[] = $what . ' dal ' . Labels::date($c['start_date']) . ' al ' . Labels::date($c['end_date']);
        }
        return 'Le date non sono disponibili: ' . implode('; ', $parts) . '.';
    }
}
