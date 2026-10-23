<?php
/** Article card partial — expects $post */
$href = url('/articles/' . ($post['slug'] ?? ''));
$date = !empty($post['published_at']) ? to_jalali($post['published_at'], 'Y/m/d') : '';
$reading = (int) ($post['reading_time'] ?? 0);
if ($reading <= 0 && !empty($post['content'])) {
    $reading = \Sarva\Services\ArticleService::estimateReadingTime((string) $post['content']);
}
?>
<article class="article-card wow fadeInUp">
    <a href="<?= e($href) ?>" class="article-card-media" data-cursor-text="مطالعه">
        <figure class="image-anime">
            <img src="<?= media_url($post['cover'] ?: 'images/post-1.jpg') ?>" alt="<?= e($post['title'] ?? '') ?>" loading="lazy">
        </figure>
    </a>
    <div class="article-card-body">
        <div class="article-card-meta">
            <?php if (!empty($post['category_name'])): ?>
                <span class="article-card-cat"><?= e($post['category_name']) ?></span>
            <?php endif; ?>
            <?php if ($date): ?><span class="article-card-date"><?= e($date) ?></span><?php endif; ?>
            <?php if ($reading): ?><span class="article-card-time"><?= e((string) $reading) ?> دقیقه مطالعه</span><?php endif; ?>
        </div>
        <h3 class="article-card-title"><a href="<?= e($href) ?>"><?= e($post['title'] ?? '') ?></a></h3>
        <?php if (!empty($post['excerpt'])): ?>
            <p class="article-card-excerpt"><?= e($post['excerpt']) ?></p>
        <?php endif; ?>
        <?php if (!empty($post['author_name'])): ?>
            <p class="article-card-author">نویسنده: <?= e($post['author_name']) ?></p>
        <?php endif; ?>
        <a href="<?= e($href) ?>" class="readmore-btn">مطالعه مقاله</a>
    </div>
</article>
