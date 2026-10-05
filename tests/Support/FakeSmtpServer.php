<?php

declare(strict_types=1);

namespace Tests\Support;

/** Starts tests/Support/fake-smtp-server.php and reads back what it received. */
final class FakeSmtpServer
{
    /** @param resource $process */
    private function __construct(private $process, public readonly int $port, public readonly string $dir)
    {
    }

    public static function start(string $scenario = 'ok'): self
    {
        $dir = sys_get_temp_dir() . '/lavolta-smtp-' . bin2hex(random_bytes(4));
        mkdir($dir, 0775, true);
        file_put_contents($dir . '/scenario', $scenario);

        $socket = stream_socket_server('tcp://127.0.0.1:0');
        $name = (string) stream_socket_get_name($socket, false);
        fclose($socket);
        $port = (int) substr($name, (int) strrpos($name, ':') + 1);

        $process = proc_open(
            [PHP_BINARY, __DIR__ . '/fake-smtp-server.php', (string) $port, $dir],
            [0 => ['pipe', 'r'], 1 => ['file', $dir . '/stdout.log', 'a'], 2 => ['file', $dir . '/stderr.log', 'a']],
            $pipes,
        );
        if (!is_resource($process)) {
            throw new \RuntimeException('Could not start the fake SMTP server.');
        }

        $deadline = microtime(true) + 5;
        while (microtime(true) < $deadline) {
            if (is_file($dir . '/ready')) {
                return new self($process, $port, $dir);
            }
            usleep(30_000);
        }
        proc_terminate($process);
        throw new \RuntimeException('The fake SMTP server did not start: ' . (string) @file_get_contents($dir . '/stderr.log'));
    }

    public function setScenario(string $scenario): void
    {
        file_put_contents($this->dir . '/scenario', $scenario);
    }

    /** @return list<array{mail_from: ?string, rcpt_to: list<string>, auth_user: ?string, data: string}> */
    public function messages(): array
    {
        $messages = [];
        foreach (glob($this->dir . '/message-*.json') ?: [] as $file) {
            $messages[] = json_decode((string) file_get_contents($file), true);
        }
        return $messages;
    }

    /** @return list<string> every command line received, connection markers included */
    public function commands(): array
    {
        $file = $this->dir . '/commands.log';
        return is_file($file) ? array_values(array_filter(explode("\n", (string) file_get_contents($file)), static fn (string $l): bool => $l !== '')) : [];
    }

    public function connectionCount(): int
    {
        return count(array_filter($this->commands(), static fn (string $l): bool => str_starts_with($l, '--- connection')));
    }

    public function stop(): void
    {
        if (is_resource($this->process)) {
            proc_terminate($this->process);
            proc_close($this->process);
        }
        foreach (glob($this->dir . '/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->dir);
    }
}
