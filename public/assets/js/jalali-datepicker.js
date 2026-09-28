/**
 * Jalali calendar helpers + booking calendar + form input pickers.
 * Storage/API values stay Gregorian (YYYY-MM-DD / YYYY-MM-DD HH:MM:SS).
 */
(function (global) {
  'use strict';

  var MONTHS = [
    'فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور',
    'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'
  ];
  var WEEKDAYS = ['ش', 'ی', 'د', 'س', 'چ', 'پ', 'ج'];

  function toFaDigits(value) {
    return String(value).replace(/\d/g, function (d) {
      return '۰۱۲۳۴۵۶۷۸۹'[d];
    });
  }

  function toEnDigits(value) {
    return String(value)
      .replace(/[۰-۹]/g, function (d) { return '۰۱۲۳۴۵۶۷۸۹'.indexOf(d); })
      .replace(/[٠-٩]/g, function (d) { return '٠١٢٣٤٥٦٧٨٩'.indexOf(d); });
  }

  function pad(n) {
    return n < 10 ? '0' + n : String(n);
  }

  function isJalaliLeap(jy) {
    var breaks = [1, 5, 9, 13, 17, 22, 26, 30];
    var mod = ((jy - (jy > 0 ? 474 : 473)) % 2820) + 474;
    return breaks.indexOf(mod % 33) !== -1;
  }

  function jalaliMonthLength(jy, jm) {
    if (jm <= 6) return 31;
    if (jm <= 11) return 30;
    return isJalaliLeap(jy) ? 30 : 29;
  }

  function gregorianToJalali(gy, gm, gd) {
    var g_d_m = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
    var gy2 = gm > 2 ? gy + 1 : gy;
    var days = 355666 + 365 * gy + Math.floor((gy2 + 3) / 4) - Math.floor((gy2 + 99) / 100)
      + Math.floor((gy2 + 399) / 400) + gd + g_d_m[gm - 1];
    var jy = -1595 + 33 * Math.floor(days / 12053);
    days %= 12053;
    jy += 4 * Math.floor(days / 1461);
    days %= 1461;
    if (days > 365) {
      jy += Math.floor((days - 1) / 365);
      days = (days - 1) % 365;
    }
    var jm;
    var jd;
    if (days < 186) {
      jm = 1 + Math.floor(days / 31);
      jd = 1 + (days % 31);
    } else {
      jm = 7 + Math.floor((days - 186) / 30);
      jd = 1 + ((days - 186) % 30);
    }
    return [jy, jm, jd];
  }

  function jalaliToGregorian(jy, jm, jd) {
    jy += 1595;
    var days = -355668 + 365 * jy + Math.floor(jy / 33) * 8 + Math.floor(((jy % 33) + 3) / 4)
      + jd + (jm < 7 ? (jm - 1) * 31 : (jm - 7) * 30 + 186);
    var gy = 400 * Math.floor(days / 146097);
    days %= 146097;
    if (days > 36524) {
      gy += 100 * Math.floor(--days / 36524);
      days %= 36524;
      if (days >= 365) days++;
    }
    gy += 4 * Math.floor(days / 1461);
    days %= 1461;
    if (days > 365) {
      gy += Math.floor((days - 1) / 365);
      days = (days - 1) % 365;
    }
    var gd = days + 1;
    var sal_a = [0, 31, ((gy % 4 === 0 && gy % 100 !== 0) || gy % 400 === 0) ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
    var gm = 1;
    while (gm <= 12 && gd > sal_a[gm]) {
      gd -= sal_a[gm];
      gm++;
    }
    return [gy, gm, gd];
  }

  function toGregorianIso(jy, jm, jd) {
    var g = jalaliToGregorian(jy, jm, jd);
    return g[0] + '-' + pad(g[1]) + '-' + pad(g[2]);
  }

  function formatJalali(jy, jm, jd) {
    return toFaDigits(jy + '/' + pad(jm) + '/' + pad(jd));
  }

  function formatJalaliFromIso(iso) {
    if (!iso || !/^\d{4}-\d{2}-\d{2}/.test(iso)) return '';
    var datePart = iso.slice(0, 10);
    var parts = datePart.split('-').map(Number);
    var j = gregorianToJalali(parts[0], parts[1], parts[2]);
    return formatJalali(j[0], j[1], j[2]);
  }

  function todayJalali() {
    var now = new Date();
    return gregorianToJalali(now.getFullYear(), now.getMonth() + 1, now.getDate());
  }

  function dayStartMs(gy, gm, gd) {
    return new Date(gy, gm - 1, gd).setHours(0, 0, 0, 0);
  }

  function parseGregorianValue(raw) {
    raw = toEnDigits(String(raw || '')).trim();
    if (!raw) return { date: '', time: '' };
    var m = raw.match(/^(\d{4}-\d{2}-\d{2})(?:[T\s](\d{2}):(\d{2})(?::\d{2})?)?/);
    if (m) {
      return { date: m[1], time: m[2] ? (m[2] + ':' + m[3]) : '' };
    }
    return { date: '', time: '' };
  }

  function parseJalaliDisplay(raw) {
    raw = toEnDigits(String(raw || '')).trim();
    var m = raw.match(/^(\d{4})[\/\-](\d{1,2})[\/\-](\d{1,2})(?:\s+(\d{1,2}):(\d{2}))?/);
    if (!m) return null;
    var jy = parseInt(m[1], 10);
    var jm = parseInt(m[2], 10);
    var jd = parseInt(m[3], 10);
    if (jm < 1 || jm > 12 || jd < 1 || jd > jalaliMonthLength(jy, jm)) return null;
    var iso = toGregorianIso(jy, jm, jd);
    var time = '';
    if (m[4] != null) {
      time = pad(parseInt(m[4], 10)) + ':' + pad(parseInt(m[5], 10));
    }
    return { date: iso, time: time };
  }

  /**
   * @param {HTMLElement} root
   * @param {{ minIso?: string|null, maxIso?: string|null, selectedIso?: string, showFooter?: boolean, onSelect?: Function }} options
   */
  function mountJalaliCalendar(root, options) {
    options = options || {};
    var today = todayJalali();
    var viewY = today[0];
    var viewM = today[1];
    var selectedIso = options.selectedIso || '';
    var minMs = null;
    var maxMs = null;
    var showFooter = options.showFooter !== false;

    if (options.minIso && /^\d{4}-\d{2}-\d{2}/.test(options.minIso)) {
      var mp = options.minIso.slice(0, 10).split('-').map(Number);
      minMs = dayStartMs(mp[0], mp[1], mp[2]);
    }
    if (options.maxIso && /^\d{4}-\d{2}-\d{2}/.test(options.maxIso)) {
      var xp = options.maxIso.slice(0, 10).split('-').map(Number);
      maxMs = dayStartMs(xp[0], xp[1], xp[2]);
    }

    if (selectedIso && /^\d{4}-\d{2}-\d{2}/.test(selectedIso)) {
      var sp = selectedIso.slice(0, 10).split('-').map(Number);
      var sj = gregorianToJalali(sp[0], sp[1], sp[2]);
      viewY = sj[0];
      viewM = sj[1];
    }

    root.classList.add('jalali-calendar');
    root.innerHTML =
      '<div class="jalali-calendar-header">' +
        '<button type="button" class="jalali-cal-nav" data-dir="-1" aria-label="ماه قبل">‹</button>' +
        '<div class="jalali-cal-title"></div>' +
        '<button type="button" class="jalali-cal-nav" data-dir="1" aria-label="ماه بعد">›</button>' +
      '</div>' +
      '<div class="jalali-cal-weekdays"></div>' +
      '<div class="jalali-cal-days" role="grid" aria-label="تقویم شمسی"></div>' +
      (showFooter ? '<div class="jalali-cal-selected" aria-live="polite"></div>' : '');

    var titleEl = root.querySelector('.jalali-cal-title');
    var weekEl = root.querySelector('.jalali-cal-weekdays');
    var daysEl = root.querySelector('.jalali-cal-days');
    var selectedEl = root.querySelector('.jalali-cal-selected');

    weekEl.innerHTML = WEEKDAYS.map(function (d) {
      return '<span>' + d + '</span>';
    }).join('');

    function shiftMonth(delta) {
      viewM += delta;
      if (viewM > 12) {
        viewM = 1;
        viewY++;
      } else if (viewM < 1) {
        viewM = 12;
        viewY--;
      }
      render();
    }

    function render() {
      titleEl.textContent = MONTHS[viewM - 1] + ' ' + toFaDigits(viewY);
      var gFirst = jalaliToGregorian(viewY, viewM, 1);
      var firstDow = new Date(gFirst[0], gFirst[1] - 1, gFirst[2]).getDay();
      var offset = (firstDow + 1) % 7;
      var len = jalaliMonthLength(viewY, viewM);
      var html = '';
      var i;
      for (i = 0; i < offset; i++) {
        html += '<span class="jalali-cal-day is-empty"></span>';
      }
      for (i = 1; i <= len; i++) {
        var iso = toGregorianIso(viewY, viewM, i);
        var g = jalaliToGregorian(viewY, viewM, i);
        var ms = dayStartMs(g[0], g[1], g[2]);
        var disabled = (minMs !== null && ms < minMs) || (maxMs !== null && ms > maxMs);
        var isToday = viewY === today[0] && viewM === today[1] && i === today[2];
        var isSelected = selectedIso && iso === selectedIso.slice(0, 10);
        var cls = 'jalali-cal-day';
        if (disabled) cls += ' is-disabled';
        if (isToday) cls += ' is-today';
        if (isSelected) cls += ' is-selected';
        html += '<button type="button" class="' + cls + '" data-iso="' + iso + '"' +
          (disabled ? ' disabled' : '') + '>' + toFaDigits(i) + '</button>';
      }
      daysEl.innerHTML = html;
      if (selectedEl) {
        selectedEl.textContent = selectedIso
          ? ('انتخاب‌شده: ' + formatJalaliFromIso(selectedIso))
          : 'روزی را انتخاب کنید';
      }
    }

    root.querySelectorAll('.jalali-cal-nav').forEach(function (btn) {
      btn.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        shiftMonth(parseInt(btn.getAttribute('data-dir'), 10));
      });
    });

    daysEl.addEventListener('click', function (e) {
      var btn = e.target.closest('.jalali-cal-day');
      if (!btn || btn.disabled || btn.classList.contains('is-empty')) return;
      e.preventDefault();
      e.stopPropagation();
      selectedIso = btn.getAttribute('data-iso') || '';
      render();
      if (typeof options.onSelect === 'function') {
        options.onSelect(selectedIso, formatJalaliFromIso(selectedIso));
      }
    });

    render();

    return {
      getValue: function () { return selectedIso; },
      setValue: function (iso) {
        selectedIso = iso || '';
        if (selectedIso && /^\d{4}-\d{2}-\d{2}/.test(selectedIso)) {
          var p = selectedIso.slice(0, 10).split('-').map(Number);
          var j = gregorianToJalali(p[0], p[1], p[2]);
          viewY = j[0];
          viewM = j[1];
        }
        render();
      },
      format: formatJalaliFromIso
    };
  }

  var openPopup = null;

  function closeOpenPopup() {
    if (openPopup && openPopup.parentNode) {
      openPopup.parentNode.removeChild(openPopup);
    }
    openPopup = null;
    document.removeEventListener('click', onDocClick, true);
    document.removeEventListener('keydown', onEsc, true);
  }

  function onDocClick(e) {
    if (!openPopup) return;
    if (openPopup.contains(e.target)) return;
    if (openPopup._anchor && openPopup._anchor.contains(e.target)) return;
    closeOpenPopup();
  }

  function onEsc(e) {
    if (e.key === 'Escape') closeOpenPopup();
  }

  function positionPopup(popup, anchor) {
    var rect = anchor.getBoundingClientRect();
    var top = rect.bottom + window.scrollY + 6;
    var left = rect.left + window.scrollX;
    popup.style.top = top + 'px';
    popup.style.left = left + 'px';
    popup.style.minWidth = Math.max(280, rect.width) + 'px';
    requestAnimationFrame(function () {
      var pr = popup.getBoundingClientRect();
      if (pr.right > window.innerWidth - 8) {
        popup.style.left = Math.max(8, window.scrollX + window.innerWidth - pr.width - 8) + 'px';
      }
      if (pr.bottom > window.innerHeight - 8) {
        popup.style.top = Math.max(8, rect.top + window.scrollY - pr.height - 6) + 'px';
      }
    });
  }

  /**
   * Bind a text input as Jalali date or datetime picker.
   * Initial value / submitted value: Gregorian.
   */
  function bindJalaliInput(input, mode) {
    if (!input || input.dataset.jalaliBound === '1') return;
    mode = mode || input.getAttribute('data-jalali') || 'date';
    input.dataset.jalaliBound = '1';
    input.setAttribute('autocomplete', 'off');
    input.setAttribute('inputmode', 'numeric');
    input.classList.add('jalali-input');
    if (input.type === 'date' || input.type === 'datetime-local') {
      input.type = 'text';
    }

    var parsed = parseGregorianValue(input.value || input.getAttribute('value') || '');
    var isoDate = parsed.date;
    var timePart = parsed.time || (mode === 'datetime' ? '00:00' : '');

    function syncDisplay() {
      if (!isoDate) {
        input.value = '';
        input.dataset.iso = '';
        return;
      }
      var label = formatJalaliFromIso(isoDate);
      if (mode === 'datetime') {
        label += ' ' + toFaDigits(timePart || '00:00');
      }
      input.value = label;
      input.dataset.iso = mode === 'datetime'
        ? (isoDate + 'T' + (timePart || '00:00'))
        : isoDate;
    }

    function applyToSubmitValue() {
      if (!isoDate) {
        input.value = '';
        return;
      }
      if (mode === 'datetime') {
        input.value = isoDate + 'T' + (timePart || '00:00');
      } else {
        input.value = isoDate;
      }
    }

    syncDisplay();

    function openPicker() {
      closeOpenPopup();
      var popup = document.createElement('div');
      popup.className = 'jalali-popup';
      popup._anchor = input;

      var calWrap = document.createElement('div');
      popup.appendChild(calWrap);

      var timeRow = null;
      var hourSel = null;
      var minSel = null;
      if (mode === 'datetime') {
        timeRow = document.createElement('div');
        timeRow.className = 'jalali-popup-time';
        timeRow.innerHTML =
          '<label>ساعت</label>' +
          '<select class="jalali-hour"></select>' +
          '<span>:</span>' +
          '<select class="jalali-minute"></select>';
        hourSel = timeRow.querySelector('.jalali-hour');
        minSel = timeRow.querySelector('.jalali-minute');
        var h;
        for (h = 0; h < 24; h++) {
          var ho = document.createElement('option');
          ho.value = pad(h);
          ho.textContent = toFaDigits(pad(h));
          hourSel.appendChild(ho);
        }
        var m;
        for (m = 0; m < 60; m += 5) {
          var mo = document.createElement('option');
          mo.value = pad(m);
          mo.textContent = toFaDigits(pad(m));
          minSel.appendChild(mo);
        }
        var tp = (timePart || '00:00').split(':');
        hourSel.value = pad(parseInt(tp[0], 10) || 0);
        var mins = parseInt(tp[1], 10) || 0;
        mins = Math.round(mins / 5) * 5;
        if (mins >= 60) mins = 55;
        minSel.value = pad(mins);
        popup.appendChild(timeRow);
      }

      var actions = document.createElement('div');
      actions.className = 'jalali-popup-actions';
      actions.innerHTML =
        '<button type="button" class="jalali-popup-clear">پاک کردن</button>' +
        '<button type="button" class="jalali-popup-today">امروز</button>' +
        (mode === 'datetime' ? '<button type="button" class="jalali-popup-ok">تأیید</button>' : '');
      popup.appendChild(actions);

      document.body.appendChild(popup);
      openPopup = popup;
      positionPopup(popup, input);

      var minIso = input.getAttribute('data-min') || null;
      var maxIso = input.getAttribute('data-max') || null;

      function commit(iso, close) {
        if (!iso) {
          isoDate = '';
          timePart = mode === 'datetime' ? '00:00' : '';
        } else {
          isoDate = iso.slice(0, 10);
          if (mode === 'datetime' && hourSel && minSel) {
            timePart = hourSel.value + ':' + minSel.value;
          }
        }
        syncDisplay();
        input.dispatchEvent(new Event('change', { bubbles: true }));
        if (close !== false) closeOpenPopup();
      }

      mountJalaliCalendar(calWrap, {
        minIso: minIso,
        maxIso: maxIso,
        selectedIso: isoDate,
        showFooter: false,
        onSelect: function (iso) {
          if (mode === 'date') {
            commit(iso, true);
          } else {
            isoDate = iso;
            syncDisplay();
          }
        }
      });

      actions.querySelector('.jalali-popup-clear').addEventListener('click', function (e) {
        e.preventDefault();
        commit('', true);
      });
      actions.querySelector('.jalali-popup-today').addEventListener('click', function (e) {
        e.preventDefault();
        var t = new Date();
        var iso = t.getFullYear() + '-' + pad(t.getMonth() + 1) + '-' + pad(t.getDate());
        if (mode === 'datetime' && hourSel && minSel) {
          hourSel.value = pad(t.getHours());
          var nearest = Math.round(t.getMinutes() / 5) * 5;
          if (nearest >= 60) nearest = 55;
          minSel.value = pad(nearest);
        }
        commit(iso, mode === 'date');
      });
      var okBtn = actions.querySelector('.jalali-popup-ok');
      if (okBtn) {
        okBtn.addEventListener('click', function (e) {
          e.preventDefault();
          if (!isoDate) {
            var t = new Date();
            isoDate = t.getFullYear() + '-' + pad(t.getMonth() + 1) + '-' + pad(t.getDate());
          }
          commit(isoDate, true);
        });
      }

      setTimeout(function () {
        document.addEventListener('click', onDocClick, true);
        document.addEventListener('keydown', onEsc, true);
      }, 0);
    }

    input.addEventListener('focus', function () {
      openPicker();
    });
    input.addEventListener('click', function () {
      if (!openPopup) openPicker();
    });

    input.addEventListener('blur', function () {
      // Allow typed Jalali values
      setTimeout(function () {
        if (openPopup) return;
        var typed = parseJalaliDisplay(input.value);
        if (typed) {
          isoDate = typed.date;
          if (mode === 'datetime') {
            timePart = typed.time || timePart || '00:00';
          }
          syncDisplay();
        } else if (!String(input.value || '').trim()) {
          isoDate = '';
          syncDisplay();
        } else {
          syncDisplay();
        }
      }, 150);
    });

    var form = input.closest('form');
    if (form && !form.dataset.jalaliSubmitBound) {
      form.dataset.jalaliSubmitBound = '1';
      form.addEventListener('submit', function () {
        form.querySelectorAll('[data-jalali]').forEach(function (el) {
          if (typeof el._jalaliApplySubmit === 'function') {
            el._jalaliApplySubmit();
          }
        });
      });
    }
    input._jalaliApplySubmit = applyToSubmitValue;
  }

  function autoInit(root) {
    root = root || document;
    root.querySelectorAll('[data-jalali]').forEach(function (el) {
      bindJalaliInput(el, el.getAttribute('data-jalali'));
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () { autoInit(); });
  } else {
    autoInit();
  }

  global.SarvaJalali = {
    gregorianToJalali: gregorianToJalali,
    jalaliToGregorian: jalaliToGregorian,
    formatJalaliFromIso: formatJalaliFromIso,
    mountJalaliCalendar: mountJalaliCalendar,
    bindJalaliInput: bindJalaliInput,
    autoInit: autoInit,
    toFaDigits: toFaDigits
  };
})(window);
