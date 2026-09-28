<?php
$article = $article ?? [];
$related = $related ?? [];
$isPreview = !empty($isPreview);
$date = !empty($article['published_at']) ? to_jalali($article['published_at'], 'Y/m/d H:i') : '';
$reading = (int) ($article['reading_time'] ?? 0);
if ($reading <= 0) {
    $reading = \Sarva\Services\ArticleService::estimateReadingTime((string) ($article['content'] ?? ''));
}
$cover = $article['cover'] ?: 'images/post-1.jpg';
partial('page-header', [
    'pageTitle' => $article['title'] ?? 'مقاله',
    'crumb' => $article['title'] ?? 'مقاله',
    'parentCrumb' => ['label' => 'مقالات', 'url' => '/articles'],
]);
?>
<?php if ($isPreview): ?>
<div class="container pt-3">
    <div class="auth-alert" style="background:#fff7e6;color:#8a5a00;">حالت پیش‌نمایش ادمین — این صفحه ممکن است هنوز منتشر نشده باشد.</div>
</div>
<?php endif; ?>

<div class="page-article-single">
    <div class="container">
        <div class="row">
            <div class="col-lg-8">
                <article class="article-entry">
                    <figure class="article-cover image-anime wow fadeInUp">
                        <img src="<?= media_url($cover) ?>" alt="<?= e($article['title'] ?? '') ?>">
                    </figure>

                    <div class="article-entry-meta wow fadeInUp" data-wow-delay="0.1s">
                        <?php if (!empty($article['category_name'])): ?>
                            <a href="<?= url('/articles?category=' . urlencode((string)$article['category_slug'])) ?>" class="article-card-cat"><?= e($article['category_name']) ?></a>
                        <?php endif; ?>
                        <?php if ($date): ?><span><?= e($date) ?></span><?php endif; ?>
                        <?php if ($reading): ?><span><?= e((string)$reading) ?> دقیقه مطالعه</span><?php endif; ?>
                        <?php if (!empty($article['author_name'])): ?><span>نویسنده: <?= e($article['author_name']) ?></span><?php endif; ?>
                        <?php if (!empty($article['medical_reviewer'])): ?><span>بازبینی پزشکی: <?= e($article['medical_reviewer']) ?></span><?php endif; ?>
                    </div>

                    <div class="section-title">
                        <h1 class="text-anime-style-3" data-cursor="-opaque"><?= e($article['title'] ?? '') ?></h1>
                    </div>

                    <?php if (!empty($article['excerpt'])): ?>
                        <p class="article-lead wow fadeInUp"><?= e($article['excerpt']) ?></p>
                    <?php endif; ?>

                    <div class="article-content wow fadeInUp" data-wow-delay="0.2s">
                        <?= $article['content'] ?? '' ?>
                    </div>

                    <div class="article-share wow fadeInUp">
                        <span>اشتراک‌گذاری:</span>
                        <?php
                        $shareUrl = urlencode(url('/articles/' . ($article['slug'] ?? '')));
                        $shareTitle = urlencode((string) ($article['title'] ?? ''));
                        ?>
                        <a href="https://twitter.com/intent/tweet?url=<?= $shareUrl ?>&text=<?= $shareTitle ?>" target="_blank" rel="noopener">X</a>
                        <a href="https://www.linkedin.com/sharing/share-offsite/?url=<?= $shareUrl ?>" target="_blank" rel="noopener">LinkedIn</a>
                        <a href="https://t.me/share/url?url=<?= $shareUrl ?>&text=<?= $shareTitle ?>" target="_blank" rel="noopener">Telegram</a>
                    </div>

                    <div class="article-cta wow fadeInUp">
                        <h3>آماده رزرو نوبت هستید؟</h3>
                        <p>برای دریافت مشاوره تخصصی در کلینیک سروا نوبت آنلاین بگیرید.</p>
                        <a href="<?= booking_url() ?>" class="btn-default">رزرو نوبت</a>
                    </div>
                </article>
            </div>

            <div class="col-lg-4">
                <aside class="article-sidebar wow fadeInUp" data-wow-delay="0.15s">
                    <div class="admin-card">
                        <h3>مقالات مرتبط</h3>
                        <?php if (empty($related)): ?>
                            <p>مقاله مرتبطی یافت نشد.</p>
                        <?php else: foreach ($related as $rel): ?>
                            <a class="related-article-link" href="<?= url('/articles/' . $rel['slug']) ?>">
                                <strong><?= e($rel['title']) ?></strong>
                                <?php if (!empty($rel['category_name'])): ?><small><?= e($rel['category_name']) ?></small><?php endif; ?>
                            </a>
                        <?php endforeach; endif; ?>
                        <a href="<?= url('/articles') ?>" class="readmore-btn mt-3 d-inline-block">همه مقالات</a>
                    </div>
                </aside>
            </div>
        </div>
    </div>
</div>

<?php
// JSON-LD BlogPosting
$publisher = setting('clinic_name', 'کلینیک دندانپزشکی سروا');
$jsonLd = [
    '@context' => 'https://schema.org',
    '@type' => 'BlogPosting',
    'headline' => $article['title'] ?? '',
    'description' => $article['excerpt'] ?? ($article['seo_description'] ?? ''),
    'image' => media_url($cover),
    'datePublished' => $article['published_at'] ?? $article['created_at'] ?? null,
    'dateModified' => $article['updated_at'] ?? null,
    'author' => [
        '@type' => 'Person',
        'name' => $article['author_name'] ?? $publisher,
    ],
    'publisher' => [
        '@type' => 'Organization',
        'name' => $publisher,
        'logo' => [
            '@type' => 'ImageObject',
            'url' => asset('images/logo.svg'),
        ],
    ],
    'mainEntityOfPage' => url('/articles/' . ($article['slug'] ?? '')),
    'inLanguage' => 'fa-IR',
];
?>
<script type="application/ld+json"><?= json_encode($jsonLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
