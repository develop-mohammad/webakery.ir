(function () {
  'use strict';
  document.addEventListener('DOMContentLoaded', function () {
    var slug = document.querySelector('input[name="role_slug"]');
    var label = document.querySelector('input[name="role_label"]');
    if (!slug || !label) return;

    var touched = false;
    slug.addEventListener('input', function () {
      touched = true;
    });
    label.addEventListener('input', function () {
      if (touched && slug.value) return;
      var map = {
        'آ': 'a', 'ا': 'a', 'ب': 'b', 'پ': 'p', 'ت': 't', 'ث': 's', 'ج': 'j', 'چ': 'ch',
        'ح': 'h', 'خ': 'kh', 'د': 'd', 'ذ': 'z', 'ر': 'r', 'ز': 'z', 'ژ': 'zh', 'س': 's',
        'ش': 'sh', 'ص': 's', 'ض': 'z', 'ط': 't', 'ظ': 'z', 'ع': 'a', 'غ': 'gh', 'ف': 'f',
        'ق': 'gh', 'ک': 'k', 'گ': 'g', 'ل': 'l', 'م': 'm', 'ن': 'n', 'و': 'v', 'ه': 'h',
        'ی': 'y', 'ئ': 'y', 'ي': 'y', 'ك': 'k'
      };
      var out = '';
      var src = label.value.trim();
      for (var i = 0; i < src.length; i++) {
        var ch = src.charAt(i);
        if (map[ch]) out += map[ch];
        else if (/[a-zA-Z0-9]/.test(ch)) out += ch.toLowerCase();
        else if (/\s|_|-/.test(ch)) out += '_';
      }
      out = out.replace(/_+/g, '_').replace(/^_|_$/g, '');
      slug.value = out;
    });
  });
})();
