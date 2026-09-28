<?php
$stats = $stats ?? [];
$upcoming = $upcoming ?? [];
$viewsToday = (int) ($viewsToday ?? 0);
$viewsWeek = (int) ($viewsWeek ?? 0);

$statusLabels = [
    'awaiting_payment' => 'در انتظار پرداخت',
    'confirmed' => 'تأیید شده',
    'completed' => 'انجام شده',
    'cancelled' => 'لغو شده',
    'no_show' => 'عدم حضور',
    'expired' => 'منقضی',
];
?>
<div class="stat-grid">
    <div class="stat-card">
        <h3><?= (int) ($stats['today'] ?? 0) ?></h3>
        <p>نوبت‌های امروز</p>
    </div>
    <div class="stat-card">
        <h3><?= (int) ($stats['tomorrow'] ?? 0) ?></h3>
        <p>نوبت‌های فردا</p>
    </div>
    <div class="stat-card">
        <h3><?= (int) ($stats['week'] ?? 0) ?></h3>
        <p>نوبت‌های هفته</p>
    </div>
    <div class="stat-card">
        <h3><?= (int) ($stats['pending_payments'] ?? 0) ?></h3>
        <p>پرداخت‌های معلق</p>
    </div>
    <div class="stat-card">
        <h3><?= (int) ($stats['paid_payments'] ?? 0) ?></h3>
        <p>پرداخت موفق امروز</p>
    </div>
    <div class="stat-card">
        <h3><?= (int) ($stats['patients'] ?? 0) ?></h3>
        <p>کل بیماران</p>
    </div>
    <div class="stat-card">
        <h3><?= (int) ($stats['new_patients'] ?? 0) ?></h3>
        <p>بیمار جدید امروز</p>
    </div>
    <div class="stat-card">
        <h3><?= $viewsToday ?></h3>
        <p>بازدید امروز</p>
    </div>
    <div class="stat-card">
        <h3><?= $viewsWeek ?></h3>
        <p>بازدید ۷ روز</p>
    </div>
</div>

<?php $smsStats = $smsStats ?? []; ?>
<div class="stat-grid">
    <div class="stat-card"><h3><?= (int) ($smsStats['sent_today'] ?? 0) ?></h3><p>پیامک ارسال‌شده امروز</p></div>
    <div class="stat-card"><h3><?= (int) ($smsStats['scheduled_today'] ?? 0) ?></h3><p>پیامک زمان‌بندی امروز</p></div>
    <div class="stat-card"><h3><?= (int) ($smsStats['queued'] ?? 0) ?></h3><p>صف پیامک</p></div>
    <div class="stat-card"><h3><?= (int) ($smsStats['failed'] ?? 0) ?></h3><p>پیامک ناموفق امروز</p></div>
    <div class="stat-card"><h3><?= (int) ($smsStats['tomorrow_appts'] ?? 0) ?></h3><p>نوبت فردا</p></div>
    <div class="stat-card"><h3><?= (int) ($smsStats['tomorrow_queued'] ?? 0) ?></h3><p>یادآوری در صف</p></div>
    <div class="stat-card"><h3><?= (int) ($smsStats['tomorrow_sent'] ?? 0) ?></h3><p>یادآوری ارسال‌شده</p></div>
    <div class="stat-card"><h3><?= (int) ($smsStats['tomorrow_failed'] ?? 0) ?></h3><p>یادآوری ناموفق</p></div>
</div>

<div class="admin-card">
    <h2 style="margin:0 0 16px;font-size:1.1rem;color:#031D4F;">نوبت‌های پیش‌رو</h2>
    <?php if (empty($upcoming)): ?>
        <p style="margin:0;color:#6b7280;">نوبت تأییدشده‌ای در آینده وجود ندارد.</p>
    <?php else: ?>
        <div style="overflow-x:auto;">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>بیمار</th>
                        <th>خدمت</th>
                        <th>زمان</th>
                        <th>وضعیت</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($upcoming as $row): ?>
                        <tr>
                            <td><?= e($row['patient_name'] ?? '') ?></td>
                            <td><?= e($row['service_name'] ?? '') ?></td>
                            <td><?= e(to_jalali($row['starts_at'] ?? null)) ?></td>
                            <td><?= e($statusLabels[$row['status'] ?? ''] ?? ($row['status'] ?? '')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
