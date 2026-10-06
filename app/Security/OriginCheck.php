<?php

declare(strict_types=1);

namespace App\Security;

use App\App;
use App\Http\Request;

/**
 * When the browser sends Origin (or Referer) on a state-changing request, it must be this site.
 * Without either header the token alone decides. Shared by the admin and the public forms.
 */
final class OriginCheck
{
    public static function allowed(Request $request, App $app): bool
    {
        $origin = $request->header('Origin');
        $source = $origin !== '' ? $origin : $request->header('Referer');
        if ($source === '') {
            return true; // no browser hint: the token alone decides
        }
        if ($source === 'null') {
            return false; // opaque origin (sandboxed or cross-site contexts)
        }

        $parts = parse_url($source);
        if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return false;
        }
        return self::normalise($parts) === self::expected($request, $app);
    }

    private static function expected(Request $request, App $app): string
    {
        $parts = parse_url($app->config->string('APP_URL'));
        if (is_array($parts) && !empty($parts['scheme']) && !empty($parts['host'])) {
            return self::normalise($parts);
        }
        // APP_URL not configured: fall back to the Host header.
        $host = strtolower($request->header('Host'));
        return $host === '' ? '' : 'http://' . $host;
    }

    /** @param array<string, mixed> $parts */
    private static function normalise(array $parts): string
    {
        $scheme = strtolower((string) $parts['scheme']);
        $host = strtolower((string) $parts['host']);
        $port = isset($parts['port']) ? (int) $parts['port'] : null;
        $default = $scheme === 'https' ? 443 : 80;

        return $scheme . '://' . $host . (($port !== null && $port !== $default) ? ':' . $port : '');
    }
}
