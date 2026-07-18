(function () {
  'use strict';

  if (typeof WCCP_ADMIN === 'undefined') return;

  var state = {
    fields: WCCP_ADMIN.fields || {},
    active: (WCCP_ADMIN.active || []).slice(),
    dirty: false,
    dragKey: null,
    optionSeed: 0,
    templateKey: WCCP_ADMIN.templateKey || '',
    defaultTpl: WCCP_ADMIN.defaultTpl || ''
  };

  function $(sel, root) { return (root || document).querySelector(sel); }
  function $$(sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); }

  function toast(msg, type) {
    var el = $('#wccp-toast');
    if (!el) {
      el = document.createElement('div');
      el.id = 'wccp-toast';
      el.className = 'wccp-toast';
      document.body.appendChild(el);
    }
    el.hidden = false;
    el.textContent = msg;
    el.className = 'wccp-toast ' + (type === 'error' ? 'is-error' : 'is-ok');
    clearTimeout(el._t);
    el._t = setTimeout(function () { el.hidden = true; }, 2500);
  }

  function availableKeys() {
    return Object.keys(state.fields).filter(function (k) {
      return state.active.indexOf(k) === -1;
    });
  }

  function typeLabel(type) {
    var map = {
      text: 'متنی',
      textarea: 'چندخطی',
      tel: 'تلفن',
      email: 'ایمیل',
      select: 'کشویی',
      radio: 'رادیو',
      checkboxes: 'چندگزینه‌ای'
    };
    return map[type] || type || 'متنی';
  }

  function optionTypes() {
    return ['select', 'radio', 'checkboxes'];
  }

  function renderItem(key) {
    var f = state.fields[key] || { label: key, type: 'text' };
    var isCustom = !!(f.custom || f.user_defined);
    var li = document.createElement('li');
    li.className = 'wccp-item';
    li.draggable = true;
    li.setAttribute('data-key', key);
    li.setAttribute('data-custom', isCustom ? '1' : '0');
    li.innerHTML =
      '<div class="wccp-item-actions">' +
        '<button type="button" class="wccp-icon-btn wccp-move-btn" title="افزودن/حذف">+</button>' +
        (isCustom ? '<button type="button" class="wccp-icon-btn wccp-edit-btn" title="ویرایش">✎</button>' : '') +
        (isCustom ? '<button type="button" class="wccp-icon-btn wccp-del-btn" title="حذف">×</button>' : '') +
      '</div>' +
      '<div class="wccp-item-meta">' +
        (isCustom ? '<span class="wccp-tag custom">سفارشی</span>' : '<span class="wccp-tag default">پیش‌فرض</span>') +
        (f.required ? '<span class="wccp-tag required">اجباری</span>' : '') +
        '<span class="wccp-tag type">' + typeLabel(f.type) + '</span>' +
        '<span class="wccp-item-label"></span>' +
        '<code class="wccp-item-key" dir="ltr"></code>' +
      '</div>' +
      '<span class="wccp-drag-handle" title="بکشید">⋮⋮</span>';
    li.querySelector('.wccp-item-label').textContent = f.label || key;
    li.querySelector('.wccp-item-key').textContent = key;
    return li;
  }

  function syncHidden() {
    var input = $('#wccp-active-input');
    if (input) input.value = JSON.stringify(state.active);
  }

  function render() {
    var avail = $('#wccp-available');
    var act = $('#wccp-active');
    if (!avail || !act) return;

    avail.innerHTML = '';
    act.innerHTML = '';

    availableKeys().forEach(function (key) {
      avail.appendChild(renderItem(key));
    });
    state.active.forEach(function (key) {
      if (state.fields[key]) act.appendChild(renderItem(key));
    });
    syncHidden();
    bindItemEvents();
  }

  function markDirty() {
    state.dirty = true;
  }

  function moveToActive(key, index) {
    var i = state.active.indexOf(key);
    if (i !== -1) state.active.splice(i, 1);
    if (typeof index === 'number' && index >= 0 && index <= state.active.length) {
      state.active.splice(index, 0, key);
    } else {
      state.active.push(key);
    }
    markDirty();
    render();
  }

  function moveToAvailable(key) {
    state.active = state.active.filter(function (k) { return k !== key; });
    markDirty();
    render();
  }

  function reorderActive(fromKey, beforeKey) {
    var from = state.active.indexOf(fromKey);
    if (from === -1) return;
    state.active.splice(from, 1);
    if (!beforeKey) {
      state.active.push(fromKey);
    } else {
      var to = state.active.indexOf(beforeKey);
      if (to === -1) state.active.push(fromKey);
      else state.active.splice(to, 0, fromKey);
    }
    markDirty();
    render();
  }

  function indexFromY(list, clientY) {
    var items = $$('.wccp-item', list);
    for (var i = 0; i < items.length; i++) {
      var rect = items[i].getBoundingClientRect();
      var mid = rect.top + rect.height / 2;
      if (clientY < mid) return i;
    }
    return items.length;
  }

  function bindItemEvents() {
    $$('.wccp-item').forEach(function (item) {
      item.addEventListener('dragstart', function (e) {
        state.dragKey = item.getAttribute('data-key');
        item.classList.add('is-dragging');
        try {
          e.dataTransfer.setData('text/plain', state.dragKey);
          e.dataTransfer.effectAllowed = 'move';
        } catch (err) {}
      });
      item.addEventListener('dragend', function () {
        item.classList.remove('is-dragging');
        state.dragKey = null;
        $$('.wccp-list').forEach(function (l) { l.classList.remove('is-over'); });
      });
    });

    $$('.wccp-move-btn').forEach(function (btn) {
      btn.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        var item = btn.closest('.wccp-item');
        var key = item.getAttribute('data-key');
        var list = item.closest('.wccp-list').getAttribute('data-list');
        if (list === 'available') moveToActive(key);
        else moveToAvailable(key);
      });
    });

    $$('.wccp-del-btn').forEach(function (btn) {
      btn.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        if (!window.confirm(WCCP_ADMIN.i18n.confirm)) return;
        var key = btn.closest('.wccp-item').getAttribute('data-key');
        deleteField(key);
      });
    });

    $$('.wccp-edit-btn').forEach(function (btn) {
      btn.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        openFieldModal(btn.closest('.wccp-item').getAttribute('data-key'));
      });
    });
  }

  function bindLists() {
    $$('.wccp-list').forEach(function (list) {
      list.addEventListener('dragover', function (e) {
        e.preventDefault();
        list.classList.add('is-over');
        try { e.dataTransfer.dropEffect = 'move'; } catch (err) {}
      });
      list.addEventListener('dragleave', function () {
        list.classList.remove('is-over');
      });
      list.addEventListener('drop', function (e) {
        e.preventDefault();
        list.classList.remove('is-over');
        var key = state.dragKey || (e.dataTransfer && e.dataTransfer.getData('text/plain'));
        if (!key) return;
        var type = list.getAttribute('data-list');
        if (type === 'available') {
          moveToAvailable(key);
          return;
        }
        var idx = indexFromY(list, e.clientY);
        var beforeKey = state.active[idx] || null;
        if (state.active.indexOf(key) === -1) {
          moveToActive(key, idx);
        } else {
          reorderActive(key, beforeKey === key ? null : beforeKey);
        }
      });
    });
  }

  function post(action, data) {
    var body = new FormData();
    body.append('action', action);
    body.append('nonce', WCCP_ADMIN.nonce);
    Object.keys(data || {}).forEach(function (k) {
      var v = data[k];
      body.append(k, typeof v === 'string' ? v : JSON.stringify(v));
    });
    return fetch(WCCP_ADMIN.ajax, { method: 'POST', body: body, credentials: 'same-origin' })
      .then(function (r) { return r.json(); });
  }

  function currentTemplateKey() {
    var app = $('.wccp-app');
    if (app && app.getAttribute('data-template')) return app.getAttribute('data-template');
    return state.templateKey || '';
  }

  function save() {
    var btn = $('#wccp-save-btn');
    if (btn) {
      btn.classList.add('is-busy');
      btn.textContent = WCCP_ADMIN.i18n.saving;
    }
    syncHidden();

    var mode = ($('.wccp-app') || {}).getAttribute ? $('.wccp-app').getAttribute('data-mode') : 'global';
    var productId = WCCP_ADMIN.productId || (($('.wccp-app') || {}).getAttribute && parseInt($('.wccp-app').getAttribute('data-product-id') || '0', 10)) || 0;
    var tplKey = currentTemplateKey();

    var req;
    if (mode === 'product' && productId) {
      req = post('wccp_save_product_fields', { product_id: productId, active: state.active });
    } else if (mode === 'template' && tplKey) {
      req = post('wccp_save_fields', { active: state.active, template_key: tplKey });
    } else {
      req = post('wccp_save_fields', { active: state.active, template_key: tplKey || '' });
    }

    return req.then(function (res) {
      if (!res || !res.success) {
        toast((res && res.data && res.data.message) || WCCP_ADMIN.i18n.error, 'error');
        return;
      }
      state.dirty = false;
      if (res.data.fields) state.fields = res.data.fields;
      if (res.data.active) state.active = res.data.active;
      if (res.data.default_tpl) state.defaultTpl = res.data.default_tpl;
      render();
      toast(res.data.message || WCCP_ADMIN.i18n.saved, 'ok');
    }).catch(function () {
      toast(WCCP_ADMIN.i18n.error, 'error');
    }).finally(function () {
      if (btn) {
        btn.classList.remove('is-busy');
        btn.innerHTML = '<span class="dashicons dashicons-yes"></span> ' + (WCCP_ADMIN.i18n.save_tpl || 'ذخیره');
      }
    });
  }

  function setDefaultTemplate(key) {
    return post('wccp_set_default_template', { template_key: key }).then(function (res) {
      if (!res || !res.success) {
        toast((res && res.data && res.data.message) || WCCP_ADMIN.i18n.error, 'error');
        return;
      }
      state.defaultTpl = res.data.default_tpl || key;
      toast(res.data.message || WCCP_ADMIN.i18n.star_ok, 'ok');
      var url = new URL(window.location.href);
      url.searchParams.set('page', 'wccp');
      url.searchParams.set('tpl', key);
      url.searchParams.delete('tab');
      window.location.href = url.toString();
    });
  }

  function currentMode() {
    var app = $('.wccp-app');
    return app ? (app.getAttribute('data-mode') || 'global') : 'global';
  }

  function getType() {
    var el = $('#wccp-field-type');
    return el ? el.value : 'text';
  }

  function setType(type) {
    var el = $('#wccp-field-type');
    if (el) el.value = type || 'text';
    $$('.wccp-type-card').forEach(function (card) {
      card.classList.toggle('is-active', card.getAttribute('data-type') === type);
    });
    toggleOptionsVisibility();
    updatePreview();
  }

  function toggleOptionsVisibility() {
    var wrap = $('#wccp-field-options-wrap');
    if (!wrap) return;
    var need = optionTypes().indexOf(getType()) !== -1;
    wrap.hidden = !need;
    if (need && !$$('.wccp-option-row').length) {
      setOptions(['گزینه ۱', 'گزینه ۲', 'گزینه ۳']);
    }
  }

  function collectOptions() {
    return $$('.wccp-option-input').map(function (inp) {
      return String(inp.value || '').trim();
    }).filter(Boolean);
  }

  function syncOptionsTextarea() {
    var ta = $('#wccp-field-options');
    if (ta) ta.value = collectOptions().join('\n');
  }

  function addOptionRow(value) {
    var list = $('#wccp-options-list');
    if (!list) return;
    state.optionSeed += 1;
    var row = document.createElement('div');
    row.className = 'wccp-option-row';
    row.innerHTML =
      '<span class="wccp-option-num"></span>' +
      '<input type="text" class="wccp-option-input widefat" placeholder="متن گزینه" />' +
      '<button type="button" class="wccp-icon-btn wccp-option-remove" title="حذف">×</button>';
    var input = row.querySelector('.wccp-option-input');
    input.value = value || '';
    input.addEventListener('input', function () {
      syncOptionsTextarea();
      renumberOptions();
      updatePreview();
    });
    row.querySelector('.wccp-option-remove').addEventListener('click', function (e) {
      e.preventDefault();
      if ($$('.wccp-option-row').length <= 1) {
        toast('حداقل یک گزینه لازم است', 'error');
        return;
      }
      row.remove();
      syncOptionsTextarea();
      renumberOptions();
      updatePreview();
    });
    list.appendChild(row);
    renumberOptions();
    syncOptionsTextarea();
    updatePreview();
    input.focus();
  }

  function renumberOptions() {
    $$('.wccp-option-row').forEach(function (row, i) {
      var num = row.querySelector('.wccp-option-num');
      if (num) num.textContent = String(i + 1);
    });
  }

  function setOptions(list) {
    var box = $('#wccp-options-list');
    if (!box) return;
    box.innerHTML = '';
    (list && list.length ? list : ['']).forEach(function (opt) {
      addOptionRow(opt);
    });
  }

  function parseOptionsText(raw) {
    return String(raw || '')
      .split(/[\r\n,]+/)
      .map(function (s) { return s.trim(); })
      .filter(Boolean);
  }

  function escapeHtml(str) {
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function updatePreview() {
    var box = $('#wccp-live-preview');
    if (!box) return;
    var label = ($('#wccp-field-label') && $('#wccp-field-label').value) || 'عنوان سوال';
    var type = getType();
    var required = $('#wccp-field-required') && $('#wccp-field-required').checked;
    var opts = collectOptions();
    var html = '<div class="wccp-pv-label">' + escapeHtml(label) + (required ? ' <span>*</span>' : '') + '</div>';

    if (type === 'textarea') {
      html += '<textarea class="wccp-pv-input" rows="3" disabled></textarea>';
    } else if (type === 'select') {
      html += '<select class="wccp-pv-input" disabled><option>انتخاب کنید</option>';
      opts.forEach(function (o) { html += '<option>' + escapeHtml(o) + '</option>'; });
      html += '</select>';
    } else if (type === 'radio' || type === 'checkboxes') {
      if (!opts.length) {
        html += '<div class="wccp-preview-empty">گزینه‌ها را اضافه کنید…</div>';
      } else {
        html += '<div class="wccp-pv-choices">';
        opts.forEach(function (o, i) {
          var t = type === 'radio' ? 'radio' : 'checkbox';
          html += '<label><input type="' + t + '" disabled ' + (i === 0 && type === 'radio' ? 'checked' : '') + ' /> ' + escapeHtml(o) + '</label>';
        });
        html += '</div>';
      }
    } else {
      var inputType = (type === 'email' || type === 'tel' || type === 'number') ? type : 'text';
      html += '<input class="wccp-pv-input" type="' + inputType + '" disabled placeholder="پاسخ کاربر…" />';
    }
    box.innerHTML = html;
  }

  function openFieldModal(key, presetType) {
    var modal = $('#wccp-field-modal');
    if (!modal) return;
    var isEdit = !!key;
    var f = isEdit ? (state.fields[key] || {}) : {};
    var title = $('#wccp-modal-title');
    if (title) title.textContent = isEdit ? 'ویرایش سوال / فیلد' : 'ساخت سوال / فیلد جدید';
    $('#wccp-field-key').value = isEdit ? key : '';
    $('#wccp-field-label').value = f.label || '';
    $('#wccp-field-required').checked = !!f.required;

    var type = presetType || f.type || 'text';
    setType(type);

    if (optionTypes().indexOf(type) !== -1) {
      var opts = parseOptionsText(f.options || '');
      setOptions(opts.length ? opts : ['گزینه ۱', 'گزینه ۲', 'گزینه ۳']);
    } else {
      setOptions([]);
      var list = $('#wccp-options-list');
      if (list) list.innerHTML = '';
    }

    updatePreview();
    modal.hidden = false;
    var labelEl = $('#wccp-field-label');
    if (labelEl && !isEdit) labelEl.focus();
  }

  function closeFieldModal() {
    var modal = $('#wccp-field-modal');
    if (modal) modal.hidden = true;
  }

  function submitFieldModal(e) {
    e.preventDefault();
    var key = $('#wccp-field-key').value;
    var type = getType();
    syncOptionsTextarea();
    var optionsText = ($('#wccp-field-options') && $('#wccp-field-options').value) || '';

    if (optionTypes().indexOf(type) !== -1 && !collectOptions().length) {
      toast('حداقل یک گزینه برای این نوع سوال لازم است', 'error');
      return;
    }

    var payload = {
      label: $('#wccp-field-label').value,
      type: type,
      options_text: optionsText,
      required: $('#wccp-field-required').checked ? 1 : 0,
      template_key: currentTemplateKey()
    };
    var action = key ? 'wccp_update_field' : 'wccp_create_field';
    if (key) payload.key = key;

    var submitBtn = $('#wccp-modal-submit');
    if (submitBtn) {
      submitBtn.disabled = true;
      submitBtn.textContent = 'در حال ذخیره…';
    }

    post(action, payload).then(function (res) {
      if (!res || !res.success) {
        toast((res && res.data && res.data.message) || WCCP_ADMIN.i18n.error, 'error');
        return;
      }
      if (res.data.fields) state.fields = res.data.fields;
      if (!key && res.data.active) state.active = res.data.active;
      else if (!key && res.data.key && state.active.indexOf(res.data.key) === -1) {
        state.active.push(res.data.key);
      }
      if (currentMode() === 'product') markDirty();
      render();
      closeFieldModal();
      toast(res.data.message || WCCP_ADMIN.i18n.saved, 'ok');
    }).catch(function () {
      toast(WCCP_ADMIN.i18n.error, 'error');
    }).finally(function () {
      if (submitBtn) {
        submitBtn.disabled = false;
        submitBtn.textContent = 'ذخیره سوال / فیلد';
      }
    });
  }

  function createField(presetType) {
    openFieldModal('', presetType || '');
  }

  function deleteField(key) {
    post('wccp_delete_field', { key: key, template_key: currentTemplateKey() }).then(function (res) {
      if (!res || !res.success) {
        toast((res && res.data && res.data.message) || WCCP_ADMIN.i18n.error, 'error');
        return;
      }
      if (res.data.fields) state.fields = res.data.fields;
      if (res.data.active) state.active = res.data.active;
      else state.active = state.active.filter(function (k) { return k !== key; });
      delete state.fields[key];
      render();
      toast(res.data.message || 'حذف شد', 'ok');
    });
  }

  function init() {
    var app = $('.wccp-app');
    if (!app) return;
    render();
    bindLists();

    var saveBtn = $('#wccp-save-btn');
    if (saveBtn) saveBtn.addEventListener('click', function (e) { e.preventDefault(); save(); });

    var addBtn = $('#wccp-add-field');
    if (addBtn) addBtn.addEventListener('click', function (e) { e.preventDefault(); createField('text'); });

    var addRadio = $('#wccp-add-radio');
    if (addRadio) addRadio.addEventListener('click', function (e) { e.preventDefault(); createField('radio'); });

    var addChecks = $('#wccp-add-checkboxes');
    if (addChecks) addChecks.addEventListener('click', function (e) { e.preventDefault(); createField('checkboxes'); });

    var addOpt = $('#wccp-add-option');
    if (addOpt) addOpt.addEventListener('click', function (e) { e.preventDefault(); addOptionRow(''); });

    $$('.wccp-type-card').forEach(function (card) {
      card.addEventListener('click', function (e) {
        e.preventDefault();
        setType(card.getAttribute('data-type'));
      });
    });

    var labelEl = $('#wccp-field-label');
    if (labelEl) labelEl.addEventListener('input', updatePreview);
    var reqEl = $('#wccp-field-required');
    if (reqEl) reqEl.addEventListener('change', updatePreview);

    var cancelBtn = $('#wccp-modal-cancel');
    if (cancelBtn) cancelBtn.addEventListener('click', function (e) { e.preventDefault(); closeFieldModal(); });
    var closeBtn = $('#wccp-modal-close');
    if (closeBtn) closeBtn.addEventListener('click', function (e) { e.preventDefault(); closeFieldModal(); });

    var modal = $('#wccp-field-modal');
    if (modal) {
      modal.addEventListener('click', function (e) {
        if (e.target === modal) closeFieldModal();
      });
    }

    var form = $('#wccp-field-form');
    if (form) form.addEventListener('submit', submitFieldModal);

    var postForm = document.getElementById('post');
    if (postForm) {
      postForm.addEventListener('submit', function () { syncHidden(); });
    }

    function bindStarButtons() {
      $$('.wccp-tpl-star, .wccp-tpl-star-inline').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
          e.preventDefault();
          e.stopPropagation();
          var key = btn.getAttribute('data-tpl');
          if (!key) return;
          if (key === state.defaultTpl) {
            toast('این قالب همین حالا پیش‌فرض checkout است', 'ok');
            return;
          }
          setDefaultTemplate(key);
        });
      });
    }
    bindStarButtons();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
