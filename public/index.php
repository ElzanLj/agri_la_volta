<?php

declare(strict_types=1);

use App\Http\Request;
use App\Http\Response;
use App\Http\Router;
use App\Http\View;

/** @var App\App $app */
$app = require dirname(__DIR__) . '/app/bootstrap.php';

const MAX_REQUEST_BYTES = 1048576;

$request = Request::fromGlobals($app->basePath());

try {
    if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > MAX_REQUEST_BYTES) {
        $response = View::error(413);
    } elseif ($request->method === 'GET' && $request->path !== '/' && str_ends_with($request->path, '/')) {
        // One canonical URL per page: no trailing slash.
        $response = Response::redirect(url(rtrim($request->path, '/')), 301);
    } else {
        $router = new Router();
        (require BASE_PATH . '/app/routes.php')($router, $app);
        $response = $router->dispatch($request);
    }
} catch (Throwable $e) {
    $app->logger->error('Unhandled exception', ['exception' => $e, 'path' => $request->path]);
    try {
        $response = $app->config->isDebug()
            ? Response::html('<pre>' . e((string) $e) . '</pre>', 500)
            : View::error(500);
    } catch (Throwable) {
        $response = new Response('Errore del server.', 500, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}

$response->send($request->method !== 'HEAD');
