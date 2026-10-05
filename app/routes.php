<?php

declare(strict_types=1);

use App\App;
use App\Http\Controllers\PageController;
use App\Http\Router;

return static function (Router $router, App $app): void {
    $pages = new PageController();

    $router->get('/', [$pages, 'home']);

    (require __DIR__ . '/routes_admin.php')($router, $app);
};
