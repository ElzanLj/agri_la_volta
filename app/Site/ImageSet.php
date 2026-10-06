<?php

declare(strict_types=1);

namespace App\Site;

/**
 * Markup for a responsive photograph: WebP sources with a JPEG fallback, explicit width and height
 * (no layout shift), lazy loading below the fold and high priority for the hero. Files follow
 * public/assets/img/<base>-<width>.webp and .jpg (made by bin/optimize-images.php).
 *
 * No photograph is registered yet (their origin is not verified): pages render placeholders.
 */
final class ImageSet
{
    private const DEFAULT_SIZES = '(min-width: 70rem) 70rem, 100vw';

    /**
     * @param array{base: string, widths: list<int>, width: int, height: int, alt: string, eager?: bool, sizes?: string} $image
     */
    public static function picture(array $image, string $class = ''): string
    {
        $widths = $image['widths'];
        sort($widths);
        $largest = (int) end($widths);
        $srcset = static fn (string $ext): string => implode(', ', array_map(
            static fn (int $w): string => asset('img/' . $image['base'] . '-' . $w . '.' . $ext) . ' ' . $w . 'w',
            $widths,
        ));
        $sizes = $image['sizes'] ?? self::DEFAULT_SIZES;
        $eager = !empty($image['eager']);

        return '<picture class="photo ' . e($class) . '">'
            . '<source type="image/webp" srcset="' . e($srcset('webp')) . '" sizes="' . e($sizes) . '">'
            . '<img src="' . e(asset('img/' . $image['base'] . '-' . $largest . '.jpg')) . '"'
            . ' srcset="' . e($srcset('jpg')) . '" sizes="' . e($sizes) . '"'
            . ' width="' . (int) $image['width'] . '" height="' . (int) $image['height'] . '"'
            . ' alt="' . e($image['alt']) . '"'
            . ($eager ? ' fetchpriority="high"' : ' loading="lazy" decoding="async"')
            . '></picture>';
    }
}
