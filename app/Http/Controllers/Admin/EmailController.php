<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Admin\Flash;
use App\Http\Admin\Labels;
use App\Http\Admin\ListFilters;
use App\Http\Request;
use App\Http\Response;
use App\Mail\CancellationDraft;
use App\Repository\OutboxRepository;
use App\Support\AuditLog;
use Throwable;

/**
 * The e-mail queue (status, manual retry) and the cancellation draft. Nothing here sends a
 * message by itself: a customer e-mail about a cancellation leaves only after an explicit POST.
 */
final class EmailController extends BasePage
{
    private const SUBJECT_MAX = 200;
    private const BODY_MAX = 10000;

    public function index(Request $request): Response
    {
        $filters = ListFilters::fromRequest($request, Labels::EMAIL_STATUSES);
        $valid = $filters->isValid();
        $outbox = new OutboxRepository($this->app->db());

        return $this->render('admin/email/index', [
            'title' => 'Email',
            'filters' => $filters,
            'rows' => $valid ? $outbox->list($filters->status, ListFilters::PER_PAGE, $filters->offset()) : [],
            'pagination' => $this->pagination($filters->page, $valid ? $outbox->count($filters->status) : 0, ListFilters::PER_PAGE),
            'counts' => $outbox->countsByStatus(),
            'transport' => strtolower($this->app->config->string('MAIL_TRANSPORT', 'smtp')),
        ], $valid ? 200 : 400);
    }

    /** @param array<string, string> $params */
    public function retry(Request $request, array $params): Response
    {
        $id = $this->id($params);
        $row = $id === null ? null : (new OutboxRepository($this->app->db()))->find($id);
        if ($row === null) {
            return $this->notFound();
        }
        if (in_array($row['status'], ['sent', 'skipped'], true)) {
            Flash::error('Questa email è già ' . ($row['status'] === 'sent' ? 'stata inviata' : 'stata saltata') . '.');
            return $this->redirect('/admin/email');
        }

        $outcome = $this->app->services()->notifications()->retry((int) $row['id']);
        $after = (new OutboxRepository($this->app->db()))->find((int) $row['id']);

        match ($outcome) {
            'sent' => Flash::ok('Email inviata.'),
            'skipped' => Flash::error('Nessun indirizzo a cui scrivere: email saltata.'),
            'busy' => Flash::error('Questa email è già in corso di invio: riprova tra qualche istante.'),
            default => Flash::error('Email NON inviata: ' . (string) ($after['error_message'] ?? 'errore sconosciuto')),
        };
        return $this->redirect('/admin/email');
    }

    // === Cancellation draft ======================================================

    /** @param array<string, string> $params */
    public function draftForm(Request $request, array $params): Response
    {
        [$booking, $response] = $this->bookingForDraft($params);
        if ($response !== null) {
            return $response;
        }

        $locale = $this->locale($request, $booking);
        $draft = CancellationDraft::build($booking, $locale);
        return $this->draftPage($booking, $locale, $draft['subject'], $draft['body'], [], 200);
    }

    /** @param array<string, string> $params */
    public function draftSend(Request $request, array $params): Response
    {
        [$booking, $response] = $this->bookingForDraft($params);
        if ($response !== null) {
            return $response;
        }

        $locale = in_array($request->input('lingua'), ['it', 'en'], true) ? $request->input('lingua') : $this->locale($request, $booking);
        $subject = trim($request->input('subject'));
        $body = trim(str_replace("\r\n", "\n", $request->input('body')));

        $errors = [];
        if ($booking['email'] === null || trim((string) $booking['email']) === '') {
            $errors['body'] = 'La prenotazione non ha un indirizzo email: copia il testo e invialo con un altro mezzo.';
        }
        if ($subject === '' || mb_strlen($subject) > self::SUBJECT_MAX) {
            $errors['subject'] = 'Indica l\'oggetto (massimo ' . self::SUBJECT_MAX . ' caratteri).';
        }
        if ($body === '' || mb_strlen($body) > self::BODY_MAX) {
            $errors['body'] ??= 'Il testo è obbligatorio (massimo ' . self::BODY_MAX . ' caratteri).';
        } elseif (CancellationDraft::hasPlaceholder($body)) {
            $errors['body'] = 'Sostituisci o rimuovi il testo tra parentesi quadre prima di inviare.';
        }
        if ($errors !== []) {
            return $this->draftPage($booking, $locale, $subject, $body, $errors, 422);
        }

        // Only now, on an explicit admin action, the message is queued; it is sent after the
        // insert has committed, like every other e-mail.
        $db = $this->app->db();
        $outbox = new OutboxRepository($db);
        $id = $outbox->enqueue('cancellation', $booking['booking_request_id'] === null ? null : (int) $booking['booking_request_id'], (int) $booking['id'], $locale, $subject, $body);
        (new AuditLog($db))->record('booking', (int) $booking['id'], 'email_queued', 'Email di cancellazione inviata dall\'amministratore', null, ['outbox_id' => $id]);

        try {
            $this->app->services()->notifications()->dispatch($id);
        } catch (Throwable) {
            // dispatch() never throws; this is only a last line of defence.
        }
        $row = $outbox->find($id);

        match ($row['status'] ?? '') {
            'sent' => Flash::ok('Email di cancellazione inviata.'),
            'failed' => Flash::error('Email NON inviata: ' . (string) $row['error_message'] . '. Puoi riprovare dalla pagina Email.'),
            default => Flash::ok('Email di cancellazione in coda: verrà inviata a breve.'),
        };
        return $this->redirect('/admin/prenotazioni/' . $booking['id']);
    }

    /**
     * @param array<string, string> $params
     * @return array{0: array<string, mixed>|null, 1: Response|null}
     */
    private function bookingForDraft(array $params): array
    {
        $id = $this->id($params);
        $booking = $id === null ? null : $this->queries()->booking($id);
        if ($booking === null) {
            return [null, $this->notFound()];
        }
        if ($booking['status'] !== 'cancelled') {
            Flash::error('La bozza di cancellazione è disponibile solo dopo aver cancellato la prenotazione.');
            return [null, $this->redirect('/admin/prenotazioni/' . $booking['id'])];
        }
        return [$booking, null];
    }

    /** @param array<string, mixed> $booking */
    private function locale(Request $request, array $booking): string
    {
        if (in_array($request->query('lingua'), ['it', 'en'], true)) {
            return $request->query('lingua');
        }
        $linked = $booking['booking_request_id'] === null ? null : $this->queries()->request((int) $booking['booking_request_id']);
        return $linked === null ? 'it' : (string) $linked['locale'];
    }

    /**
     * @param array<string, mixed> $booking
     * @param array<string, string> $errors
     */
    private function draftPage(array $booking, string $locale, string $subject, string $body, array $errors, int $status): Response
    {
        $email = trim((string) ($booking['email'] ?? ''));
        $default = CancellationDraft::build($booking, $locale);
        // mailto: uses the default text (a link cannot follow edits made in the page).
        $mailto = $email === '' ? null : 'mailto:' . rawurlencode($email) . '?subject=' . rawurlencode($default['subject'])
            . (strlen($default['body']) <= 1500 ? '&body=' . rawurlencode($default['body']) : '');

        return $this->render('admin/email/draft', [
            'title' => 'Bozza di cancellazione',
            'booking' => $booking,
            'locale' => $locale,
            'subject' => $subject,
            'body' => $body,
            'errors' => $errors,
            'mailto' => $mailto,
            'hasEmail' => $email !== '',
        ], $status);
    }
}
