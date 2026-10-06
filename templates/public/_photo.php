<?php
/**
 * A photograph, or a clearly marked placeholder while no verified photograph exists.
 *
 * @var string $label  what the picture shows (the apartment name, for instance)
 * @var string|null $class
 * @var array{base: string, widths: list<int>, width: int, height: int, alt: string, eager?: bool, sizes?: string}|null $image
 *      Optional image set: files public/assets/img/<base>-<w>.webp and <base>-<w>.jpg for every width.
 */
use App\Site\ImageSet;

$image = $image ?? null;
?>
<?php if ($image !== null): ?>
<?= ImageSet::picture($image, $class ?? '') ?>
<?php else: ?>
<div class="photo-placeholder <?= e($class ?? '') ?>" role="img" aria-label="<?= e(t('photo.alt', ['name' => $label])) ?>">
    <span><?= e(t('photo.placeholder')) ?></span>
</div>
<?php endif; ?>
