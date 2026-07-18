(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {
    var select = document.getElementById('al-role-select');
    var panels = Array.prototype.slice.call(document.querySelectorAll('.al-role-panel'));
    if (!select || !panels.length) return;

    function showRole(role) {
      panels.forEach(function (p) {
        p.style.display = p.getAttribute('data-role') === role ? 'block' : 'none';
      });
    }

    panels.forEach(function (p) {
      if (p.getAttribute('data-role') === 'administrator') {
        p.style.display = 'none';
      }
    });

    select.addEventListener('change', function () {
      showRole(select.value);
    });

    if (select.value === 'administrator') {
      var first = panels.find(function (p) {
        return p.getAttribute('data-role') !== 'administrator';
      });
      if (first) select.value = first.getAttribute('data-role');
    }
    showRole(select.value);

    var checkAll = document.getElementById('al-check-all');
    var uncheckAll = document.getElementById('al-uncheck-all');

    function activePanel() {
      return panels.find(function (p) {
        return p.style.display !== 'none';
      });
    }

    if (checkAll) {
      checkAll.addEventListener('click', function (e) {
        e.preventDefault();
        var panel = activePanel();
        if (!panel) return;
        panel.querySelectorAll('input[type="checkbox"]').forEach(function (cb) {
          cb.checked = true;
        });
      });
    }

    if (uncheckAll) {
      uncheckAll.addEventListener('click', function (e) {
        e.preventDefault();
        var panel = activePanel();
        if (!panel) return;
        panel.querySelectorAll('input[type="checkbox"]').forEach(function (cb) {
          cb.checked = false;
        });
      });
    }

    document.querySelectorAll('.al-item input[type="checkbox"]').forEach(function (cb) {
      cb.addEventListener('click', function (e) {
        e.stopPropagation();
      });
      cb.parentElement.addEventListener('click', function (e) {
        if (e.target === cb) return;
        cb.checked = !cb.checked;
      });
    });
  });
})();
