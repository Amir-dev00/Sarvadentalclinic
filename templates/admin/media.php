<?php $items = $items ?? []; ?>
<div class="admin-card">
    <h2 style="margin:0 0 16px;font-size:1.1rem;color:#031D4F;">بارگذاری رسانه</h2>
    <form method="post" action="<?= url('/admin/media/upload') ?>" enctype="multipart/form-data" class="row g-3 align-items-end">
        <?= csrf_field() ?>
        <div class="col-md-6">
            <label class="form-label">فایل تصویر (حداکثر ۵ مگابایت)</label>
            <input type="file" name="file" class="form-control" accept="image/jpeg,image/png,image/webp,image/gif" required>
        </div>
        <div class="col-md-3">
            <button type="submit" class="btn btn-primary" style="background:#05B18B;border-color:#05B18B;">آپلود</button>
        </div>
    </form>
</div>

<div class="admin-card">
    <div style="overflow-x:auto;">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>پیش‌نمایش</th>
                    <th>نام اصلی</th>
                    <th>مسیر</th>
                    <th>نوع</th>
                    <th>حجم</th>
                    <th>تاریخ</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($items)): ?>
                    <tr><td colspan="6">رسانه‌ای یافت نشد.</td></tr>
                <?php else: foreach ($items as $item): ?>
                    <tr>
                        <td>
                            <?php if (!empty($item['path'])): ?>
                                <img src="<?= media_url($item['path']) ?>" alt="" style="width:56px;height:56px;object-fit:cover;border-radius:8px;">
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                        <td><?= e($item['original_name'] ?? '') ?></td>
                        <td><code><?= e($item['path'] ?? '') ?></code></td>
                        <td><?= e($item['mime_type'] ?? '') ?></td>
                        <td><?= number_format(((int) ($item['size'] ?? 0)) / 1024, 1) ?> KB</td>
                        <td><?= e($item['created_at'] ?? '') ?></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
