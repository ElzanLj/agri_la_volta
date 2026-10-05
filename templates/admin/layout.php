<?php
/**
 * Admin layout: never indexed, not linked from the public navigation. No JavaScript.
 *
 * @var string $content
 * @var string $title
 * @var bool|null $loggedIn
 * @var list<array{type: string, message: string}>|null $flash
 */
$loggedIn = $loggedIn ?? false;
$flash = $flash ?? [];
$nav = [
    '/admin' => 'Home',
    '/admin/richieste' => 'Richieste',
    '/admin/prenotazioni' => 'Prenotazioni',
    '/admin/calendario' => 'Calendario',
    '/admin/blocchi' => 'Blocchi',
    '/admin/appartamenti' => 'Appartamenti',
    '/admin/listino' => 'Listino',
    '/admin/storico' => 'Storico',
    '/admin/export' => 'Export',
];
?>
<!doctype html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e($title ?? 'Amministrazione') ?> · La Volta</title>
    <link rel="stylesheet" href="<?= e(asset('css/site.css')) ?>">
</head>
<body class="admin">
    <a class="skip-link" href="#main">Salta al contenuto</a>
    <header class="site-header">
        <div class="container header-row">
            <span class="brand">La Volta · Amministrazione</span>
<?php if ($loggedIn): ?>
            <form method="post" action="<?= e(url('/admin/logout')) ?>">
                <?= csrf_field() ?>
                <button type="submit" class="button button-secondary">Esci</button>
            </form>
<?php endif; ?>
        </div>
<?php if ($loggedIn): ?>
        <nav class="admin-nav" aria-label="Amministrazione">
            <ul class="container">
<?php foreach ($nav as $path => $label): ?>
                <li><a href="<?= e(url($path)) ?>"><?= e($label) ?></a></li>
<?php endforeach; ?>
            </ul>
        </nav>
<?php endif; ?>
    </header>
    <main id="main" class="container" tabindex="-1">
<?php foreach ($flash as $message): ?>
        <p class="alert <?= $message['type'] === 'error' ? 'alert-error' : 'alert-ok' ?>" role="<?= $message['type'] === 'error' ? 'alert' : 'status' ?>"><?= e($message['message']) ?></p>
<?php endforeach; ?>
<?= $content ?>
    </main>
</body>
</html>
