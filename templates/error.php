<?php
/**
 * @var string $title
 * @var string $message
 * @var bool|null $isAdminError
 */
$isAdminError = $isAdminError ?? false;
?>
<h1><?= e($title) ?></h1>
<p><?= e($message) ?></p>
<?php if ($isAdminError): ?>
<p><a href="<?= e(url('/admin')) ?>">Vai all'area amministrativa</a></p>
<?php else: ?>
<p><a href="<?= e(url('/')) ?>">Torna alla pagina iniziale</a></p>
<?php endif; ?>
