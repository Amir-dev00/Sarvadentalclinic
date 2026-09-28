<?php partial('page-header', ['pageTitle' => $service['name'] ?? $title]); ?>
<div class="page-service-single">
    <div class="container">
        <div class="row">
            <div class="col-lg-8">
                <div class="service-entry">
                    <?php if (!empty($service['image'])): ?>
                    <figure class="image-anime mb-4"><img src="<?= media_url($service['image']) ?>" alt=""></figure>
                    <?php endif; ?>
                    <h2><?= e($service['name']) ?></h2>
                    <p><?= nl2br(e($service['description'] ?: $service['short_description'])) ?></p>
                    <?php if ($service['price']): ?>
                    <p><strong>هزینه ویزیت از:</strong> <?= e(number_format((float)$service['price'])) ?> ریال</p>
                    <?php endif; ?>
                    <a href="<?= booking_url() ?>" class="btn-default">رزرو این خدمت</a>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="service-sidebar admin-card">
                    <h3>رزرو نوبت</h3>
                    <p>برای دریافت نوبت آنلاین وارد شوید و زمان مناسب را انتخاب کنید.</p>
                    <a href="<?= booking_url() ?>" class="btn-default">شروع رزرو</a>
                </div>
            </div>
        </div>
    </div>
</div>
