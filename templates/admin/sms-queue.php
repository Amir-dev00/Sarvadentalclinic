<?php $items = $items ?? []; $labels = sms_status_labels(); ?>
<div class="admin-card">
    <h2 style="font-size:1.1rem;color:#031D4F;">صف و زمان‌بندی پیامک</h2>
    <p style="color:#6b7280;">این صف توسط Cron پردازش می‌شود.</p>
    <table class="admin-table">
        <thead><tr><th>بیمار</th><th>موبایل</th><th>زمان ارسال</th><th>وضعیت</th><th>تلاش</th><th></th></tr></thead>
        <tbody>
        <?php if (empty($items)): ?><tr><td colspan="6">صف خالی است.</td></tr>
        <?php else: foreach ($items as $item): ?>
            <tr>
                <td><?= e($item['patient_name'] ?? '—') ?></td>
                <td dir="ltr"><?= e($item['mobile']) ?></td>
                <td><?= e(to_jalali($item['scheduled_at'])) ?></td>
                <td><?= e($labels[$item['status'] ?? ''] ?? $item['status']) ?></td>
                <td><?= (int) $item['attempts'] ?>/<?= (int) $item['max_attempts'] ?></td>
                <td>
                    <?php if (in_array($item['status'], ['pending', 'retrying'], true)): ?>
                    <form method="post" action="<?= url('/admin/sms/queue/cancel') ?>"><?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
                        <button class="btn btn-sm btn-outline-danger">لغو</button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>
