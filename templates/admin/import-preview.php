<?php
/** @var array $import */
/** @var array $inspect */
$sheets = $inspect['sheets'] ?? [];
$previewReport = $_SESSION['_import_preview'] ?? null;
unset($_SESSION['_import_preview']);
$conflicts = $conflicts ?? [];
$previous = $inspect['previous_imports'] ?? [];
$skippedExcel = !empty($inspect['skipped_excel']);
$status = (string) ($import['status'] ?? '');
$isCompleted = in_array($status, ['completed', 'failed', 'processing'], true);
$report = $previewReport ?: (json_decode((string) ($import['report_json'] ?? ''), true) ?: []);
unset($report['_chunk']);
$csrf = $csrf ?? csrf_token();
$fields = [
    'file_number' => 'شماره پرونده',
    'first_name' => 'نام',
    'last_name' => 'نام خانوادگی',
    'father_name' => 'نام پدر',
    'birth_year_jalali' => 'سال تولد (شمسی)',
    'mobile' => 'تلفن همراه',
    'landline' => 'تلفن ثابت',
    'referrer' => 'معرف',
    'national_id' => 'کد ملی',
    'gender' => 'جنسیت',
    'email' => 'ایمیل',
    'address' => 'آدرس',
];
$patientSheetIndex = 0;
foreach ($sheets as $i => $s) {
    if (!empty($s['is_patient_sheet'])) {
        $patientSheetIndex = (int) ($s['index'] ?? $i);
        break;
    }
}
$active = $sheets[$patientSheetIndex] ?? ($sheets[0] ?? []);
$guess = $active['guess'] ?? [];
if (!empty($active['is_patient_sheet'])) {
    $guess = array_merge([
        'file_number' => 'A', 'first_name' => 'B', 'last_name' => 'C', 'father_name' => 'D',
        'birth_year_jalali' => 'E', 'mobile' => 'F', 'landline' => 'G', 'referrer' => 'H',
    ], $guess);
}
$letters = $active['letters'] ?? array_keys($active['columns'] ?? []);
$importId = (int) ($import['id'] ?? 0);
?>
<div class="admin-card">
    <h2 style="margin:0 0 8px;color:#031D4F;"><?= $status === 'processing' ? 'در حال واردسازی…' : ($status === 'completed' ? 'نتیجه ورود اکسل' : 'واردسازی یک‌باره از Excel') ?></h2>
    <p style="color:#6b7280;margin:0;">
        فایل: <code><?= e($import['original_name'] ?? $import['filename'] ?? '') ?></code>
        · وضعیت: <?= e($status) ?>
    </p>
    <p style="margin:10px 0 0;color:#6b7280;font-size:.9rem;line-height:1.7;">
        این ابزار برای <strong>یک‌بار</strong> پر کردن بیماران فعلی است. بعد از اتمام، بیماران جدید را دستی از «ایجاد پرونده» ثبت کنید.
    </p>
    <?php if ($status === 'failed' && !empty($report['error'])): ?>
        <p class="auth-alert error" style="margin:10px 0 0;"><?= e((string) $report['error']) ?></p>
    <?php endif; ?>
    <?php if ($skippedExcel && $status === 'completed'): ?>
        <p style="margin:10px 0 0;color:#067a5f;font-size:.9rem;">
            واردسازی تکمیل شده است.
            <a href="<?= url('/admin/patients') ?>">مشاهده بیماران</a>
            ·
            <a href="<?= url('/admin/patients/create') ?>">ایجاد پرونده جدید</a>
        </p>
    <?php elseif ($skippedExcel && $status === 'processing'): ?>
        <p style="margin:10px 0 0;color:#92400e;">واردسازی ناتمام مانده — می‌توانید از دکمه زیر ادامه دهید یا صفحه را تازه کنید.</p>
    <?php endif; ?>
</div>

<?php if ($report && ($previewReport || $isCompleted)): ?>
<div class="admin-card" id="import-report-card">
    <h3 style="color:#031D4F;">خلاصه</h3>
    <div class="stat-grid">
        <div class="stat-card"><h3 id="stat-total"><?= (int) ($report['total'] ?? $import['total_rows'] ?? 0) ?></h3><p>کل رکوردهای اکسل</p></div>
        <div class="stat-card"><h3 id="stat-imported"><?= (int) ($report['imported'] ?? $import['imported_rows'] ?? 0) ?></h3><p>بیماران جدید</p></div>
        <div class="stat-card"><h3 id="stat-duplicates"><?= (int) ($report['duplicates'] ?? $import['duplicate_rows'] ?? 0) ?></h3><p>تکراری / موجود</p></div>
        <div class="stat-card"><h3 id="stat-excel-dups"><?= (int) ($report['excel_duplicates'] ?? 0) ?></h3><p>تکرار شماره پرونده در اکسل</p></div>
        <div class="stat-card"><h3 id="stat-invalid"><?= (int) ($report['invalid'] ?? $import['invalid_rows'] ?? 0) ?></h3><p>نامعتبر</p></div>
        <div class="stat-card"><h3 id="stat-skipped"><?= (int) ($report['skipped'] ?? $import['skipped_rows'] ?? 0) ?></h3><p>رد شده</p></div>
    </div>
    <p style="color:#6b7280;font-size:.9rem;margin:8px 0 0;">
        هر <strong>شماره پرونده</strong> فقط یک‌بار ذخیره می‌شود؛ ردیف‌های تکراری داخل اکسل نادیده گرفته می‌شوند.
    </p>
</div>
<?php endif; ?>

<?php if (!($inspect['ok'] ?? false) && !$skippedExcel): ?>
<div class="admin-card"><p class="auth-alert error"><?= e((string) ($inspect['message'] ?? 'امکان خواندن فایل وجود ندارد.')) ?></p></div>
<?php elseif (!$skippedExcel && $sheets): ?>

<div class="admin-card">
    <h3 style="color:#031D4F;">نمونه برگه بیماران</h3>
    <?php foreach ($sheets as $sheet): ?>
        <p>
            <strong><?= e($sheet['title']) ?></strong>
            — حدود <?= (int) ($sheet['row_count'] ?? 0) ?> ردیف
            <?= !empty($sheet['is_patient_sheet']) ? '· <span class="admin-badge admin-badge--ok">برگه بیماران</span>' : '' ?>
        </p>
    <?php endforeach; ?>
    <div style="overflow:auto;max-height:220px;">
        <table class="admin-table">
            <thead>
                <tr>
                    <?php foreach (($active['columns'] ?? []) as $letter => $col): ?>
                        <th><?= e((string) $letter) ?> · <?= e((string) $col) ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach (($active['sample'] ?? []) as $row): ?>
                    <tr>
                        <?php foreach ($row as $cell): ?>
                            <td><?= e((string) $cell) ?></td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="admin-card" id="chunk-import-card">
    <h3 style="color:#031D4F;">شروع واردسازی (بهینه برای هاست اشتراکی)</h3>
    <p style="color:#6b7280;margin:0 0 12px;font-size:.92rem;line-height:1.7;">
        فایل در تکه‌های کوچک پردازش می‌شود تا سرور قفل نشود. صفحه را تا پایان باز نگه دارید.
    </p>

    <div id="import-progress-wrap" hidden style="margin:0 0 16px;">
        <div style="display:flex;justify-content:space-between;margin-bottom:6px;font-size:.9rem;color:#374151;">
            <span id="import-progress-label">آماده‌سازی…</span>
            <span id="import-progress-pct">0%</span>
        </div>
        <div style="height:12px;background:#e5e7eb;border-radius:999px;overflow:hidden;">
            <div id="import-progress-bar" style="height:100%;width:0%;background:#05B18B;transition:width .25s ease;"></div>
        </div>
        <p id="import-progress-detail" style="margin:8px 0 0;color:#6b7280;font-size:.85rem;"></p>
    </div>

    <form id="chunk-import-form" method="post" action="<?= url('/admin/imports/' . $importId . '/run') ?>" class="row g-3">
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
        <div class="col-md-4">
            <label class="form-label">برگه بیماران</label>
            <select name="sheet_index" class="form-select">
                <?php foreach ($sheets as $i => $sheet): ?>
                    <option value="<?= (int) ($sheet['index'] ?? $i) ?>" <?= ($patientSheetIndex === (int) ($sheet['index'] ?? $i)) ? 'selected' : '' ?>>
                        <?= e($sheet['title']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12">
            <label class="form-check">
                <input type="checkbox" name="use_sarva_mapping" value="1" class="form-check-input" id="use-sarva-mapping" checked>
                استفاده از نگاشت استاندارد سروا (ستون‌های A–H برگه پرونده)
            </label>
        </div>
        <div class="col-12">
            <label class="form-check">
                <input type="checkbox" name="confirm_import" value="1" class="form-check-input" id="confirm-import" required>
                تأیید می‌کنم این واردسازی یک‌باره است و بیماران بعدی را دستی ثبت می‌کنم
            </label>
        </div>
        <div class="col-12 d-flex gap-2 flex-wrap">
            <button type="button" class="btn" id="chunk-import-start" style="background:#05B18B;color:#fff;border:0;">شروع واردسازی یک‌باره</button>
            <button type="submit" class="btn btn-outline-primary" name="preview_only" value="1" formnovalidate>پیش‌نمایش بدون ذخیره</button>
        </div>
    </form>

    <details style="margin-top:18px;">
        <summary style="cursor:pointer;color:#031D4F;">تنظیمات پیشرفته نگاشت ستون‌ها (اختیاری)</summary>
        <div class="row g-3 mt-1">
            <?php foreach ($fields as $field => $label): ?>
            <div class="col-md-3">
                <label class="form-label"><?= e($label) ?></label>
                <select name="map_<?= e($field) ?>" class="form-select" form="chunk-import-form">
                    <option value="">—</option>
                    <?php foreach ($letters as $letter): ?>
                        <?php $letter = (string) $letter; ?>
                        <option value="<?= e($letter) ?>" <?= ($guess[$field] ?? '') === $letter ? 'selected' : '' ?>>
                            <?= e($letter) ?> — <?= e((string) (($active['columns'][$letter] ?? $letter))) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endforeach; ?>
        </div>
    </details>
</div>

<script>
(function () {
  var form = document.getElementById('chunk-import-form');
  var startBtn = document.getElementById('chunk-import-start');
  var wrap = document.getElementById('import-progress-wrap');
  var bar = document.getElementById('import-progress-bar');
  var pctEl = document.getElementById('import-progress-pct');
  var label = document.getElementById('import-progress-label');
  var detail = document.getElementById('import-progress-detail');
  var confirmEl = document.getElementById('confirm-import');
  var prepareUrl = <?= json_encode(url('/admin/imports/' . $importId . '/prepare'), JSON_UNESCAPED_UNICODE) ?>;
  var chunkUrl = <?= json_encode(url('/admin/imports/' . $importId . '/chunk'), JSON_UNESCAPED_UNICODE) ?>;
  var doneUrl = <?= json_encode(url('/admin/imports/' . $importId), JSON_UNESCAPED_UNICODE) ?>;
  var busy = false;

  function setProgress(pct, text, extra) {
    wrap.hidden = false;
    bar.style.width = Math.max(0, Math.min(100, pct)) + '%';
    pctEl.textContent = Math.max(0, Math.min(100, pct)) + '%';
    label.textContent = text || '';
    detail.textContent = extra || '';
  }

  function post(url, data) {
    var body = new FormData();
    Object.keys(data).forEach(function (k) { body.append(k, data[k]); });
    // Include mapping fields from form
    Array.prototype.forEach.call(form.elements, function (el) {
      if (!el.name || el.name === 'confirm_import') return;
      if (el.type === 'checkbox' && !el.checked) return;
      if (el.name.indexOf('map_') === 0 || el.name === 'sheet_index' || el.name === 'use_sarva_mapping' || el.name === '_csrf') {
        body.set(el.name, el.value);
      }
    });
    return fetch(url, {
      method: 'POST',
      body: body,
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': form._csrf.value }
    }).then(function (r) { return r.json().then(function (j) { return { status: r.status, json: j }; }); });
  }

  function runChunks() {
    return post(chunkUrl, { chunk_size: '200', _csrf: form._csrf.value }).then(function (res) {
      var j = res.json || {};
      if (!j.ok) throw new Error(j.message || 'خطا در پردازش تکه');
      setProgress(j.percent || 0, 'در حال ذخیره بیماران…',
        'پردازش‌شده: ' + (j.processed || 0) + ' از ' + (j.total || 0)
        + ' · جدید: ' + ((j.report && j.report.imported) || 0)
        + ' · تکراری: ' + ((j.report && j.report.duplicates) || 0));
      if (j.done) return j;
      return runChunks();
    });
  }

  if (startBtn) startBtn.addEventListener('click', function () {
    if (busy) return;
    if (!confirmEl.checked) {
      alert('لطفاً تأیید واردسازی یک‌باره را علامت بزنید.');
      return;
    }
    if (!confirm('واردسازی یک‌باره شروع شود؟ صفحه را تا پایان باز نگه دارید.')) return;
    busy = true;
    startBtn.disabled = true;
    startBtn.textContent = 'در حال واردسازی…';
    setProgress(2, 'آماده‌سازی فایل اکسل…', 'فقط یک‌بار خوانده می‌شود');

    post(prepareUrl, { _csrf: form._csrf.value }).then(function (res) {
      var j = res.json || {};
      if (!j.ok) throw new Error(j.message || 'آماده‌سازی ناموفق بود');
      setProgress(5, 'آماده‌سازی انجام شد', 'ردیف‌های آماده: ' + (j.cache_rows || 0) + ' از ' + (j.total || 0));
      return runChunks();
    }).then(function (j) {
      setProgress(100, 'واردسازی کامل شد', 'جدید: ' + ((j.report && j.report.imported) || 0));
      startBtn.textContent = 'تمام شد';
      setTimeout(function () { window.location.href = doneUrl; }, 800);
    }).catch(function (err) {
      busy = false;
      startBtn.disabled = false;
      startBtn.textContent = 'شروع واردسازی یک‌باره';
      setProgress(0, 'خطا', (err && err.message) ? err.message : 'خطای ناشناخته');
      alert((err && err.message) ? err.message : 'واردسازی با خطا متوقف شد');
    });
  });
})();
</script>
<?php elseif ($status === 'processing'): ?>
<div class="admin-card" id="chunk-import-card">
    <p style="margin:0 0 12px;">واردسازی قبلی ناتمام است. می‌توانید ادامه دهید (بدون خواندن دوباره اکسل).</p>
    <div id="import-progress-wrap" style="margin:0 0 16px;">
        <div style="display:flex;justify-content:space-between;margin-bottom:6px;font-size:.9rem;color:#374151;">
            <span id="import-progress-label">آماده ادامه</span>
            <span id="import-progress-pct">—</span>
        </div>
        <div style="height:12px;background:#e5e7eb;border-radius:999px;overflow:hidden;">
            <div id="import-progress-bar" style="height:100%;width:0%;background:#05B18B;transition:width .25s ease;"></div>
        </div>
        <p id="import-progress-detail" style="margin:8px 0 0;color:#6b7280;font-size:.85rem;"></p>
    </div>
    <button type="button" class="btn" id="chunk-import-resume" style="background:#05B18B;color:#fff;border:0;">ادامه واردسازی</button>
    <a class="btn btn-outline-secondary" href="<?= url('/admin/imports/' . $importId . '?remap=1') ?>">شروع دوباره از اکسل</a>
</div>
<script>
(function () {
  var btn = document.getElementById('chunk-import-resume');
  var bar = document.getElementById('import-progress-bar');
  var pctEl = document.getElementById('import-progress-pct');
  var label = document.getElementById('import-progress-label');
  var detail = document.getElementById('import-progress-detail');
  var chunkUrl = <?= json_encode(url('/admin/imports/' . $importId . '/chunk'), JSON_UNESCAPED_UNICODE) ?>;
  var doneUrl = <?= json_encode(url('/admin/imports/' . $importId), JSON_UNESCAPED_UNICODE) ?>;
  var csrf = <?= json_encode($csrf, JSON_UNESCAPED_UNICODE) ?>;
  function post() {
    var body = new FormData();
    body.append('_csrf', csrf);
    body.append('chunk_size', '200');
    return fetch(chunkUrl, { method: 'POST', body: body, credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf } })
      .then(function (r) { return r.json(); });
  }
  function loop() {
    return post().then(function (j) {
      if (!j.ok) throw new Error(j.message || 'خطا');
      bar.style.width = (j.percent || 0) + '%';
      pctEl.textContent = (j.percent || 0) + '%';
      label.textContent = j.done ? 'تمام شد' : 'در حال ادامه…';
      detail.textContent = (j.processed || 0) + ' / ' + (j.total || 0);
      if (j.done) { setTimeout(function () { location.href = doneUrl; }, 600); return; }
      return loop();
    });
  }
  if (btn) btn.addEventListener('click', function () {
    btn.disabled = true;
    loop().catch(function (e) { btn.disabled = false; alert(e.message || 'خطا'); });
  });
})();
</script>
<?php endif; ?>

<?php if ($conflicts): ?>
<div class="admin-card">
    <h3 style="color:#031D4F;">ردیف‌های نیازمند بررسی</h3>
    <table class="admin-table">
        <thead><tr><th>#ردیف</th><th>نوع</th><th>بیمار موجود</th><th>وضعیت</th></tr></thead>
        <tbody>
        <?php foreach ($conflicts as $c): ?>
            <tr>
                <td><?= (int) $c['row_number'] ?></td>
                <td><?= e($c['match_type'] ?? '') ?></td>
                <td><?php if (!empty($c['existing_patient_id'])): ?><a href="<?= url('/admin/patients/' . (int) $c['existing_patient_id']) ?>">#<?= (int) $c['existing_patient_id'] ?></a><?php else: ?>—<?php endif; ?></td>
                <td><?= e($c['status'] ?? '') ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<p><a href="<?= url('/admin/imports') ?>">بازگشت به فهرست ورودها</a> · <a href="<?= url('/admin/patients') ?>">بیماران</a></p>
