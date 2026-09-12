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

  function parseJson(raw, fallback) {
    try {
      var data = JSON.parse(raw);
      return data && typeof data === 'object' ? data : fallback;
    } catch (err) {
      return fallback;
    }
  }

  function bindFormBuilder() {
    var wrap = document.querySelector('[data-nck-form-editor]');
    var box = document.getElementById('nck-form-builder');
    var hidden = document.getElementById('nck-form-json');
    if (!wrap || !box || !hidden) return;

    var types = (window.NCKAdmin && NCKAdmin.types) || {};
    var roles = (window.NCKAdmin && NCKAdmin.roles) || {};
    var state = parseJson(hidden.value, {});
    if (!state.steps) state.steps = [];
    if (!state.payment) state.payment = { enabled: 1, amount: 0, methods: ['site'], item_name: '' };
    if (!state.product_id) state.product_id = 0;

    function esc(s) {
      return String(s || '').replace(/[&<>"']/g, function (c) {
        return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c];
      });
    }

    function optionHtml(map, selected) {
      return Object.keys(map).map(function (k) {
        return '<option value="' + k + '"' + (String(selected) === String(k) ? ' selected' : '') + '>' + map[k] + '</option>';
      }).join('');
    }

    function fieldHtml(field, si, fi) {
      var needsOpts = field.type === 'radio' || field.type === 'checkbox' || field.type === 'select';
      return '<div class="nck-builder-field" data-si="' + si + '" data-fi="' + fi + '">' +
        '<div class="nck-builder-row">' +
          '<label>عنوان<input type="text" data-fk="label" value="' + esc(field.label || '') + '" /></label>' +
          '<label>نام انگلیسی<input type="text" dir="ltr" data-fk="name" value="' + esc(field.name || '') + '" /></label>' +
          '<label>نوع<select data-fk="type">' + optionHtml(types, field.type) + '</select></label>' +
          '<label>نقش<select data-fk="role">' + optionHtml(roles, field.role || '') + '</select></label>' +
          '<label class="nck-builder-check"><input type="checkbox" data-fk="required"' + (field.required ? ' checked' : '') + ' /> الزامی</label>' +
          '<button type="button" class="button-link-delete" data-nck-del-field>حذف فیلد</button>' +
        '</div>' +
        (needsOpts ? '<label class="nck-builder-opts">گزینه‌ها (هر خط یک مورد، یا value|برچسب)<textarea data-fk="options" rows="3">' + esc(field.options || '') + '</textarea></label>' : '') +
      '</div>';
    }

    function render() {
      box.innerHTML = state.steps.map(function (step, si) {
        var fields = (step.fields || []).map(function (f, fi) { return fieldHtml(f, si, fi); }).join('');
        return '<div class="nck-builder-step" data-si="' + si + '">' +
          '<div class="nck-builder-step-head">' +
            '<strong>مرحله ' + (si + 1) + '</strong>' +
            '<label>عنوان مرحله<input type="text" data-sk="label" value="' + esc(step.label || '') + '" /></label>' +
            '<button type="button" class="button-link-delete" data-nck-del-step>حذف مرحله</button>' +
          '</div>' +
          fields +
          '<button type="button" class="button" data-nck-add-field>افزودن فیلد</button>' +
        '</div>';
      }).join('');
    }

    function readUi() {
      wrap.querySelectorAll('[data-nck-meta]').forEach(function (el) {
        var key = el.getAttribute('data-nck-meta');
        if (el.type === 'checkbox') state[key] = el.checked ? 1 : 0;
        else if (el.type === 'radio') {
          if (el.checked) state[key] = el.value;
        } else state[key] = el.value;
      });
      var pay = state.payment || {};
      wrap.querySelectorAll('[data-nck-pay]').forEach(function (el) {
        var key = el.getAttribute('data-nck-pay');
        if (el.type === 'checkbox') pay[key] = el.checked ? 1 : 0;
        else pay[key] = el.value;
      });
      pay.methods = ['site'];
      state.payment = pay;
      box.querySelectorAll('.nck-builder-step').forEach(function (stepEl) {
        var si = parseInt(stepEl.getAttribute('data-si'), 10);
        if (!state.steps[si]) return;
        var lab = stepEl.querySelector('[data-sk="label"]');
        if (lab) state.steps[si].label = lab.value;
        stepEl.querySelectorAll('.nck-builder-field').forEach(function (fieldEl) {
          var fi = parseInt(fieldEl.getAttribute('data-fi'), 10);
          if (!state.steps[si].fields[fi]) return;
          fieldEl.querySelectorAll('[data-fk]').forEach(function (inp) {
            var k = inp.getAttribute('data-fk');
            state.steps[si].fields[fi][k] = inp.type === 'checkbox' ? (inp.checked ? 1 : 0) : inp.value;
          });
        });
      });
      hidden.value = JSON.stringify(state);
    }

    wrap.addEventListener('submit', function () {
      readUi();
    });

    wrap.addEventListener('click', function (e) {
      var addStep = e.target.closest('[data-nck-add-step]');
      if (addStep) {
        e.preventDefault();
        readUi();
        state.steps.push({ label: 'مرحله جدید', fields: [] });
        render();
        return;
      }
      var delStep = e.target.closest('[data-nck-del-step]');
      if (delStep) {
        e.preventDefault();
        readUi();
        var si = parseInt(delStep.closest('.nck-builder-step').getAttribute('data-si'), 10);
        state.steps.splice(si, 1);
        render();
        return;
      }
      var addField = e.target.closest('[data-nck-add-field]');
      if (addField) {
        e.preventDefault();
        readUi();
        var s = parseInt(addField.closest('.nck-builder-step').getAttribute('data-si'), 10);
        if (!state.steps[s].fields) state.steps[s].fields = [];
        state.steps[s].fields.push({ type: 'text', name: 'field' + (state.steps[s].fields.length + 1), label: 'فیلد جدید', required: 0, role: '', options: '' });
        render();
        return;
      }
      var delField = e.target.closest('[data-nck-del-field]');
      if (delField) {
        e.preventDefault();
        readUi();
        var fieldEl = delField.closest('.nck-builder-field');
        var a = parseInt(fieldEl.getAttribute('data-si'), 10);
        var b = parseInt(fieldEl.getAttribute('data-fi'), 10);
        state.steps[a].fields.splice(b, 1);
        render();
      }
    });

    box.addEventListener('change', function (e) {
      if (e.target && e.target.getAttribute('data-fk') === 'type') {
        readUi();
        render();
      }
    });

    render();
  }

  document.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-nck-copy]');
    if (!btn) return;
    e.preventDefault();
    var text = btn.getAttribute('data-nck-copy') || '';
    function done() {
      var old = btn.textContent;
      btn.textContent = 'کپی شد';
      window.setTimeout(function () { btn.textContent = old; }, 1400);
    }
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(text).then(done).catch(function () {
        window.prompt('کپی کنید:', text);
      });
    } else {
      window.prompt('کپی کنید:', text);
    }
  });

  function bindLogoPicker() {
    var pick = document.querySelector('[data-nck-logo-pick]');
    var input = document.getElementById('nck-logo-id');
    var preview = document.querySelector('[data-nck-logo-preview]');
    var clear = document.querySelector('[data-nck-logo-clear]');
    if (!pick || !input || !preview || typeof wp === 'undefined' || !wp.media) return;
    var i18n = (window.NCKAdmin && NCKAdmin.i18n) || {};
    var frame;

    function showPreview(url) {
      preview.textContent = '';
      if (!url) {
        preview.appendChild(document.createTextNode('برگ نهال (پیش‌فرض)'));
        if (clear) clear.hidden = true;
        return;
      }
      var img = document.createElement('img');
      img.src = url;
      img.alt = '';
      preview.appendChild(img);
      if (clear) clear.hidden = false;
    }

    pick.addEventListener('click', function (e) {
      e.preventDefault();
      if (frame) {
        frame.open();
        return;
      }
      frame = wp.media({
        title: i18n.logoTitle || 'انتخاب لوگوی مجموعه',
        button: { text: i18n.logoBtn || 'استفاده از این تصویر' },
        multiple: false,
        library: { type: 'image' }
      });
      frame.on('select', function () {
        var att = frame.state().get('selection').first().toJSON();
        input.value = String(att.id || 0);
        var url = att.url || '';
        if (att.sizes && att.sizes.medium && att.sizes.medium.url) url = att.sizes.medium.url;
        showPreview(url);
      });
      frame.open();
    });

    if (clear) {
      clear.addEventListener('click', function (e) {
        e.preventDefault();
        input.value = '0';
        showPreview('');
      });
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () {
      bindFormBuilder();
      bindLogoPicker();
    });
  } else {
    bindFormBuilder();
    bindLogoPicker();
  }
})();
