(function () {
  'use strict';

  if (typeof NM_ADMIN === 'undefined' || !NM_ADMIN.questions) return;

  var Q = NM_ADMIN.questions;
  var state = {
    fields: Q.fields || {},
    active: (Q.active || []).slice(),
    category: Q.category || '',
    dirty: false,
    dragKey: null,
    saveTimer: null
  };

  function $(sel, root) { return (root || document).querySelector(sel); }
  function $$(sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); }

  function toast(msg, type) {
    var el = $('#nm-q-toast');
    if (!el) return;
    el.hidden = false;
    el.textContent = msg;
    el.className = 'nm-qboard-toast ' + (type === 'error' ? 'is-error' : 'is-ok');
    clearTimeout(el._t);
    el._t = setTimeout(function () { el.hidden = true; }, 2500);
  }

  function availableKeys() {
    return Object.keys(state.fields).filter(function (k) {
      return state.active.indexOf(parseInt(k, 10)) === -1;
    }).map(function (k) { return parseInt(k, 10); });
  }

  function typeLabel(type) {
    return { text: 'متنی', textarea: 'چندخطی', select: 'انتخابی' }[type] || type;
  }

  function renderItem(key) {
    var f = state.fields[key] || state.fields[String(key)] || { label: key, type: 'text' };
    var li = document.createElement('li');
    li.className = 'nm-qboard-item';
    li.draggable = true;
    li.setAttribute('data-key', String(key));
    li.innerHTML =
      '<div class="nm-qboard-item-actions">' +
        '<button type="button" class="nm-qboard-icon-btn nm-q-move-btn" title="افزودن/حذف">+</button>' +
        '<button type="button" class="nm-qboard-icon-btn nm-q-edit-btn" title="ویرایش">✎</button>' +
        '<button type="button" class="nm-qboard-icon-btn nm-q-del-btn" title="حذف">×</button>' +
      '</div>' +
      '<div class="nm-qboard-item-meta">' +
        '<span class="nm-qboard-tag custom">سفارشی</span>' +
        (f.required ? '<span class="nm-qboard-tag required">اجباری</span>' : '') +
        '<span class="nm-qboard-tag default">' + typeLabel(f.type) + '</span>' +
        '<span class="nm-qboard-item-label"></span>' +
        '<code class="nm-qboard-item-key" dir="ltr">#' + key + '</code>' +
      '</div>' +
      '<span class="nm-qboard-drag-handle" title="بکشید">⋮⋮</span>';
    li.querySelector('.nm-qboard-item-label').textContent = f.label || String(key);
    return li;
  }

  function syncHidden() {
    var input = $('#nm-q-active-input');
    if (input) input.value = JSON.stringify(state.active);
  }

  function render() {
    var avail = $('#nm-q-available');
    var act = $('#nm-q-active');
    if (!avail || !act) return;

    avail.innerHTML = '';
    act.innerHTML = '';

    availableKeys().forEach(function (key) {
      if (state.fields[key] || state.fields[String(key)]) avail.appendChild(renderItem(key));
    });
    state.active.forEach(function (key) {
      if (state.fields[key] || state.fields[String(key)]) act.appendChild(renderItem(key));
    });
    syncHidden();
    bindItemEvents();
  }

  function markDirty() {
    state.dirty = true;
    scheduleAutoSave();
  }

  function scheduleAutoSave() {
    clearTimeout(state.saveTimer);
    state.saveTimer = setTimeout(function () {
      if (state.dirty) save(true);
    }, 900);
  }

  function moveToActive(key, index) {
    key = parseInt(key, 10);
    state.active = state.active.filter(function (k) { return k !== key; });
    if (typeof index === 'number' && index >= 0 && index <= state.active.length) {
      state.active.splice(index, 0, key);
    } else {
      state.active.push(key);
    }
    markDirty();
    render();
  }

  function moveToAvailable(key) {
    key = parseInt(key, 10);
    state.active = state.active.filter(function (k) { return k !== key; });
    markDirty();
    render();
  }

  function reorderActive(fromKey, beforeKey) {
    fromKey = parseInt(fromKey, 10);
    beforeKey = beforeKey ? parseInt(beforeKey, 10) : null;
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
    var items = $$('.nm-qboard-item', list);
    for (var i = 0; i < items.length; i++) {
      var rect = items[i].getBoundingClientRect();
      if (clientY < rect.top + rect.height / 2) return i;
    }
    return items.length;
  }

  function bindItemEvents() {
    $$('.nm-qboard-item').forEach(function (item) {
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
        $$('.nm-qboard-list').forEach(function (l) { l.classList.remove('is-over'); });
      });
    });

    $$('.nm-q-move-btn').forEach(function (btn) {
      btn.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        var item = btn.closest('.nm-qboard-item');
        var key = item.getAttribute('data-key');
        var list = item.closest('.nm-qboard-list').getAttribute('data-list');
        if (list === 'available') moveToActive(key);
        else moveToAvailable(key);
      });
    });

    $$('.nm-q-edit-btn').forEach(function (btn) {
      btn.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        openModal(btn.closest('.nm-qboard-item').getAttribute('data-key'));
      });
    });

    $$('.nm-q-del-btn').forEach(function (btn) {
      btn.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        if (!window.confirm(NM_ADMIN.i18n.confirmDelete)) return;
        deleteField(btn.closest('.nm-qboard-item').getAttribute('data-key'));
      });
    });
  }

  function bindLists() {
    $$('.nm-qboard-list').forEach(function (list) {
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
        if (state.active.indexOf(parseInt(key, 10)) === -1) {
          moveToActive(key, idx);
        } else {
          reorderActive(key, beforeKey === parseInt(key, 10) ? null : beforeKey);
        }
      });
    });
  }

  function post(action, data) {
    var body = new FormData();
    body.append('action', 'nm_admin');
    body.append('nm_action', action);
    body.append('nonce', NM_ADMIN.nonce);
    Object.keys(data || {}).forEach(function (k) {
      var v = data[k];
      body.append(k, typeof v === 'string' ? v : JSON.stringify(v));
    });
    return fetch(NM_ADMIN.ajax, { method: 'POST', body: body, credentials: 'same-origin' })
      .then(function (r) { return r.json(); });
  }

  function applyBoard(res) {
    if (res.fields) state.fields = res.fields;
    if (res.active) state.active = res.active.map(function (x) { return parseInt(x, 10); });
    render();
  }

  function save(silent) {
    var btn = $('#nm-q-save-btn');
    if (btn && !silent) {
      btn.classList.add('is-busy');
      btn.textContent = NM_ADMIN.i18n.saving;
    }
    syncHidden();
    return post('save_questions_board', {
      category: state.category,
      active: state.active
    }).then(function (res) {
      if (!res || !res.success) {
        toast((res && res.data && res.data.message) || NM_ADMIN.i18n.error, 'error');
        return;
      }
      state.dirty = false;
      applyBoard(res.data || {});
      if (!silent) toast(res.data.message || NM_ADMIN.i18n.saved, 'ok');
    }).catch(function () {
      toast(NM_ADMIN.i18n.error, 'error');
    }).finally(function () {
      if (btn) {
        btn.classList.remove('is-busy');
        btn.innerHTML = '<span class="dashicons dashicons-yes"></span> ذخیره';
      }
    });
  }

  function openModal(key) {
    var modal = $('#nm-q-modal');
    if (!modal) return;
    var id = parseInt(key || '0', 10) || 0;
    var f = id ? (state.fields[id] || state.fields[String(id)]) : null;
    $('#nm-q-modal-title').textContent = id ? 'ویرایش سوال' : 'سوال جدید';
    $('#nm-q-id').value = id || 0;
    $('#nm-q-question').value = f ? f.label : '';
    $('#nm-q-type').value = f ? f.type : 'text';
    var opts = '';
    if (f && f.options) {
      try {
        var parsed = JSON.parse(f.options);
        if (Array.isArray(parsed)) opts = parsed.join('\n');
      } catch (e) {
        opts = f.options;
      }
    }
    $('#nm-q-options').value = opts;
    $('#nm-q-required').checked = f ? !!f.required : true;
    modal.hidden = false;
  }

  function closeModal() {
    var modal = $('#nm-q-modal');
    if (modal) modal.hidden = true;
  }

  function createField() {
    openModal(0);
  }

  function submitModal(e) {
    e.preventDefault();
    var id = parseInt($('#nm-q-id').value || '0', 10);
    var payload = {
      category: state.category,
      question: $('#nm-q-question').value,
      type: $('#nm-q-type').value,
      options_text: $('#nm-q-options').value,
      is_required: $('#nm-q-required').checked ? 1 : 0
    };
    var req = id ? post('update_question', Object.assign({ id: id }, payload)) : post('create_question', payload);
    req.then(function (res) {
      if (!res || !res.success) {
        toast((res && res.data && res.data.message) || NM_ADMIN.i18n.error, 'error');
        return;
      }
      applyBoard(res.data || {});
      closeModal();
      toast(res.data.message || NM_ADMIN.i18n.saved, 'ok');
      state.dirty = false;
    });
  }

  function deleteField(key) {
    post('delete_question', { id: parseInt(key, 10) }).then(function (res) {
      if (!res || !res.success) {
        toast((res && res.data && res.data.message) || NM_ADMIN.i18n.error, 'error');
        return;
      }
      applyBoard(res.data || {});
      toast(res.data.message || 'حذف شد', 'ok');
    });
  }

  function init() {
    var app = $('#nm-q-app');
    if (!app) return;
    state.category = app.getAttribute('data-category') || state.category;
    render();
    bindLists();

    var saveBtn = $('#nm-q-save-btn');
    if (saveBtn) saveBtn.addEventListener('click', function (e) { e.preventDefault(); save(false); });

    var addBtn = $('#nm-q-add-field');
    if (addBtn) addBtn.addEventListener('click', function (e) { e.preventDefault(); createField(); });

    var addCat = $('#nm-q-add-cat');
    if (addCat) {
      addCat.addEventListener('click', function (e) {
        e.preventDefault();
        var name = window.prompt('نام دسته‌بندی جدید:', '');
        if (!name) return;
        window.location.href = NM_ADMIN.questionsBase + '&nm_cat=' + encodeURIComponent(name.trim());
      });
    }

    var cancel = $('#nm-q-modal-cancel');
    if (cancel) cancel.addEventListener('click', function (e) { e.preventDefault(); closeModal(); });

    var form = $('#nm-q-modal-form');
    if (form) form.addEventListener('submit', submitModal);

    window.addEventListener('beforeunload', function (e) {
      if (state.dirty) {
        e.preventDefault();
        e.returnValue = '';
      }
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
