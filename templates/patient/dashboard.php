<?php
$patient = $patient ?? [];
$upcoming = $upcoming ?? [];
$past = $past ?? [];
$payments = $payments ?? [];

$statusLabels = [
    'awaiting_payment' => 'در انتظار پرداخت',
    'confirmed' => 'تأیید شده',
    'completed' => 'انجام شده',
    'cancelled' => 'لغو شده',
    'no_show' => 'عدم حضور',
    'expired' => 'منقضی',
];
$payLabels = [
    'initiated' => 'آغاز شده',
    'pending' => 'در انتظار',
    'paid' => 'پرداخت شده',
    'failed' => 'ناموفق',
    'cancelled' => 'لغو شده',
];
$name = trim(($patient['first_name'] ?? '') . ' ' . ($patient['last_name'] ?? ''));
$completed = !empty($patient['profile_completed']);
?>
<div class="admin-card" style="margin-bottom:20px;">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <h1 style="margin:0 0 6px;font-size:1.35rem;color:#031D4F;">
                سلام<?= $name !== '' ? '، ' . e($name) : '' ?>
            </h1>
            <p style="margin:0;color:#6b7280;">
                کد بیمار: <code><?= e($patient['public_code'] ?? '') ?></code>
                &nbsp;|&nbsp;
                موبایل: <span dir="ltr"><?= e($patient['mobile'] ?? '') ?></span>
            </p>
        </div>
        <div>
            <?php if ($completed): ?>
                <span class="badge" style="background:#e8f8f3;color:#067a5f;padding:8px 12px;border-radius:999px;">پروفایل کامل است</span>
            <?php else: ?>
                <a href="#patientProfileForm" class="badge" style="background:#fff4e5;color:#9a6700;padding:8px 12px;border-radius:999px;text-decoration:none;">پروفایل ناقص — تکمیل اطلاعات</a>
            <?php endif; ?>
        </div>
    </div>
    <div class="mt-3 d-flex flex-wrap gap-2">
        <a href="<?= url('/appointment') ?>" class="btn btn-primary" style="background:#05B18B;border-color:#05B18B;border-radius:12px;">رزرو نوبت جدید</a>
        <?php if (!$completed): ?>
            <a href="#patientProfileForm" class="btn btn-outline-primary" style="border-radius:12px;color:#031D4F;border-color:#031D4F;">تکمیل پروفایل</a>
        <?php endif; ?>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="admin-card">
            <h2 style="margin:0 0 14px;font-size:1.05rem;color:#031D4F;">نوبت‌های پیش‌رو</h2>
            <?php if (empty($upcoming)): ?>
                <p style="margin:0;color:#6b7280;">نوبت فعالی ندارید.</p>
            <?php else: ?>
                <div style="overflow-x:auto;">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>خدمت</th>
                                <th>پزشک</th>
                                <th>زمان</th>
                                <th>وضعیت</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($upcoming as $row): ?>
                                <tr>
                                    <td><?= e($row['service_name'] ?? '') ?></td>
                                    <td><?= e($row['doctor_name'] ?? '') ?></td>
                                    <td><?= e(to_jalali($row['starts_at'] ?? null)) ?></td>
                                    <td><?= e($statusLabels[$row['status'] ?? ''] ?? ($row['status'] ?? '')) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="admin-card">
            <h2 style="margin:0 0 14px;font-size:1.05rem;color:#031D4F;">نوبت‌های گذشته</h2>
            <?php if (empty($past)): ?>
                <p style="margin:0;color:#6b7280;">سابقه‌ای ثبت نشده است.</p>
            <?php else: ?>
                <div style="overflow-x:auto;">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>خدمت</th>
                                <th>پزشک</th>
                                <th>زمان</th>
                                <th>وضعیت</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($past as $row): ?>
                                <tr>
                                    <td><?= e($row['service_name'] ?? '') ?></td>
                                    <td><?= e($row['doctor_name'] ?? '') ?></td>
                                    <td><?= e(to_jalali($row['starts_at'] ?? null)) ?></td>
                                    <td><?= e($statusLabels[$row['status'] ?? ''] ?? ($row['status'] ?? '')) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="admin-card" style="margin-top:20px;">
    <h2 style="margin:0 0 14px;font-size:1.05rem;color:#031D4F;">پرداخت‌ها</h2>
    <?php if (empty($payments)): ?>
        <p style="margin:0;color:#6b7280;">پرداختی ثبت نشده است.</p>
    <?php else: ?>
        <div style="overflow-x:auto;">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>مبلغ</th>
                        <th>وضعیت</th>
                        <th>مرجع</th>
                        <th>تاریخ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($payments as $pay): ?>
                        <tr>
                            <td><?= (int) $pay['id'] ?></td>
                            <td><?= number_format((float) ($pay['amount'] ?? 0)) ?> تومان</td>
                            <td><?= e($payLabels[$pay['status'] ?? ''] ?? ($pay['status'] ?? '')) ?></td>
                            <td><code><?= e($pay['ref_id'] ?? $pay['authority'] ?? '—') ?></code></td>
                            <td><?= e($pay['created_at'] ?? '') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<div class="admin-card" style="margin-top:20px;">
    <h2 style="margin:0 0 16px;font-size:1.05rem;color:#031D4F;">ویرایش پروفایل</h2>
    <form method="post" action="<?= url('/patient/profile') ?>" id="patientProfileForm" class="row g-3" style="scroll-margin-top:88px;">
        <?= csrf_field() ?>
        <div class="col-md-4">
            <label class="form-label">شماره موبایل ورود</label>
            <input type="text" class="form-control" value="<?= e($patient['mobile'] ?? '') ?>" dir="ltr" readonly disabled>
        </div>
        <div class="col-md-4">
            <label class="form-label">نام *</label>
            <input type="text" name="first_name" class="form-control" required value="<?= e($patient['first_name'] ?? '') ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label">نام خانوادگی *</label>
            <input type="text" name="last_name" class="form-control" required value="<?= e($patient['last_name'] ?? '') ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label">کد ملی</label>
            <input type="text" name="national_id" class="form-control" value="<?= e($patient['national_id'] ?? '') ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label">تاریخ تولد</label>
            <input type="text" name="birth_date" class="form-control" data-jalali="date" placeholder="مثلاً ۱۳۷۰/۰۱/۱۵" value="<?= e($patient['birth_date'] ?? '') ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label">جنسیت</label>
            <select name="gender" class="form-select">
                <option value="">—</option>
                <option value="male" <?= ($patient['gender'] ?? '') === 'male' ? 'selected' : '' ?>>مرد</option>
                <option value="female" <?= ($patient['gender'] ?? '') === 'female' ? 'selected' : '' ?>>زن</option>
                <option value="other" <?= ($patient['gender'] ?? '') === 'other' ? 'selected' : '' ?>>سایر</option>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label">ایمیل</label>
            <input type="email" name="email" class="form-control" value="<?= e($patient['email'] ?? '') ?>">
        </div>
        <div class="col-12">
            <label class="form-label">آدرس</label>
            <textarea name="address" class="form-control" rows="2"><?= e($patient['address'] ?? '') ?></textarea>
        </div>
        <div class="col-md-6">
            <label class="form-label">نام تماس اضطراری</label>
            <input type="text" name="emergency_contact_name" class="form-control" value="<?= e($patient['emergency_contact_name'] ?? '') ?>">
        </div>
        <div class="col-md-6">
            <label class="form-label">موبایل تماس اضطراری</label>
            <input type="tel" name="emergency_contact_mobile" class="form-control" dir="ltr" value="<?= e($patient['emergency_contact_mobile'] ?? '') ?>">
        </div>
        <div class="col-12">
            <button type="submit" class="btn btn-primary" style="background:#031D4F;border-color:#031D4F;border-radius:12px;">ذخیره پروفایل</button>
        </div>
    </form>
</div>
<script>
(function () {
    function focusProfile() {
        var form = document.getElementById('patientProfileForm');
        if (!form) return;
        var fields = form.querySelectorAll('input, select, textarea');
        for (var i = 0; i < fields.length; i++) {
            var el = fields[i];
            if (el.disabled || el.type === 'hidden' || el.readOnly) continue;
            if (!String(el.value || '').trim()) {
                el.focus();
                return;
            }
        }
    }
    if (location.hash === '#patientProfileForm') {
        window.setTimeout(focusProfile, 60);
    }
    document.querySelectorAll('a[href="#patientProfileForm"]').forEach(function (link) {
        link.addEventListener('click', function () {
            window.setTimeout(focusProfile, 60);
        });
    });
})();
</script>
