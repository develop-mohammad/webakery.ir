(function () {
  'use strict';
  if (typeof WBCB_ADMIN === 'undefined') return;

  var listEl = document.getElementById('wbcb-conv-list');
  var searchEl = document.getElementById('wbcb-search');
  var filterEl = document.getElementById('wbcb-filter-status');
  var emptyEl = document.getElementById('wbcb-thread-empty');
  var activeWrap = document.getElementById('wbcb-thread-active');
  var messagesEl = document.getElementById('wbcb-thread-messages');
  var form = document.getElementById('wbcb-thread-form');
  var input = document.getElementById('wbcb-thread-input');
  var nameEl = document.getElementById('wbcb-thread-name');
  var metaEl = document.getElementById('wbcb-thread-meta');
  var pageLink = document.getElementById('wbcb-thread-page');
  var closeBtn = document.getElementById('wbcb-thread-close');

  var state = {
    convId: WBCB_ADMIN.convId || 0,
    lastId: 0,
    poll: null,
    items: []
  };

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

  function escapeHtml(s) {
    return String(s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;');
  }

  function renderList(items) {
    if (!listEl) return;
    listEl.innerHTML = '';
    (items || []).forEach(function (c) {
      var li = document.createElement('li');
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'wbcb-conv-item' + (state.convId === c.id ? ' is-active' : '');
      var badge = c.unread_admin ? '<span class="wbcb-badge-unread">جدید</span>' : '';
      btn.innerHTML = '<strong>' + escapeHtml(c.visitor_name || 'مهمان') + badge + '</strong>' +
        '<small>' + escapeHtml(c.visitor_email || c.page_url || '') + '</small>';
      btn.addEventListener('click', function () {
        selectConv(c.id);
      });
      li.appendChild(btn);
      listEl.appendChild(li);
    });
  }

  function renderMessages(list, replace) {
    if (!messagesEl) return;
    if (replace) {
      messagesEl.innerHTML = '';
      state.lastId = 0;
    }
    (list || []).forEach(function (m) {
      if (!replace && m.id <= state.lastId) return;
      var div = document.createElement('div');
      div.className = 'wbcb-thread-msg is-' + (m.sender || 'visitor');
      div.textContent = m.body || '';
      messagesEl.appendChild(div);
      messagesEl.scrollTop = messagesEl.scrollHeight;
      state.lastId = Math.max(state.lastId, m.id || 0);
    });
  }

  function loadList() {
    return post('wbcb_admin_list', {
      search: searchEl ? searchEl.value : '',
      status: filterEl ? filterEl.value : '',
      page: 1
    }).then(function (res) {
      if (!res || !res.success) return;
      state.items = res.data.items || [];
      renderList(state.items);
      if (state.convId && !state.items.some(function (x) { return x.id === state.convId; })) {
        // keep selection
      }
    });
  }

  function selectConv(id) {
    state.convId = id;
    if (emptyEl) emptyEl.hidden = true;
    if (activeWrap) activeWrap.hidden = false;
    renderList(state.items);
    loadThread(true);
    if (history.replaceState) {
      var url = new URL(window.location.href);
      url.searchParams.set('conv', String(id));
      history.replaceState({}, '', url.toString());
    }
    startPoll();
  }

  function loadThread(replace) {
    if (!state.convId) return;
    return post('wbcb_admin_poll', {
      conversation_id: state.convId,
      after_id: replace ? 0 : state.lastId
    }).then(function (res) {
      if (!res || !res.success) return;
      var c = res.data.conversation;
      if (c) {
        nameEl.textContent = c.visitor_name || 'مهمان';
        metaEl.textContent = (c.visitor_email || '') + (c.status === 'closed' ? ' · بسته' : ' · باز');
        if (pageLink) {
          pageLink.href = c.page_url || '#';
          pageLink.hidden = !c.page_url;
        }
      }
      renderMessages(res.data.messages, !!replace);
    });
  }

  function startPoll() {
    stopPoll();
    state.poll = setInterval(function () {
      if (!state.convId) return;
      loadThread(false);
    }, WBCB_ADMIN.pollMs || 4000);
  }

  function stopPoll() {
    if (state.poll) clearInterval(state.poll);
    state.poll = null;
  }

  if (form) {
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var text = (input.value || '').trim();
      if (!text || !state.convId) return;
      post('wbcb_admin_send', { conversation_id: state.convId, body: text }).then(function (res) {
        if (!res || !res.success) {
          alert((res && res.data && res.data.message) || WBCB_ADMIN.i18n.error);
          return;
        }
        input.value = '';
        renderMessages(res.data.messages, false);
        loadList();
      });
    });
  }

  if (closeBtn) {
    closeBtn.addEventListener('click', function () {
      if (!state.convId || !window.confirm('این گفتگو بسته شود؟')) return;
      post('wbcb_admin_close', { conversation_id: state.convId }).then(function () {
        loadList();
        loadThread(true);
      });
    });
  }

  var searchTimer;
  if (searchEl) {
    searchEl.addEventListener('input', function () {
      clearTimeout(searchTimer);
      searchTimer = setTimeout(loadList, 350);
    });
  }
  if (filterEl) filterEl.addEventListener('change', loadList);

  loadList().then(function () {
    if (state.convId) selectConv(state.convId);
  });
})();
