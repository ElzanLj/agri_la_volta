<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\PriceQuote;
use App\Http\Admin\Labels;
use App\Http\Admin\ListFilters;
use App\Http\Request;
use App\Http\Response;
use App\Service\BookingService;

final class RequestController extends BasePage
{
    public function index(Request $request): Response
    {
        $filters = ListFilters::fromRequest($request, Labels::REQUEST_STATUSES);
        $valid = $filters->isValid();
        $queries = $this->queries();

        return $this->render('admin/requests/index', [
            'title' => 'Richieste',
            'filters' => $filters,
            'rows' => $valid ? $queries->requests($filters) : [],
            'pagination' => $this->pagination($filters->page, $valid ? $queries->countRequests($filters) : 0, ListFilters::PER_PAGE),
            'apartments' => $queries->apartments(),
        ], $valid ? 200 : 400);
    }

    /** @param array<string, string> $params */
    public function show(Request $request, array $params): Response
    {
        $id = $this->id($params);
        $row = $id === null ? null : $this->queries()->request($id);
        if ($row === null) {
            return $this->notFound();
        }

        $snapshot = json_decode((string) $row['price_breakdown'], true);
        $summary = is_array($snapshot) ? PriceQuote::fromSnapshot($snapshot)->summary('it') : null;

        return $this->render('admin/requests/show', [
            'title' => 'Richiesta ' . $row['reference'],
            'row' => $row,
            'booking' => $this->queries()->bookingOfRequest((int) $row['id']),
            'summary' => $summary,
        ]);
    }

    /** @param array<string, string> $params */
    public function confirm(Request $request, array $params): Response
    {
        $id = $this->id($params);
        if ($id === null) {
            return $this->notFound();
        }
        return $this->attempt(
            fn (): array => (new BookingService($this->app->db()))->confirmRequest($id),
            'Richiesta confermata: la prenotazione è stata creata. (Nessuna email è stata inviata.)',
            static fn (array $result): string => '/admin/prenotazioni/' . $result['booking_id'],
            '/admin/richieste/' . $id,
        );
    }

    /** @param array<string, string> $params */
    public function reject(Request $request, array $params): Response
    {
        $id = $this->id($params);
        if ($id === null) {
            return $this->notFound();
        }
        return $this->attempt(
            fn () => (new BookingService($this->app->db()))->rejectRequest($id),
            'Richiesta rifiutata. (Nessuna email è stata inviata.)',
            '/admin/richieste/' . $id,
            '/admin/richieste/' . $id,
        );
    }
}
