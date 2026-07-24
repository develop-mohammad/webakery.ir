(function () {
  'use strict';
  if (typeof WBCB === 'undefined') return;

  var root = document.getElementById('wbcb-root');
  if (!root) return;

  var launcher = document.getElementById('wbcb-launcher');
  var panel = document.getElementById('wbcb-panel');
  var closeBtn = document.getElementById('wbcb-close');
  var messagesEl = document.getElementById('wbcb-messages');
  var form = document.getElementById('wbcb-form');
  var input = document.getElementById('wbcb-input');
  var intro = document.getElementById('wbcb-intro');
  var startBtn = document.getElementById('wbcb-start');
  var nameEl = document.getElementById('wbcb-name');
  var emailEl = document.getElementById('wbcb-email');
  var emailWrap = document.getElementById('wbcb-email-wrap');
  var linksEl = document.getElementById('wbcb-links');

  var state = {
    token: '',
    lastId: 0,
    booted: false,
    polling: null,
    open: false
  };

  function $(sel) { return document.querySelector(sel); }

  function post(action, data) {
    var body = new FormData();
    body.append('action', action);
    body.append('nonce', WBCB.nonce);
    Object.keys(data || {}).forEach(function (k) {
      body.append(k, data[k] == null ? '' : String(data[k]));
    });
    return fetch(WBCB.ajax, { method: 'POST', body: body, credentials: 'same-origin' })
      .then(function (r) { return r.json(); });
  }

  function escapeHtml(s) {
    return String(s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;');
  }

  function renderMessage(m) {
    var div = document.createElement('div');
    var sender = m.sender || 'visitor';
    div.className = 'wbcb-msg is-' + sender;
    var meta = m.meta || {};
    if (meta.type === 'product_context' && (meta.product_image || meta.product_name)) {
      var card = '<div class="wbcb-msg-product">';
      if (meta.product_image) {
        card += '<img src="' + escapeHtml(meta.product_image) + '" alt="" loading="lazy" />';
      }
      card += '<div><strong>' + escapeHtml(meta.product_name || 'محصول') + '</strong>';
      if (meta.product_url) {
        card += '<br><a href="' + escapeHtml(meta.product_url) + '" target="_blank" rel="noopener">مشاهده</a>';
      }
      card += '</div></div>';
      div.innerHTML = card + '<div class="wbcb-msg-text">' + escapeHtml(m.body || '').replace(/\n/g, '<br>') + '</div><time>' + escapeHtml(m.time_label || '') + '</time>';
    } else {
      div.innerHTML = '<div class="wbcb-msg-text">' + escapeHtml(m.body || '').replace(/\n/g, '<br>') + '</div><time>' + escapeHtml(m.time_label || '') + '</time>';
    }
    messagesEl.appendChild(div);
    messagesEl.scrollTop = messagesEl.scrollHeight;
    if (m.id) state.lastId = Math.max(state.lastId, m.id);
  }

  function appendMessages(list, replace) {
    if (replace) {
      messagesEl.innerHTML = '';
      state.lastId = 0;
    }
    (list || []).forEach(function (m) {
      if (m.id <= state.lastId && !replace) return;
      renderMessage(m);
    });
  }

  function setupLinks() {
    if (!linksEl) return;
    linksEl.innerHTML = '';
    var s = WBCB.settings || {};
    if (s.whatsapp) {
      var wa = document.createElement('a');
      wa.href = 'https://wa.me/' + encodeURIComponent(String(s.whatsapp).replace(/^\+/, ''));
      wa.target = '_blank';
      wa.rel = 'noopener';
      wa.textContent = 'واتساپ';
      linksEl.appendChild(wa);
    }
    if (s.telegram) {
      var tg = document.createElement('a');
      tg.href = 'https://t.me/' + encodeURIComponent(String(s.telegram).replace(/^@/, ''));
      tg.target = '_blank';
      tg.rel = 'noopener';
      tg.textContent = 'تلگرام';
      linksEl.appendChild(tg);
    }
  }

  function needsIntro() {
    var s = WBCB.settings || {};
    return s.askName || s.askEmail;
  }

  function showIntroIfNeeded() {
    if (!needsIntro() || state.booted) {
      if (intro) intro.hidden = true;
      return;
    }
    if (intro) intro.hidden = false;
    if (emailWrap) emailWrap.hidden = !WBCB.settings.askEmail;
  }

  function pageContext() {
    var c = WBCB.context || {};
    return {
      page_url: c.page_url || window.location.href,
      page_title: c.page_title || (document.title || ''),
      product_id: c.product_id || 0,
      product_name: c.product_name || '',
      product_url: c.product_url || '',
      product_image: c.product_image || ''
    };
  }

  function bootstrap() {
    var ctx = pageContext();
    var payload = {
      page_url: ctx.page_url,
      page_title: ctx.page_title,
      product_id: ctx.product_id,
      product_name: ctx.product_name,
      product_url: ctx.product_url,
      product_image: ctx.product_image,
      name: nameEl ? nameEl.value : '',
      email: emailEl ? emailEl.value : ''
    };
    if (state.token) payload.token = state.token;
    return post('wbcb_visitor_bootstrap', payload).then(function (res) {
      if (!res || !res.success) {
        throw new Error((res && res.data && res.data.message) || WBCB.i18n.error);
      }
      state.token = res.data.token;
      state.booted = true;
      if (intro) intro.hidden = true;
      appendMessages(res.data.messages, true);
      startPoll();
    });
  }

  function startPoll() {
    stopPoll();
    state.polling = setInterval(function () {
      if (!state.open || !state.token) return;
      post('wbcb_visitor_poll', { token: state.token, after_id: state.lastId }).then(function (res) {
        if (res && res.success && res.data.messages && res.data.messages.length) {
          appendMessages(res.data.messages, false);
        }
      });
    }, 5000);
  }

  function stopPoll() {
    if (state.polling) clearInterval(state.polling);
    state.polling = null;
  }

  function openPanel() {
    state.open = true;
    panel.hidden = false;
    launcher.setAttribute('aria-expanded', 'true');
    showIntroIfNeeded();
    if (!state.booted && !needsIntro()) {
      bootstrap().catch(function () {});
    }
    if (input) input.focus();
  }

  function closePanel() {
    state.open = false;
    panel.hidden = true;
    launcher.setAttribute('aria-expanded', 'false');
  }

  launcher.addEventListener('click', function () {
    if (state.open) closePanel();
    else openPanel();
  });
  closeBtn.addEventListener('click', closePanel);

  if (startBtn) {
    startBtn.addEventListener('click', function () {
      bootstrap().catch(function (e) {
        alert(e.message || WBCB.i18n.error);
      });
    });
  }

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    var text = (input.value || '').trim();
    if (!text) return;
    if (!state.booted) {
      if (needsIntro() && intro && !intro.hidden) {
        alert(WBCB.i18n.start || 'ابتدا شروع گفتگو را بزنید');
        return;
      }
      bootstrap().then(function () {
        return send(text);
      }).catch(function (err) {
        alert(err.message || WBCB.i18n.error);
      });
      return;
    }
    send(text);
  });

  function send(text) {
    input.disabled = true;
    var ctx = pageContext();
    return post('wbcb_visitor_send', {
      token: state.token,
      body: text,
      name: nameEl ? nameEl.value : '',
      email: emailEl ? emailEl.value : '',
      page_url: ctx.page_url,
      page_title: ctx.page_title,
      product_id: ctx.product_id,
      product_name: ctx.product_name,
      product_url: ctx.product_url,
      product_image: ctx.product_image
    }).then(function (res) {
      input.disabled = false;
      if (!res || !res.success) {
        alert((res && res.data && res.data.message) || WBCB.i18n.error);
        return;
      }
      input.value = '';
      appendMessages(res.data.messages, false);
      post('wbcb_visitor_poll', { token: state.token, after_id: state.lastId }).then(function (r2) {
        if (r2 && r2.success) appendMessages(r2.data.messages, false);
      });
    }).catch(function () {
      input.disabled = false;
      alert(WBCB.i18n.error);
    });
  }

  setupLinks();
})();
