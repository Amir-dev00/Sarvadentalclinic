<?php
$stats = $stats ?? [];
$driver = $driver ?? 'log';
?>
<div class="stat-grid">
    <div class="stat-card"><h3><?= (int) ($stats['sent_today'] ?? 0) ?></h3><p>ارسال امروز</p></div>
    <div class="stat-card"><h3><?= (int) ($stats['queued'] ?? 0) ?></h3><p>در صف</p></div>
    <div class="stat-card"><h3><?= (int) ($stats['scheduled_today'] ?? 0) ?></h3><p>زمان‌بندی امروز</p></div>
    <div class="stat-card"><h3><?= (int) ($stats['failed'] ?? 0) ?></h3><p>ناموفق امروز</p></div>
</div>
<div class="admin-card">
    <p style="color:#6b7280;">ارائه‌دهنده فعال: <strong><?= e($driver === 'smsir' ? 'SMS.ir' : 'حالت توسعه (log)') ?></strong></p>
    <div class="d-flex flex-wrap gap-2">
        <a class="btn btn-sm" style="background:#05B18B;color:#fff;" href="<?= url('/admin/sms/send') ?>">ارسال پیامک</a>
        <a class="btn btn-sm btn-outline-primary" href="<?= url('/admin/sms/send?mode=tomorrow') ?>">نوبت‌های فردا</a>
        <a class="btn btn-sm btn-outline-primary" href="<?= url('/admin/sms/batches') ?>">ارسال‌های گروهی</a>
        <a class="btn btn-sm btn-outline-primary" href="<?= url('/admin/sms/templates') ?>">پیام‌های آماده</a>
        <a class="btn btn-sm btn-outline-primary" href="<?= url('/admin/sms/queue') ?>">زمان‌بندی‌شده</a>
        <a class="btn btn-sm btn-outline-primary" href="<?= url('/admin/sms/automation') ?>">پیام‌های خودکار</a>
        <a class="btn btn-sm btn-outline-primary" href="<?= url('/admin/sms/logs') ?>">تاریخچه</a>
        <a class="btn btn-sm btn-outline-secondary" href="<?= url('/admin/sms/settings') ?>">تنظیمات</a>
    </div>
</div>
<div class="admin-card">
    <h2 style="font-size:1.05rem;color:#031D4F;">یادآوری نوبت‌های فردا</h2>
    <div class="stat-grid">
        <div class="stat-card"><h3><?= (int) ($stats['tomorrow_appts'] ?? 0) ?></h3><p>نوبت فردا</p></div>
        <div class="stat-card"><h3><?= (int) ($stats['tomorrow_queued'] ?? 0) ?></h3><p>یادآوری در صف</p></div>
        <div class="stat-card"><h3><?= (int) ($stats['tomorrow_sent'] ?? 0) ?></h3><p>یادآوری ارسال‌شده</p></div>
        <div class="stat-card"><h3><?= (int) ($stats['tomorrow_failed'] ?? 0) ?></h3><p>یادآوری ناموفق</p></div>
    </div>
    <p style="color:#6b7280;margin:0;font-size:.9rem;">ارسال واقعی توسط Cron سرور انجام می‌شود و به باز بودن مرورگر وابسته نیست.</p>
</div>
