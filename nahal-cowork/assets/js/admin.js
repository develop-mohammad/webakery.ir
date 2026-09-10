(function () {
  'use strict';

  function post(action, data) {
    var body = new FormData();
    body.append('action', action);
    body.append('nonce', (window.NCKAdmin && NCKAdmin.nonce) || '');
    Object.keys(data || {}).forEach(function (k) {
      body.append(k, data[k]);
    });
    return fetch((window.NCKAdmin && NCKAdmin.ajax) || ajaxurl, {
      method: 'POST',
      credentials: 'same-origin',
      body: body,
    }).then(function (r) { return r.json(); });
  }

  document.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-nck-status]');
    if (!btn) return;
    e.preventDefault();
    post('nck_admin_status', {
      member_id: btn.getAttribute('data-nck-status'),
      status: btn.getAttribute('data-to'),
    }).then(function () {
      window.location.reload();
    });
  });

  document.addEventListener('submit', function (e) {
    var form = e.target.closest('[data-nck-admin-checkin]');
    if (!form) return;
    e.preventDefault();
    var msg = document.querySelector('[data-nck-admin-msg]');
    post('nck_admin_checkin', {
      member_id: form.member_id.value,
      shift: form.shift.value,
      date: form.date.value,
    }).then(function (res) {
      if (!msg) return;
      msg.hidden = false;
      msg.textContent = (res && res.data && res.data.message) || 'خطا';
      if (res && res.success) {
        window.setTimeout(function () { window.location.reload(); }, 700);
      }
    });
  });
})();
