<?php

declare(strict_types=1);

namespace App\Support;

use Throwable;

/**
 * Minimal file logger. Callers must not pass secrets or unnecessary personal data.
 */
final class Logger
{
    public function __construct(private string $directory)
    {
    }

    /** @param array<string, mixed> $context */
    public function error(string $message, array $context = []): void
    {
        $this->write('ERROR', $message, $context);
    }

    /** @param array<string, mixed> $context */
    public function warning(string $message, array $context = []): void
    {
        $this->write('WARNING', $message, $context);
    }

    /** @param array<string, mixed> $context */
    public function info(string $message, array $context = []): void
    {
        $this->write('INFO', $message, $context);
    }

    /**
     * What is safe to log about an exception. A database exception message can contain the values of
     * the failed statement (e-mail addresses, names) or connection details, so only the SQLSTATE and the
     * driver error code are kept for those.
     */
    private static function describe(Throwable $e): string
    {
        if ($e instanceof \PDOException) {
            $sqlState = (string) ($e->errorInfo[0] ?? $e->getCode());
            $driverCode = (string) ($e->errorInfo[1] ?? '');
            return 'SQLSTATE ' . (preg_match('/^[0-9A-Z]{5}$/', $sqlState) === 1 ? $sqlState : 'unknown')
                . ($driverCode !== '' && ctype_digit($driverCode) ? ', driver code ' . $driverCode : '');
        }
        return $e->getMessage();
    }

    /** @param array<string, mixed> $context */
    private function write(string $level, string $message, array $context): void
    {
        if (($context['exception'] ?? null) instanceof Throwable) {
            $e = $context['exception'];
            $context['exception'] = sprintf('%s: %s @ %s:%d', $e::class, self::describe($e), $e->getFile(), $e->getLine());
        }
        $line = sprintf(
            "[%s] %s %s%s\n",
            gmdate('Y-m-d\TH:i:s\Z'),
            $level,
            $message,
            $context === [] ? '' : ' ' . json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        );

        // Writing a log line must never break the page. The folder can be writable while today's file is not
        // (for example it was created by another user, such as a command line script run as root): the "@"
        // keeps the warning from becoming an exception (see the error handler in bootstrap.php) and the
        // line goes to the PHP error log instead.
        $written = is_dir($this->directory) && is_writable($this->directory)
            && @file_put_contents($this->directory . '/app-' . gmdate('Y-m-d') . '.log', $line, FILE_APPEND | LOCK_EX) !== false;
        if (!$written) {
            error_log(rtrim($line));
        }
    }
}
