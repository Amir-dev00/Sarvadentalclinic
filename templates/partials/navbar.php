<?php
$clinicName = setting('clinic_name', config('app.name'));
$menuItems = [];
try {
    if (\Sarva\Core\Database::connected()) {
        $menuItems = db()->query(
            "SELECT mi.* FROM menu_items mi
             JOIN menus m ON m.id = mi.menu_id
             WHERE m.location='main' AND mi.is_active=1
             ORDER BY mi.sort_order"
        )->fetchAll();
    }
} catch (Throwable) {
    $menuItems = [];
}
if (!$menuItems) {
    $menuItems = [
        ['label' => 'خانه', 'url' => '/'],
        ['label' => 'خدمات', 'url' => '/services'],
        ['label' => 'مقالات', 'url' => '/articles'],
        ['label' => 'درباره ما', 'url' => '/about'],
        ['label' => 'تماس با ما', 'url' => '/contact'],
    ];
}
?>
<header class="main-header active-sticky-header">
    <div class="header-sticky">
        <nav class="navbar navbar-expand-lg">
            <div class="container">
                <div class="navbar-brand-block">
                    <a class="navbar-brand" href="<?= url('/') ?>">
                        <img src="<?= media_url(setting('logo_path', 'images/logo.svg')) ?>?v=3" alt="<?= e($clinicName) ?>">
                    </a>
                    <p class="clinic-legacy-name clinic-legacy-name--header">(دکتر سید حسن زمان‌زاده)</p>
                </div>
                <div class="collapse navbar-collapse main-menu">
                    <div class="nav-menu-wrapper">
                        <ul class="navbar-nav mr-auto" id="menu">
                            <?php foreach ($menuItems as $item): ?>
                                <?php
                                    $itemUrl = (string) ($item['url'] ?? '');
                                    if ($itemUrl === '/appointment' || $itemUrl === '/team' || ($item['label'] ?? '') === 'تیم پزشکی') {
                                        continue;
                                    }
                                ?>
                                <li class="nav-item">
                                    <a class="nav-link" href="<?= e(str_starts_with($item['url'], 'http') ? $item['url'] : url($item['url'])) ?>">
                                        <?= e($item['label']) ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                            <li class="nav-item submenu">
                                <a class="nav-link" href="#">صفحات</a>
                                <ul>
                                    <li class="nav-item"><a class="nav-link" href="<?= url('/case-studies') ?>">نمونه کارها</a></li>
                                    <li class="nav-item"><a class="nav-link" href="<?= url('/testimonials') ?>">نظرات بیماران</a></li>
                                    <li class="nav-item"><a class="nav-link" href="<?= url('/gallery') ?>">گالری تصاویر</a></li>
                                    <li class="nav-item"><a class="nav-link" href="<?= url('/gallery/videos') ?>">گالری ویدیو</a></li>
                                    <li class="nav-item"><a class="nav-link" href="<?= url('/faqs') ?>">سؤالات متداول</a></li>
                                </ul>
                            </li>
                            <li class="nav-item highlighted-menu">
                                <a class="nav-link" href="<?= booking_url() ?>">رزرو نوبت</a>
                            </li>
                            <li class="nav-item nav-item-auth">
                                <a class="nav-link" href="<?= url(\Sarva\Core\Auth::isPatient() ? '/patient' : '/auth') ?>">
                                    <?= \Sarva\Core\Auth::isPatient() ? 'پنل من' : 'ورود/ثبت نام' ?>
                                </a>
                            </li>
                        </ul>
                    </div>
                    <div class="header-btn">
                        <a href="<?= booking_url() ?>" class="btn-default">رزرو نوبت</a>
                    </div>
                </div>
                <div class="navbar-toggle"></div>
            </div>
        </nav>
        <div class="responsive-menu"></div>
    </div>
</header>
