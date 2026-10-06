<?php

declare(strict_types=1);

namespace App\Service;

use App\Database\TransactionRunner;
use App\Domain\BusyException;
use App\Domain\Money;
use App\Domain\StateException;
use App\Domain\ValidationException;
use App\Repository\AdminQueryRepository;
use App\Repository\ApartmentRepository;
use App\Site\Amenities;
use App\Support\AuditLog;
use PDO;

/**
 * Admin edits to the apartment details (SPEC §4). The slug is immutable so public URLs never change.
 * Photos are not handled here (later phase); amenities are a plain-text field, one per line.
 */
final class ApartmentAdminService
{
    private const TEXT_FIELDS = ['description' => 5000, 'rules' => 5000, 'amenities' => 2000, 'meta_title' => 255, 'meta_description' => 300];
    private const NUMBER_LIMITS = ['max_guests' => [1, 50], 'max_children' => [0, 20], 'max_pets' => [0, 20], 'bedrooms' => [0, 30], 'beds' => [0, 60]];
    private const TIME_FIELDS = ['check_in_from', 'check_in_until', 'check_out_until'];

    private ApartmentRepository $apartments;
    private AdminQueryRepository $queries;
    private AuditLog $audit;
    private TransactionRunner $tx;

    public function __construct(private PDO $db)
    {
        $this->apartments = new ApartmentRepository($db);
        $this->queries = new AdminQueryRepository($db);
        $this->audit = new AuditLog($db);
        $this->tx = new TransactionRunner($db);
    }

    /**
     * @param array<string, mixed> $input name, is_active, accepts_online_requests, management_mode, managing_agency,
     *        max_guests, max_children, max_pets, bedrooms, beds, check_in_from, check_in_until, check_out_until,
     *        indicative_price (euros), sort_order, and {description,rules,amenities,meta_title,meta_description}_{it,en}
     * @throws ValidationException|StateException|BusyException
     */
    public function update(int $id, array $input): void
    {
        $this->apartments->find($id) ?? throw new StateException('apartment_not_found');
        [$apartment, $translations] = $this->validate($input);

        $this->tx->run(function () use ($id, $apartment, $translations): void {
            $this->apartments->lockForUpdate($id) ?? throw new StateException('apartment_not_found');
            $oldRow = $this->queries->apartment($id) ?? throw new StateException('apartment_not_found');
            $oldTranslations = $this->queries->apartmentTranslations($id);

            $old = [];
            $new = [];
            foreach ($apartment as $field => $value) {
                $before = $oldRow[$field];
                if ($before !== null && !is_string($before)) {
                    $before = (int) $before;
                }
                if (in_array($field, self::TIME_FIELDS, true) && is_string($before)) {
                    $before = substr($before, 0, 8);
                }
                if ($before !== $value) {
                    $old[$field] = $before;
                    $new[$field] = $value;
                }
            }

            $this->db->prepare(
                'UPDATE apartments SET name = ?, is_active = ?, accepts_online_requests = ?, management_mode = ?, managing_agency = ?,
                        max_guests = ?, max_children = ?, max_pets = ?, bedrooms = ?, beds = ?,
                        check_in_from = ?, check_in_until = ?, check_out_until = ?, indicative_price_cents = ?, sort_order = ?
                 WHERE id = ?'
            )->execute([
                $apartment['name'], $apartment['is_active'], $apartment['accepts_online_requests'], $apartment['management_mode'], $apartment['managing_agency'],
                $apartment['max_guests'], $apartment['max_children'], $apartment['max_pets'], $apartment['bedrooms'], $apartment['beds'],
                $apartment['check_in_from'], $apartment['check_in_until'], $apartment['check_out_until'], $apartment['indicative_price_cents'], $apartment['sort_order'], $id,
            ]);

            foreach ($translations as $locale => $texts) {
                $existing = $oldTranslations[$locale] ?? null;
                if ($existing === null && !array_filter($texts, static fn (?string $t): bool => $t !== null)) {
                    continue; // nothing entered for this language
                }
                foreach ($texts as $field => $text) {
                    $before = $existing[$field] ?? null;
                    if ($before !== $text) {
                        $old["{$locale}.{$field}"] = $before;
                        $new["{$locale}.{$field}"] = $text;
                    }
                }
                $this->db->prepare(
                    'INSERT INTO apartment_translations (apartment_id, locale, description, rules, amenities, meta_title, meta_description)
                     VALUES (?, ?, ?, ?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE description = VALUES(description), rules = VALUES(rules), amenities = VALUES(amenities),
                         meta_title = VALUES(meta_title), meta_description = VALUES(meta_description)'
                )->execute([$id, $locale, $texts['description'], $texts['rules'], $texts['amenities'], $texts['meta_title'], $texts['meta_description']]);
            }

            if ($new !== []) {
                $this->audit->record('apartment', $id, 'updated', 'Appartamento modificato: ' . (string) $oldRow['name'], $old, $new);
            }
        });
    }

    /**
     * @param array<string, mixed> $input
     * @return array{array<string, mixed>, array<string, array<string, ?string>>}
     * @throws ValidationException
     */
    private function validate(array $input): array
    {
        $errors = [];
        $str = static fn (string $k): string => is_string($input[$k] ?? null) ? trim($input[$k]) : '';

        $name = $str('name');
        if (mb_strlen($name) < 1 || mb_strlen($name) > 100) {
            $errors['name'] = 'invalid_name';
        }

        $mode = $str('management_mode') ?: 'direct';
        if (!in_array($mode, ['direct', 'agency'], true)) {
            $errors['management_mode'] = 'invalid_management_mode';
        }
        $agency = $str('managing_agency');
        if (mb_strlen($agency) > 100) {
            $errors['managing_agency'] = 'invalid_agency';
        }

        $apartment = [
            'name' => $name,
            'is_active' => (int) filter_var($input['is_active'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'accepts_online_requests' => (int) filter_var($input['accepts_online_requests'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'management_mode' => $mode,
            'managing_agency' => ($mode === 'agency' && $agency !== '') ? $agency : null,
        ];

        foreach (self::NUMBER_LIMITS as $field => [$min, $max]) {
            $raw = $str($field);
            if ($raw === '') {
                $apartment[$field] = null;
            } elseif (preg_match('/^\d{1,3}$/', $raw) && (int) $raw >= $min && (int) $raw <= $max) {
                $apartment[$field] = (int) $raw;
            } else {
                $apartment[$field] = null;
                $errors[$field] = 'invalid_number';
            }
        }

        foreach (self::TIME_FIELDS as $field) {
            $raw = $str($field);
            if ($raw === '') {
                $apartment[$field] = null;
            } elseif (preg_match('/^([01]\d|2[0-3]):([0-5]\d)(:00)?$/', $raw, $m)) {
                $apartment[$field] = $m[1] . ':' . $m[2] . ':00';
            } else {
                $apartment[$field] = null;
                $errors[$field] = 'invalid_time';
            }
        }

        $price = $str('indicative_price');
        if ($price === '') {
            $apartment['indicative_price_cents'] = null;
        } else {
            $cents = Money::parse($price);
            if ($cents === null) {
                $errors['indicative_price'] = 'invalid_amount';
            }
            $apartment['indicative_price_cents'] = $cents;
        }

        $sort = $str('sort_order') === '' ? '0' : $str('sort_order');
        if (preg_match('/^-?\d{1,4}$/', $sort) && (int) $sort >= -1000 && (int) $sort <= 1000) {
            $apartment['sort_order'] = (int) $sort;
        } else {
            $apartment['sort_order'] = 0;
            $errors['sort_order'] = 'invalid_sort_order';
        }

        $translations = [];
        foreach (['it', 'en'] as $locale) {
            foreach (self::TEXT_FIELDS as $field => $max) {
                $text = $str("{$field}_{$locale}");
                if (mb_strlen($text) > $max) {
                    $errors["{$field}_{$locale}"] = 'text_too_long';
                }
                if ($field === 'amenities') {
                    // One amenity per line: short lines, a sensible number of them.
                    if (!Amenities::isValid($text)) {
                        $errors["{$field}_{$locale}"] = 'invalid_amenities';
                    }
                    $translations[$locale][$field] = Amenities::normalise($text);
                    continue;
                }
                $translations[$locale][$field] = $text === '' ? null : $text;
            }
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }
        return [$apartment, $translations];
    }
}
