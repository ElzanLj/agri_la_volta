<?php

declare(strict_types=1);

namespace App\Http\Controllers\Site;

use App\Http\Request;
use App\Http\Response;
use App\Repository\ApartmentRepository;

/** Informational pages: the content comes from content/<lang>.php and from the apartments table. */
final class SiteController extends SitePage
{
    public function home(Request $request, string $locale): Response
    {
        return $this->render('home', $locale, 'home', [
            'title' => 'Agriturismo La Volta',
            'description' => \App\Site\Text::get('home.description', $locale),
            'apartments' => (new ApartmentRepository($this->app->db()))->listPublic($locale),
        ]);
    }

    public function farm(Request $request, string $locale): Response
    {
        return $this->staticPage('farm', 'farm', $locale);
    }

    public function around(Request $request, string $locale): Response
    {
        return $this->staticPage('around', 'around', $locale);
    }

    public function contact(Request $request, string $locale): Response
    {
        return $this->staticPage('contact', 'contact', $locale);
    }

    public function privacy(Request $request, string $locale): Response
    {
        return $this->staticPage('privacy', 'privacy', $locale);
    }

    public function cookies(Request $request, string $locale): Response
    {
        return $this->staticPage('cookies', 'cookies', $locale);
    }

    public function apartments(Request $request, string $locale): Response
    {
        return $this->render('apartments', $locale, 'apartments', [
            'title' => \App\Site\Text::get('apartments.title', $locale),
            'description' => \App\Site\Text::get('apartments.description', $locale),
            'apartments' => (new ApartmentRepository($this->app->db()))->listPublic($locale),
        ]);
    }

    /** @param array<string, string> $params */
    public function apartment(Request $request, string $locale, array $params): Response
    {
        $row = (new ApartmentRepository($this->app->db()))->findPublicBySlug((string) ($params['slug'] ?? ''), $locale);
        if ($row === null) {
            return $this->notFound($locale);
        }
        return $this->render('apartment', $locale, 'apartment', [
            'title' => (string) (($row['meta_title'] ?? '') !== '' ? $row['meta_title'] : $row['name']),
            'description' => (string) ($row['meta_description'] ?? ''),
            'apartment' => $row,
            'crumbLabel' => (string) $row['name'],
            'structuredData' => [$this->apartmentStructuredData($row, $locale)],
        ], ['slug' => (string) $row['slug']]);
    }

    /**
     * schema.org Apartment with ONLY what the admin has entered: nothing is invented, and there are
     * no prices, ratings or addresses here.
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function apartmentStructuredData(array $row, string $locale): array
    {
        $base = rtrim($this->app->config->string('APP_URL'), '/');
        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'Apartment',
            'name' => (string) $row['name'],
            'url' => $base . \App\Site\Routes::path('apartment', $locale, ['slug' => (string) $row['slug']]),
            'inLanguage' => $locale,
        ];
        if ((string) ($row['description'] ?? '') !== '') {
            $data['description'] = (string) $row['description'];
        }
        if ($row['max_guests'] !== null) {
            $data['occupancy'] = ['@type' => 'QuantitativeValue', 'maxValue' => (int) $row['max_guests']];
        }
        if ($row['bedrooms'] !== null) {
            $data['numberOfBedrooms'] = (int) $row['bedrooms'];
        }
        $amenities = \App\Site\Amenities::lines($row['amenities'] ?? null);
        if ($amenities !== []) {
            $data['amenityFeature'] = array_map(static fn (string $name): array => ['@type' => 'LocationFeatureSpecification', 'name' => $name, 'value' => true], $amenities);
        }
        return $data;
    }

    private function staticPage(string $template, string $key, string $locale): Response
    {
        return $this->render($template, $locale, $key, [
            'title' => \App\Site\Text::get($key . '.title', $locale),
            'description' => \App\Site\Text::get($key . '.description', $locale),
        ]);
    }
}
