<?php partial('page-header', ['pageTitle' => $title ?? 'گالری ویدیو']); ?>
<div class="page-video-gallery">
    <div class="container">
        <div class="row section-row">
            <div class="col-lg-12">
                <div class="section-title section-title-center">
                    <span class="section-sub-title wow fadeInUp">ویدیوها</span>
                    <h2 class="text-anime-style-3" data-cursor="-opaque">گالری ویدیوی <span>کلینیک سروا</span></h2>
                </div>
            </div>
        </div>
        <div class="row">
            <?php foreach (($items ?? []) as $i => $item): ?>
            <?php
                $media = $item['media_path'] ?? '';
                $thumb = $item['thumb_path'] ?? '';
                $isExternal = str_starts_with($media, 'http://') || str_starts_with($media, 'https://');
                $href = $isExternal ? $media : asset($media);
                $thumbSrc = $thumb
                    ? ((str_starts_with($thumb, 'http://') || str_starts_with($thumb, 'https://')) ? $thumb : asset($thumb))
                    : asset('images/gallery-3.jpg');
                $delay = ($i % 3) * 0.2;
            ?>
            <div class="col-lg-4 col-md-6">
                <div class="video-gallery-image wow fadeInUp"<?= $delay ? ' data-wow-delay="' . e((string) $delay) . 's"' : '' ?>>
                    <a href="<?= e($href) ?>" class="<?= $isExternal ? 'popup-video' : '' ?>" data-cursor-text="پخش"<?= !$isExternal ? ' target="_blank"' : '' ?>>
                        <figure>
                            <img src="<?= e($thumbSrc) ?>" alt="<?= e($item['title'] ?? 'ویدیو') ?>">
                        </figure>
                    </a>
                    <?php if (!empty($item['title'])): ?>
                    <h3 class="mt-2"><?= e($item['title']) ?></h3>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
            <?php if (empty($items)): ?>
            <div class="col-12"><p class="text-center py-5">هنوز ویدیویی ثبت نشده است.</p></div>
            <?php endif; ?>
        </div>
        <div class="row mt-4">
            <div class="col-12 text-center wow fadeInUp">
                <a href="<?= url('/gallery') ?>" class="btn-default">گالری تصاویر</a>
            </div>
        </div>
    </div>
</div>
