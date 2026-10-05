<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\App;
use App\Http\Request;
use App\Http\Response;
use App\Http\View;
use App\Security\AdminAuth;

/**
 * Authorization guard. Anonymous visitors get a redirect to the login page on GET/HEAD and a
 * 401 on anything else; in both cases the requested action is never executed.
 */
final class RequireAdmin
{
    public function __construct(private App $app)
    {
    }

    public function __invoke(Request $request, callable $next): Response
    {
        if ((new AdminAuth($this->app->db()))->isAuthenticated()) {
            return $next($request);
        }

        if ($request->method === 'GET' || $request->method === 'HEAD') {
            return Response::redirect(url('/admin/login'));
        }
        return View::error(401, 'admin/layout');
    }
}
