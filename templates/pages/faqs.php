<?php partial('page-header', ['pageTitle' => $title ?? 'سؤالات متداول']); ?>
<div class="page-faqs">
    <div class="container">
        <div class="row">
            <div class="col-lg-4">
                <div class="page-single-sidebar">
                    <div class="section-title wow fadeInUp">
                        <span class="section-sub-title">پشتیبانی</span>
                        <h2 class="text-anime-style-3" data-cursor="-opaque">پاسخ به پرسش‌های شما</h2>
                        <p>اگر پاسخ خود را پیدا نکردید، با ما در تماس باشید.</p>
                    </div>
                    <div class="sidebar-cta-box wow fadeInUp admin-card" data-wow-delay="0.2s">
                        <div class="sidebar-cta-contact-item-list">
                            <div class="sidebar-cta-contact-item mb-3">
                                <p>تلفن</p>
                                <h3><a href="tel:<?= e(setting('phone', '')) ?>"><?= e(setting('phone', '')) ?></a></h3>
                            </div>
                            <div class="sidebar-cta-contact-item">
                                <p>ایمیل</p>
                                <h3><a href="mailto:<?= e(setting('email', '')) ?>"><?= e(setting('email', '')) ?></a></h3>
                            </div>
                        </div>
                        <a href="<?= url('/contact') ?>" class="btn-default mt-3">تماس با ما</a>
                    </div>
                </div>
            </div>
            <div class="col-lg-8">
                <div class="page-faqs-category">
                    <div class="section-title">
                        <h2 class="wow text-anime-style-3" data-cursor="-opaque">سؤالات پرتکرار</h2>
                    </div>
                    <div class="faq-accordion our-faq-accordion accordion" id="faqAccordion">
                        <?php foreach (($faqs ?? []) as $i => $faq): ?>
                        <?php $id = $i + 1; ?>
                        <div class="accordion-item wow fadeInUp" data-wow-delay="<?= e((string) min(0.2 * $i, 0.8)) ?>s">
                            <h2 class="accordion-header" id="heading<?= $id ?>">
                                <button class="accordion-button<?= $i === 0 ? '' : ' collapsed' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?= $id ?>" aria-expanded="<?= $i === 0 ? 'true' : 'false' ?>" aria-controls="collapse<?= $id ?>">
                                    <?= e($faq['question'] ?? '') ?>
                                </button>
                            </h2>
                            <div id="collapse<?= $id ?>" class="accordion-collapse collapse<?= $i === 0 ? ' show' : '' ?>" role="region" aria-labelledby="heading<?= $id ?>" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    <p><?= nl2br(e($faq['answer'] ?? '')) ?></p>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php if (empty($faqs)): ?>
                        <p class="py-4">هنوز سؤالی ثبت نشده است.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
