<?php $items = $items ?? []; ?>
<div class="admin-card">
    <h2 style="margin:0 0 8px;font-size:1.1rem;color:#031D4F;">افزودن / ویرایش نظر</h2>
    <p style="margin:0 0 16px;color:#666;font-size:.92rem;">نظرات ثبت‌شده از سایت با وضعیت «در انتظار» می‌آیند. می‌توانید آن‌ها را ویرایش، منتشر یا حذف کنید.</p>
    <form method="post" action="<?= url('/admin/testimonials/save') ?>" enctype="multipart/form-data" class="row g-3">
        <?= csrf_field() ?>
        <input type="hidden" name="id" id="t_id" value="0">
        <div class="col-md-4">
            <label class="form-label">نام بیمار</label>
            <input type="text" name="patient_name" id="t_name" class="form-control" required maxlength="150">
        </div>
        <div class="col-md-2">
            <label class="form-label">امتیاز</label>
            <select name="rating" id="t_rating" class="form-select">
                <?php for ($r = 5; $r >= 1; $r--): ?>
                <option value="<?= $r ?>"<?= $r === 5 ? ' selected' : '' ?>><?= $r ?></option>
                <?php endfor; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label">ترتیب</label>
            <input type="number" name="sort_order" id="t_sort" class="form-control" value="0">
        </div>
        <div class="col-md-4 d-flex align-items-end gap-3">
            <label class="form-check mb-2">
                <input type="checkbox" name="is_active" id="t_active" class="form-check-input" checked>
                منتشر شده
            </label>
        </div>
        <div class="col-12">
            <label class="form-label">متن نظر</label>
            <textarea name="content" id="t_content" class="form-control" rows="4" required></textarea>
        </div>
        <div class="col-md-6">
            <label class="form-label">آواتار — مسیر</label>
            <input type="text" name="avatar" id="t_avatar" class="form-control" placeholder="اختیاری — images/... یا uploads/...">
            <label class="form-label mt-2">یا آپلود آواتار</label>
            <input type="file" name="avatar_file" class="form-control" accept="image/jpeg,image/png,image/webp,image/gif">
        </div>
        <div class="col-12">
            <button type="submit" class="btn btn-primary" style="background:#031D4F;border-color:#031D4F;">ذخیره</button>
            <button type="button" class="btn btn-outline-secondary" onclick="resetTestimonialForm()">جدید</button>
        </div>
    </form>
</div>

<div class="admin-card">
    <div style="overflow-x:auto;">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>نام بیمار</th>
                    <th>نظر</th>
                    <th>امتیاز</th>
                    <th>ترتیب</th>
                    <th>تاریخ</th>
                    <th>وضعیت</th>
                    <th>عملیات</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($items)): ?>
                    <tr><td colspan="7">نظری ثبت نشده است.</td></tr>
                <?php else: foreach ($items as $item): ?>
                    <?php $active = !empty($item['is_active']); ?>
                    <tr>
                        <td><?= e($item['patient_name'] ?? '') ?></td>
                        <td style="max-width:320px;"><?= e(mb_substr((string) ($item['content'] ?? ''), 0, 140)) ?></td>
                        <td><?= e((string) ($item['rating'] ?? '—')) ?></td>
                        <td><?= (int) ($item['sort_order'] ?? 0) ?></td>
                        <td><?= e(to_jalali($item['created_at'] ?? null, 'Y/m/d H:i') ?: '—') ?></td>
                        <td><?= $active ? 'منتشر شده' : 'در انتظار' ?></td>
                        <td style="white-space:nowrap;">
                            <button type="button" class="btn btn-sm btn-outline-primary"
                                onclick='editTestimonial(<?= json_encode([
                                    'id' => (int) $item['id'],
                                    'patient_name' => $item['patient_name'] ?? '',
                                    'content' => $item['content'] ?? '',
                                    'rating' => (int) ($item['rating'] ?? 5),
                                    'avatar' => $item['avatar'] ?? '',
                                    'sort_order' => (int) ($item['sort_order'] ?? 0),
                                    'is_active' => (int) ($item['is_active'] ?? 0),
                                ], JSON_UNESCAPED_UNICODE) ?>)'>ویرایش</button>
                            <form method="post" action="<?= url('/admin/testimonials/toggle') ?>" style="display:inline;">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
                                <button type="submit" class="btn btn-sm <?= $active ? 'btn-outline-warning' : 'btn-outline-success' ?>">
                                    <?= $active ? 'عدم انتشار' : 'انتشار' ?>
                                </button>
                            </form>
                            <form method="post" action="<?= url('/admin/testimonials/delete') ?>" style="display:inline;" onsubmit="return confirm('حذف این نظر؟');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger">حذف</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
<script>
function editTestimonial(t) {
    document.getElementById('t_id').value = t.id;
    document.getElementById('t_name').value = t.patient_name || '';
    document.getElementById('t_content').value = t.content || '';
    document.getElementById('t_rating').value = t.rating || 5;
    document.getElementById('t_avatar').value = t.avatar || '';
    document.getElementById('t_sort').value = t.sort_order || 0;
    document.getElementById('t_active').checked = !!t.is_active;
    window.scrollTo({ top: 0, behavior: 'smooth' });
}
function resetTestimonialForm() {
    document.getElementById('t_id').value = 0;
    document.getElementById('t_name').value = '';
    document.getElementById('t_content').value = '';
    document.getElementById('t_rating').value = 5;
    document.getElementById('t_avatar').value = '';
    document.getElementById('t_sort').value = 0;
    document.getElementById('t_active').checked = true;
}
</script>
