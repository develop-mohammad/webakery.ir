(function () {
  'use strict';

  function qs(root, sel) {
    return root.querySelector(sel);
  }

  function qsa(root, sel) {
    return Array.prototype.slice.call(root.querySelectorAll(sel));
  }

  function show(el, on) {
    if (!el) return;
    if (on) {
      el.hidden = false;
      el.removeAttribute('hidden');
    } else {
      el.hidden = true;
      el.setAttribute('hidden', '');
    }
  }

  function setAlert(root, type, msg) {
    var err = qs(root, '[data-nck-error]');
    var ok = qs(root, '[data-nck-ok]');
    show(err, false);
    show(ok, false);
    if (!msg) return;
    var target = type === 'ok' ? ok : err;
    if (!target) return;
    target.textContent = msg;
    show(target, true);
  }

  function setLoading(btn, on, label) {
    if (!btn) return;
    if (on) {
      btn.dataset.oldText = btn.textContent;
      btn.disabled = true;
      btn.textContent = label || '…';
    } else {
      btn.disabled = false;
      if (btn.dataset.oldText) btn.textContent = btn.dataset.oldText;
    }
  }

  function post(action, data) {
    if (window.NCK && typeof NCK.mock === 'function') {
      return NCK.mock(action, data);
    }
    var body = new FormData();
    body.append('action', action);
    body.append('nonce', (window.NCK && NCK.nonce) || '');
    Object.keys(data || {}).forEach(function (k) {
      var v = data[k];
      if (Array.isArray(v)) {
        v.forEach(function (item) {
          body.append(k + '[]', item);
        });
      } else if (v === undefined || v === null) {
        body.append(k, '');
      } else {
        body.append(k, v);
      }
    });
    if (!data || data.return_url === undefined) {
      body.append('return_url', window.location.href.split('#')[0]);
    }
    return fetch((window.NCK && NCK.ajax) || '/wp-admin/admin-ajax.php', {
      method: 'POST',
      credentials: 'same-origin',
      body: body,
    }).then(function (r) {
      return r.json();
    });
  }

  function honorificLabel(v, root) {
    if (v === 'ms') {
      return (root && root.dataset.msLabel) || 'خانم';
    }
    return 'آقای';
  }

  function bindPad(canvas, onChange) {
    if (!canvas) return { drawn: false, clear: function () {}, toDataURL: function () { return ''; } };
    var ctx = canvas.getContext('2d');
    var drawing = false;
    var drawn = false;
    var last = null;
    function notify() {
      if (typeof onChange === 'function') onChange();
    }

    function pos(e) {
      var r = canvas.getBoundingClientRect();
      var src = e.touches ? e.touches[0] : e;
      var x = (src.clientX - r.left) * (canvas.width / r.width);
      var y = (src.clientY - r.top) * (canvas.height / r.height);
      return { x: x, y: y };
    }

    function paint(e) {
      if (!drawing) return;
      e.preventDefault();
      var p = pos(e);
      ctx.strokeStyle = '#1f241c';
      ctx.lineWidth = 2.4;
      ctx.lineCap = 'round';
      ctx.lineJoin = 'round';
      ctx.beginPath();
      ctx.moveTo(last.x, last.y);
      ctx.lineTo(p.x, p.y);
      ctx.stroke();
      last = p;
      drawn = true;
    }

    canvas.addEventListener('mousedown', function (e) {
      drawing = true;
      last = pos(e);
    });
    canvas.addEventListener('mousemove', paint);
    window.addEventListener('mouseup', function () {
      var was = drawing;
      drawing = false;
      if (was && drawn) notify();
    });
    canvas.addEventListener('touchstart', function (e) {
      drawing = true;
      last = pos(e);
    }, { passive: false });
    canvas.addEventListener('touchmove', paint, { passive: false });
    canvas.addEventListener('touchend', function () {
      var was = drawing;
      drawing = false;
      if (was && drawn) notify();
    });

    return {
      get drawn() { return drawn; },
      clear: function () {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        drawn = false;
        notify();
      },
      toDataURL: function () {
        return canvas.toDataURL('image/png');
      }
    };
  }

  function inkifyCanvas(srcCanvas) {
    var w = srcCanvas.width;
    var h = srcCanvas.height;
    var scale = Math.min(1, 720 / Math.max(1, w), 240 / Math.max(1, h));
    var out = document.createElement('canvas');
    out.width = Math.max(1, Math.round(w * scale));
    out.height = Math.max(1, Math.round(h * scale));
    var ctx = out.getContext('2d');
    ctx.drawImage(srcCanvas, 0, 0, out.width, out.height);
    var img = ctx.getImageData(0, 0, out.width, out.height);
    var d = img.data;
    var i;
    for (i = 0; i < d.length; i += 4) {
      var r = d[i];
      var g = d[i + 1];
      var b = d[i + 2];
      var a = d[i + 3];
      var lum = 0.299 * r + 0.587 * g + 0.114 * b;
      var ink = 1 - lum / 255;
      if (a < 18 || lum > 236 || ink < 0.06) {
        d[i] = 255;
        d[i + 1] = 255;
        d[i + 2] = 255;
        d[i + 3] = 0;
        continue;
      }
      d[i] = Math.round(22 + r * 0.1);
      d[i + 1] = Math.round(20 + g * 0.1);
      d[i + 2] = Math.round(18 + b * 0.08);
      d[i + 3] = Math.min(255, Math.round(a * (0.3 + ink * 0.7)));
    }
    ctx.putImageData(img, 0, 0);
    return out.toDataURL('image/png');
  }

  function fileToInk(file, ok, fail) {
    if (!file) {
      fail('عکس امضا انتخاب نشد.');
      return;
    }
    if (!/^image\/(png|jpe?g|webp|gif)$/i.test(file.type || '')) {
      fail('فقط تصویر PNG، JPG یا WEBP پذیرفته می‌شود.');
      return;
    }
    if (file.size > 6 * 1024 * 1024) {
      fail('حجم تصویر حداکثر ۶ مگابایت باشد.');
      return;
    }
    var img = new Image();
    var url = URL.createObjectURL(file);
    img.onload = function () {
      URL.revokeObjectURL(url);
      var w = img.naturalWidth || img.width;
      var h = img.naturalHeight || img.height;
      if (w < 40 || h < 20) {
        fail('تصویر امضا خیلی کوچک است.');
        return;
      }
      var c = document.createElement('canvas');
      c.width = w;
      c.height = h;
      c.getContext('2d').drawImage(img, 0, 0);
      ok(inkifyCanvas(c));
    };
    img.onerror = function () {
      URL.revokeObjectURL(url);
      fail('خواندن تصویر ممکن نشد.');
    };
    img.src = url;
  }

  function bindSignature(root) {
    var wrap = qs(root, '[data-nck-sign-wrap]');
    var padEl = qs(root, '[data-nck-pad]');
    var uploaded = '';
    var ink = wrap ? qs(wrap, '[data-nck-sign-ink]') : null;
    var empty = wrap ? qs(wrap, '[data-nck-sign-empty]') : null;

    function showInk(src) {
      if (ink) {
        if (src) {
          ink.src = src;
          ink.hidden = false;
        } else {
          ink.removeAttribute('src');
          ink.hidden = true;
        }
      }
      if (empty) empty.hidden = !!src;
    }

    var pad = bindPad(padEl, function () {
      if (pad.drawn) {
        uploaded = '';
        showInk(inkifyCanvas(padEl));
      } else if (!uploaded) {
        showInk('');
      }
    });

    if (!wrap && !padEl) {
      return {
        has: false,
        isReady: function () { return true; },
        getData: function () { return ''; },
        setData: function () {}
      };
    }

    if (!wrap) {
      return {
        has: true,
        isReady: function () { return pad.drawn; },
        getData: function () { return pad.drawn ? pad.toDataURL() : ''; },
        setData: function () {}
      };
    }

    var file = qs(wrap, '[data-nck-sign-file]');
    var drop = qs(wrap, '.nck-sign-drop');
    var modes = qsa(wrap, '[data-nck-sign-mode]');
    var panels = qsa(wrap, '[data-nck-sign-panel]');

    function setMode(mode) {
      modes.forEach(function (btn) {
        var on = btn.getAttribute('data-nck-sign-mode') === mode;
        btn.classList.toggle('is-on', on);
        btn.setAttribute('aria-selected', on ? 'true' : 'false');
      });
      panels.forEach(function (p) {
        p.hidden = p.getAttribute('data-nck-sign-panel') !== mode;
      });
    }

    function handleFile(f) {
      if (!f) return;
      fileToInk(f, function (data) {
        uploaded = data;
        pad.clear();
        uploaded = data;
        showInk(data);
        setMode('upload');
        setAlert(root, '', '');
      }, function (msg) {
        setAlert(root, 'err', msg);
      });
    }

    modes.forEach(function (btn) {
      btn.addEventListener('click', function () {
        setMode(btn.getAttribute('data-nck-sign-mode'));
      });
    });

    if (file) {
      file.addEventListener('change', function () {
        handleFile(file.files && file.files[0]);
        file.value = '';
      });
    }

    if (drop) {
      drop.addEventListener('dragover', function (e) {
        e.preventDefault();
        drop.classList.add('is-over');
      });
      drop.addEventListener('dragleave', function () {
        drop.classList.remove('is-over');
      });
      drop.addEventListener('drop', function (e) {
        e.preventDefault();
        drop.classList.remove('is-over');
        var f = e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files[0];
        handleFile(f);
      });
    }

    qsa(wrap, '[data-nck-clear]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        uploaded = '';
        pad.clear();
        showInk('');
        if (file) file.value = '';
      });
    });

    return {
      has: true,
      isReady: function () {
        return !!(uploaded || pad.drawn);
      },
      getData: function () {
        if (uploaded) return uploaded;
        if (pad.drawn && padEl) return inkifyCanvas(padEl);
        return '';
      },
      setData: function (src) {
        if (!src) return;
        uploaded = src;
        showInk(src);
        setMode('upload');
      }
    };
  }

  function faDigits(v) {
    return String(v).replace(/[0-9]/g, function (d) {
      return '۰۱۲۳۴۵۶۷۸۹'[d];
    });
  }

  function pad2(n) {
    n = String(n);
    return n.length < 2 ? '0' + n : n;
  }

  function toGregorian(jy, jm, jd) {
    jy = +jy;
    jm = +jm;
    jd = +jd;
    jy += 1595;
    var days = -355668 + (365 * jy) + (Math.floor(jy / 33) * 8) + Math.floor(((jy % 33) + 3) / 4) + jd
      + (jm < 7 ? (jm - 1) * 31 : ((jm - 7) * 30) + 186);
    var gy = 400 * Math.floor(days / 146097);
    days %= 146097;
    if (days > 36524) {
      gy += 100 * Math.floor(--days / 36524);
      days %= 36524;
      if (days >= 365) days++;
    }
    gy += 4 * Math.floor(days / 1461);
    days %= 1461;
    if (days > 364) {
      gy += Math.floor((days - 1) / 365);
      days = (days - 1) % 365;
    }
    var gd = days + 1;
    var leap = (gy % 4 === 0 && (gy % 100 !== 0 || gy % 400 === 0));
    var dim = [0, 31, leap ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
    var i = 1;
    for (; gd > dim[i]; i++) gd -= dim[i];
    return [gy, i, gd];
  }

  function toJalali(gy, gm, gd) {
    gy = +gy;
    gm = +gm;
    gd = +gd;
    var gMonths = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
    var jy = (gy <= 1600) ? 0 : 979;
    gy -= (gy <= 1600) ? 621 : 1600;
    var gy2 = (gm > 2) ? (gy + 1) : gy;
    var days = (365 * gy) + Math.floor((gy2 + 3) / 4) - Math.floor((gy2 + 99) / 100)
      + Math.floor((gy2 + 399) / 400) - 80 + gd + gMonths[gm - 1];
    jy += 33 * Math.floor(days / 12053);
    days %= 12053;
    jy += 4 * Math.floor(days / 1461);
    days %= 1461;
    if (days > 365) {
      jy += Math.floor((days - 1) / 365);
      days = (days - 1) % 365;
    }
    var jmDays = [31, 31, 31, 31, 31, 31, 30, 30, 30, 30, 30, 29];
    var i = 0;
    for (; i < 11 && days >= jmDays[i]; i++) days -= jmDays[i];
    return [jy, i + 1, days + 1];
  }

  function jalaliMonthLength(jy, jm) {
    if (jm <= 6) return 31;
    if (jm <= 11) return 30;
    var g = toGregorian(jy, 12, 30);
    var back = toJalali(g[0], g[1], g[2]);
    return (back[1] === 12 && back[2] === 30) ? 30 : 29;
  }

  function jalaliWeekday(jy, jm, jd) {
    var g = toGregorian(jy, jm, jd);
    var dt = new Date(g[0], g[1] - 1, g[2], 12, 0, 0);
    return (dt.getDay() + 1) % 7;
  }

  function jalaliYmd(y, m, d) {
    return y + '/' + pad2(m) + '/' + pad2(d);
  }

  function hallToday(calEl) {
    if (calEl && calEl.getAttribute('data-today-y')) {
      return {
        y: +calEl.getAttribute('data-today-y'),
        m: +calEl.getAttribute('data-today-m'),
        d: +calEl.getAttribute('data-today-d')
      };
    }
    if (window.NCK && NCK.cal && NCK.cal.y) {
      return { y: +NCK.cal.y, m: +NCK.cal.m, d: +NCK.cal.d };
    }
    var n = new Date();
    var j = toJalali(n.getFullYear(), n.getMonth() + 1, n.getDate());
    return { y: j[0], m: j[1], d: j[2] };
  }

  function monthNames() {
    if (window.NCK && NCK.cal && NCK.cal.months && NCK.cal.months.length) return NCK.cal.months;
    return ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];
  }

  function longJalali(y, m, d) {
    var names = monthNames();
    return faDigits(d) + ' ' + (names[m - 1] || '') + ' ' + faDigits(y);
  }

  function bindHallCalendar(root) {
    var wrap = qs(root, '[data-nck-cal]');
    if (!wrap) return;
    var pop = qs(wrap, '[data-nck-cal-pop]');
    var grid = qs(wrap, '[data-nck-cal-grid]');
    var title = qs(wrap, '[data-nck-cal-month]');
    var label = qs(wrap, '[data-nck-cal-label]');
    var input = qs(wrap, '[name="event_date"]');
    var openBtn = qs(wrap, '[data-nck-cal-open]');
    var today = hallToday(wrap);
    var parsed = String((input && input.value) || '').split('/');
    var sel = {
      y: parsed.length === 3 ? +parsed[0] : today.y,
      m: parsed.length === 3 ? +parsed[1] : today.m,
      d: parsed.length === 3 ? +parsed[2] : today.d
    };
    var viewY = sel.y;
    var viewM = sel.m;

    function todayKey() {
      return today.y * 10000 + today.m * 100 + today.d;
    }

    function setDate(y, m, d, close) {
      sel = { y: y, m: m, d: d };
      if (input) {
        input.value = jalaliYmd(y, m, d);
        input.dispatchEvent(new Event('change', { bubbles: true }));
      }
      if (label) label.textContent = longJalali(y, m, d);
      render();
      if (close) show(pop, false);
    }

    function render() {
      if (title) title.textContent = (monthNames()[viewM - 1] || '') + ' ' + faDigits(viewY);
      if (!grid) return;
      grid.innerHTML = '';
      var startWd = jalaliWeekday(viewY, viewM, 1);
      var len = jalaliMonthLength(viewY, viewM);
      var i;
      for (i = 0; i < startWd; i++) {
        var empty = document.createElement('span');
        empty.className = 'nck-cal-empty';
        empty.setAttribute('aria-hidden', 'true');
        grid.appendChild(empty);
      }
      for (i = 1; i <= len; i++) {
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.textContent = faDigits(i);
        var key = viewY * 10000 + viewM * 100 + i;
        if (key < todayKey()) {
          btn.disabled = true;
          btn.className = 'is-past';
        } else {
          (function (y, m, d) {
            btn.addEventListener('click', function () {
              setDate(y, m, d, true);
            });
          })(viewY, viewM, i);
        }
        if (sel.y === viewY && sel.m === viewM && sel.d === i) btn.classList.add('is-on');
        if (today.y === viewY && today.m === viewM && today.d === i) btn.classList.add('is-today');
        grid.appendChild(btn);
      }
    }

    if (openBtn) {
      openBtn.addEventListener('click', function (e) {
        e.preventDefault();
        var on = pop && pop.hidden;
        show(pop, on);
        if (on) {
          viewY = sel.y;
          viewM = sel.m;
          render();
        }
      });
    }
    var prev = qs(wrap, '[data-nck-cal-prev]');
    var next = qs(wrap, '[data-nck-cal-next]');
    if (prev) {
      prev.addEventListener('click', function () {
        if (viewY === today.y && viewM === today.m) return;
        viewM -= 1;
        if (viewM < 1) {
          viewM = 12;
          viewY -= 1;
        }
        render();
      });
    }
    if (next) {
      next.addEventListener('click', function () {
        var maxM = today.m + 18;
        var maxY = today.y + Math.floor((maxM - 1) / 12);
        maxM = ((maxM - 1) % 12) + 1;
        if (viewY > maxY || (viewY === maxY && viewM >= maxM)) return;
        viewM += 1;
        if (viewM > 12) {
          viewM = 1;
          viewY += 1;
        }
        render();
      });
    }
    document.addEventListener('click', function (e) {
      if (!pop || pop.hidden) return;
      if (wrap.contains(e.target)) return;
      show(pop, false);
    });
    setDate(sel.y, sel.m, sel.d, false);
    show(pop, false);
  }

  var HALL_WINDOWS = [
    { id: 'morning', start: 9 * 60, end: 13 * 60 },
    { id: 'evening', start: 16 * 60, end: 22 * 60 }
  ];

  function parseHallMinutes(v) {
    var m = String(v || '').match(/^(\d{1,2}):(\d{2})$/);
    if (!m) return null;
    return (+m[1]) * 60 + (+m[2]);
  }

  function fmtHallMinutes(min) {
    return pad2(Math.floor(min / 60)) + ':' + pad2(min % 60);
  }

  function hallWindowOf(min) {
    var i;
    for (i = 0; i < HALL_WINDOWS.length; i++) {
      if (min >= HALL_WINDOWS[i].start && min <= HALL_WINDOWS[i].end) return HALL_WINDOWS[i].id;
    }
    return '';
  }

  function hallSlots() {
    var out = [];
    HALL_WINDOWS.forEach(function (w) {
      var m;
      for (m = w.start; m <= w.end; m += 30) out.push(fmtHallMinutes(m));
    });
    return out;
  }

  function bindHallRolls(root) {
    var wrap = qs(root, '[data-nck-time-rolls]');
    if (!wrap) return function () {};
    var startInput = qs(wrap, '[name="start_hour"]');
    var endInput = qs(wrap, '[name="end_hour"]');
    var startRoll = qs(wrap, '[data-nck-roll="start"] [data-nck-roll-frame]');
    var endRoll = qs(wrap, '[data-nck-roll="end"] [data-nck-roll-frame]');
    if (!startRoll || !endRoll) return function () {};

    function ensureItems(frame) {
      var list = qs(frame, '.nck-roll-list');
      if (!list) return;
      if (list.querySelector('[data-value]')) return;
      hallSlots().forEach(function (slot) {
        var li = document.createElement('li');
        li.setAttribute('data-value', slot);
        li.textContent = faDigits(slot);
        list.appendChild(li);
      });
    }

    ensureItems(startRoll);
    ensureItems(endRoll);

    function itemsOf(frame) {
      return qsa(frame, '[data-value]');
    }

    function setValue(input, value) {
      if (!input) return;
      input.value = value;
      input.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function markOn(frame, value) {
      itemsOf(frame).forEach(function (li) {
        li.classList.toggle('is-on', li.getAttribute('data-value') === value);
      });
    }

    function scrollToValue(frame, value) {
      var item = frame.querySelector('[data-value="' + value + '"]');
      if (!item) return;
      var top = item.offsetTop - (frame.clientHeight - item.offsetHeight) / 2;
      frame.scrollTop = Math.max(0, top);
    }

    function nearest(frame, allowOff) {
      var mid = frame.scrollTop + frame.clientHeight / 2;
      var best = null;
      var bestDist = Infinity;
      itemsOf(frame).forEach(function (li) {
        if (!allowOff && li.classList.contains('is-off')) return;
        var c = li.offsetTop + li.offsetHeight / 2;
        var dist = Math.abs(c - mid);
        if (dist < bestDist) {
          bestDist = dist;
          best = li;
        }
      });
      return best;
    }

    function startVal() {
      return (startInput && startInput.value) || '16:00';
    }

    function validEnd(start, end) {
      var s = parseHallMinutes(start);
      var e = parseHallMinutes(end);
      if (s === null || e === null) return false;
      if (!hallWindowOf(s) || hallWindowOf(s) !== hallWindowOf(e)) return false;
      return e > s;
    }

    function firstValidEnd(start) {
      var found = '';
      itemsOf(endRoll).forEach(function (li) {
        var v = li.getAttribute('data-value');
        if (!found && validEnd(start, v)) found = v;
      });
      return found || '20:00';
    }

    function syncEndAvailability() {
      var s = startVal();
      itemsOf(endRoll).forEach(function (li) {
        var v = li.getAttribute('data-value');
        li.classList.toggle('is-off', !validEnd(s, v));
      });
      itemsOf(startRoll).forEach(function (li) {
        var v = li.getAttribute('data-value');
        var sm = parseHallMinutes(v);
        var win = sm === null ? '' : hallWindowOf(sm);
        var can = false;
        if (win) {
          HALL_WINDOWS.forEach(function (w) {
            if (w.id === win && sm < w.end) can = true;
          });
        }
        li.classList.toggle('is-off', !can);
      });
      var e = (endInput && endInput.value) || '';
      if (!validEnd(s, e)) {
        e = firstValidEnd(s);
        setValue(endInput, e);
      }
      markOn(startRoll, s);
      markOn(endRoll, e);
      return e;
    }

    function pickFromFrame(frame, input) {
      var li = nearest(frame, false);
      if (!li) return;
      var v = li.getAttribute('data-value');
      setValue(input, v);
      if (input === startInput) syncEndAvailability();
      else if (!validEnd(startVal(), v)) {
        v = firstValidEnd(startVal());
        setValue(endInput, v);
      }
      markOn(startRoll, startVal());
      markOn(endRoll, endInput.value);
      scrollToValue(frame, input === startInput ? startVal() : endInput.value);
    }

    function bindFrame(frame, input) {
      var timer = null;
      frame.addEventListener('scroll', function () {
        if (timer) clearTimeout(timer);
        timer = setTimeout(function () {
          pickFromFrame(frame, input);
        }, 80);
      });
      itemsOf(frame).forEach(function (li) {
        li.addEventListener('click', function () {
          if (li.classList.contains('is-off')) return;
          setValue(input, li.getAttribute('data-value'));
          if (input === startInput) syncEndAvailability();
          markOn(frame, li.getAttribute('data-value'));
          scrollToValue(frame, li.getAttribute('data-value'));
        });
      });
    }

    if (startInput && !startInput.value) startInput.value = '16:00';
    if (endInput && !endInput.value) endInput.value = '20:00';
    syncEndAvailability();

    function refresh() {
      syncEndAvailability();
      scrollToValue(startRoll, startVal());
      scrollToValue(endRoll, (endInput && endInput.value) || firstValidEnd(startVal()));
    }

    bindFrame(startRoll, startInput);
    bindFrame(endRoll, endInput);
    return refresh;
  }

  function bindHallPickers(root) {
    bindHallCalendar(root);
    var refreshRolls = bindHallRolls(root);
    root.nckHallRefresh = function () {
      if (typeof refreshRolls === 'function') refreshRolls();
    };
  }

  function updatePreamble(root) {
    var p = qs(root, '[data-nck-preamble]');
    if (!p || !p.dataset.tpl) return;
    var title = honorificLabel((qs(root, '[name="honorific"]') || {}).value, root);
    var name = ((qs(root, '[name="name"]') || {}).value || '').trim() || '…………………..';
    var phone = ((qs(root, '[name="phone"]') || {}).value || '').trim();
    var org = (window.NCK && NCK.org) || 'مجموعه فرهنگی نهال';
    var filled = p.dataset.tpl
      .replace(/\{\{org\}\}/g, org)
      .replace(/\{\{title\}\}/g, title)
      .replace(/\{\{name\}\}/g, name)
      .replace(/\{\{phone\}\}/g, phone ? faDigits(phone) : '…………………..');
    p.textContent = filled;
  }

  function formatFaMoney(raw) {
    var d = String(raw).replace(/[۰-۹]/g, function (c) {
      return '0123456789'['۰۱۲۳۴۵۶۷۸۹'.indexOf(c)];
    }).replace(/\D+/g, '');
    if (!d) return '';
    var withComma = d.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    return faDigits(withComma) + ' تومان';
  }

  function fillLive(root) {
    var form = qs(root, 'form');
    if (!form) return;
    root.querySelectorAll('[data-nck-live]').forEach(function (el) {
      var key = el.getAttribute('data-nck-live');
      var blank = el.getAttribute('data-blank') || '…………………….';
      var val = '';
      if (key === 'title') {
        val = honorificLabel((qs(form, '[name="honorific"]') || {}).value, root);
      } else if (key === 'package') {
        var pkg = qs(form, '[name="package"]:checked');
        if (pkg) {
          var card = pkg.closest('.nck-plan-card');
          var strong = card ? qs(card, '.nck-plan-copy strong') : null;
          val = strong ? String(strong.textContent || '').trim() : String(pkg.value || '').trim();
        }
      } else if (key === 'shift') {
        var pickedPkg = qs(form, '[name="package"]:checked');
        if (pickedPkg && pickedPkg.getAttribute('data-nck-dual') === '1') {
          val = 'صبح و عصر';
        } else {
          var sh = qs(form, '[name="shift"]:checked');
          if (sh) {
            val = sh.value === 'morning' ? 'صبح' : (sh.value === 'evening' ? 'عصر' : String(sh.value || '').trim());
          }
        }
      } else {
        var input = qs(form, '[name="' + key + '"]');
        if (input && input.type === 'radio') {
          var checked = qs(form, '[name="' + key + '"]:checked');
          val = checked ? String(checked.value || '').trim() : '';
        } else {
          val = input ? String(input.value || '').trim() : '';
        }
        if (key === 'amount' && val) {
          val = formatFaMoney(val);
        } else if (val && (key === 'phone' || key === 'national_id' || key === 'chairs' || key === 'event_date' || key === 'start_hour' || key === 'end_hour')) {
          val = faDigits(val);
        }
      }
      el.textContent = val || blank;
    });
  }

  function copyReviewInk(root) {
    var src = qs(root, '[data-nck-sign-ink]');
    var dest = qs(root, '[data-nck-review-ink]');
    var empty = qs(root, '[data-nck-review-empty]');
    if (!dest) return;
    var url = src && src.getAttribute('src') ? src.getAttribute('src') : '';
    if (url) {
      dest.src = url;
      dest.hidden = false;
      dest.removeAttribute('hidden');
      if (empty) {
        empty.hidden = true;
        empty.setAttribute('hidden', '');
      }
    } else {
      dest.removeAttribute('src');
      dest.hidden = true;
      dest.setAttribute('hidden', '');
      if (empty) {
        empty.hidden = false;
        empty.removeAttribute('hidden');
      }
    }
  }

  function bindDownloadReview(root) {
    qsa(root, '[data-nck-download-review]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        fillLive(root);
        copyReviewInk(root);
        var article = qs(root, '[data-nck-review]');
        if (!article) return;
        var styles = '';
        Array.prototype.forEach.call(document.querySelectorAll('link[rel="stylesheet"], style'), function (n) {
          styles += n.outerHTML;
        });
        var html = '<!DOCTYPE html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">' + styles + '</head><body class="nck-print-body" dir="rtl">' + article.outerHTML + '</body></html>';
        var blob = new Blob([html], { type: 'text/html;charset=utf-8' });
        var a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = 'قرارداد-نهال.html';
        document.body.appendChild(a);
        a.click();
        setTimeout(function () { URL.revokeObjectURL(a.href); a.remove(); }, 800);
      });
    });
  }

  function collectForm(form) {
    var fd = {};
    Array.prototype.forEach.call(form.elements, function (el) {
      if (!el.name) return;
      var name = el.name;
      var isArr = name.slice(-2) === '[]';
      if (isArr) name = name.slice(0, -2);
      if (el.type === 'radio') {
        if (el.checked) fd[name] = el.value;
        return;
      }
      if (el.type === 'checkbox') {
        if (isArr) {
          if (!Array.isArray(fd[name])) fd[name] = [];
          if (el.checked) fd[name].push(el.value);
          return;
        }
        fd[name] = el.checked ? (el.value || '1') : '';
        return;
      }
      if (el.type === 'button' || el.type === 'submit') return;
      fd[name] = el.value;
    });
    return fd;
  }

  function fieldLabel(el) {
    var wrap = el.closest('.nck-field');
    if (wrap) {
      var lab = wrap.querySelector('label, .nck-label');
      if (lab) return lab.textContent.replace(/\s+/g, ' ').trim();
    }
    var fs = el.closest('fieldset');
    if (fs) {
      var legend = fs.querySelector('legend');
      if (legend) return legend.textContent.replace(/\s+/g, ' ').trim();
    }
    var agree = el.closest('.nck-agree');
    if (agree) return agree.textContent.replace(/\s+/g, ' ').trim();
    return 'این مرحله';
  }

  function validateStep(step, form) {
    var reqs = step.querySelectorAll('[required]');
    var seenRadio = {};
    for (var k = 0; k < reqs.length; k++) {
      var el = reqs[k];
      if (el.disabled) continue;
      if (el.type === 'radio') {
        if (seenRadio[el.name]) continue;
        seenRadio[el.name] = true;
        var any = form.querySelector('[name="' + el.name + '"]:checked');
        if (!any) return 'لطفاً «' + fieldLabel(el) + '» را انتخاب کنید.';
        continue;
      }
      if (el.type === 'checkbox') {
        if (el.name && el.name.slice(-2) === '[]') continue;
        if (!el.checked) return 'لطفاً این مورد را بپذیرید: ' + fieldLabel(el);
        continue;
      }
      if (!String(el.value || '').trim()) {
        return 'لطفاً «' + fieldLabel(el) + '» را وارد کنید.';
      }
    }
    var need = step.getAttribute('data-nck-require-one');
    if (need) {
      var ok = false;
      need.split(',').forEach(function (n) {
        n = n.trim();
        if (!n) return;
        var nodes = step.querySelectorAll('[name="' + n + '[]"]:checked, [name="' + n + '"]:checked');
        if (nodes.length) ok = true;
      });
      if (!ok) return step.getAttribute('data-nck-require-msg') || 'حداقل یک گزینه را انتخاب کنید.';
    }
    var groups = step.querySelectorAll('[data-nck-need-one]');
    for (var g = 0; g < groups.length; g++) {
      var nm = groups[g].getAttribute('data-nck-need-one');
      if (!nm) continue;
      var picked = step.querySelectorAll('[name="' + nm + '[]"]:checked, [name="' + nm + '"]:checked');
      if (!picked.length) {
        var legend = groups[g].querySelector('legend');
        return 'حداقل یک گزینه برای «' + ((legend && legend.textContent) || 'این بخش') + '» را انتخاب کنید.';
      }
    }
    return '';
  }

  function knownLoginState() {
    return !!(window.NCK && Object.prototype.hasOwnProperty.call(NCK, 'loggedIn'));
  }

  function isLoggedIn() {
    return !!(window.NCK && NCK.loggedIn);
  }

  function wcReady() {
    return !!(window.NCK && NCK.wcReady && NCK.wcReady !== '0');
  }

  function syncPayGate(form) {
    var site = qs(form, '[data-nck-pay-site]');
    if (!site || !knownLoginState()) return;
    var logged = isLoggedIn();
    var wc = wcReady();
    show(qs(site, '[data-nck-login-needed]'), !logged);
    show(qs(site, '[data-nck-login-ok]'), logged && wc);
    show(qs(site, '[data-nck-wc-off]'), logged && !wc);
    var btn = qs(form, '[data-nck-submit]');
    if (btn && !site.hidden) {
      btn.disabled = !logged || !wc;
    }
  }

  function draftKey(root) {
    return 'nck-draft:' + (root.getAttribute('data-nck') || 'form') + ':' + window.location.pathname;
  }

  function saveDraft(root, form, sig, wizard) {
    try {
      sessionStorage.setItem(draftKey(root), JSON.stringify({
        fields: collectForm(form),
        step: wizard && typeof wizard.getIndex === 'function' ? wizard.getIndex() : 0,
        signature: sig && sig.has ? sig.getData() : ''
      }));
    } catch (err) { /* ignore quota */ }
  }

  function applyFields(form, fields) {
    Object.keys(fields || {}).forEach(function (name) {
      var val = fields[name];
      var nodes = form.querySelectorAll('[name="' + name + '"], [name="' + name + '[]"]');
      if (!nodes.length) return;
      Array.prototype.forEach.call(nodes, function (el) {
        if (el.type === 'radio') {
          el.checked = el.value === String(val);
          return;
        }
        if (el.type === 'checkbox') {
          if (Array.isArray(val)) {
            el.checked = val.indexOf(el.value) !== -1;
          } else {
            el.checked = !!val && val !== '0';
          }
          return;
        }
        if (el.type === 'file') return;
        if (!Array.isArray(val)) el.value = val;
      });
    });
  }

  function restoreDraft(root, form, sig, wizard) {
    var raw;
    try {
      raw = sessionStorage.getItem(draftKey(root));
    } catch (err) {
      return;
    }
    if (!raw) return;
    try {
      var d = JSON.parse(raw);
      applyFields(form, d.fields || {});
      form.dispatchEvent(new Event('change', { bubbles: true }));
      if (d.signature && sig && typeof sig.setData === 'function') sig.setData(d.signature);
      if (wizard && typeof wizard.go === 'function' && d.step) wizard.go(d.step);
      if (typeof root.nckHallRefresh === 'function') root.nckHallRefresh();
      sessionStorage.removeItem(draftKey(root));
    } catch (err2) { /* ignore */ }
  }

  function bindWizard(form, root) {
    var steps = [].slice.call(form.querySelectorAll('[data-nck-step]'));
    if (!steps.length) return null;
    var i = 0;
    var max = 0;

    function go(n, scroll) {
      i = Math.max(0, Math.min(steps.length - 1, n));
      if (i > max) max = i;
      steps.forEach(function (s, idx) {
        show(s, idx === i);
      });
      var now = faDigits(i + 1);
      var all = faDigits(steps.length);
      form.querySelectorAll('[data-nck-step-now]').forEach(function (el) { el.textContent = now; });
      form.querySelectorAll('[data-nck-step-all]').forEach(function (el) { el.textContent = all; });
      var title = steps[i].getAttribute('data-nck-step-label') || '';
      form.querySelectorAll('[data-nck-step-title]').forEach(function (el) { el.textContent = title; });
      var fill = form.querySelector('[data-nck-step-fill]');
      if (fill) fill.style.width = (((i + 1) / steps.length) * 100) + '%';
      var track = form.querySelector('.nck-wizard-track');
      if (track) {
        track.setAttribute('aria-valuenow', String(i + 1));
        track.setAttribute('aria-valuemax', String(steps.length));
      }
      show(qs(form, '[data-nck-prev]'), i > 0);
      show(qs(form, '[data-nck-next]'), i < steps.length - 1);
      show(qs(form, '[data-nck-submit]'), i === steps.length - 1);
      fillLive(root);
      copyReviewInk(root);
      if (typeof root.nckHallRefresh === 'function') root.nckHallRefresh();
      form.querySelectorAll('[data-nck-from]').forEach(function (el) {
        var srcName = el.getAttribute('data-nck-from');
        if (srcName) {
          var src = qs(form, '[name="' + srcName + '"]');
          if (src && src.value) el.value = src.value;
        }
      });
      syncPayGate(form);
      var ol = form.querySelector('[data-nck-step-dots]');
      if (ol) {
        ol.innerHTML = '';
        steps.forEach(function (step, idx) {
          var li = document.createElement('li');
          if (idx === i) li.className = 'is-on';
          else if (idx < i) li.className = 'is-done';
          var btn = document.createElement('button');
          btn.type = 'button';
          btn.textContent = faDigits(idx + 1);
          btn.disabled = idx > max;
          btn.setAttribute('aria-label', 'مرحله ' + faDigits(idx + 1) + ' از ' + all + ' — ' + (step.getAttribute('data-nck-step-label') || ''));
          btn.addEventListener('click', function () {
            if (idx <= max) go(idx, true);
          });
          li.appendChild(btn);
          ol.appendChild(li);
        });
      }
      if (scroll) {
        form.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
    }

    var nextBtn = qs(form, '[data-nck-next]');
    var prevBtn = qs(form, '[data-nck-prev]');
    if (nextBtn) {
      nextBtn.addEventListener('click', function () {
        var err = validateStep(steps[i], form);
        if (err) {
          setAlert(root, 'err', err);
          return;
        }
        setAlert(root, '', '');
        go(i + 1, true);
      });
    }
    if (prevBtn) {
      prevBtn.addEventListener('click', function () {
        setAlert(root, '', '');
        go(i - 1, true);
      });
    }
    form.addEventListener('keydown', function (e) {
      if (e.key !== 'Enter') return;
      if (e.target && (e.target.tagName === 'TEXTAREA' || e.target.type === 'submit')) return;
      if (i < steps.length - 1) {
        e.preventDefault();
        if (nextBtn) nextBtn.click();
      }
    });

    go(0, false);
    return {
      validateCurrent: function () {
        return validateStep(steps[i], form);
      },
      isLast: function () {
        return i === steps.length - 1;
      },
      getIndex: function () {
        return i;
      },
      go: function (n) {
        if (n > max) max = n;
        go(n, false);
      }
    };
  }

  function bindCoworkPlans(form) {
    var list = qs(form, '[data-nck-packages]');
    if (!list) return;

    function sync() {
      var picked = qs(form, '[name="package"]:checked');
      qsa(list, '.nck-plan-card').forEach(function (card) {
        var on = !!(card.querySelector('input') && card.querySelector('input').checked);
        card.classList.toggle('is-on', on);
      });
      var dual = !!(picked && picked.getAttribute('data-nck-dual') === '1');
      var wrap = qs(form, '[data-nck-shift-wrap]');
      var shifts = qsa(form, '[name="shift"]');
      if (wrap) {
        wrap.hidden = !picked || dual;
      }
      shifts.forEach(function (r) {
        r.required = !!(picked && !dual);
        r.disabled = !picked || dual;
        if (dual || !picked) r.checked = false;
      });
      var pay = qs(form, '[name="pay_amount"]');
      if (pay && picked) {
        var price = picked.getAttribute('data-nck-price') || '';
        if (price) pay.value = price;
      }
    }

    form.addEventListener('change', function (e) {
      if (!e.target) return;
      if (e.target.name === 'package' || e.target.name === 'shift') sync();
    });
    sync();
  }

  function bindSignForm(root, formSel, action) {
    var form = qs(root, formSel);
    if (!form) return;
    var wizard = bindWizard(form, root);
    var sig = bindSignature(root);
    bindCoworkPlans(form);
    bindDownloadReview(root);
    bindHallPickers(root);
    ['input', 'change'].forEach(function (ev) {
      form.addEventListener(ev, function () {
        updatePreamble(root);
        fillLive(root);
      });
    });
    fillLive(root);
    restoreDraft(root, form, sig, wizard);
    var loginLink = qs(form, '[data-nck-login-link]');
    if (loginLink) {
      loginLink.addEventListener('click', function () {
        saveDraft(root, form, sig, wizard);
      });
    }
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var i18n = (window.NCK && NCK.i18n) || {};
      if (wizard && !wizard.isLast()) {
        return;
      }
      if (wizard) {
        var stepErr = wizard.validateCurrent();
        if (stepErr) {
          setAlert(root, 'err', stepErr);
          return;
        }
      }
      if (sig.has && !sig.isReady()) {
        setAlert(root, 'err', i18n.signature || i18n.draw || 'عکس امضا را آپلود کنید یا داخل کادر بکشید.');
        return;
      }
      if (qs(form, '[data-nck-pay-site]') && knownLoginState() && !isLoggedIn()) {
        saveDraft(root, form, sig, wizard);
        var login = (window.NCK && NCK.loginUrl) || '';
        if (login) {
          window.location.href = login;
          return;
        }
        setAlert(root, 'err', 'برای پرداخت از درگاه سایت ابتدا وارد حساب کاربری شوید.');
        return;
      }
      var btn = qs(form, '[data-nck-submit]');
      setLoading(btn, true, i18n.signing);
      setAlert(root, '', '');
      var fd = collectForm(form);
      if (sig.has) fd.signature = sig.getData();
      post(action, fd).then(function (res) {
        if (!res || !res.success) {
          var data = (res && res.data) || {};
          if (data.need_login && data.login) {
            saveDraft(root, form, sig, wizard);
            window.location.href = data.login;
            return;
          }
          setLoading(btn, false);
          setAlert(root, 'err', data.message || i18n.error);
          return;
        }
        if (res.data && res.data.pay_url) {
          setLoading(btn, true, i18n.paying || 'در حال انتقال به پرداخت سایت…');
          window.location.href = res.data.pay_url;
          return;
        }
        setLoading(btn, false);
        show(form, false);
        var done = qs(root, '[data-nck-done]');
        show(done, true);
        qs(root, '[data-nck-done-msg]').textContent = res.data.message || '';
        var a = qs(root, '[data-nck-print-link]');
        if (a && res.data.print) a.href = res.data.print;
        var payLink = qs(root, '[data-nck-pay-link]');
        if (payLink && res.data.pay_url) {
          payLink.href = res.data.pay_url;
          show(payLink, true);
        }
      }).catch(function () {
        setLoading(btn, false);
        setAlert(root, 'err', i18n.error);
      });
    });
  }

  function bindContract(root) {
    bindSignForm(root, '[data-nck-sign]', 'nck_sign');
  }

  function bindHall(root) {
    bindSignForm(root, '[data-nck-sign-hall]', 'nck_sign_hall');
  }

  function bindLearner(root) {
    bindSignForm(root, '[data-nck-sign-learner]', 'nck_sign_learner');
  }

  function bindCustomForm(root) {
    bindSignForm(root, '[data-nck-sign-form]', 'nck_sign_form');
  }

  function renderCards(root, data) {
    var wrap = qs(root, '[data-nck-cards]');
    if (!wrap) return;
    wrap.innerHTML = '';
    Object.keys(data.shifts || {}).forEach(function (type) {
      var s = data.shifts[type];
      var el = document.createElement('div');
      el.className = 'nck-shift-card' + (s.current ? ' is-current' : '') + (s.open ? '' : ' is-closed');
      var remain = s.has_plan ? faDigits(s.remaining) : '—';
      var used = s.has_plan ? faDigits(s.used + ' از ' + s.quota) : 'بدون اشتراک';
      el.innerHTML = '<span>' + s.label + (s.current ? ' (الان)' : '') + '</span><strong>' + remain + '</strong><small>' + used + (s.already ? ' — امروز ثبت شده' : '') + (s.reason ? ' — ' + s.reason : '') + '</small>';
      wrap.appendChild(el);
    });
  }

  function applyMember(root, data) {
    var box = qs(root, '[data-nck-member]');
    show(box, true);
    qs(root, '[data-nck-member-name]').textContent = data.name || '';
    qs(root, '[data-nck-month]').textContent = 'ماه جاری: ' + (data.month || '');
    renderCards(root, data);
    var cin = qs(root, '[data-nck-checkin]');
    var can = data.slot && data.shifts && data.shifts[data.slot] && data.shifts[data.slot].has_plan && data.shifts[data.slot].open && !data.shifts[data.slot].already && data.status === 'active';
    show(cin, !!can);
    var pr = qs(root, '[data-nck-print]');
    if (pr) {
      if (data.print) {
        pr.href = data.print;
        show(pr, true);
      } else {
        show(pr, false);
      }
    }
  }

  function bindPortal(root) {
    var form = qs(root, '[data-nck-lookup]');
    if (!form) return;
    var i18n = (window.NCK && NCK.i18n) || {};
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var btn = qs(form, '[data-nck-lookup-btn]');
      setLoading(btn, true, i18n.looking);
      setAlert(root, '', '');
      var phone = (qs(form, '[name="phone"]') || {}).value || '';
      post('nck_lookup', { phone: phone }).then(function (res) {
        setLoading(btn, false);
        if (!res || !res.success) {
          show(qs(root, '[data-nck-member]'), false);
          setAlert(root, 'err', (res && res.data && res.data.message) || i18n.error);
          return;
        }
        root.dataset.phone = phone;
        applyMember(root, res.data);
      }).catch(function () {
        setLoading(btn, false);
        setAlert(root, 'err', i18n.error);
      });
    });

    var cin = qs(root, '[data-nck-checkin]');
    if (cin) {
      cin.addEventListener('click', function () {
        setLoading(cin, true, i18n.checking);
        post('nck_checkin', { phone: root.dataset.phone || '' }).then(function (res) {
          setLoading(cin, false);
          if (!res || !res.success) {
            setAlert(root, 'err', (res && res.data && res.data.message) || i18n.error);
            return;
          }
          setAlert(root, 'ok', res.data.message || '');
          applyMember(root, res.data);
        }).catch(function () {
          setLoading(cin, false);
          setAlert(root, 'err', i18n.error);
        });
      });
    }
  }

  function boot() {
    document.querySelectorAll('[data-nck="contract"]').forEach(bindContract);
    document.querySelectorAll('[data-nck="hall"]').forEach(bindHall);
    document.querySelectorAll('[data-nck="learner"]').forEach(bindLearner);
    document.querySelectorAll('[data-nck="form"]').forEach(bindCustomForm);
    document.querySelectorAll('[data-nck="portal"]').forEach(bindPortal);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
