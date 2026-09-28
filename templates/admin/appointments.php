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

<div class="admin-card" id="adminAppointmentsList">
    <?php
    $filterDate = (string) ($filter_date ?? '');
    $dayCancellableCount = (int) ($day_cancellable_count ?? 0);
    $cancellableStatuses = $cancellable_statuses ?? ['confirmed', 'awaiting_payment'];
    $filterDateJalali = $filterDate !== '' ? to_jalali($filterDate . ' 12:00:00', 'Y/m/d') : '';
    ?>
    <div class="appt-list-toolbar">
        <form method="get" action="<?= url('/admin/appointments') ?>" class="appt-filter-form row g-2 align-items-end">
            <?php if ($selectedPatientId > 0): ?>
                <input type="hidden" name="patient_id" value="<?= $selectedPatientId ?>">
            <?php endif; ?>
            <div class="col-auto">
                <label class="form-label mb-1">فیلتر روز</label>
                <input type="text" name="date" id="apptFilterDate" class="form-control form-control-sm" data-jalali="date"
                       value="<?= e($filterDate) ?>" placeholder="تاریخ شمسی" autocomplete="off" style="min-width:150px;">
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-sm btn-outline-primary">اعمال</button>
                <?php if ($filterDate !== '' || $selectedPatientId > 0): ?>
                    <a class="btn btn-sm btn-outline-secondary" href="<?= url('/admin/appointments') ?>">پاک کردن</a>
                <?php endif; ?>
            </div>
        </form>
        <div class="appt-bulk-actions">
            <label class="appt-select-all">
                <input type="checkbox" id="apptSelectAll" title="انتخاب همه">
                <span>انتخاب همه</span>
            </label>
            <button type="button" class="btn btn-sm btn-outline-danger" id="apptBulkCancelBtn" disabled>لغو نوبت‌های انتخاب‌شده</button>
            <?php if ($filterDate !== ''): ?>
                <button type="button" class="btn btn-sm btn-danger" id="apptDayCancelBtn"
                        data-date="<?= e($filterDate) ?>"
                        data-date-jalali="<?= e($filterDateJalali) ?>"
                        data-count="<?= $dayCancellableCount ?>"
                        <?= $dayCancellableCount > 0 ? '' : 'disabled' ?>>
                    لغو تمام نوبت‌های <?= e($filterDateJalali !== '' ? $filterDateJalali : $filterDate) ?>
                </button>
            <?php endif; ?>
        </div>
    </div>
    <?php if ($selectedPatientId > 0): ?>
        <p style="margin:0 0 12px;color:#6b7280;font-size:.9rem;">
            در حال نمایش نوبت‌های بیمار انتخاب‌شده.
            <a href="<?= url('/admin/appointments' . ($filterDate !== '' ? ('?date=' . urlencode($filterDate)) : '')) ?>">نمایش همه</a>
        </p>
    <?php endif; ?>
    <?php if ($filterDate !== ''): ?>
        <p style="margin:0 0 12px;color:#6b7280;font-size:.9rem;">
            فیلتر روز: <strong><?= e($filterDateJalali) ?></strong>
            · نوبت‌های قابل لغو: <strong><?= $dayCancellableCount ?></strong>
        </p>
    <?php endif; ?>

    <div style="overflow-x:auto;">
        <table class="admin-table" id="apptTable">
            <thead>
                <tr>
                    <th style="width:42px;"></th>
                    <th>#</th>
                    <th>بیمار</th>
                    <th>خدمت</th>
                    <th>پزشک</th>
                    <th>زمان</th>
                    <th>وضعیت</th>
                    <th>تغییر وضعیت</th>
                    <th>عملیات</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($items)): ?>
                    <tr><td colspan="9">نوبتی یافت نشد.</td></tr>
                <?php else: foreach ($items as $item):
                    $st = (string) ($item['status'] ?? '');
                    $canCancel = in_array($st, $cancellableStatuses, true);
                    $rowDate = !empty($item['starts_at']) ? date('Y-m-d', strtotime((string) $item['starts_at'])) : '';
                    $rowJalaliDate = !empty($item['starts_at']) ? to_jalali((string) $item['starts_at'], 'Y/m/d') : '';
                    $rowJalaliTime = !empty($item['starts_at']) ? to_jalali((string) $item['starts_at'], 'H:i') : '';
                    ?>
                    <tr class="<?= $canCancel ? 'appt-row--cancellable' : '' ?>">
                        <td>
                            <?php if ($canCancel): ?>
                                <input type="checkbox" class="appt-row-check" value="<?= (int) $item['id'] ?>"
                                       data-patient="<?= e($item['patient_name'] ?? '') ?>"
                                       data-date="<?= e($rowJalaliDate) ?>"
                                       data-time="<?= e($rowJalaliTime) ?>"
                                       data-doctor="<?= e($item['doctor_name'] ?? '') ?>"
                                       data-service="<?= e($item['service_name'] ?? '') ?>">
                            <?php endif; ?>
                        </td>
                        <td><?= (int) $item['id'] ?></td>
                        <td><?= e($item['patient_name'] ?? '') ?></td>
                        <td><?= e($item['service_name'] ?? '') ?></td>
                        <td><?= e($item['doctor_name'] ?? '') ?></td>
                        <td><?= e(to_jalali($item['starts_at'] ?? null)) ?></td>
                        <td><?= e($statusLabels[$st] ?? $st) ?></td>
                        <td>
                            <form method="post" action="<?= url('/admin/appointments/status') ?>" class="d-flex gap-2 align-items-center" style="margin:0;">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
                                <select name="status" class="form-select form-select-sm" style="min-width:130px;">
                                    <?php foreach ($allowedStatus as $opt): ?>
                                        <option value="<?= e($opt) ?>" <?= $st === $opt ? 'selected' : '' ?>>
                                            <?= e($statusLabels[$opt]) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" class="btn btn-sm btn-primary" style="background:#05B18B;border-color:#05B18B;">ثبت</button>
                            </form>
                        </td>
                        <td style="white-space:nowrap;">
                            <?php if ($canCancel): ?>
                                <button type="button" class="btn btn-sm btn-outline-danger appt-cancel-one"
                                        data-id="<?= (int) $item['id'] ?>"
                                        data-patient="<?= e($item['patient_name'] ?? '') ?>"
                                        data-date="<?= e($rowJalaliDate) ?>"
                                        data-time="<?= e($rowJalaliTime) ?>"
                                        data-doctor="<?= e($item['doctor_name'] ?? '') ?>"
                                        data-service="<?= e($item['service_name'] ?? '') ?>">لغو نوبت</button>
                            <?php endif; ?>
                            <form method="post" action="<?= url('/admin/appointments/delete') ?>" style="display:inline;margin:0;" onsubmit="return confirm('این نوبت حذف شود؟ زمان آزاد می‌شود و قابل برگشت از لیست نیست.');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
                                <?php if ($selectedPatientId > 0): ?>
                                    <input type="hidden" name="patient_id" value="<?= $selectedPatientId ?>">
                                <?php endif; ?>
                                <button type="submit" class="btn btn-sm btn-outline-secondary">حذف</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Cancel modal -->
<div class="appt-modal" id="apptCancelModal" hidden>
    <div class="appt-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="apptCancelTitle">
        <div class="appt-modal__head">
            <h3 id="apptCancelTitle">تأیید لغو نوبت</h3>
            <button type="button" class="btn-close" id="apptCancelClose" aria-label="بستن"></button>
        </div>
        <form method="post" id="apptCancelForm">
            <?= csrf_field() ?>
            <input type="hidden" name="id" id="apptCancelId" value="">
            <input type="hidden" name="date" id="apptCancelDayDate" value="">
            <input type="hidden" name="redirect_date" value="<?= e($filterDate) ?>">
            <?php if ($selectedPatientId > 0): ?>
                <input type="hidden" name="patient_id" value="<?= $selectedPatientId ?>">
            <?php endif; ?>
            <div id="apptCancelIdsWrap"></div>
            <div class="appt-modal__body">
                <div id="apptCancelSummary" class="appt-modal__summary"></div>
                <label class="form-label mt-3">دلیل لغو (اختیاری)</label>
                <textarea name="reason" id="apptCancelReason" class="form-control" rows="3" maxlength="500" placeholder="مثلاً تعطیلی کلینیک / پزشک در این روز حضور ندارد"></textarea>
                <label class="form-check mt-3">
                    <input type="checkbox" class="form-check-input" name="send_sms" id="apptCancelSendSms" value="1" checked>
                    <span id="apptCancelSmsLabel">ارسال پیامک لغو به بیمار</span>
                </label>
            </div>
            <div class="appt-modal__foot">
                <button type="button" class="btn btn-outline-secondary" id="apptCancelDismiss">انصراف</button>
                <button type="submit" class="btn btn-danger" id="apptCancelSubmit">تأیید لغو</button>
            </div>
        </form>
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

.appt-list-toolbar {
  display: flex; flex-wrap: wrap; gap: 12px; justify-content: space-between; align-items: flex-end;
  margin-bottom: 14px;
}
.appt-bulk-actions { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
.appt-select-all {
  display: inline-flex; align-items: center; gap: 8px; margin: 0; cursor: pointer;
  font-size: .9rem; color: #334155; min-height: 36px; padding: 4px 6px;
}
.appt-select-all input, .appt-row-check {
  width: 18px; height: 18px; min-width: 18px; cursor: pointer;
}
.appt-modal[hidden] { display: none !important; }
.appt-modal {
  position: fixed; inset: 0; z-index: 1050; background: rgba(15, 23, 42, .45);
  display: flex; align-items: center; justify-content: center; padding: 16px;
}
.appt-modal__dialog {
  width: min(520px, 100%); background: #fff; border-radius: 14px; box-shadow: 0 20px 50px rgba(0,0,0,.2);
  max-height: 90vh; overflow: auto;
}
.appt-modal__head {
  display: flex; align-items: center; justify-content: space-between; gap: 12px;
  padding: 14px 16px; border-bottom: 1px solid #eef2f7;
}
.appt-modal__head h3 { margin: 0; font-size: 1.05rem; color: #031D4F; }
.appt-modal__body { padding: 16px; }
.appt-modal__summary {
  background: #fff7ed; border: 1px solid #fed7aa; border-radius: 10px; padding: 12px;
  color: #9a3412; font-size: .92rem; line-height: 1.7;
}
.appt-modal__foot {
  display: flex; flex-wrap: wrap; gap: 8px; justify-content: flex-end;
  padding: 12px 16px 16px; border-top: 1px solid #eef2f7;
}
@media (max-width: 640px) {
  .appt-list-toolbar { flex-direction: column; align-items: stretch; }
  .appt-bulk-actions .btn { width: 100%; }
  .appt-modal__foot .btn { flex: 1 1 auto; }
}
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

(function () {
    var modal = document.getElementById('apptCancelModal');
    var form = document.getElementById('apptCancelForm');
    var summary = document.getElementById('apptCancelSummary');
    var title = document.getElementById('apptCancelTitle');
    var idInput = document.getElementById('apptCancelId');
    var dayDateInput = document.getElementById('apptCancelDayDate');
    var idsWrap = document.getElementById('apptCancelIdsWrap');
    var smsLabel = document.getElementById('apptCancelSmsLabel');
    var submitBtn = document.getElementById('apptCancelSubmit');
    var selectAll = document.getElementById('apptSelectAll');
    var bulkBtn = document.getElementById('apptBulkCancelBtn');
    var dayBtn = document.getElementById('apptDayCancelBtn');
    var cancelUrl = <?= json_encode(url('/admin/appointments/cancel'), JSON_UNESCAPED_UNICODE) ?>;
    var bulkUrl = <?= json_encode(url('/admin/appointments/cancel-bulk'), JSON_UNESCAPED_UNICODE) ?>;
    var dayUrl = <?= json_encode(url('/admin/appointments/cancel-day'), JSON_UNESCAPED_UNICODE) ?>;
    var submitting = false;

    function checks() {
        return Array.prototype.slice.call(document.querySelectorAll('.appt-row-check'));
    }
    function selectedChecks() {
        return checks().filter(function (c) { return c.checked; });
    }
    function syncBulk() {
        var n = selectedChecks().length;
        if (bulkBtn) bulkBtn.disabled = n === 0;
        if (selectAll) {
            var all = checks();
            selectAll.checked = all.length > 0 && selectedChecks().length === all.length;
            selectAll.indeterminate = n > 0 && n < all.length;
        }
    }
    function closeModal() {
        if (submitting) return;
        modal.hidden = true;
        form.reset();
        document.getElementById('apptCancelSendSms').checked = true;
        idsWrap.innerHTML = '';
        idInput.value = '';
        dayDateInput.value = '';
        submitBtn.disabled = false;
        submitBtn.textContent = 'تأیید لغو';
    }
    function openModal(opts) {
        title.textContent = opts.title || 'تأیید لغو نوبت';
        summary.innerHTML = opts.summaryHtml || '';
        smsLabel.textContent = opts.smsLabel || 'ارسال پیامک لغو به بیمار';
        form.action = opts.action;
        idInput.value = opts.id || '';
        dayDateInput.value = opts.dayDate || '';
        idsWrap.innerHTML = '';
        (opts.ids || []).forEach(function (id) {
            var inp = document.createElement('input');
            inp.type = 'hidden';
            inp.name = 'appointment_ids[]';
            inp.value = String(id);
            idsWrap.appendChild(inp);
        });
        document.getElementById('apptCancelReason').value = '';
        document.getElementById('apptCancelSendSms').checked = true;
        submitBtn.disabled = false;
        submitBtn.textContent = 'تأیید لغو';
        submitting = false;
        modal.hidden = false;
    }

    if (selectAll) {
        selectAll.addEventListener('change', function () {
            checks().forEach(function (c) { c.checked = selectAll.checked; });
            syncBulk();
        });
    }
    checks().forEach(function (c) {
        c.addEventListener('change', syncBulk);
    });
    syncBulk();

    document.querySelectorAll('.appt-cancel-one').forEach(function (btn) {
        btn.addEventListener('click', function () {
            openModal({
                title: 'لغو نوبت',
                action: cancelUrl,
                id: btn.getAttribute('data-id'),
                smsLabel: 'ارسال پیامک لغو به بیمار',
                summaryHtml:
                    '<div><strong>بیمار:</strong> ' + (btn.getAttribute('data-patient') || '—') + '</div>' +
                    '<div><strong>تاریخ:</strong> ' + (btn.getAttribute('data-date') || '—') + '</div>' +
                    '<div><strong>ساعت:</strong> ' + (btn.getAttribute('data-time') || '—') + '</div>' +
                    '<div><strong>پزشک:</strong> ' + (btn.getAttribute('data-doctor') || '—') + '</div>' +
                    '<div><strong>خدمت:</strong> ' + (btn.getAttribute('data-service') || '—') + '</div>' +
                    '<div style="margin-top:8px;">آیا از لغو این نوبت مطمئن هستید؟</div>'
            });
        });
    });

    if (bulkBtn) {
        bulkBtn.addEventListener('click', function () {
            var sel = selectedChecks();
            if (!sel.length) return;
            openModal({
                title: 'لغو نوبت‌های انتخاب‌شده',
                action: bulkUrl,
                ids: sel.map(function (c) { return c.value; }),
                smsLabel: 'ارسال پیامک لغو برای بیماران',
                summaryHtml: '<div><strong>' + sel.length + '</strong> نوبت انتخاب شده است.</div><div>آیا از لغو این نوبت‌ها مطمئن هستید؟</div>'
            });
        });
    }

    if (dayBtn) {
        dayBtn.addEventListener('click', function () {
            var count = parseInt(dayBtn.getAttribute('data-count') || '0', 10);
            var date = dayBtn.getAttribute('data-date') || '';
            var jalali = dayBtn.getAttribute('data-date-jalali') || date;
            if (!date || count < 1) return;
            openModal({
                title: 'لغو تمام نوبت‌های این روز',
                action: dayUrl,
                dayDate: date,
                smsLabel: 'ارسال پیامک لغو برای تمام بیماران',
                summaryHtml:
                    '<div>تاریخ: <strong>' + jalali + '</strong></div>' +
                    '<div>تعداد نوبت‌های قابل لغو: <strong>' + count + '</strong></div>' +
                    '<div style="margin-top:8px;color:#b91c1c;"><strong>این عملیات تمام نوبت‌های قابل لغو این روز را لغو می‌کند.</strong></div>'
            });
        });
    }

    document.getElementById('apptCancelClose').addEventListener('click', closeModal);
    document.getElementById('apptCancelDismiss').addEventListener('click', closeModal);
    modal.addEventListener('click', function (e) {
        if (e.target === modal) closeModal();
    });

    form.addEventListener('submit', function () {
        if (submitting) {
            return false;
        }
        submitting = true;
        submitBtn.disabled = true;
        submitBtn.textContent = 'در حال لغو...';
        return true;
    });
})();
</script>
