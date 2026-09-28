<?php
$postTitle = $post['title'] ?? ($title ?? 'مطلب');
$dateLabel = !empty($post['published_at']) ? to_jalali($post['published_at']) : '';
partial('page-header', ['pageTitle' => $postTitle]);
?>
<div class="page-single-post">
    <div class="container">
        <div class="row">
            <div class="col-lg-12">
                <?php if (!empty($post['cover'])): ?>
                <div class="post-image wow fadeInUp">
                    <figure class="image-anime reveal">
                        <img src="<?= media_url($post['cover']) ?>" alt="<?= e($postTitle) ?>">
                    </figure>
                </div>
                <?php endif; ?>

                <div class="post-content">
                    <?php if ($dateLabel): ?>
                    <div class="post-single-meta wow fadeInUp mb-3">
                        <ol class="breadcrumb">
                            <li><i class="fa-regular fa-clock"></i> <?= e($dateLabel) ?></li>
                        </ol>
                    </div>
                    <?php endif; ?>

                    <div class="section-title">
                        <h2 class="text-anime-style-3" data-cursor="-opaque"><?= e($postTitle) ?></h2>
                        <?php if (!empty($post['excerpt'])): ?>
                        <p class="wow fadeInUp" data-wow-delay="0.2s"><?= e($post['excerpt']) ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="post-entry wow fadeInUp" data-wow-delay="0.3s">
                        <?= nl2br(e($post['content'] ?? '')) ?>
                    </div>

                    <div class="post-tag-links wow fadeInUp" data-wow-delay="0.4s" style="margin-top:32px;">
                        <a href="<?= url('/blog') ?>" class="btn-default">بازگشت به وبلاگ</a>
                        <a href="<?= booking_url() ?>" class="btn-default" style="margin-right:12px;">رزرو نوبت</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
