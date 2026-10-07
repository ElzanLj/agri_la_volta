<?php

declare(strict_types=1);

namespace App\Http;

final class Request
{
    /**
     * @param array<string, mixed> $post
     * @param array<string, mixed> $server
     * @param array<string, mixed> $query
     */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        private array $post = [],
        private array $server = [],
        private array $query = [],
    ) {
    }

    public static function fromGlobals(string $basePath): self
    {
        $path = rawurldecode((string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/'));
        if ($basePath !== '' && str_starts_with($path, $basePath)) {
            $path = substr($path, strlen($basePath));
        }
        if ($path === '' || $path[0] !== '/') {
            $path = '/' . $path;
        }

        return new self(strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')), $path, $_POST, $_SERVER, $_GET);
    }

    /**
     * Returns a submitted form field as a string ("" when missing or not a string). Invisible and
     * direction-control characters are removed (see InvisibleChars): they can make a name look different
     * from what is stored. Use rawInput() for passwords.
     */
    public function input(string $key): string
    {
        return InvisibleChars::strip($this->rawInput($key));
    }

    /** The field exactly as submitted (passwords must not be altered). */
    public function rawInput(string $key): string
    {
        $value = $this->post[$key] ?? '';
        return is_string($value) ? $value : '';
    }

    /** Returns a query-string value as a string ("" when missing or not a string). Never used for tokens. */
    public function query(string $key): string
    {
        $value = $this->query[$key] ?? '';
        return is_string($value) ? InvisibleChars::strip($value) : '';
    }

    /** True when a query parameter was sent as an array (e.g. ?stato[]=x). */
    public function queryIsArray(string $key): bool
    {
        return is_array($this->query[$key] ?? null);
    }

    public function header(string $name): string
    {
        $value = $this->server['HTTP_' . strtoupper(str_replace('-', '_', $name))] ?? '';
        return is_string($value) ? $value : '';
    }

    public function ip(): string
    {
        return (string) ($this->server['REMOTE_ADDR'] ?? '');
    }

    public function isSafeMethod(): bool
    {
        return in_array($this->method, ['GET', 'HEAD', 'OPTIONS'], true);
    }
}
