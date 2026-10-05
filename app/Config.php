<?php

declare(strict_types=1);

namespace App;

/**
 * Configuration read from real environment variables first, then from the .env file.
 */
final class Config
{
    /** @param array<string, string> $fileValues */
    public function __construct(private array $fileValues)
    {
    }

    public static function load(string $envFile): self
    {
        return new self(is_file($envFile) ? self::parseEnvFile($envFile) : []);
    }

    public function string(string $key, string $default = ''): string
    {
        $value = getenv($key);
        if ($value === false) {
            $value = $this->fileValues[$key] ?? null;
        }
        return ($value === null || $value === '') ? $default : $value;
    }

    public function int(string $key, int $default): int
    {
        $value = $this->string($key);
        return ctype_digit($value) ? (int) $value : $default;
    }

    public function bool(string $key, bool $default = false): bool
    {
        $value = strtolower($this->string($key));
        if ($value === '') {
            return $default;
        }
        return in_array($value, ['1', 'true', 'yes', 'on'], true);
    }

    public function isProduction(): bool
    {
        return $this->string('APP_ENV', 'production') === 'production';
    }

    /** Debug output is never enabled in production, whatever APP_DEBUG says. */
    public function isDebug(): bool
    {
        return !$this->isProduction() && $this->bool('APP_DEBUG');
    }

    /** @return array<string, string> */
    private static function parseEnvFile(string $file): array
    {
        $values = [];
        foreach (file($file, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = array_map('trim', explode('=', $line, 2));
            $quote = $value[0] ?? '';
            if (($quote === '"' || $quote === "'") && str_ends_with($value, $quote) && strlen($value) > 1) {
                $value = substr($value, 1, -1);
            } else {
                // Unquoted values may carry a trailing " # comment".
                $value = trim((string) preg_replace('/\s+#.*$/', '', $value));
            }
            $values[$key] = $value;
        }
        return $values;
    }
}
