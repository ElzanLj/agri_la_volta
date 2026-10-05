<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Request;
use App\Http\Response;

/** Outermost admin guard: every admin response, errors and redirects included, is private. */
final class PrivateResponse
{
    public function __invoke(Request $request, callable $next): Response
    {
        return $next($request)
            ->withHeader('Cache-Control', 'no-store')
            ->withHeader('Pragma', 'no-cache')
            ->withHeader('X-Robots-Tag', 'noindex, nofollow');
    }
}
