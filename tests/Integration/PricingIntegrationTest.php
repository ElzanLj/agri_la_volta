<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Domain\GuestCounts;
use App\Domain\StayDates;
use App\Domain\ValidationException;
use App\Repository\PricingRepository;
use App\Service\ConfiguredPriceQuoter;
use App\Service\PricingConfigService;
use Tests\Support\DatabaseTestCase;
use Tests\Support\PricingFixtures as F;

/**
 * Pricing wired into the database and into the public request. All data below is FICTITIOUS
 * test data (see tests/Support/PricingFixtures.php).
 */
final class PricingIntegrationTest extends DatabaseTestCase
{
    private PricingConfigService $config;
    private ConfiguredPriceQuoter $quoter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->config = new PricingConfigService($this->db);
        $this->quoter = new ConfiguredPriceQuoter(new PricingRepository($this->db));
    }

    /** Loads the three fixture seasons for an apartment through the real configuration service. */
    private function loadSeasons(string $slug = 'margherita', ?int $lowMin = null, ?int $highMin = null): void
    {
        $id = $this->apartmentId($slug);
        $this->config->createRate(F::rateInput($id, '[TEST] Bassa', '2027-06-01', '2027-06-15', 1000, $lowMin));
        $this->config->createRate(F::rateInput($id, '[TEST] Alta', '2027-06-15', '2027-07-01', 2000, $highMin));
        $this->config->createRate(F::rateInput($id, '[TEST] Picco', '2027-07-01', '2027-08-01', 3000));
    }

    /** @return array<string, mixed> */
    private function requestRow(int $id): array
    {
        return $this->row('SELECT * FROM booking_requests WHERE id = ?', [$id]);
    }

    // === Quoter reads the stored price list ======================================

    public function testQuoterUsesOnlyActiveRatesOfTheRequestedApartment(): void
    {
        $this->loadSeasons('margherita');
        $this->config->createRate(F::rateInput($this->apartmentId('girasole'), '[TEST] Altro', '2027-06-01', '2027-06-15', 9999));
        $this->config->createRate(F::rateInput($this->apartmentId('rosa'), '[TEST] Spenta', '2027-06-01', '2027-06-15', 7777, null, false));

        $stay = StayDates::fromStrings('2027-06-13', '2027-06-17');

        self::assertSame(6000, $this->quoter->quote($this->apartmentId('margherita'), $stay, GuestCounts::from(2))->totalCents);
        self::assertSame(9999 * 2, $this->quoter->quote($this->apartmentId('girasole'), StayDates::fromStrings('2027-06-03', '2027-06-05'), GuestCounts::from(2))->totalCents);
        self::assertNull($this->quoter->quote($this->apartmentId('rosa'), StayDates::fromStrings('2027-06-03', '2027-06-05'), GuestCounts::from(2))->totalCents, 'inactive rate is ignored');
        self::assertNull($this->quoter->quote($this->apartmentId('viola'), $stay, GuestCounts::from(2))->totalCents, 'no rates configured for this apartment');
    }

    public function testQuoterAppliesGlobalAndApartmentRulesFromTheDatabase(): void
    {
        $this->loadSeasons();
        $this->config->createRule(F::ruleInput('adult', 'per_night', 500, 2));                                        // all apartments
        $this->config->createRule(F::ruleInput('stay', 'per_stay', 5000, 0, $this->apartmentId('margherita'), '[TEST] Pulizia'));
        $this->config->createRule(F::ruleInput('stay', 'per_stay', 4000, 0, $this->apartmentId('girasole')));        // other apartment

        $quote = $this->quoter->quote($this->apartmentId('margherita'), StayDates::fromStrings('2027-06-13', '2027-06-17'), GuestCounts::from(3));

        self::assertSame(6000 + 1 * 500 * 4 + 5000, $quote->totalCents);
        self::assertSame(['base', 'base', 'adult', 'supplement'], array_column($quote->lines, 'kind'));
    }

    // === Server-side pricing in the public request ===============================

    public function testRequestStoresTheServerSideTotalAndBreakdown(): void
    {
        $this->loadSeasons();
        $this->config->createRule(F::ruleInput('adult', 'per_night', 500, 2));
        $this->config->createRule(F::ruleInput('stay', 'per_stay', 5000, 0, null, '[TEST] Pulizia'));

        $created = $this->service->createRequest($this->requestInput('2027-06-13', '2027-06-17', ['adults' => 3]));

        $row = $this->requestRow($created['id']);
        self::assertSame(6000 + 2000 + 5000, (int) $row['quoted_total_cents']);
        $snapshot = json_decode($row['price_breakdown'], true);
        self::assertSame('quoted', $snapshot['status']);
        self::assertSame(13000, $snapshot['total_cents']);
        self::assertSame(4, $snapshot['nights']);
        self::assertSame(['base', 'base', 'adult', 'supplement'], array_column($snapshot['lines'], 'kind'));
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $snapshot['quoted_at']);
        self::assertSame('pending', $row['status']);
    }

    public function testPricesSentByTheBrowserAreIgnored(): void
    {
        $this->loadSeasons();

        $created = $this->service->createRequest($this->requestInput('2027-06-03', '2027-06-06', [
            'quoted_total_cents' => 1, 'total_cents' => 1, 'total' => 1, 'price' => 1, 'price_breakdown' => '{"total_cents":1}',
        ]));

        self::assertSame(3000, (int) $this->requestRow($created['id'])['quoted_total_cents']);
    }

    public function testStoredQuoteDoesNotChangeWhenThePriceListIsEditedLater(): void
    {
        $this->loadSeasons();
        $created = $this->service->createRequest($this->requestInput('2027-06-03', '2027-06-06'));
        self::assertSame(3000, (int) $this->requestRow($created['id'])['quoted_total_cents']);

        $rateId = (int) $this->scalar("SELECT id FROM seasonal_rates WHERE label = '[TEST] Bassa'");
        $this->config->updateRate($rateId, F::rateInput($this->apartmentId(), '[TEST] Bassa', '2027-06-01', '2027-06-15', 5000));

        self::assertSame(3000, (int) $this->requestRow($created['id'])['quoted_total_cents'], 'a quote already given is frozen');

        $booking = $this->service->confirmRequest($created['id']);
        self::assertSame(3000, (int) $this->scalar('SELECT total_cents FROM bookings WHERE id = ?', [$booking['booking_id']]), 'the confirmed total is the quoted one');

        $next = $this->service->createRequest($this->requestInput('2027-06-08', '2027-06-11'));
        self::assertSame(15000, (int) $this->requestRow($next['id'])['quoted_total_cents'], 'new requests use the new price');
    }

    public function testNoPriceListStillSavesTheRequestAsPriceToBeConfirmed(): void
    {
        $created = $this->service->createRequest($this->requestInput('2027-06-10', '2027-06-15'));

        $row = $this->requestRow($created['id']);
        self::assertSame('pending', $row['status']);
        self::assertNull($row['quoted_total_cents']);
        $snapshot = json_decode($row['price_breakdown'], true);
        self::assertSame('unquoted', $snapshot['status']);
        self::assertSame('no_rate_for_night', $snapshot['issues'][0]['code']);
        self::assertSame([['start_date' => '2027-06-10', 'end_date' => '2027-06-15']], $snapshot['issues'][0]['ranges']);

        // It can still be confirmed; the booking simply has no total.
        $booking = $this->service->confirmRequest($created['id']);
        self::assertNull($this->scalar('SELECT total_cents FROM bookings WHERE id = ?', [$booking['booking_id']]));
    }

    public function testPartialPriceListAlsoSavesTheRequest(): void
    {
        $this->loadSeasons(); // nothing after 1 August

        $created = $this->service->createRequest($this->requestInput('2027-07-30', '2027-08-03'));

        $row = $this->requestRow($created['id']);
        self::assertNull($row['quoted_total_cents']);
        $snapshot = json_decode($row['price_breakdown'], true);
        self::assertSame([['start_date' => '2027-08-01', 'end_date' => '2027-08-03']], $snapshot['issues'][0]['ranges']);
        self::assertSame(2 * 3000, $snapshot['lines'][0]['total_cents'], 'the priced nights are still itemised for the admin');
    }

    // === Minimum stay ============================================================

    public function testStayBelowTheMinimumIsRefusedAndNothingIsStored(): void
    {
        $this->loadSeasons(lowMin: 3);

        try {
            $this->service->createRequest($this->requestInput('2027-06-03', '2027-06-05'));
            self::fail('Expected a ValidationException');
        } catch (ValidationException $e) {
            self::assertSame(['check_out' => 'below_minimum_stay'], $e->errors());
            self::assertSame(['min_nights' => 3], $e->context());
        }
        self::assertSame(0, (int) $this->scalar('SELECT COUNT(*) FROM booking_requests'));
        self::assertSame(0, (int) $this->scalar('SELECT COUNT(*) FROM audit_log WHERE entity_type = \'booking_request\''));

        $ok = $this->service->createRequest($this->requestInput('2027-06-03', '2027-06-06'));
        self::assertSame('pending', $this->requestRow($ok['id'])['status']);
    }

    public function testMinimumStayIsDecidedByTheArrivalDate(): void
    {
        $this->loadSeasons(lowMin: 2, highMin: 7);

        // Arrives in LOW (min 2), 3 nights, ends in HIGH: accepted.
        $ok = $this->service->createRequest($this->requestInput('2027-06-13', '2027-06-16'));
        self::assertSame('pending', $this->requestRow($ok['id'])['status']);

        // Arrives in HIGH (min 7), 3 nights: refused.
        try {
            $this->service->createRequest($this->requestInput('2027-06-20', '2027-06-23'));
            self::fail('Expected a ValidationException');
        } catch (ValidationException $e) {
            self::assertSame(7, $e->context()['min_nights']);
        }
    }

    public function testManualBookingsIgnoreMinimumStayAndPricing(): void
    {
        $this->loadSeasons(lowMin: 5);

        // A single night is below the configured minimum but the admin may record it.
        $id = $this->service->createManualBooking($this->manualInput('2027-06-03', '2027-06-04', ['total_cents' => 12345]));

        self::assertSame(12345, (int) $this->scalar('SELECT total_cents FROM bookings WHERE id = ?', [$id]), 'the admin enters the total');
    }

    // === Children and pets limits ================================================

    public function testLimitsAreNotEnforcedWhenNotConfigured(): void
    {
        $created = $this->service->createRequest($this->requestInput('2027-06-10', '2027-06-15', ['adults' => 2, 'children' => 6, 'pets' => 4]));

        self::assertSame('pending', $this->requestRow($created['id'])['status']);
    }

    public function testChildrenLimit(): void
    {
        $this->db->exec("UPDATE apartments SET max_children = 2 WHERE slug = 'margherita'");

        $ok = $this->service->createRequest($this->requestInput('2027-06-10', '2027-06-15', ['children' => 2]));
        self::assertSame('pending', $this->requestRow($ok['id'])['status']);

        try {
            $this->service->createRequest($this->requestInput('2027-07-10', '2027-07-15', ['children' => 3]));
            self::fail('Expected a ValidationException');
        } catch (ValidationException $e) {
            self::assertSame(['children' => 'too_many_children'], $e->errors());
            self::assertSame(['max_children' => 2], $e->context());
        }
        self::assertSame(1, (int) $this->scalar('SELECT COUNT(*) FROM booking_requests'));
    }

    public function testNoChildrenAllowed(): void
    {
        $this->db->exec("UPDATE apartments SET max_children = 0 WHERE slug = 'margherita'");

        $this->expectException(ValidationException::class);
        $this->service->createRequest($this->requestInput('2027-06-10', '2027-06-15', ['children' => 1]));
    }

    public function testPetsLimitAndPetsNotAllowed(): void
    {
        $this->db->exec("UPDATE apartments SET max_pets = 1 WHERE slug = 'margherita'");
        $ok = $this->service->createRequest($this->requestInput('2027-06-10', '2027-06-15', ['pets' => 1]));
        self::assertSame('pending', $this->requestRow($ok['id'])['status']);

        try {
            $this->service->createRequest($this->requestInput('2027-07-10', '2027-07-15', ['pets' => 2]));
            self::fail('Expected a ValidationException');
        } catch (ValidationException $e) {
            self::assertSame(['pets' => 'too_many_pets'], $e->errors());
        }

        $this->db->exec("UPDATE apartments SET max_pets = 0 WHERE slug = 'margherita'");
        try {
            $this->service->createRequest($this->requestInput('2027-08-10', '2027-08-15', ['pets' => 1]));
            self::fail('Expected a ValidationException');
        } catch (ValidationException $e) {
            self::assertSame(['pets' => 'pets_not_allowed'], $e->errors());
        }
        $noPets = $this->service->createRequest($this->requestInput('2027-08-10', '2027-08-15', ['pets' => 0]));
        self::assertSame('pending', $this->requestRow($noPets['id'])['status']);
    }

    public function testLimitsApplyPerApartment(): void
    {
        $this->db->exec("UPDATE apartments SET max_pets = 0 WHERE slug = 'margherita'");

        $created = $this->service->createRequest($this->requestInput('2027-06-10', '2027-06-15', ['pets' => 2, 'apartment_id' => $this->apartmentId('girasole')]));

        self::assertSame('pending', $this->requestRow($created['id'])['status']);
    }

    public function testLimitsDoNotRestrictTheAdminsManualBookings(): void
    {
        $this->db->exec("UPDATE apartments SET max_pets = 0, max_children = 0 WHERE slug = 'margherita'");

        $id = $this->service->createManualBooking($this->manualInput('2027-06-10', '2027-06-15', ['children' => 2, 'pets' => 1]));

        self::assertGreaterThan(0, $id);
    }

    public function testLimitIsCheckedBeforeAnythingIsStoredAndBeforeAvailability(): void
    {
        $this->db->exec("UPDATE apartments SET max_pets = 0 WHERE slug = 'margherita'");
        $this->loadSeasons();

        try {
            $this->service->createRequest($this->requestInput('2027-06-10', '2027-06-15', ['pets' => 1]));
            self::fail('Expected a ValidationException');
        } catch (ValidationException) {
        }
        self::assertSame(0, (int) $this->scalar('SELECT COUNT(*) FROM booking_requests'));
        self::assertSame(0, (int) $this->scalar('SELECT COUNT(*) FROM bookings'));
    }

    // === No production data in the migrations ====================================

    public function testMigrationsSeedNoPricingData(): void
    {
        foreach (glob(dirname(__DIR__, 2) . '/migrations/*.sql') as $file) {
            $sql = (string) file_get_contents($file);
            self::assertDoesNotMatchRegularExpression('/INSERT\s+(IGNORE\s+)?INTO\s+`?(seasonal_rates|pricing_rules)`?/i', $sql, basename($file) . ' must not insert prices or rules');
        }
    }

    public function testFreshlyMigratedDatabaseHasNoPriceList(): void
    {
        $config = \App\App::current()->config;
        $rootPassword = $config->string('DB_ROOT_PASSWORD');
        if ($rootPassword === '') {
            self::markTestSkipped('DB_ROOT_PASSWORD is not configured.');
        }

        $name = 'agriturismo_migcheck_test';
        $root = new \PDO(
            sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $config->string('DB_HOST', 'localhost'), $config->int('DB_PORT', 3306)),
            'root',
            $rootPassword,
            [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION],
        );
        $root->exec("DROP DATABASE IF EXISTS `{$name}`");
        $root->exec("CREATE DATABASE `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

        try {
            $scratch = new \PDO(
                sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $config->string('DB_HOST', 'localhost'), $config->int('DB_PORT', 3306), $name),
                'root',
                $rootPassword,
                [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION],
            );
            (new \App\Database\Migrator($scratch, dirname(__DIR__, 2) . '/migrations'))->migrate();

            self::assertSame(0, (int) $scratch->query('SELECT COUNT(*) FROM seasonal_rates')->fetchColumn());
            self::assertSame(0, (int) $scratch->query('SELECT COUNT(*) FROM pricing_rules')->fetchColumn());
            self::assertSame(6, (int) $scratch->query('SELECT COUNT(*) FROM apartments')->fetchColumn());
            self::assertSame(0, (int) $scratch->query('SELECT COUNT(*) FROM apartments WHERE max_guests IS NOT NULL OR max_children IS NOT NULL OR max_pets IS NOT NULL OR indicative_price_cents IS NOT NULL')->fetchColumn());
        } finally {
            $root->exec("DROP DATABASE IF EXISTS `{$name}`");
        }
    }
}
