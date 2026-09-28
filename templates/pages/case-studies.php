<?php partial('page-header', ['pageTitle' => $title ?? 'نمونه کارها']); ?>
<div class="page-case-study">
    <div class="container">
        <div class="row section-row">
            <div class="col-lg-12">
                <div class="section-title section-title-center">
                    <span class="section-sub-title wow fadeInUp">نتایج درمان</span>
                    <h2 class="text-anime-style-3" data-cursor="-opaque">نمونه کارهای <span>کلینیک سروا</span></h2>
                </div>
            </div>
        </div>
        <div class="row">
            <?php foreach (($cases ?? []) as $i => $case): ?>
            <?php $delay = ($i % 3) * 0.2; ?>
            <div class="col-xl-4 col-md-6">
                <div class="case-study-item wow fadeInUp"<?= $delay ? ' data-wow-delay="' . e((string) $delay) . 's"' : '' ?>>
                    <div class="case-study-item-image-box">
                        <div class="case-study-item-image">
                            <a href="<?= url('/case-studies/' . $case['slug']) ?>" data-cursor-text="مشاهده">
                                <figure class="image-anime">
                                    <img src="<?= media_url($case['after_image'] ?: ($case['before_image'] ?: 'images/case-study-image-1.jpg')) ?>" alt="<?= e($case['title']) ?>">
                                </figure>
                            </a>
                        </div>
                        <div class="case-study-item-btn">
                            <a href="<?= url('/case-studies/' . $case['slug']) ?>">
                                <img src="<?= asset('images/arrow-white.svg') ?>" alt="">
                            </a>
                        </div>
                    </div>
                    <div class="case-study-item-contant">
                        <h2><a href="<?= url('/case-studies/' . $case['slug']) ?>"><?= e($case['title']) ?></a></h2>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php if (empty($cases)): ?>
            <div class="col-12"><p class="text-center py-5">هنوز نمونه کاری ثبت نشده است.</p></div>
            <?php endif; ?>
        </div>
    </div>
</div>
