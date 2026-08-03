(function () {
  'use strict';

  var COINS = {
    popular: [
      { sym: 'BTC', name: 'بیت کوین', color: '#f7931a', usd: '۶۳,۷۴۹$', toman: '۱۲,۲۶۲,۱۷۹,۷۷۹', change: 1, up: true },
      { sym: 'ETH', name: 'اتریوم', color: '#627eea', usd: '۱,۸۶۴$', toman: '۳۵۸,۵۶۳,۴۸۲', change: 0.3, up: true },
      { sym: 'USDT', name: 'تتر', color: '#26a17b', usd: '۱$', toman: '۱۹۲,۳۵۰', change: 0, up: true },
      { sym: 'BNB', name: 'بایننس کوین', color: '#f3ba2f', usd: '۵۹۱$', toman: '۱۱۳,۷۱۱,۵۴۹', change: 1, up: true },
      { sym: 'USDC', name: 'یو اس دی کوین', color: '#2775ca', usd: '۱$', toman: '۱۹۲,۵۴۲', change: 0, up: true },
      { sym: 'XRP', name: 'ریپل', color: '#23292f', usd: '۱.۰۸$', toman: '۲۰۷,۷۷۶', change: -0.2, up: false },
      { sym: 'SOL', name: 'سولانا', color: '#14f195', usd: '۷۴$', toman: '۱۴,۱۵۳,۱۱۳', change: 0.6, up: true },
      { sym: 'TRX', name: 'ترون', color: '#eb0029', usd: '۰.۳۳$', toman: '۶۳,۳۰۲', change: 0.6, up: true },
      { sym: 'HYPE', name: 'هایپر لیکویید', color: '#0b1f3a', usd: '۵۴$', toman: '۱۰,۳۹۰,۷۴۷', change: 4.8, up: true },
      { sym: 'DOGE', name: 'دوج کوین', color: '#c2a633', usd: '۰.۰۷$', toman: '۱۳,۵۱۵', change: -0.2, up: false },
    ],
  };
  COINS.new = COINS.popular.slice().reverse();
  COINS.gainers = COINS.popular.slice().sort(function (a, b) {
    return b.change - a.change;
  });
  COINS.stocks = COINS.popular.slice(0, 5);

  function renderTable(list) {
    var body = document.querySelector('[data-kp-table-body]');
    if (!body) return;
    body.innerHTML = list
      .map(function (c) {
        return (
          '<tr>' +
          '<td><div class="kp-coin"><span class="kp-coin-mark" style="background:' +
          c.color +
          '">' +
          c.sym.slice(0, 1) +
          '</span><div class="kp-coin-name"><strong>' +
          c.name +
          '</strong><small>' +
          c.sym +
          '</small></div></div></td>' +
          '<td>' +
          c.usd +
          '</td>' +
          '<td>' +
          c.toman +
          ' تومان</td>' +
          '<td><span class="kp-change ' +
          (c.up ? 'is-up' : 'is-down') +
          '">' +
          (c.up ? '▲' : '▼') +
          ' ' +
          Math.abs(c.change) +
          '٪</span></td>' +
          '<td><div class="kp-row-actions"><button type="button" class="kp-mini-btn">نمودار</button><button type="button" class="kp-mini-btn">قیمت</button><button type="button" class="kp-mini-btn is-primary">خرید و فروش</button></div></td>' +
          '</tr>'
        );
      })
      .join('');
  }

  function initTabs() {
    var tabs = document.querySelectorAll('[data-kp-tabs] .kp-tab');
    tabs.forEach(function (tab) {
      tab.addEventListener('click', function () {
        tabs.forEach(function (t) {
          t.classList.remove('is-active');
        });
        tab.classList.add('is-active');
        var key = tab.getAttribute('data-tab');
        renderTable(COINS[key] || COINS.popular);
      });
    });
    renderTable(COINS.popular);
  }

  function initAccordions() {
    document.querySelectorAll('[data-kp-accordion]').forEach(function (group) {
      group.querySelectorAll('.kp-faq-item').forEach(function (item) {
        var q = item.querySelector('.kp-faq-q');
        q.addEventListener('click', function () {
          var wasOpen = item.classList.contains('is-open');
          group.querySelectorAll('.kp-faq-item').forEach(function (el) {
            el.classList.remove('is-open');
          });
          if (!wasOpen) item.classList.add('is-open');
        });
      });
    });
  }

  function initHeaderNav() {
    var toggle = document.querySelector('[data-kp-nav-toggle]');
    var nav = document.querySelector('[data-kp-nav]');
    if (!toggle || !nav) return;
    toggle.addEventListener('click', function () {
      nav.classList.toggle('is-open');
    });
  }

  function initPromo() {
    var promo = document.querySelector('[data-kp-promo]');
    if (!promo) return;
    document.querySelectorAll('[data-kp-promo-close]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        promo.style.display = 'none';
      });
    });
  }

  function initTheme() {
    var btn = document.querySelector('[data-kp-theme]');
    if (!btn) return;
    var saved = localStorage.getItem('kp-theme');
    if (saved === 'light') {
      document.body.removeAttribute('data-theme');
    } else {
      document.body.setAttribute('data-theme', 'dark');
    }
    btn.addEventListener('click', function () {
      var isDark = document.body.getAttribute('data-theme') === 'dark';
      if (isDark) {
        document.body.removeAttribute('data-theme');
        localStorage.setItem('kp-theme', 'light');
      } else {
        document.body.setAttribute('data-theme', 'dark');
        localStorage.setItem('kp-theme', 'dark');
      }
    });
  }

  function initTestimonials() {
    var track = document.querySelector('[data-kp-testimonials]');
    if (!track) return;
    var prev = document.querySelector('[data-kp-test-prev]');
    var next = document.querySelector('[data-kp-test-next]');
    var step = function () {
      var card = track.querySelector('.kp-testimonial-card');
      return card ? card.getBoundingClientRect().width + 16 : 260;
    };
    if (next) {
      next.addEventListener('click', function () {
        track.scrollBy({ left: step(), behavior: 'smooth' });
      });
    }
    if (prev) {
      prev.addEventListener('click', function () {
        track.scrollBy({ left: -step(), behavior: 'smooth' });
      });
    }
  }

  function initSignup() {
    var form = document.querySelector('[data-kp-signup]');
    if (!form) return;
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var input = form.querySelector('input');
      if (input) {
        input.style.boxShadow = '0 0 0 2px rgba(41,82,227,.35)';
        setTimeout(function () {
          input.style.boxShadow = 'none';
        }, 800);
      }
    });
  }

  initTabs();
  initAccordions();
  initHeaderNav();
  initPromo();
  initTheme();
  initTestimonials();
  initSignup();
})();
