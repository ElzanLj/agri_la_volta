<?php

declare(strict_types=1);

namespace App;

use App\Database\Connection;
use App\Support\Logger;
use PDO;

/**
 * Holds the configuration and the services shared by a request or CLI run.
 */
final class App
{
    private static ?self $current = null;

    private ?PDO $db = null;

    public function __construct(
        public readonly Config $config,
        public readonly Logger $logger,
    ) {
        self::$current = $this;
    }

    public static function current(): self
    {
        if (self::$current === null) {
            throw new \LogicException('Application not bootstrapped.');
        }
        return self::$current;
    }

    /** Lazily opens the database connection, so pages that do not need it never touch it. */
    public function db(): PDO
    {
        return $this->db ??= Connection::connect($this->config);
    }

    /** URL path prefix when installed in a subdirectory ("" at the domain root). */
    public function basePath(): string
    {
        $path = parse_url($this->config->string('APP_URL'), PHP_URL_PATH);
        return is_string($path) ? rtrim($path, '/') : '';
    }

    public function isHttps(): bool
    {
        $https = strtolower((string) ($_SERVER['HTTPS'] ?? ''));
        return ($https !== '' && $https !== 'off')
            || (string) ($_SERVER['SERVER_PORT'] ?? '') === '443'
            || str_starts_with($this->config->string('APP_URL'), 'https://');
    }
}
