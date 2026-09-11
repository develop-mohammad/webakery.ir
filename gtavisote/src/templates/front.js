'use strict';

const c = require('./common');

function home(ctx) {
  const posts = c.publishedPosts();
  const featured = posts.filter((p) => p.featured).slice(0, 3);
  const news = posts.filter((p) => p.type === 'news');
  const rumors = posts.filter((p) => p.type === 'rumor');
  const guides = posts.filter((p) => p.type === 'guide');
  const products = c.publishedProducts().filter((p) => p.featured).slice(0, 6);
  const ticker = rumors.slice(0, 5).map((t) => `<a href="/rumors/${c.e(t.slug)}">${c.e(t.title)}</a>`).join('');
  const feat = (featured.length ? featured : news.slice(0, 3)).map((p, i) => c.postCard(p, i === 0)).join('');
  const body = `
<section class="hero">
  <div class="hero__bg" aria-hidden="true"></div>
  <p class="hero__mark" aria-hidden="true">VI</p>
  <div class="wrap hero__inner">
    <p class="kicker">غیررسمی · فارسی · تا لانچ</p>
    <h1>GTA VI را از شایعه جدا بخوان؛<br>از فروشگاه بخر.</h1>
    <p class="lede">اخبار رسمی، راهنمای لئونیدا، و فروش نسخه فیزیکی، دیجیتال، باندل کنسول و کالکشن. شایعه این‌جا قاطی خبر نمی‌شود.</p>
    <ul class="hero__meta">
      <li>PlayStation 5</li>
      <li>Xbox Series X|S</li>
      <li>۱۹ نوامبر ۲۰۲۶</li>
    </ul>
    <div class="count" data-launch="2026-11-19T00:00:00+03:30" aria-label="شمارش معکوس">
      <div><b id="d">—</b><span>روز</span></div>
      <div><b id="h">—</b><span>ساعت</span></div>
      <div><b id="m">—</b><span>دقیقه</span></div>
      <div><b id="s">—</b><span>ثانیه</span></div>
    </div>
    <div class="hero__cta">
      <a class="btn btn--pink" href="/shop">فروشگاه لانچ</a>
      <a class="btn btn--ghost" href="/news">تازه‌ترین خبرها</a>
    </div>
  </div>
  <div class="hero__sky" aria-hidden="true"></div>
</section>
${rumors.length ? `<div class="ticker" aria-label="شایعه‌های اخیر"><span>شایعه</span><div class="ticker__track"><div>${ticker}</div><div>${ticker}</div></div></div>` : ''}
<section class="wrap block">
  <header class="block__head"><h2>پوشش ویژه</h2><a href="/news">همه اخبار</a></header>
  <div class="feat">${feat}</div>
</section>
<section class="wrap block">
  <header class="block__head"><h2>فروشگاه لانچ</h2><a href="/shop">همه محصولات</a></header>
  <div class="grid grid--3">${products.map(c.productCard).join('')}</div>
</section>
<section class="wrap split">
  <div>
    <header class="block__head"><h2>شایعه جدا</h2><a href="/rumors">بیشتر</a></header>
    <ul class="stack">${rumors.map((p) => `<li><a href="/rumors/${c.e(p.slug)}"><em>شایعه</em><strong>${c.e(p.title)}</strong><span>${c.e(c.jalali.format(p.created_at))}</span></a></li>`).join('')}</ul>
  </div>
  <div>
    <header class="block__head"><h2>راهنمای بازی</h2><a href="/guide">بیشتر</a></header>
    <div class="grid grid--1">${guides.map((p) => c.postCard(p)).join('')}</div>
  </div>
</section>`;
  return c.layout(ctx, c.seoDefaults({
    canonical: c.abs('/'),
    jsonld: {
      '@context': 'https://schema.org',
      '@type': 'WebSite',
      name: c.setting('site_title'),
      url: c.siteUrl() || undefined,
      potentialAction: { '@type': 'SearchAction', target: `${c.abs('/search')}?q={q}`, 'query-input': 'required name=q' },
    },
  }), body);
}

function archive(ctx, type, title, lead) {
  const items = c.publishedPosts().filter((p) => p.type === type);
  const section = c.sectionOf(type);
  const body = `
<section class="wrap pagehead">
  <p class="kicker">${type === 'rumor' ? 'برچسب‌خورده · تأییدنشده' : 'وی‌سوت'}</p>
  <h1>${c.e(title)}</h1>
  <p class="lede">${c.e(lead)}</p>
</section>
<section class="wrap grid grid--2 pad-b">
  ${items.map((p) => c.postCard(p)).join('') || '<p class="muted">موردی نیست.</p>'}
</section>`;
  return c.layout(ctx, c.seoDefaults({
    title: `${title} | وی‌سوت`,
    description: lead,
    canonical: c.abs(`/${section}`),
  }), body);
}

function single(ctx, post) {
  const section = c.sectionOf(post.type);
  const related = c.publishedPosts().filter((p) => p.id !== post.id && p.type === post.type).slice(0, 3);
  const warn = post.type === 'rumor' ? '<p class="warn">این مطلب شایعه است، نه خبر رسمی راک‌استار.</p>' : '';
  const body = `
<article class="wrap article">
  <header class="pagehead">
    <p class="kicker"><a href="/${section}">${c.e(c.sectionLabel(post.type))}</a> · ${c.e(c.jalali.format(post.created_at))}</p>
    <h1>${c.e(post.title)}</h1>
    <p class="lede">${c.e(post.excerpt)}</p>
  </header>
  ${post.cover ? `<img class="heroimg" src="${c.e(post.cover)}" alt="">` : ''}
  ${warn}
  <div class="prose">${c.md.toHtml(post.body)}</div>
</article>
${related.length ? `<section class="wrap block"><header class="block__head"><h2>مرتبط</h2></header><div class="grid grid--3">${related.map((p) => c.postCard(p)).join('')}</div></section>` : ''}`;
  return c.layout(ctx, c.seoDefaults({
    title: post.seo_title || `${post.title} | ${c.setting('site_title', 'VISOTE')}`,
    description: post.seo_description || post.excerpt,
    canonical: c.abs(`/${section}/${post.slug}`),
    og_type: 'article',
    image: post.cover,
    jsonld: {
      '@context': 'https://schema.org',
      '@type': post.type === 'guide' ? 'Article' : 'NewsArticle',
      headline: post.title,
      datePublished: post.created_at,
      dateModified: post.updated_at,
      inLanguage: 'fa-IR',
      mainEntityOfPage: c.abs(`/${section}/${post.slug}`),
    },
  }), body);
}

function shop(ctx, current) {
  let products = c.publishedProducts();
  let title = 'فروشگاه وی‌سوت';
  if (current) {
    products = products.filter((p) => p.category_id === current.id);
    title = current.title;
  }
  const cats = c.store.where('categories', (x) => x.kind === 'product');
  const pills = [`<a class="${current ? '' : 'is-active'}" href="/shop">همه</a>`]
    .concat(cats.map((cat) => `<a class="${current && current.id === cat.id ? 'is-active' : ''}" href="/shop/${c.e(cat.slug)}">${c.e(cat.title)}</a>`))
    .join('');
  const body = `
<section class="wrap pagehead">
  <p class="kicker">فروشگاه لانچ</p>
  <h1>${c.e(title)}</h1>
  <p class="lede">فیزیکی، دیجیتال، باندل کنسول، نسخه ویژه، کالکشن و پک قانونی. کرک و بیلد لو رفته نداریم.</p>
  <nav class="pills" aria-label="دسته‌بندی فروشگاه">${pills}</nav>
</section>
<section class="wrap grid grid--3 pad-b">
  ${products.map(c.productCard).join('') || '<p class="muted">در این دسته محصولی نیست.</p>'}
</section>`;
  return c.layout(ctx, c.seoDefaults({
    title: `${title} | GTA VISOTE`,
    description: 'نسخه فیزیکی و دیجیتال، باندل کنسول، نسخه ویژه، کالکشن و لوازم جانبی مرتبط با GTA VI.',
    canonical: c.abs(current ? `/shop/${current.slug}` : '/shop'),
  }), body);
}

function product(ctx, item) {
  const more = c.publishedProducts().filter((p) => p.id !== item.id && p.kind === item.kind).slice(0, 4);
  const old = Number(item.compare_at) > Number(item.price) ? `<s>${c.e(c.money(item.compare_at))}</s>` : '';
  const body = `
<section class="wrap product">
  <div class="product__media"><img src="${c.e(item.cover || '/img/product-physical.svg')}" alt="${c.e(item.title)}"></div>
  <div>
    <p class="kicker">${c.e(c.kindLabel(item.kind))} · ${c.e(item.sku || '')}</p>
    <h1>${c.e(item.title)}</h1>
    <p class="lede">${c.e(item.excerpt)}</p>
    <p class="price price--lg"><b>${c.e(c.money(item.price))}</b> ${old}</p>
    <p class="muted">موجودی: ${c.e(c.faDigits(item.stock || 0))}</p>
    <form method="post" action="/cart/add" class="buy">
      ${c.csrfField(ctx)}
      <input type="hidden" name="product_id" value="${c.e(item.id)}">
      <label>تعداد <input type="number" name="qty" value="1" min="1" max="9"></label>
      <button class="btn btn--pink" type="submit">افزودن به سبد</button>
    </form>
    <div class="prose">${c.md.toHtml(item.body)}</div>
  </div>
</section>
${more.length ? `<section class="wrap block"><header class="block__head"><h2>مشابه</h2></header><div class="grid grid--3">${more.map(c.productCard).join('')}</div></section>` : ''}`;
  return c.layout(ctx, c.seoDefaults({
    title: item.seo_title || `${item.title} | فروشگاه وی‌سوت`,
    description: item.seo_description || item.excerpt,
    canonical: c.abs(`/product/${item.slug}`),
    og_type: 'product',
    image: item.cover,
    jsonld: {
      '@context': 'https://schema.org',
      '@type': 'Product',
      name: item.title,
      description: item.excerpt,
      image: c.abs(item.cover || '/img/og.svg'),
      sku: item.sku || item.id,
      offers: {
        '@type': 'Offer',
        priceCurrency: 'IRR',
        price: (Number(item.price) || 0) * 10,
        availability: item.in_stock ? 'https://schema.org/InStock' : 'https://schema.org/PreOrder',
        url: c.abs(`/product/${item.slug}`),
      },
    },
  }), body);
}

function cart(ctx, lines, total, shipping) {
  const rows = lines.map((line) => {
    const p = line.product;
    return `<tr>
      <td><a href="/product/${c.e(p.slug)}">${c.e(p.title)}</a></td>
      <td><form method="post" action="/cart/update">${c.csrfField(ctx)}<input type="hidden" name="product_id" value="${c.e(p.id)}"><input type="number" name="qty" value="${c.e(line.qty)}" min="1" max="9" onchange="this.form.submit()"></form></td>
      <td>${c.e(c.money(line.line))}</td>
      <td><form method="post" action="/cart/remove">${c.csrfField(ctx)}<input type="hidden" name="product_id" value="${c.e(p.id)}"><button class="linkish" type="submit">حذف</button></form></td>
    </tr>`;
  }).join('');
  const body = `
<section class="wrap pagehead"><h1>سبد خرید</h1></section>
<section class="wrap pad-b">${
    lines.length
      ? `<table class="table"><thead><tr><th>کالا</th><th>تعداد</th><th>جمع</th><th></th></tr></thead><tbody>${rows}</tbody></table>
         <p>جمع کالا: <b>${c.e(c.money(total))}</b></p>
         <p class="muted">هزینه ارسال در تسویه برای کالاهای فیزیکی محاسبه می‌شود (${c.e(c.money(shipping))}).</p>
         <a class="btn btn--pink" href="/checkout">ادامه تسویه</a>`
      : '<p class="muted">سبد خالی است. <a href="/shop">برو به فروشگاه</a></p>'
  }</section>`;
  return c.layout(ctx, c.seoDefaults({ title: 'سبد خرید | وی‌سوت', canonical: c.abs('/cart') }), body);
}

function checkout(ctx, lines, total, shipping) {
  const list = lines.map((l) => `<li>${c.e(l.product.title)} × ${c.e(c.faDigits(l.qty))}</li>`).join('');
  const body = `
<section class="wrap pagehead">
  <h1>تسویه سفارش</h1>
  <p class="lede">پرداخت آنلاین در نسخه بعد وصل می‌شود. الان کارت‌به‌کارت یا پرداخت در محل.</p>
</section>
<section class="wrap checkout pad-b">
  <form method="post" class="form" action="/checkout">
    ${c.csrfField(ctx)}
    <label>نام و نام خانوادگی <input name="name" required></label>
    <label>موبایل <input name="phone" required inputmode="tel" placeholder="0912…"></label>
    <label>شهر <input name="city" required></label>
    <label>نشانی <textarea name="address" rows="3" required></textarea></label>
    <fieldset>
      <legend>روش پرداخت</legend>
      ${c.setting('card_enabled', 1) ? '<label class="inline"><input type="radio" name="pay" value="card" checked> کارت‌به‌کارت</label>' : ''}
      ${c.setting('cod_enabled', 1) ? '<label class="inline"><input type="radio" name="pay" value="cod"> پرداخت در محل (فیزیکی)</label>' : ''}
    </fieldset>
    <label>یادداشت (پلتفرم، رنگ کنسول، ریجن) <textarea name="note" rows="2"></textarea></label>
    <button class="btn btn--pink" type="submit">ثبت سفارش</button>
  </form>
  <aside class="sum">
    <h2>خلاصه</h2>
    <ul>${list}</ul>
    <p>کالا: ${c.e(c.money(total))}</p>
    <p>ارسال تخمینی: ${c.e(c.money(shipping))}</p>
    <p><b>قابل پرداخت حدودی: ${c.e(c.money(total + shipping))}</b></p>
  </aside>
</section>`;
  return c.layout(ctx, c.seoDefaults({ title: 'تسویه سفارش | وی‌سوت', canonical: c.abs('/checkout') }), body);
}

function order(ctx, item) {
  const lis = (item.items || []).map((it) => `<li>${c.e(it.title)} × ${c.e(c.faDigits(it.qty))}</li>`).join('');
  const body = `
<section class="wrap pagehead">
  <p class="kicker">رسید</p>
  <h1>سفارش ${c.e(item.code)} ثبت شد</h1>
  <p class="lede">وضعیت: در انتظار تأیید. با ${c.e(item.phone)} تماس می‌گیریم.</p>
</section>
<section class="wrap pad-b prose">
  <p>اگر کارت‌به‌کارت انتخاب کردید:</p>
  <p><strong>${c.e(c.setting('card_number'))}</strong><br>${c.e(c.setting('card_name'))}</p>
  <p>مبلغ: <b>${c.e(c.money(item.total))}</b> — کد سفارش را در واریز بنویسید.</p>
  <ul>${lis}</ul>
  <p><a class="btn btn--ghost" href="/">بازگشت به خانه</a></p>
</section>`;
  return c.layout(ctx, c.seoDefaults({ title: `رسید سفارش ${item.code}`, canonical: c.abs(`/order/${item.code}`) }), body);
}

function search(ctx, q, posts, products) {
  const body = `
<section class="wrap pagehead">
  <h1>جستجو</h1>
  <form class="find find--lg" action="/search" method="get">
    <input type="search" name="q" value="${c.e(q)}" placeholder="مثلاً Ultimate یا لوسیا">
    <button class="btn btn--pink" type="submit">بگرد</button>
  </form>
</section>
${q ? `<section class="wrap pad-b">
  <h2>نوشته‌ها</h2>
  <div class="grid grid--2">${posts.map((p) => c.postCard(p)).join('') || '<p class="muted">نوشته‌ای نبود.</p>'}</div>
  <h2>محصولات</h2>
  <div class="grid grid--3">${products.map(c.productCard).join('') || '<p class="muted">محصولی نبود.</p>'}</div>
</section>` : ''}`;
  return c.layout(ctx, c.seoDefaults({ title: 'جستجو | وی‌سوت', canonical: c.abs('/search') }), body);
}

function cmsPage(ctx, page) {
  const body = `<article class="wrap article"><header class="pagehead"><h1>${c.e(page.title)}</h1></header><div class="prose">${c.md.toHtml(page.body)}</div></article>`;
  return c.layout(ctx, c.seoDefaults({
    title: page.seo_title || `${page.title} | وی‌سوت`,
    description: page.seo_description || c.excerpt(page.body),
    canonical: c.abs(`/p/${page.slug}`),
  }), body);
}

function notFound(ctx) {
  const body = `
<section class="wrap pagehead">
  <h1>این صفحه در لئونیدا پیدا نشد</h1>
  <p class="lede">آدرس را چک کنید یا از جستجو استفاده کنید.</p>
  <p><a class="btn btn--pink" href="/">خانه</a> <a class="btn btn--ghost" href="/shop">فروشگاه</a></p>
</section>`;
  return c.layout(ctx, c.seoDefaults({ title: 'صفحه پیدا نشد | وی‌سوت' }), body);
}

function install(error, siteUrl, csrf) {
  return `<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>نصب وی‌سوت</title>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;600;800&display=swap">
  <link rel="stylesheet" href="/css/front.css">
</head>
<body class="install-body">
  <main class="install">
    <p class="kicker">GTA VISOTE · بدون PHP · بدون وردپرس</p>
    <h1>نصب CMS وی‌سوت</h1>
    <p>فقط Node.js. بعد از این فرم، پنل مدیریت آماده است — ساده‌تر از وردپرس.</p>
    ${error ? `<p class="flash flash--err">${c.e(error)}</p>` : ''}
    <form method="post" class="form" action="/install">
      <input type="hidden" name="_csrf" value="${c.e(csrf)}">
      <label>آدرس سایت <input name="site_url" value="${c.e(siteUrl)}" required></label>
      <label>ایمیل ادمین <input name="email" type="email" placeholder="admin@gtavisote.ir"></label>
      <label>نام کاربری <input name="username" value="admin" required></label>
      <label>رمز (حداقل ۸) <input name="password" type="password" required></label>
      <label>تکرار رمز <input name="password2" type="password" required></label>
      <button class="btn btn--pink" type="submit">نصب و ورود به پنل</button>
    </form>
  </main>
</body>
</html>`;
}

module.exports = {
  home, archive, single, shop, product, cart, checkout, order, search, cmsPage, notFound, install,
};
