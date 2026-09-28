<?php
$items = $items ?? [];
$filters = $filters ?? [];
$query = $query ?? [];
$page = (int) ($page ?? 1);
$pages = (int) ($pages ?? 1);
$total = (int) ($total ?? 0);
?>
<div class="crm-toolbar admin-card">
    <div class="d-flex flex-wrap justify-content-between gap-2 align-items-center mb-3">
        <div>
            <h2 style="margin:0;font-size:1.1rem;color:#031D4F;">همه بیماران</h2>
            <p style="margin:4px 0 0;color:#6b7280;font-size:.9rem;">
                <?= number_format($total) ?>
                <?php
                $st = (string) ($filters['status'] ?? 'active');
                echo $st === 'archived' ? ' پرونده بایگانی' : ' پرونده فعال';
                ?>
            </p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <?php if (\Sarva\Core\Auth::adminHasPermission('sms.send') || \Sarva\Core\Auth::adminHasPermission('sms.send.bulk')): ?>
            <button type="button" class="btn btn-sm btn-outline-success" id="patientsBulkSms" disabled>ارسال پیامک به انتخاب‌شده‌ها</button>
            <?php endif; ?>
            <?php if (\Sarva\Core\Auth::adminHasPermission('patients.import') || \Sarva\Core\Auth::adminHasPermission('patients.manage')): ?>
            <a href="<?= url('/admin/imports') ?>" class="btn btn-outline-secondary btn-sm">ورود Excel</a>
            <?php endif; ?>
            <?php if (\Sarva\Core\Auth::adminHasPermission('patients.manage')): ?>
            <button type="button" class="btn btn-sm btn-outline-danger" id="purgeAllOpen">
                حذف همه بیماران
            </button>
            <?php endif; ?>
            <a href="<?= url('/admin/patients/create') ?>" class="btn btn-sm" style="background:#05B18B;border-color:#05B18B;color:#fff;">ایجاد پرونده</a>
        </div>
    </div>
    <form method="get" action="<?= url('/admin/patients') ?>" class="row g-2 align-items-end">
        <div class="col-md-4">
            <label class="form-label">جستجو</label>
            <input type="search" name="q" class="form-control" value="<?= e($filters['q'] ?? '') ?>" placeholder="نام، موبایل، شماره پرونده، کد ملی...">
        </div>
        <div class="col-md-2">
            <label class="form-label">منبع</label>
            <select name="source" class="form-select">
                <option value="">همه</option>
                <option value="manual" <?= ($filters['source'] ?? '') === 'manual' ? 'selected' : '' ?>>دستی</option>
                <option value="imported" <?= ($filters['source'] ?? '') === 'imported' ? 'selected' : '' ?>>Excel</option>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label">وضعیت</label>
            <select name="status" class="form-select">
                <option value="active" <?= ($filters['status'] ?? '') === 'active' ? 'selected' : '' ?>>فعال</option>
                <option value="archived" <?= ($filters['status'] ?? '') === 'archived' ? 'selected' : '' ?>>بایگانی</option>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label">پزشک</label>
            <select name="doctor_id" class="form-select">
                <option value="">همه</option>
                <?php foreach (($doctors ?? []) as $d): ?>
                <option value="<?= (int) $d['id'] ?>" <?= (int) ($filters['doctor_id'] ?? 0) === (int) $d['id'] ? 'selected' : '' ?>><?= e(trim($d['first_name'] . ' ' . $d['last_name'])) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label">خدمت</label>
            <select name="service_id" class="form-select">
                <option value="">همه</option>
                <?php foreach (($services ?? []) as $s): ?>
                <option value="<?= (int) $s['id'] ?>" <?= (int) ($filters['service_id'] ?? 0) === (int) $s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-check mb-2">
                <input type="checkbox" name="upcoming" value="1" class="form-check-input" <?= !empty($filters['upcoming']) ? 'checked' : '' ?>>
                نوبت پیش‌رو
            </label>
            <button class="btn btn-primary w-100" style="background:#031D4F;border-color:#031D4F;">اعمال</button>
        </div>
    </form>
</div>

<div class="admin-card">
    <div style="overflow-x:auto;">
        <table class="admin-table">
            <thead>
                <tr>
                    <th style="width:36px;"><input type="checkbox" id="patientsSelectAll" title="انتخاب صفحه"></th>
                    <th>شماره پرونده</th>
                    <th>نام و نام خانوادگی</th>
                    <th>موبایل</th>
                    <th>کد ملی</th>
                    <th>آخرین مراجعه</th>
                    <th>نوبت بعدی</th>
                    <th>جلسات</th>
                    <th>منبع</th>
                    <th>وضعیت</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($items)): ?>
                    <tr><td colspan="11" class="text-center py-4" style="color:#6b7280;">بیماری با این فیلتر یافت نشد.</td></tr>
                <?php else: foreach ($items as $item): ?>
                    <?php $name = trim(($item['first_name'] ?? '') . ' ' . ($item['last_name'] ?? '')); ?>
                    <tr>
                        <td><input type="checkbox" class="patient-row-check" value="<?= (int) $item['id'] ?>"></td>
                        <td><code><?= e($item['file_number'] ?: ($item['public_code'] ?? '')) ?></code></td>
                        <td><a href="<?= url('/admin/patients/' . (int) $item['id']) ?>"><?= e($name !== '' ? $name : '—') ?></a></td>
                        <td dir="ltr"><?= e($item['mobile'] ?? '—') ?></td>
                        <td><?= e($item['national_id'] ?? '—') ?></td>
                        <td><?= e(to_jalali($item['last_visit_at'] ?? $item['last_appointment'] ?? null, 'Y/m/d')) ?></td>
                        <td><?= e(to_jalali($item['next_appointment'] ?? null, 'Y/m/d H:i')) ?></td>
                        <td><?= (int) ($item['visit_count'] ?? 0) ?></td>
                        <td><?= !empty($item['is_imported']) ? '<span class="admin-badge">Excel</span>' : '<span class="admin-badge admin-badge--ok">دستی</span>' ?></td>
                        <td><?= !empty($item['deleted_at']) ? 'بایگانی' : (!empty($item['is_active']) ? 'فعال' : 'غیرفعال') ?></td>
                        <td style="white-space:nowrap;">
                            <a class="btn btn-sm btn-outline-primary" href="<?= url('/admin/patients/' . (int) $item['id']) ?>">مشاهده پرونده</a>
                            <?php if (empty($item['deleted_at'])): ?>
                            <a class="btn btn-sm btn-outline-secondary" href="<?= url('/admin/patients/' . (int) $item['id'] . '/edit') ?>">ویرایش</a>
                            <a class="btn btn-sm btn-outline-success" href="<?= url('/admin/patients/' . (int) $item['id'] . '?tab=visits&new=1') ?>">ثبت جلسه</a>
                            <a class="btn btn-sm btn-outline-secondary" href="<?= url('/admin/appointments?patient_id=' . (int) $item['id']) ?>">نوبت</a>
                            <a class="btn btn-sm btn-outline-success" href="<?= url('/admin/sms/send?preset=selected&patient_ids[]=' . (int) $item['id']) ?>">پیامک</a>
                            <?php if (\Sarva\Core\Auth::adminHasPermission('patients.archive') || \Sarva\Core\Auth::adminHasPermission('patients.manage')): ?>
                            <form method="post" action="<?= url('/admin/patients/' . (int) $item['id'] . '/archive') ?>" style="display:inline;" onsubmit="return confirm('این بیمار بایگانی (حذف از لیست فعال) شود؟');">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-sm btn-outline-danger">حذف</button>
                            </form>
                            <?php endif; ?>
                            <?php else: ?>
                            <?php if (\Sarva\Core\Auth::adminHasPermission('patients.archive') || \Sarva\Core\Auth::adminHasPermission('patients.manage')): ?>
                            <form method="post" action="<?= url('/admin/patients/' . (int) $item['id'] . '/restore') ?>" style="display:inline;">
                                <?= csrf_field() ?>
                                <input type="hidden" name="from" value="list">
                                <button type="submit" class="btn btn-sm btn-outline-success">بازیابی</button>
                            </form>
                            <?php endif; ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($pages > 1): ?>
    <nav class="admin-pager">
        <?php for ($i = 1; $i <= $pages; $i++): ?>
            <a class="<?= $i === $page ? 'is-active' : '' ?>" href="<?= e(pager_url($query, $i)) ?>"><?= $i ?></a>
        <?php endfor; ?>
    </nav>
    <?php endif; ?>
</div>

<?php if (\Sarva\Core\Auth::adminHasPermission('patients.manage')): ?>
<div id="purgeAllPanel" hidden style="position:fixed;inset:0;background:rgba(3,29,79,.45);z-index:9999;display:flex;align-items:center;justify-content:center;padding:16px;">
    <form method="post" action="<?= url('/admin/patients/purge-all') ?>" class="admin-card" style="max-width:520px;width:100%;margin:0;box-shadow:0 16px 40px rgba(0,0,0,.2);" id="purgeAllForm">
        <?= csrf_field() ?>
        <h3 style="margin:0 0 8px;color:#9a3412;">حذف همه بیماران</h3>
        <p style="color:#6b7280;margin:0 0 12px;font-size:.92rem;">
            این کار <strong>غیرقابل بازگشت</strong> است: همه پرونده‌ها به‌همراه نوبت‌ها، پرداخت‌ها، یادداشت‌ها و مدارک بیماران حذف می‌شوند.
            فایل اکسل دوباره وارد نمی‌شود.
        </p>
        <label class="form-label">برای تأیید، عبارت <code>حذف همه</code> را بنویسید</label>
        <input type="text" name="confirm_phrase" class="form-control" autocomplete="off" required placeholder="حذف همه">
        <div class="d-flex gap-2 mt-3 flex-wrap">
            <button type="submit" class="btn btn-danger" id="purgeAllSubmit">تأیید حذف همه</button>
            <button type="button" class="btn btn-outline-secondary" id="purgeAllClose">انصراف</button>
        </div>
    </form>
</div>
<?php endif; ?>

<script>
(function () {
  var all = document.getElementById('patientsSelectAll');
  var btn = document.getElementById('patientsBulkSms');
  var checks = function () { return Array.prototype.slice.call(document.querySelectorAll('.patient-row-check')); };
  function sync() {
    var ids = checks().filter(function (c) { return c.checked; }).map(function (c) { return c.value; });
    if (btn) btn.disabled = ids.length === 0;
  }
  if (all) all.addEventListener('change', function () {
    checks().forEach(function (c) { c.checked = all.checked; });
    sync();
  });
  document.addEventListener('change', function (e) {
    if (e.target && e.target.classList && e.target.classList.contains('patient-row-check')) sync();
  });
  if (btn) btn.addEventListener('click', function () {
    var ids = checks().filter(function (c) { return c.checked; }).map(function (c) { return c.value; });
    if (!ids.length) return;
    var url = <?= json_encode(url('/admin/sms/send'), JSON_UNESCAPED_UNICODE) ?> + '?preset=selected';
    ids.forEach(function (id) { url += '&patient_ids[]=' + encodeURIComponent(id); });
    window.location.href = url;
  });

  var panel = document.getElementById('purgeAllPanel');
  var openBtn = document.getElementById('purgeAllOpen');
  var closeBtn = document.getElementById('purgeAllClose');
  var form = document.getElementById('purgeAllForm');
  var submitBtn = document.getElementById('purgeAllSubmit');
  function showPanel(on) {
    if (!panel) return;
    panel.hidden = !on;
    panel.style.display = on ? 'flex' : 'none';
  }
  if (panel) panel.style.display = 'none';
  if (openBtn) openBtn.addEventListener('click', function () { showPanel(true); });
  if (closeBtn) closeBtn.addEventListener('click', function () { showPanel(false); });
  if (panel) panel.addEventListener('click', function (e) {
    if (e.target === panel) showPanel(false);
  });
  if (form) form.addEventListener('submit', function (e) {
    var phrase = (form.confirm_phrase && form.confirm_phrase.value || '').trim();
    if (phrase !== 'حذف همه') {
      e.preventDefault();
      alert('عبارت تأیید باید دقیقاً «حذف همه» باشد.');
      return;
    }
    if (!confirm('آخرین هشدار: همه بیماران برای همیشه حذف می‌شوند. ادامه؟')) {
      e.preventDefault();
      return;
    }
    if (submitBtn) {
      submitBtn.disabled = true;
      submitBtn.textContent = 'در حال حذف…';
    }
  });
})();
</script>
