<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5">
    <meta name="description" content="<?= e($metaDescription ?? setting('clinic_name', 'کلینیک دندانپزشکی سروا')) ?>">
    <?php if (!empty($robotsMeta)): ?>
    <meta name="robots" content="<?= e($robotsMeta) ?>">
    <?php endif; ?>
    <?php if (!empty($canonical)): ?>
    <link rel="canonical" href="<?= e($canonical) ?>">
    <?php endif; ?>
    <meta name="author" content="Sarva Dental Clinic">
    <meta property="og:title" content="<?= e($ogTitle ?? $title ?? setting('clinic_name')) ?>">
    <meta property="og:description" content="<?= e($ogDescription ?? $metaDescription ?? '') ?>">
    <meta property="og:locale" content="fa_IR">
    <meta property="og:type" content="article">
    <?php
    $ogImage = $ogImage ?? setting('og_default_image', 'images/about-us-image.jpg');
    ?>
    <?php if (!empty($ogImage)): ?>
    <meta property="og:image" content="<?= e(media_url($ogImage)) ?>">
    <?php endif; ?>
    <title><?= e($htmlTitle ?? (($title ?? setting('clinic_name', config('app.name'))) . ' | ' . setting('clinic_name_en', 'Sarva Dental Clinic'))) ?></title>
    <link rel="shortcut icon" type="image/x-icon" href="<?= media_url(setting('favicon_path', 'images/favicon.png')) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="<?= asset('css/bootstrap.min.css') ?>" rel="stylesheet" media="screen">
    <link href="<?= asset('css/slicknav.min.css') ?>" rel="stylesheet">
    <link href="<?= asset('css/all.min.css') ?>" rel="stylesheet" media="screen">
    <link href="<?= asset('css/animate.css') ?>" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('css/magnific-popup.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/twentytwenty.css') ?>">
    <link href="<?= asset('css/custom.css') ?>" rel="stylesheet" media="screen">
    <link href="<?= asset('css/rtl.css') ?>" rel="stylesheet" media="screen">
    <?php if (!empty($extraCss)): foreach ((array) $extraCss as $css): ?>
        <link href="<?= asset($css) ?>" rel="stylesheet">
    <?php endforeach; endif; ?>
</head>
<body>
    <?php partial('preloader'); ?>
    <?php partial('navbar'); ?>
    <?= $content ?? '' ?>
    <?php partial('footer'); ?>
    <?php partial('scripts'); ?>
    <?php if (!empty($extraJs)): foreach ((array) $extraJs as $js): ?>
        <script src="<?= asset($js) ?>" defer></script>
    <?php endforeach; endif; ?>
</body>
</html>
