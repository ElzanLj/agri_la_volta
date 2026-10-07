<?php

declare(strict_types=1);

namespace App\Http;

/**
 * One address for the site. When the site answers on more than one host name (www and without,
 * or an old domain), forms posted from the "other" host are refused by the Origin check. Visitors are
 * therefore sent to the host of APP_URL with a permanent redirect, for GET and HEAD only.
 *
 * Not done here, on purpose:
 * - nothing happens when APP_URL is empty or has no host (nothing to compare with);
 * - the http -> https redirect is left to the hosting: behind a proxy that ends TLS, doing it in PHP
 *   can loop forever;
 * - nothing happens when the request carries X-Forwarded-Host: a proxy that rewrites Host would
 *   make every request look foreign and loop. Leave APP_URL empty in that setup.
 *
 * The target is built from APP_URL, never from the Host header the visitor sent.
 */
final class CanonicalHost
{
    /**
     * @param string $appUrl the configured APP_URL
     * @param string $host the Host header of the request (with port, if any)
     * @param string $requestUri path and query of the request
     * @return string|null the URL to redirect to, or null when the request stays where it is
     */
    public static function redirectTarget(string $appUrl, string $host, string $method, string $requestUri, bool $forwardedHost = false): ?string
    {
        if ($forwardedHost || !in_array($method, ['GET', 'HEAD'], true) || $host === '') {
            return null;
        }
        $parts = parse_url($appUrl);
        if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return null;
        }
        $scheme = strtolower((string) $parts['scheme']);
        if (!in_array($scheme, ['http', 'https'], true)) {
            return null;
        }
        $port = isset($parts['port']) ? (int) $parts['port'] : null;
        $defaultPort = $scheme === 'https' ? 443 : 80;
        $authority = strtolower((string) $parts['host']) . (($port !== null && $port !== $defaultPort) ? ':' . $port : '');

        if (self::sameAuthority(strtolower($host), $authority, $scheme)) {
            return null;
        }
        // Only a path may follow: "//x" or a missing slash would otherwise change the host of the target.
        if ($requestUri === '' || $requestUri[0] !== '/' || str_starts_with($requestUri, '//') || preg_match('/[\x00-\x1F\x7F]/', $requestUri) === 1) {
            $requestUri = '/';
        }
        return $scheme . '://' . $authority . $requestUri;
    }

    /** "example.com" and "example.com:443" are the same address for an https site. */
    private static function sameAuthority(string $host, string $authority, string $scheme): bool
    {
        if ($host === $authority) {
            return true;
        }
        $default = $scheme === 'https' ? ':443' : ':80';
        return $host === $authority . $default;
    }
}
