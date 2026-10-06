<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Service\PricingConfigService;
use App\Site\Routes;

/**
 * Helpers for tests of the public site: fictitious apartment data (never real prices), the
 * request flow walked step by step as a browser would, and form parsing.
 */
abstract class PublicSiteTestCase extends HttpTestCase
{
    protected const CUSTOMER = [
        'first_name' => 'Mario',
        'last_name' => 'Rossi',
        'email' => 'mario.rossi@example.test',
        'phone' => '+39 333 1234567',
        'notes' => '',
    ];

    protected static function path(string $key, string $locale = 'it', array $params = []): string
    {
        return Routes::path($key, $locale, $params);
    }

    /** Arrival and departure far enough ahead to be valid whatever day the tests run. @return array{string, string} */
    protected function stay(int $startInDays = 90, int $nights = 4): array
    {
        $in = new \DateTimeImmutable('today +' . $startInDays . ' days');
        return [$in->format('Y-m-d'), $in->modify('+' . $nights . ' days')->format('Y-m-d')];
    }

    /** Capacity and a fictitious price list ([TEST] data) for one apartment around the given stay. */
    protected function equip(string $slug = 'margherita', int $maxGuests = 4, ?array $stay = null, int $nightlyCents = 10000): int
    {
        $id = $this->apartmentId($slug);
        $this->db->prepare('UPDATE apartments SET max_guests = ? WHERE id = ?')->execute([$maxGuests, $id]);
        if ($stay !== null) {
            $start = (new \DateTimeImmutable($stay[0]))->modify('-30 days')->format('Y-m-d');
            $end = (new \DateTimeImmutable($stay[1]))->modify('+30 days')->format('Y-m-d');
            (new PricingConfigService($this->db))->createRate(PricingFixtures::rateInput($id, '[TEST] Periodo', $start, $end, $nightlyCents));
        }
        return $id;
    }

    /** @param array<string, string> $over @return array<string, string> */
    protected function searchParams(string $in, string $out, array $over = []): array
    {
        return $over + ['check_in' => $in, 'check_out' => $out, 'adults' => '2', 'children' => '0', 'pets' => '0'];
    }

    /** The signed form token of the first public form on the page. */
    protected function formToken(string $html): string
    {
        self::assertSame(1, preg_match('/name="_form" value="([^"]+)"/', $html, $m), 'the page has no form token');
        return $m[1];
    }

    /** Hidden inputs of the page as the browser would send them back. @return array<string, string> */
    protected function hiddenFields(string $html): array
    {
        preg_match_all('/<input type="hidden" name="([^"]+)" value="([^"]*)">/', $html, $matches, PREG_SET_ORDER);
        $fields = [];
        foreach ($matches as $m) {
            $fields[html_entity_decode($m[1], ENT_QUOTES)] = html_entity_decode($m[2], ENT_QUOTES);
        }
        return $fields;
    }

    /** Walks steps 1-3 as a browser and returns the response of the summary step. @param array<string, string> $customer */
    protected function toSummary(HttpClient $client, string $slug, string $in, string $out, array $customer = self::CUSTOMER, string $locale = 'it'): HttpResponse
    {
        $query = $this->searchParams($in, $out);
        $list = $client->get(self::path('request.apartments', $locale) . '?' . http_build_query($query));
        self::assertSame(200, $list->status);

        $details = $client->get(self::path('request.details', $locale) . '?' . http_build_query($query + ['apartment' => $slug]));
        self::assertSame(200, $details->status, 'the details step should open for an available apartment');

        return $client->post(self::path('request.summary', $locale), $this->hiddenFields($details->body) + $customer);
    }

    /** Sends the request from a summary page. @param array<string, string> $extra */
    protected function sendFromSummary(HttpClient $client, HttpResponse $summary, array $extra = ['privacy_accepted' => '1'], string $locale = 'it'): HttpResponse
    {
        self::assertSame(200, $summary->status, 'the summary step should open');
        return $client->post(self::path('request.submit', $locale), $this->hiddenFields($summary->body) + $extra);
    }

    protected function requestCount(): int
    {
        return $this->countRows('booking_requests');
    }
}
