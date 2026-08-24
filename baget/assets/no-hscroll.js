/**
 * Baget — قفل JS اجباری اسکرول افقی.
 * اگر چیزی هنوز overflow بسازد، محور X را صفر نگه می‌دارد.
 */
(function () {
  'use strict';

  function clampX() {
    var x = window.scrollX || window.pageXOffset || 0;
    if (x !== 0) {
      window.scrollTo(0, window.scrollY || window.pageYOffset || 0);
    }
    if (document.documentElement) {
      document.documentElement.scrollLeft = 0;
    }
    if (document.body) {
      document.body.scrollLeft = 0;
    }
  }

  function harden() {
    if (document.documentElement) {
      document.documentElement.style.setProperty('overflow-x', 'hidden', 'important');
      document.documentElement.style.setProperty('max-width', '100%', 'important');
      document.documentElement.classList.add('wccp-no-hscroll-lock');
    }
    if (document.body) {
      document.body.style.setProperty('overflow-x', 'hidden', 'important');
      document.body.style.setProperty('max-width', '100%', 'important');
      document.body.style.setProperty('width', '100%', 'important');
      document.body.classList.add('wccp-no-hscroll-lock');
    }
    clampX();
  }

  harden();

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', harden);
  }
  window.addEventListener('load', harden);
  window.addEventListener('scroll', clampX, { passive: true });
  window.addEventListener('resize', harden, { passive: true });
  window.addEventListener('orientationchange', function () {
    setTimeout(harden, 50);
  });

  // بعد از آپدیت‌های ووکامرس/المنتور دوباره قفل کن
  if (typeof jQuery !== 'undefined') {
    jQuery(document.body).on('updated_checkout updated_cart_totals wc_fragments_refreshed', harden);
  }

  // مشاهده تغییر DOM (درج المان پهن)
  if (typeof MutationObserver !== 'undefined' && document.documentElement) {
    var t = null;
    var obs = new MutationObserver(function () {
      if (t) clearTimeout(t);
      t = setTimeout(clampX, 60);
    });
    obs.observe(document.documentElement, { childList: true, subtree: true, attributes: true });
  }
})();
