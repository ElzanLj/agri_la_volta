<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\BusyException;
use App\Domain\Money;
use App\Domain\StateException;
use App\Domain\ValidationException;
use App\Http\Admin\ErrorMessages;
use App\Http\Admin\Flash;
use App\Http\Admin\Labels;
use App\Http\Request;
use App\Http\Response;
use App\Service\ApartmentAdminService;

final class ApartmentController extends BasePage
{
    private const FIELDS = [
        'name', 'management_mode', 'managing_agency', 'max_guests', 'max_children', 'max_pets', 'bedrooms', 'beds',
        'check_in_from', 'check_in_until', 'check_out_until', 'indicative_price', 'sort_order',
    ];
    private const TEXTS = ['description', 'rules', 'meta_title', 'meta_description'];

    public function index(Request $request): Response
    {
        return $this->render('admin/apartments/index', ['title' => 'Appartamenti', 'rows' => $this->queries()->apartments()]);
    }

    /** @param array<string, string> $params */
    public function edit(Request $request, array $params): Response
    {
        $id = $this->id($params);
        $row = $id === null ? null : $this->queries()->apartment($id);
        if ($row === null) {
            return $this->notFound();
        }
        return $this->form($row, $this->valuesFromDatabase($row, $this->queries()->apartmentTranslations((int) $row['id'])), [], 200);
    }

    /** @param array<string, string> $params */
    public function update(Request $request, array $params): Response
    {
        $id = $this->id($params);
        $row = $id === null ? null : $this->queries()->apartment($id);
        if ($row === null) {
            return $this->notFound();
        }

        $values = [];
        foreach (self::FIELDS as $field) {
            $values[$field] = $request->input($field);
        }
        foreach (['it', 'en'] as $locale) {
            foreach (self::TEXTS as $field) {
                $values["{$field}_{$locale}"] = $request->input("{$field}_{$locale}");
            }
        }
        $values['is_active'] = $request->input('is_active') === '1' ? '1' : '0';
        $values['accepts_online_requests'] = $request->input('accepts_online_requests') === '1' ? '1' : '0';

        try {
            (new ApartmentAdminService($this->app->db()))->update((int) $row['id'], $values);
        } catch (ValidationException $e) {
            return $this->form($row, $values, ErrorMessages::forFields($e->errors()), 422);
        } catch (StateException $e) {
            Flash::error(ErrorMessages::forCode($e->reason));
            return $this->redirect('/admin/appartamenti');
        } catch (BusyException) {
            return $this->form($row, $values, [], 503, 'Il sistema è occupato: riprova tra qualche istante.');
        }

        Flash::ok('Appartamento aggiornato.');
        return $this->redirect('/admin/appartamenti');
    }

    /**
     * @param array<string, mixed> $row
     * @param array<string, string> $values
     * @param array<string, string> $errors
     */
    private function form(array $row, array $values, array $errors, int $status, ?string $notice = null): Response
    {
        return $this->render('admin/apartments/edit', [
            'title' => 'Appartamento ' . $row['name'],
            'row' => $row,
            'values' => $values,
            'errors' => $errors,
            'notice' => $notice,
        ], $status);
    }

    /**
     * @param array<string, mixed> $row
     * @param array<string, array<string, mixed>> $translations
     * @return array<string, string>
     */
    private function valuesFromDatabase(array $row, array $translations): array
    {
        $values = [];
        foreach (['name', 'management_mode', 'managing_agency', 'max_guests', 'max_children', 'max_pets', 'bedrooms', 'beds', 'sort_order'] as $field) {
            $values[$field] = $row[$field] === null ? '' : (string) $row[$field];
        }
        foreach (['check_in_from', 'check_in_until', 'check_out_until'] as $field) {
            $values[$field] = Labels::time($row[$field]);
        }
        $values['indicative_price'] = $row['indicative_price_cents'] === null ? '' : Money::plain((int) $row['indicative_price_cents']);
        $values['is_active'] = (string) (int) $row['is_active'];
        $values['accepts_online_requests'] = (string) (int) $row['accepts_online_requests'];
        foreach (['it', 'en'] as $locale) {
            foreach (self::TEXTS as $field) {
                $values["{$field}_{$locale}"] = (string) ($translations[$locale][$field] ?? '');
            }
        }
        return $values;
    }
}
