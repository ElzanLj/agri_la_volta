<?php

declare(strict_types=1);

namespace Tests\Concurrency;

use Tests\Support\DatabaseTestCase;
use Tests\Support\MailTestHelpers;

/**
 * Several processes (page request, cron script, manual retry) try to send the same queued
 * messages at the same moment: the atomic claim must let every message leave exactly once.
 */
final class MailConcurrencyTest extends DatabaseTestCase
{
    use MailTestHelpers;

    private const ROUNDS = 5;
    private const WORKERS = 8;

    private string $spool = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->setMailEnv();
        $this->spool = sys_get_temp_dir() . '/lavolta-mailrace-' . bin2hex(random_bytes(4));
        mkdir($this->spool, 0775, true);
    }

    protected function tearDown(): void
    {
        $this->restoreMailEnv();
        foreach (glob($this->spool . '/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->spool);
    }

    /** @return list<array<string, mixed>> */
    private function runWorkers(array $jobs): array
    {
        $startAt = microtime(true) + 1.0;
        $processes = [];
        foreach ($jobs as $i => $job) {
            $command = [PHP_BINARY, dirname(__DIR__) . '/Support/mail-worker.php', json_encode($job + ['dir' => $this->spool, 'start_at' => $startAt], JSON_THROW_ON_ERROR)];
            $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            self::assertIsResource($process);
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
            self::assertIsArray($decoded, "worker $i: stdout=[$stdout] stderr=[$stderr]");
            self::assertTrue($decoded['ok'], "worker $i crashed: " . json_encode($decoded));
            $results[$i] = $decoded;
        }
        return $results;
    }

    private function sentFiles(): int
    {
        return count(glob($this->spool . '/*.eml') ?: []);
    }

    private function resetSpool(): void
    {
        foreach (glob($this->spool . '/*') ?: [] as $file) {
            unlink($file);
        }
    }

    public function testManyWorkersDispatchingTheSameMessageSendItOnce(): void
    {
        for ($round = 1; $round <= self::ROUNDS; $round++) {
            $this->resetDatabase();
            $this->resetSpool();
            $this->service->createRequest($this->requestInput('2027-06-10', '2027-06-15'));
            $id = (int) $this->outboxRows()[0]['id'];

            $results = $this->runWorkers(array_fill(0, self::WORKERS, ['action' => 'dispatch', 'id' => $id]));

            $outcomes = array_count_values(array_column($results, 'result'));
            self::assertSame(1, $outcomes['sent'] ?? 0, "round $round: exactly one worker sends: " . json_encode($outcomes));
            self::assertSame(self::WORKERS - 1, $outcomes['busy'] ?? 0, "round $round: the others back off");
            self::assertSame(1, $this->sentFiles(), "round $round: the message left exactly once");
            self::assertSame(['sent', 1], [$this->outboxRows()[0]['status'], (int) $this->outboxRows()[0]['attempts']], "round $round");
        }
    }

    public function testPollingWorkersShareTheQueueWithoutDuplicates(): void
    {
        for ($round = 1; $round <= self::ROUNDS; $round++) {
            $this->resetDatabase();
            $this->resetSpool();
            for ($i = 0; $i < 6; $i++) {
                $this->service->createRequest($this->requestInput('2027-06-10', '2027-06-12', ['first_name' => "Cliente{$i}"]));
            }

            $results = $this->runWorkers(array_fill(0, self::WORKERS, ['action' => 'due', 'limit' => 10]));

            $sent = 0;
            foreach ($results as $r) {
                $sent += count(array_filter($r['result'], static fn (string $o): bool => $o === 'sent'));
            }
            self::assertSame(6, $sent, "round $round: each of the 6 messages was sent by exactly one worker");
            self::assertSame(6, $this->sentFiles(), "round $round: no duplicate and no lost message");
            self::assertSame(['sent'], array_unique(array_column($this->outboxRows(), 'status')));
            self::assertSame([1], array_values(array_unique(array_map('intval', array_column($this->outboxRows(), 'attempts')))), "round $round: one attempt each");
        }
    }

    public function testAMessageIsNotSentAgainOnceDelivered(): void
    {
        $this->service->createRequest($this->requestInput('2027-06-10', '2027-06-15'));
        $id = (int) $this->outboxRows()[0]['id'];
        $this->runWorkers([['action' => 'dispatch', 'id' => $id]]);

        $again = $this->runWorkers([['action' => 'dispatch', 'id' => $id], ['action' => 'due', 'limit' => 5]]);

        self::assertSame('busy', $again[0]['result']);
        self::assertSame([], $again[1]['result']);
        self::assertSame(1, $this->sentFiles());
    }
}
