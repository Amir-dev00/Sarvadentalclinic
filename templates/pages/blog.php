<?php partial('page-header', ['pageTitle' => $title ?? 'وبلاگ']); ?>
<div class="page-blog">
    <div class="container">
        <div class="row section-row">
            <div class="col-lg-12">
                <div class="section-title section-title-center">
                    <span class="section-sub-title wow fadeInUp">مقالات</span>
                    <h2 class="text-anime-style-3" data-cursor="-opaque">وبلاگ سلامت دهان و <span>دندان</span></h2>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-lg-12">
                <div class="post-item-list">
                    <?php foreach (($posts ?? []) as $i => $post): ?>
                    <?php
                        $delay = ($i % 3) * 0.2;
                        $dateLabel = !empty($post['published_at']) ? to_jalali($post['published_at'], 'Y/m/d') : '';
                    ?>
                    <div class="post-item wow fadeInUp"<?= $delay ? ' data-wow-delay="' . e((string) $delay) . 's"' : '' ?>>
                        <div class="post-featured-image">
                            <a href="<?= url('/blog/' . $post['slug']) ?>" data-cursor-text="مطالعه">
                                <figure class="image-anime">
                                    <img src="<?= media_url($post['cover'] ?: 'images/post-1.jpg') ?>" alt="<?= e($post['title'] ?? '') ?>">
                                </figure>
                            </a>
                        </div>
                        <div class="post-item-body">
                            <div class="post-item-content">
                                <?php if ($dateLabel): ?>
                                <p class="mb-1"><small><?= e($dateLabel) ?></small></p>
                                <?php endif; ?>
                                <h2><a href="<?= url('/blog/' . $post['slug']) ?>"><?= e($post['title'] ?? '') ?></a></h2>
                                <p><?= e($post['excerpt'] ?? '') ?></p>
                            </div>
                            <div class="post-item-btn">
                                <a href="<?= url('/blog/' . $post['slug']) ?>" class="readmore-btn">ادامه مطلب</a>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php if (empty($posts)): ?>
                    <p class="text-center py-5">هنوز مطلبی منتشر نشده است.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
