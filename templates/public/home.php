<?php
/** @var list<array<string, mixed>> $apartments */
use App\Http\View;
?>
<section class="hero">
    <?= View::capture('public/_photo', ['label' => 'Agriturismo La Volta', 'class' => 'photo-hero']) ?>
    <div class="hero-text">
        <h1>Agriturismo La Volta</h1>
        <p class="lead"><?= e(t('home.lead')) ?></p>
        <p><a class="button" href="<?= e(lurl('request')) ?>"><?= e(t('nav.request')) ?></a>
           <a class="button button-secondary" href="<?= e(lurl('apartments')) ?>"><?= e(t('home.see_apartments')) ?></a></p>
    </div>
</section>

<?php if ($apartments !== []): ?>
<section aria-labelledby="home-apartments">
    <h2 id="home-apartments"><?= e(t('apartments.title')) ?></h2>
    <div class="card-grid">
<?php foreach ($apartments as $apartment): ?>
        <?= View::capture('public/_apartment_card', ['apartment' => $apartment]) ?>
<?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<section aria-labelledby="home-more">
    <h2 id="home-more"><?= e(t('home.more')) ?></h2>
    <ul class="plain-list">
        <li><a href="<?= e(lurl('farm')) ?>"><?= e(t('farm.title')) ?></a></li>
        <li><a href="<?= e(lurl('around')) ?>"><?= e(t('around.title')) ?></a></li>
        <li><a href="<?= e(lurl('contact')) ?>"><?= e(t('contact.title')) ?></a></li>
    </ul>
</section>
