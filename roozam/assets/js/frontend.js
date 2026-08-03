(function () {
  'use strict';

  if (typeof window.RZM === 'undefined') return;

  var cfg = window.RZM;
  var J = window.RZMJalali;
  var root = document.querySelector('[data-rzm-root]');
  if (!root || !J) return;

  var state = {
    date: cfg.today || J.todayISO(),
    day: clone(cfg.day || { date: cfg.today, tasks: [], note: '' }),
    routines: clone(cfg.routines || []),
    prefs: clone(cfg.prefs || { wake_time: '07:00', sleep_time: '23:00', break_minutes: 10 }),
    saving: false,
  };

  var els = {
    weekday: root.querySelector('[data-rzm-weekday]'),
    jalali: root.querySelector('[data-rzm-jalali]'),
    list: root.querySelector('[data-rzm-list]'),
    empty: root.querySelector('[data-rzm-empty]'),
    note: root.querySelector('[data-rzm-note]'),
    alert: root.querySelector('[data-rzm-alert]'),
    progressText: root.querySelector('[data-rzm-progress-text]'),
    progressSub: root.querySelector('[data-rzm-progress-sub]'),
    ringFg: root.querySelector('[data-rzm-ring-fg]'),
    taskCount: root.querySelector('[data-rzm-task-count]'),
    routines: root.querySelector('[data-rzm-routines]'),
    dialogTask: root.querySelector('[data-rzm-dialog-task]'),
    dialogRoutines: root.querySelector('[data-rzm-dialog-routines]'),
    dialogPrefs: root.querySelector('[data-rzm-dialog-prefs]'),
    taskForm: root.querySelector('[data-rzm-task-form]'),
    routineForm: root.querySelector('[data-rzm-routine-form]'),
    prefsForm: root.querySelector('[data-rzm-prefs-form]'),
  };

  boot();

  function boot() {
    bind();
    if (!cfg.loggedIn) {
      loadLocal();
      flash(cfg.i18n.loginHint, 'info');
    } else {
      fetchState();
    }
    render();
  }

  function bind() {
    root.querySelector('[data-rzm-prev]').addEventListener('click', function () {
      changeDay(-1);
    });
    root.querySelector('[data-rzm-next]').addEventListener('click', function () {
      changeDay(1);
    });
    root.querySelector('[data-rzm-plan]').addEventListener('click', planDay);
    root.querySelector('[data-rzm-open-task]').addEventListener('click', function () {
      openDialog(els.dialogTask);
    });
    root.querySelector('[data-rzm-open-routines]').addEventListener('click', function () {
      renderRoutines();
      openDialog(els.dialogRoutines);
    });
    root.querySelector('[data-rzm-open-prefs]').addEventListener('click', function () {
      els.prefsForm.wake_time.value = state.prefs.wake_time;
      els.prefsForm.sleep_time.value = state.prefs.sleep_time;
      els.prefsForm.break_minutes.value = state.prefs.break_minutes;
      openDialog(els.dialogPrefs);
    });

    root.querySelectorAll('[data-rzm-close]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var dlg = btn.closest('dialog');
        if (dlg) dlg.close();
      });
    });

    els.taskForm.addEventListener('submit', function (e) {
      e.preventDefault();
      var fd = new FormData(els.taskForm);
      var task = {
        id: uid(),
        title: String(fd.get('title') || '').trim(),
        duration: clampInt(fd.get('duration'), 5, 480, 30),
        priority: String(fd.get('priority') || 'medium'),
        start: String(fd.get('start') || ''),
        category: String(fd.get('category') || '').trim(),
        done: false,
        from_routine: false,
      };
      if (!task.title) return;
      state.day.tasks.push(task);
      sortTasks();
      els.taskForm.reset();
      els.taskForm.duration.value = 30;
      els.dialogTask.close();
      persistDay();
      render();
      flash('کار اضافه شد', 'ok');
    });

    els.routineForm.addEventListener('submit', function (e) {
      e.preventDefault();
      var fd = new FormData(els.routineForm);
      var title = String(fd.get('title') || '').trim();
      if (!title) return;
      state.routines.push({
        id: uid(),
        title: title,
        duration: clampInt(fd.get('duration'), 5, 240, 20),
        priority: 'medium',
        start: '',
        enabled: true,
        category: 'عادت',
      });
      els.routineForm.reset();
      els.routineForm.duration.value = 20;
      persistRoutines();
      renderRoutines();
    });

    els.prefsForm.addEventListener('submit', function (e) {
      e.preventDefault();
      state.prefs = {
        wake_time: els.prefsForm.wake_time.value || '07:00',
        sleep_time: els.prefsForm.sleep_time.value || '23:00',
        break_minutes: clampInt(els.prefsForm.break_minutes.value, 0, 60, 10),
      };
      els.dialogPrefs.close();
      persistPrefs();
      flash('ساعات روز ذخیره شد', 'ok');
    });

    var noteTimer;
    els.note.addEventListener('input', function () {
      state.day.note = els.note.value;
      clearTimeout(noteTimer);
      noteTimer = setTimeout(persistDay, 500);
    });
  }

  function changeDay(delta) {
    state.date = J.shiftISO(state.date, delta);
    if (cfg.loggedIn) {
      fetchState();
    } else {
      loadLocal();
      render();
    }
  }

  function fetchState() {
    post('rzm_get_state', { date: state.date }).then(function (res) {
      if (!res || !res.success) return;
      state.day = res.data.day;
      state.routines = res.data.routines;
      state.prefs = res.data.prefs;
      render();
    });
  }

  function planDay() {
    var btn = root.querySelector('[data-rzm-plan]');
    btn.classList.add('is-busy');
    if (cfg.loggedIn) {
      post('rzm_auto_plan', { date: state.date, day: JSON.stringify(state.day) })
        .then(function (res) {
          btn.classList.remove('is-busy');
          if (!res || !res.success) {
            flash((res && res.data && res.data.message) || cfg.i18n.error, 'err');
            return;
          }
          state.day = res.data.day;
          render(true);
          flash(cfg.i18n.planDone, 'ok');
        })
        .catch(function () {
          btn.classList.remove('is-busy');
          flash(cfg.i18n.error, 'err');
        });
      return;
    }
    state.day = autoPlanLocal(state.day, state.routines, state.prefs);
    saveLocal();
    btn.classList.remove('is-busy');
    render(true);
    flash(cfg.i18n.planDone, 'ok');
  }

  function persistDay() {
    if (!cfg.loggedIn) {
      saveLocal();
      return;
    }
    post('rzm_save_day', { date: state.date, day: JSON.stringify(state.day) });
  }

  function persistRoutines() {
    if (!cfg.loggedIn) {
      saveLocal();
      return;
    }
    post('rzm_save_routines', { routines: JSON.stringify(state.routines) });
  }

  function persistPrefs() {
    if (!cfg.loggedIn) {
      saveLocal();
      return;
    }
    post('rzm_save_prefs', { prefs: JSON.stringify(state.prefs) });
  }

  function render(animate) {
    els.weekday.textContent = J.weekdayName(state.date);
    els.jalali.textContent = J.formatJalali(state.date);
    els.note.value = state.day.note || '';

    var tasks = state.day.tasks || [];
    els.list.innerHTML = '';
    els.empty.hidden = tasks.length > 0;
    els.taskCount.textContent = tasks.length
      ? J.toPersianDigits(tasks.length) + ' کار'
      : '';

    tasks.forEach(function (task, index) {
      var li = document.createElement('li');
      li.className = 'rzm-item' + (task.done ? ' is-done' : '') + (animate ? ' is-enter' : '');
      if (animate) li.style.animationDelay = index * 45 + 'ms';
      li.dataset.id = task.id;

      var timeLabel = task.start
        ? J.toPersianDigits(task.start) + ' · ' + J.toPersianDigits(task.duration) + 'د'
        : cfg.i18n.unscheduled + ' · ' + J.toPersianDigits(task.duration) + 'د';

      li.innerHTML =
        '<button type="button" class="rzm-check" data-act="toggle" aria-label="انجام شد"></button>' +
        '<div class="rzm-item-body">' +
        '<div class="rzm-item-top">' +
        '<strong>' +
        escapeHtml(task.title) +
        '</strong>' +
        '<span class="rzm-prio rzm-prio-' +
        escapeHtml(task.priority) +
        '">' +
        priorityLabel(task.priority) +
        '</span>' +
        '</div>' +
        '<div class="rzm-item-meta">' +
        '<span>' +
        timeLabel +
        '</span>' +
        (task.category ? '<span>' + escapeHtml(task.category) + '</span>' : '') +
        (task.from_routine ? '<span>عادت</span>' : '') +
        '</div>' +
        '</div>' +
        '<button type="button" class="rzm-iconbtn" data-act="delete" aria-label="حذف">×</button>';

      li.addEventListener('click', function (e) {
        var act = e.target.getAttribute('data-act');
        if (!act) return;
        if (act === 'toggle') {
          task.done = !task.done;
          persistDay();
          render();
        } else if (act === 'delete') {
          state.day.tasks = state.day.tasks.filter(function (t) {
            return t.id !== task.id;
          });
          persistDay();
          render();
        }
      });

      els.list.appendChild(li);
    });

    var total = tasks.length;
    var done = tasks.filter(function (t) {
      return t.done;
    }).length;
    var pct = total ? Math.round((done / total) * 100) : 0;
    els.progressText.textContent = J.toPersianDigits(pct) + '٪';
    els.progressSub.textContent = total
      ? J.toPersianDigits(done) + ' از ' + J.toPersianDigits(total) + ' انجام شده'
      : 'هنوز کاری ثبت نشده';
    if (els.ringFg) {
      var circ = 2 * Math.PI * 30;
      els.ringFg.style.strokeDasharray = String(circ);
      els.ringFg.style.strokeDashoffset = String(circ - (circ * pct) / 100);
    }
  }

  function renderRoutines() {
    els.routines.innerHTML = '';
    state.routines.forEach(function (r) {
      var li = document.createElement('li');
      li.className = 'rzm-routine' + (r.enabled ? '' : ' is-off');
      li.innerHTML =
        '<label><input type="checkbox" ' +
        (r.enabled ? 'checked' : '') +
        ' /> <span>' +
        escapeHtml(r.title) +
        '</span></label>' +
        '<span class="rzm-muted">' +
        J.toPersianDigits(r.duration) +
        'د' +
        (r.start ? ' · ' + J.toPersianDigits(r.start) : '') +
        '</span>' +
        '<button type="button" class="rzm-iconbtn" aria-label="حذف">×</button>';
      li.querySelector('input').addEventListener('change', function (e) {
        r.enabled = !!e.target.checked;
        persistRoutines();
        renderRoutines();
      });
      li.querySelector('button').addEventListener('click', function () {
        state.routines = state.routines.filter(function (x) {
          return x.id !== r.id;
        });
        persistRoutines();
        renderRoutines();
      });
      els.routines.appendChild(li);
    });
  }

  function autoPlanLocal(day, routines, prefs) {
    var wake = toMin(prefs.wake_time || '07:00');
    var sleep = toMin(prefs.sleep_time || '23:00');
    var brk = clampInt(prefs.break_minutes, 0, 60, 10);
    if (sleep <= wake) sleep = wake + 12 * 60;

    var fixed = [];
    var flex = [];
    var seen = {};
    var tasks = clone(day.tasks || []);

    tasks.forEach(function (t) {
      seen[t.title + '|' + t.duration] = true;
      if (t.start) fixed.push(t);
      else flex.push(t);
    });

    (routines || []).forEach(function (r) {
      if (!r.enabled) return;
      var key = r.title + '|' + r.duration;
      if (seen[key]) return;
      var item = {
        id: uid(),
        title: r.title,
        duration: r.duration,
        priority: r.priority || 'medium',
        start: r.start || '',
        category: r.category || 'عادت',
        done: false,
        from_routine: true,
      };
      if (item.start) fixed.push(item);
      else flex.push(item);
    });

    fixed.sort(function (a, b) {
      return toMin(a.start) - toMin(b.start);
    });
    var rank = { high: 0, medium: 1, low: 2 };
    flex.sort(function (a, b) {
      var ra = rank[a.priority] != null ? rank[a.priority] : 1;
      var rb = rank[b.priority] != null ? rank[b.priority] : 1;
      if (ra !== rb) return ra - rb;
      return a.duration - b.duration;
    });

    var busy = fixed.map(function (t) {
      var s = toMin(t.start);
      return [s, s + Math.max(5, +t.duration || 30)];
    });
    busy.sort(function (a, b) {
      return a[0] - b[0];
    });

    var cursor = wake;
    flex.forEach(function (task) {
      var dur = Math.max(5, +task.duration || 30);
      var scan = Math.max(cursor, wake);
      var placed = false;
      while (scan + dur <= sleep) {
        var end = scan + dur;
        if (!overlaps(scan, end, busy)) {
          task.start = fromMin(scan);
          busy.push([scan, end]);
          busy.sort(function (a, b) {
            return a[0] - b[0];
          });
          cursor = end + brk;
          placed = true;
          break;
        }
        var next = scan + 5;
        busy.forEach(function (b) {
          if (scan < b[1] && end > b[0]) next = Math.max(next, b[1] + brk);
        });
        scan = next;
      }
      if (!placed) task.start = '';
    });

    var all = fixed.concat(flex);
    all.sort(function (a, b) {
      if (!a.start && !b.start) return 0;
      if (!a.start) return 1;
      if (!b.start) return -1;
      return toMin(a.start) - toMin(b.start);
    });
    return { date: day.date || state.date, tasks: all, note: day.note || '' };
  }

  function overlaps(s, e, busy) {
    for (var i = 0; i < busy.length; i++) {
      if (s < busy[i][1] && e > busy[i][0]) return true;
    }
    return false;
  }

  function toMin(t) {
    var p = String(t || '00:00').split(':');
    return (+p[0] || 0) * 60 + (+p[1] || 0);
  }

  function fromMin(m) {
    m = Math.max(0, Math.min(24 * 60 - 1, m | 0));
    var h = String(Math.floor(m / 60)).padStart(2, '0');
    var min = String(m % 60).padStart(2, '0');
    return h + ':' + min;
  }

  function sortTasks() {
    state.day.tasks.sort(function (a, b) {
      if (!a.start && !b.start) return 0;
      if (!a.start) return 1;
      if (!b.start) return -1;
      return toMin(a.start) - toMin(b.start);
    });
  }

  function localKey() {
    return 'rzm_v1_' + state.date;
  }

  function saveLocal() {
    try {
      localStorage.setItem(
        localKey(),
        JSON.stringify({ day: state.day, routines: state.routines, prefs: state.prefs })
      );
      localStorage.setItem('rzm_v1_routines', JSON.stringify(state.routines));
      localStorage.setItem('rzm_v1_prefs', JSON.stringify(state.prefs));
    } catch (e) {}
  }

  function loadLocal() {
    try {
      var raw = localStorage.getItem(localKey());
      var rR = localStorage.getItem('rzm_v1_routines');
      var rP = localStorage.getItem('rzm_v1_prefs');
      if (rR) state.routines = JSON.parse(rR);
      if (rP) state.prefs = JSON.parse(rP);
      if (raw) {
        var data = JSON.parse(raw);
        state.day = data.day || { date: state.date, tasks: [], note: '' };
      } else {
        state.day = { date: state.date, tasks: [], note: '' };
      }
      state.day.date = state.date;
    } catch (e) {
      state.day = { date: state.date, tasks: [], note: '' };
    }
  }

  function post(action, fields) {
    var body = new FormData();
    body.append('action', action);
    body.append('nonce', cfg.nonce || '');
    Object.keys(fields || {}).forEach(function (k) {
      body.append(k, fields[k]);
    });
    return fetch(cfg.ajaxUrl, { method: 'POST', body: body, credentials: 'same-origin' }).then(function (r) {
      return r.json();
    });
  }

  function openDialog(dlg) {
    if (dlg && typeof dlg.showModal === 'function') dlg.showModal();
  }

  function flash(msg, type) {
    if (!els.alert || !msg) return;
    els.alert.hidden = false;
    els.alert.className = 'rzm-alert is-' + (type || 'info');
    els.alert.textContent = msg;
    clearTimeout(flash._t);
    flash._t = setTimeout(function () {
      els.alert.hidden = true;
    }, 3200);
  }

  function priorityLabel(p) {
    if (p === 'high') return cfg.i18n.priorityHigh;
    if (p === 'low') return cfg.i18n.priorityLow;
    return cfg.i18n.priorityMed;
  }

  function uid() {
    return 't' + Math.random().toString(36).slice(2, 10);
  }

  function clampInt(v, min, max, fallback) {
    var n = parseInt(v, 10);
    if (isNaN(n)) return fallback;
    return Math.max(min, Math.min(max, n));
  }

  function clone(x) {
    return JSON.parse(JSON.stringify(x));
  }

  function escapeHtml(s) {
    return String(s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }
})();
