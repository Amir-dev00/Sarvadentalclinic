<?php $items = $items ?? []; ?>
<div class="admin-card">
    <div style="overflow-x:auto;">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>عنوان</th>
                    <th>نامک</th>
                    <th>وضعیت</th>
                    <th>به‌روزرسانی</th>
                    <th>عملیات</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($items)): ?>
                    <tr><td colspan="5">صفحه‌ای یافت نشد.</td></tr>
                <?php else: foreach ($items as $item): ?>
                    <?php
                    $slug = (string) ($item['slug'] ?? '');
                    $preview = $slug === 'home' ? url('/') : url('/' . ltrim($slug, '/'));
                    ?>
                    <tr>
                        <td><?= e($item['title'] ?? '') ?></td>
                        <td><code><?= e($slug) ?></code></td>
                        <td><?= ($item['status'] ?? '') === 'published' ? 'منتشر شده' : 'پیش‌نویس' ?></td>
                        <td><?= e($item['updated_at'] ?? '') ?></td>
                        <td>
                            <a href="<?= url('/admin/pages/' . (int) $item['id']) ?>">ویرایش</a>
                            &nbsp;|&nbsp;
                            <a href="<?= e($preview) ?>" target="_blank" rel="noopener">پیش‌نمایش</a>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
