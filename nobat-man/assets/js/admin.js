(function () {
  'use strict';
  document.addEventListener('DOMContentLoaded', function () {
    if (typeof attachJalaliDatePicker === 'function') {
      document.querySelectorAll('.nm-jalali-input').forEach(function (input) {
        attachJalaliDatePicker(input);
      });
    }
  });
})();
