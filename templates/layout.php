<?php
/**
 * Public layout (Italian and English).
 *
 * @var string $content
 * @var string|null $title
 * @var string|null $description
 * @var bool|null $noindex
 * @var string|null $lang
 * @var string|null $routeKey
 * @var string|null $canonicalPath
 * @var array<string, string>|null $alternates language => path (without installation prefix)
 * @var list<array<string, mixed>>|null $structuredData extra schema.org blocks built from real data
 * @var list<array{string, ?string}>|null $crumbs breadcrumb: [label, url or null for the current page]
 */

use App\Site\Contacts;
use App\Site\Locale;
use App\Site\Routes;

$lang = $lang ?? Locale::current();
$siteName = 'Agriturismo La Volta';
$pageTitle = (($title ?? '') === '' || $title === $siteName) ? $siteName : $title . ' – ' . $siteName;
$contacts = Contacts::current();
$baseUrl = rtrim(\App\App::current()->config->string('APP_URL'), '/');
$other = Locale::other($lang);
$switchPath = $alternates[$other] ?? Routes::path('home', $other);
$nav = ['home', 'farm', 'apartments', 'around', 'contact'];
$whatsapp = $contacts->whatsappLink(\App\Support\WhatsApp::businessMessage($lang));
$phoneHref = $contacts->phoneHref();
$crumbs = $crumbs ?? [];
$canonicalUrl = !empty($canonicalPath) ? $baseUrl . $canonicalPath : null;
$jsonFlags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
$structured = [];
if ($crumbs !== []) {
    $items = [];
    foreach ($crumbs as $i => [$label, $href]) {
        $items[] = ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $label] + ($href !== null ? ['item' => $baseUrl . $href] : []);
    }
    $structured[] = ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $items];
}
if (($routeKey ?? null) === 'home' && !$contacts->isEmpty() && ($contacts->phone !== '' || $contacts->email !== '' || $contacts->address !== '')) {
    // Only details the owner has actually configured; nothing is invented.
    $structured[] = array_filter([
        '@context' => 'https://schema.org',
        '@type' => 'LodgingBusiness',
        'name' => $siteName,
        'url' => $canonicalUrl,
        'telephone' => $contacts->phone ?: null,
        'email' => $contacts->email ?: null,
        'address' => $contacts->address ?: null,
    ], static fn ($v): bool => $v !== null);
}
$structured = array_merge($structured, $structuredData ?? []);
?>
<!doctype html>
<html lang="<?= e($lang) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?></title>
<?php if (!empty($description)): ?>
    <meta name="description" content="<?= e($description) ?>">
<?php endif; ?>
<?php if (!empty($noindex)): ?>
    <meta name="robots" content="noindex">
<?php elseif (!empty($canonicalPath)): ?>
    <link rel="canonical" href="<?= e($baseUrl . $canonicalPath) ?>">
<?php foreach ($alternates ?? [] as $altLang => $altPath): ?>
    <link rel="alternate" hreflang="<?= e($altLang) ?>" href="<?= e($baseUrl . $altPath) ?>">
<?php endforeach; ?>
<?php if (isset($alternates['it'])): ?>
    <link rel="alternate" hreflang="x-default" href="<?= e($baseUrl . $alternates['it']) ?>">
<?php endif; ?>
<?php endif; ?>
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="<?= e($siteName) ?>">
    <meta property="og:title" content="<?= e($pageTitle) ?>">
<?php if (!empty($description)): ?>
    <meta property="og:description" content="<?= e($description) ?>">
<?php endif; ?>
<?php if ($canonicalUrl !== null && empty($noindex)): ?>
    <meta property="og:url" content="<?= e($canonicalUrl) ?>">
<?php endif; ?>
    <meta property="og:locale" content="<?= $lang === 'en' ? 'en_GB' : 'it_IT' ?>">
    <meta property="og:locale:alternate" content="<?= $lang === 'en' ? 'it_IT' : 'en_GB' ?>">
    <meta name="twitter:card" content="summary">
    <link rel="icon" href="<?= e(asset('favicon.svg')) ?>" type="image/svg+xml">
    <link rel="stylesheet" href="<?= e(asset('css/site.css')) ?>">
<?php foreach ($structured as $data): ?>
    <script type="application/ld+json"><?= json_encode($data, $jsonFlags) ?></script>
<?php endforeach; ?>
</head>
<body>
    <a class="skip-link" href="#main"><?= e(t('a11y.skip')) ?></a>
    <header class="site-header">
        <div class="container header-row">
            <a class="brand" href="<?= e(Routes::url('home', $lang)) ?>"><?= e($siteName) ?></a>
            <nav class="site-nav" aria-label="<?= e(t('nav.label')) ?>">
                <ul>
<?php foreach ($nav as $key): ?>
                    <li><a href="<?= e(Routes::url($key, $lang)) ?>"<?= ($routeKey ?? null) === $key ? ' aria-current="page"' : '' ?>><?= e(t('nav.' . $key)) ?></a></li>
<?php endforeach; ?>
                    <li><a href="<?= e(url($switchPath)) ?>" lang="<?= e($other) ?>" hreflang="<?= e($other) ?>"><?= e(t('nav.switch')) ?></a></li>
                </ul>
            </nav>
            <a class="button" href="<?= e(Routes::url('request', $lang)) ?>"><?= e(t('nav.request')) ?></a>
        </div>
    </header>
    <main id="main" class="container" tabindex="-1">
<?php if ($crumbs !== []): ?>
        <nav class="breadcrumb" aria-label="<?= e(t('nav.breadcrumb')) ?>">
            <ol>
<?php foreach ($crumbs as [$label, $href]): ?>
<?php if ($href !== null): ?>
                <li><a href="<?= e(url($href)) ?>"><?= e($label) ?></a></li>
<?php else: ?>
                <li><span aria-current="page"><?= e($label) ?></span></li>
<?php endif; ?>
<?php endforeach; ?>
            </ol>
        </nav>
<?php endif; ?>
<?= $content ?>
    </main>
    <footer class="site-footer">
        <div class="container footer-grid">
            <div>
                <p><strong><?= e($siteName) ?></strong></p>
<?php if ($contacts->address !== ''): ?>
                <p><?= nl2br(e($contacts->address)) ?></p>
<?php endif; ?>
            </div>
<?php if (!$contacts->isEmpty()): ?>
            <div>
                <p><strong><?= e(t('footer.contacts')) ?></strong></p>
                <ul class="plain-list">
<?php if ($contacts->phone !== ''): ?>
                    <li><?= e(t('contact.phone')) ?>: <?php if ($phoneHref !== null): ?><a href="<?= e($phoneHref) ?>"><?= e($contacts->phone) ?></a><?php else: ?><?= e($contacts->phone) ?><?php endif; ?></li>
<?php endif; ?>
<?php if ($contacts->email !== ''): ?>
                    <li><?= e(t('contact.email')) ?>: <a href="mailto:<?= e($contacts->email) ?>"><?= e($contacts->email) ?></a></li>
<?php endif; ?>
<?php if ($whatsapp !== null): ?>
                    <li><a href="<?= e($whatsapp) ?>" target="_blank" rel="noopener noreferrer"><?= e(t('contact.whatsapp_cta')) ?></a></li>
<?php endif; ?>
                </ul>
            </div>
<?php endif; ?>
            <div>
                <p><a href="<?= e(Routes::url('request', $lang)) ?>"><?= e(t('nav.request')) ?></a></p>
                <p><a href="<?= e(Routes::url('privacy', $lang)) ?>"><?= e(t('nav.privacy')) ?></a> · <a href="<?= e(Routes::url('cookies', $lang)) ?>"><?= e(t('nav.cookies')) ?></a></p>
            </div>
        </div>
    </footer>
</body>
</html>
