<?php
$items = $items ?? [];
$types = sms_type_labels();
$placeholders = \Sarva\Services\SmsTemplateRenderer::placeholders();
?>
<div class="admin-card">
    <h2 style="font-size:1.1rem;color:#031D4F;">قالب‌های SMS.ir (Verify)</h2>
    <p style="color:#6b7280;font-size:.9rem;margin:0 0 10px;">
        متن این پیام‌ها در پنل SMS.ir تعریف شده است. تغییر متن در این صفحه روی ارسال واقعی اثر ندارد.
    </p>
    <ul style="margin:0;padding-right:1.2rem;line-height:1.9;color:#031D4F;">
        <li>
            <strong>تأیید نوبت</strong> —
            قالب SMS.ir
            <code dir="ltr">#<?= (int) config('sms.appointment_confirmation_template_id', 0) ?: 159898 ?></code>
            · پارامترها:
            <code dir="ltr">FULL_NAME</code>,
            <code dir="ltr">APPOINTMENT_DATE</code>,
            <code dir="ltr">APPOINTMENT_TIME</code>
        </li>
        <li>
            <strong>یادآوری نوبت</strong> —
            قالب SMS.ir
            <code dir="ltr">#<?= (int) config('sms.appointment_reminder_template_id', 0) ?: 822711 ?></code>
            · پارامترها:
            <code dir="ltr">FULL_NAME</code>,
            <code dir="ltr">APPOINTMENT_TIME</code>
            · فقط از طریق cron/اتوماسیون یادآوری
        </li>
        <li>
            <strong>لغو نوبت</strong> —
            قالب SMS.ir
            <code dir="ltr">#<?= (int) config('sms.appointment_cancellation_template_id', 0) ?: 296200 ?></code>
            · پارامترها:
            <code dir="ltr">FULL_NAME</code>,
            <code dir="ltr">APPOINTMENT_DATE</code>,
            <code dir="ltr">APPOINTMENT_TIME</code>
        </li>
    </ul>
</div>
<div class="admin-card">
    <h2 style="font-size:1.1rem;color:#031D4F;">افزودن / ویرایش قالب</h2>
    <p style="color:#6b7280;font-size:.9rem;margin-bottom:.5rem;">متغیرها (برای درج کلیک کنید):</p>
    <div class="sms-vars" style="margin-bottom:1rem;">
        <?php foreach ($placeholders as $k => $label): ?>
            <button type="button" class="sms-var tpl-var-chip" data-var="#<?= e($k) ?>#" title="<?= e($label) ?>">#<?= e($k) ?>#</button>
        <?php endforeach; ?>
    </div>
    <form method="post" action="<?= url('/admin/sms/templates/save') ?>" class="row g-3" id="tplForm">
        <?= csrf_field() ?>
        <input type="hidden" name="id" id="tpl_id" value="0">
        <div class="col-md-4"><label class="form-label">عنوان</label><input name="name" id="tpl_name" class="form-control" required></div>
        <div class="col-md-3"><label class="form-label">شناسه داخلی</label><input name="slug" id="tpl_slug" class="form-control" placeholder="اختیاری"></div>
        <div class="col-md-3">
            <label class="form-label">نوع</label>
            <select name="type" id="tpl_type" class="form-select">
                <?php foreach ($types as $k => $label): ?><option value="<?= e($k) ?>"><?= e($label) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label">دسته</label>
            <select name="category" id="tpl_category" class="form-select">
                <option value="appointment">نوبت</option>
                <option value="reminder">یادآوری</option>
                <option value="payment">پرداخت</option>
                <option value="followup">پیگیری</option>
                <option value="operational">عملیاتی</option>
                <option value="marketing">اطلاع‌رسانی</option>
                <option value="general">عمومی</option>
            </select>
        </div>
        <div class="col-md-2 d-flex align-items-end gap-3">
            <label class="form-check"><input type="checkbox" name="is_active" id="tpl_active" class="form-check-input" checked> فعال</label>
            <label class="form-check"><input type="checkbox" name="is_favorite" id="tpl_favorite" class="form-check-input"> علاقه‌مندی</label>
        </div>
        <div class="col-12">
            <label class="form-label">متن</label>
            <textarea name="body" id="tpl_body" class="form-control" rows="5" required oninput="tplCount()"></textarea>
            <small id="tplCount" style="color:#6b7280;"></small>
            <pre class="sms-preview" id="tplPreview"></pre>
        </div>
        <div class="col-12">
            <button class="btn btn-primary" style="background:#031D4F;border-color:#031D4F;">ذخیره</button>
            <button type="button" class="btn btn-outline-secondary" onclick="resetTpl()">جدید</button>
        </div>
    </form>
</div>
<div class="admin-card">
    <table class="admin-table">
        <thead><tr><th>عنوان</th><th>نوع</th><th>وضعیت</th><th>عملیات</th></tr></thead>
        <tbody>
        <?php if (empty($items)): ?><tr><td colspan="4">قالبی نیست.</td></tr>
        <?php else: foreach ($items as $item): ?>
            <tr>
                <td><?= e($item['name']) ?><?= !empty($item['is_favorite']) ? ' ★' : '' ?><br><code><?= e($item['slug']) ?></code></td>
                <td><?= e($types[$item['type'] ?? ''] ?? $item['type']) ?><?php if (!empty($item['category'])): ?><br><small><?= e($item['category']) ?></small><?php endif; ?></td>
                <td><?= !empty($item['is_active']) ? 'فعال' : 'غیرفعال' ?></td>
                <td style="white-space:nowrap;">
                    <button class="btn btn-sm btn-outline-primary" type="button" onclick='editTpl(<?= json_encode($item, JSON_UNESCAPED_UNICODE) ?>)'>ویرایش</button>
                    <form method="post" action="<?= url('/admin/sms/templates/' . (int) $item['id'] . '/favorite') ?>" style="display:inline;"><?= csrf_field() ?><button class="btn btn-sm btn-outline-warning"><?= !empty($item['is_favorite']) ? 'حذف علاقه' : 'علاقه' ?></button></form>
                    <form method="post" action="<?= url('/admin/sms/templates/action') ?>" style="display:inline;"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $item['id'] ?>"><input type="hidden" name="action" value="duplicate"><button class="btn btn-sm btn-outline-secondary">کپی</button></form>
                    <form method="post" action="<?= url('/admin/sms/templates/action') ?>" style="display:inline;"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $item['id'] ?>"><input type="hidden" name="action" value="toggle"><button class="btn btn-sm btn-outline-warning"><?= !empty($item['is_active']) ? 'غیرفعال' : 'فعال' ?></button></form>
                    <form method="post" action="<?= url('/admin/sms/templates/action') ?>" style="display:inline;" onsubmit="return confirm('بایگانی قالب؟');"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $item['id'] ?>"><input type="hidden" name="action" value="delete"><button class="btn btn-sm btn-outline-danger">حذف</button></form>
                    <form method="post" action="<?= url('/admin/sms/templates/action') ?>" class="d-inline-flex gap-1 mt-1"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $item['id'] ?>"><input type="hidden" name="action" value="test"><input name="test_mobile" class="form-control form-control-sm" placeholder="09..." style="width:130px;" dir="ltr"><button class="btn btn-sm btn-outline-success">تست</button></form>
                </td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>
<script>
function insertTplVar(token) {
    var ta = document.getElementById('tpl_body');
    if (!ta || !token) return;
    ta.focus();
    var start = ta.selectionStart || 0;
    var end = ta.selectionEnd || 0;
    var val = ta.value || '';
    ta.value = val.slice(0, start) + token + val.slice(end);
    var pos = start + token.length;
    ta.setSelectionRange(pos, pos);
    tplCount();
}
document.querySelectorAll('.tpl-var-chip').forEach(function (btn) {
    btn.addEventListener('click', function () {
        insertTplVar(btn.getAttribute('data-var'));
    });
});
function tplCount() {
    var t = document.getElementById('tpl_body').value || '';
    var n = t.length;
    var seg = n === 0 ? 0 : Math.ceil(n / 70);
    document.getElementById('tplCount').textContent = n + ' کاراکتر · حدود ' + seg + ' پیامک';
    var sample = {
        first_name: 'علی', last_name: 'رضایی', full_name: 'علی رضایی',
        patient_number: 'P1001', mobile: '09120000000',
        appointment_date: '1405/07/07', appointment_time: '18:30',
        doctor_name: 'دکتر زمان زاده', service_name: 'ویزیت',
        clinic_name: 'کلینیک دندانپزشکی سروا', clinic_phone: '',
        cancellation_reason: 'تعطیلی کلینیک'
    };
    // Match SmsTemplateRenderer: legacy {var} → #var#, then replace #var#
    var normalized = t.replace(/\{([a-zA-Z0-9_]+)\}/g, '#$1#');
    var preview = normalized.replace(/#([a-zA-Z0-9_]+)#/g, function (_, k) {
        return Object.prototype.hasOwnProperty.call(sample, k) ? sample[k] : '';
    });
    document.getElementById('tplPreview').textContent = preview || 'پیش‌نمایش خالی است.';
}
function editTpl(t) {
    document.getElementById('tpl_id').value = t.id;
    document.getElementById('tpl_name').value = t.name || '';
    document.getElementById('tpl_slug').value = t.slug || '';
    document.getElementById('tpl_type').value = t.type || 'custom';
    var cat = document.getElementById('tpl_category');
    if (cat) cat.value = t.category || 'general';
    document.getElementById('tpl_body').value = t.body || '';
    document.getElementById('tpl_active').checked = !!parseInt(t.is_active, 10);
    var fav = document.getElementById('tpl_favorite');
    if (fav) fav.checked = !!parseInt(t.is_favorite || 0, 10);
    tplCount();
    window.scrollTo({top:0,behavior:'smooth'});
}
function resetTpl() {
    document.getElementById('tplForm').reset();
    document.getElementById('tpl_id').value = 0;
    tplCount();
}
tplCount();
</script>
