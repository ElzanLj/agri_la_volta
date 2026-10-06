<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Service\PersonalDataService;
use Tests\Support\DatabaseTestCase;

/** Export, anonymisation and retention of personal data (SPEC §32). */
final class PersonalDataTest extends DatabaseTestCase
{
    private const EMAIL = 'mario.rossi@example.test';

    private function service(string $today = '2030-01-01'): PersonalDataService
    {
        return new PersonalDataService($this->db, $today);
    }

    /** A confirmed stay from a website request, by someone. @return array{request: int, booking: int} */
    private function confirmedStay(string $in, string $out, string $email = self::EMAIL, string $first = 'Mario', string $slug = 'margherita'): array
    {
        $request = $this->service->createRequest($this->requestInput($in, $out, [
            'apartment_id' => $this->apartmentId($slug), 'email' => $email, 'first_name' => $first, 'last_name' => 'Rossi',
            'phone' => '+39 333 1234567', 'notes' => 'Allergia alle noci',
        ]));
        $booking = $this->service->confirmRequest($request['id'])['booking_id'];
        return ['request' => $request['id'], 'booking' => $booking];
    }

    public function testExportFindsEverythingAboutThePersonAndNothingAboutOthers(): void
    {
        $mine = $this->confirmedStay('2027-06-10', '2027-06-14');
        $this->confirmedStay('2027-07-10', '2027-07-14', 'altra.persona@example.test', 'Anna');
        $this->service->createManualBooking($this->manualInput('2027-08-01', '2027-08-05', ['email' => strtoupper(self::EMAIL), 'guest_name' => 'M. Rossi']));
        $this->db->prepare("INSERT INTO email_outbox (type, booking_id, subject, body) VALUES ('cancellation', ?, 'Oggetto', 'Testo con dati')")->execute([$mine['booking']]);

        $export = $this->service()->export('Mario.Rossi@Example.test');

        self::assertCount(1, $export['requests']);
        self::assertSame('Allergia alle noci', $export['requests'][0]['notes']);
        self::assertSame('+39 333 1234567', $export['requests'][0]['phone']);
        self::assertCount(2, $export['bookings'], 'the booking from the request and the manual one with the same address');
        self::assertCount(3, $export['emails'], 'the queued notifications about the request and the written cancellation');
        $withText = array_values(array_filter($export['emails'], static fn (array $m): bool => $m['body'] !== null));
        self::assertCount(1, $withText);
        self::assertSame('Testo con dati', $withText[0]['body']);
        self::assertStringNotContainsString('altra.persona', json_encode($export, JSON_THROW_ON_ERROR));
        self::assertStringNotContainsString('Anna', json_encode($export, JSON_THROW_ON_ERROR));
    }

    public function testExportOfAnUnknownPersonIsEmpty(): void
    {
        self::assertSame(['requests' => [], 'bookings' => [], 'emails' => []], $this->service()->export('nessuno@example.test'));
    }

    public function testEraseIsADryRunUnlessAppliedAndChangesNothing(): void
    {
        $this->confirmedStay('2027-06-10', '2027-06-14');
        $before = $this->row('SELECT first_name, email, phone, notes FROM booking_requests');

        $result = $this->service()->erase(self::EMAIL, false);

        self::assertSame(1, $result['requests']);
        self::assertSame(1, $result['bookings']);
        self::assertSame($before, $this->row('SELECT first_name, email, phone, notes FROM booking_requests'));
        self::assertSame(0, (int) $this->scalar("SELECT COUNT(*) FROM audit_log WHERE " . "action = 'anonymized'"));
    }

    public function testEraseAnonymisesThePersonButKeepsTheStayOccupied(): void
    {
        $mine = $this->confirmedStay('2027-06-10', '2027-06-14');
        $other = $this->confirmedStay('2027-07-10', '2027-07-14', 'altra.persona@example.test', 'Anna', 'girasole');
        $this->db->prepare("INSERT INTO email_outbox (type, booking_id, subject, body) VALUES ('cancellation', ?, 'Oggetto', 'Testo con dati')")->execute([$mine['booking']]);
        $this->db->prepare('UPDATE bookings SET notes = ?, cancellation_reason = ? WHERE id = ?')->execute(['nota', 'motivo con nome', $mine['booking']]);

        $result = $this->service()->erase(self::EMAIL, true);

        self::assertSame(['requests' => 1, 'bookings' => 1, 'emails' => 1, 'skipped' => []], $result);
        $request = $this->row('SELECT * FROM booking_requests WHERE id = ?', [$mine['request']]);
        self::assertSame('[eliminato]', $request['first_name']);
        self::assertSame('[eliminato]', $request['last_name']);
        self::assertSame('eliminato-' . $mine['request'] . '@invalid.invalid', $request['email']);
        self::assertSame('-', $request['phone']);
        self::assertNull($request['notes']);
        $booking = $this->row('SELECT * FROM bookings WHERE id = ?', [$mine['booking']]);
        self::assertSame('[eliminato]', $booking['guest_name']);
        self::assertNull($booking['email']);
        self::assertNull($booking['phone']);
        self::assertNull($booking['notes']);
        self::assertNull($booking['cancellation_reason']);
        $mail = $this->row('SELECT subject, body FROM email_outbox WHERE booking_id = ?', [$mine['booking']]);
        self::assertNull($mail['subject']);
        self::assertNull($mail['body']);

        // The stay itself is untouched: still confirmed, same apartment and dates, still occupied.
        self::assertSame(['confirmed', '2027-06-10', '2027-06-14', $this->apartmentId('margherita')], [$booking['status'], $booking['check_in'], $booking['check_out'], (int) $booking['apartment_id']]);
        try {
            $this->service->createRequest($this->requestInput('2027-06-11', '2027-06-12'));
            self::fail('the anonymised stay must still occupy its dates');
        } catch (\App\Domain\ConflictException) {
            self::assertSame(1, (int) $this->scalar("SELECT COUNT(*) FROM bookings WHERE " . "status = 'confirmed' AND apartment_id = " . $this->apartmentId('margherita')));
        }

        // Somebody else is untouched.
        self::assertSame('Anna', $this->row('SELECT first_name FROM booking_requests WHERE id = ?', [$other['request']])['first_name']);
        self::assertSame('altra.persona@example.test', $this->row('SELECT email FROM booking_requests WHERE id = ?', [$other['request']])['email']);

        // The audit trail says it happened, not who.
        $audit = $this->row("SELECT * FROM audit_log WHERE action = 'anonymized'");
        self::assertIsArray($audit);
        self::assertStringNotContainsString('rossi', strtolower(json_encode($audit, JSON_THROW_ON_ERROR)));
        self::assertStringNotContainsString('mario', strtolower(json_encode($audit, JSON_THROW_ON_ERROR)));

        // Nothing left to find, and a second run does nothing.
        self::assertSame(['requests' => [], 'bookings' => [], 'emails' => []], $this->service()->export(self::EMAIL));
        self::assertSame(0, $this->service()->erase(self::EMAIL, true)['requests']);
    }

    public function testPendingRequestsAreSkippedUnlessIncluded(): void
    {
        $request = $this->service->createRequest($this->requestInput('2027-06-10', '2027-06-14', ['email' => self::EMAIL]));

        $skipped = $this->service()->erase(self::EMAIL, true);
        self::assertSame(0, $skipped['requests']);
        self::assertCount(1, $skipped['skipped']);
        self::assertStringContainsString('ancora in attesa', $skipped['skipped'][0]);
        self::assertSame(self::EMAIL, $this->row('SELECT email FROM booking_requests WHERE id = ?', [$request['id']])['email']);

        self::assertSame(1, $this->service()->erase(self::EMAIL, true, true)['requests']);
        self::assertSame('[eliminato]', $this->row('SELECT first_name FROM booking_requests WHERE id = ?', [$request['id']])['first_name']);
    }

    public function testStaysNotYetOverAreSkippedUnlessIncluded(): void
    {
        $this->confirmedStay('2027-06-10', '2027-06-14');

        $during = $this->service('2027-06-12')->erase(self::EMAIL, true);
        self::assertSame(1, $during['requests'], 'the request is decided, so it can go');
        self::assertSame(0, $during['bookings'], 'the stay is under way: the guest may still need to be contacted');
        self::assertStringContainsString('non è ancora finito', $during['skipped'][0]);
        self::assertNotSame('[eliminato]', $this->row('SELECT guest_name FROM bookings')['guest_name']);
    }

    public function testPurgeOnlyTouchesStaysThatEndedBeforeTheLimit(): void
    {
        $old = $this->confirmedStay('2027-06-10', '2027-06-14');                                    // ended long ago
        $border = $this->confirmedStay('2027-07-01', '2027-07-05', 'confine@example.test', 'Carla'); // ends exactly on the limit
        $recent = $this->confirmedStay('2027-09-01', '2027-09-05', 'recente@example.test', 'Rita');
        $pending = $this->service->createRequest($this->requestInput('2027-05-01', '2027-05-03', ['email' => 'attesa@example.test', 'first_name' => 'Paola']));

        // 12 months before 2028-07-05 is 2027-07-05: only stays ending BEFORE that day go.
        $result = $this->service('2028-07-05')->purgeOlderThan(12, true);

        self::assertSame(1, $result['requests']);
        self::assertSame(1, $result['bookings']);
        self::assertSame('[eliminato]', $this->row('SELECT first_name FROM booking_requests WHERE id = ?', [$old['request']])['first_name']);
        self::assertSame('Carla', $this->row('SELECT first_name FROM booking_requests WHERE id = ?', [$border['request']])['first_name'], 'the limit day itself is kept');
        self::assertSame('Rita', $this->row('SELECT first_name FROM booking_requests WHERE id = ?', [$recent['request']])['first_name']);
        self::assertSame('Paola', $this->row('SELECT first_name FROM booking_requests WHERE id = ?', [$pending['id']])['first_name'], 'an unanswered request is never purged');

        $again = $this->service('2028-07-05')->purgeOlderThan(12, true);
        self::assertSame(0, $again['requests'] + $again['bookings'], 'already anonymised rows are not counted again');
    }

    public function testPurgeIsADryRunByDefaultAndRefusesNonsensePeriods(): void
    {
        $this->confirmedStay('2027-06-10', '2027-06-14');

        $result = $this->service('2030-01-01')->purgeOlderThan(6, false);

        self::assertSame(1, $result['requests']);
        self::assertSame('Mario', $this->row('SELECT first_name FROM booking_requests')['first_name']);
        foreach ([0, -3] as $months) {
            try {
                $this->service()->purgeOlderThan($months, true);
                self::fail("a retention period of $months months must be refused");
            } catch (\InvalidArgumentException) {
                self::assertSame('Mario', $this->row('SELECT first_name FROM booking_requests')['first_name']);
            }
        }
    }

    public function testNothingToDoIsReportedAsZeroAndWritesNoAuditRow(): void
    {
        $result = $this->service()->erase('nessuno@example.test', true);

        self::assertSame(['requests' => 0, 'bookings' => 0, 'emails' => 0, 'skipped' => []], $result);
        self::assertSame(0, (int) $this->scalar('SELECT COUNT(*) FROM audit_log'));
    }

    public function testCommandLineToolIsSafeByDefault(): void
    {
        $this->confirmedStay('2027-06-10', '2027-06-14');
        $run = function (array $args, array $env = []): array {
            $process = proc_open(
                [PHP_BINARY, dirname(__DIR__, 2) . '/bin/privacy.php', ...$args],
                [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                $pipes,
                null,
                array_merge(getenv(), ['DB_NAME' => getenv('TEST_DB_NAME') ?: 'agriturismo_test', 'DATA_RETENTION_MONTHS' => ''], $env),
            );
            $out = stream_get_contents($pipes[1]);
            $err = stream_get_contents($pipes[2]);
            return [proc_close($process), (string) $out, (string) $err];
        };

        [$code, $out] = $run(['export', self::EMAIL]);
        self::assertSame(0, $code);
        $json = json_decode($out, true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('Mario', $json['requests'][0]['first_name']);

        [$code, $out] = $run(['erase', self::EMAIL, '--include-active']);
        self::assertSame(0, $code);
        self::assertStringContainsString('SIMULAZIONE', $out);
        self::assertSame('Mario', $this->row('SELECT first_name FROM booking_requests')['first_name'], 'no change without --apply');

        [$code, , $err] = $run(['purge']);
        self::assertSame(1, $code);
        self::assertStringContainsString('Non è stato fatto nulla', $err);

        [$code] = $run(['erase', 'not-an-email', '--apply']);
        self::assertSame(1, $code);
        [$code] = $run(['unknown']);
        self::assertSame(1, $code);

        [$code, $out] = $run(['erase', self::EMAIL, '--apply', '--include-active']);
        self::assertSame(0, $code);
        self::assertStringContainsString('ESEGUITO', $out);
        self::assertSame('[eliminato]', $this->row('SELECT first_name FROM booking_requests')['first_name']);
    }
}
