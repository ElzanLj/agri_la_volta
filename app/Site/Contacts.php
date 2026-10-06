<?php

declare(strict_types=1);

namespace App\Site;

use App\App;
use App\Config;
use App\Support\WhatsApp;

/**
 * Public contact details. They come from the environment (PUBLIC_PHONE, PUBLIC_EMAIL,
 * PUBLIC_ADDRESS, WHATSAPP_NUMBER) and are shown only when configured: no value is ever invented.
 */
final class Contacts
{
    public function __construct(
        public readonly string $phone,
        public readonly string $email,
        public readonly string $address,
        public readonly ?string $whatsappDigits,
    ) {
    }

    public static function fromConfig(Config $config): self
    {
        $email = trim($config->string('PUBLIC_EMAIL'));
        return new self(
            trim($config->string('PUBLIC_PHONE')),
            filter_var($email, FILTER_VALIDATE_EMAIL) === false ? '' : $email,
            trim($config->string('PUBLIC_ADDRESS')),
            WhatsApp::normalize($config->string('WHATSAPP_NUMBER'), $config->string('WHATSAPP_DEFAULT_COUNTRY_CODE', '39')),
        );
    }

    public static function current(): self
    {
        return self::fromConfig(App::current()->config);
    }

    public function isEmpty(): bool
    {
        return $this->phone === '' && $this->email === '' && $this->address === '' && $this->whatsappDigits === null;
    }

    /** "tel:" link target, or null when the number has no digits. */
    public function phoneHref(): ?string
    {
        $clean = preg_replace('/[^0-9+]/', '', $this->phone) ?? '';
        return preg_match('/^\+?[0-9]{6,15}\z/', $clean) ? 'tel:' . $clean : null;
    }

    /** Link to the address on Google Maps (a plain link, no embedded map, no API); null without an address. */
    public function mapsLink(): ?string
    {
        $oneLine = trim((string) preg_replace('/\s*[\r\n]+\s*/', ', ', $this->address));
        return $oneLine === '' ? null : 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($oneLine);
    }

    /** wa.me link with a pre-filled, editable message; null when no WhatsApp number is configured. */
    public function whatsappLink(string $message): ?string
    {
        return $this->whatsappDigits === null ? null : WhatsApp::link($this->whatsappDigits, $message);
    }
}
