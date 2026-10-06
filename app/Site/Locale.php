<?php

declare(strict_types=1);

namespace App\Site;

/** Language of the current request: IT without prefix, EN under /en (decision P5). */
final class Locale
{
    public const SUPPORTED = ['it', 'en'];
    public const DEFAULT = 'it';

    private static string $current = self::DEFAULT;

    public static function current(): string
    {
        return self::$current;
    }

    public static function set(string $locale): void
    {
        self::$current = in_array($locale, self::SUPPORTED, true) ? $locale : self::DEFAULT;
    }

    public static function fromPath(string $path): string
    {
        return ($path === '/en' || str_starts_with($path, '/en/')) ? 'en' : 'it';
    }

    public static function other(string $locale): string
    {
        return $locale === 'en' ? 'it' : 'en';
    }
}
