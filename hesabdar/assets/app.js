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

    // خروجی JPEG — رندر جدول روی canvas در همان مرورگر (بدون کتابخانه خارجی)
    var jpegBtn = document.getElementById('wap_export_jpeg');
    if (jpegBtn) {
        jpegBtn.addEventListener('click', function () {
            var table = document.querySelector('#wap_capture table');
            if (!table) return;

            var rows = Array.prototype.map.call(table.querySelectorAll('tr'), function (tr) {
                return Array.prototype.map.call(tr.querySelectorAll('th,td'), function (cell) {
                    return cell.textContent.trim();
                });
            });

            var colCount = rows[0] ? rows[0].length : 0;
            var colWidth = 220;
            var rowHeight = 40;
            var padding = 24;
            var width = colCount * colWidth + padding * 2;
            var height = rows.length * rowHeight + padding * 2 + 50;

            var canvas = document.createElement('canvas');
            var ratio = window.devicePixelRatio || 1;
            canvas.width = width * ratio;
            canvas.height = height * ratio;
            var ctx = canvas.getContext('2d');
            ctx.scale(ratio, ratio);

            ctx.fillStyle = '#ffffff';
            ctx.fillRect(0, 0, width, height);

            ctx.direction = 'rtl';
            ctx.textAlign = 'right';
            ctx.textBaseline = 'middle';
            ctx.font = '600 16px Vazirmatn, Tahoma, Arial, sans-serif';
            ctx.fillStyle = '#059669';
            ctx.fillText('گزارش فروش — ' + new Date().toLocaleDateString('fa-IR'), width - padding, padding + 10);

            var top = padding + 46;
            rows.forEach(function (cells, rIdx) {
                var y = top + rIdx * rowHeight;
                var isHeader = rIdx === 0;
                ctx.fillStyle = isHeader ? '#059669' : (rIdx % 2 === 0 ? '#f8fafc' : '#ffffff');
                ctx.fillRect(padding, y, width - padding * 2, rowHeight);

                ctx.fillStyle = isHeader ? '#ffffff' : '#0f172a';
                ctx.font = isHeader
                    ? '600 14px Vazirmatn, Tahoma, Arial, sans-serif'
                    : '400 14px Vazirmatn, Tahoma, Arial, sans-serif';

                cells.forEach(function (text, cIdx) {
                    var cellRightEdge = width - padding - cIdx * colWidth - 12;
                    ctx.fillText(text, cellRightEdge, y + rowHeight / 2);
                });
            });

            ctx.strokeStyle = '#e5e9ec';
            for (var r = 0; r <= rows.length; r++) {
                var ly = top + r * rowHeight;
                ctx.beginPath();
                ctx.moveTo(padding, ly);
                ctx.lineTo(width - padding, ly);
                ctx.stroke();
            }

            var link = document.createElement('a');
            link.download = 'sales-report-' + new Date().toISOString().slice(0, 10) + '.jpg';
            link.href = canvas.toDataURL('image/jpeg', 0.95);
            link.click();
        });
    }
})();
