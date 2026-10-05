<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\BusyException;
use App\Domain\Money;
use App\Domain\StateException;
use App\Domain\ValidationException;
use App\Http\Admin\ErrorMessages;
use App\Http\Admin\Flash;
use App\Http\Request;
use App\Http\Response;
use App\Service\PricingConfigService;
use DateTimeImmutable;
use DateTimeZone;

/** Price list editor: rate periods and charge rules. All writes go through PricingConfigService. */
final class PricingController extends BasePage
{
    public function index(Request $request): Response
    {
        $queries = $this->queries();
        $apartments = $queries->apartments();
        if ($apartments === []) {
            return $this->notFound();
        }

        $wanted = $request->query('appartamento');
        $selected = $apartments[0];
        foreach ($apartments as $apartment) {
            if ((string) $apartment['id'] === $wanted) {
                $selected = $apartment;
            }
        }

        $today = $this->today();
        $horizon = (new DateTimeImmutable($today, new DateTimeZone('UTC')))->modify('+12 months')->format('Y-m-d');

        return $this->render('admin/pricing/index', [
            'title' => 'Listino prezzi',
            'apartments' => $apartments,
            'selected' => $selected,
            'rates' => $queries->ratesOf((int) $selected['id']),
            'rules' => $queries->rulesOf((int) $selected['id']),
            'gaps' => (new PricingConfigService($this->app->db()))->coverageGaps((int) $selected['id'], $today, $horizon),
            'apartmentNames' => array_column($apartments, 'name', 'id'),
        ]);
    }

    // === Rate periods ========================================================

    public function rateNew(Request $request): Response
    {
        $apartmentId = $this->apartmentFromQuery($request);
        return $this->rateForm(null, ['apartment_id' => (string) $apartmentId, 'is_active' => '1'], [], 200);
    }

    public function rateCreate(Request $request): Response
    {
        $values = $this->rateValues($request);
        $input = $this->rateInput($values);

        try {
            (new PricingConfigService($this->app->db()))->createRate($input);
        } catch (ValidationException $e) {
            return $this->rateForm(null, $values, $this->rateErrors($e), 422);
        } catch (StateException $e) {
            return $this->rateForm(null, $values, ['apartment_id' => ErrorMessages::forCode($e->reason)], 422);
        } catch (BusyException) {
            return $this->rateForm(null, $values, [], 503, 'Il sistema è occupato: riprova tra qualche istante.');
        }

        Flash::ok('Tariffa creata.');
        return $this->redirect('/admin/listino?appartamento=' . urlencode($values['apartment_id']));
    }

    /** @param array<string, string> $params */
    public function rateEdit(Request $request, array $params): Response
    {
        $id = $this->id($params);
        $row = $id === null ? null : $this->queries()->rate($id);
        if ($row === null) {
            return $this->notFound();
        }
        return $this->rateForm((int) $row['id'], [
            'apartment_id' => (string) $row['apartment_id'],
            'label_it' => (string) $row['label'],
            'label_en' => (string) ($row['label_en'] ?? ''),
            'start_date' => (string) $row['start_date'],
            'end_date' => (string) $row['end_date'],
            'nightly_rate' => Money::plain((int) $row['nightly_rate_cents']),
            'min_nights' => $row['min_nights'] === null ? '' : (string) $row['min_nights'],
            'is_active' => (string) (int) $row['is_active'],
        ], [], 200);
    }

    /** @param array<string, string> $params */
    public function rateUpdate(Request $request, array $params): Response
    {
        $id = $this->id($params);
        $row = $id === null ? null : $this->queries()->rate($id);
        if ($row === null) {
            return $this->notFound();
        }

        $values = $this->rateValues($request) + [];
        $values['apartment_id'] = (string) $row['apartment_id'];

        try {
            (new PricingConfigService($this->app->db()))->updateRate((int) $row['id'], $this->rateInput($values));
        } catch (ValidationException $e) {
            return $this->rateForm((int) $row['id'], $values, $this->rateErrors($e), 422);
        } catch (StateException $e) {
            Flash::error(ErrorMessages::forCode($e->reason));
            return $this->redirect('/admin/listino');
        } catch (BusyException) {
            return $this->rateForm((int) $row['id'], $values, [], 503, 'Il sistema è occupato: riprova tra qualche istante.');
        }

        Flash::ok('Tariffa aggiornata.');
        return $this->redirect('/admin/listino?appartamento=' . $row['apartment_id']);
    }

    /** @param array<string, string> $params */
    public function rateDelete(Request $request, array $params): Response
    {
        $id = $this->id($params);
        $row = $id === null ? null : $this->queries()->rate($id);
        if ($row === null) {
            return $this->notFound();
        }
        return $this->attempt(
            fn () => (new PricingConfigService($this->app->db()))->deleteRate((int) $row['id']),
            'Tariffa eliminata.',
            '/admin/listino?appartamento=' . $row['apartment_id'],
            '/admin/listino?appartamento=' . $row['apartment_id'],
        );
    }

    // === Charge rules ========================================================

    public function ruleNew(Request $request): Response
    {
        return $this->ruleForm(null, ['apartment_id' => $request->query('appartamento'), 'is_active' => '1', 'free_units' => '0', 'sort_order' => '0'], [], 200);
    }

    public function ruleCreate(Request $request): Response
    {
        $values = $this->ruleValues($request);

        try {
            (new PricingConfigService($this->app->db()))->createRule($this->ruleInput($values));
        } catch (ValidationException $e) {
            return $this->ruleForm(null, $values, $this->ruleErrors($e), 422);
        } catch (StateException $e) {
            return $this->ruleForm(null, $values, ['apartment_id' => ErrorMessages::forCode($e->reason)], 422);
        } catch (BusyException) {
            return $this->ruleForm(null, $values, [], 503, 'Il sistema è occupato: riprova tra qualche istante.');
        }

        Flash::ok('Regola creata.');
        return $this->redirect('/admin/listino' . ($values['apartment_id'] !== '' ? '?appartamento=' . urlencode($values['apartment_id']) : ''));
    }

    /** @param array<string, string> $params */
    public function ruleEdit(Request $request, array $params): Response
    {
        $id = $this->id($params);
        $row = $id === null ? null : $this->queries()->rule($id);
        if ($row === null) {
            return $this->notFound();
        }
        return $this->ruleForm((int) $row['id'], [
            'apartment_id' => $row['apartment_id'] === null ? '' : (string) $row['apartment_id'],
            'applies_to' => (string) $row['applies_to'],
            'charge_basis' => (string) $row['charge_basis'],
            'free_units' => (string) $row['free_units'],
            'amount' => Money::plain((int) $row['amount_cents']),
            'valid_from' => (string) ($row['valid_from'] ?? ''),
            'valid_to' => (string) ($row['valid_to'] ?? ''),
            'sort_order' => (string) $row['sort_order'],
            'label_it' => (string) $row['label_it'],
            'label_en' => (string) ($row['label_en'] ?? ''),
            'is_active' => (string) (int) $row['is_active'],
        ], [], 200);
    }

    /** @param array<string, string> $params */
    public function ruleUpdate(Request $request, array $params): Response
    {
        $id = $this->id($params);
        $row = $id === null ? null : $this->queries()->rule($id);
        if ($row === null) {
            return $this->notFound();
        }

        $values = $this->ruleValues($request);

        try {
            (new PricingConfigService($this->app->db()))->updateRule((int) $row['id'], $this->ruleInput($values));
        } catch (ValidationException $e) {
            return $this->ruleForm((int) $row['id'], $values, $this->ruleErrors($e), 422);
        } catch (StateException $e) {
            return $this->ruleForm((int) $row['id'], $values, ['apartment_id' => ErrorMessages::forCode($e->reason)], 422);
        } catch (BusyException) {
            return $this->ruleForm((int) $row['id'], $values, [], 503, 'Il sistema è occupato: riprova tra qualche istante.');
        }

        Flash::ok('Regola aggiornata.');
        return $this->redirect('/admin/listino' . ($values['apartment_id'] !== '' ? '?appartamento=' . urlencode($values['apartment_id']) : ''));
    }

    /** @param array<string, string> $params */
    public function ruleDelete(Request $request, array $params): Response
    {
        $id = $this->id($params);
        $row = $id === null ? null : $this->queries()->rule($id);
        if ($row === null) {
            return $this->notFound();
        }
        $back = '/admin/listino' . ($row['apartment_id'] !== null ? '?appartamento=' . $row['apartment_id'] : '');
        return $this->attempt(
            fn () => (new PricingConfigService($this->app->db()))->deleteRule((int) $row['id']),
            'Regola eliminata.',
            $back,
            $back,
        );
    }

    // === Form plumbing ===========================================================

    private function apartmentFromQuery(Request $request): int
    {
        $wanted = $request->query('appartamento');
        $apartments = $this->queries()->apartments();
        foreach ($apartments as $apartment) {
            if ((string) $apartment['id'] === $wanted) {
                return (int) $apartment['id'];
            }
        }
        return (int) ($apartments[0]['id'] ?? 0);
    }

    /** @return array<string, string> */
    private function rateValues(Request $request): array
    {
        $values = [];
        foreach (['apartment_id', 'label_it', 'label_en', 'start_date', 'end_date', 'nightly_rate', 'min_nights'] as $field) {
            $values[$field] = $request->input($field);
        }
        $values['is_active'] = $request->input('is_active') === '1' ? '1' : '0';
        return $values;
    }

    /**
     * @param array<string, string> $values
     * @return array<string, mixed>
     */
    private function rateInput(array $values): array
    {
        return [
            'apartment_id' => $values['apartment_id'],
            'label_it' => $values['label_it'],
            'label_en' => $values['label_en'],
            'start_date' => $values['start_date'],
            'end_date' => $values['end_date'],
            // An unparsable amount is passed as text so the service reports invalid_amount.
            'nightly_rate_cents' => Money::parse($values['nightly_rate']) ?? 'invalid',
            'min_nights' => $values['min_nights'],
            'is_active' => $values['is_active'] === '1',
        ];
    }

    /** @return array<string, string> */
    private function rateErrors(ValidationException $e): array
    {
        $errors = ErrorMessages::forFields($e->errors());
        if (isset($errors['nightly_rate_cents'])) {
            $errors['nightly_rate'] = $errors['nightly_rate_cents'];
        }
        return $errors;
    }

    /** @return array<string, string> */
    private function ruleValues(Request $request): array
    {
        $values = [];
        foreach (['apartment_id', 'applies_to', 'charge_basis', 'free_units', 'amount', 'valid_from', 'valid_to', 'sort_order', 'label_it', 'label_en'] as $field) {
            $values[$field] = $request->input($field);
        }
        foreach (['free_units', 'sort_order'] as $number) {
            if (trim($values[$number]) === '') {
                $values[$number] = '0';
            }
        }
        $values['is_active'] = $request->input('is_active') === '1' ? '1' : '0';
        return $values;
    }

    /**
     * @param array<string, string> $values
     * @return array<string, mixed>
     */
    private function ruleInput(array $values): array
    {
        return [
            'apartment_id' => $values['apartment_id'],
            'applies_to' => $values['applies_to'],
            'charge_basis' => $values['charge_basis'],
            'free_units' => $values['free_units'],
            'amount_cents' => Money::parse($values['amount']) ?? 'invalid',
            'valid_from' => $values['valid_from'],
            'valid_to' => $values['valid_to'],
            'sort_order' => $values['sort_order'],
            'label_it' => $values['label_it'],
            'label_en' => $values['label_en'],
            'is_active' => $values['is_active'] === '1',
        ];
    }

    /** @return array<string, string> */
    private function ruleErrors(ValidationException $e): array
    {
        $errors = ErrorMessages::forFields($e->errors());
        if (isset($errors['amount_cents'])) {
            $errors['amount'] = $errors['amount_cents'];
        }
        return $errors;
    }

    /** @param array<string, string> $values @param array<string, string> $errors */
    private function rateForm(?int $id, array $values, array $errors, int $status, ?string $notice = null): Response
    {
        return $this->render('admin/pricing/rate_form', [
            'title' => $id === null ? 'Nuova tariffa' : 'Modifica tariffa',
            'id' => $id,
            'values' => $values,
            'errors' => $errors,
            'notice' => $notice,
            'apartments' => $this->queries()->apartments(),
        ], $status);
    }

    /** @param array<string, string> $values @param array<string, string> $errors */
    private function ruleForm(?int $id, array $values, array $errors, int $status, ?string $notice = null): Response
    {
        return $this->render('admin/pricing/rule_form', [
            'title' => $id === null ? 'Nuova regola' : 'Modifica regola',
            'id' => $id,
            'values' => $values,
            'errors' => $errors,
            'notice' => $notice,
            'apartments' => $this->queries()->apartments(),
        ], $status);
    }
}
