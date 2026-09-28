<footer class="main-footer dark-section">
    <div class="container">
        <div class="row">
            <div class="col-lg-12">
                <div class="footer-header">
                    <div class="section-title">
                        <h2 class="text-anime-style-3" data-cursor="-opaque">آماده لبخندی <span>سالم‌تر</span> هستید؟</h2>
                    </div>
                    <div class="footer-book-appointment-btn">
                        <a href="<?= booking_url() ?>" class="btn-default btn-highlighted">
                            <img src="<?= asset('images/icon-footer-book-appointment-item.svg') ?>" alt="">رزرو نوبت
                        </a>
                    </div>
                </div>
            </div>
            <div class="col-xl-4">
                <div class="about-footer">
                    <div class="about-footer-content">
                        <p><?= e(setting('footer_text', 'مراقبت از لبخند شما با درمان‌های پیشرفته و رویکردی بیمارمحور.')) ?></p>
                    </div>
                    <div class="footer-opening-hours">
                        <h2>ساعات کاری</h2>
                        <ul>
                            <?php foreach (preg_split('/\r\n|\r|\n/', (string) setting('working_hours', "شنبه تا پنجشنبه: ۱۰:۰۰ تا ۱۹:۳۰\nجمعه: تعطیل")) as $line): ?>
                                <?php if (trim($line) !== ''): ?><li><?= e($line) ?></li><?php endif; ?>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="col-xl-8">
                <div class="footer-links-box">
                    <div class="footer-links">
                        <h2>دسترسی سریع</h2>
                        <ul>
                            <li><a href="<?= url('/') ?>">خانه</a></li>
                            <li><a href="<?= url('/about') ?>">درباره ما</a></li>
                            <li><a href="<?= url('/services') ?>">خدمات</a></li>
                            <li><a href="<?= url('/contact') ?>">تماس با ما</a></li>
                        </ul>
                    </div>
                    <div class="footer-links">
                        <h2>خدمات ما</h2>
                        <ul>
                            <?php
                            $footerServices = [];
                            try {
                                if (\Sarva\Core\Database::connected()) {
                                    $footerServices = db()->query('SELECT name, slug FROM services WHERE is_active=1 AND deleted_at IS NULL ORDER BY sort_order LIMIT 5')->fetchAll();
                                }
                            } catch (Throwable) {}
                            foreach ($footerServices as $fs): ?>
                                <li><a href="<?= url('/services/' . $fs['slug']) ?>"><?= e($fs['name']) ?></a></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <div class="footer-contact-info footer-links">
                        <h2>ارتباط با ما</h2>
                        <div class="footer-contact-info-list">
                            <ul>
                                <li><?= e(setting('address', 'تهران، ایران')) ?></li>
                                <li><a href="tel:<?= e(preg_replace('/\D+/', '', (string) setting('phone'))) ?>"><?= e(setting('phone')) ?></a></li>
                                <li><a href="mailto:<?= e(setting('email')) ?>"><?= e(setting('email')) ?></a></li>
                            </ul>
                        </div>
                        <div class="footer-social-links">
                            <ul>
                                <?php if (setting('instagram')): ?><li><a href="<?= e(setting('instagram')) ?>" rel="noopener" target="_blank"><i class="fa-brands fa-instagram"></i></a></li><?php endif; ?>
                                <?php if (setting('twitter')): ?><li><a href="<?= e(setting('twitter')) ?>" rel="noopener" target="_blank"><i class="fa-brands fa-x-twitter"></i></a></li><?php endif; ?>
                                <?php if (setting('facebook')): ?><li><a href="<?= e(setting('facebook')) ?>" rel="noopener" target="_blank"><i class="fa-brands fa-facebook-f"></i></a></li><?php endif; ?>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="footer-copyright">
        <div class="container">
            <div class="row">
                <div class="col-lg-12">
                    <div class="footer-copyright-text">
                        <p>© <?= date('Y') ?> <?= e(setting('clinic_name', 'کلینیک دندانپزشکی سروا')) ?> — تمامی حقوق محفوظ است.</p>
                        <p class="clinic-legacy-name clinic-legacy-name--footer">(دکتر سید حسن زمان‌زاده)</p>
                        <p class="footer-credit">طراحی و توسعه توسط تیم نوین کدرز (<span lang="en" dir="ltr">Novin Coders</span>)</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</footer>
