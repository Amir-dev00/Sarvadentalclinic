<?php
$items = $items ?? [];
$statusLabels = [
    'initiated' => 'آغاز شده',
    'pending' => 'در انتظار',
    'paid' => 'پرداخت شده',
    'failed' => 'ناموفق',
    'cancelled' => 'لغو شده',
];
$canManagePay = \Sarva\Core\Auth::adminHasPermission('payments.manage');
?>
<div class="admin-card">
    <div style="overflow-x:auto;">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>نوبت</th>
                    <th>بیمار</th>
                    <th>مبلغ</th>
                    <th>درگاه</th>
                    <th>Authority</th>
                    <th>وضعیت</th>
                    <th>تأیید</th>
                    <th>ایجاد</th>
                    <?php if ($canManagePay): ?><th></th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($items)): ?>
                    <tr><td colspan="<?= $canManagePay ? 10 : 9 ?>">پرداختی یافت نشد.</td></tr>
                <?php else: foreach ($items as $item): ?>
                    <tr>
                        <td><?= (int) $item['id'] ?></td>
                        <td><?= (int) ($item['appointment_id'] ?? 0) ?></td>
                        <td>
                            <?php if (!empty($item['patient_id'])): ?>
                                <a href="<?= url('/admin/patients/' . (int) $item['patient_id']) ?>"><?= e(trim((string) ($item['patient_name'] ?? '')) ?: ('#' . (int) $item['patient_id'])) ?></a>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                        <td><?= number_format((float) ($item['amount'] ?? 0)) ?> تومان</td>
                        <td><?= e($item['provider'] ?? '') ?></td>
                        <td><code><?= e($item['authority'] ?? '') ?></code></td>
                        <td><?= e($statusLabels[$item['status'] ?? ''] ?? ($item['status'] ?? '')) ?></td>
                        <td><?= e(to_jalali($item['verified_at'] ?? null) ?: '—') ?></td>
                        <td><?= e(to_jalali($item['created_at'] ?? null)) ?></td>
                        <?php if ($canManagePay): ?>
                        <td>
                            <form method="post" action="<?= url('/admin/payments/delete') ?>" style="margin:0;" onsubmit="return confirm('این پرداخت حذف (لغو) شود؟');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger">حذف</button>
                            </form>
                        </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
