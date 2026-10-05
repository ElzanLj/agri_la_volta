<?php

declare(strict_types=1);

namespace Tests\Support;

final class HttpResponse
{
    /** @param array<string, list<string>> $headers lower-case name => values */
    public function __construct(
        public readonly int $status,
        public readonly array $headers,
        public readonly string $body,
    ) {
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)][0] ?? null;
    }

    /** @return list<string> raw Set-Cookie header lines */
    public function setCookies(): array
    {
        return $this->headers['set-cookie'] ?? [];
    }

    public function location(): ?string
    {
        return $this->header('location');
    }

    /** The CSRF token of the first form on the page, if any. */
    public function csrfToken(): ?string
    {
        return preg_match('/name="_csrf" value="([0-9a-f]+)"/', $this->body, $m) === 1 ? $m[1] : null;
    }
}
