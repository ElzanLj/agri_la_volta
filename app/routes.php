<?php

declare(strict_types=1);

use App\App;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\PageController;
use App\Http\Router;

return static function (Router $router, App $app): void {
    $pages = new PageController();
    $admin = new AdminController($app);

    $router->get('/', [$pages, 'home']);

    $router->get('/admin', [$admin, 'dashboard']);
    $router->get('/admin/login', [$admin, 'loginForm']);
    $router->post('/admin/login', [$admin, 'login']);
    $router->post('/admin/logout', [$admin, 'logout']);
};
