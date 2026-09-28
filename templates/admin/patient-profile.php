<?php
$patient = $patient ?? [];
$id = (int) ($patient['id'] ?? 0);
$tab = $tab ?? 'summary';
$name = trim(($patient['first_name'] ?? '') . ' ' . ($patient['last_name'] ?? ''));
$visits = $visits ?? [];
$medical = $medical ?? [];
$documents = $documents ?? [];
$treatment_plans = $treatment_plans ?? [];
$session_count = (int) ($session_count ?? 0);
$last_visit_at = $last_visit_at ?? null;
$doctors = $doctors ?? db()->query('SELECT id, first_name, last_name FROM doctors WHERE deleted_at IS NULL ORDER BY sort_order')->fetchAll();
$services = $services ?? db()->query('SELECT id, name FROM services WHERE deleted_at IS NULL ORDER BY sort_order')->fetchAll();
$editVisitId = (int) ($_GET['visit'] ?? 0);
$editVisit = null;
foreach ($visits as $v) {
    if ((int) $v['id'] === $editVisitId) {
        $editVisit = $v;
        break;
    }
}
$planStatus = [
    'proposed' => 'پیشنهاد شده',
    'approved' => 'تأیید شده',
    'in_progress' => 'در حال انجام',
    'completed' => 'انجام شده',
    'cancelled' => 'لغو شده',
];
$docCats = [
    'radiography' => 'رادیوگرافی',
    'opg' => 'OPG',
    'intraoral' => 'عکس داخل دهانی',
    'before_after' => 'قبل و بعد',
    'lab' => 'آزمایش',
    'consent' => 'رضایت‌نامه',
    'other' => 'سایر',
];
$tabs = [
    'summary' => 'خلاصه پرونده',
    'info' => 'اطلاعات شخصی',
    'excel' => 'داده Excel',
    'visits' => 'جلسات / مراجعات',
    'medical' => 'پرونده پزشکی',
    'plans' => 'طرح درمان',
    'appointments' => 'نوبت‌ها',
    'payments' => 'پرداخت‌ها',
    'sms' => 'پیامک‌ها',
    'documents' => 'مدارک',
    'notes' => 'یادداشت داخلی',
    'history' => 'تاریخچه تغییرات',
];
if (!isset($tabs[$tab])) {
    $tab = 'summary';
}
$canVisit = \Sarva\Core\Auth::adminHasPermission('patient_visits.create')
    || \Sarva\Core\Auth::adminHasPermission('patient_visits.edit')
    || \Sarva\Core\Auth::adminHasPermission('patients.manage');
$canMedical = \Sarva\Core\Auth::adminHasPermission('patient_medical.view')
    || \Sarva\Core\Auth::adminHasPermission('patient_medical.edit')
    || \Sarva\Core\Auth::adminHasPermission('patients.manage');
$canDocs = \Sarva\Core\Auth::adminHasPermission('patient_documents.view')
    || \Sarva\Core\Auth::adminHasPermission('patient_documents.upload')
    || \Sarva\Core\Auth::adminHasPermission('patients.manage');
$canPlans = \Sarva\Core\Auth::adminHasPermission('patient_treatment_plans.view')
    || \Sarva\Core\Auth::adminHasPermission('patient_treatment_plans.manage')
    || \Sarva\Core\Auth::adminHasPermission('patients.manage');
?>
<div class="admin-card">
    <div class="d-flex flex-wrap justify-content-between gap-3">
        <div>
            <h2 style="margin:0;color:#031D4F;"><?= e($name ?: 'بیمار') ?></h2>
            <p style="margin:6px 0 0;color:#6b7280;">
                پرونده: <code><?= e($patient['file_number'] ?: $patient['public_code'] ?? '') ?></code>
                · موبایل: <span dir="ltr"><?= e($patient['mobile'] ?: '—') ?></span>
                <?php if (!empty($patient['landline'])): ?> · ثابت: <span dir="ltr"><?= e($patient['landline']) ?></span><?php endif; ?>
                · جلسات: <?= $session_count ?>
                <?php if (!empty($patient['is_imported'])): ?> · <span class="admin-badge">Excel</span><?php endif; ?>
            </p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a class="btn btn-sm btn-outline-primary" href="<?= url('/admin/patients/' . $id . '/edit') ?>">ویرایش</a>
            <?php if ($canVisit): ?>
            <a class="btn btn-sm" style="background:#05B18B;color:#fff;border:0;" href="<?= url('/admin/patients/' . $id . '?tab=visits&new=1') ?>">ثبت جلسه جدید</a>
            <?php endif; ?>
            <a class="btn btn-sm btn-outline-success" href="<?= url('/admin/sms/send?preset=selected&patient_ids[]=' . $id) ?>">ارسال پیامک</a>
            <a class="btn btn-sm btn-outline-secondary" href="<?= url('/admin/appointments?patient_id=' . $id) ?>">ثبت نوبت</a>
            <?php if (empty($patient['deleted_at'])): ?>
            <form method="post" action="<?= url('/admin/patients/' . $id . '/archive') ?>" onsubmit="return confirm('بایگانی این بیمار؟');">
                <?= csrf_field() ?>
                <button class="btn btn-sm btn-outline-danger">آرشیو</button>
            </form>
            <?php else: ?>
            <form method="post" action="<?= url('/admin/patients/' . $id . '/restore') ?>">
                <?= csrf_field() ?>
                <button class="btn btn-sm btn-outline-success">بازیابی</button>
            </form>
            <?php endif; ?>
        </div>
    </div>
    <div class="admin-tabs">
        <?php foreach ($tabs as $k => $label): ?>
            <?php
            if ($k === 'medical' && !$canMedical) continue;
            if ($k === 'documents' && !$canDocs) continue;
            if ($k === 'plans' && !$canPlans) continue;
            if ($k === 'visits' && !(\Sarva\Core\Auth::adminHasPermission('patient_visits.view') || \Sarva\Core\Auth::adminHasPermission('patients.manage') || $canVisit)) continue;
            ?>
            <a class="<?= $tab === $k ? 'is-active' : '' ?>" href="<?= url('/admin/patients/' . $id . '?tab=' . $k) ?>"><?= e($label) ?></a>
        <?php endforeach; ?>
    </div>
</div>

<?php if ($tab === 'summary'): ?>
<div class="stat-grid">
    <div class="stat-card"><h3><?= e($patient['file_number'] ?: '—') ?></h3><p>شماره پرونده</p></div>
    <div class="stat-card"><h3><?= $session_count ?></h3><p>جلسات ثبت‌شده</p></div>
    <div class="stat-card"><h3 style="font-size:1rem;"><?= e(to_jalali($last_visit_at, 'Y/m/d')) ?></h3><p>آخرین جلسه</p></div>
    <div class="stat-card"><h3 style="font-size:1rem;"><?= e(to_jalali($next_appointment ?? null, 'Y/m/d H:i')) ?></h3><p>نوبت آینده</p></div>
</div>
<div class="admin-card">
    <div class="row g-3">
        <div class="col-md-4"><strong>نام کامل</strong><div><?= e($name) ?></div></div>
        <div class="col-md-4"><strong>نام پدر</strong><div><?= e($patient['father_name'] ?? '—') ?></div></div>
        <div class="col-md-4"><strong>سال تولد شمسی</strong><div><?= e($patient['birth_year_jalali'] ?? '—') ?></div></div>
        <div class="col-md-4"><strong>موبایل</strong><div dir="ltr"><?= e($patient['mobile'] ?: '—') ?></div></div>
        <div class="col-md-4"><strong>تلفن ثابت</strong><div dir="ltr"><?= e($patient['landline'] ?? '—') ?></div></div>
        <div class="col-md-4"><strong>معرف</strong><div><?= e($patient['referrer'] ?? '—') ?></div></div>
        <div class="col-md-4"><strong>آخرین نوبت</strong><div><?= e(to_jalali($last_appointment ?? null)) ?></div></div>
        <div class="col-md-8"><strong>آخرین یادداشت داخلی</strong>
            <div><?php
                $ln = $notes[0] ?? null;
                echo $ln ? e(mb_substr((string) $ln['body'], 0, 160)) : '—';
            ?></div>
        </div>
    </div>
</div>
<?php if ($visits): ?>
<div class="admin-card">
    <h3 style="font-size:1rem;color:#031D4F;">آخرین جلسات</h3>
    <?php foreach (array_slice($visits, 0, 5) as $v): ?>
        <div style="border-bottom:1px solid #eef2f7;padding:10px 0;">
            <strong><?= e(to_jalali($v['visit_at'] ?? null)) ?></strong>
            · <?= e($v['doctor_name'] ?? '—') ?>
            · <?= e($v['service_name'] ?? '') ?>
            <div style="color:#6b7280;font-size:.9rem;"><?= e(mb_substr((string) ($v['session_notes'] ?? $v['chief_complaint'] ?? ''), 0, 140)) ?></div>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php elseif ($tab === 'info'): ?>
<div class="admin-card">
    <div class="row g-3">
        <div class="col-md-4"><strong>کد ملی</strong><div><?= e($patient['national_id'] ?? '—') ?></div></div>
        <div class="col-md-4"><strong>تاریخ تولد</strong><div><?= e($patient['birth_date'] ?? '—') ?></div></div>
        <div class="col-md-4"><strong>جنسیت</strong><div><?= e($patient['gender'] ?? '—') ?></div></div>
        <div class="col-md-4"><strong>ایمیل</strong><div><?= e($patient['email'] ?? '—') ?></div></div>
        <div class="col-md-4"><strong>تماس اضطراری</strong><div><?= e(($patient['emergency_contact_name'] ?? '') . ' ' . ($patient['emergency_contact_mobile'] ?? '')) ?></div></div>
        <div class="col-md-4"><strong>موبایل دوم</strong><div dir="ltr"><?= e($patient['secondary_mobile'] ?? '—') ?></div></div>
        <div class="col-12"><strong>آدرس</strong><div><?= e($patient['address'] ?? '—') ?></div></div>
        <div class="col-12"><strong>یادداشت پرونده</strong><div><?= nl2br(e($patient['notes'] ?? '—')) ?></div></div>
    </div>
</div>

<?php elseif ($tab === 'excel'): ?>
<div class="admin-card">
    <h3 style="font-size:1rem;color:#031D4F;">اطلاعات واردشده از Excel</h3>
    <div class="row g-3">
        <div class="col-md-3"><strong>منبع</strong><div><?= !empty($patient['is_imported']) ? 'Excel' : 'دستی / آنلاین' ?></div></div>
        <div class="col-md-3"><strong>شناسه ورود</strong><div><?= e($patient['import_id'] ?? '—') ?></div></div>
        <div class="col-md-3"><strong>شماره ردیف</strong><div><?= e($patient['import_row_number'] ?? '—') ?></div></div>
        <div class="col-md-3"><strong>برگه</strong><div><?= e($patient['import_worksheet'] ?? '—') ?></div></div>
        <div class="col-md-3"><strong>شماره پرونده</strong><div><code><?= e($patient['file_number'] ?? '—') ?></code></div></div>
        <div class="col-md-3"><strong>نام پدر</strong><div><?= e($patient['father_name'] ?? '—') ?></div></div>
        <div class="col-md-3"><strong>سال تولد شمسی</strong><div><?= e($patient['birth_year_jalali'] ?? '—') ?></div></div>
        <div class="col-md-3"><strong>معرف</strong><div><?= e($patient['referrer'] ?? '—') ?></div></div>
        <div class="col-md-3"><strong>تلفن ثابت</strong><div dir="ltr"><?= e($patient['landline'] ?? '—') ?></div></div>
        <div class="col-md-3"><strong>تلفن همراه</strong><div dir="ltr"><?= e($patient['mobile'] ?? '—') ?></div></div>
    </div>
</div>

<?php elseif ($tab === 'visits'): ?>
<?php if ($canVisit && (!empty($_GET['new']) || $editVisit)): ?>
<div class="admin-card">
    <h3 style="font-size:1rem;color:#031D4F;"><?= $editVisit ? 'ویرایش جلسه' : 'ثبت جلسه جدید' ?></h3>
    <form method="post" action="<?= url('/admin/patients/' . $id . '/visits/save') ?>" class="row g-3">
        <?= csrf_field() ?>
        <input type="hidden" name="visit_id" value="<?= (int) ($editVisit['id'] ?? 0) ?>">
        <div class="col-md-4">
            <label class="form-label">تاریخ و ساعت جلسه</label>
            <input type="text" name="visit_at" class="form-control" data-jalali="datetime"
                   value="<?= e($editVisit['visit_at'] ?? date('Y-m-d H:i')) ?>" placeholder="شمسی">
        </div>
        <div class="col-md-4">
            <label class="form-label">پزشک</label>
            <select name="doctor_id" class="form-select">
                <option value="">—</option>
                <?php foreach ($doctors as $d): ?>
                <option value="<?= (int) $d['id'] ?>" <?= (int) ($editVisit['doctor_id'] ?? 0) === (int) $d['id'] ? 'selected' : '' ?>><?= e(trim($d['first_name'] . ' ' . $d['last_name'])) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label">خدمت</label>
            <select name="service_id" class="form-select">
                <option value="">—</option>
                <?php foreach ($services as $s): ?>
                <option value="<?= (int) $s['id'] ?>" <?= (int) ($editVisit['service_id'] ?? 0) === (int) $s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-6">
            <label class="form-label">شکایت اصلی</label>
            <input name="chief_complaint" class="form-control" value="<?= e($editVisit['chief_complaint'] ?? '') ?>">
        </div>
        <div class="col-md-6">
            <label class="form-label">شماره دندان‌ها (مثل 11,26,36)</label>
            <input name="teeth" class="form-control" dir="ltr" value="<?= e($editVisit['teeth'] ?? '') ?>" placeholder="FDI: 11-48">
        </div>
        <div class="col-12">
            <label class="form-label">شرح جلسه</label>
            <textarea name="session_notes" class="form-control" rows="6" placeholder="توضیحات کامل جلسه را بنویسید..."><?= e($editVisit['session_notes'] ?? '') ?></textarea>
        </div>
        <div class="col-md-6">
            <label class="form-label">درمان انجام‌شده</label>
            <textarea name="treatment_performed" class="form-control" rows="3"><?= e($editVisit['treatment_performed'] ?? '') ?></textarea>
        </div>
        <div class="col-md-6">
            <label class="form-label">تشخیص / ارزیابی</label>
            <textarea name="diagnosis" class="form-control" rows="3"><?= e($editVisit['diagnosis'] ?? '') ?></textarea>
        </div>
        <div class="col-md-4"><label class="form-label">مواد مصرفی</label><input name="materials" class="form-control" value="<?= e($editVisit['materials'] ?? '') ?>"></div>
        <div class="col-md-4"><label class="form-label">توصیه‌ها</label><input name="recommendations" class="form-control" value="<?= e($editVisit['recommendations'] ?? '') ?>"></div>
        <div class="col-md-4"><label class="form-label">پیگیری</label><input name="follow_up" class="form-control" value="<?= e($editVisit['follow_up'] ?? '') ?>"></div>
        <div class="col-md-6"><label class="form-label">درمان بعدی پیشنهادی</label><input name="next_treatment" class="form-control" value="<?= e($editVisit['next_treatment'] ?? '') ?>"></div>
        <div class="col-md-6"><label class="form-label">یادداشت داخلی جلسه</label><input name="internal_notes" class="form-control" value="<?= e($editVisit['internal_notes'] ?? '') ?>"></div>
        <div class="col-12"><button class="btn" style="background:#05B18B;color:#fff;border:0;">ذخیره جلسه</button>
            <a class="btn btn-outline-secondary" href="<?= url('/admin/patients/' . $id . '?tab=visits') ?>">انصراف</a></div>
    </form>
</div>
<?php endif; ?>
<div class="admin-card">
    <div class="d-flex justify-content-between align-items-center mb-2">
        <h3 style="margin:0;font-size:1rem;color:#031D4F;">تاریخچه جلسات</h3>
        <?php if ($canVisit): ?><a href="<?= url('/admin/patients/' . $id . '?tab=visits&new=1') ?>">+ جلسه جدید</a><?php endif; ?>
    </div>
    <?php if (!$visits): ?>
        <p style="color:#6b7280;">هنوز جلسه‌ای ثبت نشده است.</p>
    <?php else: foreach ($visits as $v): ?>
        <details class="sms-advanced" style="margin-bottom:8px;">
            <summary>
                <?= e(to_jalali($v['visit_at'] ?? null)) ?>
                · <?= e($v['doctor_name'] ?? 'بدون پزشک') ?>
                <?php if (!empty($v['service_name'])): ?> · <?= e($v['service_name']) ?><?php endif; ?>
                <?php if (!empty($v['teeth'])): ?> · دندان <?= e($v['teeth']) ?><?php endif; ?>
            </summary>
            <div style="padding:10px 0;">
                <?php if (!empty($v['chief_complaint'])): ?><p><strong>شکایت:</strong> <?= e($v['chief_complaint']) ?></p><?php endif; ?>
                <p><strong>شرح جلسه:</strong><br><?= nl2br(e($v['session_notes'] ?? '—')) ?></p>
                <?php if (!empty($v['treatment_performed'])): ?><p><strong>درمان:</strong> <?= nl2br(e($v['treatment_performed'])) ?></p><?php endif; ?>
                <?php if (!empty($v['next_treatment'])): ?><p><strong>گام بعدی:</strong> <?= e($v['next_treatment']) ?></p><?php endif; ?>
                <?php if ($canVisit): ?>
                <a class="btn btn-sm btn-outline-primary" href="<?= url('/admin/patients/' . $id . '?tab=visits&visit=' . (int) $v['id']) ?>">ویرایش</a>
                <?php if (\Sarva\Core\Auth::adminHasPermission('patient_visits.edit') || \Sarva\Core\Auth::adminHasPermission('patients.manage')): ?>
                <form method="post" action="<?= url('/admin/patients/' . $id . '/visits/delete') ?>" style="display:inline;" onsubmit="return confirm('این جلسه حذف شود؟');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="visit_id" value="<?= (int) $v['id'] ?>">
                    <button type="submit" class="btn btn-sm btn-outline-danger">حذف</button>
                </form>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </details>
    <?php endforeach; endif; ?>
</div>

<?php elseif ($tab === 'medical' && $canMedical): ?>
<div class="admin-card">
    <form method="post" action="<?= url('/admin/patients/' . $id . '/medical') ?>" class="row g-3">
        <?= csrf_field() ?>
        <div class="col-md-6"><label class="form-label">حساسیت دارویی</label><textarea name="drug_allergies" class="form-control" rows="3"><?= e($medical['drug_allergies'] ?? '') ?></textarea></div>
        <div class="col-md-6"><label class="form-label">داروهای مصرفی</label><textarea name="current_medications" class="form-control" rows="3"><?= e($medical['current_medications'] ?? '') ?></textarea></div>
        <div class="col-md-6"><label class="form-label">بیماری‌های مهم</label><textarea name="major_diseases" class="form-control" rows="3"><?= e($medical['major_diseases'] ?? '') ?></textarea></div>
        <div class="col-md-6"><label class="form-label">سابقه جراحی</label><textarea name="surgery_history" class="form-control" rows="3"><?= e($medical['surgery_history'] ?? '') ?></textarea></div>
        <div class="col-md-6"><label class="form-label">بارداری / ملاحظات</label><input name="pregnancy_note" class="form-control" value="<?= e($medical['pregnancy_note'] ?? '') ?>"></div>
        <div class="col-md-6"><label class="form-label">شرایط پزشکی مهم</label><input name="important_conditions" class="form-control" value="<?= e($medical['important_conditions'] ?? '') ?>"></div>
        <div class="col-12"><label class="form-label">یادداشت پزشکی</label><textarea name="notes" class="form-control" rows="3"><?= e($medical['notes'] ?? '') ?></textarea></div>
        <?php if (\Sarva\Core\Auth::adminHasPermission('patient_medical.edit') || \Sarva\Core\Auth::adminHasPermission('patients.manage')): ?>
        <div class="col-12"><button class="btn" style="background:#031D4F;color:#fff;border:0;">ذخیره</button></div>
        <?php endif; ?>
    </form>
</div>

<?php elseif ($tab === 'plans' && $canPlans): ?>
<div class="admin-card">
    <?php if (\Sarva\Core\Auth::adminHasPermission('patient_treatment_plans.manage') || \Sarva\Core\Auth::adminHasPermission('patients.manage')): ?>
    <form method="post" action="<?= url('/admin/patients/' . $id . '/plans/save') ?>" class="row g-2 mb-4">
        <?= csrf_field() ?>
        <div class="col-md-4"><input name="title" class="form-control" placeholder="عنوان طرح درمان" required></div>
        <div class="col-md-3">
            <select name="status" class="form-select">
                <?php foreach ($planStatus as $k => $l): ?><option value="<?= e($k) ?>"><?= e($l) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <select name="doctor_id" class="form-select">
                <option value="">پزشک</option>
                <?php foreach ($doctors as $d): ?><option value="<?= (int) $d['id'] ?>"><?= e(trim($d['first_name'].' '.$d['last_name'])) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2"><button class="btn w-100" style="background:#05B18B;color:#fff;border:0;">افزودن</button></div>
    </form>
    <?php endif; ?>
    <?php if (!$treatment_plans): ?>
        <p style="color:#6b7280;">طرح درمانی ثبت نشده است.</p>
    <?php else: foreach ($treatment_plans as $plan): ?>
        <div style="border:1px solid #e8ecf4;border-radius:12px;padding:12px;margin-bottom:10px;">
            <strong><?= e($plan['title']) ?></strong>
            · <?= e($planStatus[$plan['status'] ?? ''] ?? $plan['status']) ?>
            · <?= e($plan['doctor_name'] ?? '') ?>
            <?php if (!empty($plan['items'])): ?>
            <ul>
                <?php foreach ($plan['items'] as $it): ?>
                <li><?= e($it['service_name'] ?? 'آیتم') ?>
                    <?php if (!empty($it['tooth'])): ?> (دندان <?= e($it['tooth']) ?>)<?php endif; ?>
                    — <?= e($planStatus[$it['status'] ?? ''] ?? $it['status']) ?>
                    <?php if ($it['estimated_cost'] !== null): ?> · <?= number_format((float)$it['estimated_cost']) ?> ریال<?php endif; ?>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
            <?php if (\Sarva\Core\Auth::adminHasPermission('patient_treatment_plans.manage') || \Sarva\Core\Auth::adminHasPermission('patients.manage')): ?>
            <form method="post" action="<?= url('/admin/patients/' . $id . '/plans/item') ?>" class="row g-2 mt-2">
                <?= csrf_field() ?>
                <input type="hidden" name="plan_id" value="<?= (int) $plan['id'] ?>">
                <div class="col-md-3"><input name="service_name" class="form-control form-control-sm" placeholder="خدمت / درمان" required></div>
                <div class="col-md-2"><input name="tooth" class="form-control form-control-sm" placeholder="دندان" dir="ltr"></div>
                <div class="col-md-2"><input name="estimated_cost" class="form-control form-control-sm" placeholder="هزینه" dir="ltr"></div>
                <div class="col-md-2">
                    <select name="status" class="form-select form-select-sm">
                        <?php foreach ($planStatus as $k => $l): ?><option value="<?= e($k) ?>"><?= e($l) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2"><button class="btn btn-sm btn-outline-primary w-100">افزودن آیتم</button></div>
            </form>
            <?php endif; ?>
        </div>
    <?php endforeach; endif; ?>
</div>

<?php elseif ($tab === 'documents' && $canDocs): ?>
<div class="admin-card">
    <?php if (\Sarva\Core\Auth::adminHasPermission('patient_documents.upload') || \Sarva\Core\Auth::adminHasPermission('patients.manage')): ?>
    <form method="post" action="<?= url('/admin/patients/' . $id . '/documents/upload') ?>" enctype="multipart/form-data" class="row g-2 mb-4">
        <?= csrf_field() ?>
        <div class="col-md-3"><input type="file" name="document" class="form-control" accept=".jpg,.jpeg,.png,.webp,.gif,.pdf" required></div>
        <div class="col-md-3"><input name="title" class="form-control" placeholder="عنوان" required></div>
        <div class="col-md-2">
            <select name="category" class="form-select">
                <?php foreach ($docCats as $k => $l): ?><option value="<?= e($k) ?>"><?= e($l) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2"><input type="text" name="document_date" class="form-control" data-jalali="date" placeholder="تاریخ"></div>
        <div class="col-md-2"><button class="btn w-100" style="background:#031D4F;color:#fff;border:0;">آپلود</button></div>
        <div class="col-12"><input name="description" class="form-control" placeholder="توضیح اختیاری"></div>
    </form>
    <?php endif; ?>
    <table class="admin-table">
        <thead><tr><th>عنوان</th><th>دسته</th><th>تاریخ</th><th>منبع</th><th></th></tr></thead>
        <tbody>
        <?php if (!$documents): ?><tr><td colspan="5">مدرکی نیست.</td></tr>
        <?php else: foreach ($documents as $doc): ?>
            <tr>
                <td><?= e($doc['title']) ?><?php if (!empty($doc['description'])): ?><br><small><?= e($doc['description']) ?></small><?php endif; ?></td>
                <td><?= e($docCats[$doc['category'] ?? ''] ?? $doc['category']) ?></td>
                <td><?= e(to_jalali($doc['document_date'] ?? $doc['created_at'] ?? null, 'Y/m/d')) ?></td>
                <td><?= e($doc['source'] ?? '') ?><?php if (!empty($doc['external_ref'])): ?> · <?= e($doc['external_ref']) ?><?php endif; ?></td>
                <td><?php if (!str_starts_with((string)($doc['file_path'] ?? ''), 'import://')): ?>
                    <a href="<?= asset($doc['file_path']) ?>" target="_blank">مشاهده</a>
                <?php else: ?>—<?php endif; ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<?php elseif ($tab === 'appointments'): ?>
<?php
$apptStatusLabels = [
    'awaiting_payment' => 'در انتظار پرداخت',
    'confirmed' => 'تأیید شده',
    'completed' => 'انجام شده',
    'cancelled' => 'لغو شده',
    'no_show' => 'عدم حضور',
    'expired' => 'منقضی',
];
$canManageAppts = \Sarva\Core\Auth::adminHasPermission('appointments.manage');
$slotsUrl = url('/api/appointment/slots');
?>
<?php if ($canManageAppts): ?>
<div class="admin-card" id="patientApptCreate">
    <h3 style="margin:0 0 12px;font-size:1.05rem;color:#031D4F;">ثبت نوبت دستی</h3>
    <p style="margin:0 0 12px;color:#6b7280;font-size:.9rem;">نوبت تأییدشده ثبت می‌شود و در یادآوری پیامک خودکار لحاظ می‌گردد.</p>
    <form method="post" action="<?= url('/admin/appointments/create') ?>" class="row g-3" id="patientApptForm">
        <?= csrf_field() ?>
        <input type="hidden" name="patient_id" value="<?= (int) $id ?>">
        <input type="hidden" name="return_to" value="<?= e('/admin/patients/' . $id . '?tab=appointments') ?>">
        <div class="col-md-4">
            <label class="form-label">پزشک</label>
            <select name="doctor_id" id="patientApptDoctor" class="form-select" required>
                <option value="">انتخاب پزشک</option>
                <?php foreach ($doctors as $d): ?>
                <option value="<?= (int) $d['id'] ?>"><?= e(trim(($d['first_name'] ?? '') . ' ' . ($d['last_name'] ?? ''))) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label">خدمت</label>
            <select name="service_id" id="patientApptService" class="form-select" required>
                <option value="">انتخاب خدمت</option>
                <?php foreach ($services as $s): ?>
                <option value="<?= (int) $s['id'] ?>"><?= e($s['name'] ?? '') ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label">وضعیت پرداخت</label>
            <select name="payment_status" class="form-select">
                <option value="unpaid">پرداخت نشده</option>
                <option value="paid">پرداخت شده</option>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label">تاریخ نوبت</label>
            <input type="text" name="date" id="patientApptDate" class="form-control" data-jalali="date" placeholder="تاریخ شمسی" required autocomplete="off">
        </div>
        <div class="col-md-3">
            <label class="form-label">ساعت آزاد</label>
            <select name="time" id="patientApptTime" class="form-select" required disabled>
                <option value="">ابتدا تاریخ را انتخاب کنید</option>
            </select>
            <div id="patientApptSlotsStatus" style="margin-top:6px;font-size:.85rem;color:#6b7280;" role="status"></div>
        </div>
        <div class="col-md-4">
            <label class="form-label">یادداشت (اختیاری)</label>
            <input type="text" name="notes" class="form-control" placeholder="مثلاً نوبت تلفنی">
        </div>
        <div class="col-md-2 d-flex align-items-end">
            <button type="submit" class="btn w-100" style="background:#05B18B;border:0;color:#fff;">ثبت نوبت</button>
        </div>
    </form>
</div>
<script>
(function () {
    var slotsUrl = <?= json_encode($slotsUrl, JSON_UNESCAPED_UNICODE) ?>;
    var doctorEl = document.getElementById('patientApptDoctor');
    var serviceEl = document.getElementById('patientApptService');
    var dateEl = document.getElementById('patientApptDate');
    var timeEl = document.getElementById('patientApptTime');
    var statusEl = document.getElementById('patientApptSlotsStatus');
    if (!doctorEl || !serviceEl || !dateEl || !timeEl) return;
    var timer = null;
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
                slots.forEach(function (slot) { timeEl.appendChild(new Option(slot, slot)); });
                timeEl.disabled = false;
                if (statusEl) statusEl.textContent = slots.length + ' ساعت آزاد';
            })
            .catch(function () { resetSlots('خطا در دریافت ساعات آزاد.'); });
    }
    function scheduleLoad() { clearTimeout(timer); timer = setTimeout(loadSlots, 200); }
    doctorEl.addEventListener('change', scheduleLoad);
    serviceEl.addEventListener('change', scheduleLoad);
    dateEl.addEventListener('change', scheduleLoad);
    dateEl.addEventListener('input', scheduleLoad);
    dateEl.addEventListener('blur', scheduleLoad);
    resetSlots('پزشک و تاریخ را انتخاب کنید.');
})();
</script>
<?php endif; ?>
<div class="admin-card">
    <table class="admin-table"><thead><tr><th>زمان</th><th>خدمت</th><th>پزشک</th><th>وضعیت</th><?php if ($canManageAppts): ?><th></th><?php endif; ?></tr></thead><tbody>
    <?php if (empty($appointments)): ?><tr><td colspan="<?= $canManageAppts ? 5 : 4 ?>">نوبتی نیست.</td></tr>
    <?php else: foreach ($appointments as $a): ?>
        <tr>
            <td><?= e(to_jalali($a['starts_at'])) ?></td>
            <td><?= e($a['service_name']) ?></td>
            <td><?= e($a['doctor_name']) ?></td>
            <td><?= e($apptStatusLabels[$a['status'] ?? ''] ?? ($a['status'] ?? '')) ?></td>
            <?php if ($canManageAppts): ?>
            <td>
                <form method="post" action="<?= url('/admin/appointments/delete') ?>" style="margin:0;" onsubmit="return confirm('این نوبت حذف شود؟');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
                    <input type="hidden" name="patient_id" value="<?= (int) $id ?>">
                    <input type="hidden" name="return_to" value="<?= e('/admin/patients/' . $id . '?tab=appointments') ?>">
                    <button type="submit" class="btn btn-sm btn-outline-danger">حذف</button>
                </form>
            </td>
            <?php endif; ?>
        </tr>
    <?php endforeach; endif; ?>
    </tbody></table>
</div>
<?php elseif ($tab === 'payments'): ?>
<?php
$payStatusLabels = [
    'initiated' => 'آغاز شده',
    'pending' => 'در انتظار',
    'paid' => 'پرداخت شده',
    'failed' => 'ناموفق',
    'cancelled' => 'لغو شده',
];
$canManagePay = \Sarva\Core\Auth::adminHasPermission('payments.manage');
$visiblePayments = array_values(array_filter($payments ?? [], static fn ($p) => ($p['status'] ?? '') !== 'cancelled'));
?>
<div class="admin-card">
    <table class="admin-table"><thead><tr><th>مبلغ</th><th>وضعیت</th><th>تاریخ</th><?php if ($canManagePay): ?><th></th><?php endif; ?></tr></thead><tbody>
    <?php if (!$visiblePayments): ?><tr><td colspan="<?= $canManagePay ? 4 : 3 ?>">پرداختی نیست.</td></tr>
    <?php else: foreach ($visiblePayments as $p): ?>
        <tr>
            <td><?= number_format((float) $p['amount']) ?></td>
            <td><?= e($payStatusLabels[$p['status'] ?? ''] ?? ($p['status'] ?? '')) ?></td>
            <td><?= e(to_jalali($p['created_at'])) ?></td>
            <?php if ($canManagePay): ?>
            <td>
                <form method="post" action="<?= url('/admin/payments/delete') ?>" style="margin:0;" onsubmit="return confirm('این پرداخت حذف (لغو) شود؟');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                    <input type="hidden" name="return_to" value="<?= e('/admin/patients/' . $id . '?tab=payments') ?>">
                    <button type="submit" class="btn btn-sm btn-outline-danger">حذف</button>
                </form>
            </td>
            <?php endif; ?>
        </tr>
    <?php endforeach; endif; ?>
    </tbody></table>
</div>
<?php elseif ($tab === 'sms'): ?>
<div class="admin-card">
    <h3 style="font-size:1rem;color:#031D4F;">ارسال پیامک</h3>
    <form method="post" action="<?= url('/admin/patients/' . $id . '/sms') ?>" class="row g-2 mb-4">
        <?= csrf_field() ?>
        <div class="col-md-4">
            <select name="template_id" class="form-select">
                <option value="0">پیام سفارشی</option>
                <?php foreach (($templates ?? []) as $t): ?>
                <option value="<?= (int) $t['id'] ?>"><?= e($t['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-8"><textarea name="message" class="form-control" rows="2" placeholder="متن سفارشی (اگر قالب انتخاب نشود)"></textarea></div>
        <div class="col-12"><button class="btn btn-sm" style="background:#05B18B;color:#fff;border:0;">ارسال به صف</button></div>
    </form>
    <table class="admin-table"><thead><tr><th>متن</th><th>منبع</th><th>وضعیت</th><th>نوبت مرتبط</th><th>ارسال‌کننده</th><th>زمان</th></tr></thead><tbody>
    <?php if (empty($sms)): ?><tr><td colspan="6">پیامکی نیست.</td></tr>
    <?php else: foreach ($sms as $s): ?>
        <tr>
            <td><?= e(mb_substr((string) $s['message_body'], 0, 100)) ?></td>
            <td><?= e($s['source'] ?? '—') ?></td>
            <td><?= e(sms_status_labels()[$s['status'] ?? ''] ?? $s['status']) ?></td>
            <td><?= !empty($s['appointment_id']) ? '#' . (int) $s['appointment_id'] : '—' ?></td>
            <td><?= e($s['admin_name'] ?? (($s['admin_user_id'] ?? null) ? 'مدیر' : 'سیستم')) ?></td>
            <td><?= e(to_jalali($s['created_at'] ?? null)) ?></td>
        </tr>
    <?php endforeach; endif; ?>
    </tbody></table>
</div>
<?php elseif ($tab === 'notes'): ?>
<div class="admin-card">
    <form method="post" action="<?= url('/admin/patients/' . $id . '/note') ?>" class="mb-3">
        <?= csrf_field() ?>
        <textarea name="body" class="form-control mb-2" rows="3" placeholder="یادداشت داخلی اداری..." required></textarea>
        <button class="btn btn-sm" style="background:#031D4F;color:#fff;border:0;">ثبت یادداشت</button>
    </form>
    <?php if (empty($notes)): ?><p style="color:#6b7280;">یادداشتی نیست.</p>
    <?php else: foreach ($notes as $n): ?>
        <div style="border-bottom:1px solid #eef2f7;padding:10px 0;">
            <div><?= nl2br(e($n['body'])) ?></div>
            <small style="color:#6b7280;"><?= e($n['admin_name'] ?? 'سیستم') ?> · <?= e(to_jalali($n['created_at'])) ?></small>
        </div>
    <?php endforeach; endif; ?>
</div>
<?php elseif ($tab === 'history'): ?>
<div class="admin-card">
    <table class="admin-table"><thead><tr><th>رویداد</th><th>زمان</th></tr></thead><tbody>
    <?php if (empty($audit)): ?><tr><td colspan="2">رویدادی نیست.</td></tr>
    <?php else: foreach ($audit as $a): ?>
        <tr><td><?= e($a['action']) ?></td><td><?= e(to_jalali($a['created_at'])) ?></td></tr>
    <?php endforeach; endif; ?>
    </tbody></table>
</div>
<?php endif; ?>
