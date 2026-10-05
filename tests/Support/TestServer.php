<?php

declare(strict_types=1);

namespace Tests\Support;

/**
 * Starts the real application behind PHP's built-in web server, pointed at the TEST database,
 * so security tests exercise real HTTP: status codes, headers, cookies, redirects.
 */
final class TestServer
{
    /** @param resource $process */
    private function __construct(private $process, public readonly int $port, private string $logFile)
    {
    }

    /** @param array<string, string> $env overrides, e.g. ['APP_URL' => 'https://127.0.0.1:1234'] */
    public static function start(array $env = []): self
    {
        $root = dirname(__DIR__, 2);
        $port = self::freePort();
        $log = sys_get_temp_dir() . '/lavolta-test-server-' . $port . '.log';

        $environment = array_merge(getenv(), [
            'APP_ENV' => 'development',
            'APP_DEBUG' => 'false',
            'APP_URL' => 'http://127.0.0.1:' . $port,
            'DB_NAME' => getenv('TEST_DB_NAME') ?: 'agriturismo_test',
            // Tests never send real mail: by default messages are written to a throw-away folder.
            'MAIL_TRANSPORT' => 'log',
            'MAIL_LOG_DIR' => sys_get_temp_dir() . '/lavolta-test-mail-' . $port,
            'MAIL_ADMIN_ADDRESS' => 'gestore@example.test',
            'MAIL_FROM_ADDRESS' => 'info@example.test',
        ], $env);

        $process = proc_open(
            [PHP_BINARY, '-S', '127.0.0.1:' . $port, '-t', $root . '/public', $root . '/public/index.php'],
            [0 => ['pipe', 'r'], 1 => ['file', $log, 'a'], 2 => ['file', $log, 'a']],
            $pipes,
            $root,
            $environment,
        );
        if (!is_resource($process)) {
            throw new \RuntimeException('Could not start the PHP built-in server.');
        }

        $deadline = microtime(true) + 10;
        while (microtime(true) < $deadline) {
            $socket = @fsockopen('127.0.0.1', $port, $errno, $error, 0.2);
            if ($socket !== false) {
                fclose($socket);
                return new self($process, $port, $log);
            }
            usleep(50_000);
        }
        proc_terminate($process);
        throw new \RuntimeException('The PHP built-in server did not start. Log: ' . (string) @file_get_contents($log));
    }

    public function baseUrl(): string
    {
        return 'http://127.0.0.1:' . $this->port;
    }

    public function stop(): void
    {
        if (is_resource($this->process)) {
            proc_terminate($this->process);
            proc_close($this->process);
        }
        @unlink($this->logFile);
        $mailDir = sys_get_temp_dir() . '/lavolta-test-mail-' . $this->port;
        foreach (glob($mailDir . '/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($mailDir);
    }

    private static function freePort(): int
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
        if ($socket === false) {
            throw new \RuntimeException('No free port: ' . $error);
        }
        $name = stream_socket_get_name($socket, false);
        fclose($socket);
        return (int) substr((string) $name, (int) strrpos((string) $name, ':') + 1);
    }
}
