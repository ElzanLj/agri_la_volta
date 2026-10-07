<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Service\PersonalDataService;
use App\Support\AuditCleaner;
use Tests\Support\DatabaseTestCase;

/**
 * Review finding A1: free text must not survive the anonymisation through the history (Storico).
 * The check reads EVERY text column of EVERY table, so a new place that copies the text is caught too.
 */
final class AuditPrivacyTest extends DatabaseTestCase
{
    private const EMAIL = 'famiglia.bianchi@example.test';
    private const NAME = 'Famiglia Bianchi';

    /** @return list<string> "table.column" of every text column that contains $needle */
    private function whereTheTextIs(string $needle): array
    {
        $columns = $this->db->query(
            "SELECT table_name AS t, column_name AS c FROM information_schema.columns
              WHERE table_schema = DATABASE() AND data_type IN ('char', 'varchar', 'tinytext', 'text', 'mediumtext', 'longtext', 'json')
              ORDER BY table_name, column_name"
        )->fetchAll();

        $found = [];
        foreach ($columns as $column) {
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM `{$column['t']}` WHERE `{$column['c']}` LIKE ?");
            $stmt->execute(['%' . $needle . '%']);
            if ((int) $stmt->fetchColumn() > 0) {
                $found[] = $column['t'] . '.' . $column['c'];
            }
        }
        return $found;
    }

    private function confirmedStayOfTheGuest(): int
    {
        $request = $this->service->createRequest($this->requestInput('2027-06-10', '2027-06-15', ['email' => self::EMAIL, 'first_name' => 'Giulia', 'last_name' => 'Bianchi']));
        return $this->service->confirmRequest($request['id'])['booking_id'];
    }

    public function testACancellationReasonWithANameIsNotCopiedIntoTheHistory(): void
    {
        $booking = $this->confirmedStayOfTheGuest();

        $this->service->cancelBooking($booking, self::NAME . ' ha avuto un problema di salute');

        $history = (string) $this->scalar("SELECT new_values FROM audit_log WHERE entity_type = 'booking' AND action = 'status_changed' ORDER BY id DESC LIMIT 1");
        self::assertStringNotContainsString('Bianchi', $history);
        self::assertSame(['status' => 'cancelled', 'reason_present' => true], json_decode($history, true));
        $places = $this->whereTheTextIs('Bianchi');
        self::assertContains('bookings.cancellation_reason', $places, 'the reason lives on the booking, where the anonymisation reaches it');
        self::assertSame([], array_values(array_filter($places, static fn (string $p): bool => str_starts_with($p, 'audit_log.'))), 'and not in the history');
    }

    public function testNoReasonIsRecordedAsAbsent(): void
    {
        $booking = $this->confirmedStayOfTheGuest();

        $this->service->cancelBooking($booking, '   ');

        $history = json_decode((string) $this->scalar("SELECT new_values FROM audit_log WHERE entity_type = 'booking' AND action = 'status_changed' ORDER BY id DESC LIMIT 1"), true);
        self::assertFalse($history['reason_present']);
    }

    public function testAfterErasingTheGuestNoTraceOfTheNameRemainsAnywhereInTheDatabase(): void
    {
        $booking = $this->confirmedStayOfTheGuest();
        $this->service->cancelBooking($booking, self::NAME . ' ha avuto un problema di salute');
        self::assertNotSame([], $this->whereTheTextIs('Bianchi'), 'before the erasure the data is there');

        (new PersonalDataService($this->db, '2030-01-01'))->erase(self::EMAIL, true);

        self::assertSame([], $this->whereTheTextIs('Bianchi'), 'last name, e-mail and reason are gone from every table');
        self::assertSame([], $this->whereTheTextIs('Giulia'));
        self::assertSame([], $this->whereTheTextIs('famiglia.bianchi'));
        self::assertSame([], $this->whereTheTextIs('problema di salute'));
    }

    public function testABlockReasonIsNotCopiedIntoTheHistoryEitherWhenCreatedOrRemoved(): void
    {
        $block = $this->service->createBlock($this->apartmentId(), '2027-09-01', '2027-09-05', 'Ospiti amici ' . self::NAME);
        $this->service->removeBlock($block);

        $rows = $this->db->query("SELECT old_values, new_values FROM audit_log WHERE entity_type = 'availability_block' ORDER BY id")->fetchAll();
        self::assertCount(2, $rows);
        foreach ($rows as $row) {
            self::assertStringNotContainsString('Bianchi', (string) $row['old_values'] . (string) $row['new_values']);
        }
        self::assertTrue(json_decode((string) $rows[0]['new_values'], true)['reason_present']);
        self::assertTrue(json_decode((string) $rows[1]['old_values'], true)['reason_present']);
        self::assertSame([], $this->whereTheTextIs('Bianchi'), 'after the block is removed the name is nowhere');
    }

    public function testTheSubmissionKeyDerivedFromTheGuestDataIsClearedWithTheGuest(): void
    {
        $key = hash('sha256', 'form|' . self::EMAIL);
        $this->service->confirmRequest($this->service->createRequest($this->requestInput('2027-06-10', '2027-06-15', ['email' => self::EMAIL, 'submission_key' => $key]))['id']);
        self::assertSame($key, $this->scalar('SELECT submission_key FROM booking_requests'));

        (new PersonalDataService($this->db, '2030-01-01'))->erase(self::EMAIL, true);

        self::assertNull($this->scalar('SELECT submission_key FROM booking_requests'));
    }

    // === Cleaning the history written by older versions ==========================

    private function oldHistoryRow(string $entity, string $action, ?string $old, ?string $new): void
    {
        $this->db->prepare('INSERT INTO audit_log (entity_type, entity_id, action, old_values, new_values) VALUES (?, 1, ?, ?, ?)')->execute([$entity, $action, $old, $new]);
    }

    public function testTheCleanerRemovesOldFreeTextReasonsAndIsSafeToRepeat(): void
    {
        $this->oldHistoryRow('booking', 'status_changed', '{"status":"confirmed"}', '{"status":"cancelled","reason":"Famiglia Bianchi: motivi di salute"}');
        $this->oldHistoryRow('availability_block', 'created', null, '{"apartment_id":1,"reason":"Ospiti Bianchi"}');
        $this->oldHistoryRow('availability_block', 'deleted', '{"apartment_id":1,"reason":null}', null);
        $this->oldHistoryRow('availability_block', 'created', null, '{"apartment_id":1,"reason":""}');
        $untouched = '{"status":"cancelled","reason_present":true}';
        $this->oldHistoryRow('booking', 'status_changed', null, $untouched);
        $this->oldHistoryRow('apartment', 'updated', null, '{"reason":"un campo che si chiama così in un altro tipo di voce"}');
        $cleaner = new AuditCleaner($this->db);

        self::assertSame(4, $cleaner->clean(false), 'dry run counts the rows');
        self::assertNotSame([], $this->whereTheTextIs('Bianchi'), 'and changes nothing');

        self::assertSame(4, $cleaner->clean(true));
        self::assertSame([], $this->whereTheTextIs('Bianchi'));
        self::assertSame(0, $cleaner->clean(true), 'running it again finds nothing');

        $rows = $this->db->query('SELECT entity_type, action, old_values, new_values FROM audit_log ORDER BY id')->fetchAll();
        self::assertSame(['status' => 'cancelled', 'reason_present' => true], json_decode($rows[0]['new_values'], true));
        self::assertSame(['apartment_id' => 1, 'reason_present' => true], json_decode($rows[1]['new_values'], true));
        self::assertSame(['apartment_id' => 1, 'reason_present' => false], json_decode($rows[2]['old_values'], true));
        self::assertSame(['apartment_id' => 1, 'reason_present' => false], json_decode($rows[3]['new_values'], true), 'an empty reason is "not present"');
        self::assertSame($untouched, $rows[4]['new_values']);
        self::assertStringContainsString('un campo che si chiama così', (string) $rows[5]['new_values'], 'other kinds of history rows are never touched');
    }

    public function testTheCleanerLeavesDamagedHistoryRowsAlone(): void
    {
        $this->oldHistoryRow('booking', 'status_changed', 'not json "reason"', '{"reason": broken');

        self::assertSame(0, (new AuditCleaner($this->db))->clean(true));
        self::assertSame('not json "reason"', $this->scalar('SELECT old_values FROM audit_log'));
    }

    public function testTheCommandSimulatesByDefault(): void
    {
        $this->oldHistoryRow('booking', 'status_changed', null, '{"reason":"Famiglia Bianchi"}');
        putenv('DB_NAME=' . (getenv('TEST_DB_NAME') ?: 'agriturismo_test'));
        $run = static function (string $extra): string {
            exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(dirname(__DIR__, 2) . '/bin/privacy.php') . ' audit-clean ' . $extra . ' 2>&1', $output);
            return implode("\n", $output);
        };

        self::assertStringContainsString('SIMULAZIONE', $run(''));
        self::assertStringContainsString('1', $run(''));
        self::assertNotSame([], $this->whereTheTextIs('Bianchi'));

        self::assertStringContainsString('ESEGUITO', $run('--apply'));
        self::assertSame([], $this->whereTheTextIs('Bianchi'));
    }
}
