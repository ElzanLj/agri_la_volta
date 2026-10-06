<?php
/**
 * Facts of an apartment. Only what the admin has filled in is shown.
 *
 * @var array<string, mixed> $apartment
 */
use App\Domain\Money;

$facts = [];
if ($apartment['max_guests'] !== null) {
    $facts[] = t('fact.guests', ['n' => (string) (int) $apartment['max_guests']]);
}
if ($apartment['bedrooms'] !== null) {
    $facts[] = t((int) $apartment['bedrooms'] === 1 ? 'fact.bedroom' : 'fact.bedrooms', ['n' => (string) (int) $apartment['bedrooms']]);
}
if ($apartment['beds'] !== null) {
    $facts[] = t((int) $apartment['beds'] === 1 ? 'fact.bed' : 'fact.beds', ['n' => (string) (int) $apartment['beds']]);
}
if ($apartment['indicative_price_cents'] !== null) {
    $facts[] = t('fact.price_from', ['price' => Money::format((int) $apartment['indicative_price_cents'], \App\Site\Locale::current())]);
}
?>
<?php if ($facts !== []): ?>
<ul class="facts">
<?php foreach ($facts as $fact): ?>
    <li><?= e($fact) ?></li>
<?php endforeach; ?>
</ul>
<?php endif; ?>
