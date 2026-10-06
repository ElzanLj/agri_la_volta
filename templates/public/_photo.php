<?php
/**
 * Marked placeholder: no photograph is published until its origin and licence are verified.
 *
 * @var string $label  what the picture will show (the apartment name, for instance)
 * @var string|null $class
 */
?>
<div class="photo-placeholder <?= e($class ?? '') ?>" role="img" aria-label="<?= e(t('photo.alt', ['name' => $label])) ?>">
    <span><?= e(t('photo.placeholder')) ?></span>
</div>
