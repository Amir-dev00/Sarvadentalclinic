<?php partial('page-header', ['pageTitle' => $title ?? ($case['title'] ?? 'نمونه کار')]); ?>
<div class="page-case-study-single">
    <div class="container">
        <div class="row">
            <div class="col-lg-8">
                <div class="case-study-single-content">
                    <?php if (!empty($case['before_image']) && !empty($case['after_image'])): ?>
                    <div class="page-single-image wow fadeInUp mb-4">
                        <div class="twentytwenty-container">
                            <img src="<?= media_url($case['before_image']) ?>" alt="قبل از درمان">
                            <img src="<?= media_url($case['after_image']) ?>" alt="بعد از درمان">
                        </div>
                    </div>
                    <?php elseif (!empty($case['after_image']) || !empty($case['before_image'])): ?>
                    <div class="page-single-image wow fadeInUp mb-4">
                        <figure class="image-anime reveal">
                            <img src="<?= media_url($case['after_image'] ?: $case['before_image']) ?>" alt="<?= e($case['title'] ?? '') ?>">
                        </figure>
                    </div>
                    <?php endif; ?>

                    <div class="section-title">
                        <h2 class="text-anime-style-3" data-cursor="-opaque"><?= e($case['title'] ?? '') ?></h2>
                    </div>
                    <?php if (!empty($case['description'])): ?>
                    <div class="wow fadeInUp" data-wow-delay="0.2s">
                        <p><?= nl2br(e($case['description'])) ?></p>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($case['before_image']) && !empty($case['after_image'])): ?>
                    <div class="row mt-4 wow fadeInUp" data-wow-delay="0.3s">
                        <div class="col-md-6 mb-3">
                            <h4>قبل از درمان</h4>
                            <figure class="image-anime">
                                <img src="<?= media_url($case['before_image']) ?>" alt="قبل">
                            </figure>
                        </div>
                        <div class="col-md-6 mb-3">
                            <h4>بعد از درمان</h4>
                            <figure class="image-anime">
                                <img src="<?= media_url($case['after_image']) ?>" alt="بعد">
                            </figure>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="wow fadeInUp" data-wow-delay="0.4s" style="margin-top:24px;">
                        <a href="<?= booking_url() ?>" class="btn-default">رزرو نوبت مشابه</a>
                        <a href="<?= url('/case-studies') ?>" class="btn-default" style="margin-right:12px;">همه نمونه‌کارها</a>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="page-single-sidebar">
                    <div class="page-category-list case-study-category-list wow fadeInUp admin-card">
                        <h2 class="page-category-list-title">جزئیات</h2>
                        <ul>
                            <?php if (!empty($case['case_date'])): ?>
                            <li>تاریخ: <span><?= e($case['case_date']) ?></span></li>
                            <?php endif; ?>
                            <li>کلینیک: <span><?= e(setting('clinic_name', 'کلینیک سروا')) ?></span></li>
                        </ul>
                    </div>
                    <div class="sidebar-cta-box wow fadeInUp admin-card" data-wow-delay="0.2s">
                        <h3>مشاوره رایگان</h3>
                        <p>برای بررسی وضعیت مشابه، نوبت آنلاین بگیرید.</p>
                        <p><a href="tel:<?= e(setting('phone', '')) ?>"><?= e(setting('phone', '')) ?></a></p>
                        <a href="<?= booking_url() ?>" class="btn-default">رزرو نوبت</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
