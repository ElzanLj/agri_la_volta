<?php

declare(strict_types=1);

namespace App\Http\Admin;

use App\Domain\StayDates;
use App\Http\Request;

/**
 * Validated list filters read from the query string: status, apartment, origin, period, page.
 * Invalid values never reach the SQL layer; they are reported and the list is shown empty.
 */
final class ListFilters
{
    public const PER_PAGE = 50;

    /** @param array<string, string> $errors */
    private function __construct(
        public readonly ?string $status,
        public readonly ?int $apartmentId,
        public readonly ?string $origin,
        public readonly ?string $from,
        public readonly ?string $to,
        public readonly ?string $entity,
        public readonly int $page,
        public readonly array $errors,
    ) {
    }

    /**
     * @param array<string, string> $allowedStatuses value => label
     * @param array<string, string> $allowedEntities value => label (only for the audit log)
     */
    public static function fromRequest(Request $request, array $allowedStatuses = [], bool $withOrigin = false, array $allowedEntities = []): self
    {
        $errors = [];
        foreach (['stato', 'appartamento', 'origine', 'tipo', 'dal', 'al', 'pagina'] as $key) {
            if ($request->queryIsArray($key)) {
                $errors[$key] = 'Parametro non valido.';
            }
        }

        $status = $request->query('stato');
        if ($status !== '' && !isset($allowedStatuses[$status])) {
            $errors['stato'] = 'Stato non valido.';
        }

        $apartment = $request->query('appartamento');
        $apartmentId = null;
        if ($apartment !== '') {
            if (preg_match('/^\d{1,9}\z/', $apartment)) {
                $apartmentId = (int) $apartment;
            } else {
                $errors['appartamento'] = 'Appartamento non valido.';
            }
        }

        $origin = $request->query('origine');
        if ($origin !== '' && (!$withOrigin || !isset(Labels::ORIGINS[$origin]))) {
            $errors['origine'] = 'Origine non valida.';
        }

        $entity = $request->query('tipo');
        if ($entity !== '' && !isset($allowedEntities[$entity])) {
            $errors['tipo'] = 'Tipo non valido.';
        }

        $from = $request->query('dal');
        $to = $request->query('al');
        foreach (['dal' => $from, 'al' => $to] as $field => $value) {
            if ($value !== '' && !StayDates::isValidDate($value)) {
                $errors[$field] = 'Data non valida.';
            }
        }
        if ($from !== '' && $to !== '' && !isset($errors['dal']) && !isset($errors['al']) && $to <= $from) {
            $errors['al'] = 'La data finale deve essere successiva a quella iniziale.';
        }

        $page = $request->query('pagina');
        $pageNumber = 1;
        if ($page !== '') {
            if (preg_match('/^\d{1,6}\z/', $page) && (int) $page >= 1) {
                $pageNumber = (int) $page;
            } else {
                $errors['pagina'] = 'Pagina non valida.';
            }
        }

        return new self(
            ($status === '' || isset($errors['stato'])) ? null : $status,
            $apartmentId,
            ($origin === '' || isset($errors['origine'])) ? null : $origin,
            ($from === '' || isset($errors['dal'])) ? null : $from,
            ($to === '' || isset($errors['al'])) ? null : $to,
            ($entity === '' || isset($errors['tipo'])) ? null : $entity,
            $pageNumber,
            $errors,
        );
    }

    public function isValid(): bool
    {
        return $this->errors === [];
    }

    public function offset(): int
    {
        return ($this->page - 1) * self::PER_PAGE;
    }

    /** Query string for links that keep the current filters (without the page). @return array<string, string> */
    public function queryParams(): array
    {
        return array_filter([
            'stato' => $this->status,
            'appartamento' => $this->apartmentId === null ? null : (string) $this->apartmentId,
            'origine' => $this->origin,
            'dal' => $this->from,
            'al' => $this->to,
            'tipo' => $this->entity,
        ], static fn (?string $v): bool => $v !== null && $v !== '');
    }
}
