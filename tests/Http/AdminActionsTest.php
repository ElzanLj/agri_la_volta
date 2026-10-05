<?php

declare(strict_types=1);

namespace Tests\Http;

use App\Domain\StateException;
use Tests\Support\HttpClient;
use Tests\Support\HttpResponse;
use Tests\Support\HttpTestCase;

/**
 * What the admin can really do, end to end over HTTP with a valid session and CSRF token:
 * each action is checked on the pages, in the database and in the audit log.
 * All prices used here are FICTITIOUS test values.
 */
final class AdminActionsTest extends HttpTestCase
{
    private HttpClient $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->loggedInClient();
    }

    /** POST with the session's CSRF token. @param array<string, mixed> $fields */
    private function act(string $path, array $fields = []): HttpResponse
    {
        return $this->admin->post($path, $fields + ['_csrf' => $this->tokenFor($this->admin)]);
    }

    /** Follows a redirect the way a browser would. */
    private function follow(HttpResponse $response): HttpResponse
    {
        self::assertContains($response->status, [301, 302, 303], 'expected a redirect, got ' . $response->status);
        return $this->admin->get((string) $response->location());
    }

    private function audit(string $entity, string $action): int
    {
        return $this->countRows('audit_log', "entity_type = '{$entity}' AND action = '{$action}'");
    }

    // === Lists, filters, pagination ===========================================

    public function testRequestListFiltersByStatusApartmentAndPeriod(): void
    {
        $a = $this->makeRequest('2027-06-10', '2027-06-15');
        $b = $this->makeRequest('2027-07-10', '2027-07-15', ['apartment_id' => $this->apartmentId('girasole')]);
        $c = $this->makeRequest('2027-09-10', '2027-09-15');
        $this->service->rejectRequest($c['id']);

        $all = $this->admin->get('/admin/richieste');
        self::assertSame(200, $all->status);
        foreach ([$a, $b, $c] as $r) {
            self::assertStringContainsString($r['reference'], $all->body);
        }

        $pending = $this->admin->get('/admin/richieste?stato=pending')->body;
        self::assertStringContainsString($a['reference'], $pending);
        self::assertStringNotContainsString($c['reference'], $pending);

        $girasole = $this->admin->get('/admin/richieste?appartamento=' . $this->apartmentId('girasole'))->body;
        self::assertStringContainsString($b['reference'], $girasole);
        self::assertStringNotContainsString($a['reference'], $girasole);

        $period = $this->admin->get('/admin/richieste?dal=2027-07-01&al=2027-08-01')->body;
        self::assertStringContainsString($b['reference'], $period);
        self::assertStringNotContainsString($a['reference'], $period);
        self::assertStringNotContainsString($c['reference'], $period);

        // A stay that only touches the period boundary does not count: [dal, al) is half-open.
        $boundary = $this->admin->get('/admin/richieste?dal=2027-07-15&al=2027-07-20')->body;
        self::assertStringNotContainsString($b['reference'], $boundary, 'a stay checking out on the first day of the period is outside it');

        $combined = $this->admin->get('/admin/richieste?stato=rejected&appartamento=' . $this->apartmentId())->body;
        self::assertStringContainsString($c['reference'], $combined);
        self::assertStringNotContainsString($a['reference'], $combined);
    }

    public function testBookingListFiltersByOriginStatusAndPeriod(): void
    {
        $phone = $this->service->createManualBooking($this->manualInput('2027-06-10', '2027-06-15', ['guest_name' => 'Telefonista']));
        $agency = $this->service->createManualBooking($this->manualInput('2027-07-10', '2027-07-15', ['origin' => 'agency', 'guest_name' => 'Agenziale']));
        $gone = $this->service->createManualBooking($this->manualInput('2027-08-10', '2027-08-15', ['guest_name' => 'Cancellato']));
        $this->service->cancelBooking($gone);

        $agencyOnly = $this->admin->get('/admin/prenotazioni?origine=agency')->body;
        self::assertStringContainsString('Agenziale', $agencyOnly);
        self::assertStringNotContainsString('Telefonista', $agencyOnly);

        $cancelled = $this->admin->get('/admin/prenotazioni?stato=cancelled')->body;
        self::assertStringContainsString('Cancellato', $cancelled);
        self::assertStringNotContainsString('Agenziale', $cancelled);

        $june = $this->admin->get('/admin/prenotazioni?dal=2027-06-01&al=2027-07-01')->body;
        self::assertStringContainsString('Telefonista', $june);
        self::assertStringNotContainsString('Agenziale', $june);
        self::assertGreaterThan(0, $phone + $agency);
    }

    public function testListsArePaginated(): void
    {
        for ($i = 0; $i < 55; $i++) {
            $this->makeRequest('2027-06-10', '2027-06-12', ['first_name' => sprintf('Cliente%02d', $i)]);
        }

        $first = $this->admin->get('/admin/richieste')->body;
        $second = $this->admin->get('/admin/richieste?pagina=2')->body;

        self::assertSame(50, substr_count($first, '<td>LV-'), 'first page: 50 rows');
        self::assertSame(5, substr_count($second, '<td>LV-'), 'second page: the remaining 5');
        self::assertStringContainsString('55 risultati', $first);
        self::assertStringContainsString('pagina 2 di 2', $second);
        self::assertStringContainsString('pagina=2', $first);
    }

    public function testInvalidFiltersAreRejectedWithoutTouchingTheDatabaseLayer(): void
    {
        $this->makeRequest();
        $this->book('2027-08-01', '2027-08-05');
        $before = $this->snapshot();

        $attacks = [
            '/admin/richieste?stato=' . rawurlencode("' OR 1=1 --"),
            '/admin/richieste?stato=pending%27%3B%20DROP%20TABLE%20bookings%3B--',
            '/admin/richieste?appartamento=' . rawurlencode('1 OR 1=1'),
            '/admin/richieste?appartamento=' . rawurlencode("1' UNION SELECT password_hash FROM admin --"),
            '/admin/richieste?dal=' . rawurlencode("2027-01-01'; DROP TABLE bookings;--"),
            '/admin/richieste?al=2027-13-45',
            '/admin/richieste?dal=2027-07-10&al=2027-07-01',
            '/admin/richieste?pagina=-1',
            '/admin/richieste?pagina=1;DROP',
            '/admin/richieste?pagina=0',
            '/admin/richieste?stato[]=pending',
            '/admin/prenotazioni?origine=' . rawurlencode("x' OR '1'='1"),
            '/admin/prenotazioni?stato=pending', // valid for requests, not for bookings
            '/admin/storico?tipo=' . rawurlencode('audit_log; DROP TABLE admin'),
            '/admin/export/richieste.csv?stato=' . rawurlencode("' OR 1=1 --"),
        ];

        foreach ($attacks as $url) {
            $response = $this->admin->get($url);
            self::assertSame(400, $response->status, $url);
            self::assertStringNotContainsString('password_hash', $response->body, $url);
            self::assertStringNotContainsString('SQLSTATE', $response->body, $url);
            self::assertStringNotContainsString('Mario', $response->body, "$url must not list data when the filter is invalid");
        }

        $this->assertDatabaseUnchanged($before, 'invalid filters must not change anything');
        self::assertSame(1, $this->countRows('admin'), 'the admin table is intact');
    }

    public function testRouteParametersMustBePositiveIntegers(): void
    {
        foreach (['/admin/richieste/0', '/admin/richieste/999999', '/admin/richieste/1%20OR%201=1', "/admin/richieste/1'--", '/admin/prenotazioni/abc', '/admin/appartamenti/-1', '/admin/listino/tariffe/00'] as $path) {
            $response = $this->admin->get($path);
            self::assertSame(404, $response->status, $path);
            self::assertStringNotContainsString('SQLSTATE', $response->body);
            self::assertStringNotContainsString('Stack trace', $response->body);
            self::assertStringContainsString('Pagina non trovata', $response->body);
        }
    }

    // === Requests: confirm / reject ============================================

    public function testRequestDetailShowsTheServerCalculatedPrice(): void
    {
        $apartment = $this->apartmentId();
        $this->admin->post('/admin/listino/tariffe', [
            '_csrf' => $this->tokenFor($this->admin), 'apartment_id' => $apartment, 'label_it' => '[TEST] Bassa', 'start_date' => '2027-06-01',
            'end_date' => '2027-07-01', 'nightly_rate' => '10', 'is_active' => '1',
        ]);
        $request = $this->makeRequest('2027-06-10', '2027-06-13');

        $page = $this->admin->get('/admin/richieste/' . $request['id']);

        self::assertSame(200, $page->status);
        self::assertStringContainsString('[TEST] Bassa', $page->body);
        self::assertStringContainsString('3 notti × € 10,00', $page->body);
        self::assertStringContainsString('€ 30,00', $page->body);
        self::assertStringContainsString('Conferma richiesta', $page->body);
    }

    public function testConfirmingARequestCreatesTheBookingAndIsVisibleEverywhere(): void
    {
        $request = $this->makeRequest('2027-06-10', '2027-06-15');

        $response = $this->act('/admin/richieste/' . $request['id'] . '/conferma');

        self::assertSame(303, $response->status);
        $booking = (int) $this->scalar('SELECT id FROM bookings WHERE booking_request_id = ?', [$request['id']]);
        self::assertStringEndsWith('/admin/prenotazioni/' . $booking, (string) $response->location());
        self::assertSame('confirmed', $this->scalar('SELECT status FROM booking_requests WHERE id = ?', [$request['id']]));
        self::assertSame('website', $this->scalar('SELECT origin FROM bookings WHERE id = ?', [$booking]));
        self::assertSame(1, $this->audit('booking_request', 'status_changed'));
        self::assertSame(1, $this->audit('booking', 'created'));

        $detail = $this->follow($response);
        self::assertStringContainsString('Richiesta confermata', $detail->body, 'flash message on the next page');
        self::assertStringContainsString('Mario Rossi', $detail->body);
        self::assertStringNotContainsString('Richiesta confermata', $this->admin->get('/admin/prenotazioni/' . $booking)->body, 'the flash message is shown once');

        self::assertStringNotContainsString('Conferma richiesta', $this->admin->get('/admin/richieste/' . $request['id'])->body, 'no more decision buttons');
        self::assertStringContainsString('Confermata', $this->admin->get('/admin/richieste?stato=confirmed')->body);
        self::assertStringContainsString('Richiesta pending → confirmed', $this->admin->get('/admin/storico')->body);
    }

    public function testConfirmationOfATakenPeriodShowsWhatBlocksItAndChangesNothing(): void
    {
        $first = $this->makeRequest('2027-06-10', '2027-06-15');
        $second = $this->makeRequest('2027-06-12', '2027-06-18', ['first_name' => 'Anna']);
        $this->act('/admin/richieste/' . $first['id'] . '/conferma');
        $before = $this->snapshot();

        $response = $this->act('/admin/richieste/' . $second['id'] . '/conferma');

        self::assertSame(303, $response->status);
        self::assertStringEndsWith('/admin/richieste/' . $second['id'], (string) $response->location());
        $page = $this->follow($response);
        self::assertStringContainsString('Le date non sono disponibili', $page->body);
        self::assertStringContainsString('prenotazione n.', $page->body);
        self::assertStringContainsString('10/06/2027', $page->body);
        self::assertSame('pending', $this->scalar('SELECT status FROM booking_requests WHERE id = ?', [$second['id']]));
        $this->assertDatabaseUnchanged($before, 'a refused confirmation must not change anything');
    }

    public function testRepeatingAConfirmationDoesNotDuplicateTheBooking(): void
    {
        $request = $this->makeRequest();
        $this->act('/admin/richieste/' . $request['id'] . '/conferma');

        $again = $this->act('/admin/richieste/' . $request['id'] . '/conferma');

        self::assertSame(303, $again->status);
        self::assertStringContainsString('già stata gestita', $this->follow($again)->body);
        self::assertSame(1, $this->countRows('bookings'));
    }

    public function testRejectingARequest(): void
    {
        $request = $this->makeRequest();

        $response = $this->act('/admin/richieste/' . $request['id'] . '/rifiuta');

        self::assertSame(303, $response->status);
        self::assertSame('rejected', $this->scalar('SELECT status FROM booking_requests WHERE id = ?', [$request['id']]));
        self::assertSame(0, $this->countRows('bookings'));
        self::assertStringContainsString('Richiesta rifiutata', $this->follow($response)->body);
        self::assertSame(1, $this->audit('booking_request', 'status_changed'));

        $second = $this->act('/admin/richieste/' . $request['id'] . '/conferma');
        self::assertStringContainsString('già stata gestita', $this->follow($second)->body, 'a rejected request cannot be confirmed');
        self::assertSame(0, $this->countRows('bookings'));
    }

    // === Manual bookings =======================================================

    public function testManualBookingFormDoesNotOfferTheWebsiteOrigin(): void
    {
        $page = $this->admin->get('/admin/prenotazioni/nuova');

        self::assertSame(200, $page->status);
        self::assertStringContainsString('value="phone"', $page->body);
        self::assertStringContainsString('value="novasol"', $page->body);
        self::assertStringNotContainsString('value="website"', $page->body);
    }

    public function testManualBookingEndToEnd(): void
    {
        $response = $this->act('/admin/prenotazioni', [
            'apartment_id' => $this->apartmentId(), 'origin' => 'agency', 'check_in' => '2027-06-10', 'check_out' => '2027-06-15',
            'adults' => '2', 'children' => '1', 'pets' => '0', 'guest_name' => 'Famiglia Bianchi', 'email' => 'bianchi@example.com',
            'phone' => '0524 1234', 'total' => '123,45', 'notes' => 'Arrivo tardi',
        ]);

        self::assertSame(303, $response->status);
        $row = $this->row('SELECT * FROM bookings');
        self::assertSame(['agency', 'confirmed', 'Famiglia Bianchi', 12345, 1], [$row['origin'], $row['status'], $row['guest_name'], (int) $row['total_cents'], (int) $row['children']]);
        self::assertStringEndsWith('/admin/prenotazioni/' . $row['id'], (string) $response->location());
        self::assertSame(1, $this->audit('booking', 'created'));

        $detail = $this->follow($response);
        self::assertStringContainsString('Prenotazione registrata', $detail->body);
        self::assertStringContainsString('Agenzia', $detail->body);
        self::assertStringContainsString('€ 123,45', $detail->body);

        // It occupies the dates for everyone.
        self::assertSame(409, $this->act('/admin/prenotazioni', [
            'apartment_id' => $this->apartmentId(), 'origin' => 'phone', 'check_in' => '2027-06-14', 'check_out' => '2027-06-16', 'adults' => '2', 'guest_name' => 'Secondo',
        ])->status);
    }

    public function testManualBookingOnTakenDatesKeepsWhatWasTypedAndExplainsTheConflict(): void
    {
        $existing = $this->book('2027-06-10', '2027-06-15');
        $before = $this->snapshot();

        $response = $this->act('/admin/prenotazioni', [
            'apartment_id' => $this->apartmentId(), 'origin' => 'phone', 'check_in' => '2027-06-12', 'check_out' => '2027-06-14',
            'adults' => '3', 'guest_name' => 'Signor Rossi', 'notes' => 'da non perdere',
        ]);

        self::assertSame(409, $response->status);
        self::assertStringContainsString('Le date non sono disponibili: prenotazione n. ' . $existing, $response->body);
        self::assertStringContainsString('value="Signor Rossi"', $response->body, 'typed values are kept');
        self::assertStringContainsString('da non perdere', $response->body);
        self::assertStringContainsString('value="2027-06-12"', $response->body);
        $this->assertDatabaseUnchanged($before, 'a conflicting booking must not be stored');
    }

    public function testManualBookingValidationErrorsAreShownNextToTheFields(): void
    {
        $before = $this->snapshot();

        $response = $this->act('/admin/prenotazioni', [
            'apartment_id' => $this->apartmentId(), 'origin' => 'website', 'check_in' => '2027-06-15', 'check_out' => '2027-06-10',
            'adults' => '0', 'guest_name' => '"><script>alert(1)</script>', 'email' => 'non-una-email', 'total' => '12x',
        ]);

        self::assertSame(422, $response->status);
        foreach (['Origine non valida', 'La data di partenza deve essere successiva', 'Numero di adulti non valido', 'Indirizzo email non valido', 'Importo totale non valido'] as $message) {
            self::assertStringContainsString($message, $response->body);
        }
        self::assertStringContainsString('aria-invalid="true" aria-describedby="err-origin"', $response->body);
        self::assertStringContainsString('id="err-check_out"', $response->body);
        self::assertStringContainsString('value="&quot;&gt;&lt;script&gt;alert(1)&lt;/script&gt;"', $response->body, 'the retyped value is escaped');
        self::assertStringNotContainsString('<script>alert(1)</script>', $response->body);
        $this->assertDatabaseUnchanged($before, 'invalid input must not be stored');
    }

    public function testManualBookingRespectsCapacityWhenConfigured(): void
    {
        $this->db->exec("UPDATE apartments SET max_guests = 2 WHERE slug = 'margherita'");

        $response = $this->act('/admin/prenotazioni', [
            'apartment_id' => $this->apartmentId(), 'origin' => 'phone', 'check_in' => '2027-06-10', 'check_out' => '2027-06-12', 'adults' => '3', 'guest_name' => 'Troppi',
        ]);

        self::assertSame(422, $response->status);
        self::assertStringContainsString('capienza', $response->body);
        self::assertSame(0, $this->countRows('bookings'));
    }

    // === Blocks ===============================================================

    public function testBlockLifecycleAndCalendar(): void
    {
        $create = $this->act('/admin/blocchi', ['apartment_id' => $this->apartmentId('rosa'), 'start_date' => '2027-06-20', 'end_date' => '2027-06-23', 'reason' => 'Imbiancatura']);

        self::assertSame(303, $create->status);
        $blockId = (int) $this->scalar('SELECT id FROM availability_blocks');
        self::assertStringContainsString('Imbiancatura', $this->follow($create)->body);
        self::assertSame(1, $this->audit('availability_block', 'created'));

        $calendar = $this->admin->get('/admin/calendario?mese=2027-06')->body;
        preg_match('#<th scope="row">Rosa</th>(.*?)</tr>#s', $calendar, $row);
        self::assertSame(3, substr_count($row[1], 'cell-block'), 'nights 20, 21 and 22 are blocked, the 23rd is free');

        // The block really closes the dates.
        self::assertSame(409, $this->act('/admin/prenotazioni', [
            'apartment_id' => $this->apartmentId('rosa'), 'origin' => 'phone', 'check_in' => '2027-06-21', 'check_out' => '2027-06-22', 'adults' => '2', 'guest_name' => 'No',
        ])->status);

        $remove = $this->act('/admin/blocchi/' . $blockId . '/rimuovi');
        self::assertSame(303, $remove->status);
        self::assertSame(0, $this->countRows('availability_blocks'));
        self::assertStringContainsString('Blocco rimosso', $this->follow($remove)->body);
        self::assertSame(1, $this->audit('availability_block', 'deleted'));

        $again = $this->act('/admin/blocchi/' . $blockId . '/rimuovi');
        self::assertStringContainsString('Blocco non trovato', $this->follow($again)->body);
    }

    public function testBlockOverAConfirmedBookingIsRefusedWithTheConflictShown(): void
    {
        $booking = $this->book('2027-06-10', '2027-06-15');
        $before = $this->snapshot();

        $response = $this->act('/admin/blocchi', ['apartment_id' => $this->apartmentId(), 'start_date' => '2027-06-12', 'end_date' => '2027-06-20']);

        self::assertSame(409, $response->status);
        self::assertStringContainsString('prenotazione n. ' . $booking, $response->body);
        $this->assertDatabaseUnchanged($before, 'a refused block must not be stored');
    }

    public function testBlockValidationMapsDateErrorsToTheFormFields(): void
    {
        $response = $this->act('/admin/blocchi', ['apartment_id' => $this->apartmentId(), 'start_date' => '2027-06-20', 'end_date' => '2027-06-10']);

        self::assertSame(422, $response->status);
        self::assertStringContainsString('id="err-end_date"', $response->body);
        self::assertSame(0, $this->countRows('availability_blocks'));
    }

    // === Cancellation ===========================================================

    public function testCancellationNeedsAConfirmationPageAndReopensTheDates(): void
    {
        $id = $this->book('2027-06-10', '2027-06-15');

        $form = $this->admin->get('/admin/prenotazioni/' . $id . '/cancella');
        self::assertSame(200, $form->status);
        self::assertStringContainsString('Conferma la cancellazione', $form->body);
        self::assertSame('confirmed', $this->scalar('SELECT status FROM bookings WHERE id = ?', [$id]), 'opening the page changes nothing');

        $response = $this->act('/admin/prenotazioni/' . $id . '/cancella', ['motivo' => 'Il cliente ha rinunciato']);

        self::assertSame(303, $response->status);
        $row = $this->row('SELECT status, cancellation_reason, cancelled_at FROM bookings WHERE id = ?', [$id]);
        self::assertSame(['cancelled', 'Il cliente ha rinunciato'], [$row['status'], $row['cancellation_reason']]);
        self::assertNotNull($row['cancelled_at']);
        $page = $this->follow($response)->body;
        self::assertStringContainsString('Prenotazione cancellata', $page);
        self::assertStringContainsString('Il cliente ha rinunciato', $page);
        self::assertSame(1, $this->audit('booking', 'status_changed'));

        // The dates are free again, through the admin itself.
        $rebook = $this->act('/admin/prenotazioni', ['apartment_id' => $this->apartmentId(), 'origin' => 'phone', 'check_in' => '2027-06-10', 'check_out' => '2027-06-15', 'adults' => '2', 'guest_name' => 'Nuovo']);
        self::assertSame(303, $rebook->status);

        // A cancelled booking cannot be cancelled again and its form redirects away.
        self::assertSame(303, $this->admin->get('/admin/prenotazioni/' . $id . '/cancella')->status);
        $second = $this->act('/admin/prenotazioni/' . $id . '/cancella');
        self::assertStringContainsString('non è confermata', $this->follow($second)->body);
    }

    public function testCancellingABookingFromARequestAlsoMarksTheRequest(): void
    {
        $request = $this->makeRequest();
        $this->act('/admin/richieste/' . $request['id'] . '/conferma');
        $booking = (int) $this->scalar('SELECT id FROM bookings');

        $this->act('/admin/prenotazioni/' . $booking . '/cancella');

        self::assertSame('cancelled', $this->scalar('SELECT status FROM booking_requests WHERE id = ?', [$request['id']]));
    }

    // === Apartments ============================================================

    /** @return array<string, string> */
    private function apartmentForm(array $overrides = []): array
    {
        return $overrides + [
            'name' => 'Margherita', 'is_active' => '1', 'accepts_online_requests' => '1', 'management_mode' => 'direct', 'managing_agency' => '',
            'max_guests' => '4', 'max_children' => '2', 'max_pets' => '1', 'bedrooms' => '2', 'beds' => '3',
            'check_in_from' => '15:00', 'check_in_until' => '19:30', 'check_out_until' => '10:00', 'indicative_price' => '80,50', 'sort_order' => '1',
            'description_it' => 'Appartamento di prova', 'rules_it' => '', 'meta_title_it' => '', 'meta_description_it' => '',
            'description_en' => '', 'rules_en' => 'Test house rules', 'meta_title_en' => '', 'meta_description_en' => '',
        ];
    }

    public function testEditingAnApartment(): void
    {
        $id = $this->apartmentId();

        $edit = $this->admin->get('/admin/appartamenti/' . $id);
        self::assertSame(200, $edit->status);
        self::assertStringContainsString('margherita', $edit->body);

        $response = $this->act('/admin/appartamenti/' . $id, $this->apartmentForm(['slug' => 'hacked-slug']));

        self::assertSame(303, $response->status);
        $row = $this->row('SELECT * FROM apartments WHERE id = ?', [$id]);
        self::assertSame('margherita', $row['slug'], 'the slug is immutable');
        self::assertSame([4, 2, 1, 2, 3, '15:00:00', '19:30:00', '10:00:00', 8050], [
            (int) $row['max_guests'], (int) $row['max_children'], (int) $row['max_pets'], (int) $row['bedrooms'], (int) $row['beds'],
            $row['check_in_from'], $row['check_in_until'], $row['check_out_until'], (int) $row['indicative_price_cents'],
        ]);
        self::assertSame('Appartamento di prova', $this->scalar("SELECT description FROM apartment_translations WHERE apartment_id = ? AND locale = 'it'", [$id]));
        self::assertSame('Test house rules', $this->scalar("SELECT rules FROM apartment_translations WHERE apartment_id = ? AND locale = 'en'", [$id]));

        $audit = $this->row("SELECT * FROM audit_log WHERE entity_type = 'apartment' AND action = 'updated'");
        self::assertSame(4, json_decode($audit['new_values'], true)['max_guests']);
        self::assertNull(json_decode($audit['old_values'], true)['max_guests']);

        self::assertStringContainsString('Appartamento aggiornato', $this->follow($response)->body);
        $reopened = $this->admin->get('/admin/appartamenti/' . $id)->body;
        self::assertStringContainsString('value="80,50"', $reopened);
        self::assertStringContainsString('value="15:00"', $reopened);

        // Saving the same data again records nothing new.
        $this->act('/admin/appartamenti/' . $id, $this->apartmentForm());
        self::assertSame(1, $this->audit('apartment', 'updated'));
    }

    public function testApartmentLimitsAreAppliedByThePublicRequest(): void
    {
        $this->act('/admin/appartamenti/' . $this->apartmentId(), $this->apartmentForm(['max_pets' => '0']));

        $this->expectException(\App\Domain\ValidationException::class);
        $this->service->createRequest($this->requestInput('2027-06-10', '2027-06-15', ['pets' => 1]));
    }

    public function testDeactivatingAnApartmentStopsNewRequests(): void
    {
        $form = $this->apartmentForm();
        unset($form['is_active'], $form['accepts_online_requests']); // unchecked checkboxes are not sent

        $this->act('/admin/appartamenti/' . $this->apartmentId(), $form);

        self::assertSame(0, (int) $this->scalar("SELECT is_active FROM apartments WHERE slug = 'margherita'"));
        try {
            $this->service->createRequest($this->requestInput('2027-06-10', '2027-06-15'));
            self::fail('Expected a StateException');
        } catch (StateException $e) {
            self::assertSame('apartment_not_bookable', $e->reason);
        }
    }

    public function testInvalidApartmentDataIsRefusedAndTheFormKeepsTheValues(): void
    {
        $before = $this->snapshot();

        $response = $this->act('/admin/appartamenti/' . $this->apartmentId(), $this->apartmentForm([
            'name' => '', 'max_guests' => 'abc', 'check_in_from' => '25:00', 'indicative_price' => '1,234', 'management_mode' => 'franchise',
            'max_pets' => '999', 'description_it' => str_repeat('x', 5001),
        ]));

        self::assertSame(422, $response->status);
        foreach (['Nome non valido', 'Numero non valido', 'Orario non valido', 'Importo non valido', 'Modalità di gestione non valida', 'Testo troppo lungo'] as $message) {
            self::assertStringContainsString($message, $response->body);
        }
        self::assertStringContainsString('value="abc"', $response->body);
        self::assertStringContainsString('value="1,234"', $response->body);
        $this->assertDatabaseUnchanged($before, 'invalid apartment data must not be stored');
    }

    // === Price list ============================================================

    public function testPriceListEditingChangesRealPricesWithoutTouchingCode(): void
    {
        $apartment = $this->apartmentId();

        // Create a rate period (fictitious values).
        $create = $this->act('/admin/listino/tariffe', [
            'apartment_id' => $apartment, 'label_it' => '[TEST] Bassa', 'label_en' => '[TEST] Low', 'start_date' => '2027-06-01', 'end_date' => '2027-07-01',
            'nightly_rate' => '10', 'min_nights' => '2', 'is_active' => '1',
        ]);
        self::assertSame(303, $create->status);
        $rateId = (int) $this->scalar('SELECT id FROM seasonal_rates');
        self::assertSame(1000, (int) $this->scalar('SELECT nightly_rate_cents FROM seasonal_rates WHERE id = ?', [$rateId]));
        self::assertSame(1, $this->audit('seasonal_rate', 'created'));
        self::assertStringContainsString('[TEST] Bassa', $this->follow($create)->body);

        // An overlapping period is refused with a readable message.
        $overlap = $this->act('/admin/listino/tariffe', [
            'apartment_id' => $apartment, 'label_it' => '[TEST] Doppia', 'start_date' => '2027-06-15', 'end_date' => '2027-07-15', 'nightly_rate' => '20', 'is_active' => '1',
        ]);
        self::assertSame(422, $overlap->status);
        self::assertStringContainsString('Si sovrappone a un altro periodo attivo', $overlap->body);
        self::assertSame(1, $this->countRows('seasonal_rates'));

        // A global rule: 2 adults included, each extra adult 5 euros per night (fictitious).
        $rule = $this->act('/admin/listino/regole', [
            'apartment_id' => '', 'applies_to' => 'adult', 'charge_basis' => 'per_night', 'free_units' => '2', 'amount' => '5', 'label_it' => '[TEST] Adulto extra', 'sort_order' => '0', 'is_active' => '1',
        ]);
        self::assertSame(303, $rule->status);
        $ruleId = (int) $this->scalar('SELECT id FROM pricing_rules');
        self::assertNull($this->scalar('SELECT apartment_id FROM pricing_rules WHERE id = ?', [$ruleId]));

        // The public request is now priced from what the admin entered: 3 nights x 10 + 1 extra adult x 5 x 3 nights.
        $request = $this->service->createRequest($this->requestInput('2027-06-10', '2027-06-13', ['adults' => 3]));
        self::assertSame(3000 + 1500, (int) $this->scalar('SELECT quoted_total_cents FROM booking_requests WHERE id = ?', [$request['id']]));

        // Editing the price changes new quotes; the old one stays frozen.
        $edit = $this->admin->get('/admin/listino/tariffe/' . $rateId);
        self::assertStringContainsString('value="10,00"', $edit->body);
        $update = $this->act('/admin/listino/tariffe/' . $rateId, [
            'label_it' => '[TEST] Bassa', 'label_en' => '', 'start_date' => '2027-06-01', 'end_date' => '2027-07-01', 'nightly_rate' => '12,50', 'min_nights' => '', 'is_active' => '1',
        ]);
        self::assertSame(303, $update->status);
        self::assertSame(1250, (int) $this->scalar('SELECT nightly_rate_cents FROM seasonal_rates WHERE id = ?', [$rateId]));
        self::assertSame(4500, (int) $this->scalar('SELECT quoted_total_cents FROM booking_requests WHERE id = ?', [$request['id']]));
        $audit = $this->row("SELECT * FROM audit_log WHERE entity_type = 'seasonal_rate' AND action = 'updated'");
        self::assertSame(1000, json_decode($audit['old_values'], true)['nightly_rate_cents']);
        self::assertSame(1250, json_decode($audit['new_values'], true)['nightly_rate_cents']);

        // The minimum stay removed by the edit is gone too: a 1-night request now works.
        self::assertGreaterThan(0, $this->service->createRequest($this->requestInput('2027-06-20', '2027-06-21'))['id']);

        // Rule edit and delete.
        $this->act('/admin/listino/regole/' . $ruleId, ['apartment_id' => '', 'applies_to' => 'adult', 'charge_basis' => 'per_night', 'free_units' => '2', 'amount' => '7,5', 'label_it' => '[TEST] Adulto extra', 'sort_order' => '0', 'is_active' => '1']);
        self::assertSame(750, (int) $this->scalar('SELECT amount_cents FROM pricing_rules WHERE id = ?', [$ruleId]));
        self::assertSame(303, $this->act('/admin/listino/regole/' . $ruleId . '/elimina')->status);
        self::assertSame(0, $this->countRows('pricing_rules'));

        // Delete the rate: new requests become "price to be confirmed".
        self::assertSame(303, $this->act('/admin/listino/tariffe/' . $rateId . '/elimina')->status);
        $unpriced = $this->service->createRequest($this->requestInput('2027-06-25', '2027-06-27'));
        self::assertNull($this->scalar('SELECT quoted_total_cents FROM booking_requests WHERE id = ?', [$unpriced['id']]));
        self::assertSame(1, $this->audit('seasonal_rate', 'deleted'));
        self::assertSame(1, $this->audit('pricing_rule', 'deleted'));
    }

    public function testPriceListPageShowsDatesWithoutARateAndRulesOfTheApartment(): void
    {
        $apartment = $this->apartmentId();
        $this->act('/admin/listino/tariffe', ['apartment_id' => $apartment, 'label_it' => '[TEST] Una', 'start_date' => '2027-06-01', 'end_date' => '2027-06-10', 'nightly_rate' => '10', 'is_active' => '1']);
        $this->act('/admin/listino/regole', ['apartment_id' => $apartment, 'applies_to' => 'stay', 'charge_basis' => 'per_stay', 'free_units' => '0', 'amount' => '30', 'label_it' => '[TEST] Pulizia', 'is_active' => '1']);
        $this->act('/admin/listino/regole', ['apartment_id' => $this->apartmentId('rosa'), 'applies_to' => 'stay', 'charge_basis' => 'per_stay', 'free_units' => '0', 'amount' => '99', 'label_it' => '[TEST] Solo Rosa', 'is_active' => '1']);

        $page = $this->admin->get('/admin/listino?appartamento=' . $apartment)->body;

        self::assertStringContainsString('[TEST] Una', $page);
        self::assertStringContainsString('[TEST] Pulizia', $page);
        self::assertStringNotContainsString('[TEST] Solo Rosa', $page, 'rules of other apartments are not mixed in');
        self::assertStringContainsString('Date senza tariffa', $page);
        self::assertStringContainsString('€ 30,00', $page);

        $empty = $this->admin->get('/admin/listino?appartamento=' . $this->apartmentId('viola'))->body;
        self::assertStringContainsString('Nessuna tariffa', $empty);
    }

    public function testPriceListValidationAndMissingItems(): void
    {
        $apartment = $this->apartmentId();
        $before = $this->snapshot();

        $bad = $this->act('/admin/listino/tariffe', ['apartment_id' => $apartment, 'label_it' => '', 'start_date' => '2027-06-10', 'end_date' => '2027-06-01', 'nightly_rate' => 'abc', 'min_nights' => '99', 'is_active' => '1']);
        self::assertSame(422, $bad->status);
        foreach (['Etichetta non valida', 'La data finale deve essere successiva', 'Importo non valido', 'Soggiorno minimo non valido'] as $message) {
            self::assertStringContainsString($message, $bad->body);
        }
        self::assertStringContainsString('value="abc"', $bad->body);

        $badRule = $this->act('/admin/listino/regole', ['apartment_id' => '', 'applies_to' => 'goat', 'charge_basis' => 'weekly', 'free_units' => '99', 'amount' => '-1', 'label_it' => '']);
        self::assertSame(422, $badRule->status);
        self::assertStringContainsString('Tipo di voce non valido', $badRule->body);
        $this->assertDatabaseUnchanged($before, 'invalid price data must not be stored');

        self::assertSame(404, $this->act('/admin/listino/tariffe/999999')->status);
        self::assertSame(404, $this->act('/admin/listino/regole/999999/elimina')->status);
    }

    // === Audit log, dashboard, calendar =========================================

    public function testAuditLogListsWhatHappened(): void
    {
        $request = $this->makeRequest();
        $this->act('/admin/richieste/' . $request['id'] . '/conferma');
        $this->act('/admin/blocchi', ['apartment_id' => $this->apartmentId('rosa'), 'start_date' => '2027-09-01', 'end_date' => '2027-09-03', 'reason' => 'Prova']);

        $all = $this->admin->get('/admin/storico');
        self::assertSame(200, $all->status);
        self::assertStringContainsString('Richiesta pending → confirmed', $all->body);
        self::assertStringContainsString('Blocco disponibilità creato', $all->body);
        self::assertStringContainsString('<pre>', $all->body);

        $onlyBlocks = $this->admin->get('/admin/storico?tipo=availability_block')->body;
        self::assertStringContainsString('Blocco disponibilità creato', $onlyBlocks);
        self::assertStringNotContainsString('Richiesta pending → confirmed', $onlyBlocks);
    }

    public function testDashboardCounts(): void
    {
        $this->makeRequest('2027-06-10', '2027-06-12');
        $this->makeRequest('2027-07-10', '2027-07-12');
        $this->book('2099-01-10', '2099-01-12');

        $page = $this->admin->get('/admin')->body;

        self::assertMatchesRegularExpression('#Richieste in attesa</dt>\s*<dd><a[^>]*>2</a>#', $page);
        self::assertMatchesRegularExpression('#Prenotazioni in corso o future</dt>\s*<dd><a[^>]*>1</a>#', $page);
        self::assertMatchesRegularExpression('#Appartamenti</dt>\s*<dd><a[^>]*>6</a>#', $page);
    }

    public function testCalendarShowsBookingsOnTheRightNightsAndValidatesTheMonth(): void
    {
        $this->book('2027-06-10', '2027-06-15'); // nights 10..14

        $page = $this->admin->get('/admin/calendario?mese=2027-06');
        self::assertSame(200, $page->status);
        preg_match('#<th scope="row">Margherita</th>(.*?)</tr>#s', $page->body, $row);
        self::assertSame(5, substr_count($row[1], 'cell-booking'));
        preg_match('#<th scope="row">Rosa</th>(.*?)</tr>#s', $page->body, $other);
        self::assertSame(0, substr_count($other[1], 'cell-booking'));
        self::assertStringContainsString('mese=2027-05', $page->body);
        self::assertStringContainsString('mese=2027-07', $page->body);

        // A stay crossing the month boundary shows only in the nights that belong to each month.
        $this->book('2027-07-29', '2027-08-03', 'girasole');
        $july = $this->admin->get('/admin/calendario?mese=2027-07')->body;
        preg_match('#<th scope="row">Girasole</th>(.*?)</tr>#s', $july, $july29);
        self::assertSame(3, substr_count($july29[1], 'cell-booking'), 'nights 29, 30 and 31 July');
        $august = $this->admin->get('/admin/calendario?mese=2027-08')->body;
        preg_match('#<th scope="row">Girasole</th>(.*?)</tr>#s', $august, $august1);
        self::assertSame(2, substr_count($august1[1], 'cell-booking'), 'nights 1 and 2 August; check-out day 3 is free');

        foreach (['2027-13', 'abc', '2027-6', '1999-12', '2101-01', '2027-06-01'] as $month) {
            self::assertSame(400, $this->admin->get('/admin/calendario?mese=' . $month)->status, $month);
        }
        self::assertSame(200, $this->admin->get('/admin/calendario')->status, 'default: current month');
    }

    // === Escaping ==============================================================

    public function testGuestSuppliedTextIsEscapedOnEveryPage(): void
    {
        $xss = '<script>alert(1)</script>';
        $request = $this->makeRequest('2027-06-10', '2027-06-15', ['first_name' => $xss, 'last_name' => '<b>x</b>', 'notes' => '"><img src=x onerror=alert(1)>']);
        $confirmed = $this->act('/admin/richieste/' . $request['id'] . '/conferma');
        $booking = (int) $this->scalar('SELECT id FROM bookings');
        $this->service->createBlock($this->apartmentId('rosa'), '2027-06-20', '2027-06-22', '<i onmouseover=alert(1)>blocco</i>');
        $this->act('/admin/appartamenti/' . $this->apartmentId('viola'), $this->apartmentForm(['name' => 'Viola <u>x</u>', 'description_it' => $xss]));
        self::assertSame(303, $confirmed->status);

        $pages = [
            '/admin/richieste', '/admin/richieste/' . $request['id'], '/admin/prenotazioni', '/admin/prenotazioni/' . $booking,
            '/admin/prenotazioni/' . $booking . '/cancella', '/admin/calendario?mese=2027-06', '/admin/blocchi', '/admin/appartamenti',
            '/admin/appartamenti/' . $this->apartmentId('viola'), '/admin/storico', '/admin/listino',
        ];
        foreach ($pages as $path) {
            $body = $this->admin->get($path)->body;
            foreach (['<script>alert(1)</script>', '<img src=x', '<b>x</b>', '<i onmouseover', '<u>x</u>'] as $raw) {
                self::assertStringNotContainsString($raw, $body, "$path contains unescaped text: $raw");
            }
        }
        self::assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $this->admin->get('/admin/richieste/' . $request['id'])->body);
        self::assertStringContainsString('&lt;i onmouseover=alert(1)&gt;blocco&lt;/i&gt;', $this->admin->get('/admin/blocchi')->body);
        self::assertStringContainsString('title="&lt;script&gt;', $this->admin->get('/admin/calendario?mese=2027-06')->body, 'attribute context is escaped too');
    }

    public function testNotFoundAndMethodErrorsInsideAdminAreFriendlyAndLeakNothing(): void
    {
        $notFound = $this->admin->get('/admin/non-esiste');
        self::assertSame(404, $notFound->status);
        self::assertStringContainsString('Pagina non trovata', $notFound->body);
        self::assertStringContainsString('Vai all\'area amministrativa', $notFound->body);
        self::assertStringNotContainsString('Stack trace', $notFound->body);
        self::assertStringNotContainsString('/var/www', $notFound->body);

        $method = $this->admin->send('DELETE', '/admin/richieste');
        self::assertContains($method->status, [403, 405]);
    }

    public function testAdminPagesHaveTheNavigationAndNoScripts(): void
    {
        $page = $this->admin->get('/admin');

        foreach (['/admin/richieste', '/admin/prenotazioni', '/admin/calendario', '/admin/blocchi', '/admin/appartamenti', '/admin/listino', '/admin/storico', '/admin/export'] as $link) {
            self::assertStringContainsString('href="' . $link . '"', $page->body);
        }
        foreach (['/admin', '/admin/richieste', '/admin/prenotazioni/nuova', '/admin/listino', '/admin/calendario'] as $path) {
            $body = $this->admin->get($path)->body;
            self::assertStringNotContainsString('<script', $body, "$path must not need JavaScript");
            self::assertStringNotContainsString(' onclick=', $body);
            self::assertStringContainsString('lang="it"', $body);
            self::assertStringContainsString('class="skip-link"', $body);
        }
    }
}
