<?php
$article = $article ?? null;
$categories = $categories ?? [];
$authors = $authors ?? [];
$media = $media ?? [];
$isEdit = !empty($article);
$id = (int) ($article['id'] ?? 0);

$toLocal = static function (?string $dt): string {
    if (!$dt) {
        return '';
    }
    $ts = strtotime($dt);
    return $ts ? date('Y-m-d\TH:i', $ts) : '';
};

$status = (string) ($article['status'] ?? 'draft');
$cover = (string) ($article['cover'] ?? '');
$ogImage = (string) ($article['og_image'] ?? '');
$contentHtml = (string) ($article['content'] ?? '');
$seoTitle = (string) ($article['seo_title'] ?? $article['meta_title'] ?? '');
$seoDesc = (string) ($article['seo_description'] ?? $article['meta_description'] ?? '');
$slug = (string) ($article['slug'] ?? '');
$appUrl = rtrim((string) config('app.url'), '/');

$adminHead = '<link href="https://cdn.jsdelivr.net/npm/quill@2.0.2/dist/quill.snow.css" rel="stylesheet">';
?>
<form method="post" action="<?= url('/admin/articles/save') ?>" id="articleForm">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= $id ?>">
    <input type="hidden" name="content" id="contentField" value="">

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="admin-card">
                <h2 style="margin:0 0 16px;font-size:1.1rem;color:#031D4F;">اطلاعات پایه</h2>
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label">عنوان مقاله</label>
                        <input type="text" name="title" id="titleField" class="form-control" required value="<?= e($article['title'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">نامک (Slug)</label>
                        <input type="text" name="slug" id="slugField" class="form-control" dir="ltr" value="<?= e($slug) ?>" placeholder="auto-from-title">
                        <div class="form-text">پس از انتشار، فقط در صورت نیاز دستی تغییر دهید.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">دسته‌بندی</label>
                        <select name="category_id" class="form-select">
                            <option value="0">بدون دسته</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= (int) $cat['id'] ?>" <?= (int) ($article['category_id'] ?? 0) === (int) $cat['id'] ? 'selected' : '' ?>><?= e($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">خلاصه کوتاه</label>
                        <textarea name="excerpt" class="form-control" rows="3"><?= e($article['excerpt'] ?? '') ?></textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label">متن کامل</label>
                        <div id="quillEditor"><?= $contentHtml ?></div>
                        <div class="form-text">سرفصل‌ها، لیست، لینک، نقل‌قول و تصویر پشتیبانی می‌شوند. HTML ناامن حذف می‌شود.</div>
                    </div>
                </div>
            </div>

            <div class="admin-card">
                <h2 style="margin:0 0 16px;font-size:1.1rem;color:#031D4F;">سئو</h2>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">SEO Title</label>
                        <input type="text" name="seo_title" id="seoTitle" class="form-control" value="<?= e($seoTitle) ?>">
                        <div class="seo-hint" id="seoTitleHint">حدود ۵۰–۶۰ کاراکتر توصیه می‌شود</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Canonical URL</label>
                        <input type="url" name="canonical_url" class="form-control" dir="ltr" value="<?= e($article['canonical_url'] ?? '') ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Meta Description</label>
                        <textarea name="seo_description" id="seoDesc" class="form-control" rows="3"><?= e($seoDesc) ?></textarea>
                        <div class="seo-hint" id="seoDescHint">حدود ۱۴۰–۱۶۰ کاراکتر توصیه می‌شود</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Open Graph Title</label>
                        <input type="text" name="og_title" class="form-control" value="<?= e($article['og_title'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">OG Image (مسیر)</label>
                        <input type="text" name="og_image" id="ogImageField" class="form-control" dir="ltr" value="<?= e($ogImage) ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Open Graph Description</label>
                        <textarea name="og_description" class="form-control" rows="2"><?= e($article['og_description'] ?? '') ?></textarea>
                    </div>
                    <div class="col-md-6">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="robots_index" id="robotsIndex" value="1" <?= !isset($article) || (int) ($article['robots_index'] ?? 1) === 1 ? 'checked' : '' ?>>
                            <label class="form-check-label" for="robotsIndex">Index</label>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="robots_follow" id="robotsFollow" value="1" <?= !isset($article) || (int) ($article['robots_follow'] ?? 1) === 1 ? 'checked' : '' ?>>
                            <label class="form-check-label" for="robotsFollow">Follow</label>
                        </div>
                    </div>
                </div>

                <div class="seo-preview-box mt-3">
                    <div style="font-size:.8rem;color:#6b7280;margin-bottom:6px;">پیش‌نمایش گوگل</div>
                    <div class="seo-title" id="seoPrevTitle">عنوان</div>
                    <div class="seo-url" id="seoPrevUrl"><?= e($appUrl) ?>/articles/…</div>
                    <div class="seo-desc" id="seoPrevDesc">توضیحات متا</div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="admin-card">
                <h2 style="margin:0 0 16px;font-size:1.1rem;color:#031D4F;">انتشار</h2>
                <div class="mb-3">
                    <label class="form-label">وضعیت</label>
                    <select name="status" id="statusField" class="form-select">
                        <option value="draft" <?= $status === 'draft' ? 'selected' : '' ?>>پیش‌نویس (Draft)</option>
                        <option value="published" <?= $status === 'published' ? 'selected' : '' ?>>منتشر شده</option>
                        <option value="scheduled" <?= $status === 'scheduled' ? 'selected' : '' ?>>زمان‌بندی‌شده</option>
                        <option value="archived" <?= $status === 'archived' ? 'selected' : '' ?>>بایگانی</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">تاریخ انتشار</label>
                    <input type="text" name="published_at" class="form-control" data-jalali="datetime" placeholder="تاریخ و ساعت شمسی" value="<?= e($toLocal($article['published_at'] ?? null)) ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">زمان‌بندی انتشار</label>
                    <input type="text" name="scheduled_at" class="form-control" data-jalali="datetime" placeholder="تاریخ و ساعت شمسی" value="<?= e($toLocal($article['scheduled_at'] ?? null)) ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">نویسنده</label>
                    <select name="author_id" class="form-select">
                        <?php foreach ($authors as $au): ?>
                            <option value="<?= (int) $au['id'] ?>" <?= (int) ($article['author_id'] ?? 0) === (int) $au['id'] ? 'selected' : '' ?>>
                                <?= e(trim(($au['first_name'] ?? '') . ' ' . ($au['last_name'] ?? ''))) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">بازبینی پزشکی (اختیاری)</label>
                    <input type="text" name="medical_reviewer" class="form-control" value="<?= e($article['medical_reviewer'] ?? '') ?>">
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <button type="submit" class="btn" style="background:#031D4F;border-color:#031D4F;color:#fff;">ذخیره</button>
                    <?php if ($isEdit): ?>
                        <a class="btn btn-outline-secondary" href="<?= url('/admin/articles/preview/' . $id) ?>" target="_blank" rel="noopener">پیش‌نمایش</a>
                    <?php endif; ?>
                    <a class="btn btn-outline-secondary" href="<?= url('/admin/articles') ?>">بازگشت</a>
                </div>
            </div>

            <div class="admin-card">
                <h2 style="margin:0 0 12px;font-size:1.1rem;color:#031D4F;">تصویر کاور</h2>
                <input type="hidden" name="cover" id="coverField" value="<?= e($cover) ?>">
                <div id="coverPreview" style="margin-bottom:12px;">
                    <?php if ($cover !== ''): ?>
                        <img src="<?= media_url($cover) ?>" alt="" style="width:100%;max-height:180px;object-fit:cover;border-radius:12px;">
                    <?php else: ?>
                        <div style="background:#f4f6fa;border-radius:12px;padding:24px;text-align:center;color:#6b7280;">کاور انتخاب نشده</div>
                    <?php endif; ?>
                </div>
                <div class="mb-2">
                    <label class="form-label">مسیر تصویر / آپلود از کتابخانه</label>
                    <input type="text" id="coverPathInput" class="form-control" dir="ltr" value="<?= e($cover) ?>" placeholder="images/post-1.jpg">
                </div>
                <div class="d-flex gap-2 mb-2">
                    <button type="button" class="btn btn-sm btn-outline-primary" id="applyCoverBtn">اعمال مسیر</button>
                    <button type="button" class="btn btn-sm btn-outline-danger" id="removeCoverBtn">حذف کاور</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="useCoverAsOg">استفاده به‌عنوان OG</button>
                </div>
                <div class="media-picker-grid" id="mediaPicker">
                    <?php foreach ($media as $m): ?>
                        <?php if (empty($m['path'])) continue; ?>
                        <button type="button" data-path="<?= e($m['path']) ?>" title="<?= e($m['original_name'] ?? $m['path']) ?>">
                            <img src="<?= media_url($m['path']) ?>" alt="">
                        </button>
                    <?php endforeach; ?>
                </div>
                <p class="form-text mt-2">برای آپلود جدید به <a href="<?= url('/admin/media') ?>" target="_blank">مدیریت رسانه</a> بروید.</p>
            </div>
        </div>
    </div>
</form>

<script src="https://cdn.jsdelivr.net/npm/quill@2.0.2/dist/quill.js"></script>
<script>
(function () {
    var quill = new Quill('#quillEditor', {
        theme: 'snow',
        modules: {
            toolbar: [
                [{ header: [2, 3, 4, false] }],
                ['bold', 'italic', 'underline'],
                [{ list: 'ordered' }, { list: 'bullet' }],
                ['blockquote', 'link', 'image'],
                [{ align: [] }],
                ['clean']
            ]
        }
    });

    var form = document.getElementById('articleForm');
    form.addEventListener('submit', function () {
        document.getElementById('contentField').value = quill.root.innerHTML;
    });

    var titleField = document.getElementById('titleField');
    var slugField = document.getElementById('slugField');
    var slugTouched = <?= $isEdit ? 'true' : 'false' ?>;
    slugField.addEventListener('input', function () { slugTouched = true; });
    titleField.addEventListener('input', function () {
        if (!slugTouched && !slugField.value) {
            // leave empty so server generates; optional soft preview
        }
        updateSeoPreview();
    });

    var assetsBase = <?= json_encode(rtrim(asset(''), '/') . '/') ?>;
    function mediaUrl(path) {
        if (!path) return '';
        if (/^https?:\/\//i.test(path)) return path;
        path = String(path).replace(/^\/+/, '').replace(/^assets\//, '');
        return assetsBase + path;
    }
    function setCover(path) {
        document.getElementById('coverField').value = path || '';
        document.getElementById('coverPathInput').value = path || '';
        var box = document.getElementById('coverPreview');
        if (path) {
            box.innerHTML = '<img src="' + mediaUrl(path) + '" alt="" style="width:100%;max-height:180px;object-fit:cover;border-radius:12px;">';
        } else {
            box.innerHTML = '<div style="background:#f4f6fa;border-radius:12px;padding:24px;text-align:center;color:#6b7280;">کاور انتخاب نشده</div>';
        }
    }

    document.getElementById('applyCoverBtn').addEventListener('click', function () {
        setCover(document.getElementById('coverPathInput').value.trim());
    });
    document.getElementById('removeCoverBtn').addEventListener('click', function () { setCover(''); });
    document.getElementById('useCoverAsOg').addEventListener('click', function () {
        document.getElementById('ogImageField').value = document.getElementById('coverField').value;
    });
    document.getElementById('mediaPicker').addEventListener('click', function (e) {
        var btn = e.target.closest('button[data-path]');
        if (!btn) return;
        setCover(btn.getAttribute('data-path'));
    });

    var seoTitle = document.getElementById('seoTitle');
    var seoDesc = document.getElementById('seoDesc');
    var baseUrl = <?= json_encode($appUrl . '/articles/') ?>;

    function updateSeoPreview() {
        var t = seoTitle.value.trim() || titleField.value.trim() || 'عنوان';
        var d = seoDesc.value.trim() || 'توضیحات متا';
        var s = slugField.value.trim() || '…';
        document.getElementById('seoPrevTitle').textContent = t;
        document.getElementById('seoPrevDesc').textContent = d;
        document.getElementById('seoPrevUrl').textContent = baseUrl + s;

        var th = document.getElementById('seoTitleHint');
        var dh = document.getElementById('seoDescHint');
        var tl = seoTitle.value.length;
        var dl = seoDesc.value.length;
        th.textContent = tl + ' کاراکتر — حدود ۵۰–۶۰ توصیه می‌شود';
        th.className = 'seo-hint' + (tl > 0 && (tl < 30 || tl > 65) ? ' warn' : '');
        dh.textContent = dl + ' کاراکتر — حدود ۱۴۰–۱۶۰ توصیه می‌شود';
        dh.className = 'seo-hint' + (dl > 0 && (dl < 70 || dl > 170) ? ' warn' : '');
    }
    seoTitle.addEventListener('input', updateSeoPreview);
    seoDesc.addEventListener('input', updateSeoPreview);
    slugField.addEventListener('input', updateSeoPreview);
    updateSeoPreview();
})();
</script>
