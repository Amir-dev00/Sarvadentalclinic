<?php partial('page-header', ['pageTitle' => $title ?? 'تیم پزشکی']); ?>
<div class="page-team">
    <div class="container">
        <div class="row section-row">
            <div class="col-lg-12">
                <div class="section-title section-title-center">
                    <span class="section-sub-title wow fadeInUp">کلینیک سروا</span>
                    <h2 class="text-anime-style-3" data-cursor="-opaque">پزشکان و متخصصان <span>ما</span></h2>
                </div>
            </div>
        </div>
        <div class="row">
            <?php foreach (($doctors ?? []) as $i => $doctor): ?>
            <?php
                $fullName = trim(($doctor['first_name'] ?? '') . ' ' . ($doctor['last_name'] ?? ''));
                $delay = ($i % 3) * 0.2;
            ?>
            <div class="col-xl-4 col-md-6">
                <div class="team-item wow fadeInUp"<?= $delay ? ' data-wow-delay="' . e((string) $delay) . 's"' : '' ?>>
                    <div class="team-item-image">
                        <a href="<?= url('/team/' . $doctor['slug']) ?>" data-cursor-text="مشاهده">
                            <figure>
                                <img src="<?= media_url($doctor['photo'] ?: 'images/team-image-1.jpg') ?>" alt="<?= e($fullName) ?>">
                            </figure>
                        </a>
                    </div>
                    <div class="team-item-content">
                        <h2><a href="<?= url('/team/' . $doctor['slug']) ?>"><?= e($fullName) ?></a></h2>
                        <p><?= e($doctor['specialty'] ?? '') ?></p>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php if (empty($doctors)): ?>
            <div class="col-12"><p class="text-center py-5">هنوز پزشکی ثبت نشده است.</p></div>
            <?php endif; ?>
        </div>
    </div>
</div>
