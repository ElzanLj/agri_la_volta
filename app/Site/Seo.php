<?php

declare(strict_types=1);

namespace App\Site;

use App\Repository\ApartmentRepository;
use PDO;

/** robots.txt and sitemap.xml, built from the URL table and the active apartments (never hand-written). */
final class Seo
{
    /** Pages that belong in the sitemap. The request steps are noindex and are left out. */
    private const SITEMAP_PAGES = ['home', 'farm', 'apartments', 'around', 'contact', 'privacy', 'cookies'];

    /** @param string $baseUrl APP_URL without trailing slash; $prefix the installation sub-path ("" at the root) */
    public static function robots(string $baseUrl, string $prefix): string
    {
        $lines = ['User-agent: *'];
        $lines[] = 'Disallow: ' . $prefix . '/admin';
        foreach (Locale::SUPPORTED as $locale) {
            $lines[] = 'Disallow: ' . $prefix . Routes::path('request', $locale) . '/';
        }
        $lines[] = '';
        $lines[] = 'Sitemap: ' . $baseUrl . '/sitemap.xml';
        return implode("\n", $lines) . "\n";
    }

    public static function sitemap(PDO $db, string $baseUrl): string
    {
        $entries = [];
        foreach (self::SITEMAP_PAGES as $key) {
            $entries[] = [$key, []];
        }
        foreach ((new ApartmentRepository($db))->listPublic('it') as $apartment) {
            $entries[] = ['apartment', ['slug' => (string) $apartment['slug']]];
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">' . "\n";
        foreach ($entries as [$key, $params]) {
            foreach (Locale::SUPPORTED as $locale) {
                $xml .= "  <url>\n    <loc>" . self::esc($baseUrl . Routes::path($key, $locale, $params)) . "</loc>\n";
                foreach (Locale::SUPPORTED as $alt) {
                    $xml .= '    <xhtml:link rel="alternate" hreflang="' . $alt . '" href="' . self::esc($baseUrl . Routes::path($key, $alt, $params)) . "\"/>\n";
                }
                $xml .= '    <xhtml:link rel="alternate" hreflang="x-default" href="' . self::esc($baseUrl . Routes::path($key, 'it', $params)) . "\"/>\n  </url>\n";
            }
        }
        return $xml . "</urlset>\n";
    }

    private static function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
