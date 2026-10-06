<?php

declare(strict_types=1);

namespace App\Site;

/**
 * Fixed texts of the public site, one file per language (content/it.php, content/en.php).
 * There is no runtime machine translation: both files are written and reviewed by hand.
 * A missing key returns the key itself, which makes the gap obvious (and a test fails).
 */
final class Text
{
    /** @var array<string, array<string, string>> */
    private static array $loaded = [];

    /** @param array<string, string> $replace values are plain text; the caller escapes the result */
    public static function get(string $key, string $locale, array $replace = []): string
    {
        $text = self::all($locale)[$key] ?? $key;
        foreach ($replace as $name => $value) {
            $text = str_replace('{' . $name . '}', $value, $text);
        }
        return $text;
    }

    public static function has(string $key, string $locale): bool
    {
        return isset(self::all($locale)[$key]);
    }

    /** @return array<string, string> */
    public static function all(string $locale): array
    {
        $locale = in_array($locale, Locale::SUPPORTED, true) ? $locale : Locale::DEFAULT;
        return self::$loaded[$locale] ??= (static function () use ($locale): array {
            $texts = require BASE_PATH . '/content/' . $locale . '.php';
            return is_array($texts) ? $texts : [];
        })();
    }

    /** Message for a validation error code, with a generic fallback. */
    public static function error(string $code, string $locale): string
    {
        return self::has('err.' . $code, $locale) ? self::get('err.' . $code, $locale) : self::get('err.generic', $locale);
    }
}
