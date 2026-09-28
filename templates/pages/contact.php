<?php partial('page-header', ['pageTitle' => $title ?? 'تماس با ما']); ?>
<?php
$phone = (string) setting('phone', '');
$mobile = (string) setting('mobile', '');
$email = (string) setting('email', '');
$address = (string) setting('address', '');
$flashOk = flash('success');
$flashErr = flash('error');
?>
<div class="page-contact-us">
    <div class="container">
        <div class="row">
            <div class="col-lg-6">
                <div class="contact-us-content-box">
                    <div class="contact-us-content-header">
                        <div class="section-title">
                            <span class="section-sub-title wow fadeInUp">ارتباط با سروا</span>
                            <h2 class="wow text-anime-style-3" data-cursor="-opaque">با تیم کلینیک در تماس باشید</h2>
                            <p class="wow fadeInUp" data-wow-delay="0.2s">برای پرسش درباره درمان‌ها، نوبت‌دهی یا راهنمایی، پیام بگذارید یا با ما تماس بگیرید.</p>
                        </div>
                        <div class="contact-us-content-list wow fadeInUp" data-wow-delay="0.4s">
                            <ul>
                                <li>پاسخگویی سریع به درخواست‌های بیماران</li>
                                <li>هماهنگی نوبت حضوری و آنلاین</li>
                                <li>پشتیبانی محترمانه و حرفه‌ای</li>
                            </ul>
                        </div>
                    </div>
                    <div class="contact-us-item-list wow fadeInUp" data-wow-delay="0.6s">
                        <?php if ($phone !== ''): ?>
                        <div class="contact-us-item">
                            <div class="icon-box">
                                <img src="<?= asset('images/icon-phone-white.svg') ?>" alt="">
                            </div>
                            <div class="contact-us-item-content">
                                <p>تلفن</p>
                                <h3><a href="tel:<?= e($phone) ?>"><?= e($phone) ?></a></h3>
                            </div>
                        </div>
                        <?php endif; ?>
                        <?php if ($mobile !== ''): ?>
                        <div class="contact-us-item">
                            <div class="icon-box">
                                <img src="<?= asset('images/icon-phone-white.svg') ?>" alt="">
                            </div>
                            <div class="contact-us-item-content">
                                <p>موبایل</p>
                                <h3><a href="tel:<?= e($mobile) ?>"><?= e($mobile) ?></a></h3>
                            </div>
                        </div>
                        <?php endif; ?>
                        <?php if ($email !== ''): ?>
                        <div class="contact-us-item">
                            <div class="icon-box">
                                <img src="<?= asset('images/icon-mail-white.svg') ?>" alt="">
                            </div>
                            <div class="contact-us-item-content">
                                <p>ایمیل</p>
                                <h3><a href="mailto:<?= e($email) ?>"><?= e($email) ?></a></h3>
                            </div>
                        </div>
                        <?php endif; ?>
                        <?php if ($address !== ''): ?>
                        <div class="contact-us-item">
                            <div class="icon-box">
                                <img src="<?= asset('images/icon-mail-white.svg') ?>" alt="">
                            </div>
                            <div class="contact-us-item-content">
                                <p>آدرس</p>
                                <h3><?= e($address) ?></h3>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="contact-us-form">
                    <div class="contact-form-title">
                        <h2 class="text-anime-style-3">ارسال پیام</h2>
                    </div>
                    <?php if ($flashOk): ?><div class="auth-alert success"><?= e($flashOk) ?></div><?php endif; ?>
                    <?php if ($flashErr): ?><div class="auth-alert error"><?= e($flashErr) ?></div><?php endif; ?>
                    <div class="contact-form wow fadeInUp" data-wow-delay="0.2s">
                        <form id="contactForm" action="<?= url('/contact') ?>" method="POST">
                            <?= csrf_field() ?>
                            <div class="row">
                                <div class="form-group col-md-6 mb-3">
                                    <input type="text" name="first_name" class="form-control" placeholder="نام *" value="<?= old('first_name') ?>" required>
                                </div>
                                <div class="form-group col-md-6 mb-3">
                                    <input type="text" name="last_name" class="form-control" placeholder="نام خانوادگی *" value="<?= old('last_name') ?>" required>
                                </div>
                                <div class="form-group col-md-6 mb-3">
                                    <input type="email" name="email" class="form-control" placeholder="ایمیل" value="<?= old('email') ?>">
                                </div>
                                <div class="form-group col-md-6 mb-3">
                                    <input type="text" name="phone" class="form-control" placeholder="شماره تماس *" value="<?= old('phone') ?>" required>
                                </div>
                                <div class="form-group col-md-12 mb-3">
                                    <textarea name="message" class="form-control" rows="4" placeholder="پیام شما...*" required><?= old('message') ?></textarea>
                                </div>
                                <div class="col-md-12">
                                    <div class="contact-form-btn">
                                        <button type="submit" class="btn-default">ارسال پیام</button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
