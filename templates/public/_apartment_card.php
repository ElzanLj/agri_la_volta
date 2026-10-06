<?php
/**
 * @var array<string, mixed> $apartment
 * @var int|null $level heading level of the name (2 or 3), so headings never skip a level
 */
use App\Http\View;

$level = $level ?? 3;
?>
<article class="card">
    <?= View::capture('public/_photo', ['label' => (string) $apartment['name']]) ?>
    <div class="card-body">
        <h<?= $level ?>><a href="<?= e(lurl('apartment', ['slug' => (string) $apartment['slug']])) ?>"><?= e((string) $apartment['name']) ?></a></h<?= $level ?>>
        <?= View::capture('public/_facts', ['apartment' => $apartment]) ?>
        <p><a href="<?= e(lurl('apartment', ['slug' => (string) $apartment['slug']])) ?>"><?= e(t('apartments.discover', ['name' => (string) $apartment['name']])) ?></a></p>
    </div>
</article>
