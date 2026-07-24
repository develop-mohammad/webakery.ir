(function () {
  'use strict';
  if (typeof WBCB_ADMIN === 'undefined') return;

  var result = document.getElementById('wbcb-test-result');

  function post(action, data) {
    var body = new FormData();
    body.append('action', action);
    body.append('nonce', WBCB_ADMIN.nonce);
    Object.keys(data || {}).forEach(function (k) {
      body.append(k, data[k] == null ? '' : String(data[k]));
    });
    return fetch(WBCB_ADMIN.ajax, { method: 'POST', body: body, credentials: 'same-origin' })
      .then(function (r) { return r.json(); });
  }

  function setResult(msg, ok) {
    if (!result) return;
    result.textContent = msg || '';
    result.className = 'wbcb-test-result ' + (ok ? 'is-ok' : 'is-error');
  }

  document.querySelectorAll('.wbcb-test-notify').forEach(function (btn) {
    btn.addEventListener('click', function (e) {
      e.preventDefault();
      var channel = btn.getAttribute('data-channel') || 'all';
      btn.disabled = true;
      setResult(WBCB_ADMIN.i18n.testing || 'در حال ارسال…', true);
      post('wbcb_test_notify', { channel: channel }).then(function (res) {
        btn.disabled = false;
        if (!res || !res.success) {
          setResult((res && res.data && res.data.message) || WBCB_ADMIN.i18n.error, false);
          return;
        }
        setResult(res.data.message || 'ارسال شد', true);
      }).catch(function () {
        btn.disabled = false;
        setResult(WBCB_ADMIN.i18n.error, false);
      });
    });
  });
})();
