<?php $items = $items ?? []; ?>
<div class="admin-card">
    <div style="overflow-x:auto;">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>عملیات</th>
                    <th>موجودیت</th>
                    <th>شناسه</th>
                    <th>ادمین</th>
                    <th>بیمار</th>
                    <th>IP</th>
                    <th>زمان</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($items)): ?>
                    <tr><td colspan="8">گزارشی یافت نشد.</td></tr>
                <?php else: foreach ($items as $item): ?>
                    <tr>
                        <td><?= (int) $item['id'] ?></td>
                        <td><code><?= e($item['action'] ?? '') ?></code></td>
                        <td><?= e($item['entity_type'] ?? '—') ?></td>
                        <td><?= e((string) ($item['entity_id'] ?? '—')) ?></td>
                        <td><?= e((string) ($item['admin_user_id'] ?? '—')) ?></td>
                        <td><?= e((string) ($item['patient_id'] ?? '—')) ?></td>
                        <td><?= e($item['ip_address'] ?? '—') ?></td>
                        <td><?= e($item['created_at'] ?? '') ?></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
