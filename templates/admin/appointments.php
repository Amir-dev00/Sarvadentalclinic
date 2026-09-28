<?php
$items = $items ?? [];
$doctors = $doctors ?? [];
$services = $services ?? [];
$selectedPatient = $selected_patient ?? null;
$selectedPatientId = (int) ($selected_patient_id ?? ($selectedPatient['id'] ?? ($_GET['patient_id'] ?? 0)));
$slotsUrl = $slots_url ?? url('/api/appointment/slots');
$patientSearchUrl = $patient_search_url ?? url('/admin/patients/api/quick-search');
$statusLabels = [
    'awaiting_payment' => 'در انتظار پرداخت',
    'confirmed' => 'تأیید شده',
    'completed' => 'انجام شده',
    'cancelled' => 'لغو شده',
    'no_show' => 'عدم حضور',
    'expired' => 'منقضی',
];
$allowedStatus = ['confirmed', 'completed', 'cancelled', 'no_show'];

$selectedPatientName = '';
$selectedPatientMeta = '';
if ($selectedPatient) {
    $selectedPatientName = trim(($selectedPatient['first_name'] ?? '') . ' ' . ($selectedPatient['last_name'] ?? ''));
    if ($selectedPatientName === '') {
        $selectedPatientName = '#' . (int) $selectedPatient['id'];
    }
    $code = (string) ($selectedPatient['file_number'] ?: ($selectedPatient['public_code'] ?? ''));
    $mobile = (string) ($selectedPatient['mobile'] ?? '');
    $selectedPatientMeta = implode(' — ', array_filter([$code !== '' ? $code : null, $mobile !== '' ? $mobile : null]));
}
?>
<div class="admin-card" id="adminAppointmentCreate">
    <div class="d-flex flex-wrap justify-content-between gap-2 align-items-center mb-3">
        <div>
            <h2 style="margin:0;font-size:1.1rem;color:#031D4F;">ثبت نوبت دستی</h2>
            <p style="margin:4px 0 0;color:#6b7280;font-size:.9rem;">نوبت با وضعیت تأییدشده ثبت می‌شود و در یادآوری پیامک خودکار لحاظ می‌گردد.</p>
        </div>
    </div>
    <form method="post" action="<?= url('/admin/appointments/create') ?>" class="row g-3" id="adminApptForm">
        <?= csrf_field() ?>
        <?php if ($selectedPatientId > 0): ?>
            <input type="hidden" name="return_patient_id" value="<?= $selectedPatientId ?>">
        <?php endif; ?>
        <div class="col-md-4">
            <label class="form-label">بیمار</label>
            <div class="admin-patient-picker" id="adminApptPatientPicker" data-api="<?= e($patientSearchUrl) ?>">
                <input type="hidden" name="patient_id" id="adminApptPatientId" value="<?= $selectedPatientId > 0 ? $selectedPatientId : '' ?>" required>
                <div class="admin-patient-picker__selected" id="adminApptPatientSelected" <?= $selectedPatientId > 0 ? '' : 'hidden' ?>>
                    <div>
                        <strong id="adminApptPatientName"><?= e($selectedPatientName) ?></strong>
                        <span id="adminApptPatientMeta"><?= e($selectedPatientMeta) ?></span>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="adminApptPatientClear">تغییر</button>
                </div>
                <div class="admin-patient-picker__search" id="adminApptPatientSearchWrap" <?= $selectedPatientId > 0 ? 'hidden' : '' ?>>
                    <input type="search" class="form-control" id="adminApptPatientSearch"
                           placeholder="جستجو با نام، نام خانوادگی، موبایل یا شماره پرونده..."
                           autocomplete="off" <?= $selectedPatientId > 0 ? '' : 'required' ?>>
                    <div class="admin-quick-search__results" id="adminApptPatientResults" hidden></div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <label class="form-label">پزشک</label>
            <select name="doctor_id" id="adminApptDoctor" class="form-select" required>
                <option value="">انتخاب پزشک</option>
                <?php foreach ($doctors as $d): ?>
                    <option value="<?= (int) $d['id'] ?>"><?= e(trim(($d['first_name'] ?? '') . ' ' . ($d['last_name'] ?? ''))) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label">خدمت</label>
            <select name="service_id" id="adminApptService" class="form-select" required>
                <option value="">انتخاب خدمت</option>
                <?php foreach ($services as $s): ?>
                    <option value="<?= (int) $s['id'] ?>"><?= e($s['name'] ?? '') ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label">تاریخ نوبت</label>
            <input type="text" name="date" id="adminApptDate" class="form-control" data-jalali="date" placeholder="تاریخ شمسی" required autocomplete="off">
        </div>
        <div class="col-md-3">
            <label class="form-label">ساعت آزاد</label>
            <select name="time" id="adminApptTime" class="form-select" required disabled>
                <option value="">ابتدا تاریخ را انتخاب کنید</option>
            </select>
            <div id="adminApptSlotsStatus" style="margin-top:6px;font-size:.85rem;color:#6b7280;" role="status"></div>
        </div>
        <div class="col-md-3">
            <label class="form-label">وضعیت پرداخت</label>
            <select name="payment_status" class="form-select">
                <option value="unpaid">پرداخت نشده</option>
                <option value="paid">پرداخت شده</option>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label">یادداشت (اختیاری)</label>
            <input type="text" name="notes" class="form-control" placeholder="مثلاً نوبت تلفنی">
        </div>
        <div class="col-12">
            <button type="submit" class="btn" style="background:#05B18B;border:0;color:#fff;">ثبت نوبت</button>
        </div>
    </form>
</div>

<div class="admin-card">
    <?php if ($selectedPatientId > 0): ?>
        <p style="margin:0 0 12px;color:#6b7280;font-size:.9rem;">
            در حال نمایش نوبت‌های بیمار انتخاب‌شده.
            <a href="<?= url('/admin/appointments') ?>">نمایش همه</a>
        </p>
    <?php endif; ?>
    <div style="overflow-x:auto;">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>بیمار</th>
                    <th>خدمت</th>
                    <th>پزشک</th>
                    <th>زمان</th>
                    <th>وضعیت</th>
                    <th>تغییر وضعیت</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($items)): ?>
                    <tr><td colspan="8">نوبتی یافت نشد.</td></tr>
                <?php else: foreach ($items as $item): ?>
                    <tr>
                        <td><?= (int) $item['id'] ?></td>
                        <td><?= e($item['patient_name'] ?? '') ?></td>
                        <td><?= e($item['service_name'] ?? '') ?></td>
                        <td><?= e($item['doctor_name'] ?? '') ?></td>
                        <td><?= e(to_jalali($item['starts_at'] ?? null)) ?></td>
                        <td><?= e($statusLabels[$item['status'] ?? ''] ?? ($item['status'] ?? '')) ?></td>
                        <td>
                            <form method="post" action="<?= url('/admin/appointments/status') ?>" class="d-flex gap-2 align-items-center" style="margin:0;">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
                                <select name="status" class="form-select form-select-sm" style="min-width:140px;">
                                    <?php foreach ($allowedStatus as $st): ?>
                                        <option value="<?= e($st) ?>" <?= ($item['status'] ?? '') === $st ? 'selected' : '' ?>>
                                            <?= e($statusLabels[$st]) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" class="btn btn-sm btn-primary" style="background:#05B18B;border-color:#05B18B;">ثبت</button>
                            </form>
                        </td>
                        <td>
                            <form method="post" action="<?= url('/admin/appointments/delete') ?>" style="margin:0;" onsubmit="return confirm('این نوبت حذف شود؟ زمان آزاد می‌شود و قابل برگشت از لیست نیست.');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
                                <?php if ($selectedPatientId > 0): ?>
                                    <input type="hidden" name="patient_id" value="<?= $selectedPatientId ?>">
                                <?php endif; ?>
                                <button type="submit" class="btn btn-sm btn-outline-danger">حذف</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<style>
.admin-patient-picker { position: relative; }
.admin-patient-picker__selected {
  display: flex; align-items: center; justify-content: space-between; gap: 10px;
  border: 1px solid #e5eaf2; border-radius: 10px; padding: 8px 12px; background: #f8fafc;
}
.admin-patient-picker__selected strong { display: block; color: #031D4F; font-size: .95rem; }
.admin-patient-picker__selected span { display: block; color: #6b7280; font-size: .8rem; margin-top: 2px; }
.admin-patient-picker .admin-quick-search__results { right: 0; left: 0; }
.admin-patient-picker .admin-quick-search__results button {
  display: block; width: 100%; text-align: right; border: 0; background: transparent;
  padding: 8px 12px; color: #031D4F; border-bottom: 1px solid #f1f5f9; cursor: pointer;
}
.admin-patient-picker .admin-quick-search__results button strong { display: block; font-size: .92rem; }
.admin-patient-picker .admin-quick-search__results button span { color: #6b7280; font-size: .78rem; }
.admin-patient-picker .admin-quick-search__results button:hover { background: #f0fbf7; }
</style>

<script>
(function () {
    var slotsUrl = <?= json_encode($slotsUrl, JSON_UNESCAPED_UNICODE) ?>;
    var searchUrl = <?= json_encode($patientSearchUrl, JSON_UNESCAPED_UNICODE) ?>;
    var doctorEl = document.getElementById('adminApptDoctor');
    var serviceEl = document.getElementById('adminApptService');
    var dateEl = document.getElementById('adminApptDate');
    var timeEl = document.getElementById('adminApptTime');
    var statusEl = document.getElementById('adminApptSlotsStatus');
    var patientIdEl = document.getElementById('adminApptPatientId');
    var searchEl = document.getElementById('adminApptPatientSearch');
    var resultsEl = document.getElementById('adminApptPatientResults');
    var selectedWrap = document.getElementById('adminApptPatientSelected');
    var searchWrap = document.getElementById('adminApptPatientSearchWrap');
    var clearBtn = document.getElementById('adminApptPatientClear');
    var nameEl = document.getElementById('adminApptPatientName');
    var metaEl = document.getElementById('adminApptPatientMeta');
    var formEl = document.getElementById('adminApptForm');
    var timer = null;
    var searchTimer = null;

    function escapeHtml(str) {
        return String(str || '').replace(/[&<>"']/g, function (c) {
            return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c];
        });
    }

    function patientMeta(item) {
        var parts = [];
        if (item.file_number) parts.push(item.file_number);
        if (item.mobile) parts.push(item.mobile);
        return parts.join(' — ');
    }

    function selectPatient(item) {
        patientIdEl.value = String(item.id || '');
        nameEl.textContent = item.name || ('#' + item.id);
        metaEl.textContent = patientMeta(item);
        selectedWrap.hidden = false;
        searchWrap.hidden = true;
        searchEl.required = false;
        searchEl.value = '';
        resultsEl.hidden = true;
        resultsEl.innerHTML = '';
    }

    function clearPatient() {
        patientIdEl.value = '';
        selectedWrap.hidden = true;
        searchWrap.hidden = false;
        searchEl.required = true;
        searchEl.value = '';
        resultsEl.hidden = true;
        resultsEl.innerHTML = '';
        searchEl.focus();
    }

    function renderResults(items) {
        if (!items.length) {
            resultsEl.innerHTML = '<div class="qs-empty">نتیجه‌ای یافت نشد</div>';
            resultsEl.hidden = false;
            return;
        }
        resultsEl.innerHTML = items.map(function (item) {
            var meta = patientMeta(item);
            return '<button type="button" data-id="' + item.id + '">'
                + '<strong>' + escapeHtml(item.name || ('#' + item.id)) + '</strong>'
                + (meta ? '<span>' + escapeHtml(meta) + '</span>' : '')
                + '</button>';
        }).join('');
        resultsEl.hidden = false;
        resultsEl.querySelectorAll('button[data-id]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var id = parseInt(btn.getAttribute('data-id'), 10);
                var found = items.find(function (x) { return x.id === id; });
                if (found) selectPatient(found);
            });
        });
    }

    function runSearch(q) {
        q = String(q || '').trim();
        if (q.length < 1) {
            resultsEl.hidden = true;
            resultsEl.innerHTML = '';
            return;
        }
        resultsEl.innerHTML = '<div class="qs-empty">در حال جستجو...</div>';
        resultsEl.hidden = false;
        fetch(searchUrl + '?q=' + encodeURIComponent(q), {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' }
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                renderResults((data && data.items) ? data.items : []);
            })
            .catch(function () {
                resultsEl.innerHTML = '<div class="qs-empty">خطا در جستجو</div>';
                resultsEl.hidden = false;
            });
    }

    if (searchEl) {
        searchEl.addEventListener('input', function () {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(function () { runSearch(searchEl.value); }, 250);
        });
        searchEl.addEventListener('focus', function () {
            if (searchEl.value.trim()) runSearch(searchEl.value);
        });
    }
    if (clearBtn) clearBtn.addEventListener('click', clearPatient);
    document.addEventListener('click', function (e) {
        if (!resultsEl || resultsEl.hidden) return;
        if (!e.target.closest('#adminApptPatientPicker')) {
            resultsEl.hidden = true;
        }
    });
    if (formEl) {
        formEl.addEventListener('submit', function (e) {
            if (!patientIdEl.value) {
                e.preventDefault();
                clearPatient();
                alert('لطفاً بیمار را از نتایج جستجو انتخاب کنید.');
            }
        });
    }

    if (!doctorEl || !serviceEl || !dateEl || !timeEl) return;

    function gregorianDateValue() {
        var iso = String(dateEl.dataset.iso || '').trim();
        if (/^\d{4}-\d{2}-\d{2}/.test(iso)) return iso.slice(0, 10);
        var v = String(dateEl.value || '').trim();
        var m = v.match(/^(\d{4})-(\d{2})-(\d{2})/);
        return m ? (m[1] + '-' + m[2] + '-' + m[3]) : '';
    }

    function resetSlots(message) {
        timeEl.innerHTML = '';
        timeEl.appendChild(new Option(message || 'ساعتی انتخاب نشده', ''));
        timeEl.disabled = true;
        if (statusEl) statusEl.textContent = message || '';
    }

    function loadSlots() {
        var doctorId = doctorEl.value;
        var serviceId = serviceEl.value;
        var date = gregorianDateValue();
        if (!doctorId || !date) {
            resetSlots('پزشک و تاریخ را انتخاب کنید.');
            return;
        }
        resetSlots('در حال بارگذاری ساعات...');
        var url = slotsUrl + '?doctor_id=' + encodeURIComponent(doctorId)
            + '&date=' + encodeURIComponent(date)
            + '&service_id=' + encodeURIComponent(serviceId || '0');
        fetch(url, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                var slots = (data && data.slots) ? data.slots : [];
                timeEl.innerHTML = '';
                if (!slots.length) {
                    timeEl.appendChild(new Option('ساعت آزادی نیست', ''));
                    timeEl.disabled = true;
                    if (statusEl) statusEl.textContent = 'برای این روز ساعت آزادی یافت نشد.';
                    return;
                }
                timeEl.appendChild(new Option('انتخاب ساعت', ''));
                slots.forEach(function (slot) {
                    timeEl.appendChild(new Option(slot, slot));
                });
                timeEl.disabled = false;
                if (statusEl) statusEl.textContent = slots.length + ' ساعت آزاد';
            })
            .catch(function () {
                resetSlots('خطا در دریافت ساعات آزاد.');
            });
    }

    function scheduleLoad() {
        clearTimeout(timer);
        timer = setTimeout(loadSlots, 200);
    }

    doctorEl.addEventListener('change', scheduleLoad);
    serviceEl.addEventListener('change', scheduleLoad);
    dateEl.addEventListener('change', scheduleLoad);
    dateEl.addEventListener('input', scheduleLoad);
    dateEl.addEventListener('blur', scheduleLoad);

    resetSlots('پزشک و تاریخ را انتخاب کنید.');
})();
</script>
