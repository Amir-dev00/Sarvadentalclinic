<?php
partial('page-header', ['pageTitle' => $title ?? 'نظرات بیماران']);
$flashOk = flash('success');
$flashErr = flash('error');
$defaultName = (string) ($defaultName ?? '');
?>
<div class="page-testimonials">
    <div class="container">
        <div class="row section-row">
            <div class="col-lg-12">
                <div class="section-title section-title-center">
                    <span class="section-sub-title wow fadeInUp">تجربه بیماران</span>
                    <h2 class="text-anime-style-3" data-cursor="-opaque">نظرات مراجعان <span>سروا</span></h2>
                </div>
            </div>
        </div>
        <div class="row">
            <?php foreach (($testimonials ?? []) as $i => $t): ?>
            <?php
                $rating = max(0, min(5, (int) ($t['rating'] ?? 5)));
                $delay = ($i % 3) * 0.2;
            ?>
            <div class="col-xl-4 col-md-6">
                <div class="testimonial-item wow fadeInUp"<?= $delay ? ' data-wow-delay="' . e((string) $delay) . 's"' : '' ?>>
                    <div class="testimonial-item-header">
                        <div class="testimonial-item-author">
                            <?php if (!empty($t['avatar'])): ?>
                            <div class="testimonial-item-author-image">
                                <figure class="image-anime">
                                    <img src="<?= media_url($t['avatar']) ?>" alt="<?= e($t['patient_name'] ?? '') ?>">
                                </figure>
                            </div>
                            <?php endif; ?>
                            <div class="testimonial-item-author-content">
                                <h2><?= e($t['patient_name'] ?? 'بیمار') ?></h2>
                                <p>مراجع کلینیک سروا</p>
                            </div>
                        </div>
                        <div class="testimonial-item-content">
                            <p>«<?= e($t['content'] ?? '') ?>»</p>
                        </div>
                    </div>
                    <div class="testimonial-item-rating">
                        <?php for ($s = 1; $s <= 5; $s++): ?>
                        <i class="fa-solid fa-star<?= $s > $rating ? ' text-muted' : '' ?>"></i>
                        <?php endfor; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php if (empty($testimonials)): ?>
            <div class="col-12"><p class="text-center py-5">هنوز نظری ثبت نشده است.</p></div>
            <?php endif; ?>
        </div>

        <div class="row mt-5" id="leave-review">
            <div class="col-lg-8 offset-lg-2">
                <div class="testimonial-submit-box wow fadeInUp">
                    <div class="section-title section-title-center mb-4">
                        <span class="section-sub-title">ثبت نظر</span>
                        <h2 class="text-anime-style-3" data-cursor="-opaque">نظر خود را بنویسید</h2>
                        <p>تجربه خود از کلینیک سروا را با دیگران به اشتراک بگذارید. پس از بررسی منتشر می‌شود.</p>
                    </div>
                    <?php if ($flashOk): ?><div class="auth-alert success"><?= e($flashOk) ?></div><?php endif; ?>
                    <?php if ($flashErr): ?><div class="auth-alert error"><?= e($flashErr) ?></div><?php endif; ?>
                    <form class="testimonial-submit-form contact-form" action="<?= url('/testimonials') ?>" method="POST">
                        <?= csrf_field() ?>
                        <div class="hp-field" aria-hidden="true">
                            <label for="website">وب‌سایت</label>
                            <input type="text" name="website" id="website" tabindex="-1" autocomplete="off">
                        </div>
                        <div class="row">
                            <div class="form-group col-md-6 mb-3">
                                <input type="text" name="patient_name" class="form-control" placeholder="نام شما *" maxlength="150" value="<?= old('patient_name', $defaultName) ?>" required>
                            </div>
                            <div class="form-group col-md-6 mb-3">
                                <select name="rating" class="form-control" required>
                                    <?php
                                    $selRating = (int) (($_SESSION['_old']['rating'] ?? '5') ?: '5');
                                    for ($r = 5; $r >= 1; $r--):
                                    ?>
                                    <option value="<?= $r ?>"<?= $selRating === $r ? ' selected' : '' ?>><?= $r ?> از 5</option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="form-group col-md-12 mb-3">
                                <textarea name="content" class="form-control" rows="5" maxlength="1000" placeholder="نظر شما...*" required><?= old('content') ?></textarea>
                            </div>
                            <div class="col-md-12 text-center">
                                <button type="submit" class="btn-default">ارسال نظر</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="row mt-5">
            <div class="col-12 text-center wow fadeInUp">
                <a href="<?= booking_url() ?>" class="btn-default">رزرو نوبت</a>
            </div>
        </div>
    </div>
</div>
