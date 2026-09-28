<?php
$items = $items ?? [];
$query = $query ?? [];
$page = (int) ($page ?? 1);
$pages = (int) ($pages ?? 1);
$labels = sms_status_labels();
$types = sms_type_labels();
?>
<div class="admin-card">
    <form method="get" class="row g-2 align-items-end mb-3">
        <div class="col-md-3"><input name="q" class="form-control" placeholder="بیمار / موبایل / متن" value="<?= e($query['q'] ?? '') ?>"></div>
        <div class="col-md-2">
            <select name="status" class="form-select"><option value="">وضعیت</option>
            <?php foreach ($labels as $k => $l): ?><option value="<?= e($k) ?>" <?= ($query['status'] ?? '') === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <select name="type" class="form-select"><option value="">نوع</option>
            <?php foreach ($types as $k => $l): ?><option value="<?= e($k) ?>" <?= ($query['type'] ?? '') === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <select name="source" class="form-select">
                <option value="">منبع</option>
                <option value="manual" <?= ($query['source'] ?? '') === 'manual' ? 'selected' : '' ?>>دستی</option>
                <option value="automation" <?= ($query['source'] ?? '') === 'automation' ? 'selected' : '' ?>>خودکار</option>
                <option value="scheduled" <?= ($query['source'] ?? '') === 'scheduled' ? 'selected' : '' ?>>زمان‌بندی</option>
            </select>
        </div>
        <div class="col-md-2">
            <input name="batch_id" class="form-control" placeholder="شناسه batch" value="<?= e($query['batch_id'] ?? '') ?>">
        </div>
        <div class="col-md-2"><input type="text" name="date_from" class="form-control" data-jalali="date" placeholder="از تاریخ" value="<?= e($query['date_from'] ?? '') ?>"></div>
        <div class="col-md-1"><button class="btn btn-primary w-100" style="background:#031D4F;border:0;">فیلتر</button></div>
    </form>
    <div style="overflow-x:auto;">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>بیمار</th><th>موبایل</th><th>متن</th><th>قالب</th><th>نوع</th><th>منبع</th><th>وضعیت</th><th>ارسال‌کننده</th><th>ارائه‌دهنده</th><th>زمان</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($items)): ?>
                    <tr><td colspan="10">گزارشی نیست.</td></tr>
                <?php else: foreach ($items as $item): ?>
                    <tr>
                        <td><?php if (!empty($item['patient_id'])): ?><a href="<?= url('/admin/patients/' . (int) $item['patient_id']) ?>"><?= e($item['patient_name'] ?? '—') ?></a><?php else: ?><?= e($item['patient_name'] ?? '—') ?><?php endif; ?></td>
                        <td dir="ltr"><?= e($item['mobile'] ?? '') ?></td>
                        <td style="max-width:280px;"><?= e(mb_substr((string) ($item['message_body'] ?? ''), 0, 90)) ?></td>
                        <td><?= e($item['template_name'] ?? '—') ?></td>
                        <td><?= e($types[$item['message_type'] ?? ''] ?? $item['message_type']) ?></td>
                        <td><?= e($item['source'] ?? '') ?><?php if (!empty($item['batch_id'])): ?><br><a href="<?= url('/admin/sms/batches/' . (int) $item['batch_id']) ?>">#<?= (int) $item['batch_id'] ?></a><?php endif; ?></td>
                        <td><?= e($labels[$item['status'] ?? ''] ?? $item['status']) ?><?php if (!empty($item['last_error'])): ?><br><small style="color:#a33;"><?= e($item['last_error']) ?></small><?php endif; ?></td>
                        <td><?= e($item['admin_name'] ?? 'سیستم') ?></td>
                        <td><?= e($item['provider'] ?? '') ?><?php if (!empty($item['provider_message_id'])): ?><br><code><?= e($item['provider_message_id']) ?></code><?php endif; ?></td>
                        <td><?= e(to_jalali($item['sent_at'] ?? $item['created_at'] ?? null)) ?></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($pages > 1): ?>
    <nav class="admin-pager">
        <?php for ($i = 1; $i <= min($pages, 20); $i++): ?>
            <a class="<?= $i === $page ? 'is-active' : '' ?>" href="<?= e(pager_url($query, $i)) ?>"><?= $i ?></a>
        <?php endfor; ?>
    </nav>
    <?php endif; ?>
</div>
