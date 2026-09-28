<?php
$items = $items ?? [];
$media = $media ?? [];
$filters = $filters ?? ['q' => '', 'status' => ''];
?>
<div class="admin-card">
    <div class="d-flex flex-wrap justify-content-between gap-2 align-items-center mb-3">
        <div>
            <h2 style="margin:0;font-size:1.1rem;color:#031D4F;">تیم پزشکی صفحه اصلی</h2>
            <p style="margin:4px 0 0;color:#6b7280;font-size:.9rem;">اعضای فعال، به ترتیب زیر در بخش تیم پزشکی صفحه اصلی نمایش داده می‌شوند.</p>
        </div>
        <button type="button" class="btn btn-sm" style="background:#05B18B;border:0;color:#fff;" onclick="resetDoctorForm(); window.scrollTo({top:0,behavior:'smooth'});">افزودن پزشک</button>
    </div>
    <form method="get" action="<?= url('/admin/doctors') ?>" class="row g-2 align-items-end">
        <div class="col-md-5">
            <label class="form-label">جستجو</label>
            <input type="search" name="q" class="form-control" value="<?= e($filters['q'] ?? '') ?>" placeholder="نام یا تخصص...">
        </div>
        <div class="col-md-3">
            <label class="form-label">وضعیت</label>
            <select name="status" class="form-select">
                <option value="">همه</option>
                <option value="active" <?= ($filters['status'] ?? '') === 'active' ? 'selected' : '' ?>>فعال در صفحه اصلی</option>
                <option value="inactive" <?= ($filters['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>غیرفعال</option>
            </select>
        </div>
        <div class="col-md-2">
            <button class="btn w-100" style="background:#031D4F;border:0;color:#fff;">اعمال</button>
        </div>
    </form>
</div>

<div class="admin-card" id="doctorFormCard">
    <h2 style="margin:0 0 16px;font-size:1.1rem;color:#031D4F;">افزودن / ویرایش پزشک</h2>
    <form method="post" action="<?= url('/admin/doctors/save') ?>" enctype="multipart/form-data" class="row g-3">
        <?= csrf_field() ?>
        <input type="hidden" name="id" id="doctor_id" value="0">
        <div class="col-md-3">
            <label class="form-label">نام</label>
            <input type="text" name="first_name" id="doctor_first" class="form-control" required>
        </div>
        <div class="col-md-3">
            <label class="form-label">نام خانوادگی</label>
            <input type="text" name="last_name" id="doctor_last" class="form-control" required>
        </div>
        <div class="col-md-4">
            <label class="form-label">عنوان / تخصص</label>
            <input type="text" name="specialty" id="doctor_specialty" class="form-control" placeholder="مثال: متخصص درمان ریشه">
        </div>
        <div class="col-md-2">
            <label class="form-label">ترتیب نمایش</label>
            <input type="number" name="sort_order" id="doctor_sort" class="form-control" value="0">
        </div>
        <div class="col-md-10">
            <label class="form-label">توضیح کوتاه / بیوگرافی</label>
            <textarea name="biography" id="doctor_bio" class="form-control" rows="3" placeholder="اختیاری — در صفحه اصلی فقط نام و تخصص نمایش داده می‌شود"></textarea>
        </div>
        <div class="col-md-2 d-flex align-items-end">
            <label class="form-check mb-2">
                <input type="checkbox" name="is_active" id="doctor_active" class="form-check-input" checked>
                نمایش در صفحه اصلی
            </label>
        </div>
        <input type="hidden" name="slug" id="doctor_slug">
        <div class="col-md-6">
            <label class="form-label">تصویر پروفایل</label>
            <input type="text" name="photo" id="doctor_photo" class="form-control" placeholder="مسیر تصویر یا انتخاب از کتابخانه">
            <label class="form-label mt-2">آپلود تصویر جدید</label>
            <input type="file" name="photo_file" id="doctor_photo_file" class="form-control" accept="image/jpeg,image/png,image/webp,image/gif">
            <div id="doctor_photo_preview" class="mt-2"></div>
        </div>
        <div class="col-md-6">
            <label class="form-label">انتخاب از کتابخانه رسانه</label>
            <div class="media-picker-grid" id="doctorMediaPicker">
                <?php foreach ($media as $m): ?>
                    <?php if (empty($m['path'])) continue; ?>
                    <button type="button" data-path="<?= e($m['path']) ?>" title="<?= e($m['original_name'] ?? $m['path']) ?>">
                        <img src="<?= media_url($m['path']) ?>" alt="">
                    </button>
                <?php endforeach; ?>
            </div>
            <?php if (empty($media)): ?>
                <p style="color:#6b7280;font-size:.9rem;margin:8px 0 0;">کتابخانه خالی است. از <a href="<?= url('/admin/media') ?>">رسانه</a> آپلود کنید.</p>
            <?php endif; ?>
        </div>
        <div class="col-12 d-flex gap-2">
            <button type="submit" class="btn btn-primary" style="background:#031D4F;border-color:#031D4F;">ذخیره</button>
            <button type="button" class="btn btn-outline-secondary" onclick="resetDoctorForm()">جدید</button>
        </div>
    </form>
</div>

<div class="admin-card">
    <div style="overflow-x:auto;">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>تصویر</th>
                    <th>نام</th>
                    <th>تخصص</th>
                    <th>ترتیب</th>
                    <th>وضعیت</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($items)): ?>
                    <tr><td colspan="6" class="text-center py-4" style="color:#6b7280;">عضوی با این فیلتر یافت نشد.</td></tr>
                <?php else: foreach ($items as $item): ?>
                    <?php $active = !empty($item['is_active']); ?>
                    <tr>
                        <td>
                            <?php if (!empty($item['photo'])): ?>
                                <img src="<?= media_url($item['photo']) ?>" alt="" style="width:56px;height:56px;object-fit:cover;border-radius:10px;">
                            <?php else: ?>—<?php endif; ?>
                        </td>
                        <td><?= e(trim(($item['first_name'] ?? '') . ' ' . ($item['last_name'] ?? ''))) ?></td>
                        <td><?= e($item['specialty'] ?? '—') ?></td>
                        <td style="white-space:nowrap;">
                            <span style="margin-left:8px;"><?= (int) ($item['sort_order'] ?? 0) ?></span>
                            <form method="post" action="<?= url('/admin/doctors/action') ?>" style="display:inline;"><?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
                                <input type="hidden" name="action" value="up">
                                <button class="btn btn-sm btn-outline-secondary" title="بالاتر">↑</button>
                            </form>
                            <form method="post" action="<?= url('/admin/doctors/action') ?>" style="display:inline;"><?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
                                <input type="hidden" name="action" value="down">
                                <button class="btn btn-sm btn-outline-secondary" title="پایین‌تر">↓</button>
                            </form>
                        </td>
                        <td>
                            <?php if ($active): ?>
                                <span class="admin-badge admin-badge--ok">فعال</span>
                            <?php else: ?>
                                <span class="admin-badge">غیرفعال</span>
                            <?php endif; ?>
                        </td>
                        <td style="white-space:nowrap;">
                            <button type="button" class="btn btn-sm btn-outline-primary"
                                onclick='editDoctor(<?= json_encode([
                                    'id' => (int) $item['id'],
                                    'first_name' => $item['first_name'] ?? '',
                                    'last_name' => $item['last_name'] ?? '',
                                    'slug' => $item['slug'] ?? '',
                                    'specialty' => $item['specialty'] ?? '',
                                    'biography' => $item['biography'] ?? '',
                                    'photo' => $item['photo'] ?? '',
                                    'photo_url' => !empty($item['photo']) ? media_url($item['photo']) : '',
                                    'sort_order' => (int) ($item['sort_order'] ?? 0),
                                    'is_active' => (int) ($item['is_active'] ?? 0),
                                ], JSON_UNESCAPED_UNICODE) ?>)'>ویرایش</button>
                            <form method="post" action="<?= url('/admin/doctors/action') ?>" style="display:inline;"><?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
                                <input type="hidden" name="action" value="toggle">
                                <button class="btn btn-sm <?= $active ? 'btn-outline-warning' : 'btn-outline-success' ?>"><?= $active ? 'غیرفعال' : 'فعال' ?></button>
                            </form>
                            <form method="post" action="<?= url('/admin/doctors/action') ?>" style="display:inline;" onsubmit="return confirm('بایگانی این عضو؟ از صفحه اصلی حذف می‌شود ولی داده باقی می‌ماند.');"><?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
                                <input type="hidden" name="action" value="archive">
                                <button class="btn btn-sm btn-outline-danger">بایگانی</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
<script>
function editDoctor(d) {
    document.getElementById('doctor_id').value = d.id;
    document.getElementById('doctor_first').value = d.first_name;
    document.getElementById('doctor_last').value = d.last_name;
    document.getElementById('doctor_slug').value = d.slug || '';
    document.getElementById('doctor_specialty').value = d.specialty || '';
    document.getElementById('doctor_bio').value = d.biography || '';
    document.getElementById('doctor_photo').value = d.photo || '';
    document.getElementById('doctor_sort').value = d.sort_order || 0;
    document.getElementById('doctor_active').checked = !!d.is_active;
    setDoctorPreview(d.photo_url || '');
    window.scrollTo({ top: 0, behavior: 'smooth' });
}
function resetDoctorForm() {
    document.getElementById('doctor_id').value = 0;
    document.getElementById('doctor_first').value = '';
    document.getElementById('doctor_last').value = '';
    document.getElementById('doctor_slug').value = '';
    document.getElementById('doctor_specialty').value = '';
    document.getElementById('doctor_bio').value = '';
    document.getElementById('doctor_photo').value = '';
    document.getElementById('doctor_sort').value = 0;
    document.getElementById('doctor_active').checked = true;
    document.getElementById('doctor_photo_file').value = '';
    setDoctorPreview('');
}
function setDoctorPreview(url) {
    var box = document.getElementById('doctor_photo_preview');
    box.innerHTML = url
        ? '<img src="' + url + '" alt="" style="width:88px;height:88px;object-fit:cover;border-radius:12px;">'
        : '';
}
document.getElementById('doctorMediaPicker') && document.getElementById('doctorMediaPicker').addEventListener('click', function (e) {
    var btn = e.target.closest('button[data-path]');
    if (!btn) return;
    e.preventDefault();
    var path = btn.getAttribute('data-path');
    document.getElementById('doctor_photo').value = path;
    var img = btn.querySelector('img');
    setDoctorPreview(img ? img.src : '');
});
document.getElementById('doctor_photo_file').addEventListener('change', function () {
    if (this.files && this.files[0]) {
        setDoctorPreview(URL.createObjectURL(this.files[0]));
    }
});
</script>
