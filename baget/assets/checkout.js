(function () {
  'use strict';

  function digits(v) {
    return String(v || '').replace(/\D+/g, '');
  }

  function normalizeIrMobile(v) {
    var d = digits(v);
    if (!d) return '';
    if (d.indexOf('98') === 0 && d.length >= 12) d = '0' + d.slice(2);
    if (/^9\d{9}$/.test(d)) d = '0' + d;
    return /^09\d{9}$/.test(d) ? d : '';
  }

  function ensureBillingPhoneInput() {
    var form = document.querySelector('form.checkout, form.woocommerce-checkout');
    if (!form) return null;
    var input = form.querySelector('#billing_phone, input[name="billing_phone"]');
    if (input) return input;
    input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'billing_phone';
    input.id = 'billing_phone';
    input.setAttribute('data-wccp-synced', '1');
    form.appendChild(input);
    return input;
  }

  function collectPhoneCandidates() {
    var form = document.querySelector('form.checkout, form.woocommerce-checkout');
    if (!form) return [];
    var nodes = form.querySelectorAll(
      'input[type="tel"], input.wccp-maps-billing-phone, .wccp-field-tel input, input[name*="phone"], input[name*="mobile"], input[id*="phone"], input[id*="mobile"]'
    );
    var out = [];
    Array.prototype.forEach.call(nodes, function (el) {
      if (!el || el.name === 'billing_phone') return;
      var n = normalizeIrMobile(el.value);
      if (n) out.push(n);
    });
    return out;
  }

  function syncBillingPhone() {
    var target = ensureBillingPhoneInput();
    if (!target) return;
    var current = normalizeIrMobile(target.value);
    if (current) {
      target.value = current;
      return current;
    }
    var list = collectPhoneCandidates();
    if (list.length) {
      target.value = list[0];
      return list[0];
    }
    return '';
  }

  function bind() {
    var form = document.querySelector('form.checkout, form.woocommerce-checkout');
    if (!form || form.getAttribute('data-wccp-phone-bound') === '1') return;
    form.setAttribute('data-wccp-phone-bound', '1');
    ensureBillingPhoneInput();
    form.addEventListener('input', function (e) {
      var t = e.target;
      if (!t || t.tagName !== 'INPUT') return;
      if (t.type === 'tel' || (t.name && /phone|mobile|شماره/i.test(t.name)) || (t.className && t.className.indexOf('wccp-maps-billing-phone') !== -1)) {
        syncBillingPhone();
      }
    }, true);
    form.addEventListener('change', syncBillingPhone, true);
    form.addEventListener('submit', function () {
      syncBillingPhone();
    }, true);
    // ووکامرس ajax checkout
    if (typeof jQuery !== 'undefined') {
      jQuery(document.body).on('checkout_place_order', function () {
        syncBillingPhone();
        return true;
      });
      jQuery(document.body).on('updated_checkout', function () {
        ensureBillingPhoneInput();
        syncBillingPhone();
      });
    }
    syncBillingPhone();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bind);
  } else {
    bind();
  }
})();
