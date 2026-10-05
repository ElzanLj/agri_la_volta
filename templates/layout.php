<?php
/**
 * Public layout.
 *
 * @var string $content
 * @var string|null $title
 * @var string|null $description
 * @var bool|null $noindex
 * @var string|null $lang
 */
?>
<!doctype html>
<html lang="<?= e($lang ?? 'it') ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? 'Agriturismo La Volta') ?></title>
<?php if (!empty($description)): ?>
    <meta name="description" content="<?= e($description) ?>">
<?php endif; ?>
<?php if (!empty($noindex)): ?>
    <meta name="robots" content="noindex">
<?php endif; ?>
    <link rel="stylesheet" href="<?= e(asset('css/site.css')) ?>">
</head>
<body>
    <a class="skip-link" href="#main">Salta al contenuto</a>
    <header class="site-header">
        <div class="container">
            <a class="brand" href="<?= e(url('/')) ?>">Agriturismo La Volta</a>
        </div>
    </header>
    <main id="main" class="container" tabindex="-1">
<?= $content ?>
    </main>
    <footer class="site-footer">
        <div class="container">
            <p>Agriturismo La Volta</p>
        </div>
    </footer>
</body>
</html>
