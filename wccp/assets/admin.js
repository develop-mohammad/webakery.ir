(function () {
  'use strict';

  if (typeof WCCP_ADMIN === 'undefined') return;

  var state = {
    fields: WCCP_ADMIN.fields || {},
    active: (WCCP_ADMIN.active || []).slice(),
    dirty: false,
    dragKey: null
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
        (isCustom ? '<button type="button" class="wccp-icon-btn wccp-del-btn" title="حذف">×</button>' : '') +
      '</div>' +
      '<div class="wccp-item-meta">' +
        (isCustom ? '<span class="wccp-tag custom">سفارشی</span>' : '<span class="wccp-tag default">پیش‌فرض</span>') +
        (f.required ? '<span class="wccp-tag required">اجباری</span>' : '') +
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

  function save() {
    var btn = $('#wccp-save-btn');
    if (btn) {
      btn.classList.add('is-busy');
      btn.textContent = WCCP_ADMIN.i18n.saving;
    }
    syncHidden();

    var mode = ($('.wccp-app') || {}).getAttribute ? $('.wccp-app').getAttribute('data-mode') : 'global';
    var productId = WCCP_ADMIN.productId || (($('.wccp-app') || {}).getAttribute && parseInt($('.wccp-app').getAttribute('data-product-id') || '0', 10)) || 0;

    var req;
    if (mode === 'product' && productId) {
      req = post('wccp_save_product_fields', { product_id: productId, active: state.active });
    } else {
      req = post('wccp_save_fields', { active: state.active });
    }

    return req.then(function (res) {
      if (!res || !res.success) {
        toast((res && res.data && res.data.message) || WCCP_ADMIN.i18n.error, 'error');
        return;
      }
      state.dirty = false;
      if (res.data.fields) state.fields = res.data.fields;
      if (res.data.active) state.active = res.data.active;
      render();
      toast(res.data.message || WCCP_ADMIN.i18n.saved, 'ok');
    }).catch(function () {
      toast(WCCP_ADMIN.i18n.error, 'error');
    }).finally(function () {
      if (btn) {
        btn.classList.remove('is-busy');
        btn.innerHTML = '<span class="dashicons dashicons-yes"></span> ذخیره';
      }
    });
  }

  function currentMode() {
    var app = $('.wccp-app');
    return app ? (app.getAttribute('data-mode') || 'global') : 'global';
  }

  function createField() {
    var label = window.prompt('عنوان فیلد سفارشی:', 'فیلد جدید');
    if (!label) return;
    post('wccp_create_field', { label: label, type: 'text' }).then(function (res) {
      if (!res || !res.success) {
        toast((res && res.data && res.data.message) || WCCP_ADMIN.i18n.error, 'error');
        return;
      }
      if (res.data.fields) state.fields = res.data.fields;
      if (currentMode() === 'product') {
        if (res.data.key && state.active.indexOf(res.data.key) === -1) {
          state.active.push(res.data.key);
        }
        markDirty();
      } else if (res.data.active) {
        state.active = res.data.active;
      }
      render();
      toast(res.data.message || WCCP_ADMIN.i18n.saved, 'ok');
    });
  }

  function deleteField(key) {
    post('wccp_delete_field', { key: key }).then(function (res) {
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
    // اگر اسکریپت روی صفحه محصول هم لود شده، state از localize می‌آید
    render();
    bindLists();

    var saveBtn = $('#wccp-save-btn');
    if (saveBtn) saveBtn.addEventListener('click', function (e) { e.preventDefault(); save(); });

    var addBtn = $('#wccp-add-field');
    if (addBtn) addBtn.addEventListener('click', function (e) { e.preventDefault(); createField(); });

    // روی ذخیره پست وردپرس هم ترتیب فیلد محصول همراه می‌شود
    var form = document.getElementById('post');
    if (form) {
      form.addEventListener('submit', function () { syncHidden(); });
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
