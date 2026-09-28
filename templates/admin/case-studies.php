<?php $items = $items ?? []; ?>
<div class="admin-card">
    <h2 style="margin:0 0 8px;font-size:1.1rem;color:#031D4F;">افزودن / ویرایش قبل و بعد</h2>
    <p style="margin:0 0 16px;color:#666;font-size:.92rem;">تصاویر را آپلود کنید یا مسیر نسبی از پوشه assets وارد کنید (مثلاً <code>images/transformation-img-before-1.jpg</code>). سه مورد اول منتشرشده در صفحه اصلی نمایش داده می‌شوند.</p>
    <form method="post" action="<?= url('/admin/case-studies/save') ?>" enctype="multipart/form-data" class="row g-3">
        <?= csrf_field() ?>
        <input type="hidden" name="id" id="cs_id" value="0">
        <div class="col-md-5">
            <label class="form-label">عنوان</label>
            <input type="text" name="title" id="cs_title" class="form-control" required>
        </div>
        <div class="col-md-4">
            <label class="form-label">نامک (Slug)</label>
            <input type="text" name="slug" id="cs_slug" class="form-control" placeholder="اختیاری">
        </div>
        <div class="col-md-3">
            <label class="form-label">ترتیب</label>
            <input type="number" name="sort_order" id="cs_sort" class="form-control" value="0">
        </div>
        <div class="col-12">
            <label class="form-label">توضیحات</label>
            <textarea name="description" id="cs_description" class="form-control" rows="3"></textarea>
        </div>
        <div class="col-md-6">
            <label class="form-label">تصویر قبل — مسیر</label>
            <input type="text" name="before_image" id="cs_before" class="form-control" placeholder="images/... یا uploads/...">
            <label class="form-label mt-2">یا آپلود تصویر قبل</label>
            <input type="file" name="before_file" class="form-control" accept="image/jpeg,image/png,image/webp,image/gif">
            <div id="cs_before_preview" class="mt-2"></div>
        </div>
        <div class="col-md-6">
            <label class="form-label">تصویر بعد — مسیر</label>
            <input type="text" name="after_image" id="cs_after" class="form-control" placeholder="images/... یا uploads/...">
            <label class="form-label mt-2">یا آپلود تصویر بعد</label>
            <input type="file" name="after_file" class="form-control" accept="image/jpeg,image/png,image/webp,image/gif">
            <div id="cs_after_preview" class="mt-2"></div>
        </div>
        <div class="col-md-4">
            <label class="form-label">وضعیت</label>
            <select name="status" id="cs_status" class="form-select">
                <option value="published">منتشر شده</option>
                <option value="draft">پیش‌نویس</option>
            </select>
        </div>
        <div class="col-12">
            <button type="submit" class="btn btn-primary" style="background:#031D4F;border-color:#031D4F;">ذخیره</button>
            <button type="button" class="btn btn-outline-secondary" onclick="resetCaseForm()">جدید</button>
        </div>
    </form>
</div>

<div class="admin-card">
    <div style="overflow-x:auto;">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>قبل</th>
                    <th>بعد</th>
                    <th>عنوان</th>
                    <th>ترتیب</th>
                    <th>وضعیت</th>
                    <th>عملیات</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($items)): ?>
                    <tr><td colspan="6">نمونه کاری ثبت نشده است.</td></tr>
                <?php else: foreach ($items as $item): ?>
                    <tr>
                        <td>
                            <?php if (!empty($item['before_image'])): ?>
                                <img src="<?= media_url($item['before_image']) ?>" alt="" style="width:64px;height:64px;object-fit:cover;border-radius:8px;">
                            <?php else: ?>—<?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($item['after_image'])): ?>
                                <img src="<?= media_url($item['after_image']) ?>" alt="" style="width:64px;height:64px;object-fit:cover;border-radius:8px;">
                            <?php else: ?>—<?php endif; ?>
                        </td>
                        <td><?= e($item['title'] ?? '') ?></td>
                        <td><?= (int) ($item['sort_order'] ?? 0) ?></td>
                        <td><?= ($item['status'] ?? '') === 'published' ? 'منتشر شده' : 'پیش‌نویس' ?></td>
                        <td style="white-space:nowrap;">
                            <button type="button" class="btn btn-sm btn-outline-primary"
                                onclick='editCase(<?= json_encode([
                                    'id' => (int) $item['id'],
                                    'title' => $item['title'] ?? '',
                                    'slug' => $item['slug'] ?? '',
                                    'description' => $item['description'] ?? '',
                                    'before_image' => $item['before_image'] ?? '',
                                    'after_image' => $item['after_image'] ?? '',
                                    'status' => $item['status'] ?? 'published',
                                    'sort_order' => (int) ($item['sort_order'] ?? 0),
                                    'before_url' => !empty($item['before_image']) ? media_url($item['before_image']) : '',
                                    'after_url' => !empty($item['after_image']) ? media_url($item['after_image']) : '',
                                ], JSON_UNESCAPED_UNICODE) ?>)'>ویرایش</button>
                            <form method="post" action="<?= url('/admin/case-studies/delete') ?>" style="display:inline;" onsubmit="return confirm('حذف این نمونه کار؟');">
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
function setPreview(elId, url) {
    var box = document.getElementById(elId);
    box.innerHTML = url
        ? '<img src="' + url + '" alt="" style="width:96px;height:96px;object-fit:cover;border-radius:10px;">'
        : '';
}
function editCase(c) {
    document.getElementById('cs_id').value = c.id;
    document.getElementById('cs_title').value = c.title || '';
    document.getElementById('cs_slug').value = c.slug || '';
    document.getElementById('cs_description').value = c.description || '';
    document.getElementById('cs_before').value = c.before_image || '';
    document.getElementById('cs_after').value = c.after_image || '';
    document.getElementById('cs_status').value = c.status || 'published';
    document.getElementById('cs_sort').value = c.sort_order || 0;
    setPreview('cs_before_preview', c.before_url || '');
    setPreview('cs_after_preview', c.after_url || '');
    window.scrollTo({ top: 0, behavior: 'smooth' });
}
function resetCaseForm() {
    document.getElementById('cs_id').value = 0;
    document.getElementById('cs_title').value = '';
    document.getElementById('cs_slug').value = '';
    document.getElementById('cs_description').value = '';
    document.getElementById('cs_before').value = '';
    document.getElementById('cs_after').value = '';
    document.getElementById('cs_status').value = 'published';
    document.getElementById('cs_sort').value = 0;
    setPreview('cs_before_preview', '');
    setPreview('cs_after_preview', '');
}
</script>
