/**
 * SMS Center — recipient builder + 3-step composer
 */
(function () {
  'use strict';

  var root = document.getElementById('smsCenter');
  if (!root) return;

  var csrf = root.dataset.csrf || '';
  var modeLabels = window.SMS_MODE_LABELS || {};
  var state = {
    step: 1,
    mode: root.dataset.initialMode || 'manual',
    q: '',
    page: 1,
    pages: 1,
    total: 0,
    items: [],
    includeIds: {},
    excludeIds: {},
    selectAllFiltered: false,
    filters: {},
    templateId: 0,
    templateName: 'پیام سفارشی',
    message: '',
    purpose: 'operational',
    sendMode: 'now',
    scheduledAt: '',
    summary: {
      selected: 0,
      valid_mobile: 0,
      invalid_mobile: 0,
      duplicates_removed: 0,
      final_recipients: 0,
      reason: ''
    },
    previewPage: 1,
    debounceTimer: null,
    summaryTimer: null
  };

  try {
    var pre = JSON.parse(root.dataset.preselect || '[]');
    if (Array.isArray(pre)) {
      pre.forEach(function (id) {
        if (id) state.includeIds[id] = true;
      });
      if (pre.length) {
        state.mode = 'selected';
        state.selectAllFiltered = false;
      }
    }
  } catch (e) {}

  function $(sel, el) { return (el || root).querySelector(sel); }
  function $all(sel, el) { return Array.prototype.slice.call((el || root).querySelectorAll(sel)); }
  function faNum(n) {
    return String(n).replace(/\d/g, function (d) { return '۰۱۲۳۴۵۶۷۸۹'[d]; });
  }

  function postJson(url, body) {
    body = body || {};
    body._csrf = csrf;
    return fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': csrf
      },
      body: JSON.stringify(body),
      credentials: 'same-origin'
    }).then(function (r) { return r.json().then(function (j) { return { status: r.status, data: j }; }); });
  }

  function getJson(url) {
    return fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
      .then(function (r) { return r.json(); });
  }

  function collectFilters() {
    var f = { mode: state.mode, status: 'active' };
    $all('[data-filter]').forEach(function (el) {
      var key = el.getAttribute('data-filter');
      if (el.multiple) {
        f[key] = Array.prototype.map.call(el.selectedOptions, function (o) { return o.value; }).filter(Boolean);
      } else if (el.value) {
        f[key] = el.value;
      }
    });
    $all('[data-filter-bool]').forEach(function (el) {
      if (el.checked) f[el.getAttribute('data-filter-bool')] = 1;
    });
    var aud = $('#smsSavedAudience');
    if (aud && aud.value && aud.value !== '0') {
      f.audience_id = parseInt(aud.value, 10);
      if (state.mode === 'saved') f.mode = 'saved';
    }
    if (state.q) f.q = state.q;
    state.filters = f;
    return f;
  }

  function selectionPayload() {
    return {
      mode: state.mode,
      filters: collectFilters(),
      include_ids: Object.keys(state.includeIds).map(Number),
      exclude_ids: Object.keys(state.excludeIds).map(Number),
      select_all_filtered: !!state.selectAllFiltered,
      template_id: state.templateId,
      message: state.message,
      purpose: state.purpose,
      send_mode: state.sendMode,
      scheduled_at: state.scheduledAt
    };
  }

  function setMode(mode) {
    state.mode = mode;
    state.page = 1;
    if (mode !== 'manual' && mode !== 'selected' && mode !== 'search') {
      state.selectAllFiltered = true;
      state.includeIds = {};
    } else if (mode === 'manual' || mode === 'search') {
      state.selectAllFiltered = false;
    }
    $all('.sms-mode').forEach(function (btn) {
      btn.classList.toggle('is-active', btn.getAttribute('data-mode') === mode);
    });
    $all('.sms-chip').forEach(function (btn) {
      btn.classList.toggle('is-active', btn.getAttribute('data-mode') === mode);
    });
    var label = modeLabels[mode] || mode;
    var el = $('#smsActiveModeLabel');
    if (el) el.textContent = label;
    if (mode === 'saved') {
      var adv = $('#smsAdvanced');
      if (adv) adv.open = true;
    }
    loadPatients();
    refreshSummary();
  }

  function gotoStep(n) {
    n = Math.max(1, Math.min(3, n));
    if (n > 1 && state.summary.final_recipients < 1 && Object.keys(state.includeIds).length < 1 && !state.selectAllFiltered) {
      alert('ابتدا حداقل یک گیرنده انتخاب کنید.');
      return;
    }
    state.step = n;
    $all('.sms-step').forEach(function (b) {
      b.classList.toggle('is-active', parseInt(b.getAttribute('data-step'), 10) === n);
    });
    $all('.sms-panel').forEach(function (p) {
      var on = parseInt(p.getAttribute('data-panel'), 10) === n;
      p.hidden = !on;
      p.classList.toggle('is-active', on);
    });
    if (n >= 2) refreshMessagePreview();
    if (n === 3) refreshSummary(true);
  }

  function loadPatients() {
    var f = collectFilters();
    var params = new URLSearchParams();
    Object.keys(f).forEach(function (k) {
      var v = f[k];
      if (Array.isArray(v)) {
        v.forEach(function (x) { params.append(k + '[]', x); });
      } else if (v !== '' && v != null) {
        params.set(k, v);
      }
    });
    params.set('page', String(state.page));
    params.set('per_page', '25');
    var list = $('#smsPatientList');
    list.innerHTML = '<div class="sms-empty">در حال جستجو...</div>';
    getJson(root.dataset.apiPatients + '?' + params.toString()).then(function (res) {
      if (!res.ok) {
        list.innerHTML = '<div class="sms-empty">خطا در دریافت بیماران.</div>';
        return;
      }
      state.items = res.items || [];
      state.total = res.total || 0;
      state.pages = res.pages || 1;
      renderPatients();
      renderPager();
      renderBanners();
    }).catch(function () {
      list.innerHTML = '<div class="sms-empty">ارتباط با سرور برقرار نشد.</div>';
    });
  }

  function isSelected(id) {
    if (state.excludeIds[id]) return false;
    if (state.selectAllFiltered) return true;
    return !!state.includeIds[id];
  }

  function renderPatients() {
    var list = $('#smsPatientList');
    if (!state.items.length) {
      list.innerHTML = '<div class="sms-empty">هیچ بیماری پیدا نشد.</div>';
      return;
    }
    list.innerHTML = state.items.map(function (p) {
      var sel = isSelected(p.id);
      var excluded = !!state.excludeIds[p.id];
      return (
        '<div class="sms-patient' + (sel ? ' is-selected' : '') + (excluded ? ' is-excluded' : '') + '" data-id="' + p.id + '">' +
          '<div class="sms-patient__check"><input type="checkbox"' + (sel ? ' checked' : '') + (excluded ? ' disabled' : '') + '></div>' +
          '<div class="sms-patient__body">' +
            '<div class="sms-patient__name">' + escapeHtml(p.full_name) +
              (excluded ? ' <span class="sms-tag">حذف‌شده از گیرندگان</span>' : '') +
            '</div>' +
            '<div class="sms-patient__meta">' +
              '<span>پرونده: <code>' + escapeHtml(p.file_number || '—') + '</code></span>' +
              '<span dir="ltr">' + escapeHtml(p.mobile || '—') + '</span>' +
              (p.mobile_valid ? '' : ' <span class="sms-tag sms-tag--bad">موبایل نامعتبر</span>') +
            '</div>' +
            '<div class="sms-patient__meta">' +
              '<span>آخرین نوبت: ' + escapeHtml(p.last_appointment_jalali || '—') + '</span>' +
              '<span>نوبت بعدی: ' + escapeHtml(p.next_appointment_jalali || '—') + '</span>' +
              (p.doctor_name ? '<span>پزشک: ' + escapeHtml(p.doctor_name) + '</span>' : '') +
              '<span>' + escapeHtml(p.status_label || '') + '</span>' +
            '</div>' +
          '</div>' +
          '<div class="sms-patient__actions">' +
            (sel && !excluded
              ? '<button type="button" class="btn btn-sm btn-outline-danger" data-exclude="' + p.id + '">حذف از گیرندگان</button>'
              : (excluded
                  ? '<button type="button" class="btn btn-sm btn-outline-success" data-unexclude="' + p.id + '">بازگردانی</button>'
                  : '')) +
          '</div>' +
        '</div>'
      );
    }).join('');
  }

  function renderPager() {
    var pager = $('#smsPager');
    if (state.pages <= 1) {
      pager.innerHTML = '';
      return;
    }
    var html = '';
    for (var i = 1; i <= state.pages && i <= 20; i++) {
      html += '<a href="#" class="' + (i === state.page ? 'is-active' : '') + '" data-page="' + i + '">' + faNum(i) + '</a>';
    }
    if (state.pages > 20) html += '<span>... ' + faNum(state.pages) + '</span>';
    pager.innerHTML = html;
  }

  function renderBanners() {
    var allBanner = $('#smsSelectAllBanner');
    var exBanner = $('#smsExcludeBanner');
    if (state.selectAllFiltered) {
      allBanner.hidden = false;
      allBanner.textContent = 'همه نتایج فیلترشده انتخاب شده‌اند (' + faNum(state.total) + ' بیمار در این فیلتر). حذف‌های دستی از این مجموعه کم می‌شوند.';
    } else {
      allBanner.hidden = true;
    }
    var ex = Object.keys(state.excludeIds).length;
    if (ex) {
      exBanner.hidden = false;
      exBanner.textContent = faNum(ex) + ' بیمار از گیرندگان حذف شده‌اند (از پایگاه داده حذف نمی‌شوند).';
    } else {
      exBanner.hidden = true;
    }
  }

  function togglePatient(id) {
    id = parseInt(id, 10);
    if (!id) return;
    if (state.excludeIds[id]) {
      delete state.excludeIds[id];
      refreshSummary();
      renderPatients();
      renderBanners();
      return;
    }
    if (state.selectAllFiltered) {
      state.excludeIds[id] = true;
    } else if (state.includeIds[id]) {
      delete state.includeIds[id];
    } else {
      state.includeIds[id] = true;
    }
    refreshSummary();
    renderPatients();
    renderBanners();
  }

  function refreshSummary(forceConfirm) {
    clearTimeout(state.summaryTimer);
    state.summaryTimer = setTimeout(function () {
      postJson(root.dataset.apiSummary, selectionPayload()).then(function (res) {
        if (!res.data || !res.data.ok) return;
        state.summary = res.data.summary;
        paintSummary();
        if (forceConfirm) paintConfirm();
      });
    }, 280);
  }

  function paintSummary() {
    var s = state.summary;
    $('#smsLiveCount').textContent = faNum(s.final_recipients || s.selected || 0);
    $('#smsStatValid').textContent = faNum(s.valid_mobile || 0);
    $('#smsStatInvalid').textContent = faNum(s.invalid_mobile || 0);
    $('#smsStatDup').textContent = faNum(s.duplicates_removed || 0);
    $('#smsStatFinal').textContent = faNum(s.final_recipients || 0);
    if (s.reason) $('#smsActiveModeLabel').textContent = s.reason;
  }

  function paintConfirm() {
    var s = state.summary;
    $all('#smsConfirmBox [data-k]').forEach(function (el) {
      el.textContent = faNum(s[el.getAttribute('data-k')] || 0);
    });
    $('#smsConfirmSendMode').textContent = state.sendMode === 'scheduled' ? 'زمان‌بندی‌شده' : 'فوری';
    $('#smsConfirmWhen').textContent = state.sendMode === 'scheduled' ? (state.scheduledAt || '—') : 'همین حالا';
    $('#smsConfirmTpl').textContent = state.templateName || 'سفارشی';
    $('#smsSideWhen').textContent = state.sendMode === 'scheduled' ? (state.scheduledAt || 'زمان‌بندی') : 'همین حالا';
  }

  function refreshMessagePreview() {
    var payload = selectionPayload();
    payload.message = state.message;
    payload.template_id = state.templateId;
    postJson(root.dataset.apiMessage, payload).then(function (res) {
      var d = res.data;
      if (!d || !d.ok) return;
      $('#smsRendered').textContent = d.rendered || '';
      $('#smsSampleName').textContent = d.sample_name || '—';
      $('#smsCharCount').textContent = faNum(d.chars || 0) + ' کاراکتر';
      $('#smsSegCount').textContent = faNum(d.segments || 0) + ' بخش (تخمینی)';
      $('#smsSideLen').textContent = faNum(d.chars || 0);
      $('#smsSideSeg').textContent = faNum(d.segments || 0);
      $('#smsConfirmSeg').textContent = faNum(d.segments || 0);
      $('#smsSideTpl').textContent = d.template || state.templateName;
    });
  }

  function escapeHtml(str) {
    return String(str == null ? '' : str)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }

  function openPreview() {
    state.previewPage = 1;
    $('#smsRecipientsModal').hidden = false;
    loadPreview();
  }

  function loadPreview() {
    var payload = selectionPayload();
    payload.page = state.previewPage;
    payload.q = ($('#smsPreviewSearch') || {}).value || '';
    postJson(root.dataset.apiPreview, payload).then(function (res) {
      var d = res.data;
      if (!d || !d.ok) return;
      var box = $('#smsPreviewList');
      if (!d.items.length) {
        box.innerHTML = '<div class="sms-empty">گیرنده‌ای برای نمایش نیست.</div>';
      } else {
        box.innerHTML = d.items.map(function (p) {
          return '<div class="sms-preview-row">' +
            '<div><strong>' + escapeHtml(p.full_name) + '</strong><div class="sms-patient__meta">' +
            '<span>' + escapeHtml(p.file_number) + '</span><span dir="ltr">' + escapeHtml(p.mobile) + '</span>' +
            '<span>' + escapeHtml(p.reason || '') + '</span></div></div>' +
            '<button type="button" class="btn btn-sm btn-outline-danger" data-exclude="' + p.id + '">حذف</button></div>';
        }).join('');
      }
      var pager = $('#smsPreviewPager');
      pager.innerHTML = '';
      for (var i = 1; i <= (d.pages || 1) && i <= 15; i++) {
        pager.innerHTML += '<a href="#" class="' + (i === state.previewPage ? 'is-active' : '') + '" data-preview-page="' + i + '">' + faNum(i) + '</a>';
      }
      if (d.summary) {
        state.summary = d.summary;
        paintSummary();
      }
    });
  }

  // Events
  $all('.sms-mode, .sms-chip').forEach(function (btn) {
    btn.addEventListener('click', function () {
      setMode(btn.getAttribute('data-mode'));
    });
  });

  $all('.sms-step').forEach(function (btn) {
    btn.addEventListener('click', function () {
      gotoStep(parseInt(btn.getAttribute('data-step'), 10));
    });
  });

  $all('[data-goto]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      gotoStep(parseInt(btn.getAttribute('data-goto'), 10));
    });
  });

  var search = $('#smsPatientSearch');
  if (search) {
    search.addEventListener('input', function () {
      clearTimeout(state.debounceTimer);
      state.debounceTimer = setTimeout(function () {
        state.q = search.value.trim();
        state.page = 1;
        if (state.mode === 'manual' || state.mode === 'selected') {
          state.mode = state.q ? 'search' : 'manual';
        }
        loadPatients();
        refreshSummary();
      }, 320);
    });
  }

  $('#smsApplyFilters') && $('#smsApplyFilters').addEventListener('click', function () {
    if (state.mode === 'manual' || state.mode === 'search' || state.mode === 'selected') {
      state.mode = 'filtered';
      $all('.sms-mode').forEach(function (b) {
        b.classList.toggle('is-active', b.getAttribute('data-mode') === 'filtered');
      });
    }
    state.page = 1;
    state.selectAllFiltered = true;
    state.includeIds = {};
    loadPatients();
    refreshSummary();
  });

  $('#smsSelectPage') && $('#smsSelectPage').addEventListener('click', function () {
    state.selectAllFiltered = false;
    state.items.forEach(function (p) {
      if (!state.excludeIds[p.id]) state.includeIds[p.id] = true;
    });
    renderPatients();
    renderBanners();
    refreshSummary();
  });

  $('#smsSelectAllFiltered') && $('#smsSelectAllFiltered').addEventListener('click', function () {
    state.selectAllFiltered = true;
    state.includeIds = {};
    renderPatients();
    renderBanners();
    refreshSummary();
  });

  $('#smsClearSelection') && $('#smsClearSelection').addEventListener('click', function () {
    state.selectAllFiltered = false;
    state.includeIds = {};
    state.excludeIds = {};
    renderPatients();
    renderBanners();
    refreshSummary();
  });

  $('#smsClearExclusions') && $('#smsClearExclusions').addEventListener('click', function () {
    state.excludeIds = {};
    renderPatients();
    renderBanners();
    refreshSummary();
  });

  $('#smsPatientList').addEventListener('click', function (e) {
    var ex = e.target.closest('[data-exclude]');
    if (ex) {
      e.preventDefault();
      e.stopPropagation();
      state.excludeIds[parseInt(ex.getAttribute('data-exclude'), 10)] = true;
      delete state.includeIds[parseInt(ex.getAttribute('data-exclude'), 10)];
      renderPatients();
      renderBanners();
      refreshSummary();
      return;
    }
    var un = e.target.closest('[data-unexclude]');
    if (un) {
      e.preventDefault();
      delete state.excludeIds[parseInt(un.getAttribute('data-unexclude'), 10)];
      renderPatients();
      renderBanners();
      refreshSummary();
      return;
    }
    var row = e.target.closest('.sms-patient');
    if (row) togglePatient(row.getAttribute('data-id'));
  });

  $('#smsPager').addEventListener('click', function (e) {
    var a = e.target.closest('[data-page]');
    if (!a) return;
    e.preventDefault();
    state.page = parseInt(a.getAttribute('data-page'), 10);
    loadPatients();
  });

  // Templates
  $all('.sms-tpl').forEach(function (btn) {
    btn.addEventListener('click', function () {
      $all('.sms-tpl').forEach(function (b) { b.classList.remove('is-active'); });
      btn.classList.add('is-active');
      state.templateId = parseInt(btn.getAttribute('data-id'), 10) || 0;
      state.templateName = btn.getAttribute('data-name') || (state.templateId ? btn.querySelector('strong').textContent : 'پیام سفارشی');
      var body = btn.getAttribute('data-body') || '';
      if (state.templateId > 0) {
        $('#smsMessage').value = body;
        state.message = body;
      }
      $('#smsSideTpl').textContent = state.templateName;
      refreshMessagePreview();
    });
  });

  $('#smsTplSearch') && $('#smsTplSearch').addEventListener('input', function () {
    var q = this.value.trim().toLowerCase();
    $all('.sms-tpl').forEach(function (btn) {
      var text = (btn.textContent || '').toLowerCase();
      btn.hidden = q !== '' && text.indexOf(q) === -1 && btn.getAttribute('data-id') !== '0';
    });
  });

  $('#smsMessage') && $('#smsMessage').addEventListener('input', function () {
    state.message = this.value;
    var len = this.value.length;
    $('#smsCharCount').textContent = faNum(len) + ' کاراکتر';
    var segs = len === 0 ? 0 : Math.max(1, Math.ceil(len / 70));
    $('#smsSegCount').textContent = faNum(segs) + ' بخش (تخمینی)';
    clearTimeout(state.debounceTimer);
    state.debounceTimer = setTimeout(refreshMessagePreview, 350);
  });

  $all('.sms-var').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var ta = $('#smsMessage');
      var v = btn.getAttribute('data-var');
      var start = ta.selectionStart || ta.value.length;
      ta.value = ta.value.slice(0, start) + v + ta.value.slice(start);
      state.message = ta.value;
      ta.focus();
      refreshMessagePreview();
    });
  });

  $('#smsPurpose') && $('#smsPurpose').addEventListener('change', function () {
    state.purpose = this.value;
  });

  $all('input[name="send_mode"]').forEach(function (r) {
    r.addEventListener('change', function () {
      state.sendMode = this.value;
      $('#smsScheduleWrap').hidden = state.sendMode !== 'scheduled';
      $all('.sms-send-mode').forEach(function (l) {
        l.classList.toggle('is-active', l.querySelector('input').checked);
      });
      paintConfirm();
    });
  });

  $('#smsScheduledAt') && $('#smsScheduledAt').addEventListener('change', function () {
    state.scheduledAt = this.value;
    paintConfirm();
  });

  $all('[data-sched-preset]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var d = new Date();
      if (btn.getAttribute('data-sched-preset') === 'tomorrow') d.setDate(d.getDate() + 1);
      d.setHours(10, 0, 0, 0);
      var iso = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0') + 'T10:00';
      var input = $('#smsScheduledAt');
      input.value = iso;
      if (input._jalaliApplySubmit) { /* keep gregorian in value for submit */ }
      state.scheduledAt = iso;
      // Trigger jalali display if bound
      input.dispatchEvent(new Event('change', { bubbles: true }));
      if (window.SarvaJalali && typeof window.SarvaJalali.bindJalaliInput === 'function') {
        // already bound via autoInit; sync by setting value as gregorian
      }
      paintConfirm();
    });
  });

  $('#smsConfirmCheck') && $('#smsConfirmCheck').addEventListener('change', function () {
    $('#smsConfirmSend').disabled = !this.checked || (state.summary.final_recipients || 0) < 1;
  });

  $('#smsConfirmSend') && $('#smsConfirmSend').addEventListener('click', function () {
    var err = $('#smsSendError');
    err.hidden = true;
    if (!$('#smsConfirmCheck').checked) return;
    // Ensure jalali datetime converted
    var sched = $('#smsScheduledAt');
    if (sched && typeof sched._jalaliApplySubmit === 'function') sched._jalaliApplySubmit();
    state.scheduledAt = sched ? sched.value : state.scheduledAt;

    var payload = selectionPayload();
    payload.confirm = 1;
    payload.expected_count = state.summary.final_recipients || 0;
    payload.message = state.message;
    payload.template_id = state.templateId;
    payload.title = (modeLabels[state.mode] || 'ارسال گروهی');

    var btn = $('#smsConfirmSend');
    btn.disabled = true;
    btn.textContent = 'در حال ثبت در صف...';
    postJson(root.dataset.apiSend, payload).then(function (res) {
      if (!res.data || !res.data.ok) {
        err.hidden = false;
        err.textContent = (res.data && res.data.message) || 'ارسال ناموفق بود.';
        btn.disabled = false;
        btn.textContent = 'تأیید و ارسال';
        return;
      }
      window.location.href = root.dataset.batchBase + '/' + res.data.batch_id;
    }).catch(function () {
      err.hidden = false;
      err.textContent = 'خطای ارتباط با سرور.';
      btn.disabled = false;
      btn.textContent = 'تأیید و ارسال';
    });
  });

  $('#smsTestSend') && $('#smsTestSend').addEventListener('click', function () {
    var mobile = ($('#smsTestMobile').value || '').trim();
    if (!mobile) {
      alert('شماره آزمایشی را وارد کنید.');
      return;
    }
    var payload = selectionPayload();
    payload.test_mobile = mobile;
    payload.message = state.message;
    payload.template_id = state.templateId;
    postJson(root.dataset.apiTest, payload).then(function (res) {
      alert((res.data && res.data.message) || 'انجام شد');
    });
  });

  $('#smsOpenPreview') && $('#smsOpenPreview').addEventListener('click', openPreview);
  $('#smsClosePreview') && $('#smsClosePreview').addEventListener('click', function () {
    $('#smsRecipientsModal').hidden = true;
  });
  $('#smsClosePreview2') && $('#smsClosePreview2').addEventListener('click', function () {
    $('#smsRecipientsModal').hidden = true;
  });
  $('#smsPreviewSearch') && $('#smsPreviewSearch').addEventListener('input', function () {
    clearTimeout(state.debounceTimer);
    state.debounceTimer = setTimeout(function () {
      state.previewPage = 1;
      loadPreview();
    }, 300);
  });
  $('#smsPreviewList') && $('#smsPreviewList').addEventListener('click', function (e) {
    var ex = e.target.closest('[data-exclude]');
    if (!ex) return;
    state.excludeIds[parseInt(ex.getAttribute('data-exclude'), 10)] = true;
    delete state.includeIds[parseInt(ex.getAttribute('data-exclude'), 10)];
    loadPreview();
    renderPatients();
    renderBanners();
    refreshSummary();
  });
  $('#smsPreviewPager') && $('#smsPreviewPager').addEventListener('click', function (e) {
    var a = e.target.closest('[data-preview-page]');
    if (!a) return;
    e.preventDefault();
    state.previewPage = parseInt(a.getAttribute('data-preview-page'), 10);
    loadPreview();
  });

  $('#smsSaveAudience') && $('#smsSaveAudience').addEventListener('click', function () {
    var name = prompt('نام گروه ذخیره‌شده:');
    if (!name) return;
    postJson(root.dataset.apiAudiencesSave, {
      name: name,
      filters: collectFilters(),
      mode: state.mode
    }).then(function (res) {
      alert((res.data && res.data.ok) ? 'گروه ذخیره شد.' : ((res.data && res.data.message) || 'ذخیره ناموفق'));
      if (res.data && res.data.ok) location.reload();
    });
  });

  $('#smsSideToggle') && $('#smsSideToggle').addEventListener('click', function () {
    $('#smsSideSummary').classList.toggle('is-open');
  });

  // Init
  var embedAudience = root.getAttribute('data-embed') === 'audience';
  if (embedAudience) {
    // Audience-only embed (automation rules): no multi-step composer.
    $all('.sms-panel').forEach(function (p) {
      var on = p.getAttribute('data-panel') === '1';
      p.hidden = !on;
      p.classList.toggle('is-active', on);
    });
    $all('.sms-step, [data-goto]').forEach(function (el) {
      if (el.closest && el.closest('[data-panel="1"]') && el.hasAttribute('data-goto')) {
        el.style.display = 'none';
      }
    });
  }

  window.SmsAudienceBuilder = {
    getSelection: function () {
      return {
        mode: state.mode,
        filters: collectFilters(),
        include_ids: Object.keys(state.includeIds).map(Number),
        exclude_ids: Object.keys(state.excludeIds).map(Number),
        select_all_filtered: !!state.selectAllFiltered
      };
    },
    setSelection: function (payload) {
      payload = payload || {};
      state.mode = payload.mode || 'all';
      state.includeIds = {};
      state.excludeIds = {};
      (payload.include_ids || []).forEach(function (id) { if (id) state.includeIds[id] = true; });
      (payload.exclude_ids || []).forEach(function (id) { if (id) state.excludeIds[id] = true; });
      state.selectAllFiltered = payload.select_all_filtered != null
        ? !!payload.select_all_filtered
        : (state.mode !== 'manual' && state.mode !== 'selected' && state.mode !== 'search');
      var filters = payload.filters || {};
      $all('[data-filter]').forEach(function (el) {
        var key = el.getAttribute('data-filter');
        var val = filters[key];
        if (el.multiple) {
          var set = {};
          (Array.isArray(val) ? val : []).forEach(function (v) { set[String(v)] = true; });
          Array.prototype.forEach.call(el.options, function (o) { o.selected = !!set[o.value]; });
        } else {
          el.value = val != null ? String(val) : '';
        }
      });
      $all('[data-filter-bool]').forEach(function (el) {
        el.checked = !!filters[el.getAttribute('data-filter-bool')];
      });
      if (filters.audience_id) {
        var aud = $('#smsSavedAudience');
        if (aud) aud.value = String(filters.audience_id);
      }
      setMode(state.mode);
    }
  };

  setMode(state.mode);
  if (Object.keys(state.includeIds).length) {
    state.selectAllFiltered = false;
    loadPatients();
    refreshSummary();
  }

  try {
    var boot = root.dataset.audienceBoot || '';
    if (boot) {
      window.SmsAudienceBuilder.setSelection(JSON.parse(boot));
    }
  } catch (e) {}
})();
