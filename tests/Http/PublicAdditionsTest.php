<?php

declare(strict_types=1);

namespace Tests\Http;

use Tests\Support\HttpClient;
use Tests\Support\PublicSiteTestCase;
use Tests\Support\TestServer;

/**
 * Final-review additions: apartment amenities (SPEC §4), WhatsApp with the stay in the request
 * flow (§19), a Google Maps link (§25) and schema.org data for apartments (§27).
 */
final class PublicAdditionsTest extends PublicSiteTestCase
{
    /** @return list<array<string, mixed>> */
    private function jsonLd(string $html): array
    {
        preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $m);
        return array_map(static fn (string $j): array => json_decode($j, true, 512, JSON_THROW_ON_ERROR), $m[1]);
    }

    /** @return array<string, mixed> */
    private function apartmentBlock(string $html): array
    {
        $blocks = array_values(array_filter($this->jsonLd($html), static fn (array $b): bool => $b['@type'] === 'Apartment'));
        self::assertCount(1, $blocks, 'exactly one Apartment block');
        return $blocks[0];
    }

    // === Amenities (G1) ===================================================

    public function testTheAdminEditsAmenitiesAndThePublicPageListsThemInEachLanguage(): void
    {
        $admin = $this->loggedInClient();
        $id = $this->apartmentId('rosa');
        $form = $admin->get('/admin/appartamenti/' . $id);
        self::assertStringContainsString('name="amenities_it"', $form->body);
        self::assertStringContainsString('name="amenities_en"', $form->body);

        $fields = [
            'name' => 'Rosa', 'is_active' => '1', 'accepts_online_requests' => '1', 'management_mode' => 'direct', 'sort_order' => '3',
            'amenities_it' => "Piscina\nPosto auto\n<b>Barbecue</b>", 'amenities_en' => "Pool\nParking",
            '_csrf' => $admin->get('/admin/appartamenti/' . $id)->csrfToken(),
        ];
        $response = $admin->post('/admin/appartamenti/' . $id, $fields);
        self::assertSame(303, $response->status);

        $visitor = $this->client();
        $it = $visitor->get(self::path('apartment', 'it', ['slug' => 'rosa']))->body;
        self::assertStringContainsString('<h2 id="apt-amenities">Servizi</h2>', $it);
        self::assertSame(3, substr_count($it, '<li>Piscina</li>') + substr_count($it, '<li>Posto auto</li>') + substr_count($it, '<li>&lt;b&gt;Barbecue&lt;/b&gt;</li>'));
        self::assertStringNotContainsString('<b>Barbecue</b>', $it, 'amenities are escaped');
        $en = $visitor->get(self::path('apartment', 'en', ['slug' => 'rosa']))->body;
        self::assertStringContainsString('<h2 id="apt-amenities">Amenities</h2>', $en);
        self::assertStringContainsString('<li>Pool</li>', $en);
        self::assertStringNotContainsString('Piscina', $en, 'each language shows its own list');

        // The edit form shows them again.
        self::assertStringContainsString('Posto auto', $admin->get('/admin/appartamenti/' . $id)->body);
    }

    public function testNoAmenitiesSectionWithoutAmenities(): void
    {
        $body = $this->client()->get(self::path('apartment', 'it', ['slug' => 'viola']))->body;

        self::assertStringNotContainsString('apt-amenities', $body);
        self::assertStringNotContainsString('Servizi', $body);
        self::assertArrayNotHasKey('amenityFeature', $this->apartmentBlock($body));
    }

    public function testAdminRejectsInvalidAmenitiesAndKeepsWhatWasTyped(): void
    {
        $admin = $this->loggedInClient();
        $id = $this->apartmentId('rosa');
        $long = str_repeat('x', 150);

        $response = $admin->post('/admin/appartamenti/' . $id, [
            'name' => 'Rosa', 'is_active' => '1', 'accepts_online_requests' => '1', 'management_mode' => 'direct', 'sort_order' => '3',
            'amenities_it' => $long, '_csrf' => $admin->get('/admin/appartamenti/' . $id)->csrfToken(),
        ]);

        self::assertSame(422, $response->status);
        self::assertStringContainsString('Servizi non validi', $response->body);
        self::assertStringContainsString($long, $response->body);
        self::assertSame(0, $this->countRows('apartment_translations'));
    }

    // === schema.org Apartment (G4) ===========================================

    public function testApartmentStructuredDataHoldsOnlyWhatTheAdminEntered(): void
    {
        $bare = $this->apartmentBlock($this->client()->get(self::path('apartment', 'it', ['slug' => 'mimosa']))->body);
        self::assertSame(['@context', '@type', 'name', 'url', 'inLanguage'], array_keys($bare), 'nothing is invented for an empty apartment');
        self::assertSame('Mimosa', $bare['name']);

        $id = $this->apartmentId('mimosa');
        $this->db->prepare('UPDATE apartments SET max_guests = 5, bedrooms = 2, indicative_price_cents = 9000 WHERE id = ?')->execute([$id]);
        $this->db->prepare("INSERT INTO apartment_translations (apartment_id, locale, description, amenities) VALUES (?, 'it', 'Testo </script> descrizione', ?)")->execute([$id, "Piscina\nTerrazzo"]);

        $html = $this->client()->get(self::path('apartment', 'it', ['slug' => 'mimosa']))->body;
        $full = $this->apartmentBlock($html);

        self::assertSame(['@type' => 'QuantitativeValue', 'maxValue' => 5], $full['occupancy']);
        self::assertSame(2, $full['numberOfBedrooms']);
        self::assertSame('Testo </script> descrizione', $full['description']);
        self::assertSame(['Piscina', 'Terrazzo'], array_column($full['amenityFeature'], 'name'));
        self::assertMatchesRegularExpression('#^http://127\.0\.0\.1:\d+/appartamenti/mimosa$#', $full['url']);
        $json = json_encode($full);
        self::assertStringNotContainsString('9000', $json, 'no price in structured data');
        self::assertStringNotContainsString('price', strtolower($json));
        self::assertStringNotContainsString('rating', strtolower($json));
        self::assertDoesNotMatchRegularExpression('#<script(?![^>]*ld\+json)#', $html);

        $english = $this->apartmentBlock($this->client()->get(self::path('apartment', 'en', ['slug' => 'mimosa']))->body);
        self::assertSame('en', $english['inLanguage']);
        self::assertArrayNotHasKey('description', $english, 'the English text is empty, so it is omitted');
    }

    // === Maps and WhatsApp in the flow (G3, G2) ===============================

    public function testMapsLinkAndWhatsAppWithTheStayAppearOnlyWhenConfigured(): void
    {
        [$in, $out] = $this->stay();
        $this->equip('margherita', 4, [$in, $out]);
        $this->db->exec("UPDATE apartments SET accepts_online_requests = 0 WHERE slug <> 'margherita'");
        $query = http_build_query($this->searchParams($in, $out, ['children' => '1']));

        // Nothing configured: no maps link, no WhatsApp button.
        $plain = $this->client();
        self::assertStringNotContainsString('google.com/maps', $plain->get(self::path('contact'))->body);
        $list = $plain->get(self::path('request.apartments') . '?' . $query)->body;
        self::assertStringNotContainsString('wa.me', $list);
        self::assertStringNotContainsString('Chiedi su WhatsApp', $list);

        $server = TestServer::start(['PUBLIC_ADDRESS' => "Via Esempio 1\n43000 Parma", 'WHATSAPP_NUMBER' => '393331234567']);
        try {
            $client = new HttpClient($server->baseUrl());

            $contact = $client->get(self::path('contact'))->body;
            self::assertStringContainsString('href="https://www.google.com/maps/search/?api=1&amp;query=Via%20Esempio%201%2C%2043000%20Parma"', $contact);
            self::assertStringContainsString('rel="noopener noreferrer"', $contact);
            self::assertStringContainsString('Apri la posizione su Google Maps', $contact);
            self::assertStringContainsString('Open the location on Google Maps', $client->get(self::path('contact', 'en'))->body);

            $flow = $client->get(self::path('request.apartments') . '?' . $query)->body;
            self::assertStringContainsString('Preferisci scriverci? Chiedi su WhatsApp', $flow);
            preg_match_all('#href="(https://wa\.me/393331234567\?text=[^"]+)"#', $flow, $m);
            self::assertNotEmpty($m[1]);
            $text = urldecode(substr($m[1][0], strpos($m[1][0], 'text=') + 5));
            self::assertStringContainsString('Margherita', $text, 'the apartment is named');
            self::assertStringContainsString((new \DateTimeImmutable($in))->format('d/m'), $text);
            self::assertStringContainsString((new \DateTimeImmutable($out))->format('d/m'), $text);
            self::assertStringContainsString('2 adulti', $text);
            self::assertStringContainsString('1 bambino', $text);
            self::assertStringContainsString('rel="noopener noreferrer"', $flow);

            // No apartment available: the general WhatsApp button (without an apartment name) is offered.
            $none = $client->get(self::path('request.apartments', 'en') . '?' . http_build_query($this->searchParams($in, $out, ['adults' => '9'])))->body;
            self::assertStringContainsString('Ask on WhatsApp', $none);
            self::assertStringContainsString('wa.me/393331234567?text=Hello', $none);
        } finally {
            $server->stop();
        }
    }
}
