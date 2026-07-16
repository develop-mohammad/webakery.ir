/**
 * Hesabdar — تغییر وضعیت سفارش با کرکره (accordion)
 */
(function () {
    'use strict';

    var cfg = window.WCI_ORDER_STATUS || (window.WAP_SHEETS && window.WAP_SHEETS.orderStatus) || null;
    if (!cfg || !cfg.ajaxUrl || !cfg.nonce) {
        return;
    }

    var openAccordion = null;

    function toast(message) {
        var el = document.createElement('div');
        el.className = 'wap-toast is-visible';
        el.textContent = message;
        el.style.cssText = 'position:fixed;bottom:24px;left:50%;transform:translateX(-50%);z-index:99999;';
        document.body.appendChild(el);
        setTimeout(function () {
            el.classList.remove('is-visible');
            setTimeout(function () { el.remove(); }, 350);
        }, 2800);
    }

    function prefixFrom(root) {
        return (root.getAttribute('data-context') || 'wci') === 'wap' ? 'wap' : 'wci';
    }

    function statusClasses(prefix, status) {
        if (prefix === 'wap') {
            return 'wap-status-trigger wap-status-' + status;
        }
        return 'wci-status-trigger wci-status wci-status--' + status;
    }

    function currentStatusFromTrigger(trigger, prefix) {
        if (!trigger || !trigger.className) return '';
        var re = prefix === 'wap'
            ? /wap-status-([a-z-]+)/
            : /wci-status--([a-z-]+)/;
        var m = trigger.className.match(re);
        return m ? m[1] : '';
    }

    function postStatus(orderId, status) {
        var body = new URLSearchParams({
            action: 'wci_update_order_status',
            nonce: cfg.nonce,
            order_id: String(orderId),
            status: status
        });
        return fetch(cfg.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            body: body.toString()
        }).then(function (r) { return r.json(); });
    }

    function closeAccordion(root) {
        if (!root) return;
        var panel = root.querySelector('[class$="-status-panel"]');
        var trigger = root.querySelector('[class$="-status-trigger"]');
        if (panel) panel.hidden = true;
        if (trigger) {
            trigger.setAttribute('aria-expanded', 'false');
            trigger.classList.remove('is-open');
        }
        root.classList.remove('is-open');
        if (openAccordion === root) openAccordion = null;
    }

    function closeAllExcept(except) {
        document.querySelectorAll('.wci-status-accordion.is-open, .wap-status-accordion.is-open').forEach(function (el) {
            if (el !== except) closeAccordion(el);
        });
    }

    function openPanel(root) {
        closeAllExcept(root);
        var panel = root.querySelector('[class$="-status-panel"]');
        var trigger = root.querySelector('[class$="-status-trigger"]');
        if (!panel || !trigger) return;
        panel.hidden = false;
        trigger.setAttribute('aria-expanded', 'true');
        trigger.classList.add('is-open');
        root.classList.add('is-open');
        openAccordion = root;
    }

    function updateTrigger(root, prefix, status, label) {
        var trigger = root.querySelector('[class$="-status-trigger"]');
        var labelEl = root.querySelector('[class$="-status-trigger__label"]');
        if (!trigger) return;
        trigger.className = prefix + '-status-trigger ' + statusClasses(prefix, status);
        if (labelEl && label) labelEl.textContent = label;
    }

    function syncActiveOptions(root, prefix, status) {
        root.querySelectorAll('[class$="-status-option"]').forEach(function (btn) {
            var val = btn.getAttribute('data-status');
            var active = val === status;
            if (prefix === 'wap') {
                btn.className = 'wap-status-option wap-status-' + val + (active ? ' is-active' : '');
            } else {
                btn.className = 'wci-status-option wci-status--' + val + (active ? ' is-active' : '');
            }
            btn.setAttribute('aria-selected', active ? 'true' : 'false');
            var tick = btn.querySelector('[class$="-status-option__tick"]');
            if (active && !tick) {
                var span = document.createElement('span');
                span.className = prefix + '-status-option__tick';
                span.textContent = '✓';
                btn.appendChild(span);
            } else if (!active && tick) {
                tick.remove();
            }
        });
    }

    function bindAccordion(root) {
        if (root.dataset.wciBound) return;
        root.dataset.wciBound = '1';

        var prefix = prefixFrom(root);
        var trigger = root.querySelector('[class$="-status-trigger"]');
        var panel = root.querySelector('[class$="-status-panel"]');
        if (!trigger || !panel) return;

        trigger.addEventListener('click', function (e) {
            e.stopPropagation();
            if (root.classList.contains('is-loading')) return;
            if (root.classList.contains('is-open')) {
                closeAccordion(root);
            } else {
                openPanel(root);
            }
        });

        panel.querySelectorAll('[class$="-status-option"]').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.stopPropagation();
                if (root.classList.contains('is-loading')) return;

                var orderId = root.getAttribute('data-order-id');
                var next = btn.getAttribute('data-status');
                var currentTrigger = root.querySelector('[class$="-status-trigger"]');
                var previous = currentStatusFromTrigger(currentTrigger, prefix) || next;

                if (next === previous) {
                    closeAccordion(root);
                    return;
                }

                root.classList.add('is-loading');
                trigger.disabled = true;

                postStatus(orderId, next)
                    .then(function (res) {
                        if (res && res.success) {
                            var newStatus = (res.data && res.data.status) ? res.data.status : next;
                            var newLabel = (res.data && res.data.label) ? res.data.label : btn.textContent.replace('✓', '').trim();
                            updateTrigger(root, prefix, newStatus, newLabel);
                            syncActiveOptions(root, prefix, newStatus);
                            closeAccordion(root);
                            if (document.body.classList.contains('wap-body')) {
                                toast((res.data && res.data.message) ? res.data.message : 'وضعیت ذخیره شد');
                            }
                        } else {
                            alert((res && res.data && res.data.message) ? res.data.message : 'خطا در ذخیره وضعیت');
                        }
                    })
                    .catch(function () {
                        alert('خطا در ارتباط با سرور');
                    })
                    .finally(function () {
                        root.classList.remove('is-loading');
                        trigger.disabled = false;
                    });
            });
        });
    }

    function initAll() {
        document.querySelectorAll('.wci-status-accordion, .wap-status-accordion').forEach(bindAccordion);
    }

    document.addEventListener('click', function () {
        closeAllExcept(null);
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeAllExcept(null);
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAll);
    } else {
        initAll();
    }
})();
