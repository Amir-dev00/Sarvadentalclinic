<?php
$items = $items ?? [];
$statusLabels = [
    'uploaded' => 'بارگذاری شده',
    'previewed' => 'پیش‌نمایش',
    'processing' => 'در حال واردسازی',
    'completed' => 'تکمیل شده',
    'failed' => 'ناموفق',
];
$hasCompleted = false;
foreach ($items as $it) {
    if (($it['status'] ?? '') === 'completed') {
        $hasCompleted = true;
        break;
    }
}
?>
<div class="admin-card">
    <h2 style="margin:0 0 8px;font-size:1.1rem;color:#031D4F;">ورود یک‌باره بیماران از Excel</h2>
    <p style="color:#6b7280;margin:0 0 16px;line-height:1.7;">
        این بخش برای <strong>یک‌بار</strong> وارد کردن همه بیماران فعلی کلینیک از فایل اکسل است.
        بعد از تکمیل، بیماران جدید را از صفحه <a href="<?= url('/admin/patients/create') ?>">ایجاد پرونده</a> به‌صورت دستی ثبت کنید تا سرور شلوغ نشود.
    </p>
    <?php if ($hasCompleted): ?>
        <p style="margin:0 0 16px;padding:10px 12px;background:#e8f8f3;color:#067a5f;border-radius:10px;font-size:.92rem;">
            یک واردسازی موفق قبلاً ثبت شده است. معمولاً نیازی به آپلود دوباره اکسل نیست.
        </p>
    <?php endif; ?>
    <form method="post" action="<?= url('/admin/imports/upload') ?>" enctype="multipart/form-data" class="row g-3 align-items-end">
        <?= csrf_field() ?>
        <div class="col-md-6">
            <label class="form-label">فایل اکسل بیماران (برگه پرونده)</label>
            <input type="file" name="excel" class="form-control" accept=".xlsx,.xls,.csv" required>
        </div>
        <div class="col-md-3">
            <button type="submit" class="btn btn-primary" style="background:#05B18B;border-color:#05B18B;">آپلود و ادامه</button>
        </div>
    </form>
    <p style="margin:12px 0 0;color:#6b7280;font-size:.85rem;">فرمت‌های مجاز: XLSX، XLS، CSV · حداکثر ۱۵ مگابایت</p>
</div>

<div class="admin-card">
    <h2 style="margin:0 0 16px;font-size:1.1rem;color:#031D4F;">تاریخچه ورودها</h2>
    <div style="overflow-x:auto;">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>فایل</th>
                    <th>وضعیت</th>
                    <th>کل ردیف</th>
                    <th>وارد شده</th>
                    <th>به‌روز</th>
                    <th>تکراری</th>
                    <th>رد شده</th>
                    <th>تاریخ</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($items)): ?>
                    <tr><td colspan="10">واردسازی ثبت نشده است.</td></tr>
                <?php else: foreach ($items as $item): ?>
                    <tr>
                        <td><?= (int) $item['id'] ?></td>
                        <td><code><?= e($item['original_name'] ?? $item['filename'] ?? '') ?></code></td>
                        <td><?= e($statusLabels[$item['status'] ?? ''] ?? ($item['status'] ?? '')) ?></td>
                        <td><?= (int) ($item['total_rows'] ?? 0) ?></td>
                        <td><?= (int) ($item['imported_rows'] ?? 0) ?></td>
                        <td><?= (int) ($item['updated_rows'] ?? 0) ?></td>
                        <td><?= (int) ($item['duplicate_rows'] ?? 0) ?></td>
                        <td><?= (int) ($item['skipped_rows'] ?? 0) + (int) ($item['invalid_rows'] ?? 0) ?></td>
                        <td><?= e(to_jalali($item['created_at'] ?? null)) ?></td>
                        <td><a href="<?= url('/admin/imports/' . (int) $item['id']) ?>">مشاهده</a></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
