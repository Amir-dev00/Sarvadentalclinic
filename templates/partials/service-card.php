<?php
/**
 * Shared service card — matches Smilico structure.
 * Expects: $service (array), optional $index (0-based for icon/number fallback)
 */
$index = (int) ($index ?? 0);
$num = max(1, (int) ($service['sort_order'] ?? ($index + 1)));
$iconNum = (($num - 1) % 6) + 1;
$icon = !empty($service['icon'])
    ? $service['icon']
    : 'images/icon-service-item-' . $iconNum . '.svg';
$imgNum = (($num - 1) % 6) + 1;
$image = !empty($service['image'])
    ? $service['image']
    : (is_file(dirname(__DIR__, 2) . '/assets/images/service-image-' . $imgNum . '.jpg')
        ? 'images/service-image-' . $imgNum . '.jpg'
        : null);
$delay = $index > 0 ? '0.' . min($index * 2, 8) . 's' : null;
?>
<div class="service-item wow fadeInUp"<?= $delay ? ' data-wow-delay="' . e($delay) . '"' : '' ?>>
    <div class="service-item-header">
        <div class="service-item-title">
            <p><?= e(str_pad((string) $num, 2, '0', STR_PAD_LEFT)) ?></p>
            <h2><a href="<?= url('/services/' . $service['slug']) ?>"><?= e($service['name']) ?></a></h2>
        </div>
        <div class="icon-box">
            <img src="<?= media_url($icon) ?>" alt="">
        </div>
    </div>
    <div class="service-item-content">
        <p><?= e($service['short_description']) ?></p>
    </div>
    <?php if ($image): ?>
    <div class="service-image">
        <a href="<?= url('/services/' . $service['slug']) ?>" data-cursor-text="مشاهده">
            <figure class="image-anime">
                <img src="<?= media_url($image) ?>" alt="<?= e($service['name']) ?>">
            </figure>
        </a>
    </div>
    <?php endif; ?>
</div>
