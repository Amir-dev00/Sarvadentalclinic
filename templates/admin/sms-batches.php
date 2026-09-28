<?php
$items = $items ?? [];
$page = (int) ($page ?? 1);
$pages = (int) ($pages ?? 1);
$labels = sms_status_labels();
$modes = \Sarva\Services\SmsAudienceService::modeLabels();
?>
<div class="admin-card">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <h2 style="margin:0;font-size:1.1rem;color:#031D4F;">ارسال‌های گروهی</h2>
        <a class="btn btn-sm" style="background:#05B18B;color:#fff;border:0;" href="<?= url('/admin/sms/send') ?>">ارسال جدید</a>
    </div>
    <div style="overflow-x:auto;">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>کد</th><th>عنوان</th><th>مخاطب</th><th>وضعیت</th>
                    <th>صف</th><th>ارسال</th><th>ناموفق</th><th>در انتظار</th><th>مدیر</th><th>زمان</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($items)): ?>
                <tr><td colspan="10" class="text-center py-4" style="color:#6b7280;">هنوز ارسال گروهی ثبت نشده است.</td></tr>
            <?php else: foreach ($items as $item): ?>
                <tr>
                    <td><a href="<?= url('/admin/sms/batches/' . (int) $item['id']) ?>"><code><?= e($item['public_code']) ?></code></a></td>
                    <td><?= e($item['title'] ?? '') ?></td>
                    <td><?= e($modes[$item['mode'] ?? ''] ?? ($item['mode'] ?? '')) ?></td>
                    <td><?= e($labels[$item['status'] ?? ''] ?? $item['status']) ?></td>
                    <td><?= (int) ($item['queued_count'] ?? 0) ?></td>
                    <td><?= (int) ($item['sent_count'] ?? 0) ?></td>
                    <td><?= (int) ($item['failed_count'] ?? 0) ?></td>
                    <td><?= (int) ($item['pending_count'] ?? 0) ?></td>
                    <td><?= e($item['admin_name'] ?? '—') ?></td>
                    <td><?= e(to_jalali($item['created_at'] ?? null)) ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($pages > 1): ?>
    <nav class="admin-pager">
        <?php for ($i = 1; $i <= $pages; $i++): ?>
            <a class="<?= $i === $page ? 'is-active' : '' ?>" href="?page=<?= $i ?>"><?= $i ?></a>
        <?php endfor; ?>
    </nav>
    <?php endif; ?>
</div>
