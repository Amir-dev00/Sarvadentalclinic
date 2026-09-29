<?php
$items = $items ?? [];
$previews = $previews ?? [];
$doctors = $doctors ?? [];
$services = $services ?? [];
$templates = $templates ?? [];
$whoLabels = [
    'all' => 'همه بیمارانِ دارای نوبت',
    'new' => 'فقط بیماران جدید (۳۰ روز اخیر)',
    'old' => 'فقط بیماران قدیمی',
];
?>
<style>
.auto-simple { max-width: 720px; }
.auto-step {
    border: 1px solid #e8ecf4;
    border-radius: 16px;
    padding: 16px 18px;
    margin-bottom: 14px;
    background: #fff;
}
.auto-step__num {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background: #031D4F;
    color: #fff;
    font-size: .85rem;
    font-weight: 700;
    margin-left: 8px;
}
.auto-step__title {
    font-size: 1rem;
    color: #031D4F;
    font-weight: 700;
    margin: 0 0 4px;
}
.auto-step__hint {
    margin: 0 0 12px;
    color: #6b7280;
    font-size: .88rem;
    line-height: 1.6;
}
.auto-when {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    align-items: end;
}
.auto-when .form-control,
.auto-when .form-select { max-width: 140px; }
.auto-who {
    display: grid;
    gap: 8px;
}
.auto-who label {
    display: flex;
    gap: 10px;
    align-items: flex-start;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    padding: 12px 14px;
    cursor: pointer;
    margin: 0;
    transition: border-color .15s, background .15s;
}
.auto-who label:hover { border-color: #05B18B; }
.auto-who label.is-on {
    border-color: #05B18B;
    background: #e8f8f3;
}
.auto-who input { margin-top: 3px; }
.auto-who strong { display: block; color: #031D4F; font-size: .95rem; }
.auto-who span { display: block; color: #6b7280; font-size: .82rem; margin-top: 2px; }
.auto-rule-card {
    border: 1px solid #e8ecf4;
    border-radius: 14px;
    padding: 14px 16px;
    margin-bottom: 10px;
    background: #fbfcfe;
}
.auto-rule-card__top {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
    align-items: flex-start;
}
.auto-rule-card h3 { margin: 0; font-size: 1rem; color: #031D4F; }
.auto-rule-card p { margin: 6px 0 0; color: #4b5563; font-size: .9rem; line-height: 1.65; }
.auto-badge {
    display: inline-block;
    padding: 2px 10px;
    border-radius: 999px;
    font-size: .78rem;
    background: #e8f8f3;
    color: #067a5f;
}
.auto-badge.is-off { background: #f3f4f6; color: #6b7280; }
.auto-preview {
    margin-top: 10px;
    padding: 10px 12px;
    background: #fff;
    border-radius: 10px;
    border: 1px dashed #d1d5db;
    white-space: pre-wrap;
    font-size: .85rem;
    color: #374151;
}
</style>

<div class="admin-card auto-simple">
    <h2 style="margin:0 0 6px;font-size:1.15rem;color:#031D4F;">پیامک یادآوری نوبت</h2>
    <p style="margin:0 0 18px;color:#6b7280;font-size:.92rem;line-height:1.7;">
        یادآوری نوبت فردا یک ارسال روزانه است، نه ارسال بلافاصله بعد از ساخت نوبت.
        برای ارسال دستی/گروهی از <a href="<?= url('/admin/sms/send') ?>">مرکز پیامک</a> استفاده کنید.
    </p>

    <form method="post" action="<?= url('/admin/sms/automation/save') ?>" id="sms-automation-form">
        <?= csrf_field() ?>
        <input type="hidden" name="id" id="rule_id" value="0">
        <input type="hidden" name="appointment_statuses" value="confirmed">

        <div class="auto-step">
            <h3 class="auto-step__title"><span class="auto-step__num">۱</span>نام این یادآوری چیست؟</h3>
            <p class="auto-step__hint">یک اسم ساده بنویسید تا بعداً پیدایش کنید.</p>
            <input name="name" id="rule_name" class="form-control form-control-lg" required
                   placeholder="مثلاً: یادآوری یک روز قبل از نوبت">
        </div>

        <div class="auto-step">
            <h3 class="auto-step__title"><span class="auto-step__num">۲</span>یادآوری نوبت فردا</h3>
            <p class="auto-step__hint">در این ساعت، برای نوبت‌های تأییدشده فردا که هنوز یادآوری نگرفته‌اند پیام ارسال می‌شود. اگر همین امروز ساعت را عوض کنید، زمان جدید در بازه خودش یک‌بار اجرا می‌شود. نوبتی که قبلاً یادآوری گرفته، دوباره پیام نمی‌گیرد.</p>
            <div class="auto-when">
                <div>
                    <label class="form-label">چند</label>
                    <input type="number" min="1" max="30" name="offset_value" id="rule_off" class="form-control form-control-lg" value="1" required>
                </div>
                <div>
                    <label class="form-label">واحد</label>
                    <select name="offset_unit" id="rule_unit" class="form-select form-select-lg">
                        <option value="days">روز قبل از نوبت</option>
                        <option value="hours">ساعت قبل از نوبت</option>
                    </select>
                </div>
                <div id="sendTimeWrap">
                    <label class="form-label">ساعت ارسال یادآوری نوبت‌های فردا</label>
                    <input type="time" name="send_time" id="rule_time" class="form-control form-control-lg" value="18:00">
                </div>
            </div>
            <p id="autoWhenSentence" style="margin:12px 0 0;color:#031D4F;font-weight:600;"></p>
        </div>

        <div class="auto-step">
            <h3 class="auto-step__title"><span class="auto-step__num">۳</span>متن پیام کدام باشد؟</h3>
            <p class="auto-step__hint">قالب را از قبل در «قالب‌های پیامک» بسازید، بعد اینجا انتخاب کنید.</p>
            <select name="template_id" id="rule_tpl" class="form-select form-select-lg" required>
                <option value="">— انتخاب قالب —</option>
                <?php foreach ($templates as $t): ?>
                    <option value="<?= (int) $t['id'] ?>"><?= e($t['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if (!$templates): ?>
                <p style="margin:8px 0 0;color:#9a3412;font-size:.88rem;">هنوز قالبی ندارید. اول یک قالب بسازید، بعد برگردید.</p>
            <?php endif; ?>
        </div>

        <div class="auto-step">
            <h3 class="auto-step__title"><span class="auto-step__num">۴</span>برای چه کسانی برود؟</h3>
            <p class="auto-step__hint">معمولاً همان گزینه اول کافی است.</p>
            <div class="auto-who" id="autoWho">
                <label class="is-on" data-who="all">
                    <input type="radio" name="patient_filter" value="all" checked>
                    <div>
                        <strong>همه بیمارانِ دارای نوبت</strong>
                        <span>هر کسی که نوبت تأییدشده دارد و زمانش با تنظیم بالا جور است.</span>
                    </div>
                </label>
                <label data-who="new">
                    <input type="radio" name="patient_filter" value="new">
                    <div>
                        <strong>فقط بیماران جدید</strong>
                        <span>بیمارانی که در ۳۰ روز اخیر ثبت شده‌اند.</span>
                    </div>
                </label>
                <label data-who="old">
                    <input type="radio" name="patient_filter" value="old">
                    <div>
                        <strong>فقط بیماران قدیمی</strong>
                        <span>بیمارانی که بیشتر از ۳۰ روز است ثبت شده‌اند.</span>
                    </div>
                </label>
            </div>

            <details style="margin-top:14px;">
                <summary style="cursor:pointer;color:#354A72;font-size:.9rem;">محدود کردن اختیاری (پزشک یا خدمت)</summary>
                <div class="row g-3 mt-1">
                    <div class="col-md-6">
                        <label class="form-label">فقط این پزشک</label>
                        <select name="doctor_id" id="rule_doc" class="form-select">
                            <option value="">همه پزشکان</option>
                            <?php foreach ($doctors as $d): ?>
                                <option value="<?= (int) $d['id'] ?>"><?= e(trim($d['first_name'] . ' ' . $d['last_name'])) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">فقط این خدمت</label>
                        <select name="service_id" id="rule_svc" class="form-select">
                            <option value="">همه خدمت‌ها</option>
                            <?php foreach ($services as $s): ?>
                                <option value="<?= (int) $s['id'] ?>"><?= e($s['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </details>
        </div>

        <div class="auto-step" style="display:flex;flex-wrap:wrap;gap:12px;align-items:center;justify-content:space-between;">
            <label class="form-check" style="margin:0;">
                <input type="checkbox" name="is_active" id="rule_on" class="form-check-input" checked>
                این یادآوری فعال باشد
            </label>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-lg" style="background:#05B18B;border:0;color:#fff;">ذخیره</button>
                <button type="button" class="btn btn-lg btn-outline-secondary" onclick="resetRuleForm()">پاک کردن فرم</button>
            </div>
        </div>
    </form>
</div>

<div class="admin-card" style="margin-top:8px;">
    <h2 style="margin:0 0 14px;font-size:1.05rem;color:#031D4F;">یادآوری‌های ذخیره‌شده</h2>
    <?php if (empty($items)): ?>
        <p style="margin:0;color:#6b7280;">هنوز یادآوری نساخته‌اید. فرم بالا را پر کنید و ذخیره بزنید.</p>
    <?php else: foreach ($items as $item):
        $pv = $previews[(int) $item['id']] ?? [];
        $who = \Sarva\Services\SmsAutomationService::normalizePatientFilter($item['patient_filter'] ?? 'all');
        $when = (int) $item['offset_value'] . ' ' . ($item['offset_unit'] === 'hours' ? 'ساعت' : 'روز') . ' قبل';
        if (!empty($item['send_time']) && ($item['offset_unit'] ?? '') !== 'hours') {
            $when .= ' · ساعت ' . substr((string) $item['send_time'], 0, 5);
        }
        ?>
        <div class="auto-rule-card">
            <div class="auto-rule-card__top">
                <div>
                    <h3><?= e($item['name']) ?>
                        <span class="auto-badge<?= empty($item['is_active']) ? ' is-off' : '' ?>">
                            <?= !empty($item['is_active']) ? 'فعال' : 'خاموش' ?>
                        </span>
                    </h3>
                    <p>
                        زمان: <?= e($when) ?><br>
                        قالب: <?= e($item['template_name'] ?? '') ?><br>
                        مخاطب: <?= e($whoLabels[$who] ?? $who) ?>
                    </p>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <?php if (($item['offset_unit'] ?? '') !== 'hours'):
                        $eligibleTomorrow = (int) (($tomorrowCounts[(int) $item['id']] ?? 0));
                        ?>
                    <form method="post" action="<?= url('/admin/sms/automation/run-tomorrow') ?>" style="display:inline;"
                          onsubmit="return confirm('برای <?= $eligibleTomorrow ?> نوبت فردا پیام یادآوری ارسال شود؟');">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
                        <button class="btn btn-sm btn-outline-success" type="submit">ارسال یادآوری نوبت‌های فردا اکنون</button>
                    </form>
                    <?php endif; ?>
                    <button type="button" class="btn btn-sm btn-outline-primary"
                        onclick='editRule(<?= json_encode([
                            'id' => (int) $item['id'],
                            'name' => $item['name'] ?? '',
                            'offset_value' => (int) ($item['offset_value'] ?? 1),
                            'offset_unit' => $item['offset_unit'] ?? 'days',
                            'send_time' => substr((string) ($item['send_time'] ?? '18:00:00'), 0, 5),
                            'template_id' => (int) ($item['template_id'] ?? 0),
                            'patient_filter' => $who,
                            'doctor_id' => (int) ($item['doctor_id'] ?? 0),
                            'service_id' => (int) ($item['service_id'] ?? 0),
                            'is_active' => (int) ($item['is_active'] ?? 0),
                        ], JSON_UNESCAPED_UNICODE) ?>)'>ویرایش</button>
                    <form method="post" action="<?= url('/admin/sms/automation/toggle') ?>" style="display:inline;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
                        <button class="btn btn-sm <?= !empty($item['is_active']) ? 'btn-outline-warning' : 'btn-outline-success' ?>">
                            <?= !empty($item['is_active']) ? 'خاموش کردن' : 'روشن کردن' ?>
                        </button>
                    </form>
                </div>
            </div>
            <?php if (!empty($pv['preview'])): ?>
                <div class="auto-preview"><?= e($pv['preview']) ?></div>
            <?php endif; ?>
        </div>
    <?php endforeach; endif; ?>
</div>

<script>
(function () {
    var unit = document.getElementById('rule_unit');
    var off = document.getElementById('rule_off');
    var time = document.getElementById('rule_time');
    var timeWrap = document.getElementById('sendTimeWrap');
    var sentence = document.getElementById('autoWhenSentence');
    var whoBox = document.getElementById('autoWho');

    function paintWhen() {
        var n = off.value || '1';
        var u = unit.value === 'hours' ? 'ساعت' : 'روز';
        timeWrap.style.display = unit.value === 'hours' ? 'none' : '';
        if (unit.value === 'hours') {
            sentence.textContent = 'پیام حدود ' + n + ' ساعت قبل از ساعت نوبت ارسال می‌شود.';
        } else {
            sentence.textContent = 'در ساعت ' + (time.value || '18:00') + ' برای نوبت‌های فردا که هنوز یادآوری نگرفته‌اند پیام ارسال می‌شود. تغییر ساعت از همین امروز در زمان جدید اجرا می‌شود.';
        }
    }
    unit.addEventListener('change', paintWhen);
    off.addEventListener('input', paintWhen);
    time.addEventListener('change', paintWhen);
    paintWhen();

    whoBox.addEventListener('change', function (e) {
        if (!e.target || e.target.name !== 'patient_filter') return;
        whoBox.querySelectorAll('label').forEach(function (lab) {
            lab.classList.toggle('is-on', lab.getAttribute('data-who') === e.target.value);
        });
    });

    window.editRule = function (r) {
        document.getElementById('rule_id').value = r.id || 0;
        document.getElementById('rule_name').value = r.name || '';
        document.getElementById('rule_off').value = r.offset_value || 1;
        document.getElementById('rule_unit').value = r.offset_unit || 'days';
        document.getElementById('rule_time').value = r.send_time || '18:00';
        document.getElementById('rule_tpl').value = r.template_id || '';
        document.getElementById('rule_doc').value = r.doctor_id || '';
        document.getElementById('rule_svc').value = r.service_id || '';
        document.getElementById('rule_on').checked = !!r.is_active;
        var pf = r.patient_filter || 'all';
        whoBox.querySelectorAll('input[name="patient_filter"]').forEach(function (inp) {
            inp.checked = inp.value === pf;
        });
        whoBox.querySelectorAll('label').forEach(function (lab) {
            lab.classList.toggle('is-on', lab.getAttribute('data-who') === pf);
        });
        paintWhen();
        window.scrollTo({ top: 0, behavior: 'smooth' });
    };

    window.resetRuleForm = function () {
        document.getElementById('rule_id').value = 0;
        document.getElementById('rule_name').value = '';
        document.getElementById('rule_off').value = 1;
        document.getElementById('rule_unit').value = 'days';
        document.getElementById('rule_time').value = '18:00';
        document.getElementById('rule_tpl').value = '';
        document.getElementById('rule_doc').value = '';
        document.getElementById('rule_svc').value = '';
        document.getElementById('rule_on').checked = true;
        whoBox.querySelectorAll('input[name="patient_filter"]').forEach(function (inp) {
            inp.checked = inp.value === 'all';
        });
        whoBox.querySelectorAll('label').forEach(function (lab) {
            lab.classList.toggle('is-on', lab.getAttribute('data-who') === 'all');
        });
        paintWhen();
    };
})();
</script>
