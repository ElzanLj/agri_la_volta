<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Domain\StateException;
use App\Domain\ValidationException;
use App\Service\ApartmentAdminService;
use Tests\Support\DatabaseTestCase;

final class ApartmentAdminServiceTest extends DatabaseTestCase
{
    private ApartmentAdminService $service2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service2 = new ApartmentAdminService($this->db);
    }

    /** @param array<string, mixed> $overrides @return array<string, mixed> */
    private function input(array $overrides = []): array
    {
        return $overrides + [
            'name' => 'Margherita', 'is_active' => '1', 'accepts_online_requests' => '1', 'management_mode' => 'direct', 'managing_agency' => '',
            'max_guests' => '', 'max_children' => '', 'max_pets' => '', 'bedrooms' => '', 'beds' => '',
            'check_in_from' => '', 'check_in_until' => '', 'check_out_until' => '', 'indicative_price' => '', 'sort_order' => '1',
        ];
    }

    /** @param array<string, string> $expected */
    private function assertRejected(array $input, array $expected): void
    {
        try {
            $this->service2->update($this->apartmentId(), $input);
            self::fail('Expected a ValidationException');
        } catch (ValidationException $e) {
            foreach ($expected as $field => $code) {
                self::assertSame($code, $e->errors()[$field] ?? null, "field $field");
            }
        }
    }

    public function testUpdateStoresValuesAndAuditsOnlyWhatChanged(): void
    {
        $id = $this->apartmentId();

        $this->service2->update($id, $this->input(['name' => 'Margherita Nuova', 'max_guests' => '4', 'check_in_from' => '15:00', 'indicative_price' => '80,5']));

        $row = $this->row('SELECT * FROM apartments WHERE id = ?', [$id]);
        self::assertSame(['margherita', 'Margherita Nuova', 4, '15:00:00', 8050], [$row['slug'], $row['name'], (int) $row['max_guests'], $row['check_in_from'], (int) $row['indicative_price_cents']]);

        $audit = $this->row("SELECT * FROM audit_log WHERE entity_type = 'apartment'");
        self::assertSame('updated', $audit['action']);
        self::assertEqualsCanonicalizing(['name', 'max_guests', 'check_in_from', 'indicative_price_cents'], array_keys(json_decode($audit['new_values'], true)));
        self::assertSame('Margherita', json_decode($audit['old_values'], true)['name']);
        self::assertNull(json_decode($audit['old_values'], true)['max_guests']);
        self::assertSame('Margherita Nuova', json_decode($audit['new_values'], true)['name']);
    }

    public function testSavingTheSameDataAgainWritesNoAuditEntry(): void
    {
        $this->service2->update($this->apartmentId(), $this->input(['max_guests' => '4', 'check_in_from' => '15:00']));
        $this->service2->update($this->apartmentId(), $this->input(['max_guests' => '4', 'check_in_from' => '15:00']));

        self::assertSame(1, (int) $this->scalar("SELECT COUNT(*) FROM audit_log WHERE entity_type = 'apartment'"));
    }

    public function testTranslationsAreCreatedUpdatedAndAudited(): void
    {
        $id = $this->apartmentId();
        $this->service2->update($id, $this->input(['description_it' => 'Descrizione', 'rules_en' => 'Rules']));

        self::assertSame('Descrizione', $this->scalar("SELECT description FROM apartment_translations WHERE apartment_id = ? AND locale = 'it'", [$id]));
        self::assertSame('Rules', $this->scalar("SELECT rules FROM apartment_translations WHERE apartment_id = ? AND locale = 'en'", [$id]));
        self::assertSame(2, (int) $this->scalar('SELECT COUNT(*) FROM apartment_translations WHERE apartment_id = ?', [$id]));

        $this->service2->update($id, $this->input(['description_it' => 'Descrizione 2', 'rules_en' => '']));
        self::assertSame('Descrizione 2', $this->scalar("SELECT description FROM apartment_translations WHERE apartment_id = ? AND locale = 'it'", [$id]));
        self::assertNull($this->scalar("SELECT rules FROM apartment_translations WHERE apartment_id = ? AND locale = 'en'", [$id]), 'an emptied text becomes NULL');

        $audit = $this->row("SELECT * FROM audit_log WHERE entity_type = 'apartment' ORDER BY id DESC LIMIT 1");
        self::assertSame('Descrizione', json_decode($audit['old_values'], true)['it.description']);
        self::assertSame('Descrizione 2', json_decode($audit['new_values'], true)['it.description']);
    }

    public function testNothingIsCreatedForALanguageWithNoText(): void
    {
        $this->service2->update($this->apartmentId(), $this->input());

        self::assertSame(0, (int) $this->scalar('SELECT COUNT(*) FROM apartment_translations'));
    }

    public function testTheSlugCannotBeChanged(): void
    {
        $this->service2->update($this->apartmentId(), $this->input(['slug' => 'altro', 'name' => 'Margherita']));

        self::assertSame('margherita', $this->scalar('SELECT slug FROM apartments WHERE id = ?', [$this->apartmentId()]));
    }

    public function testManagementModeAndAgency(): void
    {
        $id = $this->apartmentId();

        $this->service2->update($id, $this->input(['management_mode' => 'agency', 'managing_agency' => 'Agenzia di prova']));
        self::assertSame(['agency', 'Agenzia di prova'], array_values($this->row('SELECT management_mode, managing_agency FROM apartments WHERE id = ?', [$id])));

        $this->service2->update($id, $this->input(['management_mode' => 'direct', 'managing_agency' => 'Agenzia di prova']));
        self::assertSame(['direct', null], array_values($this->row('SELECT management_mode, managing_agency FROM apartments WHERE id = ?', [$id])), 'a directly managed apartment has no agency');
    }

    public function testUncheckedCheckboxesMeanOff(): void
    {
        $input = $this->input();
        unset($input['is_active'], $input['accepts_online_requests']);

        $this->service2->update($this->apartmentId(), $input);

        self::assertSame([0, 0], array_map('intval', array_values($this->row('SELECT is_active, accepts_online_requests FROM apartments WHERE id = ?', [$this->apartmentId()]))));
    }

    public function testValidation(): void
    {
        $this->assertRejected($this->input(['name' => '  ']), ['name' => 'invalid_name']);
        $this->assertRejected($this->input(['name' => str_repeat('x', 101)]), ['name' => 'invalid_name']);
        $this->assertRejected($this->input(['management_mode' => 'franchise']), ['management_mode' => 'invalid_management_mode']);
        $this->assertRejected($this->input(['managing_agency' => str_repeat('x', 101)]), ['managing_agency' => 'invalid_agency']);
        foreach (['max_guests' => ['0', '51', 'x', '-1', '2.5'], 'max_children' => ['21', 'x'], 'max_pets' => ['21'], 'bedrooms' => ['31'], 'beds' => ['61']] as $field => $bad) {
            foreach ($bad as $value) {
                $this->assertRejected($this->input([$field => $value]), [$field => 'invalid_number']);
            }
        }
        foreach (['25:00', '12:60', '9:00', 'noon', '12'] as $time) {
            $this->assertRejected($this->input(['check_in_from' => $time]), ['check_in_from' => 'invalid_time']);
        }
        foreach (['1,234', '-5', 'abc', '€5', '12345678'] as $price) {
            $this->assertRejected($this->input(['indicative_price' => $price]), ['indicative_price' => 'invalid_amount']);
        }
        $this->assertRejected($this->input(['sort_order' => '5000']), ['sort_order' => 'invalid_sort_order']);
        $this->assertRejected($this->input(['description_it' => str_repeat('x', 5001)]), ['description_it' => 'text_too_long']);
        $this->assertRejected($this->input(['meta_description_en' => str_repeat('x', 301)]), ['meta_description_en' => 'text_too_long']);
    }

    public function testValidTimesAndZeroLimitsAreAccepted(): void
    {
        $this->service2->update($this->apartmentId(), $this->input(['check_in_from' => '00:00', 'check_out_until' => '23:59:00', 'max_pets' => '0', 'max_children' => '0', 'bedrooms' => '0']));

        $row = $this->row('SELECT * FROM apartments WHERE id = ?', [$this->apartmentId()]);
        self::assertSame(['00:00:00', '23:59:00', 0, 0, 0], [$row['check_in_from'], $row['check_out_until'], (int) $row['max_pets'], (int) $row['max_children'], (int) $row['bedrooms']]);
    }

    public function testFailedValidationChangesNothing(): void
    {
        $before = $this->row('SELECT * FROM apartments WHERE id = ?', [$this->apartmentId()]);

        $this->assertRejected($this->input(['name' => 'Cambiato', 'max_guests' => 'abc']), ['max_guests' => 'invalid_number']);

        self::assertSame($before, $this->row('SELECT * FROM apartments WHERE id = ?', [$this->apartmentId()]));
        self::assertSame(0, (int) $this->scalar("SELECT COUNT(*) FROM audit_log WHERE entity_type = 'apartment'"));
    }

    public function testUnknownApartment(): void
    {
        $this->expectException(StateException::class);
        $this->service2->update(999999, $this->input());
    }
}
