<?php partial('page-header', ['pageTitle' => $title ?? 'مقالات']); ?>
<div class="page-articles">
    <div class="container">
        <div class="row section-row">
            <div class="col-lg-12">
                <div class="section-title section-title-center">
                    <span class="section-sub-title wow fadeInUp">دانش و آگاهی</span>
                    <h2 class="text-anime-style-3" data-cursor="-opaque">مقالات <span>کلینیک سروا</span></h2>
                    <p class="wow fadeInUp" data-wow-delay="0.2s">مطالب آموزشی و کاربردی درباره سلامت دهان و دندان</p>
                </div>
            </div>
        </div>

        <?php if (!empty($categories)): ?>
        <div class="article-cats wow fadeInUp">
            <a href="<?= url('/articles') ?>" class="article-cat-chip<?= empty($category) ? ' active' : '' ?>">همه</a>
            <?php foreach ($categories as $cat): ?>
                <a href="<?= url('/articles?category=' . urlencode($cat['slug'])) ?>"
                   class="article-cat-chip<?= (!empty($category) && (int)$category['id'] === (int)$cat['id']) ? ' active' : '' ?>">
                    <?= e($cat['name']) ?>
                    <?php if (!empty($cat['post_count'])): ?><small>(<?= (int)$cat['post_count'] ?>)</small><?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="row">
            <?php foreach (($posts ?? []) as $post): ?>
            <div class="col-lg-4 col-md-6">
                <?php partial('article-card', ['post' => $post]); ?>
            </div>
            <?php endforeach; ?>
            <?php if (empty($posts)): ?>
            <div class="col-12">
                <p class="text-center py-5">هنوز مقاله‌ای در این بخش منتشر نشده است.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
