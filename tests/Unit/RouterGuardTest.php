<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Http\Request;
use App\Http\Response;
use App\Http\Router;
use PHPUnit\Framework\TestCase;

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__, 2));
}

/** The default-deny guard mechanism of the router, with fake guards and handlers (no HTTP, no DB). */
final class RouterGuardTest extends TestCase
{
    /** @var list<string> */
    private array $trace = [];

    private function guard(string $name, ?int $stopWith = null): callable
    {
        return function (Request $request, callable $next) use ($name, $stopWith): Response {
            $this->trace[] = $name;
            return $stopWith === null ? $next($request) : new Response("stopped by $name", $stopWith);
        };
    }

    private function router(): Router
    {
        $router = new Router();
        $handler = function (string $name): callable {
            return function () use ($name): Response {
                $this->trace[] = 'handler:' . $name;
                return new Response($name);
            };
        };
        $router->get('/', $handler('home'));
        $router->get('/admin', $handler('admin-home'));
        $router->get('/admin/login', $handler('login'));
        $router->post('/admin/login', $handler('login-post'));
        $router->get('/admin/items/{id}', $handler('item'));
        $router->post('/admin/items/{id}/delete', $handler('delete'));
        $router->get('/administrator', $handler('public-look-alike'));
        return $router;
    }

    private function dispatch(Router $router, string $method, string $path): Response
    {
        $this->trace = [];
        return $router->dispatch(new Request($method, $path));
    }

    public function testGuardsRunBeforeEveryAdminHandlerInOrder(): void
    {
        $router = $this->router();
        $router->guard('/admin', $this->guard('first'));
        $router->guard('/admin', $this->guard('second'));

        $response = $this->dispatch($router, 'GET', '/admin/items/5');

        self::assertSame('item', $response->body);
        self::assertSame(['first', 'second', 'handler:item'], $this->trace);
    }

    public function testAGuardThatRefusesStopsTheHandler(): void
    {
        $router = $this->router();
        $router->guard('/admin', $this->guard('auth', 401));

        $response = $this->dispatch($router, 'POST', '/admin/items/5/delete');

        self::assertSame(401, $response->status);
        self::assertSame(['auth'], $this->trace, 'the handler must never run');
    }

    public function testGuardsAlsoCoverUnknownUrlsAndWrongMethods(): void
    {
        $router = $this->router();
        $router->guard('/admin', $this->guard('auth', 401));

        foreach ([['GET', '/admin/nothing-here'], ['DELETE', '/admin/items/5'], ['GET', '/admin/items/5/delete'], ['POST', '/admin/../admin']] as [$method, $path]) {
            $response = $this->dispatch($router, $method, $path);
            self::assertSame(401, $response->status, "$method $path");
            self::assertSame(['auth'], $this->trace);
        }
    }

    public function testAnExemptRouteSkipsOnlyThatGuard(): void
    {
        $router = $this->router();
        $router->guard('/admin', $this->guard('auth', 401), except: ['GET /admin/login', 'POST /admin/login']);
        $router->guard('/admin', $this->guard('csrf'));

        $login = $this->dispatch($router, 'POST', '/admin/login');
        self::assertSame('login-post', $login->body);
        self::assertSame(['csrf', 'handler:login-post'], $this->trace, 'login is exempt from auth but not from the other guard');

        self::assertSame(401, $this->dispatch($router, 'GET', '/admin')->status);
        self::assertSame(401, $this->dispatch($router, 'GET', '/admin/login/')->status, 'only the exact exempt request is exempt');
    }

    public function testTheExemptionIsPerMethod(): void
    {
        $router = $this->router();
        $router->guard('/admin', $this->guard('auth', 401), except: ['GET /admin/login']);

        self::assertSame('login', $this->dispatch($router, 'GET', '/admin/login')->body);
        self::assertSame(401, $this->dispatch($router, 'POST', '/admin/login')->status);
    }

    public function testPathsThatOnlyLookLikeTheAdminPrefixAreNotGuarded(): void
    {
        $router = $this->router();
        $router->guard('/admin', $this->guard('auth', 401));

        $response = $this->dispatch($router, 'GET', '/administrator');

        self::assertSame('public-look-alike', $response->body);
        self::assertSame(['handler:public-look-alike'], $this->trace);
        self::assertSame('home', $this->dispatch($router, 'GET', '/')->body);
    }

    public function testHeadIsTreatedAsGetAndOtherMethodsGetAllowHeader(): void
    {
        $router = $this->router();

        self::assertSame('login', $this->dispatch($router, 'HEAD', '/admin/login')->body);
        $wrong = $this->dispatch($router, 'DELETE', '/admin/login');
        self::assertSame(405, $wrong->status);
        self::assertSame('GET, POST', $wrong->headers['Allow']);
        self::assertSame(404, $this->dispatch($router, 'GET', '/nope')->status);
    }

    public function testRoutesAreListedForAudits(): void
    {
        $routes = $this->router()->routes();

        self::assertContains(['method' => 'POST', 'pattern' => '/admin/items/{id}/delete'], $routes);
        self::assertCount(7, $routes);
    }
}
