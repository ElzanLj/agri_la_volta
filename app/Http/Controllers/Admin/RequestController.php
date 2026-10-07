<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\PriceQuote;
use App\Http\Admin\EmailStatusText;
use App\Http\Admin\ErrorMessages;
use App\Http\Admin\Flash;
use App\Http\Admin\Labels;
use App\Http\Admin\ListFilters;
use App\Http\Request;
use App\Http\Response;
use App\Mail\MessageBuilder;
use App\Mail\NoRecipientException;
use App\Repository\OutboxRepository;
use App\Support\WhatsApp;

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
            'emails' => (new OutboxRepository($this->app->db()))->forRequest((int) $row['id']),
            'whatsapp' => WhatsApp::linkForPhone($row['phone'], WhatsApp::customerMessage((string) $row['locale'], [
                'first_name' => (string) $row['first_name'], 'reference' => (string) $row['reference'], 'apartment' => (string) $row['apartment_name'],
                'check_in' => (string) $row['check_in'], 'check_out' => (string) $row['check_out'],
                'adults' => (int) $row['adults'], 'children' => (int) $row['children'], 'pets' => (int) $row['pets'],
            ]), $this->defaultCountryCode()),
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
            fn (): array => ($this->app->services()->bookingService())->confirmRequest($id),
            fn (array $result): string => 'Richiesta confermata: la prenotazione è stata creata. ' . EmailStatusText::forRequest($this->app->db(), $id, 'request_confirmed'),
            static fn (array $result): string => '/admin/prenotazioni/' . $result['booking_id'],
            '/admin/richieste/' . $id,
        );
    }

    /**
     * Step before a rejection: what is being rejected and the exact e-mail the guest will receive.
     * Reading only; the rejection itself is the POST below.
     *
     * @param array<string, string> $params
     */
    public function rejectForm(Request $request, array $params): Response
    {
        $id = $this->id($params);
        $row = $id === null ? null : $this->queries()->request($id);
        if ($row === null) {
            return $this->notFound();
        }
        if ($row['status'] !== 'pending') {
            Flash::error(ErrorMessages::forCode('request_not_pending'));
            return $this->redirect('/admin/richieste/' . $row['id']);
        }

        // The same builder that renders the e-mail when it is sent, so the preview is what the guest gets.
        $preview = null;
        $previewProblem = null;
        try {
            $message = (new MessageBuilder($this->app->db(), $this->app->config))->build([
                'type' => 'request_rejected', 'booking_request_id' => (int) $row['id'], 'booking_id' => null,
            ]);
            $preview = ['to' => $message->to, 'subject' => $message->subject, 'body' => $message->body];
        } catch (NoRecipientException) {
            $previewProblem = 'Questa richiesta non ha un indirizzo email valido: il cliente non riceverà nessun messaggio.';
        } catch (\Throwable) {
            $previewProblem = 'Non è stato possibile preparare l\'anteprima dell\'email.';
        }

        return $this->render('admin/requests/reject', [
            'title' => 'Rifiuta la richiesta ' . $row['reference'],
            'row' => $row,
            'preview' => $preview,
            'previewProblem' => $previewProblem,
        ]);
    }

    /** @param array<string, string> $params */
    public function reject(Request $request, array $params): Response
    {
        $id = $this->id($params);
        if ($id === null) {
            return $this->notFound();
        }
        // Only the confirmation page sends this field: a direct POST changes nothing and leads to that page.
        if ($request->input('conferma') !== '1') {
            return $this->redirect('/admin/richieste/' . $id . '/rifiuta');
        }
        return $this->attempt(
            fn () => ($this->app->services()->bookingService())->rejectRequest($id),
            fn (): string => 'Richiesta rifiutata. ' . EmailStatusText::forRequest($this->app->db(), $id, 'request_rejected'),
            '/admin/richieste/' . $id,
            '/admin/richieste/' . $id,
        );
    }
}
