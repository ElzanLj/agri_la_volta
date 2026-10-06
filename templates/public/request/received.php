<?php
/** @var string|null $reference */
?>
<h1><?= e(t('flow.received.title')) ?></h1>
<p class="alert alert-ok" role="status"><?= e(t('flow.received.lead')) ?></p>
<?php if ($reference !== null): ?>
<p><?= e(t('flow.received.reference')) ?>: <strong><?= e($reference) ?></strong></p>
<?php endif; ?>
<p><?= e(t('flow.received.next')) ?></p>
<p><?= e(t('flow.not_a_booking')) ?></p>
<p><a class="button" href="<?= e(lurl('home')) ?>"><?= e(t('error.home')) ?></a></p>
