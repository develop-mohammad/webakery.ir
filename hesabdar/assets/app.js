(function () {
    'use strict';

    // تقویم شمسی برای فیلدهای تاریخ
    if (window.attachJalaliDatePicker) {
        ['wap_date_from', 'wap_date_to'].forEach(function (id) {
            var el = document.getElementById(id);
            if (el) attachJalaliDatePicker(el, { today: window.WAP_TODAY });
        });
    }

    // چیپ‌های بازه سریع
    document.querySelectorAll('.wap-chip').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var from = document.getElementById('wap_date_from');
            var to   = document.getElementById('wap_date_to');
            if (from) from.value = btn.getAttribute('data-from');
            if (to)   to.value   = btn.getAttribute('data-to');
            document.querySelectorAll('.wap-chip').forEach(function (c) {
                c.classList.remove('is-active');
            });
            btn.classList.add('is-active');
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

    // خروجی تصویری گزارش (JPG/PNG) — با html2canvas برای نمودارها و کارت‌ها
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

    function wapCaptureTarget(btn) {
        var sel = btn.getAttribute('data-target') || '#wap_capture';
        return document.querySelector(sel);
    }

    function wapExportImage(btn, format) {
        var target = wapCaptureTarget(btn);
        if (!target) {
            wapToast('بخش گزارش برای خروجی تصویر یافت نشد.');
            return;
        }
        var label = btn.getAttribute('data-label') || 'گزارش';
        var orig = btn.textContent;
        btn.disabled = true;
        btn.textContent = 'در حال ساخت تصویر…';

        wapLoadHtml2Canvas(function () {
            var hideNodes = [];
            target.querySelectorAll('[data-wap-no-capture], .wap-export-bar, .wap-bulk-bar, .wap-pagination').forEach(function (el) {
                hideNodes.push({ el: el, display: el.style.display });
                el.style.display = 'none';
            });
            var prevBg = target.style.background;
            target.classList.add('is-capturing');
            target.style.background = '#ffffff';
            window.html2canvas(target, {
                backgroundColor: '#ffffff',
                scale: Math.min(2, window.devicePixelRatio || 2),
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
                hideNodes.forEach(function (item) {
                    item.el.style.display = item.display;
                });
                var mime = format === 'png' ? 'image/png' : 'image/jpeg';
                var ext = format === 'png' ? 'png' : 'jpg';
                var quality = format === 'png' ? undefined : 0.92;
                var link = document.createElement('a');
                var stamp = new Date().toISOString().slice(0, 10);
                link.download = 'hesabdar-' + label + '-' + stamp + '.' + ext;
                link.href = quality ? canvas.toDataURL(mime, quality) : canvas.toDataURL(mime);
                link.click();
                btn.disabled = false;
                btn.textContent = orig;
                wapToast('خروجی تصویری آماده شد.');
            }).catch(function () {
                target.style.background = prevBg;
                target.classList.remove('is-capturing');
                hideNodes.forEach(function (item) {
                    item.el.style.display = item.display;
                });
                btn.disabled = false;
                btn.textContent = orig;
                wapToast('ساخت تصویر با خطا مواجه شد.');
            });
        });
    }

    document.querySelectorAll('[data-wap-export-image]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            wapExportImage(btn, btn.getAttribute('data-format') || 'jpg');
        });
    });
    // سازگاری با دکمه‌های قدیمی
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
})();
