<?php
/** @var array $t */
$name = (string) ($t['patient_name'] ?? 'بیمار');
$content = (string) ($t['content'] ?? '');
$rating = max(0, min(5, (int) ($t['rating'] ?? 5)));
$avatar = $t['avatar'] ?? '';
$initial = mb_substr(trim($name) !== '' ? $name : 'ب', 0, 1);
?>
<article class="tm-card" dir="rtl">
    <div class="tm-card-rating" aria-label="<?= e((string) $rating) ?> از ۵">
        <?php for ($s = 1; $s <= 5; $s++): ?>
            <i class="fa-solid fa-star<?= $s > $rating ? ' is-empty' : '' ?>" aria-hidden="true"></i>
        <?php endfor; ?>
    </div>
    <p class="tm-card-text">«<?= e($content) ?>»</p>
    <footer class="tm-card-author">
        <?php if ($avatar): ?>
            <img class="tm-card-avatar" src="<?= media_url($avatar) ?>" alt="" width="40" height="40" loading="lazy">
        <?php else: ?>
            <span class="tm-card-avatar tm-card-avatar--fallback" aria-hidden="true"><?= e($initial) ?></span>
        <?php endif; ?>
        <div class="tm-card-meta">
            <strong><?= e($name) ?></strong>
            <span>مراجع کلینیک سروا</span>
        </div>
    </footer>
</article>
