(function () {
  'use strict';

  var cfg = window.DIDAdmin || {};

  function showProv() {
    var sel = document.getElementById('google_serp_provider');
    if (!sel) return;
    var v = sel.value;
    document.querySelectorAll('.did-prov').forEach(function (row) {
      var show = row.classList.contains('did-prov-' + v);
      row.style.display = show ? '' : 'none';
    });
  }
  var prov = document.getElementById('google_serp_provider');
  if (prov) {
    prov.addEventListener('change', showProv);
    showProv();
  }

  function post(action, extra) {
    var body = new FormData();
    body.append('action', action);
    body.append('nonce', cfg.nonce || '');
    body.append('project_id', String(cfg.projectId || extra.project_id || 0));
    Object.keys(extra || {}).forEach(function (k) {
      if (k === 'project_id') return;
      body.append(k, extra[k]);
    });
    return fetch(cfg.ajax, { method: 'POST', credentials: 'same-origin', body: body }).then(function (r) {
      return r.json();
    });
  }

  function msgEl() {
    return document.getElementById('did-job-msg');
  }

  function setMsg(text, err) {
    var el = msgEl();
    if (!el) return;
    el.hidden = false;
    el.removeAttribute('hidden');
    el.textContent = text;
    el.classList.toggle('is-err', !!err);
  }

  function disableBtns(on) {
    ['did-run-crawl', 'did-run-rank', 'did-run-backlinks'].forEach(function (id) {
      var b = document.getElementById(id);
      if (b) b.disabled = on || !cfg.licensed;
    });
  }

  function loop(action) {
    if (!cfg.licensed) {
      setMsg('لایسنس فعال نیست.', true);
      return;
    }
    disableBtns(true);
    setMsg((cfg.i18n && cfg.i18n.running) || 'در حال اجرا…', false);

    function tick() {
      post(action, {})
        .then(function (res) {
          if (!res || !res.success) {
            var m = res && res.data && res.data.message ? res.data.message : (cfg.i18n && cfg.i18n.error) || 'خطا';
            setMsg(m, true);
            disableBtns(false);
            return;
          }
          var d = res.data || {};
          var line = d.message || '';
          if (typeof d.percent === 'number') {
            line += ' (' + d.percent + '٪)';
          }
          setMsg(line, false);
          if (d.done) {
            disableBtns(false);
            window.setTimeout(function () {
              window.location.reload();
            }, 700);
            return;
          }
          window.setTimeout(tick, 450);
        })
        .catch(function () {
          setMsg('خطای ارتباط با سرور.', true);
          disableBtns(false);
        });
    }
    tick();
  }

  var crawl = document.getElementById('did-run-crawl');
  if (crawl) {
    crawl.addEventListener('click', function () {
      loop('did_crawl_step');
    });
  }
  var rank = document.getElementById('did-run-rank');
  if (rank) {
    rank.addEventListener('click', function () {
      loop('did_rank_step');
    });
  }
  var backlinks = document.getElementById('did-run-backlinks');
  if (backlinks) {
    backlinks.addEventListener('click', function () {
      loop('did_backlinks_step');
    });
  }
})();
