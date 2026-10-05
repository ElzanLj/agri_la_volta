<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\App;
use App\Http\Request;
use App\Http\Response;
use App\Http\View;
use App\Security\Csrf;

/**
 * CSRF guard for every non-safe method. Two independent checks must both pass:
 * 1. the `_csrf` form field equals the session token (never read from the query string);
 * 2. when the browser sends Origin (or Referer), it must be this site.
 * Failure answers 403 and the handler never runs.
 */
final class VerifyCsrf
{
    public function __construct(private App $app)
    {
    }

    public function __invoke(Request $request, callable $next): Response
    {
        if ($request->isSafeMethod()) {
            return $next($request);
        }
        if (!Csrf::isValid($request->input('_csrf')) || !$this->originAllowed($request)) {
            $this->app->logger->warning('CSRF check failed', ['path' => $request->path]);
            return View::error(403, 'admin/layout');
        }
        return $next($request);
    }

    private function originAllowed(Request $request): bool
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
        return self::normalise($parts) === $this->expectedOrigin($request);
    }

    private function expectedOrigin(Request $request): string
    {
        $parts = parse_url($this->app->config->string('APP_URL'));
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
