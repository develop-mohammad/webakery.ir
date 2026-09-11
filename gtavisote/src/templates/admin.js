'use strict';

const { e, faDigits, money, jalali, csrfField, kindLabel, sectionLabel, store, setting } = require('./common');

function orderStatus(s) {
  return { pending: 'در انتظار', paid: 'پرداخت شد', shipped: 'ارسال شد', cancelled: 'لغو شد' }[s] || s;
}
const { asset, VERSION } = require('../util');

function shell(ctx, title, body) {
  const flash = ctx.flash ? `<p class="flash flash--${e(ctx.flash.type)}">${e(ctx.flash.text)}</p>` : '';
  const pending = store.where('orders', (o) => o.status === 'pending').length;
  const user = ctx.user ? e(ctx.user.username) : '';
  return `<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>${e(title)} | پنل وی‌سوت</title>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;700&display=swap">
  <link rel="stylesheet" href="${e(asset('css/admin.css'))}">
</head>
<body class="adm">
  <aside class="adm__nav">
    <a class="adm__brand" href="/admin">VISOTE <small>CMS ${e(VERSION)}</small></a>
    <a href="/admin">داشبورد</a>
    <a href="/admin/posts">نوشته‌ها</a>
    <a href="/admin/products">محصولات</a>
    <a href="/admin/orders">سفارش‌ها${pending ? ` <b>${e(faDigits(pending))}</b>` : ''}</a>
    <a href="/admin/pages">برگه‌ها</a>
    <a href="/admin/categories">دسته‌ها</a>
    <a href="/admin/media">رسانه</a>
    <a href="/admin/settings">تنظیمات</a>
    <a href="/" target="_blank" rel="noopener">مشاهده سایت</a>
    <a href="/admin/logout">خروج (${user})</a>
  </aside>
  <main class="adm__main">
    ${flash}
    ${body}
  </main>
  <script src="${e(asset('js/admin.js'))}" defer></script>
</body>
</html>`;
}

function login(error, csrf) {
  return `<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>ورود پنل | وی‌سوت</title>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;700&display=swap">
  <link rel="stylesheet" href="${e(asset('css/admin.css'))}">
</head>
<body class="adm-login">
  <form method="post" action="/admin/login" class="card-login">
    <p class="kicker">GTA VISOTE CMS</p>
    <h1>ورود به پنل</h1>
    ${error ? `<p class="flash flash--err">${e(error)}</p>` : ''}
    <input type="hidden" name="_csrf" value="${e(csrf)}">
    <label>نام کاربری <input name="username" autocomplete="username" required></label>
    <label>رمز <input name="password" type="password" autocomplete="current-password" required></label>
    <button type="submit">ورود</button>
  </form>
</body>
</html>`;
}

function dashboard(ctx) {
  const orders = store.all('orders').slice().sort((a, b) => String(b.created_at).localeCompare(String(a.created_at))).slice(0, 6);
  const body = `
    <h1>داشبورد</h1>
    <p class="lede">مثل وردپرس است، فقط شلوغ نیست: نوشته، محصول، سفارش.</p>
    <div class="stats">
      <article><b>${e(faDigits(store.all('posts').length))}</b><span>نوشته</span></article>
      <article><b>${e(faDigits(store.all('products').length))}</b><span>محصول</span></article>
      <article><b>${e(faDigits(store.all('orders').length))}</b><span>سفارش</span></article>
      <article><b>${e(faDigits(store.all('pages').length))}</b><span>برگه</span></article>
    </div>
    <h2>سفارش‌های اخیر</h2>
    <table class="table">
      <thead><tr><th>کد</th><th>خریدار</th><th>مبلغ</th><th>وضعیت</th></tr></thead>
      <tbody>${orders.map((o) => `<tr><td><a href="/admin/orders/${e(o.id)}">${e(o.code)}</a></td><td>${e(o.name)}</td><td>${e(money(o.total))}</td><td>${e(orderStatus(o.status))}</td></tr>`).join('') || '<tr><td colspan="4">سفارشی نیست.</td></tr>'}</tbody>
    </table>`;
  return shell(ctx, 'داشبورد', body);
}

function list(ctx, heading, newUrl, kind, rows) {
  const add = newUrl ? `<a class="btn" href="${e(newUrl)}">افزودن</a>` : '';
  let inner;
  if (kind === 'orders') {
    inner = rows.map((o) => `<tr>
      <td><a href="/admin/orders/${e(o.id)}">${e(o.code)}</a></td>
      <td>${e(o.name)}</td>
      <td>${e(money(o.total))}</td>
      <td>${e(orderStatus(o.status))}</td>
      <td>${e(jalali.format(o.created_at))}</td>
    </tr>`).join('');
  } else {
    inner = rows.map((r) => `<tr>
      <td><a href="/admin/${kind}/${e(r.id)}">${e(r.title)}</a></td>
      <td>${e(r.status || '')}${r.type ? ` · ${e(sectionLabel(r.type) === r.type ? kindLabel(r.kind || r.type) : sectionLabel(r.type))}` : ''}</td>
      <td>${e(jalali.format(r.updated_at))}</td>
      <td>
        <form method="post" action="/admin/${kind}/delete" onsubmit="return confirm('حذف شود؟')">
          ${csrfField(ctx)}<input type="hidden" name="id" value="${e(r.id)}">
          <button class="linkish" type="submit">حذف</button>
        </form>
      </td>
    </tr>`).join('');
  }
  const head = kind === 'orders'
    ? '<th>کد</th><th>خریدار</th><th>مبلغ</th><th>وضعیت</th><th>تاریخ</th>'
    : '<th>عنوان</th><th>وضعیت</th><th>به‌روزرسانی</th><th></th>';
  const body = `<div class="row-head"><h1>${e(heading)}</h1>${add}</div>
    <table class="table"><thead><tr>${head}</tr></thead><tbody>${inner || '<tr><td colspan="4">خالی است.</td></tr>'}</tbody></table>`;
  return shell(ctx, heading, body);
}

function postForm(ctx, item, cats) {
  const options = cats.map((cat) => `<option value="${e(cat.id)}" ${item.category_id === cat.id ? 'selected' : ''}>${e(cat.title)}</option>`).join('');
  const body = `
    <h1>${item.id ? 'ویرایش نوشته' : 'نوشته جدید'}</h1>
    <form method="post" action="/admin/posts/save" class="form">
      ${csrfField(ctx)}
      <input type="hidden" name="id" value="${e(item.id || '')}">
      <label>عنوان <input name="title" value="${e(item.title || '')}" required></label>
      <label>نامک <input name="slug" value="${e(item.slug || '')}" placeholder="خودکار از عنوان"></label>
      <div class="split">
        <label>نوع
          <select name="type">
            ${['news', 'rumor', 'guide'].map((t) => `<option value="${t}" ${item.type === t ? 'selected' : ''}>${e(sectionLabel(t))}</option>`).join('')}
          </select>
        </label>
        <label>دسته <select name="category_id"><option value="">—</option>${options}</select></label>
        <label>وضعیت
          <select name="status">
            <option value="published" ${item.status === 'published' ? 'selected' : ''}>منتشر</option>
            <option value="draft" ${item.status === 'draft' ? 'selected' : ''}>پیش‌نویس</option>
          </select>
        </label>
      </div>
      <label class="inline"><input type="checkbox" name="featured" ${item.featured ? 'checked' : ''}> ویژه در خانه</label>
      <label>تصویر (مسیر مثل /img/cover-news.svg) <input name="cover" value="${e(item.cover || '')}"></label>
      <label>خلاصه <textarea name="excerpt" rows="3">${e(item.excerpt || '')}</textarea></label>
      <label>متن (Markdown ساده) <textarea name="body" rows="14">${e(item.body || '')}</textarea></label>
      <details><summary>سئو</summary>
        <label>عنوان سئو <input name="seo_title" value="${e(item.seo_title || '')}"></label>
        <label>توضیح متا <textarea name="seo_description" rows="2">${e(item.seo_description || '')}</textarea></label>
      </details>
      <button class="btn" type="submit">ذخیره</button>
    </form>`;
  return shell(ctx, item.title || 'نوشته', body);
}

function productForm(ctx, item, cats) {
  const kinds = ['physical', 'digital', 'bundle', 'edition', 'merch', 'accessory', 'addon'];
  const options = cats.map((cat) => `<option value="${e(cat.id)}" ${item.category_id === cat.id ? 'selected' : ''}>${e(cat.title)}</option>`).join('');
  const body = `
    <h1>${item.id ? 'ویرایش محصول' : 'محصول جدید'}</h1>
    <form method="post" action="/admin/products/save" class="form">
      ${csrfField(ctx)}
      <input type="hidden" name="id" value="${e(item.id || '')}">
      <label>نام <input name="title" value="${e(item.title || '')}" required></label>
      <label>نامک <input name="slug" value="${e(item.slug || '')}"></label>
      <div class="split">
        <label>SKU <input name="sku" value="${e(item.sku || '')}"></label>
        <label>نوع <select name="kind">${kinds.map((k) => `<option value="${k}" ${item.kind === k ? 'selected' : ''}>${e(kindLabel(k))}</option>`).join('')}</select></label>
        <label>دسته <select name="category_id"><option value="">—</option>${options}</select></label>
      </div>
      <div class="split">
        <label>قیمت (تومان) <input name="price" type="number" value="${e(item.price || 0)}"></label>
        <label>قیمت قبلی <input name="compare_at" type="number" value="${e(item.compare_at || 0)}"></label>
        <label>موجودی <input name="stock" type="number" value="${e(item.stock || 0)}"></label>
      </div>
      <label>وضعیت <select name="status"><option value="published" ${item.status === 'published' ? 'selected' : ''}>منتشر</option><option value="draft" ${item.status !== 'published' ? 'selected' : ''}>پیش‌نویس</option></select></label>
      <label class="inline"><input type="checkbox" name="in_stock" ${item.in_stock ? 'checked' : ''}> قابل سفارش</label>
      <label class="inline"><input type="checkbox" name="featured" ${item.featured ? 'checked' : ''}> ویژه</label>
      <label>تصویر <input name="cover" value="${e(item.cover || '')}"></label>
      <label>خلاصه <textarea name="excerpt" rows="2">${e(item.excerpt || '')}</textarea></label>
      <label>توضیح <textarea name="body" rows="8">${e(item.body || '')}</textarea></label>
      <details><summary>سئو</summary>
        <label>عنوان سئو <input name="seo_title" value="${e(item.seo_title || '')}"></label>
        <label>متا <textarea name="seo_description" rows="2">${e(item.seo_description || '')}</textarea></label>
      </details>
      <button class="btn" type="submit">ذخیره</button>
    </form>`;
  return shell(ctx, item.title || 'محصول', body);
}

function pageForm(ctx, item) {
  const body = `
    <h1>${item.id ? 'ویرایش برگه' : 'برگه جدید'}</h1>
    <form method="post" action="/admin/pages/save" class="form">
      ${csrfField(ctx)}
      <input type="hidden" name="id" value="${e(item.id || '')}">
      <label>عنوان <input name="title" value="${e(item.title || '')}" required></label>
      <label>نامک <input name="slug" value="${e(item.slug || '')}"></label>
      <label>وضعیت <select name="status"><option value="published" ${item.status === 'published' ? 'selected' : ''}>منتشر</option><option value="draft" ${item.status !== 'published' ? 'selected' : ''}>پیش‌نویس</option></select></label>
      <label class="inline"><input type="checkbox" name="in_nav" ${item.in_nav ? 'checked' : ''}> نمایش در منو</label>
      <label>متن <textarea name="body" rows="12">${e(item.body || '')}</textarea></label>
      <label>عنوان سئو <input name="seo_title" value="${e(item.seo_title || '')}"></label>
      <label>متا <textarea name="seo_description" rows="2">${e(item.seo_description || '')}</textarea></label>
      <button class="btn" type="submit">ذخیره</button>
    </form>`;
  return shell(ctx, item.title || 'برگه', body);
}

function orderView(ctx, item) {
  const lis = (item.items || []).map((it) => `<li>${e(it.title)} × ${e(faDigits(it.qty))} — ${e(money(it.price * it.qty))}</li>`).join('');
  const body = `
    <h1>سفارش ${e(item.code)}</h1>
    <p>${e(item.name)} · ${e(item.phone)} · ${e(item.city)}</p>
    <p>${e(item.address)}</p>
    <p>پرداخت: ${e(item.pay)} · یادداشت: ${e(item.note || '—')}</p>
    <ul>${lis}</ul>
    <p>ارسال ${e(money(item.shipping))} · جمع <b>${e(money(item.total))}</b></p>
    <form method="post" action="/admin/orders/save" class="form">
      ${csrfField(ctx)}
      <input type="hidden" name="id" value="${e(item.id)}">
      <label>وضعیت
        <select name="status">
          ${['pending', 'paid', 'shipped', 'cancelled'].map((s) => `<option value="${s}" ${item.status === s ? 'selected' : ''}>${e(orderStatus(s))}</option>`).join('')}
        </select>
      </label>
      <label>یادداشت ادمین <textarea name="admin_note" rows="3">${e(item.admin_note || '')}</textarea></label>
      <button class="btn" type="submit">ذخیره</button>
    </form>`;
  return shell(ctx, item.code, body);
}

function categories(ctx, items) {
  const rows = items.map((c) => `<tr>
    <td>${e(c.title)}</td><td>${e(c.kind)}</td><td>${e(c.slug)}</td>
    <td><form method="post" action="/admin/categories/delete">${csrfField(ctx)}<input type="hidden" name="id" value="${e(c.id)}"><button class="linkish">حذف</button></form></td>
  </tr>`).join('');
  const body = `
    <h1>دسته‌ها</h1>
    <form method="post" action="/admin/categories/save" class="form form--inline">
      ${csrfField(ctx)}
      <input name="title" placeholder="عنوان" required>
      <input name="slug" placeholder="نامک">
      <select name="kind"><option value="post">نوشته</option><option value="product">محصول</option></select>
      <input name="type" placeholder="news / rumor / shop" value="news">
      <button class="btn" type="submit">افزودن</button>
    </form>
    <table class="table"><thead><tr><th>عنوان</th><th>گروه</th><th>نامک</th><th></th></tr></thead><tbody>${rows}</tbody></table>`;
  return shell(ctx, 'دسته‌ها', body);
}

function media(ctx, items) {
  const cards = items.map((m) => `<figure>
    <img src="${e(m.url)}" alt="">
    <figcaption>${e(m.url)}</figcaption>
    <form method="post" action="/admin/media/delete">${csrfField(ctx)}<input type="hidden" name="id" value="${e(m.id)}"><button class="linkish">حذف</button></form>
  </figure>`).join('');
  const body = `
    <h1>رسانه</h1>
    <form method="post" action="/admin/media/upload" enctype="multipart/form-data" class="form">
      ${csrfField(ctx)}
      <input type="file" name="file" accept="image/*" required>
      <button class="btn" type="submit">آپلود</button>
    </form>
    <div class="media-grid">${cards || '<p>فایلی نیست. می‌توانید از تصاویر آماده /img استفاده کنید.</p>'}</div>`;
  return shell(ctx, 'رسانه', body);
}

function settings(ctx, s) {
  const body = `
    <h1>تنظیمات</h1>
    <form method="post" action="/admin/settings" class="form">
      ${csrfField(ctx)}
      <label>عنوان سایت <input name="site_title" value="${e(s.site_title || '')}"></label>
      <label>شعار <input name="tagline" value="${e(s.tagline || '')}"></label>
      <label>آدرس سایت <input name="site_url" value="${e(s.site_url || '')}"></label>
      <label>عنوان سئو <input name="seo_title" value="${e(s.seo_title || '')}"></label>
      <label>توضیح سئو <textarea name="seo_description" rows="3">${e(s.seo_description || '')}</textarea></label>
      <label>تلفن <input name="contact_phone" value="${e(s.contact_phone || '')}"></label>
      <label>ایمیل <input name="contact_email" value="${e(s.contact_email || '')}"></label>
      <label>شماره کارت <input name="card_number" value="${e(s.card_number || '')}"></label>
      <label>صاحب کارت <input name="card_name" value="${e(s.card_name || '')}"></label>
      <label>هزینه ارسال <input name="shipping_cost" type="number" value="${e(s.shipping_cost || 0)}"></label>
      <label class="inline"><input type="checkbox" name="card_enabled" ${s.card_enabled ? 'checked' : ''}> کارت‌به‌کارت</label>
      <label class="inline"><input type="checkbox" name="cod_enabled" ${s.cod_enabled ? 'checked' : ''}> پرداخت در محل</label>
      <label>اینستاگرام <input name="instagram" value="${e(s.instagram || '')}"></label>
      <label>تلگرام <input name="telegram" value="${e(s.telegram || '')}"></label>
      <label>تصویر OG <input name="og_image" value="${e(s.og_image || '')}"></label>
      <label>سلب مسئولیت <textarea name="disclaimer" rows="3">${e(s.disclaimer || '')}</textarea></label>
      <label>پاورقی <textarea name="footer_note" rows="2">${e(s.footer_note || '')}</textarea></label>
      <label>کد آمار (اختیاری) <textarea name="analytics" rows="3">${e(s.analytics || '')}</textarea></label>
      <label>رمز جدید ادمین (خالی = بدون تغییر) <input name="new_password" type="password"></label>
      <button class="btn" type="submit">ذخیره تنظیمات</button>
    </form>`;
  return shell(ctx, 'تنظیمات', body);
}

module.exports = {
  login, dashboard, list, postForm, productForm, pageForm, orderView, categories, media, settings,
};
