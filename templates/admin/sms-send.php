<?php
$templates = $templates ?? [];
$doctors = $doctors ?? [];
$services = $services ?? [];
$audiences = $audiences ?? [];
$quickCounts = $quickCounts ?? [];
$modeLabels = $modeLabels ?? [];
$placeholders = $placeholders ?? [];
$preselectIds = $preselectIds ?? [];
$initialMode = $initialMode ?? 'manual';
$csrf = $csrf ?? csrf_token();
$bulkLimit = (int) ($bulkLimit ?? 500);
$catLabels = [
    'appointment' => 'نوبت',
    'reminder' => 'یادآوری',
    'payment' => 'پرداخت',
    'operational' => 'عملیاتی',
    'followup' => 'پیگیری',
    'marketing' => 'اطلاع‌رسانی',
    'general' => 'عمومی',
];
?>
<div id="smsCenter"
     class="sms-center"
     data-csrf="<?= e($csrf) ?>"
     data-initial-mode="<?= e($initialMode) ?>"
     data-bulk-limit="<?= $bulkLimit ?>"
     data-preselect="<?= e(json_encode(array_values($preselectIds), JSON_UNESCAPED_UNICODE)) ?>"
     data-api-patients="<?= e(url('/admin/sms/api/patients')) ?>"
     data-api-summary="<?= e(url('/admin/sms/api/summary')) ?>"
     data-api-preview="<?= e(url('/admin/sms/api/preview-recipients')) ?>"
     data-api-message="<?= e(url('/admin/sms/api/preview-message')) ?>"
     data-api-send="<?= e(url('/admin/sms/api/send')) ?>"
     data-api-test="<?= e(url('/admin/sms/api/test-send')) ?>"
     data-api-audiences-save="<?= e(url('/admin/sms/api/audiences/save')) ?>"
     data-api-audiences-delete="<?= e(url('/admin/sms/api/audiences/delete')) ?>"
     data-batch-base="<?= e(url('/admin/sms/batches')) ?>">

    <div class="sms-center__head admin-card">
        <div>
            <h2 style="margin:0;font-size:1.2rem;color:#031D4F;">مرکز ارسال پیامک</h2>
            <p style="margin:6px 0 0;color:#6b7280;">گیرندگان را بسازید، پیام را شخصی‌سازی کنید، سپس تأیید و ارسال کنید.</p>
        </div>
        <div class="sms-steps" role="tablist">
            <button type="button" class="sms-step is-active" data-step="1"><span>۱</span> گیرندگان</button>
            <button type="button" class="sms-step" data-step="2"><span>۲</span> متن پیام</button>
            <button type="button" class="sms-step" data-step="3"><span>۳</span> زمان و تأیید</button>
        </div>
    </div>

    <div class="sms-quick admin-card">
        <div class="sms-quick__title">میانبر مخاطبان</div>
        <div class="sms-quick__row">
            <button type="button" class="sms-chip" data-mode="today">نوبت‌های امروز <strong><?= (int) ($quickCounts['today'] ?? 0) ?></strong></button>
            <button type="button" class="sms-chip" data-mode="tomorrow">نوبت‌های فردا <strong><?= (int) ($quickCounts['tomorrow'] ?? 0) ?></strong></button>
            <button type="button" class="sms-chip" data-mode="this_week">این هفته <strong><?= (int) ($quickCounts['this_week'] ?? 0) ?></strong></button>
            <button type="button" class="sms-chip" data-mode="new">بیماران جدید <strong><?= (int) ($quickCounts['new'] ?? 0) ?></strong></button>
            <button type="button" class="sms-chip" data-mode="manual">انتخاب دستی</button>
            <button type="button" class="sms-chip" data-mode="all">همه بیماران <strong><?= (int) ($quickCounts['all'] ?? 0) ?></strong></button>
        </div>
    </div>

    <div class="sms-layout">
        <div class="sms-main">
            <!-- STEP 1 -->
            <section class="sms-panel admin-card is-active" data-panel="1">
                <h3 class="sms-panel__title">ارسال پیامک به چه کسانی؟</h3>
                <div class="sms-modes">
                    <?php
                    $modeOrder = ['manual','all','filtered','today','tomorrow','range','this_week','next_week','this_month','next_month','doctor','service','new','old','saved','search'];
                    foreach ($modeOrder as $m):
                        if (!isset($modeLabels[$m])) continue;
                    ?>
                    <button type="button" class="sms-mode<?= $initialMode === $m ? ' is-active' : '' ?>" data-mode="<?= e($m) ?>"><?= e($modeLabels[$m]) ?></button>
                    <?php endforeach; ?>
                </div>

                <div class="sms-search-wrap">
                    <label class="form-label">جستجوی سریع بیماران</label>
                    <input type="search" id="smsPatientSearch" class="form-control form-control-lg sms-search" placeholder="نام، نام خانوادگی، موبایل، شماره پرونده، کد ملی، شناسه...">
                </div>

                <details class="sms-advanced" id="smsAdvanced">
                    <summary>فیلترهای پیشرفته</summary>
                    <div class="row g-3 mt-1">
                        <div class="col-md-3">
                            <label class="form-label">نام</label>
                            <input class="form-control" data-filter="first_name">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">نام خانوادگی</label>
                            <input class="form-control" data-filter="last_name">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">شماره پرونده</label>
                            <input class="form-control" data-filter="file_number">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">موبایل</label>
                            <input class="form-control" data-filter="mobile" dir="ltr">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">منبع</label>
                            <select class="form-select" data-filter="source">
                                <option value="">همه</option>
                                <option value="manual">ثبت دستی</option>
                                <option value="imported">واردشده از Excel</option>
                                <option value="online">ثبت آنلاین / اپ</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">وضعیت بیمار</label>
                            <select class="form-select" data-filter="status">
                                <option value="active">فعال</option>
                                <option value="archived">بایگانی</option>
                                <option value="all">همه</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">ثبت از تاریخ</label>
                            <input type="text" class="form-control" data-filter="registered_from" data-jalali="date" placeholder="شمسی">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">ثبت تا تاریخ</label>
                            <input type="text" class="form-control" data-filter="registered_to" data-jalali="date" placeholder="شمسی">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">پزشک(ها)</label>
                            <select class="form-select" data-filter="doctor_ids" multiple size="4">
                                <?php foreach ($doctors as $d): ?>
                                <option value="<?= (int) $d['id'] ?>"><?= e(trim($d['first_name'] . ' ' . $d['last_name'])) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">خدمت(ها)</label>
                            <select class="form-select" data-filter="service_ids" multiple size="4">
                                <?php foreach ($services as $s): ?>
                                <option value="<?= (int) $s['id'] ?>"><?= e($s['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">وضعیت نوبت</label>
                            <select class="form-select" data-filter="appointment_statuses" multiple size="4">
                                <option value="confirmed" selected>تأیید شده</option>
                                <option value="completed">انجام شده</option>
                                <option value="no_show">عدم مراجعه</option>
                                <option value="cancelled">لغو شده</option>
                                <option value="awaiting_payment">در انتظار پرداخت</option>
                            </select>
                            <small class="text-muted">برای یادآوری، وضعیت‌های لغو شده لحاظ نمی‌شوند.</small>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">نوبت از</label>
                            <input type="text" class="form-control" data-filter="appt_date_from" data-jalali="date">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">نوبت تا</label>
                            <input type="text" class="form-control" data-filter="appt_date_to" data-jalali="date">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">آخرین مراجعه از</label>
                            <input type="text" class="form-control" data-filter="last_visit_from" data-jalali="date">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">بدون مراجعه از</label>
                            <input type="text" class="form-control" data-filter="no_visit_since" data-jalali="date">
                        </div>
                        <div class="col-md-3">
                            <label class="form-check mt-4">
                                <input type="checkbox" class="form-check-input" data-filter-bool="upcoming"> نوبت پیش‌رو دارد
                            </label>
                        </div>
                        <div class="col-md-3">
                            <label class="form-check mt-4">
                                <input type="checkbox" class="form-check-input" data-filter-bool="no_upcoming"> بدون نوبت پیش‌رو
                            </label>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">گروه ذخیره‌شده</label>
                            <select class="form-select" id="smsSavedAudience">
                                <option value="0">—</option>
                                <?php foreach ($audiences as $a): ?>
                                <option value="<?= (int) $a['id'] ?>"><?= e($a['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3 d-flex align-items-end gap-2">
                            <button type="button" class="btn btn-primary" id="smsApplyFilters" style="background:#031D4F;border:0;">اعمال فیلتر</button>
                        </div>
                    </div>
                </details>

                <div class="sms-select-bar">
                    <button type="button" class="btn btn-sm btn-outline-primary" id="smsSelectPage">انتخاب همه نتایج صفحه</button>
                    <button type="button" class="btn btn-sm btn-outline-success" id="smsSelectAllFiltered">انتخاب همه نتایج فیلترشده</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="smsClearSelection">لغو انتخاب همه</button>
                    <button type="button" class="btn btn-sm btn-outline-danger" id="smsClearExclusions">پاک کردن حذف‌شده‌ها</button>
                    <button type="button" class="btn btn-sm btn-outline-dark" id="smsSaveAudience">ذخیره گروه</button>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="smsOpenPreview">مشاهده گیرندگان</button>
                </div>

                <div id="smsSelectAllBanner" class="sms-banner" hidden></div>
                <div id="smsExcludeBanner" class="sms-banner sms-banner--warn" hidden></div>

                <div id="smsPatientList" class="sms-patient-list">
                    <div class="sms-empty">برای شروع، جستجو کنید یا یک میانبر مخاطب را انتخاب کنید.</div>
                </div>
                <div id="smsPager" class="admin-pager"></div>

                <div class="sms-panel__nav">
                    <button type="button" class="btn" style="background:#05B18B;color:#fff;border:0;" data-goto="2">ادامه: متن پیام</button>
                </div>
            </section>

            <!-- STEP 2 -->
            <section class="sms-panel admin-card" data-panel="2" hidden>
                <h3 class="sms-panel__title">متن پیام</h3>
                <div class="row g-3">
                    <div class="col-md-5">
                        <label class="form-label">جستجوی قالب</label>
                        <input type="search" id="smsTplSearch" class="form-control" placeholder="جستجو در پیام‌های آماده...">
                        <div class="sms-tpl-list" id="smsTplList">
                            <button type="button" class="sms-tpl is-active" data-id="0" data-body="" data-cat="custom">
                                <strong>پیام سفارشی</strong>
                                <span>نوشتن متن آزاد</span>
                            </button>
                            <?php foreach ($templates as $t):
                                $cat = (string) ($t['category'] ?? 'general');
                                $fav = !empty($t['is_favorite']);
                            ?>
                            <button type="button" class="sms-tpl<?= $fav ? ' is-fav' : '' ?>"
                                    data-id="<?= (int) $t['id'] ?>"
                                    data-body="<?= e($t['body']) ?>"
                                    data-cat="<?= e($cat) ?>"
                                    data-name="<?= e($t['name']) ?>">
                                <strong><?= $fav ? '★ ' : '' ?><?= e($t['name']) ?></strong>
                                <span><?= e($catLabels[$cat] ?? $cat) ?> · <?= e(sms_type_labels()[$t['type'] ?? ''] ?? ($t['type'] ?? '')) ?></span>
                            </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="col-md-7">
                        <label class="form-label">متن پیامک</label>
                        <textarea id="smsMessage" class="form-control" rows="8" placeholder="متن پیام را بنویسید..."></textarea>
                        <div class="sms-vars">
                            <?php foreach ($placeholders as $key => $label): ?>
                            <button type="button" class="sms-var" data-var="{<?= e($key) ?>}">{<?= e($label) ?></button>
                            <?php endforeach; ?>
                        </div>
                        <div class="sms-msg-meta">
                            <span id="smsCharCount">۰ کاراکتر</span>
                            <span id="smsSegCount">۰ بخش (تخمینی)</span>
                        </div>
                        <div class="sms-live-preview">
                            <div class="sms-live-preview__label">پیش‌نمایش با بیمار نمونه: <strong id="smsSampleName">—</strong></div>
                            <pre id="smsRendered" class="sms-preview">متن پیش‌نمایش اینجا نمایش داده می‌شود.</pre>
                        </div>
                        <div class="mt-3">
                            <label class="form-label">نوع پیام</label>
                            <select id="smsPurpose" class="form-select">
                                <option value="operational">عملیاتی / تراکنشی (نوبت، تأیید، پرداخت)</option>
                                <option value="marketing">اطلاع‌رسانی / بازاریابی</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="sms-panel__nav">
                    <button type="button" class="btn btn-outline-secondary" data-goto="1">بازگشت</button>
                    <button type="button" class="btn" style="background:#05B18B;color:#fff;border:0;" data-goto="3">ادامه: زمان و تأیید</button>
                </div>
            </section>

            <!-- STEP 3 -->
            <section class="sms-panel admin-card" data-panel="3" hidden>
                <h3 class="sms-panel__title">زمان ارسال و تأیید نهایی</h3>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">نحوه ارسال</label>
                        <div class="sms-send-modes">
                            <label class="sms-send-mode is-active"><input type="radio" name="send_mode" value="now" checked> ارسال همین حالا</label>
                            <label class="sms-send-mode"><input type="radio" name="send_mode" value="scheduled"> زمان‌بندی ارسال</label>
                        </div>
                        <div id="smsScheduleWrap" class="mt-3" hidden>
                            <label class="form-label">تاریخ و ساعت ارسال</label>
                            <input type="text" id="smsScheduledAt" class="form-control" data-jalali="datetime" placeholder="تاریخ و ساعت شمسی">
                            <div class="d-flex gap-2 flex-wrap mt-2">
                                <button type="button" class="btn btn-sm btn-outline-secondary" data-sched-preset="today">امروز</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" data-sched-preset="tomorrow">فردا</button>
                            </div>
                        </div>
                        <div class="mt-3">
                            <label class="form-label">ارسال آزمایشی (یک شماره)</label>
                            <div class="input-group">
                                <input type="text" id="smsTestMobile" class="form-control" dir="ltr" placeholder="0912...">
                                <button type="button" class="btn btn-outline-primary" id="smsTestSend">ارسال آزمایشی</button>
                            </div>
                            <small class="text-muted">فقط یک پیام آزمایشی در صف قرار می‌گیرد؛ گیرندگان گروهی اضافه نمی‌شوند.</small>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="sms-confirm-box" id="smsConfirmBox">
                            <h4>خلاصه تأیید</h4>
                            <ul>
                                <li>گیرندگان انتخاب‌شده: <strong data-k="selected">۰</strong></li>
                                <li>شماره معتبر: <strong data-k="valid_mobile">۰</strong></li>
                                <li>فاقد شماره معتبر: <strong data-k="invalid_mobile">۰</strong></li>
                                <li>تکراری حذف‌شده: <strong data-k="duplicates_removed">۰</strong></li>
                                <li>گیرندگان نهایی پیامک: <strong data-k="final_recipients">۰</strong></li>
                                <li>نوع ارسال: <strong id="smsConfirmSendMode">فوری</strong></li>
                                <li>زمان ارسال: <strong id="smsConfirmWhen">همین حالا</strong></li>
                                <li>قالب / متن: <strong id="smsConfirmTpl">سفارشی</strong></li>
                                <li>بخش تقریبی پیامک: <strong id="smsConfirmSeg">۰</strong></li>
                            </ul>
                            <label class="form-check mt-2">
                                <input type="checkbox" class="form-check-input" id="smsConfirmCheck"> تأیید می‌کنم که پیام برای گیرندگان نهایی ارسال شود
                            </label>
                            <button type="button" class="btn w-100 mt-3" id="smsConfirmSend" style="background:#05B18B;color:#fff;border:0;" disabled>تأیید و ارسال</button>
                            <div id="smsSendError" class="sms-error" hidden></div>
                        </div>
                    </div>
                </div>
                <div class="sms-panel__nav">
                    <button type="button" class="btn btn-outline-secondary" data-goto="2">بازگشت</button>
                </div>
            </section>
        </div>

        <aside class="sms-side admin-card" id="smsSideSummary">
            <button type="button" class="sms-side__toggle d-lg-none" id="smsSideToggle">خلاصه گیرندگان</button>
            <div class="sms-side__body">
                <div class="sms-side__count">گیرندگان انتخاب‌شده: <strong id="smsLiveCount">۰</strong> نفر</div>
                <ul class="sms-side__stats">
                    <li>شماره معتبر: <span id="smsStatValid">۰</span></li>
                    <li>فاقد/نامعتبر: <span id="smsStatInvalid">۰</span></li>
                    <li>تکراری حذف‌شده: <span id="smsStatDup">۰</span></li>
                    <li>گیرندگان نهایی: <span id="smsStatFinal">۰</span></li>
                </ul>
                <div class="sms-side__mode">حالت فعال: <strong id="smsActiveModeLabel">انتخاب دستی</strong></div>
                <div class="sms-side__tpl">قالب: <strong id="smsSideTpl">سفارشی</strong></div>
                <div class="sms-side__len">طول پیام: <strong id="smsSideLen">۰</strong> · <strong id="smsSideSeg">۰</strong> بخش</div>
                <div class="sms-side__when">ارسال: <strong id="smsSideWhen">همین حالا</strong></div>
            </div>
        </aside>
    </div>
</div>

<!-- Recipients modal -->
<div class="sms-modal" id="smsRecipientsModal" hidden>
    <div class="sms-modal__dialog">
        <div class="sms-modal__head">
            <h3>مشاهده گیرندگان</h3>
            <button type="button" class="btn-close" id="smsClosePreview" aria-label="بستن"></button>
        </div>
        <div class="sms-modal__toolbar">
            <input type="search" id="smsPreviewSearch" class="form-control" placeholder="جستجو در گیرندگان...">
        </div>
        <div class="sms-modal__body" id="smsPreviewList"></div>
        <div class="sms-modal__foot">
            <div id="smsPreviewPager" class="admin-pager"></div>
            <button type="button" class="btn btn-secondary" id="smsClosePreview2">بستن</button>
        </div>
    </div>
</div>

<script>
window.SMS_MODE_LABELS = <?= json_encode($modeLabels, JSON_UNESCAPED_UNICODE) ?>;
</script>
<script src="<?= asset('js/sms-center.js') ?>"></script>
