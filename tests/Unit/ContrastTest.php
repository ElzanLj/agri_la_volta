<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * WCAG 2.2 contrast of the public palette, computed from the colours actually defined in
 * public/assets/css/site.css (:root variables), so a palette change that breaks it fails here.
 */
final class ContrastTest extends TestCase
{
    /** @var array<string, string> */
    private static array $colors = [];

    public static function setUpBeforeClass(): void
    {
        $css = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/css/site.css');
        preg_match('/:root\s*\{(.*?)\n\}/s', $css, $root);
        preg_match_all('/--([a-z-]+):\s*(#[0-9a-fA-F]{3,6})\s*;/', $root[1] ?? '', $m, PREG_SET_ORDER);
        foreach ($m as $match) {
            self::$colors[$match[1]] = $match[2];
        }
    }

    private static function luminance(string $hex): float
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        $channels = array_map(static function (string $c): float {
            $v = hexdec($c) / 255;
            return $v <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4;
        }, str_split($hex, 2));
        return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
    }

    public static function ratio(string $a, string $b): float
    {
        $la = self::luminance($a);
        $lb = self::luminance($b);
        return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
    }

    private function color(string $name): string
    {
        self::assertArrayHasKey($name, self::$colors, "colour variable --$name is not defined in :root");
        return self::$colors[$name];
    }

    public function testRatioFunctionMatchesKnownValues(): void
    {
        self::assertEqualsWithDelta(21.0, self::ratio('#000000', '#ffffff'), 0.01);
        self::assertEqualsWithDelta(1.0, self::ratio('#777777', '#777777'), 0.001);
        self::assertEqualsWithDelta(4.48, self::ratio('#777777', '#ffffff'), 0.02, 'the classic #777 on white is just below AA');
    }

    /** @return array<string, array{string, string, float}> foreground, background, minimum ratio */
    public static function textPairs(): array
    {
        return [
            'body text on page' => ['color-text', 'color-bg', 4.5],
            'body text on white cards' => ['color-text', 'color-surface', 4.5],
            'muted text on page' => ['color-muted', 'color-bg', 4.5],
            'muted text on white' => ['color-muted', 'color-surface', 4.5],
            'muted text on notice' => ['color-muted', 'color-notice-bg', 4.5],
            'body text on notice' => ['color-text', 'color-notice-bg', 4.5],
            'placeholder text on its stripes (a)' => ['color-muted', 'color-placeholder', 4.5],
            'placeholder text on its stripes (b)' => ['color-muted', 'color-notice-bg', 4.5],
            'headings on page' => ['color-heading', 'color-bg', 4.5],
            'headings on white' => ['color-heading', 'color-surface', 4.5],
            'links on page' => ['color-primary-dark', 'color-bg', 4.5],
            'links on white' => ['color-primary-dark', 'color-surface', 4.5],
            'links on notice' => ['color-primary-dark', 'color-notice-bg', 4.5],
            'button text on green' => ['color-surface', 'color-primary', 4.5],
            'button text on dark green (hover)' => ['color-surface', 'color-primary-dark', 4.5],
            'secondary button text on white' => ['color-primary-dark', 'color-surface', 4.5],
            'error text on error background' => ['color-error', 'color-error-bg', 4.5],
            'error text on page' => ['color-error', 'color-bg', 4.5],
            'error text on white' => ['color-error', 'color-surface', 4.5],
            // Non-text elements (WCAG 1.4.11) need 3:1.
            'form field border on white' => ['color-muted', 'color-surface', 3.0],
            'focus outline on page' => ['color-focus', 'color-bg', 3.0],
            'focus outline on white' => ['color-focus', 'color-surface', 3.0],
            'focus outline on green button' => ['color-focus', 'color-primary', 1.0],
        ];
    }

    /** @dataProvider textPairs */
    public function testPaletteMeetsWcagAa(string $foreground, string $background, float $minimum): void
    {
        $ratio = self::ratio($this->color($foreground), $this->color($background));

        self::assertGreaterThanOrEqual($minimum, $ratio, sprintf('%s on %s is %.2f:1, needs %.1f:1', $foreground, $background, $ratio, $minimum));
    }

    public function testNoHardcodedLightTextOnTheLightPublicBackgrounds(): void
    {
        $css = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/css/site.css');
        $public = substr($css, (int) strpos($css, '/* === Public site'));

        preg_match_all('/(?<![-a-z])color:\s*(#[0-9a-fA-F]{3,6})/', $public, $m);
        self::assertSame([], $m[1], 'public styles must use the palette variables, which are contrast-checked: ' . implode(', ', $m[1]));
    }
}
