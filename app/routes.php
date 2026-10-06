<?php

declare(strict_types=1);

use App\App;
use App\Http\Controllers\Site\RequestFlowController;
use App\Http\Controllers\Site\SeoController;
use App\Http\Controllers\Site\SiteController;
use App\Http\Request;
use App\Http\Router;
use App\Site\Locale;
use App\Site\Routes;

return static function (Router $router, App $app): void {
    $site = new SiteController($app);
    $flow = new RequestFlowController($app);

    $seo = new SeoController($app);
    $router->get('/robots.txt', [$seo, 'robots']);
    $router->get('/sitemap.xml', [$seo, 'sitemap']);

    // Public pages: every page exists once per language (Routes::PATHS holds the URLs).
    $pages = [
        'home' => [$site, 'home'],
        'farm' => [$site, 'farm'],
        'apartments' => [$site, 'apartments'],
        'around' => [$site, 'around'],
        'contact' => [$site, 'contact'],
        'privacy' => [$site, 'privacy'],
        'cookies' => [$site, 'cookies'],
        'request' => [$flow, 'search'],
        'request.apartments' => [$flow, 'apartments'],
        'request.details' => [$flow, 'details'],
        'request.received' => [$flow, 'received'],
    ];
    $posts = [
        'request.details' => [$flow, 'edit'],
        'request.summary' => [$flow, 'summary'],
        'request.submit' => [$flow, 'submit'],
    ];

    foreach (Locale::SUPPORTED as $locale) {
        foreach ($pages as $key => $handler) {
            $router->get(Routes::path($key, $locale), static fn (Request $r): \App\Http\Response => $handler($r, $locale));
        }
        foreach ($posts as $key => $handler) {
            $router->post(Routes::path($key, $locale), static fn (Request $r): \App\Http\Response => $handler($r, $locale));
        }
        $router->get(Routes::path('apartment', $locale), static fn (Request $r, array $params): \App\Http\Response => $site->apartment($r, $locale, $params));
    }

    (require __DIR__ . '/routes_admin.php')($router, $app);
};
