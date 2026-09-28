<?php
$page = $page ?? [];
$sections = $sections ?? [];
$pageId = (int) ($page['id'] ?? 0);
$sectionLabels = [
    'hero' => 'هیرو (تصویر پس‌زمینه صفحه اصلی)',
    'about' => 'درباره ما (تصویر بخش درباره)',
    'services' => 'خدمات',
];
?>
<div class="admin-card">
    <form method="post" action="<?= url('/admin/pages/' . $pageId) ?>" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <label class="form-label">عنوان</label>
                <input type="text" name="title" class="form-control" required value="<?= e($page['title'] ?? '') ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">وضعیت</label>
                <select name="status" class="form-select">
                    <option value="published" <?= ($page['status'] ?? '') === 'published' ? 'selected' : '' ?>>منتشر شده</option>
                    <option value="draft" <?= ($page['status'] ?? '') === 'draft' ? 'selected' : '' ?>>پیش‌نویس</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">نامک</label>
                <input type="text" class="form-control" value="<?= e($page['slug'] ?? '') ?>" disabled>
            </div>
            <div class="col-md-6">
                <label class="form-label">عنوان متا</label>
                <input type="text" name="meta_title" class="form-control" value="<?= e($page['meta_title'] ?? '') ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">توضیح متا</label>
                <input type="text" name="meta_description" class="form-control" value="<?= e($page['meta_description'] ?? '') ?>">
            </div>
        </div>

        <h2 style="margin:8px 0 8px;font-size:1.1rem;color:#031D4F;">بخش‌های صفحه و تصاویر</h2>
        <p style="margin:0 0 16px;color:#6b7280;font-size:.9rem;">تصاویر هر بخش در پایگاه داده ذخیره می‌شوند و با آپلود فایل یا مسیر نسبی قابل تغییر هستند.</p>
        <?php if (empty($sections)): ?>
            <p style="color:#6b7280;">بخشی برای این صفحه تعریف نشده است.</p>
        <?php else: foreach ($sections as $sec): ?>
            <?php
                $sid = (int) $sec['id'];
                $key = (string) ($sec['section_key'] ?? '');
                $label = $sectionLabels[$key] ?? $key;
                $isHero = $key === 'hero';
            ?>
            <div class="border rounded-3 p-3 mb-3" style="border-color:#e8ecf4!important;">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <strong style="color:#031D4F;"><?= e($label) ?></strong>
                    <label class="form-check m-0">
                        <input type="checkbox" class="form-check-input" name="sections[<?= $sid ?>][is_visible]" value="1" <?= !empty($sec['is_visible']) ? 'checked' : '' ?>>
                        نمایش
                    </label>
                </div>
                <div class="row g-2">
                    <div class="col-md-6">
                        <label class="form-label">عنوان</label>
                        <input type="text" name="sections[<?= $sid ?>][title]" class="form-control" value="<?= e($sec['title'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">زیرعنوان</label>
                        <input type="text" name="sections[<?= $sid ?>][subtitle]" class="form-control" value="<?= e($sec['subtitle'] ?? '') ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label">توضیحات</label>
                        <textarea name="sections[<?= $sid ?>][description]" class="form-control" rows="3"><?= e($sec['description'] ?? '') ?></textarea>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label"><?= $isHero ? 'تصویر پس‌زمینه هیرو' : 'تصویر بخش' ?> — مسیر</label>
                        <input type="text" name="sections[<?= $sid ?>][image]" class="form-control" value="<?= e($sec['image'] ?? '') ?>" placeholder="images/... یا uploads/...">
                        <?php if (!empty($sec['image'])): ?>
                            <img src="<?= media_url($sec['image']) ?>" alt="" class="mt-2" style="width:<?= $isHero ? '160px;height:90px' : '96px;height:96px' ?>;object-fit:cover;border-radius:10px;">
                        <?php endif; ?>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">آپلود تصویر جدید</label>
                        <input type="file" name="section_image_<?= $sid ?>" class="form-control" accept="image/jpeg,image/png,image/webp,image/gif">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">متن دکمه</label>
                        <input type="text" name="sections[<?= $sid ?>][cta_text]" class="form-control" value="<?= e($sec['cta_text'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">لینک دکمه</label>
                        <input type="text" name="sections[<?= $sid ?>][cta_link]" class="form-control" value="<?= e($sec['cta_link'] ?? '') ?>">
                    </div>
                </div>
            </div>
        <?php endforeach; endif; ?>

        <div class="d-flex gap-2 mt-3">
            <button type="submit" class="btn btn-primary" style="background:#031D4F;border-color:#031D4F;">ذخیره صفحه</button>
            <a href="<?= url('/admin/pages') ?>" class="btn btn-outline-secondary">بازگشت</a>
        </div>
    </form>
</div>
