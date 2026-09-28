<?php
$templates = $templates ?? [];
?>
<div class="admin-card">
    <h2 style="font-size:1.1rem;color:#031D4F;">تنظیمات پیامک</h2>
    <p>کلید API هرگز در رابط کاربری نمایش داده نمی‌شود. مقدار فعلی: <code><?= e($masked_key ?? '') ?></code></p>
    <p>ارائه‌دهنده: <strong><?= e(($driver ?? 'log') === 'smsir' ? 'SMS.ir' : 'log (توسعه)') ?></strong>
        · خط: <code><?= e($line ?: '—') ?></code></p>
    <form method="post" action="<?= url('/admin/sms/settings') ?>" class="row g-3">
        <?= csrf_field() ?>
        <div class="col-md-3 d-flex align-items-end">
            <label class="form-check"><input type="checkbox" name="sms_enabled" class="form-check-input" <?= setting('sms_enabled', '1') !== '0' ? 'checked' : '' ?>> سیستم پیامک فعال</label>
        </div>
        <div class="col-md-3">
            <label class="form-label">حداکثر تلاش مجدد</label>
            <input name="sms_max_retries" type="number" min="1" max="5" class="form-control" value="<?= e((string) setting('sms_max_retries', '3')) ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label">فاصله تلاش (دقیقه)</label>
            <input name="sms_retry_delay_minutes" type="number" min="5" class="form-control" value="<?= e((string) setting('sms_retry_delay_minutes', '10')) ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label">ساعت پیش‌فرض یادآوری</label>
            <input name="sms_default_reminder_time" type="time" class="form-control" value="<?= e((string) setting('sms_default_reminder_time', '18:00')) ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label">سقف ارسال گروهی</label>
            <input name="sms_bulk_limit" type="number" min="1" max="500" class="form-control" value="<?= e((string) setting('sms_bulk_limit', '200')) ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label">منطقه زمانی کلینیک</label>
            <input name="timezone" class="form-control" value="<?= e((string) setting('timezone', 'Asia/Tehran')) ?>">
        </div>
        <div class="col-12">
            <button class="btn btn-primary" style="background:#031D4F;border-color:#031D4F;">ذخیره</button>
        </div>
    </form>
    <p class="mt-3" style="color:#6b7280;font-size:.9rem;">برای SMS.ir در فایل <code>.env</code> این مقادیر را بگذارید:<br>
        <code>SMS_DRIVER=smsir</code> · <code>SMSIR_API_KEY</code> · <code>SMSIR_LINE_NUMBER</code> · <code>SMSIR_TEMPLATE_ID</code> (اختیاری برای OTP)</p>
</div>
