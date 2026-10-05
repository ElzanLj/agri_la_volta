<?php

declare(strict_types=1);

namespace Tests\Http;

use Tests\Support\HttpClient;
use Tests\Support\HttpResponse;
use Tests\Support\HttpTestCase;

/** CSV exports over real HTTP: format, filters, privacy and spreadsheet-formula neutralisation. */
final class AdminExportTest extends HttpTestCase
{
    private HttpClient $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->loggedInClient();
    }

    /** @return array{header: list<string>, rows: list<list<string>>} */
    private function parse(HttpResponse $response): array
    {
        self::assertSame(200, $response->status);
        self::assertStringStartsWith("\xEF\xBB\xBF", $response->body, 'UTF-8 BOM so Excel reads accents correctly');
        $stream = fopen('php://memory', 'w+');
        fwrite($stream, substr($response->body, 3));
        rewind($stream);

        $rows = [];
        while (($row = fgetcsv($stream, 0, ';', '"', '')) !== false) {
            $rows[] = $row;
        }
        fclose($stream);

        return ['header' => array_shift($rows) ?? [], 'rows' => $rows];
    }

    public function testRequestsCsvFormatAndHeaders(): void
    {
        $request = $this->makeRequest('2027-06-10', '2027-06-15', ['adults' => 2, 'children' => 1, 'pets' => 1, 'notes' => "Arrivo tardi;\nporto il \"cane\""]);

        $response = $this->admin->get('/admin/export/richieste.csv');

        self::assertSame('text/csv; charset=UTF-8', $response->header('Content-Type'));
        self::assertMatchesRegularExpression('/^attachment; filename="richieste-\d{4}-\d{2}-\d{2}\.csv"$/', (string) $response->header('Content-Disposition'));
        self::assertSame('no-store', $response->header('Cache-Control'));
        self::assertStringContainsString("\r\n", $response->body);

        $csv = $this->parse($response);
        self::assertSame(['Riferimento', 'Stato', 'Appartamento', 'Arrivo', 'Partenza', 'Notti', 'Adulti', 'Bambini', 'Animali', 'Nome', 'Cognome', 'Email', 'Telefono', 'Note', 'Lingua', 'Totale (EUR)', 'Ricevuta il', 'Decisa il'], $csv['header']);
        self::assertCount(1, $csv['rows']);
        $row = array_combine($csv['header'], $csv['rows'][0]);
        self::assertSame($request['reference'], $row['Riferimento']);
        self::assertSame(['In attesa', 'Margherita', '2027-06-10', '2027-06-15', '5', '2', '1', '1'], [$row['Stato'], $row['Appartamento'], $row['Arrivo'], $row['Partenza'], $row['Notti'], $row['Adulti'], $row['Bambini'], $row['Animali']]);
        self::assertSame("Arrivo tardi;\nporto il \"cane\"", $row['Note'], 'delimiters, newlines and quotes survive a round trip');
        self::assertSame('', $row['Totale (EUR)'], 'no price list: empty total, not an invented one');
        self::assertMatchesRegularExpression('#^\d{2}/\d{2}/\d{4} \d{2}:\d{2}$#', $row['Ricevuta il']);
    }

    public function testBookingsCsvFormat(): void
    {
        $id = $this->service->createManualBooking($this->manualInput('2027-06-10', '2027-06-15', ['origin' => 'novasol', 'guest_name' => 'Famiglia Verdi', 'total_cents' => 12345, 'email' => 'verdi@example.com']));
        $gone = $this->service->createManualBooking($this->manualInput('2027-07-10', '2027-07-12', ['guest_name' => 'Annullato']));
        $this->service->cancelBooking($gone, 'ha cambiato idea');

        $response = $this->admin->get('/admin/export/prenotazioni.csv');

        self::assertMatchesRegularExpression('/filename="prenotazioni-\d{4}-\d{2}-\d{2}\.csv"/', (string) $response->header('Content-Disposition'));
        $csv = $this->parse($response);
        self::assertCount(2, $csv['rows']);
        $byName = [];
        foreach ($csv['rows'] as $r) {
            $row = array_combine($csv['header'], $r);
            $byName[$row['Ospite']] = $row;
        }
        self::assertSame([(string) $id, 'Confermata', 'Novasol', '123,45', 'verdi@example.com'], [$byName['Famiglia Verdi']['N.'], $byName['Famiglia Verdi']['Stato'], $byName['Famiglia Verdi']['Origine'], $byName['Famiglia Verdi']['Totale (EUR)'], $byName['Famiglia Verdi']['Email']]);
        self::assertSame(['Cancellata', 'ha cambiato idea'], [$byName['Annullato']['Stato'], $byName['Annullato']['Motivo cancellazione']]);
    }

    public function testNoCellCanBeInterpretedAsAFormula(): void
    {
        $evil = ['=HYPERLINK("http://evil.example","clicca")', '+39 333 1234567', '-2+3', '@SUM(1+1)', "\t=1+1", "\r=1+1"];
        $this->makeRequest('2027-06-10', '2027-06-12', ['first_name' => $evil[0], 'last_name' => $evil[3], 'phone' => '+39 333 1234567', 'notes' => $evil[2]]);
        $this->service->createManualBooking($this->manualInput('2027-07-10', '2027-07-12', ['guest_name' => $evil[2], 'notes' => $evil[0]]));
        $cancelled = $this->service->createManualBooking($this->manualInput('2027-08-10', '2027-08-12', ['guest_name' => 'x']));
        $this->service->cancelBooking($cancelled, "=cmd|' /C calc'!A0");

        foreach (['/admin/export/richieste.csv', '/admin/export/prenotazioni.csv'] as $url) {
            $csv = $this->parse($this->admin->get($url));
            $cells = 0;
            foreach ($csv['rows'] as $row) {
                foreach ($row as $cell) {
                    $cells++;
                    self::assertDoesNotMatchRegularExpression('/^[=+\-@\t\r]/', $cell, "$url: a cell starts with a formula character: " . json_encode($cell));
                }
            }
            self::assertGreaterThan(10, $cells);
        }

        // The text is still there, only defused by a leading apostrophe.
        $requests = $this->admin->get('/admin/export/richieste.csv')->body;
        self::assertStringContainsString('"\'=HYPERLINK(""http://evil.example"",""clicca"")"', $requests);
        self::assertStringContainsString("'+39 333 1234567", $requests);
        self::assertStringContainsString("'@SUM(1+1)", $requests);
        self::assertStringContainsString("'=cmd|' /C calc'!A0", $this->admin->get('/admin/export/prenotazioni.csv')->body);
    }

    public function testFiltersApplyToTheExport(): void
    {
        $june = $this->makeRequest('2027-06-10', '2027-06-15', ['first_name' => 'Giugno']);
        $july = $this->makeRequest('2027-07-10', '2027-07-15', ['first_name' => 'Luglio', 'apartment_id' => $this->apartmentId('girasole')]);
        $rejected = $this->makeRequest('2027-09-10', '2027-09-15', ['first_name' => 'Rifiutato']);
        $this->service->rejectRequest($rejected['id']);

        $names = fn (string $query): array => array_column($this->parse($this->admin->get('/admin/export/richieste.csv' . $query))['rows'], 9);

        self::assertEqualsCanonicalizing(['Giugno', 'Luglio', 'Rifiutato'], $names(''));
        self::assertSame(['Luglio'], $names('?dal=2027-07-01&al=2027-08-01'));
        self::assertSame(['Rifiutato'], $names('?stato=rejected'));
        self::assertSame(['Luglio'], $names('?appartamento=' . $this->apartmentId('girasole')));
        self::assertEqualsCanonicalizing(['Giugno', 'Rifiutato'], $names('?appartamento=' . $this->apartmentId()));
        self::assertSame([], $names('?dal=2030-01-01&al=2030-02-01'), 'an empty export still has its header');
        self::assertGreaterThan(0, $june['id'] + $july['id']);

        $this->service->createManualBooking($this->manualInput('2027-06-10', '2027-06-15', ['guest_name' => 'Telefono']));
        $this->service->createManualBooking($this->manualInput('2027-07-10', '2027-07-15', ['origin' => 'agency', 'guest_name' => 'Agenzia']));
        $bookings = fn (string $query): array => array_column($this->parse($this->admin->get('/admin/export/prenotazioni.csv' . $query))['rows'], 10);
        self::assertSame(['Agenzia'], $bookings('?origine=agency'));
        self::assertSame(['Telefono'], $bookings('?dal=2027-06-01&al=2027-07-01'));
    }

    public function testExportRequiresLoginAndRefusesBadFilters(): void
    {
        self::assertSame(303, $this->client()->get('/admin/export/richieste.csv')->status);
        self::assertSame(303, $this->client()->get('/admin/export/prenotazioni.csv')->status);
        self::assertSame(400, $this->admin->get('/admin/export/richieste.csv?stato=nope')->status);
        self::assertSame(400, $this->admin->get('/admin/export/prenotazioni.csv?dal=ieri')->status);
        self::assertSame(400, $this->admin->get('/admin/export/prenotazioni.csv?origine=' . rawurlencode("' OR 1=1"))->status);
    }

    public function testAnonymousRequestForTheCsvLeaksNothing(): void
    {
        $this->makeRequest('2027-06-10', '2027-06-12', ['first_name' => 'Riservato']);

        $response = $this->client()->get('/admin/export/richieste.csv');

        self::assertSame(303, $response->status);
        self::assertStringNotContainsString('Riservato', $response->body);
        self::assertNotSame('text/csv; charset=UTF-8', $response->header('Content-Type'));
    }

    public function testExportPageOffersBothDownloads(): void
    {
        $page = $this->admin->get('/admin/export');

        self::assertSame(200, $page->status);
        self::assertStringContainsString('action="/admin/export/richieste.csv"', $page->body);
        self::assertStringContainsString('action="/admin/export/prenotazioni.csv"', $page->body);
        self::assertStringContainsString('method="get"', $page->body);
    }

    public function testExportIsCsvInjectionSafeEvenForTheHeaderOfEmptyData(): void
    {
        $csv = $this->parse($this->admin->get('/admin/export/richieste.csv'));

        self::assertSame([], $csv['rows']);
        self::assertCount(18, $csv['header']);
    }
}
