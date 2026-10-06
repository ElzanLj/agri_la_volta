<?php

declare(strict_types=1);

/*
 * Makes the responsive variants of VERIFIED photographs (owner-supplied or properly licensed).
 *
 *   php bin/optimize-images.php <source.jpg> <base-name> [width ...]
 *
 * Writes public/assets/img/<base-name>-<width>.webp and .jpg for every width (default 480 800 1200 1600),
 * never upscaling and never touching the original. Needs PHP with GD and WebP support.
 * Do NOT run it on photographs whose origin is uncertain: see docs/IMAGES.md.
 */

if (PHP_SAPI !== 'cli') {
    exit(1);
}
if (!function_exists('imagewebp') || !function_exists('imagecreatefromjpeg')) {
    fwrite(STDERR, "This script needs PHP with GD and WebP support (extension gd).\n");
    exit(1);
}

$source = $argv[1] ?? '';
$base = $argv[2] ?? '';
$widths = array_map('intval', array_slice($argv, 3)) ?: [480, 800, 1200, 1600];

if (!is_file($source) || !preg_match('/^[a-z0-9-]{1,60}$/', $base)) {
    fwrite(STDERR, "Usage: php bin/optimize-images.php <source.jpg> <base-name (a-z, 0-9, -)> [width ...]\n");
    exit(1);
}

$info = getimagesize($source);
$image = match ($info[2] ?? 0) {
    IMAGETYPE_JPEG => imagecreatefromjpeg($source),
    IMAGETYPE_PNG => imagecreatefrompng($source),
    IMAGETYPE_WEBP => imagecreatefromwebp($source),
    default => false,
};
if ($image === false || !$info) {
    fwrite(STDERR, "Unsupported or unreadable image.\n");
    exit(1);
}

[$width, $height] = $info;
$outDir = dirname(__DIR__) . '/public/assets/img';
if (!is_dir($outDir) && !mkdir($outDir, 0755, true) && !is_dir($outDir)) {
    fwrite(STDERR, "Cannot create $outDir\n");
    exit(1);
}

foreach (array_unique($widths) as $target) {
    if ($target < 1 || $target > $width) {
        continue; // never upscale
    }
    $scaled = imagescale($image, $target);
    if ($scaled === false) {
        continue;
    }
    imagewebp($scaled, "$outDir/$base-$target.webp", 80);
    imagejpeg($scaled, "$outDir/$base-$target.jpg", 82);
    printf("%s-%d (%dx%d)\n", $base, $target, $target, (int) round($height * $target / $width));
}
