<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Mail\MailMessage;
use App\Mail\MailTransportException;
use App\Mail\NotificationService;
use PDOException;
use Tests\Support\DatabaseTestCase;
use Tests\Support\InMemoryTransport;
use Tests\Support\MailTestHelpers;
use Tests\Support\PricingFixtures as F;

/** What is queued, when, with which content; retry schedule and exclusive claiming. FICTITIOUS prices. */
final class OutboxFlowTest extends DatabaseTestCase
{
    use MailTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setMailEnv();
    }

    protected function tearDown(): void
    {
        $this->restoreMailEnv();
        $this->db->exec('DROP TRIGGER IF EXISTS test_fail_audit');
    }

    private function failAuditFor(string $entityType): void
    {
        $this->db->exec('DROP TRIGGER IF EXISTS test_fail_audit');
        $this->db->exec(
            "CREATE TRIGGER test_fail_audit BEFORE INSERT ON audit_log FOR EACH ROW
             IF NEW.entity_type = '{$entityType}' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'forced failure'; END IF"
        );
    }

    private function loadPriceList(): void
    {
        $config = new \App\Service\PricingConfigService($this->db);
        $config->createRate(F::rateInput($this->apartmentId(), '[TEST] Bassa', '2027-06-01', '2027-07-01', 1000));
    }

    // === What gets queued ========================================================

    public function testANewRequestQueuesOneNotificationForTheManager(): void
    {
        $request = $this->service->createRequest($this->requestInput('2027-06-10', '2027-06-15', ['locale' => 'en']));

        $rows = $this->outboxRows();
        self::assertCount(1, $rows);
        self::assertSame(['new_request_admin', $request['id'], null, 'en', 'pending', 0], [
            $rows[0]['type'], (int) $rows[0]['booking_request_id'], $rows[0]['booking_id'], $rows[0]['locale'], $rows[0]['status'], (int) $rows[0]['attempts'],
        ]);
        self::assertNull($rows[0]['subject']);
        self::assertNull($rows[0]['body']);
    }

    public function testNoAcknowledgementIsQueuedForTheGuestOnSubmission(): void
    {
        $this->service->createRequest($this->requestInput('2027-06-10', '2027-06-15'));

        self::assertSame([], $this->outboxRows("type <> 'new_request_admin'"), 'the SPEC only asks for a message to the manager at this point');
    }

    public function testConfirmationAndRejectionQueueTheCustomerMessage(): void
    {
        $a = $this->service->createRequest($this->requestInput('2027-06-10', '2027-06-15'));
        $b = $this->service->createRequest($this->requestInput('2027-07-10', '2027-07-15', ['locale' => 'en']));

        $booking = $this->service->confirmRequest($a['id']);
        $this->service->rejectRequest($b['id']);

        $confirmed = $this->outboxRows("type = 'request_confirmed'");
        $rejected = $this->outboxRows("type = 'request_rejected'");
        self::assertCount(1, $confirmed);
        self::assertSame([$a['id'], $booking['booking_id'], 'it'], [(int) $confirmed[0]['booking_request_id'], (int) $confirmed[0]['booking_id'], $confirmed[0]['locale']]);
        self::assertCount(1, $rejected);
        self::assertSame([$b['id'], null, 'en'], [(int) $rejected[0]['booking_request_id'], $rejected[0]['booking_id'], $rejected[0]['locale']]);
    }

    public function testCancellationManualBookingsAndBlocksQueueNothing(): void
    {
        $id = $this->book('2027-06-10', '2027-06-15');
        $block = $this->service->createBlock($this->apartmentId('rosa'), '2027-06-10', '2027-06-12');
        $this->service->removeBlock($block);
        $this->service->cancelBooking($id, 'prova');

        self::assertSame([], $this->outboxRows(), 'a cancellation e-mail is only ever a draft that the admin sends explicitly');
    }

    public function testRefusedOperationsQueueNothing(): void
    {
        $a = $this->service->createRequest($this->requestInput('2027-06-10', '2027-06-15'));
        $this->service->confirmRequest($a['id']);
        $before = $this->outboxRows();

        foreach ([
            fn () => $this->service->confirmRequest($a['id']),
            fn () => $this->service->rejectRequest($a['id']),
            fn () => $this->service->createRequest($this->requestInput('2027-06-12', '2027-06-14')),
            fn () => $this->service->createRequest($this->requestInput('2027-06-12', '2027-06-14', ['email' => 'nope'])),
        ] as $attempt) {
            try {
                $attempt();
            } catch (\Throwable) {
                $this->addToAssertionCount(1);
            }
        }

        self::assertSame($before, $this->outboxRows());
    }

    // === Atomicity: the message is queued WITH the change, never without it =========

    public function testAFailureInsideTheTransactionLeavesNoRequestAndNoQueuedMessage(): void
    {
        $this->failAuditFor('booking_request');

        try {
            $this->service->createRequest($this->requestInput('2027-06-10', '2027-06-15'));
            self::fail('Expected the forced failure');
        } catch (PDOException) {
        }

        self::assertSame(0, (int) $this->scalar('SELECT COUNT(*) FROM booking_requests'));
        self::assertSame([], $this->outboxRows());
    }

    public function testAFailedConfirmationLeavesNoBookingAndNoQueuedMessage(): void
    {
        $request = $this->service->createRequest($this->requestInput('2027-06-10', '2027-06-15'));
        $this->db->exec('DELETE FROM email_outbox');
        $this->failAuditFor('booking');

        try {
            $this->service->confirmRequest($request['id']);
            self::fail('Expected the forced failure');
        } catch (PDOException) {
        }

        self::assertSame('pending', $this->scalar('SELECT status FROM booking_requests WHERE id = ?', [$request['id']]));
        self::assertSame(0, (int) $this->scalar('SELECT COUNT(*) FROM bookings'));
        self::assertSame([], $this->outboxRows(), 'no message about a confirmation that did not happen');
    }

    public function testAFailedRejectionQueuesNothing(): void
    {
        $request = $this->service->createRequest($this->requestInput('2027-06-10', '2027-06-15'));
        $this->db->exec('DELETE FROM email_outbox');
        $this->failAuditFor('booking_request');

        try {
            $this->service->rejectRequest($request['id']);
            self::fail('Expected the forced failure');
        } catch (PDOException) {
        }

        self::assertSame('pending', $this->scalar('SELECT status FROM booking_requests WHERE id = ?', [$request['id']]));
        self::assertSame([], $this->outboxRows());
    }

    public function testDeletingARequestRemovesItsQueuedMessages(): void
    {
        $request = $this->service->createRequest($this->requestInput('2027-06-10', '2027-06-15'));

        $this->db->exec('DELETE FROM booking_requests WHERE id = ' . $request['id']);

        self::assertSame([], $this->outboxRows(), 'privacy: erasing a request leaves no queue rows behind');
    }

    // === Content of the messages =================================================

    public function testTheManagerMessageHasEverythingTheSpecAsks(): void
    {
        $request = $this->service->createRequest($this->requestInput('2027-06-10', '2027-06-15', ['adults' => 2, 'children' => 1, 'pets' => 1, 'notes' => "Arrivo tardi\nPorto il cane"]));
        $transport = new InMemoryTransport();

        self::assertSame('sent', $this->notifications($transport)->dispatch((int) $this->outboxRows()[0]['id']));

        self::assertCount(1, $transport->sent);
        $m = $transport->sent[0];
        self::assertSame('gestore@example.test', $m->to);
        self::assertSame('Nuova richiesta di soggiorno ' . $request['reference'], $m->subject);
        self::assertSame('mario.rossi@example.com', $m->replyTo, 'replying answers the guest directly');
        self::assertSame('Mario Rossi', $m->replyToName);
        foreach ([
            'Riferimento: ' . $request['reference'], 'Appartamento: Margherita', 'Arrivo: 10/06/2027', 'Partenza: 15/06/2027 (5 notti)',
            'Ospiti: 2 adulti, 1 bambino, 1 animale', 'Cliente: Mario Rossi', 'Email: mario.rossi@example.com', 'Telefono: +39 333 1234567',
            "Arrivo tardi\n  Porto il cane", 'Prezzo da confermare', 'https://sito.example.test/admin/richieste/' . $request['id'], 'non una prenotazione',
        ] as $expected) {
            self::assertStringContainsString($expected, $m->body);
        }
        self::assertStringNotContainsString('LV-LV-', $m->body);
    }

    public function testTheManagerMessageShowsTheCalculatedPrice(): void
    {
        $this->loadPriceList();
        $this->service->createRequest($this->requestInput('2027-06-10', '2027-06-13'));
        $transport = new InMemoryTransport();

        $this->notifications($transport)->dispatch((int) $this->outboxRows()[0]['id']);

        self::assertStringContainsString('3 notti × € 10,00 = € 30,00', $transport->sent[0]->body);
        self::assertStringContainsString('Totale: € 30,00', $transport->sent[0]->body);
        self::assertStringNotContainsString('Prezzo da confermare', $transport->sent[0]->body);
    }

    public function testTheManagerMessageHasNoLinkWithoutAPublicUrl(): void
    {
        $this->setMailEnv(['APP_URL' => '']);
        $this->service->createRequest($this->requestInput('2027-06-10', '2027-06-15'));
        $transport = new InMemoryTransport();

        $this->notifications($transport)->dispatch((int) $this->outboxRows()[0]['id']);

        self::assertStringNotContainsString('Apri la richiesta', $transport->sent[0]->body);
        self::assertStringNotContainsString('http', $transport->sent[0]->body);
    }

    public function testTheConfirmationToTheGuestInItalian(): void
    {
        $this->loadPriceList();
        $this->db->exec("UPDATE apartments SET check_in_from = '15:00:00', check_in_until = '19:30:00', check_out_until = '10:00:00' WHERE slug = 'margherita'");
        $request = $this->service->createRequest($this->requestInput('2027-06-10', '2027-06-13', ['adults' => 2, 'children' => 1]));
        $this->service->confirmRequest($request['id']);
        $transport = new InMemoryTransport();

        $this->notifications($transport)->dispatch((int) $this->outboxRows("type = 'request_confirmed'")[0]['id']);

        $m = $transport->sent[0];
        self::assertSame('mario.rossi@example.com', $m->to);
        self::assertSame('Prenotazione confermata – Agriturismo La Volta (' . $request['reference'] . ')', $m->subject);
        foreach ([
            'Gentile Mario Rossi', 'siamo lieti di confermare la sua richiesta ' . $request['reference'], 'Appartamento: Margherita',
            'Arrivo: 10/06/2027 (dalle 15:00 alle 19:30)', 'Partenza: 13/06/2027 (entro le 10:00)', 'Ospiti: 2 adulti, 1 bambino', 'Totale: € 30,00', 'Cordiali saluti',
        ] as $expected) {
            self::assertStringContainsString($expected, $m->body);
        }
        self::assertNull($m->replyTo);
        self::assertStringNotContainsString('pagament', strtolower($m->body), 'there is no online payment');
    }

    public function testTheConfirmationInEnglishWithoutConfiguredTimesOrPrice(): void
    {
        $request = $this->service->createRequest($this->requestInput('2027-06-10', '2027-06-15', ['locale' => 'en']));
        $this->service->confirmRequest($request['id']);
        $transport = new InMemoryTransport();

        $this->notifications($transport)->dispatch((int) $this->outboxRows("type = 'request_confirmed'")[0]['id']);

        $m = $transport->sent[0];
        self::assertSame('Booking confirmed – Agriturismo La Volta (' . $request['reference'] . ')', $m->subject);
        self::assertStringContainsString('Dear Mario Rossi', $m->body);
        self::assertStringContainsString('Arrival: 10 June 2027' . "\n", $m->body, 'no time is invented when none is configured');
        self::assertStringContainsString('Departure: 15 June 2027' . "\n", $m->body);
        self::assertStringContainsString('The final price will be communicated to you directly.', $m->body);
        self::assertStringNotContainsString('Totale', $m->body);
        self::assertStringContainsString('Kind regards', $m->body);
    }

    public function testTheRejectionInBothLanguagesInventsNoReason(): void
    {
        $it = $this->service->createRequest($this->requestInput('2027-06-10', '2027-06-15'));
        $en = $this->service->createRequest($this->requestInput('2027-07-10', '2027-07-15', ['locale' => 'en']));
        $this->service->rejectRequest($it['id']);
        $this->service->rejectRequest($en['id']);
        $transport = new InMemoryTransport();
        $notifications = $this->notifications($transport);

        foreach ($this->outboxRows("type = 'request_rejected'") as $row) {
            $notifications->dispatch((int) $row['id']);
        }

        [$italian, $english] = $transport->sent;
        self::assertStringContainsString("Purtroppo non possiamo accettare la richiesta {$it['reference']}", $italian->body);
        self::assertStringContainsString('dal 10/06/2027 al 15/06/2027', $italian->body);
        self::assertSame('La sua richiesta – Agriturismo La Volta (' . $it['reference'] . ')', $italian->subject);
        self::assertStringContainsString("Unfortunately we are not able to accept request {$en['reference']}", $english->body);
        self::assertStringContainsString('from 10 July 2027 to 15 July 2027', $english->body);
        foreach ([$italian, $english] as $m) {
            self::assertDoesNotMatchRegularExpression('/(because|perch[eé]|motivo|reason)/i', $m->body);
        }
    }

    public function testNamesWithLineBreaksCannotInjectHeaders(): void
    {
        $this->service->createRequest($this->requestInput('2027-06-10', '2027-06-15', ['first_name' => "Mario\r\nBcc: spy@example.com", 'last_name' => 'Rossi']));
        $transport = new InMemoryTransport();

        $this->notifications($transport)->dispatch((int) $this->outboxRows()[0]['id']);

        $m = $transport->sent[0];
        self::assertStringNotContainsString("\n", (string) $m->replyToName);
        self::assertStringNotContainsString("\n", $m->subject);
        self::assertStringNotContainsString("\r", $m->to);
    }

    public function testAnEmptyAdminAddressIsAConfigurationErrorNotACrash(): void
    {
        $this->setMailEnv(['MAIL_ADMIN_ADDRESS' => '']);
        $this->service->createRequest($this->requestInput('2027-06-10', '2027-06-15'));
        $transport = new InMemoryTransport();

        self::assertSame('failed', $this->notifications($transport)->dispatch((int) $this->outboxRows()[0]['id']));

        $row = $this->outboxRows()[0];
        self::assertSame(['not_configured', 0], [$row['error_code'], (int) $row['retryable']]);
        self::assertStringContainsString('MAIL_ADMIN_ADDRESS', (string) $row['error_message']);
        self::assertSame(0, $transport->attempts);
    }

    public function testNothingIsSentWhenThereIsNobodyToWriteTo(): void
    {
        $request = $this->service->createRequest($this->requestInput('2027-06-10', '2027-06-15'));
        $this->service->confirmRequest($request['id']);
        $this->db->exec("UPDATE booking_requests SET email = '' WHERE id = " . $request['id']);
        $transport = new InMemoryTransport();

        $outcome = $this->notifications($transport)->dispatch((int) $this->outboxRows("type = 'request_confirmed'")[0]['id']);

        self::assertSame('skipped', $outcome);
        self::assertSame(0, $transport->attempts);
        $row = $this->outboxRows("type = 'request_confirmed'")[0];
        self::assertSame(['skipped', 'no_recipient'], [$row['status'], $row['error_code']]);
    }

    public function testSentRowsKeepNoPersonalDataAndNoMessageText(): void
    {
        $this->service->createRequest($this->requestInput('2027-06-10', '2027-06-15', ['notes' => 'segreto']));
        $this->notifications(new InMemoryTransport())->dispatch((int) $this->outboxRows()[0]['id']);

        $row = $this->outboxRows()[0];
        $dump = json_encode($row);
        self::assertSame('sent', $row['status']);
        foreach (['Mario', 'Rossi', 'mario.rossi@example.com', '333 1234567', 'segreto'] as $personal) {
            self::assertStringNotContainsString($personal, (string) $dump);
        }
        self::assertSame([1, null, null], [(int) $row['attempts'], $row['error_code'], $row['body']]);
        self::assertNotNull($row['sent_at']);
    }

    // === Retry schedule ========================================================

    private function minutesUntilNextAttempt(int $id): ?int
    {
        $value = $this->scalar('SELECT TIMESTAMPDIFF(MINUTE, UTC_TIMESTAMP(), next_attempt_at) FROM email_outbox WHERE id = ?', [$id]);
        return $value === null ? null : (int) $value;
    }

    private function makeDue(int $id): void
    {
        $this->db->exec('UPDATE email_outbox SET next_attempt_at = UTC_TIMESTAMP() - INTERVAL 1 MINUTE WHERE id = ' . $id);
    }

    public function testATemporaryFailureIsRetriedWithGrowingDelaysThenStops(): void
    {
        $this->service->createRequest($this->requestInput('2027-06-10', '2027-06-15'));
        $id = (int) $this->outboxRows()[0]['id'];
        $transport = new InMemoryTransport(static function (MailMessage $m): void {
            throw new MailTransportException(MailTransportException::CONNECTION, 'Connessione non riuscita.', true);
        });
        $notifications = $this->notifications($transport);

        // Attempt 1 fails: retry in 5 minutes.
        self::assertSame('failed', $notifications->dispatch($id));
        self::assertContains($this->minutesUntilNextAttempt($id), [4, 5], 'first retry in about 5 minutes');
        self::assertSame([], $notifications->dispatchDue(), 'not due yet: nothing is retried too early');
        self::assertSame(1, $transport->attempts);

        // Attempt 2 and 3: 30 minutes, then 120 minutes.
        $this->makeDue($id);
        self::assertSame([$id => 'failed'], $notifications->dispatchDue());
        self::assertContains($this->minutesUntilNextAttempt($id), [29, 30]);
        $this->makeDue($id);
        self::assertSame([$id => 'failed'], $notifications->dispatchDue());
        self::assertContains($this->minutesUntilNextAttempt($id), [119, 120]);

        // Attempt 4 is the last automatic one.
        $this->makeDue($id);
        self::assertSame([$id => 'failed'], $notifications->dispatchDue());
        $row = $this->outboxRows()[0];
        self::assertSame([4, null, 'failed'], [(int) $row['attempts'], $row['next_attempt_at'], $row['status']]);
        self::assertSame([], $notifications->dispatchDue(), 'after the maximum number of automatic attempts only a manual retry is possible');
        self::assertSame(4, $transport->attempts);
    }

    public function testAPermanentFailureIsNeverRetriedAutomaticallyButCanBeRetriedByHand(): void
    {
        $this->service->createRequest($this->requestInput('2027-06-10', '2027-06-15'));
        $id = (int) $this->outboxRows()[0]['id'];
        $broken = true;
        $transport = new InMemoryTransport(static function (MailMessage $m) use (&$broken): void {
            if ($broken) {
                throw new MailTransportException(MailTransportException::AUTH_FAILED, 'Autenticazione SMTP rifiutata (codice 535).', false);
            }
        });
        $notifications = $this->notifications($transport);

        self::assertSame('failed', $notifications->dispatch($id));
        $row = $this->outboxRows()[0];
        self::assertSame(['auth_failed', 0, null], [$row['error_code'], (int) $row['retryable'], $row['next_attempt_at']]);
        self::assertSame([], $notifications->dispatchDue(), 'a wrong password is not fixed by trying again');

        $broken = false; // the owner corrected the settings
        self::assertSame('sent', $notifications->retry($id));
        self::assertSame(['sent', null], [$this->outboxRows()[0]['status'], $this->outboxRows()[0]['error_code']]);
        self::assertCount(1, $transport->sent);
    }

    public function testAMessageIsSentOnlyOnce(): void
    {
        $this->service->createRequest($this->requestInput('2027-06-10', '2027-06-15'));
        $id = (int) $this->outboxRows()[0]['id'];
        $transport = new InMemoryTransport();
        $notifications = $this->notifications($transport);

        self::assertSame('sent', $notifications->dispatch($id));
        self::assertSame('busy', $notifications->dispatch($id));
        self::assertSame('busy', $notifications->retry($id), 'a sent message cannot be sent again by a retry');
        self::assertSame([], $notifications->dispatchDue());
        self::assertCount(1, $transport->sent);
    }

    public function testTheClaimIsExclusiveAndAbandonedClaimsExpire(): void
    {
        $this->service->createRequest($this->requestInput('2027-06-10', '2027-06-15'));
        $id = (int) $this->outboxRows()[0]['id'];
        $transport = new InMemoryTransport();
        $notifications = $this->notifications($transport);

        // Another worker claimed it a minute ago and is still busy: hands off.
        $this->db->exec("UPDATE email_outbox SET status = 'sending', locked_at = UTC_TIMESTAMP() - INTERVAL 1 MINUTE, attempts = 1 WHERE id = " . $id);
        self::assertSame('busy', $notifications->dispatch($id));
        self::assertSame([], $notifications->dispatchDue());
        self::assertSame(0, $transport->attempts);

        // That worker died ten minutes ago: the claim has expired and the message goes out.
        $this->db->exec('UPDATE email_outbox SET locked_at = UTC_TIMESTAMP() - INTERVAL 10 MINUTE WHERE id = ' . $id);
        self::assertSame([$id => 'sent'], $notifications->dispatchDue());
        self::assertSame(2, (int) $this->outboxRows()[0]['attempts']);
    }

    public function testSeveralMessagesAreSentInOrderAndTheBatchSizeIsRespected(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->service->createRequest($this->requestInput('2027-06-10', '2027-06-12', ['first_name' => "Cliente{$i}"]));
        }
        $transport = new InMemoryTransport();
        $notifications = $this->notifications($transport);

        $first = $notifications->dispatchDue(3);
        $second = $notifications->dispatchDue(10);

        self::assertCount(3, $first);
        self::assertCount(2, $second);
        self::assertCount(5, $transport->sent);
        $ids = array_map('intval', array_column($this->outboxRows(), 'id'));
        self::assertSame(array_slice($ids, 0, 3), array_keys($first), 'oldest first');
        self::assertSame(array_slice($ids, 3), array_keys($second));
    }

    public function testAnExhaustedTimeBudgetSkipsTheRest(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->service->createRequest($this->requestInput('2027-06-10', '2027-06-12'));
        }
        $transport = new InMemoryTransport();

        $outcomes = $this->notifications($transport)->dispatchMany(array_map('intval', array_column($this->outboxRows(), 'id')), -1.0);

        self::assertSame([], $outcomes);
        self::assertSame(0, $transport->attempts);
        self::assertSame(3, count($this->outboxRows("status = 'pending'")), 'skipped messages stay queued for the next run');
    }

    public function testAnUnknownMessageIdIsHarmless(): void
    {
        self::assertSame('busy', $this->notifications(new InMemoryTransport())->dispatch(999999));
    }

    public function testTheDeferralStrategyIsInlineOutsideFpm(): void
    {
        $transport = new InMemoryTransport();
        $service = $this->bookingServiceWithMail($transport);

        $service->createRequest($this->requestInput('2027-06-10', '2027-06-15'));

        self::assertCount(1, $transport->sent, 'without PHP-FPM the message is sent inline, right after the commit');
        self::assertInstanceOf(NotificationService::class, $this->notifications($transport));
    }
}
