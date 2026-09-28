<?php $items = $items ?? []; ?>
<div class="admin-card">
    <h2 style="margin:0 0 16px;font-size:1.1rem;color:#031D4F;">افزودن / ویرایش خدمت</h2>
    <form method="post" action="<?= url('/admin/services/save') ?>" enctype="multipart/form-data" class="row g-3">
        <?= csrf_field() ?>
        <input type="hidden" name="id" id="service_id" value="0">
        <div class="col-md-4">
            <label class="form-label">نام</label>
            <input type="text" name="name" id="service_name" class="form-control" required>
        </div>
        <div class="col-md-4">
            <label class="form-label">نامک</label>
            <input type="text" name="slug" id="service_slug" class="form-control">
        </div>
        <div class="col-md-2">
            <label class="form-label">قیمت (تومان)</label>
            <input type="number" name="price" id="service_price" class="form-control" value="0" min="0">
        </div>
        <div class="col-md-2">
            <label class="form-label">مدت (دقیقه)</label>
            <input type="number" name="duration_minutes" id="service_duration" class="form-control" value="30" min="5">
        </div>
        <div class="col-md-8">
            <label class="form-label">توضیح کوتاه</label>
            <input type="text" name="short_description" id="service_short" class="form-control">
        </div>
        <div class="col-md-2">
            <label class="form-label">ترتیب</label>
            <input type="number" name="sort_order" id="service_sort" class="form-control" value="0">
        </div>
        <div class="col-md-2 d-flex align-items-end">
            <label class="form-check mb-2">
                <input type="checkbox" name="is_active" id="service_active" class="form-check-input" checked>
                فعال
            </label>
        </div>
        <div class="col-12">
            <label class="form-label">توضیحات کامل</label>
            <textarea name="description" id="service_description" class="form-control" rows="4"></textarea>
        </div>
        <div class="col-md-6">
            <label class="form-label">تصویر — مسیر</label>
            <input type="text" name="image" id="service_image" class="form-control" placeholder="images/... یا uploads/...">
            <label class="form-label mt-2">یا آپلود تصویر</label>
            <input type="file" name="image_file" class="form-control" accept="image/jpeg,image/png,image/webp,image/gif">
            <div id="service_image_preview" class="mt-2"></div>
        </div>
        <div class="col-md-6">
            <label class="form-label">آیکون — مسیر</label>
            <input type="text" name="icon" id="service_icon" class="form-control" placeholder="images/icon-service-item-1.svg">
            <label class="form-label mt-2">یا آپلود آیکون</label>
            <input type="file" name="icon_file" class="form-control" accept="image/jpeg,image/png,image/webp,image/gif,image/svg+xml">
            <div id="service_icon_preview" class="mt-2"></div>
        </div>
        <div class="col-12">
            <button type="submit" class="btn btn-primary" style="background:#031D4F;border-color:#031D4F;">ذخیره</button>
            <button type="button" class="btn btn-outline-secondary" onclick="resetServiceForm()">جدید</button>
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
                    <th>نامک</th>
                    <th>قیمت</th>
                    <th>مدت</th>
                    <th>ترتیب</th>
                    <th>وضعیت</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($items)): ?>
                    <tr><td colspan="8">خدمتی ثبت نشده است.</td></tr>
                <?php else: foreach ($items as $item): ?>
                    <tr>
                        <td>
                            <?php if (!empty($item['image'])): ?>
                                <img src="<?= media_url($item['image']) ?>" alt="" style="width:48px;height:48px;object-fit:cover;border-radius:8px;">
                            <?php else: ?>—<?php endif; ?>
                        </td>
                        <td><?= e($item['name'] ?? '') ?></td>
                        <td><code><?= e($item['slug'] ?? '') ?></code></td>
                        <td><?= number_format((float) ($item['price'] ?? 0)) ?></td>
                        <td><?= (int) ($item['duration_minutes'] ?? 0) ?></td>
                        <td><?= (int) ($item['sort_order'] ?? 0) ?></td>
                        <td><?= !empty($item['is_active']) ? 'فعال' : 'غیرفعال' ?></td>
                        <td>
                            <button type="button" class="btn btn-sm btn-outline-primary"
                                onclick='editService(<?= json_encode([
                                    'id' => (int) $item['id'],
                                    'name' => $item['name'] ?? '',
                                    'slug' => $item['slug'] ?? '',
                                    'price' => (int) ($item['price'] ?? 0),
                                    'duration_minutes' => (int) ($item['duration_minutes'] ?? 30),
                                    'short_description' => $item['short_description'] ?? '',
                                    'description' => $item['description'] ?? '',
                                    'image' => $item['image'] ?? '',
                                    'image_url' => !empty($item['image']) ? media_url($item['image']) : '',
                                    'icon' => $item['icon'] ?? '',
                                    'icon_url' => !empty($item['icon']) ? media_url($item['icon']) : '',
                                    'sort_order' => (int) ($item['sort_order'] ?? 0),
                                    'is_active' => (int) ($item['is_active'] ?? 0),
                                ], JSON_UNESCAPED_UNICODE) ?>)'>ویرایش</button>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
<script>
function editService(s) {
    document.getElementById('service_id').value = s.id;
    document.getElementById('service_name').value = s.name;
    document.getElementById('service_slug').value = s.slug;
    document.getElementById('service_price').value = s.price;
    document.getElementById('service_duration').value = s.duration_minutes;
    document.getElementById('service_short').value = s.short_description || '';
    document.getElementById('service_description').value = s.description || '';
    document.getElementById('service_image').value = s.image || '';
    document.getElementById('service_icon').value = s.icon || '';
    document.getElementById('service_sort').value = s.sort_order || 0;
    document.getElementById('service_active').checked = !!s.is_active;
    var box = document.getElementById('service_image_preview');
    box.innerHTML = s.image_url
        ? '<img src="' + s.image_url + '" alt="" style="width:80px;height:80px;object-fit:cover;border-radius:10px;">'
        : '';
    var ibox = document.getElementById('service_icon_preview');
    ibox.innerHTML = s.icon_url
        ? '<img src="' + s.icon_url + '" alt="" style="width:48px;height:48px;object-fit:contain;border-radius:8px;background:#f3f4f6;padding:4px;">'
        : '';
    window.scrollTo({ top: 0, behavior: 'smooth' });
}
function resetServiceForm() {
    document.getElementById('service_id').value = 0;
    document.getElementById('service_name').value = '';
    document.getElementById('service_slug').value = '';
    document.getElementById('service_price').value = 0;
    document.getElementById('service_duration').value = 30;
    document.getElementById('service_short').value = '';
    document.getElementById('service_description').value = '';
    document.getElementById('service_image').value = '';
    document.getElementById('service_icon').value = '';
    document.getElementById('service_sort').value = 0;
    document.getElementById('service_active').checked = true;
    document.getElementById('service_image_preview').innerHTML = '';
    document.getElementById('service_icon_preview').innerHTML = '';
}
</script>
