<?php

declare(strict_types=1);

namespace Tests\Support;

/**
 * Minimal browser-like client: keeps cookies, never follows redirects (so tests see the raw
 * 303/401/403), sends form-encoded POSTs.
 */
final class HttpClient
{
    /** @var array<string, string> */
    public array $cookies = [];

    public function __construct(private string $baseUrl)
    {
    }

    /** @param array<string, string> $headers */
    public function get(string $path, array $headers = []): HttpResponse
    {
        return $this->request('GET', $path, null, $headers);
    }

    /** @param array<string, string> $headers */
    public function head(string $path, array $headers = []): HttpResponse
    {
        return $this->request('HEAD', $path, null, $headers);
    }

    /**
     * @param array<string, mixed> $fields
     * @param array<string, string> $headers
     */
    public function post(string $path, array $fields = [], array $headers = []): HttpResponse
    {
        return $this->request('POST', $path, http_build_query($fields), $headers + ['Content-Type' => 'application/x-www-form-urlencoded']);
    }

    /** @param array<string, string> $headers */
    public function send(string $method, string $path, ?string $body = null, array $headers = []): HttpResponse
    {
        return $this->request($method, $path, $body, $headers);
    }

    public function sessionId(): ?string
    {
        return $this->cookies['lavolta_session'] ?? null;
    }

    /** @param array<string, string> $headers */
    private function request(string $method, string $path, ?string $body, array $headers): HttpResponse
    {
        $responseHeaders = [];
        $status = 0;

        $lines = [];
        foreach ($headers as $name => $value) {
            $lines[] = $name . ': ' . $value;
        }
        if ($this->cookies !== []) {
            $lines[] = 'Cookie: ' . implode('; ', array_map(static fn (string $k, string $v): string => $k . '=' . $v, array_keys($this->cookies), $this->cookies));
        }

        $curl = curl_init($this->baseUrl . $path);
        curl_setopt_array($curl, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HTTPHEADER => $lines,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_NOBODY => $method === 'HEAD',
            CURLOPT_HEADERFUNCTION => static function ($ch, string $line) use (&$responseHeaders, &$status): int {
                if (preg_match('#^HTTP/\S+ (\d{3})#', $line, $m)) {
                    $status = (int) $m[1];
                } elseif (str_contains($line, ':')) {
                    [$name, $value] = explode(':', $line, 2);
                    $responseHeaders[strtolower(trim($name))][] = trim($value);
                }
                return strlen($line);
            },
        ]);
        if ($body !== null) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
        }

        $content = curl_exec($curl);
        if ($content === false) {
            $error = curl_error($curl);
            curl_close($curl);
            throw new \RuntimeException("HTTP request failed: $method $path: $error");
        }
        curl_close($curl);

        foreach ($responseHeaders['set-cookie'] ?? [] as $cookie) {
            $this->storeCookie($cookie);
        }

        return new HttpResponse($status, $responseHeaders, (string) $content);
    }

    private function storeCookie(string $line): void
    {
        $parts = array_map('trim', explode(';', $line));
        [$name, $value] = array_pad(explode('=', array_shift($parts), 2), 2, '');

        foreach ($parts as $attribute) {
            if (preg_match('/^expires=(.+)$/i', $attribute, $m) && strtotime($m[1]) !== false && strtotime($m[1]) < time()) {
                unset($this->cookies[$name]);
                return;
            }
        }
        $this->cookies[$name] = $value;
    }
}
