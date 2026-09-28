<?php
use Sarva\Services\ArticleService;

$items = $items ?? [];
$categories = $categories ?? [];
$authors = $authors ?? [];
$filters = $filters ?? ['q' => '', 'status' => '', 'categoryId' => 0, 'authorId' => 0];

$statusLabels = [
    'draft' => 'پیش‌نویس',
    'published' => 'منتشر شده',
    'scheduled' => 'زمان‌بندی‌شده',
    'archived' => 'بایگانی',
];
?>
<div class="admin-card" style="margin-bottom:16px;">
    <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
        <p style="margin:0;color:#6b7280;">مدیریت مقالات کلینیک — ایجاد، انتشار و بهینه‌سازی SEO</p>
        <div class="d-flex gap-2">
            <a href="<?= url('/admin/article-categories') ?>" class="btn btn-outline-secondary btn-sm">دسته‌بندی‌ها</a>
            <a href="<?= url('/admin/articles/create') ?>" class="btn btn-sm" style="background:#05B18B;border-color:#05B18B;color:#fff;">افزودن مقاله</a>
        </div>
    </div>

    <form method="get" action="<?= url('/admin/articles') ?>" class="row g-2 align-items-end">
        <div class="col-md-3">
            <label class="form-label">جستجو عنوان</label>
            <input type="search" name="q" class="form-control" value="<?= e($filters['q'] ?? '') ?>" placeholder="عنوان مقاله...">
        </div>
        <div class="col-md-2">
            <label class="form-label">وضعیت</label>
            <select name="status" class="form-select">
                <option value="">همه</option>
                <?php foreach ($statusLabels as $k => $lab): ?>
                    <option value="<?= e($k) ?>" <?= ($filters['status'] ?? '') === $k ? 'selected' : '' ?>><?= e($lab) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label">دسته</label>
            <select name="category_id" class="form-select">
                <option value="0">همه</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= (int) $cat['id'] ?>" <?= (int) ($filters['categoryId'] ?? 0) === (int) $cat['id'] ? 'selected' : '' ?>><?= e($cat['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label">نویسنده</label>
            <select name="author_id" class="form-select">
                <option value="0">همه</option>
                <?php foreach ($authors as $au): ?>
                    <option value="<?= (int) $au['id'] ?>" <?= (int) ($filters['authorId'] ?? 0) === (int) $au['id'] ? 'selected' : '' ?>>
                        <?= e(trim(($au['first_name'] ?? '') . ' ' . ($au['last_name'] ?? ''))) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label">از تاریخ انتشار</label>
            <input type="text" name="published_from" class="form-control" data-jalali="date" placeholder="از تاریخ" value="<?= e($filters['publishedFrom'] ?? '') ?>">
        </div>
        <div class="col-md-2">
            <label class="form-label">تا تاریخ انتشار</label>
            <input type="text" name="published_to" class="form-control" data-jalali="date" placeholder="تا تاریخ" value="<?= e($filters['publishedTo'] ?? '') ?>">
        </div>
        <div class="col-md-2">
            <button type="submit" class="btn btn-primary w-100" style="background:#031D4F;border-color:#031D4F;">فیلتر</button>
        </div>
    </form>
</div>

<div class="admin-card">
    <div style="overflow-x:auto;">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>کاور</th>
                    <th>عنوان</th>
                    <th>دسته</th>
                    <th>نویسنده</th>
                    <th>وضعیت</th>
                    <th>انتشار</th>
                    <th>SEO</th>
                    <th>آخرین ویرایش</th>
                    <th>عملیات</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($items)): ?>
                    <tr><td colspan="9">مقاله‌ای یافت نشد.</td></tr>
                <?php else: foreach ($items as $item): ?>
                    <?php
                    $seo = ArticleService::seoStatus($item);
                    $st = (string) ($item['status'] ?? 'draft');
                    ?>
                    <tr>
                        <td>
                            <?php if (!empty($item['cover'])): ?>
                                <img src="<?= media_url($item['cover']) ?>" alt="" style="width:56px;height:40px;object-fit:cover;border-radius:8px;">
                            <?php else: ?>
                                <span style="color:#9ca3af;">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <strong><?= e($item['title'] ?? '') ?></strong>
                            <div style="font-size:.8rem;color:#6b7280;direction:ltr;text-align:right;"><code><?= e($item['slug'] ?? '') ?></code></div>
                        </td>
                        <td><?= e($item['category_name'] ?? '—') ?></td>
                        <td><?= e(trim((string) ($item['author_name'] ?? '')) ?: '—') ?></td>
                        <td><?= e($statusLabels[$st] ?? $st) ?></td>
                        <td><?= e(to_jalali($item['published_at'] ?? $item['scheduled_at'] ?? null)) ?></td>
                        <td><span class="seo-badge <?= e($seo) ?>"><?= e(ArticleService::seoStatusLabel($seo)) ?></span></td>
                        <td><?= e(to_jalali($item['updated_at'] ?? null)) ?></td>
                        <td>
                            <div class="d-flex flex-wrap gap-1">
                                <a class="btn btn-sm btn-outline-primary" href="<?= url('/admin/articles/' . (int) $item['id']) ?>">ویرایش</a>
                                <a class="btn btn-sm btn-outline-secondary" href="<?= url('/admin/articles/preview/' . (int) $item['id']) ?>" target="_blank" rel="noopener">پیش‌نمایش</a>
                                <?php if ($st !== 'published'): ?>
                                    <form method="post" action="<?= url('/admin/articles/action') ?>" style="display:inline;">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
                                        <input type="hidden" name="action" value="publish">
                                        <button type="submit" class="btn btn-sm btn-outline-success">انتشار</button>
                                    </form>
                                <?php else: ?>
                                    <form method="post" action="<?= url('/admin/articles/action') ?>" style="display:inline;">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
                                        <input type="hidden" name="action" value="unpublish">
                                        <button type="submit" class="btn btn-sm btn-outline-warning">لغو انتشار</button>
                                    </form>
                                <?php endif; ?>
                                <form method="post" action="<?= url('/admin/articles/action') ?>" style="display:inline;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
                                    <input type="hidden" name="action" value="duplicate">
                                    <button type="submit" class="btn btn-sm btn-outline-secondary">کپی</button>
                                </form>
                                <form method="post" action="<?= url('/admin/articles/action') ?>" style="display:inline;" onsubmit="return confirm('حذف این مقاله؟');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <button type="submit" class="btn btn-sm btn-outline-danger">حذف</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
