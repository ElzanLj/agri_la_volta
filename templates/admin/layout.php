<?php
/**
 * Admin layout: never indexed, not linked from the public navigation.
 *
 * @var string $content
 * @var string $title
 * @var bool $loggedIn
 */
?>
<!doctype html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e($title) ?> · La Volta</title>
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
    </header>
    <main id="main" class="container" tabindex="-1">
<?= $content ?>
    </main>
</body>
</html>
