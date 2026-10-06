<?php

declare(strict_types=1);

namespace Tests\Http;

use Tests\Support\PublicSiteTestCase;

/**
 * The availability request, walked step by step over real HTTP as a browser would:
 * dates -> available apartments with price -> customer data -> summary and consent -> "request received".
 */
final class PublicRequestFlowTest extends PublicSiteTestCase
{
    /** The other apartments take no online requests, so lists show only this one. */
    private function only(string $slug): void
    {
        $this->db->prepare('UPDATE apartments SET accepts_online_requests = 0 WHERE slug <> ?')->execute([$slug]);
    }

    // === The whole flow =========================================================

    public function testFullFlowStoresAPendingRequestWithTheServerSidePrice(): void
    {
        [$in, $out] = $this->stay();
        $this->equip('margherita', 4, [$in, $out], 10000);
        $client = $this->client();

        $list = $client->get(self::path('request.apartments') . '?' . http_build_query($this->searchParams($in, $out)));
        self::assertStringContainsString('Margherita', $list->body);
        self::assertStringContainsString('400,00', $list->body, '4 nights at the fictitious 100,00 rate');
        self::assertStringContainsString('Scegli Margherita', $list->body);

        $summary = $this->toSummary($client, 'margherita', $in, $out);
        self::assertStringContainsString('Margherita', $summary->body);
        self::assertStringContainsString('400,00', $summary->body);
        self::assertStringContainsString('name="privacy_accepted"', $summary->body);
        self::assertSame(0, $this->requestCount(), 'nothing is stored before the final step');

        $sent = $this->sendFromSummary($client, $summary);
        self::assertSame(303, $sent->status);
        self::assertSame(1, preg_match('#/richiedi-disponibilita/ricevuta\?rif=(LV-[A-Z0-9]+)$#', (string) $sent->location(), $m), (string) $sent->location());

        $received = $client->get((string) $sent->location());
        self::assertSame(200, $received->status);
        self::assertStringContainsString('Richiesta ricevuta', $received->body);
        self::assertStringContainsString($m[1], $received->body);
        self::assertStringNotContainsStringIgnoringCase('prenotazione confermata', $received->body);

        $row = $this->row('SELECT * FROM booking_requests');
        self::assertSame('pending', $row['status']);
        self::assertSame($m[1], $row['reference']);
        self::assertSame($this->apartmentId('margherita'), (int) $row['apartment_id']);
        self::assertSame($in, $row['check_in']);
        self::assertSame($out, $row['check_out']);
        self::assertSame([2, 0, 0], [(int) $row['adults'], (int) $row['children'], (int) $row['pets']]);
        self::assertSame('Mario', $row['first_name']);
        self::assertSame('mario.rossi@example.test', $row['email']);
        self::assertSame('it', $row['locale']);
        self::assertSame(40000, (int) $row['quoted_total_cents']);
        self::assertSame(0, $this->countRows('bookings'), 'a request never creates a booking');
        self::assertSame(1, $this->countRows('email_outbox', "type = 'new_request_admin'"), 'the manager is notified');
        self::assertSame([], $client->cookies, 'no cookie at any step of the flow');
    }

    public function testEnglishFlowStoresTheEnglishLocale(): void
    {
        [$in, $out] = $this->stay();
        $this->equip('margherita', 4, [$in, $out]);
        $client = $this->client();

        $summary = $this->toSummary($client, 'margherita', $in, $out, self::CUSTOMER, 'en');
        self::assertStringContainsString('<html lang="en">', $summary->body);
        self::assertStringContainsString('Summary and send', $summary->body);
        $sent = $this->sendFromSummary($client, $summary, ['privacy_accepted' => '1'], 'en');

        self::assertSame(303, $sent->status);
        self::assertStringContainsString('/en/request-availability/received?rif=', (string) $sent->location());
        $received = $client->get((string) $sent->location());
        self::assertStringContainsString('Request received', $received->body);
        self::assertStringNotContainsStringIgnoringCase('booking confirmed', $received->body);
        self::assertSame('en', $this->row('SELECT locale FROM booking_requests')['locale']);
    }

    public function testNothingSentByTheBrowserCanChangeThePriceOrTheStatus(): void
    {
        [$in, $out] = $this->stay();
        $this->equip('margherita', 4, [$in, $out], 10000);
        $client = $this->client();

        $summary = $this->toSummary($client, 'margherita', $in, $out);
        $sent = $this->sendFromSummary($client, $summary, [
            'privacy_accepted' => '1',
            'total_cents' => '1', 'quoted_total_cents' => '1', 'price' => '0,01', 'total' => '1', 'nightly_rate_cents' => '1',
            'status' => 'confirmed', 'apartment_id' => (string) $this->apartmentId('rosa'), 'reference' => 'HACKED',
        ]);

        self::assertSame(303, $sent->status);
        $row = $this->row('SELECT * FROM booking_requests');
        self::assertSame(40000, (int) $row['quoted_total_cents']);
        self::assertSame('pending', $row['status']);
        self::assertSame($this->apartmentId('margherita'), (int) $row['apartment_id']);
        self::assertNotSame('HACKED', $row['reference']);
    }

    public function testTheLanguageOfTheRouteWinsOverAFieldSentByTheBrowser(): void
    {
        [$in, $out] = $this->stay();
        $this->equip('margherita', 4, [$in, $out]);
        $client = $this->client();

        $this->sendFromSummary($client, $this->toSummary($client, 'margherita', $in, $out), ['privacy_accepted' => '1', 'locale' => 'en']);

        self::assertSame('it', $this->row('SELECT locale FROM booking_requests')['locale']);
    }

    public function testWithoutAPriceListTheRequestIsStillSavedAsPriceToBeConfirmed(): void
    {
        [$in, $out] = $this->stay();
        $this->equip('margherita', 4); // capacity only, no rates
        $client = $this->client();

        $list = $client->get(self::path('request.apartments') . '?' . http_build_query($this->searchParams($in, $out)));
        self::assertStringContainsString('Prezzo da confermare', $list->body);

        $summary = $this->toSummary($client, 'margherita', $in, $out);
        self::assertStringContainsString('Prezzo da confermare', $summary->body);
        self::assertSame(303, $this->sendFromSummary($client, $summary)->status);
        self::assertNull($this->row('SELECT quoted_total_cents FROM booking_requests')['quoted_total_cents']);
    }

    public function testTheAdminSeesTheNewRequestAsPending(): void
    {
        [$in, $out] = $this->stay();
        $this->equip('margherita', 4, [$in, $out]);
        $client = $this->client();
        $this->sendFromSummary($client, $this->toSummary($client, 'margherita', $in, $out));

        $admin = $this->loggedInClient()->get('/admin/richieste');

        self::assertStringContainsString('Rossi', $admin->body);
        self::assertStringContainsString('Margherita', $admin->body);
    }

    // === Availability and apartment limits ======================================

    public function testApartmentsAlreadyBookedAreNotOffered(): void
    {
        [$in, $out] = $this->stay();
        $this->equip('margherita', 4, [$in, $out]);
        $this->equip('girasole', 4, [$in, $out]);
        (new \App\Service\BookingService($this->db))->createManualBooking($this->manualInput($in, $out));
        $client = $this->client();

        $list = $client->get(self::path('request.apartments') . '?' . http_build_query($this->searchParams($in, $out)));

        self::assertStringNotContainsString('Scegli Margherita', $list->body);
        self::assertStringContainsString('Scegli Girasole', $list->body);
    }

    public function testBlockedDatesAndPartialOverlapsAreNotOffered(): void
    {
        [$in, $out] = $this->stay();
        $this->equip('margherita', 4, [$in, $out]);
        $this->only('margherita');
        $middle = (new \DateTimeImmutable($in))->modify('+2 days')->format('Y-m-d');
        $after = (new \DateTimeImmutable($in))->modify('+3 days')->format('Y-m-d');
        (new \App\Service\BookingService($this->db))->createBlock($this->apartmentId('margherita'), $middle, $after);
        $client = $this->client();

        $list = $client->get(self::path('request.apartments') . '?' . http_build_query($this->searchParams($in, $out)));

        self::assertStringNotContainsString('Scegli Margherita', $list->body);
        self::assertStringContainsString('non ci sono appartamenti disponibili', $list->body);
    }

    public function testBackToBackStaysRemainAvailable(): void
    {
        [$in, $out] = $this->stay(90, 4);
        $this->equip('margherita', 4, [$in, $out]);
        $before = (new \DateTimeImmutable($in))->modify('-3 days')->format('Y-m-d');
        (new \App\Service\BookingService($this->db))->createManualBooking($this->manualInput($before, $in)); // leaves on the day we arrive
        $client = $this->client();

        $list = $client->get(self::path('request.apartments') . '?' . http_build_query($this->searchParams($in, $out)));

        self::assertStringContainsString('Scegli Margherita', $list->body, '[check_in, check_out): the departure day is free');
    }

    public function testTooManyGuestsLeavesNoApartmentAndSaysSo(): void
    {
        [$in, $out] = $this->stay();
        $this->equip('margherita', 4, [$in, $out]);
        $this->only('margherita');
        $client = $this->client();

        $list = $client->get(self::path('request.apartments') . '?' . http_build_query($this->searchParams($in, $out, ['adults' => '5'])));

        self::assertSame(200, $list->status);
        self::assertStringNotContainsString('Scegli', $list->body);
        self::assertStringContainsString('non ci sono appartamenti disponibili', $list->body);
    }

    public function testPetsNotAllowedAndMinimumStayAreExplainedAndCannotBeChosen(): void
    {
        [$in, $out] = $this->stay(90, 4);
        $this->equip('margherita', 4);
        $this->equip('girasole', 4, [$in, $out], 10000);
        $this->db->exec("UPDATE apartments SET max_pets = 0 WHERE slug = 'margherita'");
        $this->db->exec("UPDATE apartments SET max_children = 1 WHERE slug = 'girasole'");
        $client = $this->client();

        $pets = $client->get(self::path('request.apartments') . '?' . http_build_query($this->searchParams($in, $out, ['pets' => '1'])));
        self::assertStringContainsString('animali non sono ammessi', $pets->body);
        self::assertStringNotContainsString('Scegli Margherita', $pets->body);

        $children = $client->get(self::path('request.apartments') . '?' . http_build_query($this->searchParams($in, $out, ['children' => '2'])));
        self::assertStringContainsString('al massimo 1', $children->body);
        self::assertStringNotContainsString('Scegli Girasole', $children->body);

        $details = $client->get(self::path('request.details') . '?' . http_build_query($this->searchParams($in, $out, ['pets' => '1']) + ['apartment' => 'margherita']));
        self::assertSame(303, $details->status, 'an unsuitable apartment cannot be reached by typing the URL');

        // Minimum stay.
        $this->db->exec('DELETE FROM seasonal_rates');
        $this->equip('girasole', 4, [$in, $out], 10000);
        $this->db->exec('UPDATE seasonal_rates SET min_nights = 7');
        $short = $client->get(self::path('request.apartments') . '?' . http_build_query($this->searchParams($in, $out)));
        self::assertStringContainsString('soggiorno minimo è di 7 notti', $short->body);
        self::assertStringNotContainsString('Scegli Girasole', $short->body);
    }

    public function testAnApartmentThatTakesNoOnlineRequestsCannotBeChosen(): void
    {
        [$in, $out] = $this->stay();
        $this->equip('margherita', 4, [$in, $out]);
        $this->db->exec("UPDATE apartments SET accepts_online_requests = 0 WHERE slug = 'margherita'");
        $client = $this->client();

        $list = $client->get(self::path('request.apartments') . '?' . http_build_query($this->searchParams($in, $out)));
        self::assertStringNotContainsString('Margherita', $list->body);

        $details = $client->get(self::path('request.details') . '?' . http_build_query($this->searchParams($in, $out) + ['apartment' => 'margherita']));
        self::assertSame(303, $details->status);
        self::assertStringContainsString('non è possibile inviare la richiesta online', $client->get(self::path('apartment', 'it', ['slug' => 'margherita']))->body);
    }

    public function testDatesTakenBetweenSummaryAndSendAreRefusedWithoutStoringAnything(): void
    {
        [$in, $out] = $this->stay();
        $this->equip('margherita', 4, [$in, $out]);
        $client = $this->client();
        $summary = $this->toSummary($client, 'margherita', $in, $out);

        (new \App\Service\BookingService($this->db))->createManualBooking($this->manualInput($in, $out)); // taken meanwhile
        $sent = $this->sendFromSummary($client, $summary);

        self::assertSame(303, $sent->status);
        self::assertStringContainsString('/richiedi-disponibilita/appartamenti', (string) $sent->location(), 'back to the choice of apartment');
        self::assertSame(0, $this->requestCount());
    }

    // === Validation =============================================================

    public function testStepOneRejectsInvalidDatesAndGuestsKeepingWhatWasTyped(): void
    {
        $client = $this->client();
        [$in, $out] = $this->stay();
        $yesterday = (new \DateTimeImmutable('yesterday'))->format('Y-m-d');
        $cases = [
            'check-out before check-in' => [['check_in' => $out, 'check_out' => $in], 'La partenza deve essere dopo l’arrivo'],
            'same day' => [['check_in' => $in, 'check_out' => $in], 'La partenza deve essere dopo l’arrivo'],
            'garbage date' => [['check_in' => '31/02/2027', 'check_out' => $out], 'Inserisci una data valida'],
            'past arrival' => [['check_in' => $yesterday, 'check_out' => (new \DateTimeImmutable('+2 days'))->format('Y-m-d')], 'non può essere passata'],
            'no adults' => [['adults' => '0'], 'almeno un adulto'],
            'negative children' => [['children' => '-1'], 'numero valido di bambini'],
            'text pets' => [['pets' => 'many'], 'numero valido di animali'],
            'far future' => [['check_in' => '2099-01-01', 'check_out' => '2099-01-05'], 'troppo lontana'],
        ];

        foreach ($cases as $name => [$override, $message]) {
            $response = $client->get(self::path('request.apartments') . '?' . http_build_query($this->searchParams($in, $out, $override)));

            self::assertSame(422, $response->status, $name);
            self::assertStringContainsString($message, $response->body, $name);
            self::assertStringContainsString('role="alert"', $response->body, $name);
            self::assertStringContainsString('aria-invalid="true"', $response->body, $name);
            self::assertStringContainsString('value="' . ($override['adults'] ?? '2') . '"', $response->body, "$name keeps the typed adults");
        }
        self::assertSame(0, $this->requestCount());
    }

    public function testStepOneSurvivesArraysAndEmptyInput(): void
    {
        $client = $this->client();

        self::assertSame(303, $client->get(self::path('request.apartments'))->status, 'no parameters: back to the form');
        self::assertSame(303, $client->get(self::path('request.apartments') . '?check_in[]=a&check_out[]=b&adults[]=1')->status, 'arrays count as missing');
        self::assertSame(303, $client->get(self::path('request.apartments') . '?check_in=&check_out=&adults=')->status, 'empty dates count as missing');
        foreach (['?check_in=' . str_repeat('9', 5000), '?adults=999999999999999999999&check_in=2027-01-01&check_out=2027-01-02'] as $query) {
            self::assertSame(422, $client->get(self::path('request.apartments') . $query)->status, $query);
        }
        self::assertSame(200, $client->get(self::path('request') . '?check_in[]=x')->status);
    }

    public function testCustomerDataErrorsShowMessagesAndKeepTheOtherFields(): void
    {
        [$in, $out] = $this->stay();
        $this->equip('margherita', 4, [$in, $out]);
        $cases = [
            'email' => [['email' => 'not-an-email'], 'indirizzo email valido', 'f-email'],
            'phone' => [['phone' => 'abc'], 'numero di telefono valido', 'f-phone'],
            'first name' => [['first_name' => ''], 'Inserisci il nome', 'f-first_name'],
            'last name' => [['last_name' => '   '], 'Inserisci il cognome', 'f-last_name'],
            'notes' => [['notes' => str_repeat('x', 2001)], 'note sono troppo lunghe', 'f-notes'],
        ];

        foreach ($cases as $name => [$override, $message, $anchor]) {
            $client = $this->client();
            $response = $this->toSummary($client, 'margherita', $in, $out, $override + self::CUSTOMER);

            self::assertSame(422, $response->status, $name);
            self::assertStringContainsString($message, $response->body, $name);
            self::assertStringContainsString('href="#' . $anchor . '"', $response->body, "$name: the error links to its field");
            self::assertStringContainsString('aria-invalid="true"', $response->body, $name);
            self::assertStringContainsString('name="_form"', $response->body, "$name: the form can be sent again");
            if ($name !== 'first name') {
                self::assertStringContainsString('value="Mario"', $response->body, "$name keeps the other fields");
            }
        }
        self::assertSame(0, $this->requestCount());
    }

    public function testPrivacyConsentIsRequiredAndNothingIsStoredWithoutIt(): void
    {
        [$in, $out] = $this->stay();
        $this->equip('margherita', 4, [$in, $out]);
        $client = $this->client();
        $summary = $this->toSummary($client, 'margherita', $in, $out);

        foreach ([[], ['privacy_accepted' => '0'], ['privacy_accepted' => ''], ['privacy_accepted' => 'on'], ['privacy_accepted' => 'true'], ['privacy_accepted' => ['1']]] as $extra) {
            $response = $this->sendFromSummary($client, $summary, $extra);

            self::assertSame(422, $response->status, json_encode($extra));
            self::assertStringContainsString('accettare l’informativa sulla privacy', $response->body, json_encode($extra));
            self::assertStringContainsString('Riepilogo e invio', $response->body);
        }
        self::assertSame(0, $this->requestCount());
        self::assertSame(0, $this->countRows('email_outbox'));
    }

    public function testEnglishErrorMessagesAreInEnglish(): void
    {
        [$in, $out] = $this->stay();
        $this->equip('margherita', 4, [$in, $out]);
        $client = $this->client();

        $step1 = $client->get(self::path('request.apartments', 'en') . '?' . http_build_query($this->searchParams($out, $in)));
        self::assertSame(422, $step1->status);
        self::assertStringContainsString('The departure must be after the arrival', $step1->body);

        $step3 = $this->toSummary($client, 'margherita', $in, $out, ['email' => 'bad'] + self::CUSTOMER, 'en');
        self::assertSame(422, $step3->status);
        self::assertStringContainsString('Enter a valid email address', $step3->body);
        self::assertStringNotContainsString('indirizzo email', $step3->body);
    }

    public function testCustomerTextIsStoredExactlyAndShownEscaped(): void
    {
        [$in, $out] = $this->stay();
        $this->equip('margherita', 4, [$in, $out]);
        $client = $this->client();
        $notes = "Arriveremo tardi <b>forse</b>\n\"con\" l'auto & 2 cani";
        $customer = ['first_name' => '<script>alert(1)</script>', 'last_name' => "O'Brien \"Jr\"", 'notes' => $notes] + self::CUSTOMER;

        $summary = $this->toSummary($client, 'margherita', $in, $out, $customer);

        self::assertSame(200, $summary->status);
        self::assertStringNotContainsString('<script>alert(1)</script>', $summary->body);
        self::assertStringNotContainsString('<b>forse</b>', $summary->body);
        self::assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $summary->body);

        $this->sendFromSummary($client, $summary);
        $row = $this->row('SELECT * FROM booking_requests');
        self::assertSame('<script>alert(1)</script>', $row['first_name'], 'stored as typed; output is always escaped');
        self::assertSame("O'Brien \"Jr\"", $row['last_name']);
        self::assertSame($notes, str_replace("\r\n", "\n", (string) $row['notes']));
    }

    public function testEditingTheDataFromTheSummaryShowsTheFormFilledInAgain(): void
    {
        [$in, $out] = $this->stay();
        $this->equip('margherita', 4, [$in, $out]);
        $client = $this->client();
        $summary = $this->toSummary($client, 'margherita', $in, $out, ['notes' => 'una nota'] + self::CUSTOMER);

        $edit = $client->post(self::path('request.details'), $this->hiddenFields($summary->body));

        self::assertSame(200, $edit->status);
        self::assertStringContainsString('value="Mario"', $edit->body);
        self::assertStringContainsString('value="mario.rossi@example.test"', $edit->body);
        self::assertStringContainsString('una nota', $edit->body);
        self::assertSame(0, $this->requestCount(), 'editing writes nothing');
    }

    public function testUnknownOrMaliciousApartmentSlugsCannotBeSent(): void
    {
        [$in, $out] = $this->stay();
        $this->equip('margherita', 4, [$in, $out]);
        $client = $this->client();
        $summary = $this->toSummary($client, 'margherita', $in, $out);

        foreach (['nope', "margherita' OR '1'='1", '../admin', 'MARGHERITA', '', str_repeat('a', 500)] as $slug) {
            $fields = ['apartment' => $slug, 'privacy_accepted' => '1'] + $this->hiddenFields($summary->body);
            $response = $client->post(self::path('request.submit'), $fields);

            self::assertSame(303, $response->status, "slug: $slug");
            self::assertStringContainsString('/richiedi-disponibilita/appartamenti', (string) $response->location());
        }
        self::assertSame(0, $this->requestCount());
    }

    // === Received page ==========================================================

    public function testReceivedPageShowsOnlyAWellFormedReference(): void
    {
        $client = $this->client();

        self::assertStringContainsString('ABC234', $client->get(self::path('request.received') . '?rif=ABC234')->body);
        foreach (['<script>alert(1)</script>', 'abc', 'ABC 234', str_repeat('A', 40), 'ABC234%0A'] as $bad) {
            $body = $client->get(self::path('request.received') . '?rif=' . rawurlencode($bad))->body;
            self::assertStringNotContainsString('Riferimento della richiesta', $body, $bad);
            self::assertStringNotContainsString('<script>alert(1)', $body);
        }
        $body = $client->get(self::path('request.received'))->body;
        self::assertStringContainsString('Richiesta ricevuta', $body);
        self::assertStringContainsString('non una prenotazione', $body);
    }
}
