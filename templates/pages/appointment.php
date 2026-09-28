<?php
partial('page-header', ['pageTitle' => $title ?? 'رزرو نوبت']);
$isPatient = !empty($isPatient);
$services = $services ?? [];
$doctors = $doctors ?? [];
$defaultDoctor = $doctors[0] ?? null;
$defaultDoctorId = (int) ($defaultDoctor['id'] ?? 0);
$defaultDoctorName = $defaultDoctor
    ? trim(($defaultDoctor['first_name'] ?? '') . ' ' . ($defaultDoctor['last_name'] ?? ''))
    : '';
?>
<div class="page-appointment">
    <div class="container">
        <div class="row">
            <div class="col-xl-10 mx-auto">
                <div class="section-title section-title-center mb-4">
                    <span class="section-sub-title wow fadeInUp">نوبت آنلاین</span>
                    <h2 class="text-anime-style-3" data-cursor="-opaque">رزرو نوبت در <span>کلینیک سروا</span></h2>
                    <p class="wow fadeInUp" data-wow-delay="0.2s">خدمت و زمان مناسب را انتخاب کنید و پرداخت را تکمیل نمایید.</p>
                </div>

                <?php if (!$isPatient): ?>
                <div class="auth-alert error booking-login-alert">
                    برای رزرو نوبت ابتدا وارد حساب بیمار شوید.
                    <a href="<?= url('/auth') ?>" class="btn-default">ورود / ثبت‌نام</a>
                </div>
                <?php endif; ?>

                <div id="booking-app"
                     class="booking-wizard"
                     data-logged-in="<?= $isPatient ? '1' : '0' ?>"
                     data-slots-url="<?= e(url('/api/appointment/slots')) ?>"
                     data-book-url="<?= e(url('/api/appointment/book')) ?>"
                     data-auth-url="<?= e(url('/auth')) ?>"
                     data-default-doctor-id="<?= $defaultDoctorId ?>"
                     data-default-doctor-name="<?= e($defaultDoctorName) ?>">

                    <ol class="booking-progress" id="booking-steps" aria-label="مراحل رزرو">
                        <li class="booking-progress-item is-active" data-step="1">
                            <span class="booking-progress-num" aria-hidden="true">۱</span>
                            <span class="booking-progress-label">خدمات</span>
                        </li>
                        <li class="booking-progress-item" data-step="2">
                            <span class="booking-progress-num" aria-hidden="true">۲</span>
                            <span class="booking-progress-label">زمان</span>
                        </li>
                        <li class="booking-progress-item" data-step="3">
                            <span class="booking-progress-num" aria-hidden="true">۳</span>
                            <span class="booking-progress-label">تایید نهایی</span>
                        </li>
                    </ol>

                    <!-- Step 1: Service -->
                    <section class="booking-panel is-active" data-panel="1" aria-labelledby="booking-step1-title">
                        <header class="booking-panel-head">
                            <h3 id="booking-step1-title">انتخاب خدمت</h3>
                            <p>خدمت مورد نظر خود را از میان گزینه‌ها انتخاب کنید.</p>
                        </header>
                        <div class="booking-card-grid" id="service-grid" role="radiogroup" aria-labelledby="booking-step1-title">
                            <?php foreach ($services as $service): ?>
                                <?php
                                $sid = (int) $service['id'];
                                $icon = $service['icon'] ?? '';
                                $price = $service['price'] ?? null;
                                $duration = (int) ($service['duration_minutes'] ?? 0);
                                ?>
                                <label class="booking-select-card" for="service-<?= $sid ?>">
                                    <input
                                        type="radio"
                                        class="booking-select-input"
                                        name="service_id"
                                        id="service-<?= $sid ?>"
                                        value="<?= $sid ?>"
                                        data-name="<?= e($service['name']) ?>"
                                        data-price="<?= e((string) ($price ?? '')) ?>"
                                    >
                                    <span class="booking-select-body">
                                        <span class="booking-select-check" aria-hidden="true"></span>
                                        <?php if ($icon): ?>
                                            <span class="booking-select-icon">
                                                <img src="<?= media_url($icon) ?>" alt="" width="40" height="40">
                                            </span>
                                        <?php else: ?>
                                            <span class="booking-select-icon booking-select-icon--fallback" aria-hidden="true">
                                                <i class="fa-solid fa-tooth"></i>
                                            </span>
                                        <?php endif; ?>
                                        <span class="booking-select-content">
                                            <span class="booking-select-title"><?= e($service['name']) ?></span>
                                            <?php if (!empty($service['short_description'])): ?>
                                                <span class="booking-select-desc"><?= e($service['short_description']) ?></span>
                                            <?php endif; ?>
                                            <span class="booking-select-meta">
                                                <?php if ($price !== null && $price !== ''): ?>
                                                    <strong><?= e(number_format((float) $price)) ?> ریال</strong>
                                                <?php endif; ?>
                                                <?php if ($duration > 0): ?>
                                                    <em><?= e((string) $duration) ?> دقیقه</em>
                                                <?php endif; ?>
                                            </span>
                                        </span>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                            <?php if (empty($services)): ?>
                                <p class="booking-empty">خدمت فعالی برای رزرو وجود ندارد.</p>
                            <?php endif; ?>
                        </div>
                    </section>

                    <!-- Step 2: Date & time -->
                    <section class="booking-panel" data-panel="2" hidden aria-labelledby="booking-step2-title">
                        <header class="booking-panel-head">
                            <h3 id="booking-step2-title">تاریخ و ساعت</h3>
                            <p>روز و ساعت آزاد مورد نظر را مشخص کنید.</p>
                        </header>
                        <div class="booking-datetime">
                            <div class="booking-date-field">
                                <label class="booking-field-label" id="booking-date-label">تاریخ نوبت (شمسی)</label>
                                <div id="jalali-calendar" class="jalali-calendar" aria-labelledby="booking-date-label"></div>
                                <input type="hidden" id="booking-date" value="" autocomplete="off">
                            </div>
                            <div class="booking-slots-wrap">
                                <div class="booking-field-label" id="slots-label">ساعات آزاد</div>
                                <div id="slots-status" class="booking-slots-status" role="status">ابتدا تاریخ را انتخاب کنید.</div>
                                <div class="booking-slots" id="slots-grid" role="radiogroup" aria-labelledby="slots-label"></div>
                            </div>
                        </div>
                    </section>

                    <!-- Step 3: Confirm -->
                    <section class="booking-panel" data-panel="3" hidden aria-labelledby="booking-step3-title">
                        <header class="booking-panel-head">
                            <h3 id="booking-step3-title">خلاصه و تأیید</h3>
                            <p>اطلاعات نوبت را بررسی کنید و ثبت را نهایی نمایید.</p>
                        </header>
                        <ul class="booking-summary" id="booking-summary">
                            <li><span>خدمت</span><strong data-sum="service">—</strong></li>
                            <li><span>تاریخ</span><strong data-sum="date">—</strong></li>
                            <li><span>ساعت</span><strong data-sum="time">—</strong></li>
                            <li data-sum-price-row style="display:none;"><span>مبلغ</span><strong data-sum="price">—</strong></li>
                        </ul>
                        <div id="booking-message" class="auth-alert" hidden></div>
                    </section>

                    <div class="booking-nav">
                        <button type="button" class="booking-btn booking-btn--ghost" id="btn-prev" hidden>مرحله قبل</button>
                        <button type="button" class="booking-btn booking-btn--primary" id="btn-next">ادامه</button>
                        <button type="button" class="booking-btn booking-btn--accent" id="btn-book" hidden>ثبت نوبت و پرداخت</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="<?= asset('js/jalali-datepicker.js') ?>"></script>
<script>
(function () {
  var app = document.getElementById('booking-app');
  if (!app) return;

  var state = {
    step: 1,
    serviceId: 0,
    serviceName: '',
    servicePrice: '',
    doctorId: parseInt(app.getAttribute('data-default-doctor-id') || '0', 10) || 0,
    doctorName: app.getAttribute('data-default-doctor-name') || '',
    date: '',
    time: ''
  };

  var loggedIn = app.getAttribute('data-logged-in') === '1';
  var slotsUrl = app.getAttribute('data-slots-url') || '';
  var bookUrl = app.getAttribute('data-book-url') || '';
  var authUrl = app.getAttribute('data-auth-url') || '/auth';
  var totalSteps = 3;

  var progressItems = app.querySelectorAll('.booking-progress-item');
  var panels = app.querySelectorAll('.booking-panel');
  var btnPrev = document.getElementById('btn-prev');
  var btnNext = document.getElementById('btn-next');
  var btnBook = document.getElementById('btn-book');
  var dateInput = document.getElementById('booking-date');
  var calendarRoot = document.getElementById('jalali-calendar');
  var slotsGrid = document.getElementById('slots-grid');
  var slotsStatus = document.getElementById('slots-status');
  var msgEl = document.getElementById('booking-message');
  var priceRow = app.querySelector('[data-sum-price-row]');
  var dateLabelJalali = '';
  var jalaliPicker = null;

  if (calendarRoot && window.SarvaJalali) {
    jalaliPicker = window.SarvaJalali.mountJalaliCalendar(calendarRoot, {
      minIso: <?= json_encode(date('Y-m-d'), JSON_UNESCAPED_UNICODE) ?>,
      onSelect: function (iso, label) {
        state.date = iso || '';
        dateLabelJalali = label || '';
        if (dateInput) dateInput.value = state.date;
        state.time = '';
        clearSlotSelection();
        loadSlots();
      }
    });
  }

  function setHidden(el, hidden) {
    if (!el) return;
    if (hidden) {
      el.setAttribute('hidden', '');
      el.hidden = true;
    } else {
      el.removeAttribute('hidden');
      el.hidden = false;
    }
  }

  function showMsg(text, ok) {
    setHidden(msgEl, false);
    msgEl.className = 'auth-alert ' + (ok ? 'success' : 'error');
    msgEl.textContent = text;
  }

  function clearMsg() {
    setHidden(msgEl, true);
    msgEl.textContent = '';
  }

  function renderStep() {
    progressItems.forEach(function (item) {
      var s = parseInt(item.getAttribute('data-step'), 10);
      item.classList.toggle('is-active', s === state.step);
      item.classList.toggle('is-done', s < state.step);
      item.setAttribute('aria-current', s === state.step ? 'step' : 'false');
    });

    panels.forEach(function (panel) {
      var s = parseInt(panel.getAttribute('data-panel'), 10);
      var active = s === state.step;
      panel.classList.toggle('is-active', active);
      setHidden(panel, !active);
    });

    setHidden(btnPrev, state.step <= 1);
    setHidden(btnNext, state.step >= totalSteps);
    setHidden(btnBook, state.step !== totalSteps);

    if (state.step === totalSteps) {
      app.querySelector('[data-sum="service"]').textContent = state.serviceName || '—';
      app.querySelector('[data-sum="date"]').textContent = dateLabelJalali || state.date || '—';
      app.querySelector('[data-sum="time"]').textContent = state.time || '—';
      if (state.servicePrice) {
        setHidden(priceRow, false);
        app.querySelector('[data-sum="price"]').textContent =
          Number(state.servicePrice).toLocaleString('fa-IR') + ' ریال';
      } else {
        setHidden(priceRow, true);
      }
    }
  }

  function syncCardStates(grid) {
    if (!grid) return;
    grid.querySelectorAll('.booking-select-card').forEach(function (card) {
      var input = card.querySelector('.booking-select-input');
      card.classList.toggle('is-selected', !!(input && input.checked && !input.disabled));
    });
  }

  function bindRadioGrid(gridId, onChange) {
    var grid = document.getElementById(gridId);
    if (!grid) return;

    grid.addEventListener('change', function (e) {
      var input = e.target;
      if (!input || !input.classList.contains('booking-select-input')) return;
      syncCardStates(grid);
      onChange(input);
    });

    grid.addEventListener('keydown', function (e) {
      var card = e.target.closest('.booking-select-card');
      if (!card || !grid.contains(card)) return;
      if (e.key === ' ' || e.key === 'Enter') {
        var input = card.querySelector('.booking-select-input');
        if (input && !input.disabled) {
          e.preventDefault();
          input.checked = true;
          input.dispatchEvent(new Event('change', { bubbles: true }));
        }
      }
    });

    syncCardStates(grid);
  }

  bindRadioGrid('service-grid', function (input) {
    state.serviceId = parseInt(input.value, 10) || 0;
    state.serviceName = input.getAttribute('data-name') || '';
    state.servicePrice = input.getAttribute('data-price') || '';
    state.time = '';
    clearSlotSelection();
    loadSlots();
  });

  function clearSlotSelection() {
    state.time = '';
    if (slotsGrid) slotsGrid.innerHTML = '';
  }

  function loadSlots() {
    if (!slotsGrid || !slotsStatus) return;
    clearSlotSelection();

    if (!state.doctorId) {
      slotsStatus.textContent = 'در حال حاضر امکان نمایش ساعات آزاد وجود ندارد.';
      return;
    }
    if (!state.date) {
      slotsStatus.textContent = 'ابتدا تاریخ را انتخاب کنید.';
      return;
    }

    slotsStatus.textContent = 'در حال دریافت ساعات آزاد...';
    var q = new URLSearchParams({
      doctor_id: String(state.doctorId),
      date: state.date,
      service_id: String(state.serviceId || 0)
    });

    fetch(slotsUrl + '?' + q.toString(), { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        var slots = (data && data.slots) || [];
        if (!slots.length) {
          slotsStatus.textContent = 'ساعت آزادی برای این روز وجود ندارد.';
          return;
        }
        slotsStatus.textContent = 'یک ساعت را انتخاب کنید:';
        slots.forEach(function (t, idx) {
          var id = 'slot-' + String(t).replace(/[^\d]/g, '') + '-' + idx;
          var label = document.createElement('label');
          label.className = 'booking-slot-card';
          label.setAttribute('for', id);

          var input = document.createElement('input');
          input.type = 'radio';
          input.className = 'booking-slot-input';
          input.name = 'appointment_time';
          input.id = id;
          input.value = t;

          var face = document.createElement('span');
          face.className = 'booking-slot-face';
          face.textContent = t;

          label.appendChild(input);
          label.appendChild(face);
          slotsGrid.appendChild(label);
        });
      })
      .catch(function () {
        slotsStatus.textContent = 'خطا در دریافت ساعات. دوباره تلاش کنید.';
      });
  }

  if (slotsGrid) {
    slotsGrid.addEventListener('change', function (e) {
      var input = e.target;
      if (!input || !input.classList.contains('booking-slot-input')) return;
      state.time = input.value || '';
      slotsGrid.querySelectorAll('.booking-slot-card').forEach(function (card) {
        var inp = card.querySelector('.booking-slot-input');
        card.classList.toggle('is-selected', !!(inp && inp.checked));
      });
    });
  }

  if (dateInput && !jalaliPicker) {
    dateInput.addEventListener('change', function () {
      state.date = dateInput.value || '';
      loadSlots();
    });
  }

  btnPrev.addEventListener('click', function () {
    if (state.step > 1) {
      state.step -= 1;
      clearMsg();
      renderStep();
    }
  });

  btnNext.addEventListener('click', function () {
    if (state.step === 1 && !state.serviceId) {
      alert('لطفاً یک خدمت انتخاب کنید.');
      return;
    }
    if (state.step === 2 && (!state.date || !state.time)) {
      alert('تاریخ و ساعت را انتخاب کنید.');
      return;
    }
    if (state.step < totalSteps) {
      state.step += 1;
      clearMsg();
      renderStep();
    }
  });

  btnBook.addEventListener('click', function () {
    if (!loggedIn) {
      showMsg('برای ثبت نوبت ابتدا وارد شوید.', false);
      window.location.href = authUrl;
      return;
    }
    if (!state.serviceId || !state.doctorId || !state.date || !state.time) {
      showMsg('اطلاعات نوبت ناقص است.', false);
      return;
    }

    btnBook.disabled = true;
    showMsg('در حال ثبت نوبت...', true);

    var body = new FormData();
    var csrf = (window.SARVA && window.SARVA.csrf) || '';
    body.append('_csrf', csrf);
    body.append('service_id', String(state.serviceId));
    body.append('doctor_id', String(state.doctorId));
    body.append('date', state.date);
    body.append('time', state.time);

    fetch(bookUrl, {
      method: 'POST',
      body: body,
      credentials: 'same-origin',
      headers: { 'X-CSRF-TOKEN': csrf }
    })
      .then(function (r) {
        return r.json().then(function (j) { return { okHttp: r.ok, j: j }; });
      })
      .then(function (res) {
        if (res.j && res.j.ok && res.j.redirect) {
          showMsg('نوبت ثبت شد. در حال انتقال به درگاه پرداخت...', true);
          window.location.href = res.j.redirect;
          return;
        }
        showMsg((res.j && res.j.message) || 'ثبت نوبت ناموفق بود.', false);
        btnBook.disabled = false;
      })
      .catch(function () {
        showMsg('خطای شبکه. دوباره تلاش کنید.', false);
        btnBook.disabled = false;
      });
  });

  renderStep();
})();
</script>
