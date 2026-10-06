<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\App;
use App\Http\Request;
use App\Http\Response;
use App\Http\View;
use App\Security\Csrf;
use App\Security\OriginCheck;

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
        if (!Csrf::isValid($request->input('_csrf')) || !OriginCheck::allowed($request, $this->app)) {
            $this->app->logger->warning('CSRF check failed', ['path' => $request->path]);
            return View::error(403, 'admin/layout');
        }
        return $next($request);
    }
}
