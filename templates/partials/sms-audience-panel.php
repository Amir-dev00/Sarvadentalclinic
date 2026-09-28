<?php
/**
 * Shared SMS Center audience builder (step 1 UI).
 * @var array $modeLabels
 * @var array $doctors
 * @var array $services
 * @var array $audiences
 * @var string $initialMode
 * @var bool $embedAudienceOnly  when true, hide "continue to message" nav
 */
$modeLabels = $modeLabels ?? \Sarva\Services\SmsAudienceService::modeLabels();
$doctors = $doctors ?? [];
$services = $services ?? [];
$audiences = $audiences ?? [];
$initialMode = $initialMode ?? 'all';
$embedAudienceOnly = !empty($embedAudienceOnly);
$modeOrder = ['manual','all','filtered','today','tomorrow','range','this_week','next_week','this_month','next_month','doctor','service','new','old','saved','search'];
?>
<section class="sms-panel admin-card is-active" data-panel="1">
    <h3 class="sms-panel__title">ارسال پیامک به چه کسانی؟</h3>
    <div class="sms-modes">
        <?php foreach ($modeOrder as $m):
            if (!isset($modeLabels[$m])) {
                continue;
            }
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
        <div class="sms-empty">برای شروع، یک میانبر مخاطب را انتخاب کنید یا جستجو کنید.</div>
    </div>
    <div id="smsPager" class="admin-pager"></div>

    <?php if (!$embedAudienceOnly): ?>
    <div class="sms-panel__nav">
        <button type="button" class="btn" style="background:#05B18B;color:#fff;border:0;" data-goto="2">ادامه: متن پیام</button>
    </div>
    <?php else: ?>
    <p class="text-muted" style="margin:12px 0 0;font-size:.88rem;">این انتخاب مخاطب روی قانون خودکار ذخیره می‌شود. زمان ارسال را در بالای فرم تنظیم کنید.</p>
    <?php endif; ?>
</section>
