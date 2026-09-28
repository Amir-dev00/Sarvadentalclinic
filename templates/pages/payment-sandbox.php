<?php partial('page-header', ['pageTitle' => $title ?? 'پرداخت آزمایشی']); ?>
<div class="page-appointment">
    <div class="container">
        <div class="row">
            <div class="col-lg-6 mx-auto">
                <div class="admin-card wow fadeInUp text-center">
                    <div class="section-title section-title-center">
                        <span class="section-sub-title">درگاه آزمایشی</span>
                        <h2 class="text-anime-style-3" data-cursor="-opaque">پرداخت شبیه‌سازی‌شده</h2>
                        <p class="wow fadeInUp" data-wow-delay="0.2s">این صفحه جایگزین درگاه واقعی در محیط توسعه است. برای ادامه، پرداخت موفق را تأیید کنید.</p>
                    </div>
                    <?php if (!empty($authority)): ?>
                    <p class="mb-3"><small>شناسه تراکنش: <code dir="ltr"><?= e($authority) ?></code></small></p>
                    <?php endif; ?>
                    <form method="POST" action="<?= url('/payment/callback') ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="authority" value="<?= e($authority ?? '') ?>">
                        <button type="submit" class="btn-default btn-highlighted">پرداخت موفق (آزمایشی)</button>
                    </form>
                    <p class="mt-4 mb-0">
                        <a href="<?= url('/appointment') ?>">انصراف و بازگشت</a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
