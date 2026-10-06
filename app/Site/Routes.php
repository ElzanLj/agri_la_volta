<?php

declare(strict_types=1);

namespace App\Site;

/**
 * The public URL table: one entry per page, one path per language. Templates and controllers
 * never write a public path by hand; they ask for a route key. {slug} marks the apartment.
 */
final class Routes
{
    public const PATHS = [
        'home' => ['it' => '/', 'en' => '/en'],
        'farm' => ['it' => '/agriturismo', 'en' => '/en/the-farm'],
        'apartments' => ['it' => '/appartamenti', 'en' => '/en/apartments'],
        'apartment' => ['it' => '/appartamenti/{slug}', 'en' => '/en/apartments/{slug}'],
        'around' => ['it' => '/dintorni', 'en' => '/en/around-us'],
        'request' => ['it' => '/richiedi-disponibilita', 'en' => '/en/request-availability'],
        'request.apartments' => ['it' => '/richiedi-disponibilita/appartamenti', 'en' => '/en/request-availability/apartments'],
        'request.details' => ['it' => '/richiedi-disponibilita/dati', 'en' => '/en/request-availability/details'],
        'request.summary' => ['it' => '/richiedi-disponibilita/riepilogo', 'en' => '/en/request-availability/summary'],
        'request.submit' => ['it' => '/richiedi-disponibilita/invia', 'en' => '/en/request-availability/submit'],
        'request.received' => ['it' => '/richiedi-disponibilita/ricevuta', 'en' => '/en/request-availability/received'],
        'contact' => ['it' => '/contatti', 'en' => '/en/contact'],
        'privacy' => ['it' => '/privacy', 'en' => '/en/privacy'],
        'cookies' => ['it' => '/cookie', 'en' => '/en/cookies'],
    ];

    /** Path without the installation prefix. @param array<string, string> $params */
    public static function path(string $key, string $locale, array $params = []): string
    {
        $path = self::PATHS[$key][$locale] ?? self::PATHS[$key]['it'];
        foreach ($params as $name => $value) {
            $path = str_replace('{' . $name . '}', rawurlencode($value), $path);
        }
        return $path;
    }

    /** @param array<string, string> $params @param array<string, string> $query */
    public static function url(string $key, string $locale, array $params = [], array $query = []): string
    {
        $url = url(self::path($key, $locale, $params));
        return $query === [] ? $url : $url . '?' . http_build_query($query);
    }
}
