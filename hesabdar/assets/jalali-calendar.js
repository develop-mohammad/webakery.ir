/**
 * تقویم شمسی مینیمال — بدون کتابخانه خارجی.
 * کلیک روی فیلد = تقویم روز؛ کلیک روی نام ماه = انتخاب ماه کامل.
 */
(function (global) {
    'use strict';

    var MONTH_NAMES = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];
    var WEEK_DAYS   = ['ش', 'ی', 'د', 'س', 'چ', 'پ', 'ج'];

    function pad(n) { return n < 10 ? '0' + n : '' + n; }

    function toGregorian(jy, jm, jd) {
        jy = parseInt(jy, 10); jm = parseInt(jm, 10); jd = parseInt(jd, 10);
        jy += 1595;
        var days = -355668 + (365 * jy) + Math.floor(jy / 33) * 8 + Math.floor(((jy % 33) + 3) / 4) + jd
                 + (jm < 7 ? (jm - 1) * 31 : ((jm - 7) * 30) + 186);
        var gy = 400 * Math.floor(days / 146097);
        days = days % 146097;
        if (days > 36524) {
            gy += 100 * Math.floor(--days / 36524);
            days = days % 36524;
            if (days >= 365) { days++; }
        }
        gy += 4 * Math.floor(days / 1461);
        days = days % 1461;
        if (days > 364) {
            gy += Math.floor((days - 1) / 365);
            days = (days - 1) % 365;
        }
        var gd = days + 1;
        var leap = (gy % 4 === 0 && (gy % 100 !== 0 || gy % 400 === 0));
        var daysInMonth = [0, 31, leap ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
        var i;
        for (i = 1; gd > daysInMonth[i]; i++) { gd -= daysInMonth[i]; }
        return [gy, i, gd];
    }

    function toJalali(gy, gm, gd) {
        gy = parseInt(gy, 10); gm = parseInt(gm, 10); gd = parseInt(gd, 10);
        var gYm = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
        var jy = (gy <= 1600) ? 0 : 979;
        gy -= (gy <= 1600) ? 621 : 1600;
        var gy2 = (gm > 2) ? (gy + 1) : gy;
        var days = (365 * gy) + Math.floor((gy2 + 3) / 4) - Math.floor((gy2 + 99) / 100)
                 + Math.floor((gy2 + 399) / 400) - 80 + gd + gYm[gm - 1];
        jy += 33 * Math.floor(days / 12053);
        days = days % 12053;
        jy += 4 * Math.floor(days / 1461);
        days = days % 1461;
        if (days > 365) {
            jy += Math.floor((days - 1) / 365);
            days = (days - 1) % 365;
        }
        var jmDays = [31, 31, 31, 31, 31, 31, 30, 30, 30, 30, 30, 29];
        var i;
        for (i = 0; i < 11 && days >= jmDays[i]; i++) { days -= jmDays[i]; }
        return [jy, i + 1, days + 1];
    }

    function monthLength(jy, jm) {
        if (jm <= 6) return 31;
        if (jm <= 11) return 30;
        var g = toGregorian(jy, 12, 30);
        var back = toJalali(g[0], g[1], g[2]);
        return (back[1] === 12 && back[2] === 30) ? 30 : 29;
    }

    function weekday(jy, jm, jd) {
        var g = toGregorian(jy, jm, jd);
        var d = new Date(g[0], g[1] - 1, g[2]);
        return (d.getDay() + 1) % 7;
    }

    function parseValue(input) {
        var v = (input.value || '').trim().replace(/[۰-۹]/g, function (c) { return '0123456789'['۰۱۲۳۴۵۶۷۸۹'.indexOf(c)]; });
        var m = v.match(/^(\d{4})\/(\d{1,2})\/(\d{1,2})$/);
        return m ? { y: +m[1], m: +m[2], d: +m[3] } : null;
    }

    function fmt(y, m, d) {
        return y + '/' + pad(m) + '/' + pad(d);
    }

    function attach(input, opts) {
        if (!input || input._jcalAttached) return;
        input._jcalAttached = true;
        opts = opts || {};
        var today = opts.today || (function () {
            var now = new Date();
            var j = toJalali(now.getFullYear(), now.getMonth() + 1, now.getDate());
            return { y: j[0], m: j[1], d: j[2] };
        })();
        var pairFrom = opts.pairFrom || null;
        var pairTo = opts.pairTo || null;
        var role = opts.role || ''; // 'from' | 'to' | ''

        var popup = null;
        var mode = 'days'; // days | months

        function position() {
            if (!popup) return;
            var r = input.getBoundingClientRect();
            popup.style.top  = (window.scrollY + r.bottom + 4) + 'px';
            popup.style.left = (window.scrollX + r.left) + 'px';
        }

        function outside(e) {
            if (popup && !popup.contains(e.target) && e.target !== input) close();
        }

        function close() {
            if (!popup) return;
            popup.parentNode.removeChild(popup);
            popup = null;
            mode = 'days';
            document.removeEventListener('mousedown', outside, true);
            window.removeEventListener('resize', position);
        }

        function setDay(y, m, d) {
            input.value = fmt(y, m, d);
            input.dispatchEvent(new Event('change', { bubbles: true }));
            close();
        }

        function setWholeMonth(y, m) {
            var fromEl = pairFrom || (role === 'from' ? input : null);
            var toEl = pairTo || (role === 'to' ? input : null);
            if (fromEl && toEl) {
                fromEl.value = fmt(y, m, 1);
                toEl.value = fmt(y, m, monthLength(y, m));
                fromEl.dispatchEvent(new Event('change', { bubbles: true }));
                toEl.dispatchEvent(new Event('change', { bubbles: true }));
            } else if (role === 'to') {
                input.value = fmt(y, m, monthLength(y, m));
                input.dispatchEvent(new Event('change', { bubbles: true }));
            } else {
                input.value = fmt(y, m, 1);
                input.dispatchEvent(new Event('change', { bubbles: true }));
            }
            close();
        }

        function renderMonths(view) {
            popup.innerHTML = '';
            var header = document.createElement('div');
            header.className = 'jcal-header';
            var prev = document.createElement('button');
            prev.type = 'button'; prev.className = 'jcal-nav'; prev.textContent = '›';
            var label = document.createElement('span');
            label.className = 'jcal-label';
            label.textContent = 'سال ' + view.y;
            label.title = 'بازگشت به روزها';
            var next = document.createElement('button');
            next.type = 'button'; next.className = 'jcal-nav'; next.textContent = '‹';
            prev.onclick = function () { view.y--; renderMonths(view); };
            next.onclick = function () { view.y++; renderMonths(view); };
            label.onclick = function () { mode = 'days'; render(view, parseValue(input) || today); };
            header.appendChild(prev);
            header.appendChild(label);
            header.appendChild(next);
            popup.appendChild(header);

            var tip = document.createElement('div');
            tip.className = 'jcal-tip';
            tip.textContent = 'یک ماه را انتخاب کنید (کل ماه پر می‌شود)';
            popup.appendChild(tip);

            var grid = document.createElement('div');
            grid.className = 'jcal-months';
            MONTH_NAMES.forEach(function (name, idx) {
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'jcal-month';
                if (today.y === view.y && today.m === idx + 1) btn.className += ' jcal-today';
                btn.textContent = name;
                btn.onclick = function () { setWholeMonth(view.y, idx + 1); };
                grid.appendChild(btn);
            });
            popup.appendChild(grid);
        }

        function render(view, selected) {
            if (mode === 'months') {
                renderMonths(view);
                return;
            }
            popup.innerHTML = '';

            var header = document.createElement('div');
            header.className = 'jcal-header';
            var prev = document.createElement('button');
            prev.type = 'button'; prev.className = 'jcal-nav'; prev.textContent = '›';
            var label = document.createElement('button');
            label.type = 'button';
            label.className = 'jcal-label jcal-label-btn';
            label.textContent = MONTH_NAMES[view.m - 1] + ' ' + view.y;
            label.title = 'انتخاب ماه';
            var next = document.createElement('button');
            next.type = 'button'; next.className = 'jcal-nav'; next.textContent = '‹';

            prev.onclick = function () { view.m--; if (view.m < 1) { view.m = 12; view.y--; } render(view, selected); };
            next.onclick = function () { view.m++; if (view.m > 12) { view.m = 1; view.y++; } render(view, selected); };
            label.onclick = function () { mode = 'months'; renderMonths({ y: view.y, m: view.m }); };

            header.appendChild(prev);
            header.appendChild(label);
            header.appendChild(next);
            popup.appendChild(header);

            var tip = document.createElement('div');
            tip.className = 'jcal-tip';
            tip.textContent = 'روی نام ماه کلیک کنید تا کل ماه انتخاب شود';
            popup.appendChild(tip);

            var grid = document.createElement('div');
            grid.className = 'jcal-grid';
            WEEK_DAYS.forEach(function (w) {
                var el = document.createElement('div');
                el.className = 'jcal-wd';
                el.textContent = w;
                grid.appendChild(el);
            });

            var startWd = weekday(view.y, view.m, 1);
            for (var i = 0; i < startWd; i++) {
                var blank = document.createElement('div');
                blank.className = 'jcal-blank';
                grid.appendChild(blank);
            }

            var len = monthLength(view.y, view.m);
            for (var d = 1; d <= len; d++) {
                (function (dd) {
                    var cell = document.createElement('button');
                    cell.type = 'button';
                    cell.className = 'jcal-day';
                    if (selected && selected.y === view.y && selected.m === view.m && selected.d === dd) cell.className += ' jcal-selected';
                    if (today.y === view.y && today.m === view.m && today.d === dd) cell.className += ' jcal-today';
                    cell.textContent = dd;
                    cell.onclick = function () { setDay(view.y, view.m, dd); };
                    grid.appendChild(cell);
                })(d);
            }
            popup.appendChild(grid);

            var footer = document.createElement('div');
            footer.className = 'jcal-footer';
            var monthBtn = document.createElement('button');
            monthBtn.type = 'button'; monthBtn.className = 'jcal-footer-btn';
            monthBtn.textContent = 'کل این ماه';
            monthBtn.onclick = function () { setWholeMonth(view.y, view.m); };
            var todayBtn = document.createElement('button');
            todayBtn.type = 'button'; todayBtn.className = 'jcal-footer-btn';
            todayBtn.textContent = 'امروز';
            todayBtn.onclick = function () { setDay(today.y, today.m, today.d); };
            var clearBtn = document.createElement('button');
            clearBtn.type = 'button'; clearBtn.className = 'jcal-footer-btn';
            clearBtn.textContent = 'پاک کردن';
            clearBtn.onclick = function () {
                input.value = '';
                input.dispatchEvent(new Event('change', { bubbles: true }));
                close();
            };
            footer.appendChild(monthBtn);
            footer.appendChild(todayBtn);
            footer.appendChild(clearBtn);
            popup.appendChild(footer);
        }

        function open() {
            if (popup) return;
            if (input.readOnly && input.hasAttribute('data-wap-date-locked')) return;
            var val = parseValue(input) || today;
            mode = 'days';
            popup = document.createElement('div');
            popup.className = 'jcal-popup';
            document.body.appendChild(popup);
            position();
            render({ y: val.y, m: val.m }, val);
            document.addEventListener('mousedown', outside, true);
            window.addEventListener('resize', position);
        }

        if (!input.hasAttribute('data-wap-date-locked')) {
            input.setAttribute('readonly', 'readonly');
        }
        input.setAttribute('inputmode', 'none');
        input.addEventListener('focus', open);
        input.addEventListener('click', open);
    }

    global.attachJalaliDatePicker = attach;
    global.WAP_JalaliCal = {
        monthLength: monthLength,
        monthNames: MONTH_NAMES,
        format: fmt,
        parse: parseValue,
        toJalali: toJalali
    };
})(window);
