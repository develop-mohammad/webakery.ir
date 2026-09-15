(function ($) {
	'use strict';

	var J_MONTHS = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];
	var G_MONTHS = ['ژانویه', 'فوریه', 'مارس', 'آوریل', 'مه', 'ژوئن', 'ژوئیه', 'اوت', 'سپتامبر', 'اکتبر', 'نوامبر', 'دسامبر'];
	var WEEK = ['ش', 'ی', 'د', 'س', 'چ', 'پ', 'ج'];

	function toGregorian(jy, jm, jd) {
		jy = parseInt(jy, 10);
		jm = parseInt(jm, 10);
		jd = parseInt(jd, 10);
		jy += 1595;
		var days = -355668 + 365 * jy + Math.floor(jy / 33) * 8 + Math.floor(((jy % 33) + 3) / 4) + jd;
		days += jm < 7 ? (jm - 1) * 31 : (jm - 7) * 30 + 186;
		var gy = 400 * Math.floor(days / 146097);
		days = days % 146097;
		if (days > 36524) {
			gy += 100 * Math.floor(--days / 36524);
			days = days % 36524;
			if (days >= 365) {
				days++;
			}
		}
		gy += 4 * Math.floor(days / 1461);
		days = days % 1461;
		if (days > 364) {
			gy += Math.floor((days - 1) / 365);
			days = (days - 1) % 365;
		}
		var gd = days + 1;
		var leap = gy % 4 === 0 && (gy % 100 !== 0 || gy % 400 === 0);
		var dim = [0, 31, leap ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
		var i = 1;
		for (; gd > dim[i]; i++) {
			gd -= dim[i];
		}
		return [gy, i, gd];
	}

	function toJalali(gy, gm, gd) {
		gy = parseInt(gy, 10);
		gm = parseInt(gm, 10);
		gd = parseInt(gd, 10);
		var g_y_m = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
		var jy = gy <= 1600 ? 0 : 979;
		gy -= gy <= 1600 ? 621 : 1600;
		var gy2 = gm > 2 ? gy + 1 : gy;
		var days = 365 * gy + Math.floor((gy2 + 3) / 4) - Math.floor((gy2 + 99) / 100) + Math.floor((gy2 + 399) / 400) - 80 + gd + g_y_m[gm - 1];
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
		for (; i < 11 && days >= jmDays[i]; i++) {
			days -= jmDays[i];
		}
		return [jy, i + 1, days + 1];
	}

	function jalaliMonthLen(jy, jm) {
		jm = parseInt(jm, 10);
		if (jm < 1 || jm > 12) {
			return 0;
		}
		if (jm <= 6) {
			return 31;
		}
		if (jm <= 11) {
			return 30;
		}
		var g = toGregorian(jy, 12, 30);
		var j = toJalali(g[0], g[1], g[2]);
		return j[1] === 12 && j[2] === 30 ? 30 : 29;
	}

	function gregorianMonthLen(y, m) {
		return new Date(y, m, 0).getDate();
	}

	function faToEn(str) {
		return String(str || '').replace(/[۰-۹]/g, function (d) {
			return '۰۱۲۳۴۵۶۷۸۹'.indexOf(d);
		}).replace(/[٠-٩]/g, function (d) {
			return '٠١٢٣٤٥٦٧٨٩'.indexOf(d);
		});
	}

	function pad(n) {
		n = parseInt(n, 10) || 0;
		return n < 10 ? '0' + n : String(n);
	}

	function parseRaw(raw, cal) {
		raw = faToEn(raw).trim().replace(/[.\s\\-]/g, '/');
		var m = raw.match(/^(\d{4})\/(\d{1,2})\/(\d{1,2})$/);
		if (!m) {
			return null;
		}
		var y = parseInt(m[1], 10);
		var mo = parseInt(m[2], 10);
		var d = parseInt(m[3], 10);
		if (mo < 1 || mo > 12 || d < 1 || d > 31) {
			return null;
		}
		var jalali = y > 1200 && y < 1700;
		if (!jalali && y < 1700) {
			jalali = cal === 'jalali';
		}
		if (jalali) {
			var g = toGregorian(y, mo, d);
			return { y: g[0], m: g[1], d: g[2] };
		}
		return { y: y, m: mo, d: d };
	}

	function formatOut(y, m, d, cal) {
		if (cal === 'jalali') {
			var j = toJalali(y, m, d);
			return pad(j[0]) + '/' + pad(j[1]) + '/' + pad(j[2]);
		}
		return pad(y) + '/' + pad(m) + '/' + pad(d);
	}

	function todayParts() {
		var ymd = (window.wbeAdmin && wbeAdmin.today) || '';
		var m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(ymd);
		if (m) {
			return { y: parseInt(m[1], 10), m: parseInt(m[2], 10), d: parseInt(m[3], 10) };
		}
		var n = new Date();
		return { y: n.getFullYear(), m: n.getMonth() + 1, d: n.getDate() };
	}

	function fieldCalendar(el) {
		var $el = $(el);
		var $panel = $el.closest('.wbe-product-panel');
		var $sel = $panel.find('select[name="wbe_calendar"], select[name$="[calendar]"]').first();
		if ($sel.length) {
			var v = $sel.val();
			if (v === 'jalali' || v === 'gregorian') {
				return v;
			}
		}
		if ($el.closest('[data-calendar]').attr('data-calendar')) {
			var dc = $el.closest('[data-calendar]').attr('data-calendar');
			if (dc === 'jalali' || dc === 'gregorian') {
				return dc;
			}
		}
		return (window.wbeAdmin && wbeAdmin.calendar) || 'jalali';
	}

	var $box = null;
	var $input = null;
	var view = { y: 1405, m: 1, cal: 'jalali' };

	function ensureBox() {
		if ($box && $box.length) {
			return $box;
		}
		$box = $('<div class="wbe-datepicker" hidden dir="rtl"></div>');
		$('body').append($box);
		$box.on('click', function (e) {
			e.stopPropagation();
		});
		$box.on('click', '[data-nav]', function () {
			shiftView(parseInt($(this).attr('data-nav'), 10), false);
		});
		$box.on('click', '[data-year]', function () {
			shiftView(0, parseInt($(this).attr('data-year'), 10));
		});
		$box.on('click', '[data-day]', function () {
			pickDay(parseInt($(this).attr('data-day'), 10));
		});
		$box.on('click', '[data-act="today"]', function () {
			var t = todayParts();
			if (view.cal === 'jalali') {
				var j = toJalali(t.y, t.m, t.d);
				view.y = j[0];
				view.m = j[1];
				pickDay(j[2]);
			} else {
				view.y = t.y;
				view.m = t.m;
				pickDay(t.d);
			}
		});
		$box.on('click', '[data-act="clear"]', function () {
			if ($input) {
				$input.val('').trigger('change').trigger('input');
			}
			hide();
		});
		return $box;
	}

	function shiftView(monthDelta, yearDelta) {
		if (yearDelta) {
			view.y += yearDelta;
		}
		view.m += monthDelta;
		if (view.m < 1) {
			view.m = 12;
			view.y -= 1;
		} else if (view.m > 12) {
			view.m = 1;
			view.y += 1;
		}
		render();
	}

	function pickDay(day) {
		if (!$input) {
			return;
		}
		var gy, gm, gd;
		if (view.cal === 'jalali') {
			var g = toGregorian(view.y, view.m, day);
			gy = g[0];
			gm = g[1];
			gd = g[2];
		} else {
			gy = view.y;
			gm = view.m;
			gd = day;
		}
		$input.val(formatOut(gy, gm, gd, view.cal)).trigger('change').trigger('input');
		hide();
	}

	function weekdayIndex(gy, gm, gd) {
		var dt = new Date(gy, gm - 1, gd);
		return (dt.getDay() + 1) % 7;
	}

	function render() {
		ensureBox();
		var names = view.cal === 'jalali' ? J_MONTHS : G_MONTHS;
		var len = view.cal === 'jalali' ? jalaliMonthLen(view.y, view.m) : gregorianMonthLen(view.y, view.m);
		var firstG;
		if (view.cal === 'jalali') {
			firstG = toGregorian(view.y, view.m, 1);
		} else {
			firstG = [view.y, view.m, 1];
		}
		var start = weekdayIndex(firstG[0], firstG[1], firstG[2]);
		var t = todayParts();
		var todayKey = t.y + '-' + t.m + '-' + t.d;
		var selected = $input ? parseRaw($input.val(), view.cal) : null;
		var html = '';
		html += '<div class="wbe-datepicker__nav">';
		html += '<button type="button" class="button-link" data-year="-1" aria-label="سال قبل">«</button>';
		html += '<button type="button" class="button-link" data-nav="-1" aria-label="ماه قبل">‹</button>';
		html += '<span class="wbe-datepicker__title">' + names[view.m - 1] + ' ' + view.y + '</span>';
		html += '<button type="button" class="button-link" data-nav="1" aria-label="ماه بعد">›</button>';
		html += '<button type="button" class="button-link" data-year="1" aria-label="سال بعد">»</button>';
		html += '</div><div class="wbe-datepicker__week">';
		WEEK.forEach(function (w) {
			html += '<span>' + w + '</span>';
		});
		html += '</div><div class="wbe-datepicker__days">';
		var i;
		for (i = 0; i < start; i++) {
			html += '<span class="is-empty"></span>';
		}
		for (var d = 1; d <= len; d++) {
			var g = view.cal === 'jalali' ? toGregorian(view.y, view.m, d) : [view.y, view.m, d];
			var key = g[0] + '-' + g[1] + '-' + g[2];
			var cls = 'wbe-datepicker__day';
			if (key === todayKey) {
				cls += ' is-today';
			}
			if (selected && selected.y === g[0] && selected.m === g[1] && selected.d === g[2]) {
				cls += ' is-selected';
			}
			html += '<button type="button" class="' + cls + '" data-day="' + d + '">' + d + '</button>';
		}
		html += '</div><div class="wbe-datepicker__foot">';
		html += '<button type="button" class="button-link" data-act="today">امروز</button>';
		html += '<button type="button" class="button-link" data-act="clear">پاک کردن</button>';
		html += '</div>';
		$box.html(html);
	}

	function place() {
		if (!$input || !$box) {
			return;
		}
		var rect = $input[0].getBoundingClientRect();
		var top = rect.bottom + window.scrollY + 4;
		var left = rect.left + window.scrollX;
		var width = Math.max(rect.width, 240);
		$box.css({ top: top + 'px', left: left + 'px', minWidth: width + 'px' });
	}

	function show(el) {
		$input = $(el);
		view.cal = fieldCalendar(el);
		var parsed = parseRaw($input.val(), view.cal);
		var t = todayParts();
		if (parsed) {
			if (view.cal === 'jalali') {
				var j = toJalali(parsed.y, parsed.m, parsed.d);
				view.y = j[0];
				view.m = j[1];
			} else {
				view.y = parsed.y;
				view.m = parsed.m;
			}
		} else if (view.cal === 'jalali') {
			var tj = toJalali(t.y, t.m, t.d);
			view.y = tj[0];
			view.m = tj[1];
		} else {
			view.y = t.y;
			view.m = t.m;
		}
		ensureBox();
		render();
		$box.removeAttr('hidden');
		place();
	}

	function hide() {
		if ($box) {
			$box.attr('hidden', 'hidden');
		}
		$input = null;
	}

	$(document).on('focus click', '.wbe-date', function () {
		show(this);
	});

	$(document).on('mousedown', function (e) {
		if (!$box || $box.prop('hidden')) {
			return;
		}
		if ($(e.target).closest('.wbe-datepicker, .wbe-date').length) {
			return;
		}
		hide();
	});

	$(window).on('resize scroll', function () {
		if ($box && !$box.prop('hidden')) {
			place();
		}
	});

	window.WBEDatepicker = {
		toGregorian: toGregorian,
		toJalali: toJalali,
		jalaliMonthLen: jalaliMonthLen,
		formatOut: formatOut,
		parseRaw: parseRaw
	};
})(window.jQuery);
