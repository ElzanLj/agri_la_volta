<?php
/** @var string $messageKey */
?>
<h1><?= e(t('flow.error.title')) ?></h1>
<p class="alert alert-error" role="alert"><?= e(t($messageKey)) ?></p>
<p><a class="button" href="<?= e(lurl('request')) ?>"><?= e(t('form.change_search')) ?></a>
   <a class="button button-secondary" href="<?= e(lurl('contact')) ?>"><?= e(t('contact.title')) ?></a></p>
