'use strict';

const store = require('../store');
const md = require('../markdown');
const jalali = require('../jalali');
const {
  e, faDigits, money, sectionOf, sectionLabel, kindLabel, navActive, asset, excerpt, VERSION,
} = require('../util');

function setting(key, fallback = '') {
  const s = store.find('settings', 'main') || {};
  return s[key] != null && s[key] !== '' ? s[key] : fallback;
}

function siteUrl() {
  return String(setting('site_url', '')).replace(/\/$/, '') || '';
}

function abs(path) {
  if (/^https?:/i.test(path)) return path;
  return `${siteUrl()}${path.startsWith('/') ? path : `/${path}`}`;
}

function csrfField(ctx) {
  return `<input type="hidden" name="_csrf" value="${e(ctx.csrf)}">`;
}

function flashHtml(ctx) {
  if (!ctx.flash) return '';
  return `<div class="wrap"><p class="flash flash--${e(ctx.flash.type)}">${e(ctx.flash.text)}</p></div>`;
}

function postCard(post, big = false) {
  const href = `/${sectionOf(post.type)}/${post.slug}`;
  return `<article class="card${big ? ' card--big' : ''}">
    <a href="${e(href)}">
      <div class="card__media">
        <img src="${e(post.cover || '/img/cover-news.svg')}" alt="" width="640" height="360" loading="lazy">
      </div>
      <div class="card__body">
        <span class="tag tag--${e(post.type)}">${e(sectionLabel(post.type))}</span>
        <h3>${e(post.title)}</h3>
        <p>${e(post.excerpt)}</p>
        <time datetime="${e(post.created_at || '')}">${e(jalali.format(post.created_at))}</time>
      </div>
    </a>
  </article>`;
}

function productCard(product) {
  const old = Number(product.compare_at) > Number(product.price)
    ? `<s>${e(money(product.compare_at))}</s>` : '';
  return `<article class="pcard">
    <a href="/product/${e(product.slug)}">
      <div class="pcard__media">
        <img src="${e(product.cover || '/img/product-physical.svg')}" alt="" width="480" height="320" loading="lazy">
        <span class="tag">${e(kindLabel(product.kind))}</span>
      </div>
      <div class="pcard__body">
        <h3>${e(product.title)}</h3>
        <p class="price"><b>${e(money(product.price))}</b> ${old}</p>
      </div>
    </a>
  </article>`;
}

function layout(ctx, seo, body) {
  const pages = store.where('pages', (p) => p.status === 'published' && p.in_nav);
  const jsonld = seo.jsonld ? `<script type="application/ld+json">${JSON.stringify(seo.jsonld)}</script>` : '';
  const navPages = pages.map((p) => `<a href="/p/${e(p.slug)}">${e(p.title)}</a>`).join('');
  return `<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>${e(seo.title)}</title>
  <meta name="description" content="${e(seo.description)}">
  <link rel="canonical" href="${e(seo.canonical)}">
  <meta property="og:title" content="${e(seo.title)}">
  <meta property="og:description" content="${e(seo.description)}">
  <meta property="og:type" content="${e(seo.og_type || 'website')}">
  <meta property="og:url" content="${e(seo.canonical)}">
  <meta property="og:image" content="${e(abs(seo.image || setting('og_image', '/img/og.svg')))}">
  <meta property="og:locale" content="fa_IR">
  <meta name="twitter:card" content="summary_large_image">
  <meta name="theme-color" content="#08070a">
  <link rel="icon" href="/img/favicon.svg" type="image/svg+xml">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;600;700;800&family=Oswald:wght@500;600;700&display=swap">
  <link rel="stylesheet" href="${e(asset('css/front.css'))}">
  ${jsonld}
</head>
<body>
  <div class="grain" aria-hidden="true"></div>
  <a class="skip" href="#main">رفتن به محتوا</a>
  <p class="tape">${e(setting('disclaimer'))}</p>
  <header class="top">
    <div class="wrap top__row">
      <a class="brand" href="/">
        <img src="/img/logo.svg" width="32" height="32" alt="">
        <span><strong>VISOTE</strong><small>gtavistore.ir</small></span>
      </a>
      <button type="button" class="nav-burger" aria-controls="site-nav" aria-expanded="false">منو</button>
      <nav class="nav" id="site-nav" aria-label="اصلی">
        <a class="${navActive(ctx.path, '/')}" href="/">خانه</a>
        <a class="${navActive(ctx.path, '/news')}" href="/news">اخبار</a>
        <a class="${navActive(ctx.path, '/rumors')}" href="/rumors">شایعه</a>
        <a class="${navActive(ctx.path, '/guide')}" href="/guide">راهنما</a>
        <a class="${navActive(ctx.path, '/shop')}" href="/shop">فروشگاه</a>
      </nav>
      <div class="top__tools">
        <form class="find" action="/search" method="get" role="search">
          <input type="search" name="q" placeholder="جستجو" value="${e(ctx.query.q || '')}" aria-label="جستجو">
        </form>
        <a class="bag" href="/cart">سبد<span>${e(faDigits(ctx.cartCount || 0))}</span></a>
      </div>
    </div>
  </header>
  <main id="main">${flashHtml(ctx)}${body}</main>
  <footer class="foot">
    <div class="wrap foot__grid">
      <div>
        <p class="brand brand--foot">VISOTE</p>
        <p>${e(setting('tagline'))}</p>
        <p class="muted">${e(setting('footer_note'))}</p>
      </div>
      <div>
        <h2>مسیرها</h2>
        <a href="/news">اخبار</a><a href="/rumors">شایعه</a><a href="/guide">راهنما</a>
        <a href="/shop">فروشگاه</a>
        ${navPages}
        <a href="/p/terms">قوانین</a>
      </div>
      <div>
        <h2>ارتباط</h2>
        <p>${e(setting('contact_phone'))}</p>
        <p>${e(setting('contact_email'))}</p>
        ${setting('instagram') ? `<a href="${e(setting('instagram'))}">اینستاگرام</a>` : ''}
        ${setting('telegram') ? `<a href="${e(setting('telegram'))}">تلگرام</a>` : ''}
      </div>
    </div>
    <p class="copy wrap">© ${e(faDigits(new Date().getFullYear()))} gtavistore.ir · ${e(VERSION)}</p>
  </footer>
  <script src="${e(asset('js/front.js'))}" defer></script>
  ${setting('analytics')}
</body>
</html>`;
}

function seoDefaults(over = {}) {
  return {
    title: setting('seo_title', setting('site_title', 'GTA VISOTE')),
    description: setting('seo_description', setting('tagline')),
    canonical: '',
    og_type: 'website',
    image: setting('og_image', '/img/og.svg'),
    jsonld: null,
    ...over,
  };
}

function publishedPosts() {
  return store.where('posts', (p) => p.status === 'published')
    .sort((a, b) => String(b.created_at).localeCompare(String(a.created_at)));
}

function publishedProducts() {
  return store.where('products', (p) => p.status === 'published');
}

module.exports = {
  setting,
  siteUrl,
  abs,
  csrfField,
  flashHtml,
  postCard,
  productCard,
  layout,
  seoDefaults,
  publishedPosts,
  publishedProducts,
  e,
  faDigits,
  money,
  sectionOf,
  sectionLabel,
  kindLabel,
  md,
  jalali,
  excerpt,
  store,
};
