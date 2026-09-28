<?php $items = $items ?? []; ?>
<div class="admin-card">
    <h2 style="margin:0 0 8px;font-size:1.1rem;color:#031D4F;">افزودن / ویرایش آیتم گالری</h2>
    <p style="margin:0 0 16px;color:#666;font-size:.92rem;">برای تصویر، فایل آپلود کنید یا مسیر وارد کنید. برای ویدیو، مسیر فایل یا لینک ویدیو را در فیلد مسیر بگذارید.</p>
    <form method="post" action="<?= url('/admin/gallery/save') ?>" enctype="multipart/form-data" class="row g-3">
        <?= csrf_field() ?>
        <input type="hidden" name="id" id="g_id" value="0">
        <div class="col-md-4">
            <label class="form-label">عنوان</label>
            <input type="text" name="title" id="g_title" class="form-control">
        </div>
        <div class="col-md-2">
            <label class="form-label">نوع</label>
            <select name="type" id="g_type" class="form-select">
                <option value="image">تصویر</option>
                <option value="video">ویدیو</option>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label">دسته</label>
            <input type="text" name="category" id="g_category" class="form-control" placeholder="اختیاری">
        </div>
        <div class="col-md-1">
            <label class="form-label">ترتیب</label>
            <input type="number" name="sort_order" id="g_sort" class="form-control" value="0">
        </div>
        <div class="col-md-2 d-flex align-items-end">
            <label class="form-check mb-2">
                <input type="checkbox" name="is_active" id="g_active" class="form-check-input" checked>
                فعال
            </label>
        </div>
        <div class="col-md-6">
            <label class="form-label">مسیر رسانه</label>
            <input type="text" name="media_path" id="g_media" class="form-control" placeholder="images/... یا uploads/... یا URL ویدیو">
            <label class="form-label mt-2">یا آپلود تصویر</label>
            <input type="file" name="media_file" class="form-control" accept="image/jpeg,image/png,image/webp,image/gif">
        </div>
        <div class="col-md-6">
            <label class="form-label">تصویر بندانگشتی (ویدیو)</label>
            <input type="text" name="thumb_path" id="g_thumb" class="form-control" placeholder="اختیاری">
            <label class="form-label mt-2">یا آپلود بندانگشتی</label>
            <input type="file" name="thumb_file" class="form-control" accept="image/jpeg,image/png,image/webp,image/gif">
        </div>
        <div class="col-12">
            <button type="submit" class="btn btn-primary" style="background:#031D4F;border-color:#031D4F;">ذخیره</button>
            <button type="button" class="btn btn-outline-secondary" onclick="resetGalleryForm()">جدید</button>
        </div>
    </form>
</div>

<div class="admin-card">
    <div style="overflow-x:auto;">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>پیش‌نمایش</th>
                    <th>عنوان</th>
                    <th>نوع</th>
                    <th>مسیر</th>
                    <th>دسته</th>
                    <th>ترتیب</th>
                    <th>وضعیت</th>
                    <th>عملیات</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($items)): ?>
                    <tr><td colspan="8">آیتمی در گالری نیست.</td></tr>
                <?php else: foreach ($items as $item): ?>
                    <?php
                    $preview = $item['thumb_path'] ?: $item['media_path'] ?? '';
                    $isVideo = ($item['type'] ?? '') === 'video';
                    ?>
                    <tr>
                        <td>
                            <?php if ($preview && !$isVideo): ?>
                                <img src="<?= media_url($preview) ?>" alt="" style="width:56px;height:56px;object-fit:cover;border-radius:8px;">
                            <?php elseif ($preview && $isVideo && !empty($item['thumb_path'])): ?>
                                <img src="<?= media_url($item['thumb_path']) ?>" alt="" style="width:56px;height:56px;object-fit:cover;border-radius:8px;">
                            <?php else: ?>
                                <?= $isVideo ? 'ویدیو' : '—' ?>
                            <?php endif; ?>
                        </td>
                        <td><?= e($item['title'] ?? '—') ?></td>
                        <td><?= $isVideo ? 'ویدیو' : 'تصویر' ?></td>
                        <td><code style="font-size:.8rem;"><?= e(mb_substr((string) ($item['media_path'] ?? ''), 0, 40)) ?></code></td>
                        <td><?= e($item['category'] ?? '—') ?></td>
                        <td><?= (int) ($item['sort_order'] ?? 0) ?></td>
                        <td><?= !empty($item['is_active']) ? 'فعال' : 'غیرفعال' ?></td>
                        <td style="white-space:nowrap;">
                            <button type="button" class="btn btn-sm btn-outline-primary"
                                onclick='editGallery(<?= json_encode([
                                    'id' => (int) $item['id'],
                                    'title' => $item['title'] ?? '',
                                    'type' => $item['type'] ?? 'image',
                                    'media_path' => $item['media_path'] ?? '',
                                    'thumb_path' => $item['thumb_path'] ?? '',
                                    'category' => $item['category'] ?? '',
                                    'sort_order' => (int) ($item['sort_order'] ?? 0),
                                    'is_active' => (int) ($item['is_active'] ?? 0),
                                ], JSON_UNESCAPED_UNICODE) ?>)'>ویرایش</button>
                            <form method="post" action="<?= url('/admin/gallery/delete') ?>" style="display:inline;" onsubmit="return confirm('حذف این آیتم؟');">
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
function editGallery(g) {
    document.getElementById('g_id').value = g.id;
    document.getElementById('g_title').value = g.title || '';
    document.getElementById('g_type').value = g.type || 'image';
    document.getElementById('g_media').value = g.media_path || '';
    document.getElementById('g_thumb').value = g.thumb_path || '';
    document.getElementById('g_category').value = g.category || '';
    document.getElementById('g_sort').value = g.sort_order || 0;
    document.getElementById('g_active').checked = !!g.is_active;
    window.scrollTo({ top: 0, behavior: 'smooth' });
}
function resetGalleryForm() {
    document.getElementById('g_id').value = 0;
    document.getElementById('g_title').value = '';
    document.getElementById('g_type').value = 'image';
    document.getElementById('g_media').value = '';
    document.getElementById('g_thumb').value = '';
    document.getElementById('g_category').value = '';
    document.getElementById('g_sort').value = 0;
    document.getElementById('g_active').checked = true;
}
</script>
