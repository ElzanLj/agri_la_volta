<?php

declare(strict_types=1);

namespace Tests\Http;

use Tests\Support\FakeSmtpServer;
use Tests\Support\HttpClient;
use Tests\Support\HttpResponse;
use Tests\Support\HttpTestCase;
use Tests\Support\TestServer;

/**
 * The admin area with REAL e-mail delivery through the real SmtpTransport to a fake SMTP server:
 * decisions are saved whether or not the mail goes out, failures are visible and retryable,
 * the cancellation e-mail is only ever an explicit, edited draft, and WhatsApp links are valid.
 */
final class AdminMailTest extends HttpTestCase
{
    private const SMTP_PASSWORD = 'Sup3r-Secret-Passw0rd!';

    private static ?FakeSmtpServer $smtp = null;
    private HttpClient $admin;

    public static function setUpBeforeClass(): void
    {
        self::$smtp = FakeSmtpServer::start('ok');
        self::$server = TestServer::start([
            'MAIL_TRANSPORT' => 'smtp',
            'SMTP_HOST' => '127.0.0.1',
            'SMTP_PORT' => (string) self::$smtp->port,
            'SMTP_ENCRYPTION' => 'none',
            'SMTP_USERNAME' => 'smtp-user',
            'SMTP_PASSWORD' => self::SMTP_PASSWORD,
            'SMTP_TIMEOUT' => '2',
            'MAIL_FROM_ADDRESS' => 'info@example.test',
            'MAIL_FROM_NAME' => 'Agriturismo La Volta',
            'MAIL_ADMIN_ADDRESS' => 'gestore@example.test',
            'WHATSAPP_DEFAULT_COUNTRY_CODE' => '39',
        ]);
    }

    public static function tearDownAfterClass(): void
    {
        self::$server?->stop();
        self::$server = null;
        self::$smtp?->stop();
        self::$smtp = null;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->loggedInClient();
        self::$smtp->setScenario('ok');
        foreach (glob(self::$smtp->dir . '/message-*.json') ?: [] as $file) {
            unlink($file);
        }
        @unlink(self::$smtp->dir . '/commands.log');
    }

    private function act(string $path, array $fields = []): HttpResponse
    {
        return $this->admin->post($path, $fields + ['_csrf' => $this->tokenFor($this->admin)]);
    }

    private function follow(HttpResponse $response): HttpResponse
    {
        self::assertContains($response->status, [301, 302, 303], 'expected a redirect, got ' . $response->status);
        return $this->admin->get((string) $response->location());
    }

    /** @return list<array{mail_from: ?string, rcpt_to: list<string>, auth_user: ?string, data: string}> */
    private function received(): array
    {
        return self::$smtp->messages();
    }

    private function bodyOf(string $data): string
    {
        [, $body] = explode("\r\n\r\n", $data, 2) + [1 => ''];
        return str_replace("\r\n", "\n", quoted_printable_decode($body));
    }

    /**
     * A pending request WITHOUT its queued manager notification, so a test only sees the e-mails
     * produced by the admin action it exercises. (testAnEarlierQueuedMessage... covers the rest.)
     *
     * @param array<string, mixed> $overrides
     * @return array{id: int, reference: string}
     */
    private function request(string $in = '2027-06-10', string $out = '2027-06-15', array $overrides = []): array
    {
        $request = $this->makeRequest($in, $out, $overrides);
        $this->db->exec("DELETE FROM email_outbox WHERE type = 'new_request_admin' AND booking_request_id = " . $request['id']);
        return $request;
    }

    // === Decisions are saved whatever happens to the e-mail =============================

    public function testConfirmingSendsTheGuestMessageAndSaysSo(): void
    {
        $request = $this->request('2027-06-10', '2027-06-15');

        $response = $this->act('/admin/richieste/' . $request['id'] . '/conferma');

        self::assertSame(303, $response->status);
        self::assertSame('confirmed', $this->scalar('SELECT status FROM booking_requests WHERE id = ?', [$request['id']]));
        self::assertStringContainsString('Email al cliente inviata.', $this->follow($response)->body);

        $messages = $this->received();
        self::assertCount(1, $messages);
        self::assertSame(['mario.rossi@example.com'], $messages[0]['rcpt_to']);
        self::assertSame('info@example.test', $messages[0]['mail_from']);
        self::assertSame('smtp-user', $messages[0]['auth_user']);
        self::assertStringContainsString('siamo lieti di confermare la sua richiesta ' . $request['reference'], $this->bodyOf($messages[0]['data']));
        self::assertSame('sent', $this->scalar("SELECT status FROM email_outbox WHERE type = 'request_confirmed'"));
    }

    public function testAnSmtpOutageDoesNotStopTheConfirmationAndIsShownToTheAdmin(): void
    {
        self::$smtp->setScenario('greeting_421');
        $request = $this->request('2027-06-10', '2027-06-15');

        $response = $this->act('/admin/richieste/' . $request['id'] . '/conferma');

        // The booking exists and is confirmed.
        self::assertSame(303, $response->status);
        $bookingId = (int) $this->scalar('SELECT id FROM bookings WHERE booking_request_id = ?', [$request['id']]);
        self::assertStringEndsWith('/admin/prenotazioni/' . $bookingId, (string) $response->location());
        self::assertSame(['confirmed', 'confirmed'], [
            $this->scalar('SELECT status FROM booking_requests WHERE id = ?', [$request['id']]),
            $this->scalar('SELECT status FROM bookings WHERE id = ?', [$bookingId]),
        ]);
        self::assertSame(0, $this->overlapViolations());

        // The admin is told, plainly.
        $page = $this->follow($response)->body;
        self::assertStringContainsString('Richiesta confermata: la prenotazione è stata creata.', $page);
        self::assertStringContainsString('Email al cliente NON inviata', $page);
        self::assertStringContainsString('Verrà ritentata automaticamente', $page);
        self::assertStringContainsString('Riprova invio', $page, 'the booking page offers a retry button');

        // The failure is recorded and visible.
        $row = $this->row("SELECT * FROM email_outbox WHERE type = 'request_confirmed'");
        self::assertSame(['failed', 'temporary_rejection', 1], [$row['status'], $row['error_code'], (int) $row['attempts']]);
        $list = $this->admin->get('/admin/email?stato=failed')->body;
        self::assertStringContainsString('Conferma al cliente', $list);
        self::assertStringContainsString('Prossimo tentativo automatico', $list);
        self::assertMatchesRegularExpression('#Email non inviate o in coda</dt>\s*<dd><a[^>]*>1</a>#', $this->admin->get('/admin')->body);

        // Nothing sensitive reached the page, the database or the logs.
        foreach ([$page, $list, json_encode($row), $this->logs()] as $text) {
            self::assertStringNotContainsString(self::SMTP_PASSWORD, (string) $text);
        }
        self::assertSame([], $this->received());
    }

    public function testTheAdminCanRetryOnceTheServerIsBack(): void
    {
        self::$smtp->setScenario('rcpt_550');
        $request = $this->request('2027-06-10', '2027-06-15');
        $this->act('/admin/richieste/' . $request['id'] . '/conferma');
        $rows = $this->db->query("SELECT id, status, error_code, retryable FROM email_outbox WHERE type = 'request_confirmed'")->fetchAll();
        self::assertSame(['failed', 'recipient_rejected', 0], [$rows[0]['status'], $rows[0]['error_code'], (int) $rows[0]['retryable']]);
        $outboxId = (int) $rows[0]['id'];

        // Still broken: the retry fails again and says why, nothing else changes.
        $again = $this->act('/admin/email/' . $outboxId . '/riprova');
        self::assertStringContainsString('Email NON inviata', $this->follow($again)->body);
        self::assertSame(2, (int) $this->scalar('SELECT attempts FROM email_outbox WHERE id = ?', [$outboxId]));

        // The server is fixed: one click delivers it.
        self::$smtp->setScenario('ok');
        $retry = $this->act('/admin/email/' . $outboxId . '/riprova');
        self::assertSame(303, $retry->status);
        self::assertStringContainsString('Email inviata.', $this->follow($retry)->body);
        self::assertSame('sent', $this->scalar('SELECT status FROM email_outbox WHERE id = ?', [$outboxId]));
        self::assertCount(1, $this->received());
        self::assertSame('confirmed', $this->scalar('SELECT status FROM booking_requests WHERE id = ?', [$request['id']]));

        // Clicking again does not send it twice.
        $third = $this->act('/admin/email/' . $outboxId . '/riprova');
        self::assertStringContainsString('già stata inviata', $this->follow($third)->body);
        self::assertCount(1, $this->received());
    }

    public function testAnEarlierQueuedMessageIsDeliveredTogetherWithTheNextDecision(): void
    {
        $request = $this->makeRequest('2027-06-10', '2027-06-15'); // its manager notification is still queued (never sent)
        self::assertSame(['pending'], array_column($this->db->query('SELECT status FROM email_outbox')->fetchAll(), 'status'));

        $this->act('/admin/richieste/' . $request['id'] . '/conferma');

        $recipients = array_merge(...array_column($this->received(), 'rcpt_to'));
        sort($recipients);
        self::assertSame(['gestore@example.test', 'mario.rossi@example.com'], $recipients, 'the older notification goes out with the new message');
        self::assertSame(['sent', 'sent'], array_column($this->db->query('SELECT status FROM email_outbox ORDER BY id')->fetchAll(), 'status'));
    }

    public function testRejectingInEnglishSendsAnEnglishMessage(): void
    {
        $request = $this->request('2027-06-10', '2027-06-15', ['locale' => 'en']);

        $response = $this->act('/admin/richieste/' . $request['id'] . '/rifiuta');

        self::assertSame(303, $response->status);
        self::assertSame('rejected', $this->scalar('SELECT status FROM booking_requests WHERE id = ?', [$request['id']]));
        self::assertStringContainsString('Email al cliente inviata.', $this->follow($response)->body);
        $body = $this->bodyOf($this->received()[0]['data']);
        self::assertStringContainsString('Unfortunately we are not able to accept request ' . $request['reference'], $body);
        self::assertStringContainsString('from 10 June 2027 to 15 June 2027', $body);
    }

    public function testARejectionWithAnSmtpOutageIsStillARejection(): void
    {
        self::$smtp->setScenario('hang');
        $request = $this->request('2027-06-10', '2027-06-15');
        $started = microtime(true);

        $response = $this->act('/admin/richieste/' . $request['id'] . '/rifiuta');

        self::assertSame(303, $response->status);
        self::assertLessThan(8.0, microtime(true) - $started, 'the hanging server cost about the 2 second timeout, not more');
        self::assertSame('rejected', $this->scalar('SELECT status FROM booking_requests WHERE id = ?', [$request['id']]));
        self::assertSame('timeout', $this->scalar("SELECT error_code FROM email_outbox WHERE type = 'request_rejected'"));
        self::assertStringContainsString('Email al cliente NON inviata', $this->follow($response)->body);
    }

    public function testTheEmailPageListsFiltersAndValidates(): void
    {
        self::$smtp->setScenario('greeting_421');
        $a = $this->request('2027-06-10', '2027-06-15');
        $this->act('/admin/richieste/' . $a['id'] . '/conferma');
        self::$smtp->setScenario('ok');
        $b = $this->request('2027-07-10', '2027-07-15');
        $this->act('/admin/richieste/' . $b['id'] . '/rifiuta');

        $all = $this->admin->get('/admin/email');
        self::assertSame(200, $all->status);
        self::assertStringContainsString('Conferma al cliente', $all->body);
        self::assertStringContainsString('Rifiuto al cliente', $all->body);
        self::assertStringContainsString('Non inviata: 1', $all->body);
        self::assertStringContainsString('Inviata: 1', $all->body);
        self::assertStringNotContainsString('MAIL_TRANSPORT=log', $all->body);

        $failed = $this->admin->get('/admin/email?stato=failed')->body;
        self::assertStringContainsString('Conferma al cliente', $failed);
        self::assertStringNotContainsString('Rifiuto al cliente', $failed);

        foreach (['stato=inviata', 'stato=' . rawurlencode("' OR 1=1"), 'pagina=0', 'stato[]=sent'] as $bad) {
            self::assertSame(400, $this->admin->get('/admin/email?' . $bad)->status, $bad);
        }
        self::assertSame(404, $this->act('/admin/email/999999/riprova')->status);
    }

    public function testNoAcknowledgementIsEverSentToTheGuestOnSubmission(): void
    {
        $this->makeRequest('2027-06-10', '2027-06-15'); // the public path, in the test process: no hook, nothing sent

        self::assertSame([], $this->received());
        self::assertSame(['new_request_admin'], array_column($this->db->query('SELECT type FROM email_outbox')->fetchAll(), 'type'));
    }

    // === Cancellation: only an explicit, edited draft ====================================

    private function confirmedBooking(): int
    {
        $request = $this->request('2027-06-10', '2027-06-15');
        $this->act('/admin/richieste/' . $request['id'] . '/conferma');
        return (int) $this->scalar('SELECT id FROM bookings WHERE booking_request_id = ?', [$request['id']]);
    }

    public function testCancellingNeverSendsAnythingByItself(): void
    {
        $booking = $this->confirmedBooking();
        $sentBefore = count($this->received());
        $outboxBefore = (int) $this->scalar('SELECT COUNT(*) FROM email_outbox');

        $response = $this->act('/admin/prenotazioni/' . $booking . '/cancella', ['motivo' => 'prova']);

        self::assertSame(303, $response->status);
        self::assertSame('cancelled', $this->scalar('SELECT status FROM bookings WHERE id = ?', [$booking]));
        self::assertCount($sentBefore, $this->received(), 'no e-mail leaves when a booking is cancelled');
        self::assertSame($outboxBefore, (int) $this->scalar('SELECT COUNT(*) FROM email_outbox'), 'nothing is even queued');
        $page = $this->follow($response)->body;
        self::assertStringContainsString('Nessuna email è stata inviata', $page);
        self::assertStringContainsString('Prepara l\'email di cancellazione', $page);
    }

    public function testTheDraftIsEditableAndNotSentUntilTheAdminSendsTheEditedText(): void
    {
        $booking = $this->confirmedBooking();
        $this->act('/admin/prenotazioni/' . $booking . '/cancella');
        $sentBefore = count($this->received());

        $form = $this->admin->get('/admin/prenotazioni/' . $booking . '/bozza-cancellazione');
        self::assertSame(200, $form->status);
        self::assertStringContainsString('non viene inviata automaticamente', $form->body);
        self::assertStringContainsString('Cancellazione della prenotazione – Agriturismo La Volta', $form->body);
        self::assertStringContainsString('Gentile Mario Rossi', $form->body);
        self::assertStringContainsString('[Scrivi qui il motivo e le eventuali indicazioni]', $form->body);
        self::assertStringContainsString('<textarea', $form->body);
        self::assertStringContainsString('href="mailto:mario.rossi%40example.com?subject=', $form->body);
        self::assertStringContainsString('lingua=en', $form->body);
        self::assertCount($sentBefore, $this->received(), 'opening the draft sends nothing');

        // Sending the draft untouched is refused: it still contains the placeholder.
        $unedited = $this->act('/admin/prenotazioni/' . $booking . '/bozza-cancellazione', [
            'lingua' => 'it', 'subject' => 'Cancellazione', 'body' => "Gentile Mario,\n\n[Scrivi qui il motivo e le eventuali indicazioni]\n",
        ]);
        self::assertSame(422, $unedited->status);
        self::assertStringContainsString('Sostituisci o rimuovi il testo tra parentesi quadre', $unedited->body);
        self::assertStringContainsString('aria-describedby="err-body"', $unedited->body);
        self::assertCount($sentBefore, $this->received());

        // Sending the edited text delivers exactly that text.
        $edited = "Gentile Mario,\n\nci dispiace: la struttura resta chiusa per un imprevisto.\nÈ possibile spostare il soggiorno.\n\nCordiali saluti\nAgriturismo La Volta";
        $sent = $this->act('/admin/prenotazioni/' . $booking . '/bozza-cancellazione', ['lingua' => 'it', 'subject' => 'Cancellazione prenotazione', 'body' => $edited]);

        self::assertSame(303, $sent->status);
        self::assertStringEndsWith('/admin/prenotazioni/' . $booking, (string) $sent->location());
        self::assertStringContainsString('Email di cancellazione inviata.', $this->follow($sent)->body);
        $messages = $this->received();
        self::assertCount($sentBefore + 1, $messages);
        $last = end($messages);
        self::assertSame(['mario.rossi@example.com'], $last['rcpt_to']);
        self::assertStringContainsString('ci dispiace: la struttura resta chiusa per un imprevisto.', $this->bodyOf($last['data']));
        self::assertStringContainsString('È possibile spostare il soggiorno.', $this->bodyOf($last['data']));
        self::assertStringNotContainsString('[Scrivi qui', $this->bodyOf($last['data']));

        $row = $this->row("SELECT * FROM email_outbox WHERE type = 'cancellation'");
        self::assertSame(['sent', null, null], [$row['status'], $row['body'], $row['subject']], 'the text is cleared once sent');
        self::assertSame(1, (int) $this->scalar("SELECT COUNT(*) FROM audit_log WHERE entity_type = 'booking' AND action = 'email_queued'"));
    }

    public function testAFailedCancellationEmailKeepsTheTextForARetry(): void
    {
        $booking = $this->confirmedBooking();
        $this->act('/admin/prenotazioni/' . $booking . '/cancella');
        self::$smtp->setScenario('greeting_421');

        $sent = $this->act('/admin/prenotazioni/' . $booking . '/bozza-cancellazione', ['lingua' => 'it', 'subject' => 'Cancellazione', 'body' => 'Testo definitivo della mail.']);

        self::assertSame(303, $sent->status);
        self::assertStringContainsString('Email NON inviata', $this->follow($sent)->body);
        $row = $this->row("SELECT * FROM email_outbox WHERE type = 'cancellation'");
        self::assertSame(['failed', 'Testo definitivo della mail.'], [$row['status'], $row['body']]);
        self::assertSame('cancelled', $this->scalar('SELECT status FROM bookings WHERE id = ?', [$booking]));

        self::$smtp->setScenario('ok');
        $this->act('/admin/email/' . $row['id'] . '/riprova');
        self::assertSame('sent', $this->scalar('SELECT status FROM email_outbox WHERE id = ?', [$row['id']]));
        $messages = $this->received();
        self::assertStringContainsString('Testo definitivo della mail.', $this->bodyOf(end($messages)['data']));
    }

    public function testTheDraftIsOnlyForCancelledBookingsAndValidatesTheText(): void
    {
        $booking = $this->confirmedBooking();

        $early = $this->admin->get('/admin/prenotazioni/' . $booking . '/bozza-cancellazione');
        self::assertSame(303, $early->status);
        self::assertStringContainsString('disponibile solo dopo aver cancellato', $this->follow($early)->body);
        self::assertSame(303, $this->act('/admin/prenotazioni/' . $booking . '/bozza-cancellazione', ['subject' => 'x', 'body' => 'y'])->status);

        $this->act('/admin/prenotazioni/' . $booking . '/cancella');
        $before = count($this->received());
        foreach ([['', 'testo'], ['oggetto', ''], [str_repeat('x', 201), 'testo'], ['oggetto', str_repeat('y', 10001)]] as [$subject, $body]) {
            self::assertSame(422, $this->act('/admin/prenotazioni/' . $booking . '/bozza-cancellazione', ['lingua' => 'it', 'subject' => $subject, 'body' => $body])->status);
        }
        self::assertCount($before, $this->received());
        self::assertSame(404, $this->admin->get('/admin/prenotazioni/999999/bozza-cancellazione')->status);
        self::assertSame(404, $this->act('/admin/prenotazioni/999999/bozza-cancellazione', ['subject' => 'x', 'body' => 'y'])->status);
    }

    public function testABookingWithoutAnEmailAddressCannotBeSentTheDraft(): void
    {
        $id = $this->service->createManualBooking($this->manualInput('2027-06-10', '2027-06-15', ['guest_name' => 'Cliente Telefono', 'phone' => '333 1234567']));
        $this->act('/admin/prenotazioni/' . $id . '/cancella');

        $form = $this->admin->get('/admin/prenotazioni/' . $id . '/bozza-cancellazione');
        self::assertStringContainsString('nessun indirizzo email', $form->body);
        self::assertStringNotContainsString('Invia email al cliente', $form->body);
        self::assertStringNotContainsString('mailto:', $form->body);

        $post = $this->act('/admin/prenotazioni/' . $id . '/bozza-cancellazione', ['lingua' => 'it', 'subject' => 'Cancellazione', 'body' => 'Testo']);
        self::assertSame(422, $post->status);
        self::assertStringContainsString('non ha un indirizzo email', $post->body);
        self::assertSame(0, (int) $this->scalar("SELECT COUNT(*) FROM email_outbox WHERE type = 'cancellation'"));
    }

    public function testTheDraftEscapesGuestSuppliedText(): void
    {
        $id = $this->service->createManualBooking($this->manualInput('2027-06-10', '2027-06-15', ['guest_name' => '<script>alert(1)</script>', 'email' => 'x@example.com']));
        $this->act('/admin/prenotazioni/' . $id . '/cancella');

        $body = $this->admin->get('/admin/prenotazioni/' . $id . '/bozza-cancellazione')->body;

        self::assertStringNotContainsString('<script>alert(1)</script>', $body);
        self::assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $body);
    }

    public function testTheDraftCanBeCreatedInEnglish(): void
    {
        $booking = $this->confirmedBooking();
        $this->act('/admin/prenotazioni/' . $booking . '/cancella');

        $page = $this->admin->get('/admin/prenotazioni/' . $booking . '/bozza-cancellazione?lingua=en')->body;

        self::assertStringContainsString('Cancellation of your booking', $page);
        self::assertStringContainsString('from 10 June 2027 to 15 June 2027', $page);
        self::assertStringContainsString('[Write here the reason and any instructions]', $page);
    }

    // === WhatsApp ===============================================================

    private function whatsappHref(string $html): ?string
    {
        return preg_match('#href="(https://wa\.me/[^"]+)"#', $html, $m) === 1 ? html_entity_decode($m[1]) : null;
    }

    public function testTheRequestPageHasAValidWhatsAppLinkWithAnEditableMessage(): void
    {
        $request = $this->request('2027-06-10', '2027-06-15', ['phone' => '+39 333 123 4567', 'adults' => 2, 'children' => 1]);

        $page = $this->admin->get('/admin/richieste/' . $request['id'])->body;

        $href = $this->whatsappHref($page);
        self::assertNotNull($href);
        self::assertMatchesRegularExpression('#^https://wa\.me/393331234567\?text=[A-Za-z0-9\-_.~%]+$#', $href);
        parse_str((string) parse_url($href, PHP_URL_QUERY), $query);
        self::assertSame(
            "Buongiorno Mario, la contatto in merito alla sua richiesta {$request['reference']} per l'appartamento Margherita dal 10/06 al 15/06 per 2 adulti e 1 bambino.",
            $query['text'],
        );
        self::assertStringContainsString('target="_blank" rel="noopener noreferrer"', $page);
        self::assertStringContainsString('modificabile prima dell\'invio', $page);
    }

    public function testNationalAndInternationalNumbersBothWork(): void
    {
        foreach (['333 1234567' => '393331234567', '0039 333 1234567' => '393331234567', '+44 7700 900123' => '447700900123', '0524 587057' => '390524587057'] as $typed => $digits) {
            $request = $this->request('2027-06-10', '2027-06-12', ['phone' => $typed]);
            self::assertStringStartsWith('https://wa.me/' . $digits . '?text=', (string) $this->whatsappHref($this->admin->get('/admin/richieste/' . $request['id'])->body), $typed);
        }
    }

    public function testNoLinkIsOfferedForAnUnusableNumber(): void
    {
        foreach (['12345', '+39 333 1234567 int. 5', '+39 333 123 <script>'] as $typed) {
            $request = $this->request('2027-06-10', '2027-06-12');
            $this->db->prepare('UPDATE booking_requests SET phone = ? WHERE id = ?')->execute([$typed, $request['id']]); // e.g. data from before the validation existed
            $page = $this->admin->get('/admin/richieste/' . $request['id'])->body;

            self::assertNull($this->whatsappHref($page), $typed);
            self::assertStringContainsString('numero non utilizzabile per WhatsApp', $page);
            self::assertStringNotContainsString('<script>', $page);
        }
    }

    public function testTheBookingPageHasAWhatsAppLinkForManualBookingsToo(): void
    {
        $withPhone = $this->service->createManualBooking($this->manualInput('2027-06-10', '2027-06-15', ['guest_name' => 'Famiglia Bianchi', 'phone' => '333 1234567', 'adults' => 3, 'children' => 0]));
        $noPhone = $this->service->createManualBooking($this->manualInput('2027-07-10', '2027-07-15', ['guest_name' => 'Senza Telefono']));

        $href = $this->whatsappHref($this->admin->get('/admin/prenotazioni/' . $withPhone)->body);

        self::assertNotNull($href);
        self::assertStringStartsWith('https://wa.me/393331234567?text=', $href);
        parse_str((string) parse_url($href, PHP_URL_QUERY), $query);
        self::assertSame("Buongiorno Famiglia, la contatto in merito alla sua richiesta per l'appartamento Margherita dal 10/06 al 15/06 per 3 adulti.", $query['text']);
        self::assertNull($this->whatsappHref($this->admin->get('/admin/prenotazioni/' . $noPhone)->body));
    }

    // === Operations ===============================================================

    public function testTheQueueScriptSendsWhatIsDue(): void
    {
        self::$smtp->setScenario('greeting_421');
        $request = $this->request('2027-06-10', '2027-06-15');
        $this->act('/admin/richieste/' . $request['id'] . '/conferma');
        self::$smtp->setScenario('ok');
        $this->db->exec("UPDATE email_outbox SET next_attempt_at = UTC_TIMESTAMP() - INTERVAL 1 MINUTE WHERE status = 'failed'");

        $output = $this->runQueueScript();

        self::assertMatchesRegularExpression('/Email elaborate: \d+ \(sent: \d+\)/', $output);
        self::assertSame(['sent'], array_values(array_unique(array_column($this->db->query("SELECT status FROM email_outbox WHERE type = 'request_confirmed'")->fetchAll(), 'status'))));
        self::assertContains('mario.rossi@example.com', array_merge(...array_column($this->received(), 'rcpt_to')));
        self::assertStringContainsString('Nessuna email da inviare.', $this->runQueueScript(), 'a second run finds nothing');
    }

    private function runQueueScript(): string
    {
        $root = dirname(__DIR__, 2);
        $process = proc_open(
            [PHP_BINARY, $root . '/bin/send-queued-mail.php', '--limit=10'],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            $root,
            array_merge(getenv(), [
                'APP_ENV' => 'development', 'DB_NAME' => 'agriturismo_test', 'MAIL_TRANSPORT' => 'smtp', 'SMTP_HOST' => '127.0.0.1',
                'SMTP_PORT' => (string) self::$smtp->port, 'SMTP_ENCRYPTION' => 'none', 'SMTP_TIMEOUT' => '2',
                'MAIL_FROM_ADDRESS' => 'info@example.test', 'MAIL_ADMIN_ADDRESS' => 'gestore@example.test',
            ]),
        );
        $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
        proc_close($process);
        return $output;
    }

    private function logs(): string
    {
        $text = '';
        foreach (glob(dirname(__DIR__, 2) . '/storage/logs/app-*.log') ?: [] as $file) {
            $text .= (string) file_get_contents($file);
        }
        return $text;
    }

    public function testTheMailPagesAreProtectedLikeEveryOtherAdminPage(): void
    {
        $request = $this->makeRequest();
        $this->act('/admin/richieste/' . $request['id'] . '/conferma');
        $outboxId = (int) $this->scalar('SELECT id FROM email_outbox LIMIT 1');
        $anonymous = $this->client();

        self::assertSame(303, $anonymous->get('/admin/email')->status);
        self::assertSame(401, $anonymous->post('/admin/email/' . $outboxId . '/riprova')->status);
        self::assertSame(403, $this->admin->post('/admin/email/' . $outboxId . '/riprova')->status, 'no token');
        self::assertSame(403, $this->admin->post('/admin/prenotazioni/1/bozza-cancellazione', ['subject' => 'x', 'body' => 'y'])->status, 'no token');
        self::assertSame(405, $this->admin->get('/admin/email/' . $outboxId . '/riprova')->status);
    }
}
