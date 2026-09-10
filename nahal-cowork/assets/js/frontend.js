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
      var v = data[k];
      if (Array.isArray(v)) {
        v.forEach(function (item) {
          body.append(k + '[]', item);
        });
      } else if (v === undefined || v === null) {
        body.append(k, '');
      } else {
        body.append(k, v);
      }
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

  function collectForm(form) {
    var fd = {};
    Array.prototype.forEach.call(form.elements, function (el) {
      if (!el.name) return;
      var name = el.name;
      var isArr = name.slice(-2) === '[]';
      if (isArr) name = name.slice(0, -2);
      if (el.type === 'radio') {
        if (el.checked) fd[name] = el.value;
        return;
      }
      if (el.type === 'checkbox') {
        if (isArr) {
          if (!Array.isArray(fd[name])) fd[name] = [];
          if (el.checked) fd[name].push(el.value);
          return;
        }
        fd[name] = el.checked ? (el.value || '1') : '';
        return;
      }
      if (el.type === 'button' || el.type === 'submit') return;
      fd[name] = el.value;
    });
    return fd;
  }

  function fieldLabel(el) {
    var wrap = el.closest('.nck-field');
    if (wrap) {
      var lab = wrap.querySelector('label, .nck-label');
      if (lab) return lab.textContent.replace(/\s+/g, ' ').trim();
    }
    var fs = el.closest('fieldset');
    if (fs) {
      var legend = fs.querySelector('legend');
      if (legend) return legend.textContent.replace(/\s+/g, ' ').trim();
    }
    var agree = el.closest('.nck-agree');
    if (agree) return agree.textContent.replace(/\s+/g, ' ').trim();
    return 'این مرحله';
  }

  function validateStep(step, form) {
    var reqs = step.querySelectorAll('[required]');
    var seenRadio = {};
    for (var k = 0; k < reqs.length; k++) {
      var el = reqs[k];
      if (el.disabled) continue;
      if (el.type === 'radio') {
        if (seenRadio[el.name]) continue;
        seenRadio[el.name] = true;
        var any = form.querySelector('[name="' + el.name + '"]:checked');
        if (!any) return 'لطفاً «' + fieldLabel(el) + '» را انتخاب کنید.';
        continue;
      }
      if (el.type === 'checkbox') {
        if (el.name && el.name.slice(-2) === '[]') continue;
        if (!el.checked) return 'لطفاً این مورد را بپذیرید: ' + fieldLabel(el);
        continue;
      }
      if (!String(el.value || '').trim()) {
        return 'لطفاً «' + fieldLabel(el) + '» را وارد کنید.';
      }
    }
    var need = step.getAttribute('data-nck-require-one');
    if (need) {
      var ok = false;
      need.split(',').forEach(function (n) {
        n = n.trim();
        if (!n) return;
        var nodes = step.querySelectorAll('[name="' + n + '[]"]:checked, [name="' + n + '"]:checked');
        if (nodes.length) ok = true;
      });
      if (!ok) return step.getAttribute('data-nck-require-msg') || 'حداقل یک گزینه را انتخاب کنید.';
    }
    var groups = step.querySelectorAll('[data-nck-need-one]');
    for (var g = 0; g < groups.length; g++) {
      var nm = groups[g].getAttribute('data-nck-need-one');
      if (!nm) continue;
      var picked = step.querySelectorAll('[name="' + nm + '[]"]:checked, [name="' + nm + '"]:checked');
      if (!picked.length) {
        var legend = groups[g].querySelector('legend');
        return 'حداقل یک گزینه برای «' + ((legend && legend.textContent) || 'این بخش') + '» را انتخاب کنید.';
      }
    }
    return '';
  }

  function bindWizard(form, root) {
    var steps = [].slice.call(form.querySelectorAll('[data-nck-step]'));
    if (!steps.length) return null;
    var i = 0;
    var max = 0;

    function go(n, scroll) {
      i = Math.max(0, Math.min(steps.length - 1, n));
      if (i > max) max = i;
      steps.forEach(function (s, idx) {
        show(s, idx === i);
      });
      var now = faDigits(i + 1);
      var all = faDigits(steps.length);
      form.querySelectorAll('[data-nck-step-now]').forEach(function (el) { el.textContent = now; });
      form.querySelectorAll('[data-nck-step-all]').forEach(function (el) { el.textContent = all; });
      var title = steps[i].getAttribute('data-nck-step-label') || '';
      form.querySelectorAll('[data-nck-step-title]').forEach(function (el) { el.textContent = title; });
      var fill = form.querySelector('[data-nck-step-fill]');
      if (fill) fill.style.width = (((i + 1) / steps.length) * 100) + '%';
      var track = form.querySelector('.nck-wizard-track');
      if (track) {
        track.setAttribute('aria-valuenow', String(i + 1));
        track.setAttribute('aria-valuemax', String(steps.length));
      }
      show(qs(form, '[data-nck-prev]'), i > 0);
      show(qs(form, '[data-nck-next]'), i < steps.length - 1);
      show(qs(form, '[data-nck-submit]'), i === steps.length - 1);
      var ol = form.querySelector('[data-nck-step-dots]');
      if (ol) {
        ol.innerHTML = '';
        steps.forEach(function (step, idx) {
          var li = document.createElement('li');
          if (idx === i) li.className = 'is-on';
          else if (idx < i) li.className = 'is-done';
          var btn = document.createElement('button');
          btn.type = 'button';
          btn.textContent = faDigits(idx + 1);
          btn.disabled = idx > max;
          btn.setAttribute('aria-label', 'مرحله ' + faDigits(idx + 1) + ' از ' + all + ' — ' + (step.getAttribute('data-nck-step-label') || ''));
          btn.addEventListener('click', function () {
            if (idx <= max) go(idx, true);
          });
          li.appendChild(btn);
          ol.appendChild(li);
        });
      }
      if (scroll) {
        form.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
    }

    var nextBtn = qs(form, '[data-nck-next]');
    var prevBtn = qs(form, '[data-nck-prev]');
    if (nextBtn) {
      nextBtn.addEventListener('click', function () {
        var err = validateStep(steps[i], form);
        if (err) {
          setAlert(root, 'err', err);
          return;
        }
        setAlert(root, '', '');
        go(i + 1, true);
      });
    }
    if (prevBtn) {
      prevBtn.addEventListener('click', function () {
        setAlert(root, '', '');
        go(i - 1, true);
      });
    }
    form.addEventListener('keydown', function (e) {
      if (e.key !== 'Enter') return;
      if (e.target && (e.target.tagName === 'TEXTAREA' || e.target.type === 'submit')) return;
      if (i < steps.length - 1) {
        e.preventDefault();
        if (nextBtn) nextBtn.click();
      }
    });

    go(0, false);
    return {
      validateCurrent: function () {
        return validateStep(steps[i], form);
      },
      isLast: function () {
        return i === steps.length - 1;
      }
    };
  }

  function bindSignForm(root, formSel, action) {
    var form = qs(root, formSel);
    if (!form) return;
    var wizard = bindWizard(form, root);
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
      if (wizard && !wizard.isLast()) {
        return;
      }
      if (wizard) {
        var stepErr = wizard.validateCurrent();
        if (stepErr) {
          setAlert(root, 'err', stepErr);
          return;
        }
      }
      if (!pad.drawn && qs(root, '[data-nck-pad]')) {
        setAlert(root, 'err', i18n.draw || 'لطفاً داخل کادر امضا کنید.');
        return;
      }
      var btn = qs(form, '[data-nck-submit]');
      setLoading(btn, true, i18n.signing);
      setAlert(root, '', '');
      var fd = collectForm(form);
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

  function bindLearner(root) {
    bindSignForm(root, '[data-nck-sign-learner]', 'nck_sign_learner');
  }

  function bindCustomForm(root) {
    bindSignForm(root, '[data-nck-sign-form]', 'nck_sign_form');
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
    document.querySelectorAll('[data-nck="learner"]').forEach(bindLearner);
    document.querySelectorAll('[data-nck="form"]').forEach(bindCustomForm);
    document.querySelectorAll('[data-nck="portal"]').forEach(bindPortal);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
