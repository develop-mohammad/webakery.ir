(function () {
  'use strict';

  var cfg = window.WCCP_QUICK_BUY;
  if (!cfg || !cfg.enabled || !cfg.checkoutUrl) {
    return;
  }

  var going = false;

  function go() {
    if (going) {
      return;
    }
    going = true;
    window.location.href = cfg.checkoutUrl;
  }

  function requestUrl(input) {
    if (!input) {
      return '';
    }
    if (typeof input === 'string') {
      return input;
    }
    if (input.url) {
      return String(input.url);
    }
    return '';
  }

  if (typeof jQuery !== 'undefined') {
    jQuery(document.body).on('added_to_cart', go);
  }

  document.addEventListener('added_to_cart', go);
  document.addEventListener('wc-blocks_added_to_cart', go);

  if (typeof window.fetch === 'function') {
    var origFetch = window.fetch;
    window.fetch = function (input) {
      var url = requestUrl(input);
      return origFetch.apply(this, arguments).then(function (res) {
        if (res && res.ok && /\/wc\/store(?:\/v\d+)?\/cart\/add-item/.test(url)) {
          go();
        }
        return res;
      });
    };
  }
})();
