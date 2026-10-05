<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\BusyException;
use App\Domain\ConflictException;
use App\Domain\Money;
use App\Domain\StateException;
use App\Domain\ValidationException;
use App\Http\Admin\ErrorMessages;
use App\Http\Admin\Flash;
use App\Http\Admin\Labels;
use App\Http\Admin\ListFilters;
use App\Http\Request;
use App\Http\Response;
use App\Service\BookingService;

final class BookingController extends BasePage
{
    public function index(Request $request): Response
    {
        $filters = ListFilters::fromRequest($request, Labels::BOOKING_STATUSES, withOrigin: true);
        $valid = $filters->isValid();
        $queries = $this->queries();

        return $this->render('admin/bookings/index', [
            'title' => 'Prenotazioni',
            'filters' => $filters,
            'rows' => $valid ? $queries->bookings($filters) : [],
            'pagination' => $this->pagination($filters->page, $valid ? $queries->countBookings($filters) : 0, ListFilters::PER_PAGE),
            'apartments' => $queries->apartments(),
        ], $valid ? 200 : 400);
    }

    /** @param array<string, string> $params */
    public function show(Request $request, array $params): Response
    {
        $id = $this->id($params);
        $row = $id === null ? null : $this->queries()->booking($id);
        if ($row === null) {
            return $this->notFound();
        }
        return $this->render('admin/bookings/show', ['title' => 'Prenotazione n. ' . $row['id'], 'row' => $row]);
    }

    /** Confirmation page: nothing changes on GET. @param array<string, string> $params */
    public function cancelForm(Request $request, array $params): Response
    {
        $id = $this->id($params);
        $row = $id === null ? null : $this->queries()->booking($id);
        if ($row === null) {
            return $this->notFound();
        }
        if ($row['status'] !== 'confirmed') {
            Flash::error(ErrorMessages::forCode('booking_not_confirmed'));
            return $this->redirect('/admin/prenotazioni/' . $id);
        }
        return $this->render('admin/bookings/cancel', ['title' => 'Cancella prenotazione n. ' . $row['id'], 'row' => $row, 'errors' => [], 'reason' => '']);
    }

    /** @param array<string, string> $params */
    public function cancel(Request $request, array $params): Response
    {
        $id = $this->id($params);
        if ($id === null) {
            return $this->notFound();
        }
        return $this->attempt(
            fn () => (new BookingService($this->app->db()))->cancelBooking($id, $request->input('motivo') ?: null),
            'Prenotazione cancellata: le date sono di nuovo disponibili. L\'email di cancellazione sarà una bozza (non ancora disponibile).',
            '/admin/prenotazioni/' . $id,
            '/admin/prenotazioni/' . $id,
        );
    }

    public function newForm(Request $request): Response
    {
        return $this->form([], [], 200);
    }

    public function create(Request $request): Response
    {
        $values = [];
        foreach (['apartment_id', 'origin', 'check_in', 'check_out', 'adults', 'children', 'pets', 'guest_name', 'email', 'phone', 'total', 'notes'] as $field) {
            $values[$field] = $request->input($field);
        }

        // An emptied counter means zero, as the form shows by default.
        foreach (['children', 'pets'] as $counter) {
            if (trim($values[$counter]) === '') {
                $values[$counter] = '0';
            }
        }

        $total = trim($values['total']);
        $input = $values + [
            // "invalid" makes the service report invalid_total instead of silently dropping the value.
            'total_cents' => $total === '' ? null : (Money::parse($total) ?? 'invalid'),
        ];

        try {
            $bookingId = (new BookingService($this->app->db()))->createManualBooking($input);
        } catch (ValidationException $e) {
            return $this->form($values, ErrorMessages::forFields($e->errors()), 422);
        } catch (ConflictException $e) {
            return $this->form($values, [], 409, ErrorMessages::conflict($e));
        } catch (StateException $e) {
            return $this->form($values, ['apartment_id' => ErrorMessages::forCode($e->reason)], 422);
        } catch (BusyException) {
            return $this->form($values, [], 503, 'Il sistema è occupato: riprova tra qualche istante.');
        }

        Flash::ok('Prenotazione registrata.');
        return $this->redirect('/admin/prenotazioni/' . $bookingId);
    }

    /** @param array<string, string> $values @param array<string, string> $errors */
    private function form(array $values, array $errors, int $status, ?string $notice = null): Response
    {
        $errors['total'] = $errors['total_cents'] ?? $errors['total'] ?? '';
        if ($errors['total'] === '') {
            unset($errors['total']);
        }
        return $this->render('admin/bookings/new', [
            'title' => 'Nuova prenotazione',
            'values' => $values,
            'errors' => $errors,
            'notice' => $notice,
            'apartments' => $this->queries()->apartments(),
        ], $status);
    }
}
