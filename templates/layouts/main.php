<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5">
    <meta name="theme-color" content="#031D4F">
    <meta name="google-site-verification" content="Dn1_wOUr7-Ojh72hy0SrNWLwUT-VD_30kfe8-lvRa40">
    <?php
    $pageTitle = (string) ($htmlTitle ?? (($title ?? setting('clinic_name', config('app.name'))) . ' | ' . setting('clinic_name_en', 'Sarva Dental Clinic')));
    $pageDescription = trim((string) ($metaDescription ?? ''));
    if ($pageDescription === '') {
        $pageDescription = (string) setting(
            'meta_description',
            'کلینیک دندانپزشکی سروا — خدمات تخصصی دندانپزشکی، ایمپلنت، ارتودنسی و زیبایی لبخند با تیمی مجرب در محیطی مدرن و امن.'
        );
    }
    $pageCanonical = (string) ($canonical ?? url($_SERVER['REQUEST_URI'] ?? '/'));
    // Strip query string from canonical for cleaner SEO (keep path only).
    $canonPath = parse_url($pageCanonical, PHP_URL_PATH) ?: '/';
    $pageCanonical = url($canonPath === '' ? '/' : $canonPath);
    $ogType = (string) ($ogType ?? 'website');
    $preloadImage = $preloadImage ?? null;
    ?>
    <meta name="description" content="<?= e($pageDescription) ?>">
    <?php if (!empty($robotsMeta)): ?>
    <meta name="robots" content="<?= e($robotsMeta) ?>">
    <?php endif; ?>
    <link rel="canonical" href="<?= e($pageCanonical) ?>">
    <meta name="author" content="Sarva Dental Clinic">
    <meta property="og:title" content="<?= e($ogTitle ?? $title ?? setting('clinic_name')) ?>">
    <meta property="og:description" content="<?= e($ogDescription ?? $pageDescription) ?>">
    <meta property="og:url" content="<?= e($pageCanonical) ?>">
    <meta property="og:locale" content="fa_IR">
    <meta property="og:type" content="<?= e($ogType) ?>">
    <meta property="og:site_name" content="<?= e(setting('clinic_name', 'کلینیک دندانپزشکی سروا')) ?>">
    <?php
    $ogImage = $ogImage ?? setting('og_default_image', 'images/about-us-image.jpg');
    ?>
    <?php if (!empty($ogImage)): ?>
    <meta property="og:image" content="<?= e(media_url($ogImage)) ?>">
    <?php endif; ?>
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= e($ogTitle ?? $title ?? setting('clinic_name')) ?>">
    <meta name="twitter:description" content="<?= e($ogDescription ?? $pageDescription) ?>">
    <title><?= e($pageTitle) ?></title>
    <link rel="shortcut icon" type="image/x-icon" href="<?= media_url(setting('favicon_path', 'images/favicon.png')) ?>">
    <link rel="icon" type="image/png" href="<?= media_url(setting('favicon_path', 'images/favicon.png')) ?>">
    <link rel="apple-touch-icon" href="<?= media_url(setting('favicon_path', 'images/favicon.png')) ?>">
    <?php if (!empty($preloadImage)): ?>
    <link rel="preload" as="image" href="<?= e(media_url((string) $preloadImage)) ?>" fetchpriority="high">
    <?php endif; ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="<?= asset('css/bootstrap.min.css') ?>" rel="stylesheet">
    <link href="<?= asset('css/all.min.css') ?>" rel="stylesheet">
    <link href="<?= asset('css/custom.css') ?>" rel="stylesheet">
    <link href="<?= asset('css/rtl.css') ?>" rel="stylesheet">
    <?php
    // Non-critical CSS — load async so first paint is not blocked.
    $deferredCss = array_merge(
        ['css/slicknav.min.css', 'css/animate.css', 'css/magnific-popup.css', 'css/twentytwenty.css'],
        array_map(static fn ($c) => ltrim((string) $c, '/'), (array) ($extraCss ?? []))
    );
    foreach (array_unique($deferredCss) as $cssFile):
        $href = asset($cssFile);
    ?>
    <link rel="stylesheet" href="<?= e($href) ?>" media="print" onload="this.media='all'">
    <noscript><link rel="stylesheet" href="<?= e($href) ?>"></noscript>
    <?php endforeach; ?>
    <?php if (!empty($structuredData) && is_array($structuredData)): ?>
    <script type="application/ld+json"><?= json_encode($structuredData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
    <?php endif; ?>
</head>
<body>
    <a class="skip-link" href="#main-content">رفتن به محتوای اصلی</a>
    <?php partial('preloader'); ?>
    <?php partial('navbar'); ?>
    <main id="main-content">
        <?= $content ?? '' ?>
    </main>
    <?php partial('footer'); ?>
    <?php partial('scripts'); ?>
    <?php if (!empty($extraJs)): foreach ((array) $extraJs as $js): ?>
        <script src="<?= asset($js) ?>" defer></script>
    <?php endforeach; endif; ?>
</body>
</html>
