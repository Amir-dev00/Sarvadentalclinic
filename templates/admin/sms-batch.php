<?php
$batch = $batch ?? [];
$queue = $queue ?? ['items' => [], 'page' => 1, 'pages' => 1, 'total' => 0];
$labels = sms_status_labels();
$id = (int) ($batch['id'] ?? 0);
$statusFilter = $statusFilter ?? '';
$modeLabel = \Sarva\Services\SmsAudienceService::modeLabels()[$batch['mode'] ?? ''] ?? ($batch['mode'] ?? '');
?>
<div class="admin-card">
    <div class="d-flex flex-wrap justify-content-between gap-2 align-items-start">
        <div>
            <h2 style="margin:0;color:#031D4F;">ارسال گروهی <?= e($batch['public_code'] ?? '') ?></h2>
            <p style="margin:6px 0 0;color:#6b7280;">
                <?= e($batch['title'] ?? '') ?>
                · <?= e($modeLabel) ?>
                · توسط <?= e($batch['admin_name'] ?? '—') ?>
                · <?= e(to_jalali($batch['created_at'] ?? null)) ?>
            </p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a class="btn btn-sm btn-outline-secondary" href="<?= url('/admin/sms/batches') ?>">همه ارسال‌ها</a>
            <?php if (in_array($batch['status'] ?? '', ['queued', 'processing'], true)): ?>
            <form method="post" action="<?= url('/admin/sms/batches/' . $id . '/cancel') ?>" onsubmit="return confirm('لغو پیام‌های در انتظار این ارسال؟');">
                <?= csrf_field() ?>
                <button class="btn btn-sm btn-outline-danger">لغو زمان‌بندی / صف</button>
            </form>
            <?php endif; ?>
            <?php if ((int) ($batch['failed_count'] ?? 0) > 0): ?>
            <form method="post" action="<?= url('/admin/sms/batches/' . $id . '/retry') ?>" onsubmit="return confirm('ارسال مجدد ناموفق‌ها؟');">
                <?= csrf_field() ?>
                <button class="btn btn-sm btn-outline-warning">ارسال مجدد ناموفق‌ها</button>
            </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="sms-batch-stats" id="smsBatchStats" data-api="<?= e(url('/admin/sms/api/batches/' . $id)) ?>">
    <div class="stat-card"><h3><?= (int) ($batch['queued_count'] ?? 0) ?></h3><p>صف‌شده</p></div>
    <div class="stat-card"><h3 data-k="pending_count"><?= (int) ($batch['pending_count'] ?? 0) ?></h3><p>در انتظار</p></div>
    <div class="stat-card"><h3 data-k="sent_count"><?= (int) ($batch['sent_count'] ?? 0) ?></h3><p>ارسال‌شده</p></div>
    <div class="stat-card"><h3 data-k="failed_count"><?= (int) ($batch['failed_count'] ?? 0) ?></h3><p>ناموفق</p></div>
    <div class="stat-card"><h3 data-k="cancelled_count"><?= (int) ($batch['cancelled_count'] ?? 0) ?></h3><p>لغو شده</p></div>
</div>

<div class="admin-card">
    <p style="color:#6b7280;">
        وضعیت: <strong><?= e($labels[$batch['status'] ?? ''] ?? ($batch['status'] ?? '')) ?></strong>
        · نوع ارسال: <?= ($batch['send_mode'] ?? '') === 'scheduled' ? 'زمان‌بندی‌شده' : 'فوری' ?>
        · زمان: <?= e(to_jalali($batch['scheduled_at'] ?? null)) ?>
        · قالب: <?= e($batch['template_name'] ?? 'سفارشی') ?>
        · بخش تقریبی: <?= (int) ($batch['segments_estimate'] ?? 0) ?>
    </p>
    <?php if (!empty($batch['message_body'])): ?>
    <pre class="sms-preview"><?= e($batch['message_body']) ?></pre>
    <?php endif; ?>
</div>

<div class="admin-card">
    <form method="get" class="row g-2 align-items-end mb-3">
        <div class="col-md-3">
            <select name="status" class="form-select">
                <option value="">همه وضعیت‌ها</option>
                <?php foreach (['pending','processing','retrying','sent','failed','cancelled'] as $st): ?>
                <option value="<?= e($st) ?>" <?= $statusFilter === $st ? 'selected' : '' ?>><?= e($labels[$st] ?? $st) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2"><button class="btn btn-primary" style="background:#031D4F;border:0;">فیلتر</button></div>
    </form>
    <div style="overflow-x:auto;">
        <table class="admin-table">
            <thead>
                <tr><th>بیمار</th><th>موبایل</th><th>وضعیت</th><th>زمان‌بندی</th><th>خطا</th></tr>
            </thead>
            <tbody>
            <?php if (empty($queue['items'])): ?>
                <tr><td colspan="5">موردی نیست.</td></tr>
            <?php else: foreach ($queue['items'] as $item): ?>
                <tr>
                    <td><?= e($item['patient_name'] ?? '—') ?><?php if (!empty($item['file_number'])): ?><br><code><?= e($item['file_number']) ?></code><?php endif; ?></td>
                    <td dir="ltr"><?= e($item['mobile'] ?? '') ?></td>
                    <td><?= e($labels[$item['status'] ?? ''] ?? $item['status']) ?></td>
                    <td><?= e(to_jalali($item['scheduled_at'] ?? null)) ?></td>
                    <td><?= e($item['last_error'] ?? '') ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
<script>
(function () {
  var box = document.getElementById('smsBatchStats');
  if (!box) return;
  var api = box.getAttribute('data-api');
  setInterval(function () {
    fetch(api, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (j) {
        if (!j || !j.ok || !j.batch) return;
        ['pending_count','sent_count','failed_count','cancelled_count'].forEach(function (k) {
          var el = box.querySelector('[data-k="' + k + '"]');
          if (el) el.textContent = j.batch[k] || 0;
        });
      }).catch(function () {});
  }, 8000);
})();
</script>
