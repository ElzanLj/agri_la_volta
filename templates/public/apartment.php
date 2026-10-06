<?php
/** @var array<string, mixed> $apartment */
use App\Http\View;
use App\Site\Contacts;
use App\Support\WhatsApp;

$name = (string) $apartment['name'];
$contacts = Contacts::current();
$whatsapp = $contacts->whatsappLink(WhatsApp::businessMessage(\App\Site\Locale::current(), $name));
$time = static fn (?string $value): ?string => $value === null ? null : substr($value, 0, 5);
$checkIn = $time($apartment['check_in_from']);
$checkInUntil = $time($apartment['check_in_until']);
$checkOut = $time($apartment['check_out_until']);
?>
<nav class="breadcrumb" aria-label="<?= e(t('nav.breadcrumb')) ?>">
    <a href="<?= e(lurl('home')) ?>"><?= e(t('nav.home')) ?></a> ›
    <a href="<?= e(lurl('apartments')) ?>"><?= e(t('apartments.title')) ?></a> ›
    <span aria-current="page"><?= e($name) ?></span>
</nav>

<h1><?= e($name) ?></h1>
<?= View::capture('public/_photo', ['label' => $name, 'class' => 'photo-wide']) ?>
<?= View::capture('public/_facts', ['apartment' => $apartment]) ?>

<?php if (($apartment['description'] ?? '') !== ''): ?>
<section aria-labelledby="apt-description">
    <h2 id="apt-description"><?= e(t('apartment.description')) ?></h2>
    <p><?= nl2br(e((string) $apartment['description'])) ?></p>
</section>
<?php endif; ?>

<?php if ($checkIn !== null || $checkOut !== null): ?>
<section aria-labelledby="apt-times">
    <h2 id="apt-times"><?= e(t('apartment.times')) ?></h2>
    <ul class="plain-list">
<?php if ($checkIn !== null): ?>
        <li><?= e($checkInUntil !== null ? t('apartment.check_in_between', ['from' => $checkIn, 'to' => $checkInUntil]) : t('apartment.check_in_from', ['from' => $checkIn])) ?></li>
<?php endif; ?>
<?php if ($checkOut !== null): ?>
        <li><?= e(t('apartment.check_out_until', ['to' => $checkOut])) ?></li>
<?php endif; ?>
    </ul>
</section>
<?php endif; ?>

<?php if (($apartment['rules'] ?? '') !== ''): ?>
<section aria-labelledby="apt-rules">
    <h2 id="apt-rules"><?= e(t('apartment.rules')) ?></h2>
    <p><?= nl2br(e((string) $apartment['rules'])) ?></p>
</section>
<?php endif; ?>

<section class="cta" aria-labelledby="apt-cta">
    <h2 id="apt-cta"><?= e(t('apartment.cta_title')) ?></h2>
<?php if (!(int) $apartment['accepts_online_requests']): ?>
    <p class="notice"><?= e(t('apartment.no_online')) ?></p>
<?php endif; ?>
    <p>
<?php if ((int) $apartment['accepts_online_requests']): ?>
        <a class="button" href="<?= e(lurl('request')) ?>"><?= e(t('nav.request')) ?></a>
<?php endif; ?>
<?php if ($whatsapp !== null): ?>
        <a class="button button-secondary" href="<?= e($whatsapp) ?>" target="_blank" rel="noopener noreferrer"><?= e(t('contact.whatsapp_cta')) ?></a>
<?php endif; ?>
    </p>
</section>
