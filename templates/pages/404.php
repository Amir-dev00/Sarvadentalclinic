<?php partial('page-header', ['pageTitle' => $title ?? 'صفحه یافت نشد']); ?>
<div class="error-page">
    <div class="container">
        <div class="row">
            <div class="col-lg-12">
                <div class="error-page-image wow fadeInUp">
                    <img src="<?= asset('images/404-error-img.png') ?>" alt="۴۰۴">
                </div>
                <div class="error-page-content">
                    <div class="section-title">
                        <h2 class="text-anime-style-3" data-cursor="-opaque">متأسفیم! این صفحه پیدا نشد</h2>
                    </div>
                    <div class="error-page-content-body">
                        <p class="wow fadeInUp" data-wow-delay="0.2s">آدرس واردشده وجود ندارد یا جابه‌جا شده است. می‌توانید به صفحه اصلی بازگردید یا نوبت خود را رزرو کنید.</p>
                        <a class="btn-default wow fadeInUp" data-wow-delay="0.4s" href="<?= url('/') ?>">بازگشت به خانه</a>
                        <a class="btn-default wow fadeInUp" data-wow-delay="0.5s" href="<?= booking_url() ?>" style="margin-right:12px;">رزرو نوبت</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
