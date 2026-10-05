<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\BusyException;
use App\Domain\ConflictException;
use App\Domain\StateException;
use App\Domain\ValidationException;
use App\Http\Admin\ErrorMessages;
use App\Http\Admin\Flash;
use App\Http\Request;
use App\Http\Response;
use App\Service\BookingService;

final class BlockController extends BasePage
{
    public function index(Request $request): Response
    {
        return $this->page([], [], 200);
    }

    public function create(Request $request): Response
    {
        $values = [];
        foreach (['apartment_id', 'start_date', 'end_date', 'reason'] as $field) {
            $values[$field] = $request->input($field);
        }
        $apartmentId = preg_match('/^[1-9]\d{0,9}$/', $values['apartment_id']) ? (int) $values['apartment_id'] : 0;

        try {
            ($this->app->services()->bookingService())->createBlock($apartmentId, $values['start_date'], $values['end_date'], $values['reason'] ?: null);
        } catch (ValidationException $e) {
            return $this->page($values, $this->mapDateErrors(ErrorMessages::forFields($e->errors())), 422);
        } catch (ConflictException $e) {
            return $this->page($values, [], 409, ErrorMessages::conflict($e));
        } catch (StateException $e) {
            return $this->page($values, ['apartment_id' => ErrorMessages::forCode($e->reason)], 422);
        } catch (BusyException) {
            return $this->page($values, [], 503, 'Il sistema è occupato: riprova tra qualche istante.');
        }

        Flash::ok('Blocco creato: le date non sono più disponibili.');
        return $this->redirect('/admin/blocchi');
    }

    /** @param array<string, string> $params */
    public function remove(Request $request, array $params): Response
    {
        $id = $this->id($params);
        if ($id === null) {
            return $this->notFound();
        }
        return $this->attempt(
            fn () => ($this->app->services()->bookingService())->removeBlock($id),
            'Blocco rimosso: le date sono di nuovo disponibili.',
            '/admin/blocchi',
            '/admin/blocchi',
        );
    }

    /** The service reports date errors on check_in/check_out; the form calls them start/end. @param array<string, string> $errors @return array<string, string> */
    private function mapDateErrors(array $errors): array
    {
        $map = ['check_in' => 'start_date', 'check_out' => 'end_date'];
        $out = [];
        foreach ($errors as $field => $message) {
            $out[$map[$field] ?? $field] = $message;
        }
        return $out;
    }

    /** @param array<string, string> $values @param array<string, string> $errors */
    private function page(array $values, array $errors, int $status, ?string $notice = null): Response
    {
        $queries = $this->queries();
        return $this->render('admin/blocks/index', [
            'title' => 'Blocchi di disponibilità',
            'rows' => $queries->blocks(),
            'apartments' => $queries->apartments(),
            'values' => $values,
            'errors' => $errors,
            'notice' => $notice,
        ], $status);
    }
}
