<?php

declare(strict_types=1);

namespace Tests\Http;

use Tests\Support\HttpClient;
use Tests\Support\HttpResponse;
use Tests\Support\PublicSiteTestCase;

/**
 * The whole life of a stay through the real application: a visitor asks for dates, the admin
 * confirms, the dates disappear from the public offer, the stay appears in the exports and the
 * history, the admin cancels it, and the dates come back. All prices are FICTITIOUS test values.
 */
final class EndToEndTest extends PublicSiteTestCase
{
    private function offers(HttpClient $client, string $in, string $out, string $apartment = 'Margherita'): bool
    {
        $list = $client->get(self::path('request.apartments') . '?' . http_build_query($this->searchParams($in, $out)));
        self::assertSame(200, $list->status);
        return str_contains($list->body, 'Scegli ' . $apartment);
    }

    /** @param array<string, string> $fields */
    private function adminPost(HttpClient $admin, string $path, array $fields = []): HttpResponse
    {
        return $admin->post($path, $fields + ['_csrf' => $this->tokenFor($admin)]);
    }

    private function onlyMargherita(): void
    {
        $this->db->exec("UPDATE apartments SET accepts_online_requests = 0 WHERE slug <> 'margherita'");
    }

    public function testTheWholeJourneyFromVisitorToCancellation(): void
    {
        [$in, $out] = $this->stay();
        $this->equip('margherita', 4, [$in, $out], 10000);
        $this->onlyMargherita();
        $visitor = $this->client();
        $admin = $this->loggedInClient();

        // 1. The visitor sees the apartment, its price, and sends a request.
        self::assertTrue($this->offers($visitor, $in, $out));
        $summary = $this->toSummary($visitor, 'margherita', $in, $out);
        self::assertStringContainsString('400,00', $summary->body);
        $sent = $this->sendFromSummary($visitor, $summary);
        self::assertSame(303, $sent->status);
        $request = $this->row('SELECT * FROM booking_requests');
        self::assertSame('pending', $request['status']);
        self::assertSame(0, $this->countRows('bookings'));
        self::assertSame(1, $this->countRows('email_outbox', "type = 'new_request_admin'"), 'the manager is told');

        // 2. A pending request does not take the dates away from anyone.
        self::assertTrue($this->offers($this->client(), $in, $out), 'only a confirmation occupies dates');

        // 3. The admin sees it and confirms it.
        $list = $admin->get('/admin/richieste?stato=pending');
        self::assertStringContainsString((string) $request['reference'], $list->body);
        $confirm = $this->adminPost($admin, '/admin/richieste/' . $request['id'] . '/conferma');
        self::assertSame(303, $confirm->status);
        $booking = $this->row('SELECT * FROM bookings');
        self::assertSame('confirmed', $booking['status']);
        self::assertSame('website', $booking['origin']);
        self::assertSame(40000, (int) $booking['total_cents']);
        self::assertSame(1, $this->countRows('email_outbox', "type = 'request_confirmed'"), 'the guest is told');

        // 4. The dates have left the public offer, for the exact stay and for any overlap.
        self::assertFalse($this->offers($this->client(), $in, $out));
        $inside = (new \DateTimeImmutable($in))->modify('+1 day')->format('Y-m-d');
        $after = (new \DateTimeImmutable($out))->modify('+2 days')->format('Y-m-d');
        self::assertFalse($this->offers($this->client(), $inside, $after), 'a partial overlap is not offered either');
        self::assertTrue($this->offers($this->client(), $out, $after), 'the day of departure is free for the next guest');
        self::assertSame(0, $this->overlapViolations());

        // 5. The stay shows up in the calendar, the exports and the history.
        self::assertStringContainsString('Mario Rossi', $admin->get('/admin/prenotazioni?stato=confirmed')->body);
        $month = (new \DateTimeImmutable($in))->format('Y-m');
        self::assertStringContainsString('Mario', $admin->get('/admin/calendario?mese=' . $month)->body);
        self::assertStringContainsString((string) $request['reference'], $admin->get('/admin/export/richieste.csv')->body);
        self::assertStringContainsString('Mario Rossi', $admin->get('/admin/export/prenotazioni.csv')->body);
        $history = $admin->get('/admin/storico')->body;
        self::assertStringContainsString('Richiesta pending → confirmed', $history);

        // 6. The admin cancels: the dates return, nothing is sent by itself, a draft is offered.
        $cancel = $this->adminPost($admin, '/admin/prenotazioni/' . $booking['id'] . '/cancella', ['motivo' => 'Il cliente ha rinunciato']);
        self::assertSame(303, $cancel->status);
        self::assertSame('cancelled', $this->scalar('SELECT status FROM bookings WHERE id = ?', [$booking['id']]));
        self::assertTrue($this->offers($this->client(), $in, $out), 'a cancelled stay frees the dates');
        self::assertSame(0, $this->countRows('email_outbox', "type = 'cancellation'"), 'a cancellation e-mail is never sent automatically');
        $draft = $admin->get('/admin/prenotazioni/' . $booking['id'] . '/bozza-cancellazione');
        self::assertSame(200, $draft->status);
        self::assertStringContainsString('<textarea', $draft->body, 'the draft is editable');
        self::assertStringContainsString('Prenotazione confirmed → cancelled', $admin->get('/admin/storico')->body);

        // 7. And the dates can be requested and confirmed again.
        $second = $this->client();
        $again = $this->toSummary($second, 'margherita', $in, $out);
        self::assertSame(303, $this->sendFromSummary($second, $again)->status, 'a second visitor can ask for the freed dates');
        self::assertSame(2, $this->requestCount());
        self::assertSame(0, $this->overlapViolations());
    }

    public function testTwoVisitorsAskForTheSameDatesAndOnlyOneRequestCanBeConfirmed(): void
    {
        [$in, $out] = $this->stay();
        $this->equip('margherita', 4, [$in, $out]);
        $this->onlyMargherita();
        $admin = $this->loggedInClient();

        foreach (['Mario', 'Anna'] as $name) {
            $visitor = $this->client();
            $sent = $this->sendFromSummary($visitor, $this->toSummary($visitor, 'margherita', $in, $out, ['first_name' => $name] + self::CUSTOMER));
            self::assertSame(303, $sent->status, "$name's request is accepted: pending requests do not compete");
        }
        $ids = array_map('intval', $this->db->query('SELECT id FROM booking_requests ORDER BY id')->fetchAll(\PDO::FETCH_COLUMN));
        self::assertCount(2, $ids);

        $first = $this->adminPost($admin, '/admin/richieste/' . $ids[0] . '/conferma');
        self::assertSame(303, $first->status);
        $second = $this->adminPost($admin, '/admin/richieste/' . $ids[1] . '/conferma');
        self::assertSame(303, $second->status);
        $page = $admin->get((string) $second->location())->body;

        self::assertSame(1, $this->countRows('bookings'), 'the second confirmation must not create a booking');
        self::assertSame('pending', $this->scalar('SELECT status FROM booking_requests WHERE id = ?', [$ids[1]]), 'and the request stays open for the admin to decide');
        self::assertStringContainsString('Le date non sono disponibili', $page, 'the admin is told why');
        self::assertSame(0, $this->overlapViolations());

        // The admin can still refuse the second one.
        self::assertSame(303, $this->adminPost($admin, '/admin/richieste/' . $ids[1] . '/rifiuta', ['conferma' => '1'])->status);
        self::assertSame('rejected', $this->scalar('SELECT status FROM booking_requests WHERE id = ?', [$ids[1]]));
        self::assertSame(1, $this->countRows('email_outbox', "type = 'request_rejected'"));
    }

    public function testAVisitorCannotConfirmRejectOrCancelEvenWithAValidPublicToken(): void
    {
        [$in, $out] = $this->stay();
        $this->equip('margherita', 4, [$in, $out]);
        $visitor = $this->client();
        $summary = $this->toSummary($visitor, 'margherita', $in, $out);
        $this->sendFromSummary($visitor, $summary);
        $id = (int) $this->scalar('SELECT id FROM booking_requests');
        $bookingId = $this->book((new \DateTimeImmutable($in))->modify('+20 days')->format('Y-m-d'), (new \DateTimeImmutable($in))->modify('+24 days')->format('Y-m-d'), 'rosa');
        $before = $this->snapshot();
        $publicToken = $this->formToken($summary->body);

        foreach ([
            '/admin/richieste/' . $id . '/conferma',
            '/admin/richieste/' . $id . '/rifiuta',
            '/admin/prenotazioni/' . $bookingId . '/cancella',
            '/admin/blocchi',
            '/admin/listino/tariffe',
        ] as $path) {
            foreach ([['_form' => $publicToken], ['_csrf' => $publicToken], ['_csrf' => 'x', 'motivo' => 'x'], []] as $fields) {
                $response = $visitor->post($path, $fields);

                self::assertSame(401, $response->status, "$path with " . json_encode(array_keys($fields)));
            }
        }
        $this->assertDatabaseUnchanged($before, 'a visitor must not be able to change anything under /admin');
        self::assertSame('pending', $this->scalar('SELECT status FROM booking_requests WHERE id = ?', [$id]));
        self::assertSame(1, $this->countRows('bookings'));
    }
}
