<?php
$fullName = trim(($doctor['first_name'] ?? '') . ' ' . ($doctor['last_name'] ?? ''));
partial('page-header', ['pageTitle' => $title ?? $fullName, 'crumb' => $fullName]);
?>
<div class="page-team-single">
    <div class="container">
        <div class="row">
            <div class="col-lg-12">
                <div class="page-team-single-box">
                    <div class="team-member-about">
                        <div class="team-single-image">
                            <figure class="image-anime reveal">
                                <img src="<?= media_url($doctor['photo'] ?: 'images/team-image-1.jpg') ?>" alt="<?= e($fullName) ?>">
                            </figure>
                        </div>
                        <div class="team-about-content">
                            <div class="section-title">
                                <span class="section-sub-title wow fadeInUp"><?= e($doctor['specialty'] ?? 'دندانپزشک') ?></span>
                                <h2 class="text-anime-style-3" data-cursor="-opaque"><?= e($fullName) ?></h2>
                                <p class="wow fadeInUp" data-wow-delay="0.2s"><?= nl2br(e($doctor['biography'] ?? 'عضو تیم تخصصی کلینیک دندانپزشکی سروا.')) ?></p>
                            </div>
                            <?php if (!empty($doctor['experience'])): ?>
                            <div class="team-contact-item wow fadeInUp" data-wow-delay="0.3s">
                                <div class="team-contact-item-content">
                                    <h3>سوابق و تجربه</h3>
                                    <p><?= nl2br(e($doctor['experience'])) ?></p>
                                </div>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($doctor['education'])): ?>
                            <div class="team-contact-item wow fadeInUp" data-wow-delay="0.4s">
                                <div class="team-contact-item-content">
                                    <h3>تحصیلات</h3>
                                    <p><?= nl2br(e($doctor['education'])) ?></p>
                                </div>
                            </div>
                            <?php endif; ?>
                            <div class="wow fadeInUp" data-wow-delay="0.5s" style="margin-top:24px;">
                                <a href="<?= booking_url() ?>" class="btn-default">رزرو نوبت با این پزشک</a>
                                <a href="<?= url('/') ?>" class="btn-default" style="margin-right:12px;">بازگشت به صفحه اصلی</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
