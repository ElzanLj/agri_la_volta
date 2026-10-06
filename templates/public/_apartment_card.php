<?php
/** @var array<string, mixed> $apartment */
use App\Http\View;
?>
<article class="card">
    <?= View::capture('public/_photo', ['label' => (string) $apartment['name']]) ?>
    <div class="card-body">
        <h3><a href="<?= e(lurl('apartment', ['slug' => (string) $apartment['slug']])) ?>"><?= e((string) $apartment['name']) ?></a></h3>
        <?= View::capture('public/_facts', ['apartment' => $apartment]) ?>
        <p><a href="<?= e(lurl('apartment', ['slug' => (string) $apartment['slug']])) ?>"><?= e(t('apartments.discover', ['name' => (string) $apartment['name']])) ?></a></p>
    </div>
</article>
