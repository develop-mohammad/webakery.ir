(function (global) {
  'use strict';

  function uid() {
    return 't' + Math.random().toString(36).slice(2, 10);
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

  function overlaps(s, e, busy) {
    for (var i = 0; i < busy.length; i++) {
      if (s < busy[i][1] && e > busy[i][0]) return true;
    }
    return false;
  }

  function clampInt(v, min, max, fallback) {
    var n = parseInt(v, 10);
    if (isNaN(n)) return fallback;
    return Math.max(min, Math.min(max, n));
  }

  function clone(x) {
    return JSON.parse(JSON.stringify(x));
  }

  function defaultRoutines() {
    return [
      {
        id: 'r1',
        title: 'ورزش سبک',
        duration: 30,
        priority: 'medium',
        start: '07:30',
        enabled: true,
        category: 'سلامت',
      },
      {
        id: 'r2',
        title: 'مرور اهداف روز',
        duration: 15,
        priority: 'high',
        start: '08:15',
        enabled: true,
        category: 'تمرکز',
      },
      {
        id: 'r3',
        title: 'مطالعه',
        duration: 45,
        priority: 'medium',
        start: '',
        enabled: true,
        category: 'رشد',
      },
    ];
  }

  function defaultPrefs() {
    return { wake_time: '07:00', sleep_time: '23:00', break_minutes: 10 };
  }

  /**
   * زمان‌بندی هوشمند: کارهای ثابت را نگه می‌دارد و بقیه را در شکاف‌های آزاد می‌چیند.
   */
  function autoPlan(day, routines, prefs) {
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

    return {
      date: day.date,
      tasks: all,
      note: day.note || '',
    };
  }

  function endTime(start, duration) {
    if (!start) return '';
    return fromMin(toMin(start) + Math.max(5, +duration || 30));
  }

  function sortTasks(tasks) {
    return (tasks || []).slice().sort(function (a, b) {
      if (!a.start && !b.start) return 0;
      if (!a.start) return 1;
      if (!b.start) return -1;
      return toMin(a.start) - toMin(b.start);
    });
  }

  global.RZMPlanner = {
    uid: uid,
    toMin: toMin,
    fromMin: fromMin,
    clampInt: clampInt,
    clone: clone,
    autoPlan: autoPlan,
    endTime: endTime,
    sortTasks: sortTasks,
    defaultRoutines: defaultRoutines,
    defaultPrefs: defaultPrefs,
  };
})(typeof window !== 'undefined' ? window : this);
