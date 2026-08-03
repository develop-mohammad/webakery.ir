(function (global) {
  'use strict';

  var WEEKDAYS = ['یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنجشنبه', 'جمعه', 'شنبه'];
  var MONTHS = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];

  function div(a, b) {
    return Math.floor(a / b);
  }

  function toJalali(gy, gm, gd) {
    var g_d_m = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
    var gy2 = gm > 2 ? gy + 1 : gy;
    var days =
      355666 +
      365 * gy +
      div(gy2 + 3, 4) -
      div(gy2 + 99, 100) +
      div(gy2 + 399, 400) +
      gd +
      g_d_m[gm - 1];
    var jy = -1595 + 33 * div(days, 12053);
    days %= 12053;
    jy += 4 * div(days, 1461);
    days %= 1461;
    if (days > 365) {
      jy += div(days - 1, 365);
      days = (days - 1) % 365;
    }
    var jm, jd;
    if (days < 186) {
      jm = 1 + div(days, 31);
      jd = 1 + (days % 31);
    } else {
      jm = 7 + div(days - 186, 30);
      jd = 1 + ((days - 186) % 30);
    }
    return { jy: jy, jm: jm, jd: jd };
  }

  function parseISO(iso) {
    var p = String(iso || '').split('-');
    if (p.length !== 3) return null;
    return {
      y: parseInt(p[0], 10),
      m: parseInt(p[1], 10),
      d: parseInt(p[2], 10),
    };
  }

  function toPersianDigits(str) {
    return String(str).replace(/\d/g, function (d) {
      return '۰۱۲۳۴۵۶۷۸۹'[d];
    });
  }

  function formatJalali(iso) {
    var g = parseISO(iso);
    if (!g) return '—';
    var j = toJalali(g.y, g.m, g.d);
    return toPersianDigits(j.jd + ' ' + MONTHS[j.jm - 1] + ' ' + j.jy);
  }

  function weekdayName(iso) {
    var g = parseISO(iso);
    if (!g) return '—';
    var dt = new Date(g.y, g.m - 1, g.d);
    return WEEKDAYS[dt.getDay()];
  }

  function shiftISO(iso, delta) {
    var g = parseISO(iso);
    if (!g) return iso;
    var dt = new Date(g.y, g.m - 1, g.d);
    dt.setDate(dt.getDate() + delta);
    var y = dt.getFullYear();
    var m = String(dt.getMonth() + 1).padStart(2, '0');
    var d = String(dt.getDate()).padStart(2, '0');
    return y + '-' + m + '-' + d;
  }

  function todayISO() {
    var dt = new Date();
    var y = dt.getFullYear();
    var m = String(dt.getMonth() + 1).padStart(2, '0');
    var d = String(dt.getDate()).padStart(2, '0');
    return y + '-' + m + '-' + d;
  }

  global.RZMJalali = {
    formatJalali: formatJalali,
    weekdayName: weekdayName,
    shiftISO: shiftISO,
    todayISO: todayISO,
    toPersianDigits: toPersianDigits,
    months: MONTHS,
  };
})(typeof window !== 'undefined' ? window : this);
