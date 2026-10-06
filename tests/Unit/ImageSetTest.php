<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Site\ImageSet;
use PHPUnit\Framework\TestCase;

/** Markup of responsive photographs (no photograph is registered yet; this is the contract for later). */
final class ImageSetTest extends TestCase
{
    private const IMAGE = ['base' => 'rosa', 'widths' => [1200, 480, 800], 'width' => 1200, 'height' => 800, 'alt' => 'Soggiorno dell’appartamento "Rosa"'];

    public function testBelowTheFoldImageIsLazyWithDimensionsAltAndWebpSource(): void
    {
        $html = ImageSet::picture(self::IMAGE);

        self::assertStringContainsString('<source type="image/webp"', $html);
        self::assertStringContainsString('rosa-480.webp', $html);
        self::assertStringContainsString('480w, ', $html);
        self::assertStringContainsString('rosa-1200.jpg', $html, 'the JPEG fallback is the largest variant');
        self::assertStringContainsString('width="1200" height="800"', $html);
        self::assertStringContainsString('loading="lazy"', $html);
        self::assertStringNotContainsString('fetchpriority', $html);
        self::assertStringContainsString('alt="Soggiorno dell’appartamento &quot;Rosa&quot;"', $html, 'the alt text is escaped');
    }

    public function testSourcesAreListedFromSmallestToLargest(): void
    {
        $html = ImageSet::picture(self::IMAGE);

        self::assertLessThan(strpos($html, 'rosa-800.webp'), strpos($html, 'rosa-480.webp'));
        self::assertLessThan(strpos($html, 'rosa-1200.webp'), strpos($html, 'rosa-800.webp'));
    }

    public function testTheHeroIsNeverLazyAndGetsHighPriority(): void
    {
        $html = ImageSet::picture(['eager' => true] + self::IMAGE);

        self::assertStringContainsString('fetchpriority="high"', $html);
        self::assertStringNotContainsString('loading="lazy"', $html);
    }

    public function testNoExternalUrlIsEverProduced(): void
    {
        self::assertStringNotContainsString('http', ImageSet::picture(self::IMAGE));
    }
}
