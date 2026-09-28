<?php
$about = $about ?? [];
$aboutImage = $about['image'] ?? 'images/about-us-image.jpg';
?>
<?php partial('page-header', ['pageTitle' => $title ?? 'درباره ما']); ?>
<div class="page-about-us">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-6">
                <figure class="image-anime wow fadeInUp">
                    <img src="<?= media_url($aboutImage) ?>" alt="<?= e($about['title'] ?? 'درباره کلینیک سروا') ?>">
                </figure>
            </div>
            <div class="col-lg-6">
                <div class="section-title">
                    <span class="section-sub-title wow fadeInUp"><?= e($about['subtitle'] ?? 'کلینیک دندانپزشکی سروا') ?></span>
                    <h2 class="text-anime-style-3" data-cursor="-opaque"><?= e($about['title'] ?? 'مراقبت حرفه‌ای با تمرکز بر آرامش بیمار') ?></h2>
                    <p class="wow fadeInUp" data-wow-delay="0.2s"><?= e($about['description'] ?? 'کلینیک دندانپزشکی سروا با بهره‌گیری از دانش روز، تجهیزات مدرن و تیمی متخصص، خدمات دندانپزشکی عمومی و تخصصی را در محیطی آرام ارائه می‌کند.') ?></p>
                </div>
                <a href="<?= !empty($about['cta_link']) ? url((string) $about['cta_link']) : booking_url() ?>" class="btn-default wow fadeInUp" data-wow-delay="0.4s"><?= e($about['cta_text'] ?? 'رزرو نوبت') ?></a>
            </div>
        </div>
    </div>
</div>
