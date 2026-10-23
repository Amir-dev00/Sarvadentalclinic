<?php
$hero = $sections['hero'] ?? [];
$about = $sections['about'] ?? [];
?>
<div class="hero dark-section parallaxie" style="background-image: url('<?= e(media_url($hero['image'] ?? 'images/hero-bg-image.png')) ?>');">
    <div class="container">
        <div class="row">
            <div class="col-xl-7">
                <div class="hero-content">
                    <div class="section-title">
                        <span class="section-sub-title wow fadeInUp"><?= e($hero['subtitle'] ?? 'مراقبت مطمئن دندانپزشکی') ?></span>
                        <h1 class="text-anime-style-3" data-cursor="-opaque"><?= e($hero['title'] ?? 'لبخندی سالم‌تر از اینجا آغاز می‌شود') ?> <span><?= e(setting('clinic_name', 'کلینیک سروا')) ?></span></h1>
                        <p class="wow fadeInUp" data-wow-delay="0.2s"><?= e($hero['description'] ?? 'تجربه درمان شخصی‌سازی‌شده با تجهیزات مدرن و تیمی متعهد به سلامت لبخند شما.') ?></p>
                    </div>
                    <div class="hero-content-body wow fadeInUp" data-wow-delay="0.4s">
                        <div class="hero-btn">
                            <a href="<?= url($hero['cta_link'] ?? '/services') ?>" class="btn-default btn-highlighted"><?= e($hero['cta_text'] ?? 'مشاهده خدمات') ?></a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="about-us">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-xl-6">
                <div class="about-us-image-box wow fadeInUp">
                    <div class="about-us-image">
                        <figure class="image-anime">
                            <img src="<?= media_url($about['image'] ?? 'images/about-us-image.jpg') ?>" alt="" width="640" height="720">
                        </figure>
                    </div>
                    <div class="about-us-blockquote-box">
                        <p><?= e($about['description'] ?? 'ما مراقبت ایمن، راحت و قابل‌اعتماد را برای هر بیمار فراهم می‌کنیم.') ?></p>
                        <a href="<?= url($about['cta_link'] ?? '/about') ?>"><?= e($about['cta_text'] ?? 'بیشتر بدانید') ?></a>
                    </div>
                </div>
            </div>
            <div class="col-xl-6">
                <div class="about-us-content">
                    <div class="section-title">
                        <span class="section-sub-title wow fadeInUp"><?= e($about['subtitle'] ?? 'درباره ما') ?></span>
                        <h2 class="text-anime-style-3" data-cursor="-opaque"><?= e($about['title'] ?? 'سلامت دهان، آرامش خاطر') ?></h2>
                        <p class="wow fadeInUp" data-wow-delay="0.2s"><?= e($about['description'] ?? 'کلینیک دندانپزشکی سروا با تمرکز بر کیفیت درمان و تجربه بیمار، خدمات تخصصی ارائه می‌دهد.') ?></p>
                    </div>
                    <div class="about-us-btn wow fadeInUp" data-wow-delay="0.4s">
                        <a href="<?= url('/about') ?>" class="btn-default">درباره کلینیک</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="our-services bg-section">
    <div class="container">
        <div class="row section-row align-items-center">
            <div class="col-lg-6">
                <div class="section-title">
                    <span class="section-sub-title wow fadeInUp">خدمات ما</span>
                    <h2 class="text-anime-style-3" data-cursor="-opaque">درمان‌های تخصصی برای <span>لبخند شما</span></h2>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="section-btn wow fadeInUp" data-wow-delay="0.2s">
                    <a href="<?= url('/services') ?>" class="btn-default">همه خدمات</a>
                </div>
            </div>
        </div>
        <div class="row">
            <?php foreach (($services ?? []) as $i => $service): ?>
            <div class="col-xl-4 col-md-6">
                <?php partial('service-card', ['service' => $service, 'index' => (int) $i]); ?>
            </div>
            <?php endforeach; ?>
            <?php if (empty($services)): ?>
            <div class="col-12"><p>خدماتی برای نمایش وجود ندارد.</p></div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="our-team">
    <div class="container">
        <div class="row section-row">
            <div class="col-lg-12">
                <div class="section-title section-title-center">
                    <span class="section-sub-title wow fadeInUp">تیم پزشکی</span>
                    <h2 class="text-anime-style-3" data-cursor="-opaque">پزشکان <span>کلینیک سروا</span></h2>
                </div>
            </div>
        </div>
        <div class="row justify-content-center">
            <?php foreach (($doctors ?? []) as $i => $doctor): ?>
            <?php
                $fullName = trim(($doctor['first_name'] ?? '') . ' ' . ($doctor['last_name'] ?? ''));
                $delay = ($i % 3) * 0.15;
            ?>
            <div class="col-lg-4 col-md-6">
                <div class="team-item wow fadeInUp"<?= $delay ? ' data-wow-delay="' . e((string) $delay) . 's"' : '' ?>>
                    <div class="team-item-image">
                        <figure>
                            <img src="<?= media_url($doctor['photo'] ?: 'images/team-image-1.jpg') ?>" alt="<?= e($fullName) ?>" loading="lazy" width="400" height="420">
                        </figure>
                    </div>
                    <div class="team-item-content">
                        <h2><?= e($fullName) ?></h2>
                        <p><?= e($doctor['specialty'] ?? '') ?></p>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php
$transformations = $transformations ?? [];
?>
<div class="our-transformation">
    <div class="container">
        <div class="row section-row">
            <div class="col-lg-12">
                <div class="section-title section-title-center">
                    <span class="section-sub-title wow fadeInUp">قبل و بعد</span>
                    <h2 class="text-anime-style-3" data-cursor="-opaque">نتایج واقعی، لبخندهای <span>مطمئن</span></h2>
                    <p class="wow fadeInUp" data-wow-delay="0.2s">نمونه‌هایی از درمان‌های کلینیک سروا؛ از سفیدکردن تا طراحی لبخند و ارتودنسی.</p>
                </div>
            </div>
        </div>
        <div class="row">
            <?php if (empty($transformations)): ?>
            <div class="col-12 text-center"><p class="wow fadeInUp">هنوز نمونه قبل و بعدی منتشر نشده است. از پنل مدیریت » قبل و بعد اضافه کنید.</p></div>
            <?php endif; ?>
            <?php foreach ($transformations as $i => $item): ?>
            <?php
                $before = $item['before'] ?? ($item['before_image'] ?? '');
                $after = $item['after'] ?? ($item['after_image'] ?? '');
                $title = $item['title'] ?? 'نمونه درمان';
                if ($before === '' || $after === '') {
                    continue;
                }
                $delay = ($i % 3) * 0.2;
            ?>
            <div class="col-lg-4 col-md-6">
                <div class="transformation-image-box wow fadeInUp"<?= $delay ? ' data-wow-delay="' . e((string) $delay) . 's"' : '' ?>>
                    <div class="transformation_image twentytwenty-container">
                        <img src="<?= media_url($before) ?>" alt="قبل — <?= e($title) ?>" loading="lazy">
                        <img src="<?= media_url($after) ?>" alt="بعد — <?= e($title) ?>" loading="lazy">
                    </div>
                    <div class="transformation-image-title">
                        <h3><?= e($title) ?></h3>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="row">
            <div class="col-12 text-center wow fadeInUp" data-wow-delay="0.3s">
                <a href="<?= url('/case-studies') ?>" class="btn-default">مشاهده نمونه کارها</a>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($testimonials)): ?>
<?php
    // Visual fill for short lists (animation only — DB rows unchanged)
    $marqueeItems = $testimonials;
    while (count($marqueeItems) < 6) {
        $marqueeItems = array_merge($marqueeItems, $testimonials);
    }
?>
<div class="our-testimonials bg-section">
    <div class="container">
        <div class="row section-row">
            <div class="col-lg-12">
                <div class="section-title section-title-center">
                    <span class="section-sub-title wow fadeInUp">نظرات بیماران</span>
                    <h2 class="text-anime-style-3" data-cursor="-opaque">تجربه بیماران <span>سروا</span></h2>
                </div>
            </div>
        </div>
    </div>

    <div class="tm-marquee" data-testimonial-marquee>
        <div class="tm-marquee-viewport">
            <div class="tm-marquee-track">
                <div class="tm-marquee-group">
                    <?php foreach ($marqueeItems as $t): ?>
                        <?php partial('testimonial-marquee-card', ['t' => $t]); ?>
                    <?php endforeach; ?>
                </div>
                <div class="tm-marquee-group" aria-hidden="true">
                    <?php foreach ($marqueeItems as $t): ?>
                        <?php partial('testimonial-marquee-card', ['t' => $t]); ?>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="row mt-4">
            <div class="col-12 text-center wow fadeInUp">
                <a href="<?= url('/testimonials') ?>" class="btn-default">مشاهده همه نظرات</a>
                <a href="<?= url('/testimonials#leave-review') ?>" class="btn-default btn-highlighted ms-2">ثبت نظر</a>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if (!empty($latestArticles)): ?>
<div class="our-articles">
    <div class="container">
        <div class="row section-row align-items-center">
            <div class="col-lg-8">
                <div class="section-title">
                    <span class="section-sub-title wow fadeInUp">دانش و آگاهی</span>
                    <h2 class="text-anime-style-3" data-cursor="-opaque">آخرین <span>مقالات</span></h2>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="section-btn wow fadeInUp" data-wow-delay="0.2s">
                    <a href="<?= url('/articles') ?>" class="btn-default">مشاهده همه مقالات</a>
                </div>
            </div>
        </div>
        <div class="row">
            <?php foreach ($latestArticles as $post): ?>
            <div class="col-lg-4 col-md-6">
                <?php partial('article-card', ['post' => $post]); ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if (!empty($faqs)): ?>
<div class="our-faqs">
    <div class="container">
        <div class="row section-row">
            <div class="col-lg-12">
                <div class="section-title section-title-center">
                    <span class="section-sub-title wow fadeInUp">سؤالات متداول</span>
                    <h2 class="text-anime-style-3" data-cursor="-opaque">پاسخ به پرسش‌های <span>شما</span></h2>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-lg-10 mx-auto">
                <div class="faq-accordion" id="homeFaq">
                    <?php foreach ($faqs as $i => $faq): ?>
                    <div class="faq-accordion-item wow fadeInUp">
                        <div class="faq-accordion-header <?= $i === 0 ? 'active' : '' ?>" data-bs-toggle="collapse" data-bs-target="#hf<?= $i ?>">
                            <h3><?= e($faq['question']) ?></h3>
                        </div>
                        <div id="hf<?= $i ?>" class="collapse <?= $i === 0 ? 'show' : '' ?>" data-bs-parent="#homeFaq">
                            <div class="faq-accordion-body">
                                <p><?= e($faq['answer']) ?></p>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="text-center mt-4">
                    <a href="<?= url('/faqs') ?>" class="btn-default">مشاهده همه پرسش‌ها</a>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>
