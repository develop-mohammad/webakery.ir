(function () {
  'use strict';
  if (typeof NM_DATA === 'undefined') return;

  var root = document.querySelector('.nm-app');
  if (!root) return;

  var state = {
    y: NM_DATA.today.y,
    m: NM_DATA.today.m,
    specialist: parseInt(document.getElementById('nm-specialist-id').value || '0', 10) || 0,
    date: '',
    slot: null
  };

  function qs(sel, el) { return (el || root).querySelector(sel); }
  function qsa(sel, el) { return Array.prototype.slice.call((el || root).querySelectorAll(sel)); }

  /** برچسب کوچک زیر روز — فقط تعطیل رسمی را «تعطیل» نشان بده */
  function daySubLabel(day) {
    if (day.available) {
      return (day.slots || 0) + ' نوبت';
    }
    if (day.holiday) {
      return 'تعطیل';
    }
    var r = String(day.reason || '');
    if (r.indexOf('هفتگی') !== -1) return 'بسته';
    if (r === 'گذشته') return 'گذشته';
    if (r.indexOf('بازه') !== -1) return 'خارج بازه';
    if (r.indexOf('برنامه') !== -1) return 'بسته';
    if (r.indexOf('پر') !== -1) return 'پر';
    if (r.indexOf('ماه') !== -1) return 'غیرفعال';
    return r ? 'بسته' : '';
  }

  function go(step) {
    qsa('.nm-step').forEach(function (b) { b.classList.toggle('is-active', String(b.getAttribute('data-step')) === String(step)); });
    qsa('.nm-panel').forEach(function (p) { p.classList.toggle('is-active', String(p.getAttribute('data-panel')) === String(step)); });
    if (String(step) === '2') loadMonth();
    if (String(step) === '4') renderSummary();
  }

  function api(action, data, isForm) {
    var body;
    if (isForm) {
      body = data;
      body.append('action', 'nm_api');
      body.append('nm_action', action);
      body.append('nonce', NM_DATA.nonce);
    } else {
      body = new URLSearchParams();
      body.set('action', 'nm_api');
      body.set('nm_action', action);
      body.set('nonce', NM_DATA.nonce);
      Object.keys(data || {}).forEach(function (k) { body.set(k, data[k]); });
    }
    return fetch(NM_DATA.ajax, { method: 'POST', body: body, credentials: 'same-origin' })
      .then(function (r) { return r.json(); });
  }

  function loadMonth() {
    var grid = qs('#nm-cal-grid');
    var label = qs('#nm-cal-label');
    var weekdays = qs('#nm-cal-weekdays');
    grid.innerHTML = '<div class="nm-muted">' + NM_DATA.i18n.loading + '</div>';
    api('month', { y: state.y, m: state.m, specialist_id: state.specialist }).then(function (res) {
      if (!res || !res.success) {
        grid.innerHTML = '<p class="nm-muted">خطا در بارگذاری تقویم. صفحه را رفرش کنید.</p>';
        return;
      }
      var d = res.data;
      label.textContent = d.label;
      weekdays.innerHTML = '';
      (d.weekdays || []).forEach(function (w) {
        var el = document.createElement('div'); el.textContent = w.charAt(0); weekdays.appendChild(el);
      });
      grid.innerHTML = '';
      var availableDays = 0;
      d.days.forEach(function (day) {
        if (!day) {
          var blank = document.createElement('div');
          grid.appendChild(blank);
          return;
        }
        var isAvailable = !!day.available;
        if (isAvailable) availableDays++;
        var btn = document.createElement('button');
        btn.type = 'button';
        var isHoliday = !!day.holiday;
        btn.className = 'nm-day' + (isAvailable ? ' is-available' : ' is-disabled') + (isHoliday ? ' is-holiday' : '') + (state.date === day.jalali ? ' is-selected' : '');
        btn.innerHTML = day.d + '<small>' + daySubLabel(day) + '</small>';
        btn.title = day.reason || day.holiday || (isAvailable ? 'انتخاب این روز' : 'این روز قابل رزرو نیست');
        if (isAvailable) {
          btn.addEventListener('click', function () {
            state.date = day.jalali;
            qs('#nm-jalali-date').value = day.jalali;
            state.slot = null;
            qs('#nm-start-time').value = '';
            qs('#nm-to-info').disabled = true;
            loadMonth();
            loadSlots();
          });
        }
        grid.appendChild(btn);
      });
      if (availableDays === 0) {
        var tip = document.createElement('p');
        tip.className = 'nm-muted nm-cal-tip';
        tip.style.marginTop = '12px';
        tip.textContent = d.has_schedule === false
          ? 'برنامه کاری تنظیم نشده. از پیشخوان نوبت من ← ساعات کاری را ذخیره کنید.'
          : 'در این ماه روز قابل رزروی نیست. در تنظیمات نوبت من، «روزهای کاری هفته» را درست تیک بزنید (معمولاً شنبه تا پنج‌شنبه)، بازه تاریخ و ساعات کاری را هم چک کنید.';
        var oldTip = qs('.nm-cal-tip');
        if (oldTip) oldTip.remove();
        grid.parentNode.appendChild(tip);
      } else {
        var clearTip = qs('.nm-cal-tip');
        if (clearTip) clearTip.remove();
      }
    }).catch(function () {
      grid.innerHTML = '<p class="nm-muted">خطا در ارتباط با سرور.</p>';
    });
  }

  function loadSlots() {
    var box = qs('#nm-slots');
    box.innerHTML = '<p class="nm-muted">' + NM_DATA.i18n.loading + '</p>';
    api('slots', { date: state.date, specialist_id: state.specialist }).then(function (res) {
      if (!res.success) return;
      var slots = res.data.slots || [];
      box.innerHTML = '';
      if (!slots.length) {
        box.innerHTML = '<p class="nm-muted">ساعت آزادی نیست.</p>';
        return;
      }
      slots.forEach(function (s) {
        var b = document.createElement('button');
        b.type = 'button';
        b.className = 'nm-slot';
        b.textContent = s.start + ' · ' + s.price_fa;
        b.addEventListener('click', function () {
          qsa('.nm-slot').forEach(function (x) { x.classList.remove('is-selected'); });
          b.classList.add('is-selected');
          state.slot = s;
          qs('#nm-start-time').value = s.start;
          qs('#nm-duration').value = s.duration;
          qs('#nm-to-info').disabled = false;
        });
        box.appendChild(b);
      });
    });
  }

  function loadQuestions(cat) {
    var box = qs('#nm-dynamic-questions');
    box.innerHTML = '';
    api('questions', { category: cat || '' }).then(function (res) {
      if (!res.success) return;
      (res.data.questions || []).forEach(function (q) {
        if (cat && q.category !== cat) return;
        var label = document.createElement('label');
        label.textContent = q.question + (q.is_required === '1' || q.is_required === 1 ? ' *' : '');
        var field;
        var opts = [];
        try { opts = q.options ? JSON.parse(q.options) : []; } catch (e) { opts = []; }
        if (q.type === 'select') {
          field = document.createElement('select');
          field.name = 'answers[' + q.id + ']';
          var o0 = document.createElement('option'); o0.value = ''; o0.textContent = 'انتخاب'; field.appendChild(o0);
          (opts || []).forEach(function (op) {
            var o = document.createElement('option'); o.value = op; o.textContent = op; field.appendChild(o);
          });
        } else if (q.type === 'textarea') {
          field = document.createElement('textarea');
          field.name = 'answers[' + q.id + ']';
          field.rows = 3;
        } else {
          field = document.createElement('input');
          field.type = 'text';
          field.name = 'answers[' + q.id + ']';
        }
        if (q.is_required === '1' || q.is_required === 1) field.required = true;
        label.appendChild(field);
        box.appendChild(label);
      });
    });
  }

  function renderSummary() {
    var form = qs('#nm-booking-form');
    var fd = new FormData(form);
    var html = '<div><strong>تاریخ:</strong> ' + (fd.get('jalali_date') || '-') + ' ساعت ' + (fd.get('start_time') || '-') + '</div>';
    html += '<div><strong>نام:</strong> ' + (fd.get('customer_name') || '-') + '</div>';
    html += '<div><strong>تلفن:</strong> ' + (fd.get('customer_phone') || '-') + '</div>';
    html += '<div><strong>شهر:</strong> ' + (fd.get('customer_city') || '-') + '</div>';
    html += '<div><strong>دسته مشکل:</strong> ' + (fd.get('problem_category') || '-') + '</div>';
    if (state.slot) html += '<div><strong>مبلغ:</strong> ' + state.slot.price_fa + '</div>';
    qs('#nm-summary').innerHTML = html;
  }

  // Events
  qsa('.nm-pick-sp').forEach(function (btn) {
    btn.addEventListener('click', function () {
      qsa('.nm-pick-sp').forEach(function (b) { b.classList.remove('is-selected'); });
      btn.classList.add('is-selected');
      state.specialist = parseInt(btn.getAttribute('data-id'), 10) || 0;
      qs('#nm-specialist-id').value = state.specialist;
      var dur = btn.getAttribute('data-duration');
      if (dur) qs('#nm-duration').value = dur;
      go(2);
    });
  });

  var auto = qs('#nm-auto-sp');
  if (auto) {
    state.specialist = parseInt(auto.value, 10) || 0;
    qs('#nm-specialist-id').value = state.specialist;
    if (auto.getAttribute('data-duration')) qs('#nm-duration').value = auto.getAttribute('data-duration');
  }

  qsa('[data-next]').forEach(function (b) {
    b.addEventListener('click', function () { go(b.getAttribute('data-next')); });
  });
  qsa('[data-prev]').forEach(function (b) {
    b.addEventListener('click', function () { go(b.getAttribute('data-prev')); });
  });

  qs('#nm-cal-prev').addEventListener('click', function () {
    state.m--; if (state.m < 1) { state.m = 12; state.y--; } loadMonth();
  });
  qs('#nm-cal-next').addEventListener('click', function () {
    state.m++; if (state.m > 12) { state.m = 1; state.y++; } loadMonth();
  });

  var cat = qs('#nm-problem-cat');
  if (cat) cat.addEventListener('change', function () { loadQuestions(cat.value); });

  qs('#nm-booking-form').addEventListener('submit', function (e) {
    e.preventDefault();
    var btn = qs('#nm-submit');
    btn.disabled = true;
    btn.textContent = NM_DATA.i18n.loading;
    var fd = new FormData(qs('#nm-booking-form'));
    api('book', fd, true).then(function (res) {
      btn.disabled = false;
      btn.textContent = NM_DATA.i18n.pay;
      var box = qs('#nm-result');
      box.hidden = false;
      if (!res.success) {
        box.style.background = '#fef2f2';
        box.innerHTML = (res.data && res.data.message) ? res.data.message : NM_DATA.i18n.error;
        return;
      }
      box.style.background = '#ecfdf5';
      box.innerHTML = res.data.thank_you || ('ثبت شد: ' + res.data.booking_code);
      if (res.data.pay_url) {
        box.innerHTML += '<p style="margin-top:12px"><a class="nm-btn nm-btn-primary" href="' + res.data.pay_url + '">ادامه پرداخت</a></p>';
        window.location.href = res.data.pay_url;
      } else {
        box.style.background = '#fff7ed';
        box.innerHTML += '<p style="margin-top:12px;color:#9a3412">لینک پرداخت ساخته نشد. مدیر سایت باید در تنظیمات نوبت من مرچنت زرین‌پال را درست وارد کند (نه در فیلد زیبال) یا درگاه ووکامرس را فعال کند.</p>';
      }
    }).catch(function () {
      btn.disabled = false;
      btn.textContent = NM_DATA.i18n.pay;
    });
  });
})();
