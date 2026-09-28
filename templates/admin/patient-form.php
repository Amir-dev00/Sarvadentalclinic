<?php
$patient = $patient ?? [];
$id = (int) ($patient['id'] ?? 0);
$isCreate = $id <= 0;
$suggested = $suggested_file_number ?? null;
$fileValue = $patient['file_number'] ?? ($isCreate ? (string) ($suggested ?? '') : '');
?>
<style>
.dossier-hero {
    display: flex; flex-wrap: wrap; gap: 16px; align-items: flex-start;
    justify-content: space-between; margin-bottom: 18px;
}
.dossier-hero h2 { margin: 0; font-size: 1.25rem; color: #031D4F; }
.dossier-hero p { margin: 6px 0 0; color: #6b7280; font-size: .92rem; max-width: 52rem; line-height: 1.7; }
.dossier-badge {
    display: inline-flex; align-items: center; gap: 6px;
    background: #e8f8f3; color: #067a5f; border-radius: 999px;
    padding: 6px 12px; font-size: .8rem; font-weight: 600;
}
.dossier-section {
    border: 1px solid #e8ecf4; border-radius: 16px; padding: 18px;
    margin-bottom: 16px; background: #fff;
}
.dossier-section__head {
    display: flex; align-items: center; justify-content: space-between;
    gap: 12px; margin-bottom: 14px; flex-wrap: wrap;
}
.dossier-section__head h3 {
    margin: 0; font-size: 1rem; color: #031D4F; font-weight: 700;
}
.dossier-section__head small { color: #6b7280; }
.dossier-excel-grid .form-label { font-weight: 600; color: #031D4F; }
.dossier-excel-grid .excel-col {
    display: inline-block; margin-inline-start: 6px; font-size: .72rem;
    font-weight: 700; color: #05B18B; background: #eefbf7;
    border-radius: 6px; padding: 1px 6px; vertical-align: middle;
}
.dossier-hint { font-size: .8rem; color: #6b7280; margin-top: 4px; }
.dossier-actions {
    position: sticky; bottom: 12px; z-index: 5;
    background: rgba(255,255,255,.92); backdrop-filter: blur(8px);
    border: 1px solid #e8ecf4; border-radius: 14px; padding: 12px 14px;
    display: flex; gap: 10px; flex-wrap: wrap; align-items: center;
    box-shadow: 0 8px 24px rgba(3,29,79,.08);
}
</style>

<div class="dossier-hero">
    <div>
        <h2><?= $isCreate ? 'ایجاد پرونده بیمار' : 'ویرایش پرونده بیمار' ?></h2>
        <p>
            <?= $isCreate
                ? 'فرم زیر مطابق ستون‌های برگه اکسل «پرونده» طراحی شده است. پس از ذخیره، پرونده کامل بیمار در سیستم باز می‌شود.'
                : 'ویرایش اطلاعات پرونده. فیلدهای اصلی همان ساختار اکسل واردشده هستند.' ?>
        </p>
    </div>
    <div class="dossier-badge"><i class="fa-solid fa-file-medical"></i> ساختار پرونده سروا</div>
</div>

<form method="post" action="<?= url('/admin/patients/save') ?>" class="row g-0" id="dossierForm" autocomplete="off">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= $id ?>">

    <div class="dossier-section dossier-excel-grid">
        <div class="dossier-section__head">
            <h3>۱) مشخصات اصلی پرونده</h3>
            <small>همان فیلدهای برگه اکسل: شماره پرونده، نام، نام خانوادگی، نام پدر، سال تولد، موبایل، تلفن ثابت، معرف</small>
        </div>
        <div class="row g-3">
            <div class="col-md-3">
                <label class="form-label">شماره پرونده <span class="excel-col">A</span></label>
                <input name="file_number" id="file_number" class="form-control" dir="ltr"
                       value="<?= e($fileValue) ?>" placeholder="مثلاً ۶۲۲۵">
                <?php if ($isCreate && $suggested): ?>
                    <div class="dossier-hint">پیشنهاد بعدی بر اساس آخرین پرونده: <strong dir="ltr"><?= e((string) $suggested) ?></strong></div>
                <?php endif; ?>
            </div>
            <div class="col-md-3">
                <label class="form-label">نام * <span class="excel-col">B</span></label>
                <input name="first_name" class="form-control" required value="<?= e($patient['first_name'] ?? '') ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">نام خانوادگی * <span class="excel-col">C</span></label>
                <input name="last_name" class="form-control" required value="<?= e($patient['last_name'] ?? '') ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">نام پدر <span class="excel-col">D</span></label>
                <input name="father_name" class="form-control" value="<?= e($patient['father_name'] ?? '') ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">سال تولد شمسی <span class="excel-col">E</span></label>
                <input name="birth_year_jalali" class="form-control" dir="ltr"
                       value="<?= e((string) ($patient['birth_year_jalali'] ?? '')) ?>" placeholder="مثلاً ۱۳۴۹">
                <div class="dossier-hint">دو رقم (۴۹) هم پذیرفته می‌شود و به ۱۳۴۹ تبدیل می‌شود.</div>
            </div>
            <div class="col-md-3">
                <label class="form-label">موبایل<?= $isCreate ? ' *' : '' ?> <span class="excel-col">F</span></label>
                <input name="mobile" class="form-control" <?= $isCreate ? 'required' : '' ?> dir="ltr"
                       value="<?= e($patient['mobile'] ?? '') ?>" placeholder="09xxxxxxxxx" inputmode="tel">
            </div>
            <div class="col-md-3">
                <label class="form-label">تلفن ثابت <span class="excel-col">G</span></label>
                <input name="landline" class="form-control" dir="ltr" value="<?= e($patient['landline'] ?? '') ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">معرف <span class="excel-col">H</span></label>
                <input name="referrer" class="form-control" value="<?= e($patient['referrer'] ?? '') ?>">
            </div>
        </div>
    </div>

    <div class="dossier-section">
        <div class="dossier-section__head">
            <h3>۲) اطلاعات تکمیلی</h3>
            <small>اختیاری — برای تکمیل پرونده کلینیکی</small>
        </div>
        <div class="row g-3">
            <div class="col-md-3">
                <label class="form-label">کد ملی</label>
                <input name="national_id" class="form-control" dir="ltr" value="<?= e($patient['national_id'] ?? '') ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">تاریخ تولد کامل</label>
                <input type="text" name="birth_date" class="form-control" data-jalali="date"
                       placeholder="مثلاً ۱۳۷۰/۰۱/۱۵" value="<?= e($patient['birth_date'] ?? '') ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">جنسیت</label>
                <select name="gender" class="form-select">
                    <option value="">—</option>
                    <option value="female" <?= ($patient['gender'] ?? '') === 'female' ? 'selected' : '' ?>>زن</option>
                    <option value="male" <?= ($patient['gender'] ?? '') === 'male' ? 'selected' : '' ?>>مرد</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">ایمیل</label>
                <input type="email" name="email" class="form-control" dir="ltr" value="<?= e($patient['email'] ?? '') ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">موبایل دوم</label>
                <input name="secondary_mobile" class="form-control" dir="ltr" value="<?= e($patient['secondary_mobile'] ?? '') ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">پزشک مرتبط</label>
                <select name="preferred_doctor_id" class="form-select">
                    <option value="">—</option>
                    <?php foreach (($doctors ?? []) as $d): ?>
                    <option value="<?= (int) $d['id'] ?>" <?= (int) ($patient['preferred_doctor_id'] ?? 0) === (int) $d['id'] ? 'selected' : '' ?>><?= e(trim($d['first_name'] . ' ' . $d['last_name'])) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12">
                <label class="form-label">آدرس</label>
                <input name="address" class="form-control" value="<?= e($patient['address'] ?? '') ?>">
            </div>
        </div>
    </div>

    <div class="dossier-section">
        <div class="dossier-section__head">
            <h3>۳) تماس اضطراری و یادداشت</h3>
            <small>برای پیگیری‌های داخلی کلینیک</small>
        </div>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">نام تماس اضطراری</label>
                <input name="emergency_contact_name" class="form-control" value="<?= e($patient['emergency_contact_name'] ?? '') ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">موبایل اضطراری</label>
                <input name="emergency_contact_mobile" class="form-control" dir="ltr" value="<?= e($patient['emergency_contact_mobile'] ?? '') ?>">
            </div>
            <div class="col-12">
                <label class="form-label">یادداشت داخلی</label>
                <textarea name="notes" class="form-control" rows="3" placeholder="نکات مهم پرونده، حساسیت‌ها، ترجیحات بیمار..."><?= e($patient['notes'] ?? '') ?></textarea>
            </div>
        </div>
    </div>

    <div class="dossier-actions">
        <button type="submit" class="btn btn-primary" style="background:#031D4F;border-color:#031D4F;">
            <i class="fa-solid fa-floppy-disk"></i>
            <?= $isCreate ? 'ثبت پرونده' : 'ذخیره تغییرات' ?>
        </button>
        <?php if ($isCreate): ?>
            <button type="reset" class="btn btn-outline-secondary">پاک کردن فرم</button>
        <?php endif; ?>
        <a href="<?= url($id ? '/admin/patients/' . $id : '/admin/patients') ?>" class="btn btn-outline-secondary">انصراف</a>
        <?php if (!$isCreate && !empty($patient['public_code'])): ?>
            <span class="dossier-hint" style="margin:0;">کد سیستمی: <strong dir="ltr"><?= e((string) $patient['public_code']) ?></strong></span>
        <?php endif; ?>
    </div>
</form>
