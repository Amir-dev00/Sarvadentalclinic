<?php
$items = $items ?? [];
$edit = null;
if (!empty($_GET['edit'])) {
    $eid = (int) $_GET['edit'];
    foreach ($items as $it) {
        if ((int) $it['id'] === $eid) {
            $edit = $it;
            break;
        }
    }
}
?>
<div class="row g-3">
    <div class="col-lg-4">
        <div class="admin-card">
            <h2 style="margin:0 0 16px;font-size:1.1rem;color:#031D4F;"><?= $edit ? 'ویرایش دسته' : 'افزودن دسته' ?></h2>
            <form method="post" action="<?= url('/admin/article-categories/save') ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">
                <div class="mb-3">
                    <label class="form-label">نام دسته</label>
                    <input type="text" name="name" class="form-control" required value="<?= e($edit['name'] ?? '') ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">نامک (slug)</label>
                    <input type="text" name="slug" class="form-control" value="<?= e($edit['slug'] ?? '') ?>" placeholder="خودکار از نام">
                </div>
                <div class="mb-3">
                    <label class="form-label">ترتیب نمایش</label>
                    <input type="number" name="sort_order" class="form-control" value="<?= e((string) ($edit['sort_order'] ?? 0)) ?>">
                </div>
                <div class="form-check mb-3">
                    <input type="checkbox" class="form-check-input" name="is_active" id="catActive" value="1" <?= !isset($edit) || !empty($edit['is_active']) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="catActive">فعال</label>
                </div>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn" style="background:#05B18B;border-color:#05B18B;color:#fff;">ذخیره</button>
                    <?php if ($edit): ?>
                        <a href="<?= url('/admin/article-categories') ?>" class="btn btn-outline-secondary">انصراف</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
        <p style="margin-top:12px;"><a href="<?= url('/admin/articles') ?>">← بازگشت به مقالات</a></p>
    </div>
    <div class="col-lg-8">
        <div class="admin-card">
            <div style="overflow-x:auto;">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>نام</th>
                            <th>نامک</th>
                            <th>ترتیب</th>
                            <th>وضعیت</th>
                            <th>عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($items)): ?>
                            <tr><td colspan="5">دسته‌ای تعریف نشده است.</td></tr>
                        <?php else: foreach ($items as $item): ?>
                            <tr>
                                <td><?= e($item['name'] ?? '') ?></td>
                                <td><code><?= e($item['slug'] ?? '') ?></code></td>
                                <td><?= (int) ($item['sort_order'] ?? 0) ?></td>
                                <td><?= !empty($item['is_active']) ? 'فعال' : 'غیرفعال' ?></td>
                                <td>
                                    <a class="btn btn-sm btn-outline-primary" href="<?= url('/admin/article-categories?edit=' . (int) $item['id']) ?>">ویرایش</a>
                                    <form method="post" action="<?= url('/admin/article-categories/delete') ?>" style="display:inline;" onsubmit="return confirm('حذف این دسته؟');">
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
    </div>
</div>
