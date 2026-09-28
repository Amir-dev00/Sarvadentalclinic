<?php $items = $items ?? []; ?>
<div class="admin-card">
    <div style="overflow-x:auto;">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>عنوان</th>
                    <th>نامک</th>
                    <th>وضعیت</th>
                    <th>انتشار</th>
                    <th>به‌روزرسانی</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($items)): ?>
                    <tr><td colspan="5">مطلبی یافت نشد.</td></tr>
                <?php else: foreach ($items as $item): ?>
                    <tr>
                        <td><?= e($item['title'] ?? '') ?></td>
                        <td><code><?= e($item['slug'] ?? '') ?></code></td>
                        <td><?= ($item['status'] ?? '') === 'published' ? 'منتشر شده' : 'پیش‌نویس' ?></td>
                        <td><?= e(to_jalali($item['published_at'] ?? null)) ?></td>
                        <td><?= e(to_jalali($item['updated_at'] ?? null)) ?></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
