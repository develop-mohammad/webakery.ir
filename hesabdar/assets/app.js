(function () {
    'use strict';

    function wapParseJalali(str) {
        if (!str) return null;
        var v = String(str).trim().replace(/[۰-۹]/g, function (c) {
            return '0123456789'['۰۱۲۳۴۵۶۷۸۹'.indexOf(c)];
        });
        var m = v.match(/^(\d{4})\/(\d{1,2})\/(\d{1,2})$/);
        if (!m) return null;
        return { y: +m[1], m: +m[2], d: +m[3] };
    }

    function wapFmtJalali(y, m, d) {
        function pad(n) { return n < 10 ? '0' + n : '' + n; }
        return y + '/' + pad(m) + '/' + pad(d);
    }

    function wapDateFields() {
        return {
            from: document.getElementById('wap_date_from'),
            to: document.getElementById('wap_date_to'),
            cmpFrom: document.getElementById('wap_compare_from'),
            cmpTo: document.getElementById('wap_compare_to')
        };
    }

    function wapAttachCalendars() {
        if (!window.attachJalaliDatePicker) return;
        var f = wapDateFields();
        var today = window.WAP_TODAY;
        if (f.from) {
            attachJalaliDatePicker(f.from, {
                today: today,
                role: 'from',
                pairFrom: f.from,
                pairTo: f.to
            });
        }
        if (f.to) {
            attachJalaliDatePicker(f.to, {
                today: today,
                role: 'to',
                pairFrom: f.from,
                pairTo: f.to
            });
        }
        if (f.cmpFrom) {
            attachJalaliDatePicker(f.cmpFrom, {
                today: today,
                role: 'from',
                pairFrom: f.cmpFrom,
                pairTo: f.cmpTo
            });
        }
        if (f.cmpTo) {
            attachJalaliDatePicker(f.cmpTo, {
                today: today,
                role: 'to',
                pairFrom: f.cmpFrom,
                pairTo: f.cmpTo
            });
        }
    }
    wapAttachCalendars();

    function wapSubmitFilters() {
        var form = document.querySelector('form.wap-filters');
        if (!form) return;
        if (typeof form.requestSubmit === 'function') {
            form.requestSubmit();
        } else {
            form.submit();
        }
    }

    function wapMonthLength(y, m) {
        if (window.WAP_JalaliCal && WAP_JalaliCal.monthLength) {
            return WAP_JalaliCal.monthLength(y, m);
        }
        return m <= 6 ? 31 : (m <= 11 ? 30 : 29);
    }

    function wapShiftMonthParts(p, delta) {
        var y = p.y;
        var m = p.m + delta;
        while (m < 1) { m += 12; y--; }
        while (m > 12) { m -= 12; y++; }
        var d = Math.min(p.d, wapMonthLength(y, m));
        return { y: y, m: m, d: d };
    }

    function wapFillSameLastYear() {
        var f = wapDateFields();
        if (!f.from || !f.to || !f.cmpFrom || !f.cmpTo) return false;
        var pf = wapParseJalali(f.from.value);
        var pt = wapParseJalali(f.to.value);
        if (!pf || !pt) return false;
        var y1 = pf.y - 1;
        var y2 = pt.y - 1;
        var d1 = Math.min(pf.d, wapMonthLength(y1, pf.m));
        var d2 = Math.min(pt.d, wapMonthLength(y2, pt.m));
        f.cmpFrom.value = wapFmtJalali(y1, pf.m, d1);
        f.cmpTo.value = wapFmtJalali(y2, pt.m, d2);
        return true;
    }

    /** پر کردن بازه مقایسه با ماه قبلِ بازه اصلی (دو خط روی‌هم). */
    function wapFillPreviousMonth() {
        var f = wapDateFields();
        if (!f.from || !f.to || !f.cmpFrom || !f.cmpTo) return false;
        var pf = wapParseJalali(f.from.value);
        var pt = wapParseJalali(f.to.value);
        if (!pf || !pt) return false;
        var fullMonth = pf.y === pt.y && pf.m === pt.m
            && pf.d === 1 && pt.d === wapMonthLength(pt.y, pt.m);
        if (fullMonth) {
            var prev = wapShiftMonthParts({ y: pf.y, m: pf.m, d: 1 }, -1);
            f.cmpFrom.value = wapFmtJalali(prev.y, prev.m, 1);
            f.cmpTo.value = wapFmtJalali(prev.y, prev.m, wapMonthLength(prev.y, prev.m));
            return true;
        }
        var a = wapShiftMonthParts(pf, -1);
        var b = wapShiftMonthParts(pt, -1);
        f.cmpFrom.value = wapFmtJalali(a.y, a.m, a.d);
        f.cmpTo.value = wapFmtJalali(b.y, b.m, b.d);
        return true;
    }

    // چیپ‌های بازه سریع (بازه اصلی) — بلافاصله اعمال + مقایسه ماه قبل
    document.querySelectorAll('#wap_presets .wap-chip').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var from = document.getElementById('wap_date_from');
            var to   = document.getElementById('wap_date_to');
            if (from) from.value = btn.getAttribute('data-from');
            if (to)   to.value   = btn.getAttribute('data-to');
            document.querySelectorAll('#wap_presets .wap-chip').forEach(function (c) {
                c.classList.remove('is-active');
            });
            btn.classList.add('is-active');
            wapFillPreviousMonth();
            wapSubmitFilters();
        });
    });

    // چیپ‌های انتخاب ماه (اصلی / مقایسه) — بلافاصله اعمال
    document.querySelectorAll('[data-wap-month-target]').forEach(function (wrap) {
        var target = wrap.getAttribute('data-wap-month-target');
        wrap.querySelectorAll('.wap-chip-month').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var fromId = target === 'compare' ? 'wap_compare_from' : 'wap_date_from';
                var toId = target === 'compare' ? 'wap_compare_to' : 'wap_date_to';
                var from = document.getElementById(fromId);
                var to = document.getElementById(toId);
                if (from) from.value = btn.getAttribute('data-from');
                if (to) to.value = btn.getAttribute('data-to');
                wrap.querySelectorAll('.wap-chip-month').forEach(function (c) {
                    c.classList.remove('is-active');
                });
                btn.classList.add('is-active');
                if (target === 'primary') {
                    wapFillPreviousMonth();
                }
                wapSubmitFilters();
            });
        });
    });

    // یک‌کلیک: ماه قبل + اعمال فیلتر (دو خط روی‌هم)
    document.querySelectorAll('[data-wap-compare-previous-month]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (!wapFillPreviousMonth()) {
                wapToast('ابتدا بازه اصلی (از/تا) را انتخاب کنید، بعد «ماه قبل» را بزنید.');
                return;
            }
            wapSubmitFilters();
        });
    });

    // یک‌کلیک: ماه مشابه پارسال + اعمال فیلتر
    document.querySelectorAll('[data-wap-compare-same-last-year]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (!wapFillSameLastYear()) {
                wapToast('ابتدا بازه اصلی (از/تا) را انتخاب کنید، بعد «ماه مشابه پارسال» را بزنید.');
                return;
            }
            wapSubmitFilters();
        });
    });

    function wapValidateDateForm(form) {
        var from = form.querySelector('[name="date_from"]');
        var to = form.querySelector('[name="date_to"]');
        var cmpFrom = form.querySelector('[name="compare_from"]');
        var cmpTo = form.querySelector('[name="compare_to"]');
        if (!from || !to) return true;

        function swapIfNeeded(a, b, label) {
            var pa = wapParseJalali(a.value);
            var pb = wapParseJalali(b.value);
            if (pa && pb) {
                var ta = pa.y * 10000 + pa.m * 100 + pa.d;
                var tb = pb.y * 10000 + pb.m * 100 + pb.d;
                if (ta > tb) {
                    var tmp = a.value;
                    a.value = b.value;
                    b.value = tmp;
                    wapToast(label + ' برعکس بود و اصلاح شد.');
                }
            } else if ((a.value && !pa) || (b.value && !pb)) {
                wapToast(label + ' نامعتبر است. از تقویم یا لیست ماه‌ها انتخاب کنید.');
                return false;
            }
            return true;
        }

        if (!swapIfNeeded(from, to, 'بازه اصلی')) return false;
        if (cmpFrom && cmpTo) {
            var cf = (cmpFrom.value || '').trim();
            var ct = (cmpTo.value || '').trim();
            if ((cf && !ct) || (!cf && ct)) {
                wapToast('برای مقایسه هر دو فیلد «مقایسه از» و «مقایسه تا» لازم است.');
                return false;
            }
            if (cf && ct && !swapIfNeeded(cmpFrom, cmpTo, 'بازه مقایسه')) return false;
        }
        return true;
    }

    document.querySelectorAll('form.wap-filters').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            // فیلدهای عددی خالی را نفرست تا فیلتر اشتباه اعمال نشود
            form.querySelectorAll('input[type="number"]').forEach(function (inp) {
                if (String(inp.value || '').trim() === '') {
                    inp.disabled = true;
                }
            });
            if (!wapValidateDateForm(form)) {
                form.querySelectorAll('input[type="number"]').forEach(function (inp) {
                    inp.disabled = false;
                });
                e.preventDefault();
            }
        });
    });

    // سایه هدر هنگام اسکرول
    var header = document.getElementById('wap-header');
    if (header) {
        var onScroll = function () {
            header.classList.toggle('is-scrolled', window.scrollY > 8);
        };
        onScroll();
        window.addEventListener('scroll', onScroll, { passive: true });
    }

    function wapToast(message) {
        var toast = document.createElement('div');
        toast.className = 'wap-toast';
        toast.textContent = message;
        document.body.appendChild(toast);
        requestAnimationFrame(function () { toast.classList.add('is-visible'); });
        setTimeout(function () {
            toast.classList.remove('is-visible');
            setTimeout(function () { toast.remove(); }, 350);
        }, 5000);
    }

    function wapCopyText(text) {
        if (navigator.clipboard && window.isSecureContext) {
            return navigator.clipboard.writeText(text);
        }
        return new Promise(function (resolve, reject) {
            var ta = document.createElement('textarea');
            ta.value = text;
            ta.setAttribute('readonly', '');
            ta.style.position = 'fixed';
            ta.style.left = '-9999px';
            document.body.appendChild(ta);
            ta.select();
            try {
                document.execCommand('copy');
                resolve();
            } catch (err) {
                reject(err);
            } finally {
                document.body.removeChild(ta);
            }
        });
    }

    function wapSheetsCfg() {
        return window.WAP_SHEETS || {};
    }

    function wapCloseModal() {
        var m = document.getElementById('wap-sheets-modal');
        if (m) m.remove();
    }

    function wapEscapeCsvCell(val) {
        var s = String(val == null ? '' : val);
        if (/[",\n\r]/.test(s)) {
            return '"' + s.replace(/"/g, '""') + '"';
        }
        return s;
    }

    function wapRowsToCsv(rows) {
        return rows.map(function (row) {
            return row.map(wapEscapeCsvCell).join(',');
        }).join('\r\n');
    }

    function wapRowsToTsv(rows) {
        return rows.map(function (row) {
            return row.map(function (c) {
                return String(c == null ? '' : c).replace(/\t/g, ' ').replace(/\r?\n/g, ' ');
            }).join('\t');
        }).join('\n');
    }

    function wapDownloadCsv(rows, filename) {
        var bom = '\uFEFF';
        var blob = new Blob([bom + wapRowsToCsv(rows)], { type: 'text/csv;charset=utf-8;' });
        var url = URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url;
        a.download = filename || ('hesabdar-' + new Date().toISOString().slice(0, 10) + '.csv');
        document.body.appendChild(a);
        a.click();
        setTimeout(function () {
            URL.revokeObjectURL(url);
            a.remove();
        }, 1000);
    }

    function wapShowImportGuide() {
        wapCloseModal();
        var modal = document.createElement('div');
        modal.id = 'wap-sheets-modal';
        modal.className = 'wap-modal';
        modal.innerHTML =
            '<div class="wap-modal-backdrop" data-close></div>' +
            '<div class="wap-modal-box" role="dialog" aria-modal="true">' +
            '<h2>✅ فایل آماده است</h2>' +
            '<p>فایل CSV دانلود شد و Google Sheets باز می‌شود. الان فقط این ۳ کار را بکنید:</p>' +
            '<ol class="wap-modal-steps">' +
            '<li>در شیت بروید به <b>File</b> → <b>Import</b> (فایل ← وارد کردن)</li>' +
            '<li>تب <b>Upload</b> ← فایل CSV دانلودشده را انتخاب کنید</li>' +
            '<li><b>Import data</b> را بزنید — تمام!</li>' +
            '</ol>' +
            '<p class="wap-modal-hint">اگر شیت باز نشد، VPN را برای docs.google.com روشن کنید. برای Cloud Console نیازی نیست.</p>' +
            '<a class="wap-btn wap-btn-sheets" href="https://docs.google.com/spreadsheets/create" target="_blank" rel="noopener">باز کردن Google Sheets</a>' +
            '<button type="button" class="wap-btn wap-btn-ghost" data-close>متوجه شدم</button>' +
            '</div>';
        document.body.appendChild(modal);
        modal.querySelectorAll('[data-close]').forEach(function (el) {
            el.addEventListener('click', wapCloseModal);
        });
    }

    function wapRunSheetsExport(triggerBtn) {
        var cfg = wapSheetsCfg();
        if (!cfg.ajaxUrl) {
            wapToast('پیکربندی یافت نشد. صفحه را رفرش کنید.');
            return;
        }
        if (triggerBtn) {
            triggerBtn.disabled = true;
            triggerBtn.dataset.prev = triggerBtn.textContent;
            triggerBtn.textContent = 'در حال آماده‌سازی…';
        }
        var body = new FormData();
        body.append('action', 'wap_export_google_sheets');
        body.append('nonce', cfg.nonce || '');
        body.append('wap_view', cfg.view || 'sales');
        if (cfg.productId) body.append('product_id', String(cfg.productId));
        var q = cfg.query || {};
        Object.keys(q).forEach(function (key) {
            if (key === 'wap_view' || key === 'product_id') return;
            if (q[key] !== undefined && q[key] !== null && q[key] !== '') {
                body.append(key, q[key]);
            }
        });

        fetch(cfg.ajaxUrl, { method: 'POST', body: body, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (json) {
                if (!json || !json.success) {
                    throw new Error((json && json.data && json.data.message) || 'خروجی ناموفق');
                }
                var data = json.data || {};
                var rows = data.rows || [];
                if (!rows.length) {
                    throw new Error('داده‌ای برای خروجی نیست.');
                }

                // ۱) دانلود CSV روی دستگاه کاربر (بدون Google Cloud)
                wapDownloadCsv(rows, 'hesabdar-export.csv');

                // ۲) کپی جدول برای Paste اختیاری
                wapCopyText(wapRowsToTsv(rows)).catch(function () { /* نادیده */ });

                // ۳) باز کردن شیت + راهنما
                setTimeout(function () {
                    window.open('https://docs.google.com/spreadsheets/create', '_blank', 'noopener');
                    wapShowImportGuide();
                }, 400);
            })
            .catch(function (err) {
                wapToast((err && err.message) || 'خطا در خروجی');
            })
            .finally(function () {
                if (triggerBtn) {
                    triggerBtn.disabled = false;
                    triggerBtn.textContent = triggerBtn.dataset.prev || '📊 خروجی گوگل شیت';
                }
            });
    }

    document.querySelectorAll('[data-wap-sheets]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            wapRunSheetsExport(btn);
        });
    });

    // خروجی تصویری پیشرفته گزارش‌ها
    function wapImgCfg() {
        return window.WAP_IMAGE || {};
    }

    function wapLoadHtml2Canvas(cb) {
        if (window.html2canvas) {
            cb();
            return;
        }
        var s = document.createElement('script');
        s.src = 'https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js';
        s.async = true;
        s.onload = function () { cb(); };
        s.onerror = function () {
            wapToast('بارگذاری ابزار خروجی تصویر ناموفق بود.');
        };
        document.head.appendChild(s);
    }

    function wapToolsRoot(btn) {
        return btn.closest('[data-wap-image-tools]') || document.querySelector('[data-wap-image-tools]');
    }

    function wapToolsVal(tools, sel, fallback) {
        if (!tools) return fallback;
        var el = tools.querySelector(sel);
        if (!el) return fallback;
        if (el.type === 'checkbox') return !!el.checked;
        return el.value || fallback;
    }

    function wapCaptureTarget(btn) {
        var sel = btn.getAttribute('data-target') || '#wap_capture';
        return document.querySelector(sel);
    }

    function wapPersianStamp() {
        try {
            return new Date().toLocaleDateString('fa-IR');
        } catch (e) {
            return new Date().toISOString().slice(0, 10);
        }
    }

    function wapSafeFilePart(s) {
        return String(s || '')
            .replace(/[\\/:*?"<>|]+/g, '-')
            .replace(/\s+/g, '-')
            .replace(/-+/g, '-')
            .replace(/^-|-$/g, '') || 'گزارش';
    }

    function wapEnsureWatermark(target, tools) {
        var old = target.querySelector('.wap-capture-watermark');
        if (old) old.remove();
        var cfg = wapImgCfg();
        var note = wapToolsVal(tools, '[data-wap-img-note]', '');
        var cmpFrom = wapToolsVal(tools, '[data-wap-img-compare-from]', cfg.compareFrom || '');
        var cmpTo = wapToolsVal(tools, '[data-wap-img-compare-to]', cfg.compareTo || '');
        var wm = document.createElement('div');
        wm.className = 'wap-capture-watermark';
        wm.setAttribute('data-wap-watermark', '1');
        var range = (cfg.dateFrom || '—') + ' تا ' + (cfg.dateTo || '—');
        var html = '<div><strong>حسابدار</strong> — بازه: ' + range + '</div>';
        html += '<div>تاریخ خروجی: ' + wapPersianStamp() + '</div>';
        if (cmpFrom && cmpTo) {
            html += '<div>مقایسه با: ' + cmpFrom + ' تا ' + cmpTo + '</div>';
        }
        if (note) {
            html += '<div>یادداشت: ' + String(note).replace(/</g, '&lt;') + '</div>';
        }
        wm.innerHTML = html;
        target.insertBefore(wm, target.firstChild);
        return wm;
    }

    function wapApplyScope(target, scope) {
        var changed = [];
        var parts = target.querySelectorAll('[data-wap-capture-part]');
        if (!parts.length) {
            // fallback selectors
            var map = {
                cards: target.querySelectorAll('.wap-cards'),
                chart: target.querySelectorAll('.wap-chart-card, .wap-analytics-grid, .wap-donut-card'),
                table: target.querySelectorAll('.wap-table-wrap')
            };
            Object.keys(map).forEach(function (key) {
                map[key].forEach(function (el) {
                    el.setAttribute('data-wap-capture-part', key);
                });
            });
            parts = target.querySelectorAll('[data-wap-capture-part]');
        }
        if (scope === 'full' || !parts.length) return changed;
        parts.forEach(function (el) {
            var part = el.getAttribute('data-wap-capture-part');
            if (part !== scope) {
                changed.push({ el: el, display: el.style.display });
                el.style.display = 'none';
            }
        });
        return changed;
    }

    function wapInjectCompareCards(target, metrics) {
        var old = target.querySelector('[data-wap-live-compare]');
        if (old) old.remove();
        if (!metrics) return null;
        var box = document.createElement('div');
        box.className = 'wap-cards wap-compare-cards';
        box.setAttribute('data-wap-capture-part', 'cards');
        box.setAttribute('data-wap-live-compare', '1');
        box.innerHTML =
            '<div class="wap-card"><span class="wap-card-label">مقایسه ناخالص (' + metrics.date_from + ' تا ' + metrics.date_to + ')</span>' +
            '<span class="wap-card-value">' + Number(metrics.gross_total || 0).toLocaleString('fa-IR') + '</span>' +
            '<span class="wap-card-accent">' + Number(metrics.gross_count || 0).toLocaleString('fa-IR') + ' سفارش</span></div>' +
            '<div class="wap-card wap-card-net"><span class="wap-card-label">مقایسه خالص</span>' +
            '<span class="wap-card-value">' + Number(metrics.net_total || 0).toLocaleString('fa-IR') + '</span>' +
            '<span class="wap-card-accent">' + Number(metrics.net_count || 0).toLocaleString('fa-IR') + ' سفارش موفق</span></div>';
        var cards = target.querySelector('.wap-cards');
        if (cards && cards.parentNode) {
            cards.parentNode.insertBefore(box, cards.nextSibling);
        } else {
            target.insertBefore(box, target.firstChild);
        }
        return box;
    }

    function wapFetchCompare(tools) {
        return new Promise(function (resolve) {
            var cfg = wapImgCfg();
            var from = wapToolsVal(tools, '[data-wap-img-compare-from]', '');
            var to = wapToolsVal(tools, '[data-wap-img-compare-to]', '');
            if (!from || !to || !cfg.ajaxUrl) {
                resolve(null);
                return;
            }
            // اگر سرور قبلاً رندر کرده، دوباره نگیر
            if (document.querySelector('.wap-compare-cards:not([data-wap-live-compare])')) {
                resolve(null);
                return;
            }
            var body = new FormData();
            body.append('action', 'wap_compare_report_metrics');
            body.append('nonce', cfg.nonce || '');
            body.append('compare_from', from);
            body.append('compare_to', to);
            fetch(cfg.ajaxUrl, { method: 'POST', body: body, credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (json) {
                    if (json && json.success) resolve(json.data);
                    else resolve(null);
                })
                .catch(function () { resolve(null); });
        });
    }

    function wapSliceCanvases(source, maxH) {
        if (!maxH || source.height <= maxH) return [source];
        var pages = [];
        var y = 0;
        while (y < source.height) {
            var h = Math.min(maxH, source.height - y);
            var c = document.createElement('canvas');
            c.width = source.width;
            c.height = h;
            var ctx = c.getContext('2d');
            ctx.fillStyle = '#ffffff';
            ctx.fillRect(0, 0, c.width, c.height);
            ctx.drawImage(source, 0, y, source.width, h, 0, 0, source.width, h);
            pages.push(c);
            y += h;
        }
        return pages;
    }

    function wapDownloadCanvas(canvas, filename, mime, quality) {
        var link = document.createElement('a');
        link.download = filename;
        link.href = quality != null ? canvas.toDataURL(mime, quality) : canvas.toDataURL(mime);
        link.click();
    }

    function wapCopyCanvas(canvas) {
        return new Promise(function (resolve, reject) {
            if (!navigator.clipboard || !window.ClipboardItem) {
                reject(new Error('clipboard'));
                return;
            }
            canvas.toBlob(function (blob) {
                if (!blob) {
                    reject(new Error('blob'));
                    return;
                }
                navigator.clipboard.write([new ClipboardItem({ 'image/png': blob })]).then(resolve).catch(reject);
            }, 'image/png');
        });
    }

    function wapArchiveCanvas(canvas, label, note, mime, quality) {
        var cfg = wapImgCfg();
        return new Promise(function (resolve, reject) {
            if (!cfg.canArchive) {
                reject(new Error('آرشیو برای نقش شما مجاز نیست.'));
                return;
            }
            var dataUrl = quality != null ? canvas.toDataURL(mime, quality) : canvas.toDataURL(mime === 'image/jpeg' ? 'image/jpeg' : 'image/png');
            var body = new FormData();
            body.append('action', 'wap_archive_report_image');
            body.append('nonce', cfg.nonce || '');
            body.append('data_url', dataUrl);
            body.append('label', label || 'report');
            body.append('note', note || '');
            fetch(cfg.ajaxUrl, { method: 'POST', body: body, credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (json) {
                    if (json && json.success) resolve(json.data);
                    else reject(new Error((json && json.data && json.data.message) || 'آرشیو ناموفق'));
                })
                .catch(reject);
        });
    }

    function wapExportImage(btn, format) {
        var target = wapCaptureTarget(btn);
        if (!target) {
            wapToast('بخش گزارش برای خروجی تصویر یافت نشد.');
            return;
        }
        var tools = wapToolsRoot(btn);
        var cfg = wapImgCfg();
        var label = btn.getAttribute('data-label') || cfg.view || 'report';
        var scope = wapToolsVal(tools, '[data-wap-img-scope]', 'full');
        var qualityMode = wapToolsVal(tools, '[data-wap-img-quality]', 'normal');
        var compact = wapToolsVal(tools, '[data-wap-img-compact]', false);
        var multipage = wapToolsVal(tools, '[data-wap-img-multipage]', true);
        var note = wapToolsVal(tools, '[data-wap-img-note]', '');
        var orig = btn.textContent;
        btn.disabled = true;
        btn.textContent = 'در حال ساخت تصویر…';

        wapFetchCompare(tools).then(function (metrics) {
            wapLoadHtml2Canvas(function () {
                var hideNodes = [];
                target.querySelectorAll('[data-wap-no-capture], .wap-export-bar, .wap-bulk-bar, .wap-pagination, .wap-image-export-tools').forEach(function (el) {
                    hideNodes.push({ el: el, display: el.style.display });
                    el.style.display = 'none';
                });
                var scopeChanged = wapApplyScope(target, scope);
                var liveCompare = wapInjectCompareCards(target, metrics);
                var wm = wapEnsureWatermark(target, tools);
                var prevBg = target.style.background;
                target.classList.add('is-capturing');
                if (compact) target.classList.add('is-compact-print');
                target.style.background = '#ffffff';

                // حالت فشرده: مخفی کردن لینک‌ها در capture
                var linkHides = [];
                if (compact) {
                    target.querySelectorAll('a').forEach(function (a) {
                        linkHides.push({ el: a, href: a.getAttribute('href') });
                        a.removeAttribute('href');
                    });
                }

                var scale = qualityMode === 'high' ? Math.min(3, (window.devicePixelRatio || 1) * 2) : Math.min(2, window.devicePixelRatio || 2);
                window.html2canvas(target, {
                    backgroundColor: '#ffffff',
                    scale: scale,
                    useCORS: true,
                    logging: false,
                    scrollX: 0,
                    scrollY: -window.scrollY,
                    ignoreElements: function (el) {
                        return !!(el && el.getAttribute && el.getAttribute('data-wap-no-capture') !== null);
                    }
                }).then(function (canvas) {
                    target.style.background = prevBg;
                    target.classList.remove('is-capturing');
                    target.classList.remove('is-compact-print');
                    hideNodes.forEach(function (item) { item.el.style.display = item.display; });
                    scopeChanged.forEach(function (item) { item.el.style.display = item.display; });
                    linkHides.forEach(function (item) {
                        if (item.href != null) item.el.setAttribute('href', item.href);
                    });
                    if (wm && wm.parentNode) wm.remove();
                    if (liveCompare && liveCompare.parentNode) liveCompare.remove();

                    var mime = (format === 'png' || format === 'clipboard' || format === 'archive') ? 'image/png' : 'image/jpeg';
                    if (format === 'jpg' || format === 'jpeg') mime = 'image/jpeg';
                    var jpegQ = qualityMode === 'high' ? 0.95 : 0.9;
                    var labelFa = cfg.labelFa || label;
                    var rangePart = wapSafeFilePart((cfg.dateFrom || '') + '_' + (cfg.dateTo || ''));
                    var baseName = 'حسابدار-' + wapSafeFilePart(labelFa) + '-' + rangePart + '-' + wapSafeFilePart(wapPersianStamp());

                    var pages = multipage ? wapSliceCanvases(canvas, cfg.multipageMax || 3200) : [canvas];
                    var finishOk = function (msg) {
                        btn.disabled = false;
                        btn.textContent = orig;
                        wapToast(msg || 'خروجی تصویری آماده شد.');
                    };
                    var finishErr = function (msg) {
                        btn.disabled = false;
                        btn.textContent = orig;
                        wapToast(msg || 'ساخت تصویر با خطا مواجه شد.');
                    };

                    if (format === 'clipboard') {
                        wapCopyCanvas(pages[0]).then(function () {
                            finishOk('تصویر در کلیپ‌بورد کپی شد.');
                        }).catch(function () {
                            // fallback: دانلود PNG
                            wapDownloadCanvas(pages[0], baseName + '.png', 'image/png');
                            finishOk('کپی پشتیبانی نشد — PNG دانلود شد.');
                        });
                        return;
                    }

                    if (format === 'archive') {
                        wapArchiveCanvas(pages[0], label, note, mime, mime === 'image/jpeg' ? jpegQ : undefined)
                            .then(function (data) {
                                finishOk((data && data.message) || 'در رسانه ذخیره شد.');
                            })
                            .catch(function (err) {
                                finishErr((err && err.message) || 'آرشیو ناموفق');
                            });
                        return;
                    }

                    var ext = mime === 'image/png' ? 'png' : 'jpg';
                    pages.forEach(function (page, idx) {
                        var name = pages.length > 1 ? (baseName + '-صفحه' + (idx + 1) + '.' + ext) : (baseName + '.' + ext);
                        wapDownloadCanvas(page, name, mime, mime === 'image/jpeg' ? jpegQ : undefined);
                    });
                    finishOk(pages.length > 1 ? (pages.length + ' صفحه تصویر آماده شد.') : 'خروجی تصویری آماده شد.');
                }).catch(function () {
                    target.style.background = prevBg;
                    target.classList.remove('is-capturing');
                    target.classList.remove('is-compact-print');
                    hideNodes.forEach(function (item) { item.el.style.display = item.display; });
                    scopeChanged.forEach(function (item) { item.el.style.display = item.display; });
                    if (wm && wm.parentNode) wm.remove();
                    if (liveCompare && liveCompare.parentNode) liveCompare.remove();
                    btn.disabled = false;
                    btn.textContent = orig;
                    wapToast('ساخت تصویر با خطا مواجه شد.');
                });
            });
        });
    }

    document.querySelectorAll('[data-wap-export-image]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            wapExportImage(btn, btn.getAttribute('data-format') || 'jpg');
        });
    });
    ['wap_export_jpg', 'wap_export_jpeg'].forEach(function (id) {
        var el = document.getElementById(id);
        if (el && !el.hasAttribute('data-wap-export-image')) {
            el.setAttribute('data-wap-export-image', '1');
            el.setAttribute('data-format', 'jpg');
            el.setAttribute('data-label', 'sales');
            el.addEventListener('click', function () {
                wapExportImage(el, 'jpg');
            });
        }
    });

    // قفل بازه تاریخ
    if (wapImgCfg().dateLocked) {
        ['wap_date_from', 'wap_date_to', 'wap_compare_from', 'wap_compare_to'].forEach(function (id) {
            var el = document.getElementById(id);
            if (el) {
                el.readOnly = true;
                el.setAttribute('data-wap-date-locked', '1');
                el.title = 'بازه تاریخ توسط مدیر قفل شده است';
            }
        });
        document.querySelectorAll('.wap-chip, .wap-chip-month, [data-wap-compare-same-last-year]').forEach(function (c) {
            c.style.pointerEvents = 'none';
            c.style.opacity = '0.45';
        });
    }
})();
