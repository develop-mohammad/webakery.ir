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
    } else {
      el.hidden = true;
      el.setAttribute('hidden', '');
    }
  }

  function setAlert(root, type, msg) {
    var err = qs(root, '[data-nck-error]');
    var ok = qs(root, '[data-nck-ok]');
    show(err, false);
    show(ok, false);
    if (!msg) return;
    var target = type === 'ok' ? ok : err;
    if (!target) return;
    target.textContent = msg;
    show(target, true);
  }

  function setLoading(btn, on, label) {
    if (!btn) return;
    if (on) {
      btn.dataset.oldText = btn.textContent;
      btn.disabled = true;
      btn.textContent = label || '…';
    } else {
      btn.disabled = false;
      if (btn.dataset.oldText) btn.textContent = btn.dataset.oldText;
    }
  }

  function post(action, data) {
    if (window.NCK && typeof NCK.mock === 'function') {
      return NCK.mock(action, data);
    }
    var body = new FormData();
    body.append('action', action);
    body.append('nonce', (window.NCK && NCK.nonce) || '');
    Object.keys(data || {}).forEach(function (k) {
      body.append(k, data[k]);
    });
    return fetch((window.NCK && NCK.ajax) || '/wp-admin/admin-ajax.php', {
      method: 'POST',
      credentials: 'same-origin',
      body: body,
    }).then(function (r) {
      return r.json();
    });
  }

  function honorificLabel(v, root) {
    if (v === 'ms') {
      return (root && root.dataset.msLabel) || 'خانم';
    }
    return 'آقای';
  }

  function bindPad(canvas) {
    if (!canvas) return { drawn: false, clear: function () {}, toDataURL: function () { return ''; } };
    var ctx = canvas.getContext('2d');
    var drawing = false;
    var drawn = false;
    var last = null;

    function pos(e) {
      var r = canvas.getBoundingClientRect();
      var src = e.touches ? e.touches[0] : e;
      var x = (src.clientX - r.left) * (canvas.width / r.width);
      var y = (src.clientY - r.top) * (canvas.height / r.height);
      return { x: x, y: y };
    }

    function paint(e) {
      if (!drawing) return;
      e.preventDefault();
      var p = pos(e);
      ctx.strokeStyle = '#1f241c';
      ctx.lineWidth = 2.4;
      ctx.lineCap = 'round';
      ctx.lineJoin = 'round';
      ctx.beginPath();
      ctx.moveTo(last.x, last.y);
      ctx.lineTo(p.x, p.y);
      ctx.stroke();
      last = p;
      drawn = true;
    }

    canvas.addEventListener('mousedown', function (e) {
      drawing = true;
      last = pos(e);
    });
    canvas.addEventListener('mousemove', paint);
    window.addEventListener('mouseup', function () {
      drawing = false;
    });
    canvas.addEventListener('touchstart', function (e) {
      drawing = true;
      last = pos(e);
    }, { passive: false });
    canvas.addEventListener('touchmove', paint, { passive: false });
    canvas.addEventListener('touchend', function () {
      drawing = false;
    });

    return {
      get drawn() { return drawn; },
      clear: function () {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        drawn = false;
      },
      toDataURL: function () {
        return canvas.toDataURL('image/png');
      }
    };
  }

  function faDigits(v) {
    return String(v).replace(/[0-9]/g, function (d) {
      return '۰۱۲۳۴۵۶۷۸۹'[d];
    });
  }

  function updatePreamble(root) {
    var p = qs(root, '[data-nck-preamble]');
    if (!p || !p.dataset.tpl) return;
    var title = honorificLabel((qs(root, '[name="honorific"]') || {}).value, root);
    var name = ((qs(root, '[name="name"]') || {}).value || '').trim() || '…………………..';
    var phone = ((qs(root, '[name="phone"]') || {}).value || '').trim();
    var org = (window.NCK && NCK.org) || 'مجموعه فرهنگی نهال';
    var filled = p.dataset.tpl
      .replace(/\{\{org\}\}/g, org)
      .replace(/\{\{title\}\}/g, title)
      .replace(/\{\{name\}\}/g, name)
      .replace(/\{\{phone\}\}/g, phone ? faDigits(phone) : '…………………..');
    p.textContent = filled;
  }

  function formatFaMoney(raw) {
    var d = String(raw).replace(/[۰-۹]/g, function (c) {
      return '0123456789'['۰۱۲۳۴۵۶۷۸۹'.indexOf(c)];
    }).replace(/\D+/g, '');
    if (!d) return '';
    var withComma = d.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    return faDigits(withComma) + ' تومان';
  }

  function fillLive(root) {
    var form = qs(root, 'form');
    if (!form) return;
    root.querySelectorAll('[data-nck-live]').forEach(function (el) {
      var key = el.getAttribute('data-nck-live');
      var blank = el.getAttribute('data-blank') || '…………………….';
      var val = '';
      if (key === 'title') {
        val = honorificLabel((qs(form, '[name="honorific"]') || {}).value, root);
      } else {
        var input = qs(form, '[name="' + key + '"]');
        val = input ? String(input.value || '').trim() : '';
        if (key === 'amount' && val) {
          val = formatFaMoney(val);
        } else if (val && (key === 'phone' || key === 'national_id' || key === 'chairs' || key === 'event_date' || key === 'start_hour' || key === 'end_hour')) {
          val = faDigits(val);
        }
      }
      el.textContent = val || blank;
    });
  }

  function bindSignForm(root, formSel, action) {
    var form = qs(root, formSel);
    if (!form) return;
    var pad = bindPad(qs(root, '[data-nck-pad]'));
    var clearBtn = qs(root, '[data-nck-clear]');
    if (clearBtn) {
      clearBtn.addEventListener('click', function () { pad.clear(); });
    }
    ['input', 'change'].forEach(function (ev) {
      form.addEventListener(ev, function () {
        updatePreamble(root);
        fillLive(root);
      });
    });
    fillLive(root);
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var i18n = (window.NCK && NCK.i18n) || {};
      if (!pad.drawn) {
        setAlert(root, 'err', i18n.draw || 'لطفاً داخل کادر امضا کنید.');
        return;
      }
      var btn = qs(form, '[data-nck-submit]');
      setLoading(btn, true, i18n.signing);
      setAlert(root, '', '');
      var fd = {};
      Array.prototype.forEach.call(form.elements, function (el) {
        if (!el.name) return;
        if (el.type === 'radio' && !el.checked) return;
        if (el.type === 'checkbox') {
          fd[el.name] = el.checked ? (el.value || '1') : '';
          return;
        }
        fd[el.name] = el.value;
      });
      fd.signature = pad.toDataURL();
      post(action, fd).then(function (res) {
        setLoading(btn, false);
        if (!res || !res.success) {
          setAlert(root, 'err', (res && res.data && res.data.message) || i18n.error);
          return;
        }
        show(form, false);
        var done = qs(root, '[data-nck-done]');
        show(done, true);
        qs(root, '[data-nck-done-msg]').textContent = res.data.message || '';
        var a = qs(root, '[data-nck-print-link]');
        if (a && res.data.print) a.href = res.data.print;
      }).catch(function () {
        setLoading(btn, false);
        setAlert(root, 'err', i18n.error);
      });
    });
  }

  function bindContract(root) {
    bindSignForm(root, '[data-nck-sign]', 'nck_sign');
  }

  function bindHall(root) {
    bindSignForm(root, '[data-nck-sign-hall]', 'nck_sign_hall');
  }

  function renderCards(root, data) {
    var wrap = qs(root, '[data-nck-cards]');
    if (!wrap) return;
    wrap.innerHTML = '';
    Object.keys(data.shifts || {}).forEach(function (type) {
      var s = data.shifts[type];
      var el = document.createElement('div');
      el.className = 'nck-shift-card' + (s.current ? ' is-current' : '') + (s.open ? '' : ' is-closed');
      var remain = s.has_plan ? faDigits(s.remaining) : '—';
      var used = s.has_plan ? faDigits(s.used + ' از ' + s.quota) : 'بدون اشتراک';
      el.innerHTML = '<span>' + s.label + (s.current ? ' (الان)' : '') + '</span><strong>' + remain + '</strong><small>' + used + (s.already ? ' — امروز ثبت شده' : '') + (s.reason ? ' — ' + s.reason : '') + '</small>';
      wrap.appendChild(el);
    });
  }

  function applyMember(root, data) {
    var box = qs(root, '[data-nck-member]');
    show(box, true);
    qs(root, '[data-nck-member-name]').textContent = data.name || '';
    qs(root, '[data-nck-month]').textContent = 'ماه جاری: ' + (data.month || '');
    renderCards(root, data);
    var cin = qs(root, '[data-nck-checkin]');
    var can = data.slot && data.shifts && data.shifts[data.slot] && data.shifts[data.slot].has_plan && data.shifts[data.slot].open && !data.shifts[data.slot].already && data.status === 'active';
    show(cin, !!can);
    var pr = qs(root, '[data-nck-print]');
    if (pr) {
      if (data.print) {
        pr.href = data.print;
        show(pr, true);
      } else {
        show(pr, false);
      }
    }
  }

  function bindPortal(root) {
    var form = qs(root, '[data-nck-lookup]');
    if (!form) return;
    var i18n = (window.NCK && NCK.i18n) || {};
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var btn = qs(form, '[data-nck-lookup-btn]');
      setLoading(btn, true, i18n.looking);
      setAlert(root, '', '');
      var phone = (qs(form, '[name="phone"]') || {}).value || '';
      post('nck_lookup', { phone: phone }).then(function (res) {
        setLoading(btn, false);
        if (!res || !res.success) {
          show(qs(root, '[data-nck-member]'), false);
          setAlert(root, 'err', (res && res.data && res.data.message) || i18n.error);
          return;
        }
        root.dataset.phone = phone;
        applyMember(root, res.data);
      }).catch(function () {
        setLoading(btn, false);
        setAlert(root, 'err', i18n.error);
      });
    });

    var cin = qs(root, '[data-nck-checkin]');
    if (cin) {
      cin.addEventListener('click', function () {
        setLoading(cin, true, i18n.checking);
        post('nck_checkin', { phone: root.dataset.phone || '' }).then(function (res) {
          setLoading(cin, false);
          if (!res || !res.success) {
            setAlert(root, 'err', (res && res.data && res.data.message) || i18n.error);
            return;
          }
          setAlert(root, 'ok', res.data.message || '');
          applyMember(root, res.data);
        }).catch(function () {
          setLoading(cin, false);
          setAlert(root, 'err', i18n.error);
        });
      });
    }
  }

  function boot() {
    document.querySelectorAll('[data-nck="contract"]').forEach(bindContract);
    document.querySelectorAll('[data-nck="hall"]').forEach(bindHall);
    document.querySelectorAll('[data-nck="portal"]').forEach(bindPortal);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
