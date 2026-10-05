<?php

declare(strict_types=1);

namespace App\Http;

/**
 * Minimal router: exact paths with optional {param} segments ([a-z0-9-]+).
 */
final class Router
{
    /** @var list<array{string, string, callable}> method, regex, handler */
    private array $routes = [];

    /** @param callable(Request, array<string, string>): Response $handler */
    public function get(string $pattern, callable $handler): void
    {
        $this->routes[] = ['GET', $this->compile($pattern), $handler];
    }

    /** @param callable(Request, array<string, string>): Response $handler */
    public function post(string $pattern, callable $handler): void
    {
        $this->routes[] = ['POST', $this->compile($pattern), $handler];
    }

    public function dispatch(Request $request): Response
    {
        $method = $request->method === 'HEAD' ? 'GET' : $request->method;
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
            return View::error(405)->withHeader('Allow', implode(', ', array_unique($allowed)));
        }
        return View::error(404);
    }

    private function compile(string $pattern): string
    {
        return '#^' . preg_replace('#\{([a-z_]+)\}#', '(?P<$1>[a-z0-9-]+)', $pattern) . '$#';
    }
}
