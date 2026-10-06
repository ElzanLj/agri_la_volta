<?php
/** @var list<array<string, mixed>> $apartments */
use App\Http\View;
?>
<h1><?= e(t('apartments.title')) ?></h1>
<?php if ($apartments === []): ?>
<p class="notice"><?= e(t('apartments.none')) ?></p>
<?php else: ?>
<div class="card-grid">
<?php foreach ($apartments as $apartment): ?>
    <?= View::capture('public/_apartment_card', ['apartment' => $apartment, 'level' => 2]) ?>
<?php endforeach; ?>
</div>
<?php endif; ?>
<p><a class="button" href="<?= e(lurl('request')) ?>"><?= e(t('nav.request')) ?></a></p>
