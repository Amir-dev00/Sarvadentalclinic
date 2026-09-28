<?php partial('page-header', ['pageTitle' => $title ?? 'گالری تصاویر']); ?>
<div class="page-gallery">
    <div class="container">
        <div class="row section-row">
            <div class="col-lg-12">
                <div class="section-title section-title-center">
                    <span class="section-sub-title wow fadeInUp">فضای کلینیک</span>
                    <h2 class="text-anime-style-3" data-cursor="-opaque">گالری تصاویر <span>سروا</span></h2>
                </div>
            </div>
        </div>
        <div class="row gallery-items page-gallery-box">
            <?php foreach (($items ?? []) as $i => $item): ?>
            <?php
                $src = $item['media_path'] ?? '';
                $full = (str_starts_with($src, 'http://') || str_starts_with($src, 'https://')) ? $src : media_url($src ?: 'images/gallery-1.jpg');
                $delay = ($i % 3) * 0.2;
            ?>
            <div class="col-lg-4 col-6">
                <div class="photo-gallery wow fadeInUp"<?= $delay ? ' data-wow-delay="' . e((string) $delay) . 's"' : '' ?>>
                    <a href="<?= e($full) ?>" data-cursor-text="مشاهده">
                        <figure class="image-anime">
                            <img src="<?= e($full) ?>" alt="<?= e($item['title'] ?? 'گالری') ?>">
                        </figure>
                    </a>
                    <?php if (!empty($item['title'])): ?>
                    <p class="mt-2 text-center"><?= e($item['title']) ?></p>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
            <?php if (empty($items)): ?>
            <div class="col-12"><p class="text-center py-5">هنوز تصویری در گالری ثبت نشده است.</p></div>
            <?php endif; ?>
        </div>
        <div class="row mt-4">
            <div class="col-12 text-center wow fadeInUp">
                <a href="<?= url('/gallery/videos') ?>" class="btn-default">مشاهده گالری ویدیو</a>
            </div>
        </div>
    </div>
</div>
