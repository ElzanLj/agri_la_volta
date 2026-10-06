<?php

declare(strict_types=1);

namespace App\Http\Controllers\Site;

use App\App;
use App\Http\Response;
use App\Http\View;
use App\Site\Locale;
use App\Site\Routes;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Shared helpers for the public pages. Public pages never start a session (no cookie for
 * anonymous visitors); the forms are protected by App\Site\FormToken instead.
 */
abstract class SitePage
{
    public function __construct(protected App $app)
    {
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, string> $routeParams parameters of the route, e.g. ['slug' => 'rosa']
     */
    protected function render(string $template, string $locale, string $routeKey, array $data = [], array $routeParams = [], int $status = 200): Response
    {
        Locale::set($locale);
        $alternates = [];
        foreach (Locale::SUPPORTED as $other) {
            $alternates[$other] = Routes::path($routeKey, $other, $routeParams);
        }

        $response = View::render('public/' . $template, $data + [
            'lang' => $locale,
            'routeKey' => $routeKey,
            'canonicalPath' => Routes::path($routeKey, $locale, $routeParams),
            'alternates' => $alternates,
        ], $status);

        // Pages with forms or personal data must never be stored by shared caches.
        return !empty($data['private']) ? $response->withHeader('Cache-Control', 'no-store') : $response;
    }

    protected function notFound(string $locale): Response
    {
        Locale::set($locale);
        return View::error(404);
    }

    protected function redirect(string $routeKey, string $locale, array $query = []): Response
    {
        return Response::redirect(Routes::url($routeKey, $locale, [], $query));
    }

    protected function today(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone(date_default_timezone_get())))->format('Y-m-d');
    }
}
