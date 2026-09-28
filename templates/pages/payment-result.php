<?php partial('page-header', ['pageTitle' => $title ?? 'نتیجه پرداخت']); ?>
<?php
$ok = !empty($ok);
$message = $message ?? ($ok ? 'پرداخت با موفقیت انجام شد.' : 'پرداخت ناموفق بود.');
?>
<div class="page-appointment">
    <div class="container">
        <div class="row">
            <div class="col-lg-6 mx-auto">
                <div class="admin-card wow fadeInUp text-center">
                    <div class="section-title section-title-center">
                        <span class="section-sub-title"><?= $ok ? 'موفق' : 'ناموفق' ?></span>
                        <h2 class="text-anime-style-3" data-cursor="-opaque"><?= $ok ? 'پرداخت تأیید شد' : 'پرداخت انجام نشد' ?></h2>
                    </div>
                    <div class="auth-alert <?= $ok ? 'success' : 'error' ?>"><?= e($message) ?></div>
                    <?php if ($ok && !empty($ref_id)): ?>
                    <p>کد پیگیری: <strong dir="ltr"><?= e((string) $ref_id) ?></strong></p>
                    <?php endif; ?>
                    <?php if (!empty($appointment_id)): ?>
                    <p>شماره نوبت: <strong>#<?= e((string) $appointment_id) ?></strong></p>
                    <?php endif; ?>
                    <div class="mt-4">
                        <?php if ($ok): ?>
                        <a href="<?= url('/patient') ?>" class="btn-default btn-highlighted">پنل بیمار</a>
                        <?php else: ?>
                        <a href="<?= url('/appointment') ?>" class="btn-default btn-highlighted">تلاش مجدد</a>
                        <?php endif; ?>
                        <a href="<?= url('/') ?>" class="btn-default" style="margin-right:12px;">صفحه اصلی</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
