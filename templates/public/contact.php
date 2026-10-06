<?php
use App\Site\Contacts;
use App\Support\WhatsApp;

$contacts = Contacts::current();
$whatsapp = $contacts->whatsappLink(WhatsApp::businessMessage(\App\Site\Locale::current()));
$phoneHref = $contacts->phoneHref();
?>
<h1><?= e(t('contact.title')) ?></h1>
<?php if ($contacts->isEmpty()): ?>
<p class="notice"><?= e(t('contact.pending')) ?></p>
<?php else: ?>
<dl class="details">
<?php if ($contacts->phone !== ''): ?>
    <dt><?= e(t('contact.phone')) ?></dt>
    <dd><?php if ($phoneHref !== null): ?><a href="<?= e($phoneHref) ?>"><?= e($contacts->phone) ?></a><?php else: ?><?= e($contacts->phone) ?><?php endif; ?></dd>
<?php endif; ?>
<?php if ($contacts->email !== ''): ?>
    <dt><?= e(t('contact.email')) ?></dt>
    <dd><a href="mailto:<?= e($contacts->email) ?>"><?= e($contacts->email) ?></a></dd>
<?php endif; ?>
<?php if ($contacts->address !== ''): ?>
    <dt><?= e(t('contact.address')) ?></dt>
    <dd><?= nl2br(e($contacts->address)) ?></dd>
<?php endif; ?>
</dl>
<?php if ($contacts->mapsLink() !== null): ?>
<p><a href="<?= e($contacts->mapsLink()) ?>" target="_blank" rel="noopener noreferrer"><?= e(t('contact.map')) ?></a></p>
<?php endif; ?>
<?php if ($whatsapp !== null): ?>
<p><a class="button button-secondary" href="<?= e($whatsapp) ?>" target="_blank" rel="noopener noreferrer"><?= e(t('contact.whatsapp_cta')) ?></a> <?= e(t('contact.whatsapp_hint')) ?></p>
<?php endif; ?>
<?php endif; ?>
<p><a class="button" href="<?= e(lurl('request')) ?>"><?= e(t('nav.request')) ?></a></p>
