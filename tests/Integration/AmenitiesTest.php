<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Domain\ValidationException;
use App\Repository\ApartmentRepository;
use App\Service\ApartmentAdminService;
use Tests\Support\DatabaseTestCase;

/** Apartment amenities (SPEC §4): stored per language, one per line, edited only through the admin service. */
final class AmenitiesTest extends DatabaseTestCase
{
    private ApartmentAdminService $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = new ApartmentAdminService($this->db);
    }

    /** @param array<string, mixed> $extra */
    private function input(array $extra = []): array
    {
        return $extra + [
            'name' => 'Margherita', 'is_active' => '1', 'accepts_online_requests' => '1', 'management_mode' => 'direct', 'managing_agency' => '',
            'max_guests' => '', 'max_children' => '', 'max_pets' => '', 'bedrooms' => '', 'beds' => '',
            'check_in_from' => '', 'check_in_until' => '', 'check_out_until' => '', 'indicative_price' => '', 'sort_order' => '1',
        ];
    }

    public function testAmenitiesAreStoredPerLanguageAndNormalised(): void
    {
        $id = $this->apartmentId();

        $this->admin->update($id, $this->input(['amenities_it' => "  Piscina \r\n\r\nPosto auto  ", 'amenities_en' => "Pool\nParking"]));

        self::assertSame("Piscina\nPosto auto", $this->scalar("SELECT amenities FROM apartment_translations WHERE apartment_id = ? AND locale = 'it'", [$id]));
        self::assertSame("Pool\nParking", $this->scalar("SELECT amenities FROM apartment_translations WHERE apartment_id = ? AND locale = 'en'", [$id]));
        $public = (new ApartmentRepository($this->db))->findPublicBySlug('margherita', 'it');
        self::assertSame("Piscina\nPosto auto", $public['amenities']);
        self::assertSame("Pool\nParking", (new ApartmentRepository($this->db))->findPublicBySlug('margherita', 'en')['amenities']);
    }

    public function testTheChangeIsAuditedAndSavingAgainWritesNothing(): void
    {
        $id = $this->apartmentId();
        $this->admin->update($id, $this->input(['amenities_it' => 'Piscina']));

        $audit = $this->row("SELECT * FROM audit_log WHERE entity_type = 'apartment'");
        self::assertSame(['it.amenities' => 'Piscina'], json_decode($audit['new_values'], true));

        $this->admin->update($id, $this->input(['amenities_it' => "Piscina\r\n"]));
        self::assertSame(1, (int) $this->scalar("SELECT COUNT(*) FROM audit_log WHERE entity_type = 'apartment'"), 'the same list in other whitespace is not a change');
    }

    public function testEmptyingTheFieldRemovesTheAmenities(): void
    {
        $id = $this->apartmentId();
        $this->admin->update($id, $this->input(['amenities_it' => 'Piscina']));

        $this->admin->update($id, $this->input(['amenities_it' => "  \n  "]));

        self::assertNull($this->scalar("SELECT amenities FROM apartment_translations WHERE apartment_id = ? AND locale = 'it'", [$id]));
    }

    public function testInvalidAmenitiesAreRejectedAndNothingChanges(): void
    {
        $id = $this->apartmentId();
        $this->admin->update($id, $this->input(['amenities_it' => 'Piscina']));
        $cases = [
            'line too long' => [str_repeat('a', 101), 'amenities_it'],
            'too many lines' => [implode("\n", array_fill(0, 31, 'x')), 'amenities_it'],
        ];

        foreach ($cases as $name => [$text, $field]) {
            try {
                $this->admin->update($id, $this->input(['name' => 'Cambiato', $field => $text]));
                self::fail("$name must be rejected");
            } catch (ValidationException $e) {
                self::assertSame('invalid_amenities', $e->errors()[$field] ?? null, $name);
            }
        }
        self::assertSame('Margherita', $this->scalar('SELECT name FROM apartments WHERE id = ?', [$id]), 'a failed update changes nothing, not even the name');
        self::assertSame('Piscina', $this->scalar("SELECT amenities FROM apartment_translations WHERE apartment_id = ? AND locale = 'it'", [$id]));
    }

    public function testNoAmenitiesExistUntilTheOwnerEntersThem(): void
    {
        $count = (int) $this->scalar('SELECT COUNT(*) FROM apartment_translations');
        self::assertSame(0, $count, 'migrations and the test reset insert no amenities or texts');
        self::assertNull((new ApartmentRepository($this->db))->findPublicBySlug('rosa', 'it')['amenities']);
    }
}
