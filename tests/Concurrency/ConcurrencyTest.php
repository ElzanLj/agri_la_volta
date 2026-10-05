<?php

declare(strict_types=1);

namespace Tests\Concurrency;

use Tests\Support\DatabaseTestCase;

/**
 * Real concurrency: every scenario starts several separate PHP processes, each with its own
 * database connection, released at the same instant (tests/Support/worker.php). Scenarios are
 * repeated in rounds because races are probabilistic. After every round the database itself is
 * checked for overlapping confirmed bookings, independently of the application code.
 */
final class ConcurrencyTest extends DatabaseTestCase
{
    private const ROUNDS = 8;
    private const WORKERS = 8;
    /** Time given to the workers to start and connect before they are released together. */
    private const START_DELAY = 1.0;

    /**
     * Runs the jobs in parallel and returns their decoded results in job order.
     *
     * @param list<array{action: string, params: array<string, mixed>}> $jobs
     * @return list<array<string, mixed>>
     */
    private function runParallel(array $jobs): array
    {
        $startAt = microtime(true) + self::START_DELAY;
        $processes = [];

        foreach ($jobs as $i => $job) {
            $command = [PHP_BINARY, dirname(__DIR__) . '/Support/worker.php', json_encode($job + ['start_at' => $startAt], JSON_THROW_ON_ERROR)];
            $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            self::assertIsResource($process, "could not start worker $i");
            $processes[$i] = [$process, $pipes];
        }

        $results = [];
        foreach ($processes as $i => [$process, $pipes]) {
            $stdout = stream_get_contents($pipes[1]);
            $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($process);

            $decoded = json_decode(trim((string) $stdout), true);
            self::assertIsArray($decoded, "worker $i produced no valid output. stdout=[$stdout] stderr=[$stderr]");
            $results[$i] = $decoded;
        }

        return $results;
    }

    /**
     * Fails on anything other than a clean success or an expected business refusal:
     * no exceptions, no lock timeouts, no deadlocks.
     *
     * @param list<array<string, mixed>> $results
     */
    private function assertNoTechnicalErrors(array $results, string $context): void
    {
        foreach ($results as $i => $result) {
            if (($result['ok'] ?? false) === true) {
                continue;
            }
            self::assertContains($result['error'] ?? null, ['conflict', 'state'], "$context: worker $i failed unexpectedly: " . json_encode($result));
        }
    }

    /** @param list<array<string, mixed>> $results */
    private function countOk(array $results): int
    {
        return count(array_filter($results, static fn (array $r): bool => $r['ok'] === true));
    }

    /** @return list<int> ids of pending requests, one per client, all for the given dates */
    private function pendingRequests(int $count, string $in, string $out, ?int $apartmentId = null): array
    {
        $ids = [];
        for ($i = 0; $i < $count; $i++) {
            $input = $this->requestInput($in, $out, ['first_name' => 'Cliente' . $i, 'email' => "cliente{$i}@example.com"]);
            if ($apartmentId !== null) {
                $input['apartment_id'] = $apartmentId;
            }
            $ids[] = $this->service->createRequest($input)['id'];
        }
        return $ids;
    }

    private function assertNoOverlaps(string $context): void
    {
        self::assertSame(0, $this->overlapViolations(), "$context: overlapping confirmed bookings found in the database");
    }

    public function testManyRequestsForTheSameDatesYieldExactlyOneConfirmation(): void
    {
        for ($round = 1; $round <= self::ROUNDS; $round++) {
            $this->resetDatabase();
            $ids = $this->pendingRequests(self::WORKERS, '2027-06-10', '2027-06-15');

            $results = $this->runParallel(array_map(
                static fn (int $id): array => ['action' => 'confirm', 'params' => ['request_id' => $id]],
                $ids,
            ));

            $ctx = "round $round";
            $this->assertNoTechnicalErrors($results, $ctx);
            self::assertSame(1, $this->countOk($results), "$ctx: exactly one confirmation must win");
            foreach ($results as $result) {
                if ($result['ok'] === false) {
                    self::assertSame('conflict', $result['error'], "$ctx: the losers must be clean date conflicts");
                }
            }
            self::assertSame(1, (int) $this->scalar("SELECT COUNT(*) FROM bookings WHERE status = 'confirmed'"), $ctx);
            self::assertSame(1, (int) $this->scalar("SELECT COUNT(*) FROM booking_requests WHERE status = 'confirmed'"), $ctx);
            self::assertSame(self::WORKERS - 1, (int) $this->scalar("SELECT COUNT(*) FROM booking_requests WHERE status = 'pending'"), "$ctx: losing requests stay pending");
            $this->assertNoOverlaps($ctx);
        }
    }

    public function testTheSameRequestConfirmedByManyWorkersCreatesOneBooking(): void
    {
        for ($round = 1; $round <= self::ROUNDS; $round++) {
            $this->resetDatabase();
            [$id] = $this->pendingRequests(1, '2027-06-10', '2027-06-15');

            $results = $this->runParallel(array_fill(0, self::WORKERS, ['action' => 'confirm', 'params' => ['request_id' => $id]]));

            $ctx = "round $round";
            $this->assertNoTechnicalErrors($results, $ctx);
            self::assertSame(1, $this->countOk($results), $ctx);
            self::assertSame(1, (int) $this->scalar('SELECT COUNT(*) FROM bookings'), "$ctx: no duplicate booking");
            $this->assertNoOverlaps($ctx);
        }
    }

    public function testConfirmAndRejectOfTheSameRequestNeverBothSucceed(): void
    {
        for ($round = 1; $round <= self::ROUNDS; $round++) {
            $this->resetDatabase();
            [$id] = $this->pendingRequests(1, '2027-06-10', '2027-06-15');

            $results = $this->runParallel([
                ['action' => 'confirm', 'params' => ['request_id' => $id]],
                ['action' => 'reject', 'params' => ['request_id' => $id]],
            ]);

            $ctx = "round $round";
            $this->assertNoTechnicalErrors($results, $ctx);
            self::assertSame(1, $this->countOk($results), "$ctx: exactly one decision wins");
            $status = (string) $this->scalar('SELECT status FROM booking_requests WHERE id = ?', [$id]);
            $bookings = (int) $this->scalar('SELECT COUNT(*) FROM bookings');
            self::assertSame($status === 'confirmed' ? 1 : 0, $bookings, "$ctx: request is '$status' but there are $bookings bookings");
        }
    }

    public function testRequestsManualBookingsAndBlocksCompetingForTheSameDates(): void
    {
        for ($round = 1; $round <= self::ROUNDS; $round++) {
            $this->resetDatabase();
            $apartmentId = $this->apartmentId();
            $ids = $this->pendingRequests(3, '2027-06-10', '2027-06-15');

            $jobs = [];
            foreach ($ids as $id) {
                $jobs[] = ['action' => 'confirm', 'params' => ['request_id' => $id]];
            }
            for ($i = 0; $i < 3; $i++) {
                $jobs[] = ['action' => 'manual', 'params' => ['input' => $this->manualInput('2027-06-11', '2027-06-14', ['guest_name' => "Telefono $i"])]];
            }
            for ($i = 0; $i < 2; $i++) {
                $jobs[] = ['action' => 'block', 'params' => ['apartment_id' => $apartmentId, 'start' => '2027-06-12', 'end' => '2027-06-16', 'reason' => "blocco $i"]];
            }

            $results = $this->runParallel($jobs);

            $ctx = "round $round";
            $this->assertNoTechnicalErrors($results, $ctx);
            $bookings = (int) $this->scalar("SELECT COUNT(*) FROM bookings WHERE status = 'confirmed'");
            $blocks = (int) $this->scalar('SELECT COUNT(*) FROM availability_blocks');

            // Either one booking took the dates (and then no block fits), or only blocks exist.
            self::assertTrue(
                ($bookings === 1 && $blocks === 0) || ($bookings === 0 && $blocks === 2),
                "$ctx: bookings=$bookings blocks=$blocks",
            );
            $this->assertNoOverlaps($ctx);
        }
    }

    public function testConsecutiveStaysBookedAtTheSameTimeAllSucceed(): void
    {
        for ($round = 1; $round <= self::ROUNDS; $round++) {
            $this->resetDatabase();
            $stays = [['2027-06-05', '2027-06-10'], ['2027-06-10', '2027-06-15'], ['2027-06-15', '2027-06-18'], ['2027-06-18', '2027-06-20']];

            $results = $this->runParallel(array_map(
                fn (array $s): array => ['action' => 'manual', 'params' => ['input' => $this->manualInput($s[0], $s[1])]],
                $stays,
            ));

            $ctx = "round $round";
            self::assertSame(4, $this->countOk($results), "$ctx: back-to-back stays must not exclude each other: " . json_encode($results));
            self::assertSame(4, (int) $this->scalar("SELECT COUNT(*) FROM bookings WHERE status = 'confirmed'"), $ctx);
            $this->assertNoOverlaps($ctx);
        }
    }

    public function testDifferentApartmentsAreBookedInParallelWithoutBlockingEachOther(): void
    {
        for ($round = 1; $round <= self::ROUNDS; $round++) {
            $this->resetDatabase();
            $slugs = ['margherita', 'girasole', 'rosa', 'mimosa', 'ciclamino', 'viola'];

            $results = $this->runParallel(array_map(
                fn (string $slug): array => ['action' => 'manual', 'params' => ['input' => $this->manualInput('2027-06-10', '2027-06-15', ['apartment_id' => $this->apartmentId($slug)])]],
                $slugs,
            ));

            $ctx = "round $round";
            self::assertSame(6, $this->countOk($results), "$ctx: " . json_encode($results));
            $this->assertNoOverlaps($ctx);
        }
    }

    public function testCancellationRacingWithAConfirmationLeavesAConsistentState(): void
    {
        for ($round = 1; $round <= self::ROUNDS; $round++) {
            $this->resetDatabase();
            // The request is submitted first (dates still free); the phone booking arrives afterwards.
            [$requestId] = $this->pendingRequests(1, '2027-06-12', '2027-06-17');
            $existing = $this->book('2027-06-10', '2027-06-15');

            $results = $this->runParallel([
                ['action' => 'cancel', 'params' => ['booking_id' => $existing, 'reason' => 'test']],
                ['action' => 'confirm', 'params' => ['request_id' => $requestId]],
            ]);

            $ctx = "round $round";
            $this->assertNoTechnicalErrors($results, $ctx);
            self::assertTrue($results[0]['ok'], "$ctx: the cancellation must always succeed");
            // Confirm either ran after the cancellation (success) or before it (conflict, request stays pending).
            if ($results[1]['ok']) {
                self::assertSame('confirmed', $this->scalar('SELECT status FROM booking_requests WHERE id = ?', [$requestId]), $ctx);
                self::assertSame(1, (int) $this->scalar("SELECT COUNT(*) FROM bookings WHERE status = 'confirmed'"), $ctx);
            } else {
                self::assertSame('conflict', $results[1]['error'], $ctx);
                self::assertSame('pending', $this->scalar('SELECT status FROM booking_requests WHERE id = ?', [$requestId]), $ctx);
                self::assertSame(0, (int) $this->scalar("SELECT COUNT(*) FROM bookings WHERE status = 'confirmed'"), $ctx);
            }
            self::assertSame('cancelled', $this->scalar('SELECT status FROM bookings WHERE id = ?', [$existing]), $ctx);
            $this->assertNoOverlaps($ctx);
        }
    }

    public function testRandomStaysBookedConcurrentlyNeverOverlap(): void
    {
        mt_srand(20271005); // fixed seed: the scenario is reproducible
        for ($round = 1; $round <= self::ROUNDS; $round++) {
            $this->resetDatabase();
            $jobs = [];
            for ($i = 0; $i < 16; $i++) {
                $startDay = mt_rand(1, 24);
                $nights = mt_rand(1, 6);
                $in = sprintf('2027-07-%02d', $startDay);
                $out = date('Y-m-d', strtotime("$in +$nights days"));
                $jobs[] = ['action' => 'manual', 'params' => ['input' => $this->manualInput($in, $out, ['guest_name' => "Random $i"])]];
            }

            $results = $this->runParallel($jobs);

            $ctx = "round $round";
            $this->assertNoTechnicalErrors($results, $ctx);
            self::assertGreaterThanOrEqual(1, $this->countOk($results), $ctx);
            self::assertSame($this->countOk($results), (int) $this->scalar("SELECT COUNT(*) FROM bookings WHERE status = 'confirmed'"), "$ctx: reported successes must match stored bookings");
            $this->assertNoOverlaps($ctx);
        }
    }
}
