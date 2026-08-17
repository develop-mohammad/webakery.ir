(function () {
  'use strict';

  var J = window.RZMJalali;
  var P = window.RZMPlanner;
  var root = document.querySelector('[data-rzm-root]');
  if (!root || !J || !P) return;

  var STORAGE_DAYS = 'roozam.days.v1';
  var STORAGE_ROUTINES = 'roozam.routines.v1';
  var STORAGE_PREFS = 'roozam.prefs.v1';

  var state = {
    date: J.todayISO(),
    day: { date: J.todayISO(), tasks: [], note: '' },
    routines: P.defaultRoutines(),
    prefs: P.defaultPrefs(),
  };

  var deferredInstall = null;

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
    installBtn: root.querySelector('[data-rzm-install]'),
    exportBtn: root.querySelector('[data-rzm-export]'),
    importInput: root.querySelector('[data-rzm-import]'),
  };

  boot();

  function boot() {
    loadAll();
    bind();
    registerSW();
    render();
  }

  function bind() {
    root.querySelector('[data-rzm-prev]').addEventListener('click', function () {
      changeDay(-1);
    });
    root.querySelector('[data-rzm-next]').addEventListener('click', function () {
      changeDay(1);
    });

    onAll('[data-rzm-plan]', planDay);
    onAll('[data-rzm-open-task]', function () {
      openDialog(els.dialogTask);
    });
    onAll('[data-rzm-open-routines]', function () {
      renderRoutines();
      openDialog(els.dialogRoutines);
    });
    onAll('[data-rzm-open-prefs]', function () {
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

    var homeTab = root.querySelector('[data-rzm-tab="home"]');
    if (homeTab) {
      homeTab.addEventListener('click', function () {
        window.scrollTo({ top: 0, behavior: 'smooth' });
      });
    }

    els.taskForm.addEventListener('submit', function (e) {
      e.preventDefault();
      var fd = new FormData(els.taskForm);
      var task = {
        id: P.uid(),
        title: String(fd.get('title') || '').trim(),
        duration: P.clampInt(fd.get('duration'), 5, 480, 30),
        priority: String(fd.get('priority') || 'medium'),
        start: String(fd.get('start') || ''),
        category: String(fd.get('category') || '').trim(),
        done: false,
        from_routine: false,
      };
      if (!task.title) return;
      state.day.tasks.push(task);
      state.day.tasks = P.sortTasks(state.day.tasks);
      els.taskForm.reset();
      els.taskForm.duration.value = 30;
      els.dialogTask.close();
      saveDay();
      render();
      flash('کار اضافه شد', 'ok');
    });

    els.routineForm.addEventListener('submit', function (e) {
      e.preventDefault();
      var fd = new FormData(els.routineForm);
      var title = String(fd.get('title') || '').trim();
      if (!title) return;
      state.routines.push({
        id: P.uid(),
        title: title,
        duration: P.clampInt(fd.get('duration'), 5, 240, 20),
        priority: 'medium',
        start: '',
        enabled: true,
        category: 'عادت',
      });
      els.routineForm.reset();
      els.routineForm.duration.value = 20;
      saveRoutines();
      renderRoutines();
    });

    els.prefsForm.addEventListener('submit', function (e) {
      e.preventDefault();
      state.prefs = {
        wake_time: els.prefsForm.wake_time.value || '07:00',
        sleep_time: els.prefsForm.sleep_time.value || '23:00',
        break_minutes: P.clampInt(els.prefsForm.break_minutes.value, 0, 60, 10),
      };
      els.dialogPrefs.close();
      savePrefs();
      flash('ساعات روز ذخیره شد', 'ok');
    });

    var noteTimer;
    els.note.addEventListener('input', function () {
      state.day.note = els.note.value;
      clearTimeout(noteTimer);
      noteTimer = setTimeout(saveDay, 400);
    });

    els.exportBtn.addEventListener('click', exportBackup);
    els.importInput.addEventListener('change', importBackup);

    window.addEventListener('beforeinstallprompt', function (e) {
      e.preventDefault();
      deferredInstall = e;
      if (els.installBtn) els.installBtn.hidden = false;
    });

    if (els.installBtn) {
      els.installBtn.addEventListener('click', function () {
        if (!deferredInstall) return;
        deferredInstall.prompt();
        deferredInstall.userChoice.finally(function () {
          deferredInstall = null;
          els.installBtn.hidden = true;
        });
      });
    }
  }

  function changeDay(delta) {
    saveDay();
    state.date = J.shiftISO(state.date, delta);
    loadDay();
    render();
  }

  function planDay() {
    state.day = P.autoPlan(state.day, state.routines, state.prefs);
    saveDay();
    render(true);
    flash('برنامه امروز چیده شد', 'ok');
  }

  function render(animate) {
    els.weekday.textContent = J.weekdayName(state.date);
    els.jalali.textContent = J.formatJalali(state.date);
    els.note.value = state.day.note || '';

    var tasks = state.day.tasks || [];
    els.list.innerHTML = '';
    els.empty.hidden = tasks.length > 0;
    els.taskCount.textContent = tasks.length ? J.toPersianDigits(tasks.length) + ' کار' : '';

    tasks.forEach(function (task, index) {
      var li = document.createElement('li');
      li.className = 'rzm-item' + (task.done ? ' is-done' : '') + (animate ? ' is-enter' : '');
      if (animate) li.style.animationDelay = index * 45 + 'ms';

      var end = P.endTime(task.start, task.duration);
      var timeLabel = task.start
        ? J.toPersianDigits(task.start) + (end ? '–' + J.toPersianDigits(end) : '')
        : 'بدون زمان · ' + J.toPersianDigits(task.duration) + 'د';
      var initial = (task.title || 'ک').charAt(0);

      li.innerHTML =
        '<div class="rzm-item-main">' +
        '<button type="button" class="rzm-avatar rzm-avatar-sm' +
        (task.done ? ' is-done-avatar' : '') +
        '" data-act="toggle" aria-label="انجام شد">' +
        (task.done ? '✓' : escapeHtml(initial)) +
        '</button>' +
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
        (task.category ? '<span>· ' + escapeHtml(task.category) + '</span>' : '') +
        (task.from_routine ? '<span>· عادت</span>' : '') +
        '</div>' +
        '</div>' +
        '</div>' +
        '<div class="rzm-item-actions">' +
        '<button type="button" class="rzm-action' +
        (task.done ? ' is-liked' : '') +
        '" data-act="toggle">' +
        (task.done ? 'انجام شد' : 'انجام') +
        '</button>' +
        '<button type="button" class="rzm-action rzm-action-danger" data-act="delete">حذف</button>' +
        '</div>';

      li.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-act]');
        if (!btn) return;
        var act = btn.getAttribute('data-act');
        if (act === 'toggle') {
          task.done = !task.done;
          saveDay();
          render();
        } else if (act === 'delete') {
          state.day.tasks = state.day.tasks.filter(function (t) {
            return t.id !== task.id;
          });
          saveDay();
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
        saveRoutines();
        renderRoutines();
      });
      li.querySelector('button').addEventListener('click', function () {
        state.routines = state.routines.filter(function (x) {
          return x.id !== r.id;
        });
        saveRoutines();
        renderRoutines();
      });
      els.routines.appendChild(li);
    });
  }

  function loadAll() {
    try {
      var prefs = JSON.parse(localStorage.getItem(STORAGE_PREFS) || 'null');
      if (prefs) state.prefs = Object.assign(P.defaultPrefs(), prefs);
      var routines = JSON.parse(localStorage.getItem(STORAGE_ROUTINES) || 'null');
      if (routines && routines.length) state.routines = routines;
    } catch (e) {}
    loadDay();
  }

  function loadDay() {
    try {
      var days = JSON.parse(localStorage.getItem(STORAGE_DAYS) || '{}');
      if (days[state.date]) {
        state.day = days[state.date];
        state.day.date = state.date;
      } else {
        state.day = { date: state.date, tasks: [], note: '' };
      }
    } catch (e) {
      state.day = { date: state.date, tasks: [], note: '' };
    }
  }

  function saveDay() {
    try {
      var days = JSON.parse(localStorage.getItem(STORAGE_DAYS) || '{}');
      days[state.date] = state.day;
      var keys = Object.keys(days).sort();
      while (keys.length > 120) {
        delete days[keys.shift()];
        keys = Object.keys(days).sort();
      }
      localStorage.setItem(STORAGE_DAYS, JSON.stringify(days));
    } catch (e) {}
  }

  function saveRoutines() {
    try {
      localStorage.setItem(STORAGE_ROUTINES, JSON.stringify(state.routines));
    } catch (e) {}
  }

  function savePrefs() {
    try {
      localStorage.setItem(STORAGE_PREFS, JSON.stringify(state.prefs));
    } catch (e) {}
  }

  function exportBackup() {
    saveDay();
    var payload = {
      version: 1,
      exportedAt: new Date().toISOString(),
      prefs: state.prefs,
      routines: state.routines,
      days: JSON.parse(localStorage.getItem(STORAGE_DAYS) || '{}'),
    };
    var blob = new Blob([JSON.stringify(payload, null, 2)], { type: 'application/json' });
    var a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = 'roozam-backup-' + state.date + '.json';
    a.click();
    URL.revokeObjectURL(a.href);
    flash('فایل پشتیبان دانلود شد', 'ok');
  }

  function importBackup(e) {
    var file = e.target.files && e.target.files[0];
    if (!file) return;
    var reader = new FileReader();
    reader.onload = function () {
      try {
        var data = JSON.parse(String(reader.result || '{}'));
        if (data.prefs) {
          state.prefs = Object.assign(P.defaultPrefs(), data.prefs);
          savePrefs();
        }
        if (Array.isArray(data.routines)) {
          state.routines = data.routines;
          saveRoutines();
        }
        if (data.days && typeof data.days === 'object') {
          localStorage.setItem(STORAGE_DAYS, JSON.stringify(data.days));
        }
        loadDay();
        render();
        flash('بازیابی انجام شد', 'ok');
      } catch (err) {
        flash('فایل پشتیبان نامعتبر است', 'err');
      }
      e.target.value = '';
    };
    reader.readAsText(file);
  }

  function registerSW() {
    if (!('serviceWorker' in navigator)) return;
    navigator.serviceWorker.register('./sw.js').catch(function () {});
  }

  function onAll(selector, handler) {
    root.querySelectorAll(selector).forEach(function (el) {
      el.addEventListener('click', handler);
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
    }, 3000);
  }

  function priorityLabel(p) {
    if (p === 'high') return 'مهم';
    if (p === 'low') return 'کم‌اهمیت';
    return 'عادی';
  }

  function escapeHtml(s) {
    return String(s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }
})();
