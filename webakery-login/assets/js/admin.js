(function () {
  'use strict';

  var provider = document.getElementById('sms_provider');
  function syncProvider() {
    if (!provider) return;
    var isMeli = provider.value === 'melipayamak';
    document.querySelectorAll('.wbl-melipayamak-only').forEach(function (row) {
      row.style.display = isMeli ? '' : 'none';
    });
    document.querySelectorAll('.wbl-api-key-row').forEach(function (row) {
      row.style.display = isMeli ? 'none' : '';
    });
  }
  if (provider) {
    provider.addEventListener('change', syncProvider);
    syncProvider();
  }

  var btn = document.getElementById('wbl-test-sms');
  var phone = document.getElementById('wbl-test-phone');
  var result = document.getElementById('wbl-test-result');
  if (!btn) return;

  btn.addEventListener('click', function () {
    if (!window.WBLAdmin) return;
    result.className = '';
    result.textContent = 'در حال ارسال…';
    var body = new FormData();
    body.append('action', 'wbl_test_sms');
    body.append('nonce', WBLAdmin.nonce);
    body.append('phone', phone ? phone.value : '');
    fetch(WBLAdmin.ajax, { method: 'POST', credentials: 'same-origin', body: body })
      .then(function (r) {
        return r.json();
      })
      .then(function (res) {
        if (res && res.success) {
          result.className = 'ok';
          result.textContent = res.data.message;
        } else {
          result.className = 'err';
          result.textContent = (res && res.data && res.data.message) || 'خطا';
        }
      })
      .catch(function () {
        result.className = 'err';
        result.textContent = 'خطای ارتباط';
      });
  });
})();
