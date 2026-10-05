<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Money;
use App\Http\Admin\Labels;
use App\Http\Admin\ListFilters;
use App\Http\Request;
use App\Http\Response;
use App\Http\View;
use App\Support\Csv;

/**
 * CSV exports of requests and bookings (SPEC §31). Read-only; the admin guard applies like
 * everywhere under /admin. Guest-supplied text is neutralised against spreadsheet formulas (Csv).
 */
final class ExportController extends BasePage
{
    public function index(Request $request): Response
    {
        return $this->render('admin/export/index', ['title' => 'Esportazione dati', 'apartments' => $this->queries()->apartments()]);
    }

    public function requests(Request $request): Response
    {
        $filters = ListFilters::fromRequest($request, Labels::REQUEST_STATUSES);
        if (!$filters->isValid()) {
            return View::error(400, 'admin/layout');
        }

        $csv = Csv::row([
            'Riferimento', 'Stato', 'Appartamento', 'Arrivo', 'Partenza', 'Notti', 'Adulti', 'Bambini', 'Animali',
            'Nome', 'Cognome', 'Email', 'Telefono', 'Note', 'Lingua', 'Totale (EUR)', 'Ricevuta il', 'Decisa il',
        ]);
        foreach ($this->queries()->requests($filters, paginate: false) as $r) {
            $csv .= Csv::row([
                $r['reference'], Labels::requestStatus((string) $r['status']), $r['apartment_name'],
                $r['check_in'], $r['check_out'], $this->nights($r), $r['adults'], $r['children'], $r['pets'],
                $r['first_name'], $r['last_name'], $r['email'], $r['phone'], $r['notes'], $r['locale'],
                $this->amount($r['quoted_total_cents']), Labels::dateTime($r['created_at']), $r['decided_at'] === null ? '' : Labels::dateTime($r['decided_at']),
            ]);
        }

        return Response::csv($csv, 'richieste-' . $this->today() . '.csv');
    }

    public function bookings(Request $request): Response
    {
        $filters = ListFilters::fromRequest($request, Labels::BOOKING_STATUSES, withOrigin: true);
        if (!$filters->isValid()) {
            return View::error(400, 'admin/layout');
        }

        $csv = Csv::row([
            'N.', 'Stato', 'Origine', 'Appartamento', 'Arrivo', 'Partenza', 'Notti', 'Adulti', 'Bambini', 'Animali',
            'Ospite', 'Email', 'Telefono', 'Totale (EUR)', 'Note', 'Richiesta', 'Creata il', 'Cancellata il', 'Motivo cancellazione',
        ]);
        foreach ($this->queries()->bookings($filters, paginate: false) as $b) {
            $csv .= Csv::row([
                $b['id'], Labels::bookingStatus((string) $b['status']), Labels::origin((string) $b['origin']), $b['apartment_name'],
                $b['check_in'], $b['check_out'], $this->nights($b), $b['adults'], $b['children'], $b['pets'],
                $b['guest_name'], $b['email'], $b['phone'], $this->amount($b['total_cents']), $b['notes'], $b['request_reference'],
                Labels::dateTime($b['created_at']), $b['cancelled_at'] === null ? '' : Labels::dateTime($b['cancelled_at']), $b['cancellation_reason'],
            ]);
        }

        return Response::csv($csv, 'prenotazioni-' . $this->today() . '.csv');
    }

    /** @param array<string, mixed> $row */
    private function nights(array $row): int
    {
        return (int) ((new \DateTimeImmutable((string) $row['check_in']))->diff(new \DateTimeImmutable((string) $row['check_out']))->days);
    }

    private function amount(mixed $cents): string
    {
        return $cents === null ? '' : Money::plain((int) $cents);
    }
}
