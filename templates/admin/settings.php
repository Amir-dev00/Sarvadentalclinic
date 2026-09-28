<?php
$items = $items ?? [];
$groups = [];
foreach ($items as $item) {
    $g = $item['group_name'] ?? 'عمومی';
    $groups[$g][] = $item;
}
$imageKeys = ['logo_path', 'favicon_path', 'og_default_image'];
$imageLabels = [
    'logo_path' => 'لوگوی سایت (هدر)',
    'favicon_path' => 'فاوآیکون / آیکون پیش‌بارگذاری',
    'og_default_image' => 'تصویر پیش‌فرض اشتراک‌گذاری (OG)',
];
?>
<div class="admin-card">
    <form method="post" action="<?= url('/admin/settings') ?>" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <?php if (empty($items)): ?>
            <p style="margin:0;color:#6b7280;">تنظیماتی یافت نشد.</p>
        <?php else: ?>
            <?php foreach ($groups as $group => $rows): ?>
                <h2 style="margin:0 0 12px;font-size:1.05rem;color:#031D4F;"><?= e((string) $group) ?></h2>
                <div class="row g-3 mb-4">
                    <?php foreach ($rows as $item): ?>
                        <?php
                            $key = (string) ($item['key'] ?? '');
                            $isImage = in_array($key, $imageKeys, true);
                        ?>
                        <div class="col-md-<?= $isImage ? '12' : '6' ?>">
                            <label class="form-label">
                                <?php if ($isImage): ?>
                                    <?= e($imageLabels[$key] ?? $key) ?>
                                    <code class="ms-1" style="font-size:.75rem;"><?= e($key) ?></code>
                                <?php else: ?>
                                    <code><?= e($key) ?></code>
                                <?php endif; ?>
                            </label>
                            <input
                                type="text"
                                name="settings[<?= e($key) ?>]"
                                class="form-control"
                                value="<?= e($item['value'] ?? '') ?>"
                                <?= $isImage ? 'placeholder="images/... یا uploads/..."' : '' ?>
                            >
                            <?php if ($isImage): ?>
                                <div class="row g-2 mt-1 align-items-center">
                                    <div class="col-md-6">
                                        <input type="file" name="setting_file_<?= e($key) ?>" class="form-control" accept="image/jpeg,image/png,image/webp,image/gif,image/svg+xml">
                                    </div>
                                    <div class="col-md-6">
                                        <?php if (!empty($item['value'])): ?>
                                            <img src="<?= media_url($item['value']) ?>" alt="" style="max-height:56px;max-width:160px;object-fit:contain;border-radius:8px;background:#f3f4f6;padding:4px;">
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
            <button type="submit" class="btn btn-primary" style="background:#031D4F;border-color:#031D4F;">ذخیره تنظیمات</button>
        <?php endif; ?>
    </form>
</div>
