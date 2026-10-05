<?php

declare(strict_types=1);

namespace App\Http;

/**
 * Minimal router: exact paths with optional {param} segments ([a-z0-9-]+), plus prefix guards.
 *
 * A guard is a middleware `callable(Request, callable $next): Response` bound to a path prefix.
 * Guards run for EVERY request under the prefix, including unknown URLs, before routing: a
 * forgotten per-route check is therefore impossible (default deny). Guards run in the order
 * they were added; a route can be exempted from a guard explicitly ("GET /admin/login").
 */
final class Router
{
    /** @var list<array{string, string, callable, string}> method, regex, handler, pattern */
    private array $routes = [];

    /** @var list<array{string, callable, list<string>}> prefix, middleware, exempt "METHOD /path" entries */
    private array $guards = [];

    /** @param callable(Request, array<string, string>): Response $handler */
    public function get(string $pattern, callable $handler): void
    {
        $this->routes[] = ['GET', $this->compile($pattern), $handler, $pattern];
    }

    /** @param callable(Request, array<string, string>): Response $handler */
    public function post(string $pattern, callable $handler): void
    {
        $this->routes[] = ['POST', $this->compile($pattern), $handler, $pattern];
    }

    /**
     * @param callable(Request, callable): Response $middleware
     * @param list<string> $except exact requests exempt from this guard, e.g. "GET /admin/login"
     */
    public function guard(string $prefix, callable $middleware, array $except = []): void
    {
        $this->guards[] = [rtrim($prefix, '/'), $middleware, $except];
    }

    /** @return list<array{method: string, pattern: string}> */
    public function routes(): array
    {
        return array_map(static fn (array $r): array => ['method' => $r[0], 'pattern' => $r[3]], $this->routes);
    }

    public function dispatch(Request $request): Response
    {
        $handler = fn (Request $r): Response => $this->route($r);

        foreach (array_reverse($this->guards) as [$prefix, $middleware, $except]) {
            if (!self::isUnder($request->path, $prefix) || in_array($request->method . ' ' . $request->path, $except, true)) {
                continue;
            }
            $next = $handler;
            $handler = static fn (Request $r): Response => $middleware($r, $next);
        }

        return $handler($request);
    }

    private function route(Request $request): Response
    {
        $method = $request->method === 'HEAD' ? 'GET' : $request->method;
        $layout = self::isUnder($request->path, '/admin') ? 'admin/layout' : 'layout';
        $allowed = [];

        foreach ($this->routes as [$routeMethod, $regex, $handler]) {
            if (!preg_match($regex, $request->path, $matches)) {
                continue;
            }
            if ($routeMethod !== $method) {
                $allowed[] = $routeMethod;
                continue;
            }
            $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
            return $handler($request, $params);
        }

        if ($allowed !== []) {
            return View::error(405, $layout)->withHeader('Allow', implode(', ', array_unique($allowed)));
        }
        return View::error(404, $layout);
    }

    private static function isUnder(string $path, string $prefix): bool
    {
        return $path === $prefix || str_starts_with($path, $prefix . '/');
    }

    private function compile(string $pattern): string
    {
        return '#^' . preg_replace('#\{([a-z_]+)\}#', '(?P<$1>[a-z0-9-]+)', $pattern) . '$#';
    }
}
