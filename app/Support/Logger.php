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

    /** @param array<string, mixed> $context */
    private function write(string $level, string $message, array $context): void
    {
        if (($context['exception'] ?? null) instanceof Throwable) {
            $e = $context['exception'];
            $context['exception'] = sprintf('%s: %s @ %s:%d', $e::class, $e->getMessage(), $e->getFile(), $e->getLine());
        }
        $line = sprintf(
            "[%s] %s %s%s\n",
            gmdate('Y-m-d\TH:i:s\Z'),
            $level,
            $message,
            $context === [] ? '' : ' ' . json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        );

        if (is_dir($this->directory) && is_writable($this->directory)) {
            file_put_contents($this->directory . '/app-' . gmdate('Y-m-d') . '.log', $line, FILE_APPEND | LOCK_EX);
        } else {
            error_log(rtrim($line));
        }
    }
}
