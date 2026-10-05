<?php

declare(strict_types=1);

namespace App\Http;

final class Response
{
    private const DEFAULT_HEADERS = [
        'Content-Type' => 'text/html; charset=UTF-8',
        'X-Content-Type-Options' => 'nosniff',
        'X-Frame-Options' => 'DENY',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
        'Content-Security-Policy' => "default-src 'self'; img-src 'self' data:; style-src 'self'; script-src 'self'; "
            . "frame-ancestors 'none'; form-action 'self'; base-uri 'self'; object-src 'none'",
    ];

    /** @param array<string, string> $headers */
    public function __construct(
        public string $body = '',
        public int $status = 200,
        public array $headers = [],
    ) {
    }

    public static function html(string $body, int $status = 200): self
    {
        return new self($body, $status);
    }

    /** 303 by default: the right status after a form POST. */
    public static function redirect(string $location, int $status = 303): self
    {
        return new self('', $status, ['Location' => $location]);
    }

    public function withHeader(string $name, string $value): self
    {
        $clone = clone $this;
        $clone->headers[$name] = $value;
        return $clone;
    }

    public function send(bool $includeBody = true): void
    {
        http_response_code($this->status);
        foreach ($this->headers + self::DEFAULT_HEADERS as $name => $value) {
            header($name . ': ' . $value);
        }
        if ($includeBody) {
            echo $this->body;
        }
    }
}
