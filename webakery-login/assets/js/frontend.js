(function () {
  'use strict';

  function qs(root, sel) {
    return root.querySelector(sel);
  }

  function show(el, on) {
    if (!el) return;
    if (on) {
      el.hidden = false;
      el.removeAttribute('hidden');
      el.style.removeProperty('display');
      el.classList.remove('is-enter');
      // reflow for restart animation
      void el.offsetWidth;
      el.classList.add('is-enter');
    } else {
      el.hidden = true;
      el.setAttribute('hidden', '');
      el.classList.remove('is-enter');
    }
  }

  function setAlert(root, type, msg) {
    var err = qs(root, '[data-wbl-error]');
    var ok = qs(root, '[data-wbl-ok]');
    show(err, false);
    show(ok, false);
    if (!msg) return;
    var target = type === 'ok' ? ok : err;
    if (!target) return;
    target.textContent = msg;
    target.hidden = false;
    target.classList.remove('is-shake');
    void target.offsetWidth;
    if (type !== 'ok') target.classList.add('is-shake');
  }

  function setLoading(btn, on, label) {
    if (!btn) return;
    if (on) {
      btn.dataset.oldText = btn.textContent;
      btn.classList.add('is-loading');
      btn.disabled = true;
      btn.textContent = label || '…';
    } else {
      btn.classList.remove('is-loading');
      btn.disabled = false;
      if (btn.dataset.oldText) btn.textContent = btn.dataset.oldText;
    }
  }

  function post(action, data) {
    var body = new FormData();
    body.append('action', action);
    body.append('nonce', (window.WBL && WBL.nonce) || '');
    Object.keys(data || {}).forEach(function (k) {
      body.append(k, data[k]);
    });
    return fetch((window.WBL && WBL.ajax) || '/wp-admin/admin-ajax.php', {
      method: 'POST',
      credentials: 'same-origin',
      body: body,
    }).then(function (r) {
      return r.json();
    });
  }

  function startTimer(btn, seconds, i18n) {
    var left = seconds;
    btn.disabled = true;
    function tick() {
      if (left <= 0) {
        btn.disabled = false;
        btn.textContent = i18n.resend;
        return;
      }
      btn.textContent = (i18n.wait || '').replace('%s', String(left));
      left -= 1;
      setTimeout(tick, 1000);
    }
    tick();
  }

  function bind(root) {
    var phoneForm = qs(root, '[data-wbl-step="phone"]');
    var codeForm = qs(root, '[data-wbl-step="code"]');
    var sendBtn = qs(root, '[data-wbl-send]');
    var verifyBtn = qs(root, '[data-wbl-verify]');
    var resendBtn = qs(root, '[data-wbl-resend]');
    var backBtn = qs(root, '[data-wbl-back]');
    var hint = qs(root, '[data-wbl-hint]');
    var phoneInput = qs(root, 'input[name="phone"]');
    var codeInput = qs(root, 'input[name="code"]');
    var i18n = (window.WBL && WBL.i18n) || {};
    var currentPhone = '';

    if (!phoneForm) return;

    function goCode(msg, wait) {
      show(phoneForm, false);
      show(codeForm, true);
      if (hint) hint.textContent = msg || i18n.enterCode || '';
      if (codeInput) {
        codeInput.value = '';
        codeInput.focus();
      }
      if (resendBtn) startTimer(resendBtn, wait || 60, i18n);
    }

    function goPhone() {
      show(codeForm, false);
      show(phoneForm, true);
      setAlert(root, '', '');
      if (phoneInput) phoneInput.focus();
    }

    function ripple(btn) {
      if (!btn || !window.WBL || (WBL.animation !== 'telegram' && WBL.animation !== 'hybrid')) return;
      btn.classList.remove('wbl-ripple');
      void btn.offsetWidth;
      btn.classList.add('wbl-ripple');
      setTimeout(function () { btn.classList.remove('wbl-ripple'); }, 480);
    }

    phoneForm.addEventListener('submit', function (e) {
      e.preventDefault();
      setAlert(root, '', '');
      var phone = phoneInput ? phoneInput.value.trim() : '';
      if (!phone) return;
      ripple(sendBtn);
      setLoading(sendBtn, true, i18n.sending || '…');
      post('wbl_send_otp', { phone: phone })
        .then(function (res) {
          if (res && res.success) {
            currentPhone = (res.data && res.data.phone) || phone;
            goCode(res.data.message, res.data.wait);
          } else {
            setAlert(root, 'err', (res && res.data && res.data.message) || i18n.error);
          }
        })
        .catch(function () {
          setAlert(root, 'err', i18n.error);
        })
        .finally(function () {
          setLoading(sendBtn, false);
        });
    });

    codeForm &&
      codeForm.addEventListener('submit', function (e) {
        e.preventDefault();
        setAlert(root, '', '');
        var code = codeInput ? codeInput.value.trim() : '';
        if (!code) return;
        ripple(verifyBtn);
        setLoading(verifyBtn, true, i18n.verifying || '…');
        post('wbl_verify_otp', { phone: currentPhone || (phoneInput && phoneInput.value), code: code })
          .then(function (res) {
            if (res && res.success) {
              setAlert(root, 'ok', (res.data && res.data.message) || 'OK');
              var redir = root.getAttribute('data-redirect') || (res.data && res.data.redirect) || '/';
              window.location.href = redir;
            } else {
              setAlert(root, 'err', (res && res.data && res.data.message) || i18n.error);
            }
          })
          .catch(function () {
            setAlert(root, 'err', i18n.error);
          })
          .finally(function () {
            setLoading(verifyBtn, false);
          });
      });

    resendBtn &&
      resendBtn.addEventListener('click', function () {
        if (resendBtn.disabled) return;
        setAlert(root, '', '');
        post('wbl_send_otp', { phone: currentPhone || (phoneInput && phoneInput.value) })
          .then(function (res) {
            if (res && res.success) {
              setAlert(root, 'ok', res.data.message);
              startTimer(resendBtn, res.data.wait || 60, i18n);
            } else {
              setAlert(root, 'err', (res && res.data && res.data.message) || i18n.error);
            }
          })
          .catch(function () {
            setAlert(root, 'err', i18n.error);
          });
      });

    backBtn && backBtn.addEventListener('click', goPhone);
  }

  function init() {
    document.querySelectorAll('[data-wbl-root]').forEach(bind);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
