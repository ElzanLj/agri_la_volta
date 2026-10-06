<?php

declare(strict_types=1);

namespace App\Http\Controllers\Site;

use App\Http\Request;
use App\Http\Response;
use App\Site\Seo;

/** robots.txt and sitemap.xml: plain documents, no session, no cookie. */
final class SeoController extends SitePage
{
    public function robots(Request $request): Response
    {
        return new Response(
            Seo::robots($this->baseUrl(), $this->app->basePath()),
            200,
            ['Content-Type' => 'text/plain; charset=UTF-8', 'Cache-Control' => 'public, max-age=3600'],
        );
    }

    public function sitemap(Request $request): Response
    {
        return new Response(
            Seo::sitemap($this->app->db(), $this->baseUrl()),
            200,
            ['Content-Type' => 'application/xml; charset=UTF-8', 'Cache-Control' => 'public, max-age=3600'],
        );
    }

    private function baseUrl(): string
    {
        return rtrim($this->app->config->string('APP_URL'), '/');
    }
}
