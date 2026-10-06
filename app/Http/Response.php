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
        // The site needs none of these browser features: deny them outright.
        'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=(), usb=(), interest-cohort=()',
        'Cross-Origin-Opener-Policy' => 'same-origin',
        'X-Permitted-Cross-Domain-Policies' => 'none',
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

    /** A CSV download. The BOM makes Excel read the file as UTF-8. */
    public static function csv(string $content, string $filename): self
    {
        return new self("\xEF\xBB\xBF" . $content, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . preg_replace('/[^A-Za-z0-9._-]/', '_', $filename) . '"',
        ]);
    }

    public function withHeader(string $name, string $value): self
    {
        $clone = clone $this;
        $clone->headers[$name] = $value;
        return $clone;
    }

    /**
     * HSTS only when the site is configured for HTTPS (APP_URL starts with https://) and HSTS_MAX_AGE is not 0:
     * browsers then refuse plain HTTP for that long, so it must never be sent by an HTTP-only installation.
     *
     * @return array<string, string>
     */
    private static function transportSecurity(): array
    {
        try {
            $config = \App\App::current()->config;
        } catch (\LogicException) {
            return [];
        }
        $maxAge = $config->int('HSTS_MAX_AGE', 15552000);
        if ($maxAge <= 0 || !str_starts_with($config->string('APP_URL'), 'https://')) {
            return [];
        }
        return ['Strict-Transport-Security' => 'max-age=' . $maxAge];
    }

    public function send(bool $includeBody = true): void
    {
        http_response_code($this->status);
        header_remove('X-Powered-By'); // do not advertise the PHP version
        foreach ($this->headers + self::DEFAULT_HEADERS + self::transportSecurity() as $name => $value) {
            header($name . ': ' . $value);
        }
        if ($includeBody) {
            echo $this->body;
        }
    }
}
