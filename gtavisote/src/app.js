'use strict';

const fs = require('fs');
const http = require('http');
const path = require('path');
const crypto = require('crypto');
const { URL } = require('url');
const store = require('./store');
const seed = require('./seed');
const frontT = require('./templates/front');
const adminT = require('./templates/admin');
const {
  ROOT, VERSION, slug, uid, phone, parseCookies, verifyPassword, hashPassword, sectionOf,
} = require('./util');
const { publishedPosts, publishedProducts, setting } = require('./templates/common');

const MIME = {
  '.css': 'text/css; charset=utf-8',
  '.js': 'text/javascript; charset=utf-8',
  '.svg': 'image/svg+xml',
  '.png': 'image/png',
  '.jpg': 'image/jpeg',
  '.jpeg': 'image/jpeg',
  '.webp': 'image/webp',
  '.gif': 'image/gif',
  '.ico': 'image/x-icon',
  '.woff2': 'font/woff2',
  '.txt': 'text/plain; charset=utf-8',
  '.xml': 'application/xml; charset=utf-8',
  '.json': 'application/json; charset=utf-8',
};

const sessions = new Map();
const loginHits = new Map();
const PUBLIC = path.join(ROOT, 'public');
const LOCK = path.join(ROOT, 'data', 'installed.lock');

function installed() {
  return fs.existsSync(LOCK);
}

function sidFrom(req) {
  return parseCookies(req.headers.cookie).gvs || '';
}

function session(req) {
  let id = sidFrom(req);
  if (!id || !sessions.has(id)) {
    id = crypto.randomBytes(16).toString('hex');
    sessions.set(id, { id, cart: [], csrf: crypto.randomBytes(16).toString('hex'), flash: null, userId: null });
  }
  return sessions.get(id);
}

function setCookie(res, sess) {
  const parts = [`gvs=${sess.id}`, 'Path=/', 'HttpOnly', 'SameSite=Lax', 'Max-Age=1209600'];
  res.setHeader('Set-Cookie', parts.join('; '));
}

function ctxOf(req, sess) {
  const url = new URL(req.url, 'http://local');
  return {
    req,
    path: url.pathname.replace(/\/+$/, '') || '/',
    query: Object.fromEntries(url.searchParams),
    csrf: sess.csrf,
    flash: sess.flash,
    user: sess.userId ? store.find('users', sess.userId) : null,
    cartCount: (sess.cart || []).reduce((n, r) => n + Number(r.qty || 0), 0),
    sess,
  };
}

function takeFlash(sess) {
  const f = sess.flash;
  sess.flash = null;
  return f;
}

function html(res, sess, code, body) {
  setCookie(res, sess);
  res.writeHead(code, { 'Content-Type': 'text/html; charset=utf-8', 'Cache-Control': 'no-store' });
  res.end(body);
}

function redirect(res, sess, location, code = 302) {
  setCookie(res, sess);
  res.writeHead(code, { Location: location });
  res.end();
}

function text(res, code, body, type = 'text/plain; charset=utf-8') {
  res.writeHead(code, { 'Content-Type': type });
  res.end(body);
}

function sendFile(res, file) {
  const ext = path.extname(file).toLowerCase();
  const type = MIME[ext] || 'application/octet-stream';
  const stream = fs.createReadStream(file);
  res.writeHead(200, { 'Content-Type': type, 'Cache-Control': 'public, max-age=86400' });
  stream.pipe(res);
}

function safeStatic(urlPath) {
  const rel = decodeURIComponent(urlPath.split('?')[0]);
  if (rel.includes('..')) return null;
  const file = path.normalize(path.join(PUBLIC, rel));
  if (!file.startsWith(PUBLIC)) return null;
  if (fs.existsSync(file) && fs.statSync(file).isFile()) return file;
  return null;
}

function readBody(req) {
  return new Promise((resolve, reject) => {
    const chunks = [];
    let size = 0;
    req.on('data', (c) => {
      size += c.length;
      if (size > 8 * 1024 * 1024) {
        reject(new Error('too large'));
        req.destroy();
        return;
      }
      chunks.push(c);
    });
    req.on('end', () => resolve(Buffer.concat(chunks)));
    req.on('error', reject);
  });
}

function parseForm(buf, contentType) {
  const type = String(contentType || '');
  if (type.includes('multipart/form-data')) {
    return parseMultipart(buf, type);
  }
  const fields = Object.fromEntries(new URLSearchParams(buf.toString('utf8')));
  return { fields, file: null };
}

function parseMultipart(buf, contentType) {
  const m = /boundary=(?:"([^"]+)"|([^;]+))/i.exec(contentType);
  if (!m) return { fields: {}, file: null };
  const boundary = `--${m[1] || m[2]}`;
  const parts = buf.toString('latin1').split(boundary).slice(1);
  const fields = {};
  let file = null;
  for (const part of parts) {
    if (part === '--\r\n' || part === '--') continue;
    const split = part.indexOf('\r\n\r\n');
    if (split < 0) continue;
    const head = part.slice(0, split);
    let body = part.slice(split + 4);
    if (body.endsWith('\r\n')) body = body.slice(0, -2);
    const nameM = /name="([^"]+)"/i.exec(head);
    const fileM = /filename="([^"]*)"/i.exec(head);
    if (!nameM) continue;
    if (fileM && fileM[1]) {
      const mimeM = /Content-Type:\s*([^\r\n]+)/i.exec(head);
      file = {
        field: nameM[1],
        filename: fileM[1],
        mime: mimeM ? mimeM[1].trim() : 'application/octet-stream',
        data: Buffer.from(body, 'latin1'),
      };
    } else {
      fields[nameM[1]] = Buffer.from(body, 'latin1').toString('utf8');
    }
  }
  return { fields, file };
}

function checkCsrf(sess, fields) {
  return fields._csrf && fields._csrf === sess.csrf;
}

function uniqueSlug(type, value, id) {
  let s = value;
  let n = 2;
  const base = s;
  while (true) {
    const found = store.findBy(type, 'slug', s);
    if (!found || String(found.id) === String(id)) return s;
    s = `${base}-${n}`;
    n += 1;
  }
}

function hydrateCart(sess) {
  const lines = [];
  for (const row of sess.cart || []) {
    const product = store.find('products', row.id);
    if (!product || product.status !== 'published') continue;
    const qty = Math.max(1, Number(row.qty) || 1);
    lines.push({ product, qty, line: Number(product.price || 0) * qty });
  }
  return lines;
}

function cartTotal(lines) {
  return lines.reduce((n, l) => n + l.line, 0);
}

function requireAdmin(ctx, res, sess) {
  if (!ctx.user) {
    redirect(res, sess, '/admin/login');
    return false;
  }
  return true;
}

function ipOf(req) {
  return String(req.headers['x-forwarded-for'] || req.socket.remoteAddress || '0').split(',')[0].trim();
}

function loginAllowed(ip) {
  const row = loginHits.get(ip);
  if (!row) return true;
  if (Date.now() - row.t > 15 * 60 * 1000) return true;
  return row.n < 8;
}

function loginHit(ip) {
  const row = loginHits.get(ip);
  if (!row || Date.now() - row.t > 15 * 60 * 1000) loginHits.set(ip, { n: 1, t: Date.now() });
  else row.n += 1;
}

async function handle(req, res) {
  const sess = session(req);
  const ctx = ctxOf(req, sess);
  ctx.flash = takeFlash(sess);
  const method = req.method.toUpperCase();
  const { path: p } = ctx;

  if (method === 'GET' && (p.startsWith('/css/') || p.startsWith('/js/') || p.startsWith('/img/') || p.startsWith('/uploads/'))) {
    const file = safeStatic(p);
    if (file) return sendFile(res, file);
    return text(res, 404, 'not found');
  }

  if (method === 'GET' && p === '/health') {
    return text(res, 200, 'ok');
  }

  if (!installed() && p !== '/install') {
    return redirect(res, sess, '/install');
  }

  try {
    if (p === '/install' && method === 'GET') {
      if (installed()) return redirect(res, sess, '/');
      const fwd = String(req.headers['x-forwarded-proto'] || '').split(',')[0].trim();
      const proto = fwd === 'https' || fwd === 'http' ? fwd : 'http';
      const guess = `${proto}://${req.headers.host || 'localhost:8787'}`;
      return html(res, sess, 200, frontT.install('', guess, sess.csrf));
    }
    if (p === '/install' && method === 'POST') {
      if (installed()) return redirect(res, sess, '/');
      const { fields } = parseForm(await readBody(req), req.headers['content-type']);
      if (!checkCsrf(sess, fields)) return text(res, 400, 'نشست منقضی شد.');
      const user = String(fields.username || '').trim();
      const pass = String(fields.password || '');
      const pass2 = String(fields.password2 || '');
      const email = String(fields.email || '').trim();
      const siteUrl = String(fields.site_url || '').trim().replace(/\/$/, '');
      let error = '';
      if (user.length < 3) error = 'نام کاربری حداقل ۳ حرف باشد.';
      else if (pass.length < 8) error = 'رمز حداقل ۸ کاراکتر باشد.';
      else if (pass !== pass2) error = 'تکرار رمز مطابقت ندارد.';
      else if (!siteUrl) error = 'آدرس سایت را بنویسید.';
      if (error) return html(res, sess, 200, frontT.install(error, siteUrl, sess.csrf));
      seed.run({ username: user, password: pass, email: email || 'admin@gtavistore.ir', site_url: siteUrl });
      fs.writeFileSync(LOCK, `${VERSION}\n${new Date().toISOString()}\n`);
      const u = store.findBy('users', 'username', user);
      sess.userId = u ? u.id : null;
      sess.flash = { type: 'ok', text: 'نصب تمام شد. از همین پنل خبر و محصول را مدیریت کنید — ساده‌تر از وردپرس.' };
      return redirect(res, sess, '/admin');
    }

    if (p === '/robots.txt') {
      return text(res, 200, `User-agent: *\nAllow: /\nDisallow: /admin\nDisallow: /install\nDisallow: /checkout\nDisallow: /cart\nSitemap: ${setting('site_url')}/sitemap.xml\n`);
    }
    if (p === '/sitemap.xml') {
      const urls = ['/', '/news', '/rumors', '/guide', '/shop'];
      publishedPosts().forEach((post) => urls.push(`/${sectionOf(post.type)}/${post.slug}`));
      publishedProducts().forEach((prod) => urls.push(`/product/${prod.slug}`));
      store.where('pages', (x) => x.status === 'published').forEach((page) => urls.push(`/p/${page.slug}`));
      store.where('categories', (x) => x.kind === 'product').forEach((cat) => urls.push(`/shop/${cat.slug}`));
      const base = setting('site_url');
      const xml = `<?xml version="1.0" encoding="UTF-8"?>\n<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">\n${urls.map((u) => `<url><loc>${base}${u}</loc></url>`).join('\n')}\n</urlset>`;
      return text(res, 200, xml, 'application/xml; charset=utf-8');
    }
    if (p === '/feed.xml') {
      const base = setting('site_url');
      const items = publishedPosts().slice(0, 20).map((post) => {
        const link = `${base}/${sectionOf(post.type)}/${post.slug}`;
        return `<item><title>${escapeXml(post.title)}</title><link>${link}</link><description>${escapeXml(post.excerpt)}</description></item>`;
      }).join('');
      const rss = `<?xml version="1.0" encoding="UTF-8"?><rss version="2.0"><channel><title>${escapeXml(setting('site_title'))}</title><link>${base}</link><language>fa-IR</language>${items}</channel></rss>`;
      return text(res, 200, rss, 'application/rss+xml; charset=utf-8');
    }

    if (p === '/' && method === 'GET') return html(res, sess, 200, frontT.home(ctx));
    if (p === '/news' && method === 'GET') return html(res, sess, 200, frontT.archive(ctx, 'news', 'اخبار GTA VI', 'خبرهای رسمی و پوشش تریلر، تاریخ انتشار و نسخه‌ها — جدا از شایعه.'));
    if (p === '/rumors' && method === 'GET') return html(res, sess, 200, frontT.archive(ctx, 'rumor', 'شایعه‌ها', 'گمانه‌زنی‌ها با برچسب واضح. تا راک‌استار تأیید نکند، خریدتان را روی شایعه قفل نکنید.'));
    if (p === '/guide' && method === 'GET') return html(res, sess, 200, frontT.archive(ctx, 'guide', 'راهنمای بازی', 'شخصیت‌ها، نقشه لئونیدا، پلتفرم و راهنمای خرید.'));

    const postM = p.match(/^\/(news|rumors|guide)\/([^/]+)$/);
    if (postM && method === 'GET') {
      const post = store.findBy('posts', 'slug', decodeURIComponent(postM[2]));
      if (!post || post.status !== 'published') return html(res, sess, 404, frontT.notFound(ctx));
      const expected = postM[1] === 'rumors' ? 'rumor' : postM[1] === 'guide' ? 'guide' : 'news';
      if (post.type !== expected) return redirect(res, sess, `/${sectionOf(post.type)}/${post.slug}`, 301);
      return html(res, sess, 200, frontT.single(ctx, post));
    }

    if (p === '/shop' && method === 'GET') return html(res, sess, 200, frontT.shop(ctx, null));
    const shopM = p.match(/^\/shop\/([^/]+)$/);
    if (shopM && method === 'GET') {
      const cat = store.findBy('categories', 'slug', decodeURIComponent(shopM[1]));
      if (!cat || cat.kind !== 'product') return html(res, sess, 404, frontT.notFound(ctx));
      return html(res, sess, 200, frontT.shop(ctx, cat));
    }
    const prodM = p.match(/^\/product\/([^/]+)$/);
    if (prodM && method === 'GET') {
      const item = store.findBy('products', 'slug', decodeURIComponent(prodM[1]));
      if (!item || item.status !== 'published') return html(res, sess, 404, frontT.notFound(ctx));
      return html(res, sess, 200, frontT.product(ctx, item));
    }

    if (p === '/cart' && method === 'GET') {
      const lines = hydrateCart(sess);
      return html(res, sess, 200, frontT.cart(ctx, lines, cartTotal(lines), Number(setting('shipping_cost', 0))));
    }
    if (p === '/cart/add' && method === 'POST') {
      const { fields } = parseForm(await readBody(req), req.headers['content-type']);
      if (!checkCsrf(sess, fields)) return text(res, 400, 'نشست منقضی شد.');
      const id = String(fields.product_id || '');
      const prod = store.find('products', id);
      if (!prod || prod.status !== 'published') {
        sess.flash = { type: 'err', text: 'محصول پیدا نشد.' };
        return redirect(res, sess, '/shop');
      }
      const qty = Math.max(1, Number(fields.qty) || 1);
      const hit = sess.cart.find((r) => r.id === id);
      if (hit) hit.qty += qty;
      else sess.cart.push({ id, qty });
      sess.flash = { type: 'ok', text: 'به سبد اضافه شد.' };
      return redirect(res, sess, '/cart');
    }
    if (p === '/cart/update' && method === 'POST') {
      const { fields } = parseForm(await readBody(req), req.headers['content-type']);
      if (!checkCsrf(sess, fields)) return text(res, 400, 'نشست منقضی شد.');
      const id = String(fields.product_id || '');
      const qty = Number(fields.qty) || 0;
      sess.cart = sess.cart.map((r) => (r.id === id ? { ...r, qty } : r)).filter((r) => r.qty > 0);
      return redirect(res, sess, '/cart');
    }
    if (p === '/cart/remove' && method === 'POST') {
      const { fields } = parseForm(await readBody(req), req.headers['content-type']);
      if (!checkCsrf(sess, fields)) return text(res, 400, 'نشست منقضی شد.');
      sess.cart = sess.cart.filter((r) => r.id !== String(fields.product_id || ''));
      return redirect(res, sess, '/cart');
    }

    if (p === '/checkout' && method === 'GET') {
      if (!sess.cart.length) {
        sess.flash = { type: 'err', text: 'سبد خالی است.' };
        return redirect(res, sess, '/cart');
      }
      const lines = hydrateCart(sess);
      return html(res, sess, 200, frontT.checkout(ctx, lines, cartTotal(lines), Number(setting('shipping_cost', 0))));
    }
    if (p === '/checkout' && method === 'POST') {
      const { fields } = parseForm(await readBody(req), req.headers['content-type']);
      if (!checkCsrf(sess, fields)) return text(res, 400, 'نشست منقضی شد.');
      const lines = hydrateCart(sess);
      if (!lines.length) return redirect(res, sess, '/cart');
      const name = String(fields.name || '').trim();
      const mob = phone(String(fields.phone || ''));
      if (!name || !/^09\d{9}$/.test(mob)) {
        sess.flash = { type: 'err', text: 'نام و موبایل ۱۱ رقمی با ۰۹ لازم است.' };
        return redirect(res, sess, '/checkout');
      }
      const hasPhysical = lines.some((l) => l.product.kind !== 'digital' && l.product.kind !== 'addon');
      const shipping = hasPhysical ? Number(setting('shipping_cost', 0)) : 0;
      const code = `GVS-${crypto.randomBytes(4).toString('hex').slice(0, 8).toUpperCase()}`;
      const order = store.save('orders', {
        id: uid('ord'),
        code,
        status: 'pending',
        name,
        phone: mob,
        city: String(fields.city || '').trim(),
        address: String(fields.address || '').trim(),
        pay: String(fields.pay || 'card'),
        note: String(fields.note || '').trim(),
        items: lines.map((l) => ({ id: l.product.id, title: l.product.title, qty: l.qty, price: Number(l.product.price) })),
        subtotal: cartTotal(lines),
        shipping,
        total: cartTotal(lines) + shipping,
      });
      sess.cart = [];
      return redirect(res, sess, `/order/${order.code}`);
    }
    const ordM = p.match(/^\/order\/([^/]+)$/);
    if (ordM && method === 'GET') {
      const item = store.findBy('orders', 'code', decodeURIComponent(ordM[1]).toUpperCase())
        || store.findBy('orders', 'code', decodeURIComponent(ordM[1]));
      if (!item) return html(res, sess, 404, frontT.notFound(ctx));
      return html(res, sess, 200, frontT.order(ctx, item));
    }

    if (p === '/search' && method === 'GET') {
      const q = String(ctx.query.q || '').trim();
      let posts = [];
      let products = [];
      if (q) {
        const ql = q.toLowerCase();
        posts = publishedPosts().filter((item) => `${item.title} ${item.excerpt} ${item.body}`.toLowerCase().includes(ql));
        products = publishedProducts().filter((item) => `${item.title} ${item.excerpt} ${item.body}`.toLowerCase().includes(ql));
      }
      return html(res, sess, 200, frontT.search(ctx, q, posts, products));
    }
    const pageM = p.match(/^\/p\/([^/]+)$/);
    if (pageM && method === 'GET') {
      const page = store.findBy('pages', 'slug', decodeURIComponent(pageM[1]));
      if (!page || page.status !== 'published') return html(res, sess, 404, frontT.notFound(ctx));
      return html(res, sess, 200, frontT.cmsPage(ctx, page));
    }

    if (p === '/admin/login' && method === 'GET') {
      if (ctx.user) return redirect(res, sess, '/admin');
      return html(res, sess, 200, adminT.login('', sess.csrf));
    }
    if (p === '/admin/login' && method === 'POST') {
      const { fields } = parseForm(await readBody(req), req.headers['content-type']);
      if (!checkCsrf(sess, fields)) return text(res, 400, 'نشست منقضی شد.');
      const ip = ipOf(req);
      if (!loginAllowed(ip)) return html(res, sess, 200, adminT.login('تلاش زیاد. کمی صبر کنید.', sess.csrf));
      const user = store.findBy('users', 'username', String(fields.username || '').trim());
      if (!user || !verifyPassword(String(fields.password || ''), user.password)) {
        loginHit(ip);
        return html(res, sess, 200, adminT.login('نام کاربری یا رمز اشتباه است.', sess.csrf));
      }
      sess.userId = user.id;
      return redirect(res, sess, '/admin');
    }
    if (p === '/admin/logout') {
      sess.userId = null;
      return redirect(res, sess, '/admin/login');
    }

    if (p.startsWith('/admin')) {
      if (!requireAdmin(ctx, res, sess)) return;
      return adminRoutes(req, res, sess, ctx, method, p);
    }

    return html(res, sess, 404, frontT.notFound(ctx));
  } catch (err) {
    console.error(err);
    return text(res, 500, 'خطای سرور');
  }
}

async function adminRoutes(req, res, sess, ctx, method, p) {
  if (p === '/admin' && method === 'GET') return html(res, sess, 200, adminT.dashboard(ctx));

  if (p === '/admin/posts' && method === 'GET') {
    return html(res, sess, 200, adminT.list(ctx, 'نوشته‌ها', '/admin/posts/new', 'posts', sortRows(store.all('posts'))));
  }
  if (p === '/admin/posts/new' && method === 'GET') {
    return html(res, sess, 200, adminT.postForm(ctx, { type: 'news', status: 'published' }, postCats()));
  }
  const pe = p.match(/^\/admin\/posts\/([^/]+)$/);
  if (pe && method === 'GET') {
    const item = store.find('posts', pe[1]);
    if (!item) return redirect(res, sess, '/admin/posts');
    return html(res, sess, 200, adminT.postForm(ctx, item, postCats()));
  }
  if (p === '/admin/posts/save' && method === 'POST') {
    const { fields } = parseForm(await readBody(req), req.headers['content-type']);
    if (!checkCsrf(sess, fields)) return text(res, 400, 'نشست منقضی شد.');
    const title = String(fields.title || '').trim();
    const id = String(fields.id || '') || uid('post');
    const s = uniqueSlug('posts', String(fields.slug || '').trim() || slug(title), id);
    store.save('posts', {
      id, title, slug: s, type: fields.type || 'news', category_id: fields.category_id || '',
      status: fields.status || 'draft', featured: fields.featured ? 1 : 0, cover: String(fields.cover || '').trim(),
      excerpt: String(fields.excerpt || '').trim(), body: String(fields.body || ''),
      seo_title: String(fields.seo_title || '').trim(), seo_description: String(fields.seo_description || '').trim(),
    });
    sess.flash = { type: 'ok', text: 'نوشته ذخیره شد.' };
    return redirect(res, sess, '/admin/posts');
  }
  if (p === '/admin/posts/delete' && method === 'POST') return del(req, res, sess, 'posts', '/admin/posts');

  if (p === '/admin/products' && method === 'GET') {
    return html(res, sess, 200, adminT.list(ctx, 'محصولات', '/admin/products/new', 'products', sortRows(store.all('products'))));
  }
  if (p === '/admin/products/new' && method === 'GET') {
    return html(res, sess, 200, adminT.productForm(ctx, { kind: 'physical', status: 'published', in_stock: 1 }, productCats()));
  }
  const pr = p.match(/^\/admin\/products\/([^/]+)$/);
  if (pr && method === 'GET') {
    const item = store.find('products', pr[1]);
    if (!item) return redirect(res, sess, '/admin/products');
    return html(res, sess, 200, adminT.productForm(ctx, item, productCats()));
  }
  if (p === '/admin/products/save' && method === 'POST') {
    const { fields } = parseForm(await readBody(req), req.headers['content-type']);
    if (!checkCsrf(sess, fields)) return text(res, 400, 'نشست منقضی شد.');
    const title = String(fields.title || '').trim();
    const id = String(fields.id || '') || uid('prd');
    store.save('products', {
      id, title, slug: uniqueSlug('products', String(fields.slug || '').trim() || slug(title), id),
      sku: String(fields.sku || '').trim(), kind: fields.kind || 'physical', category_id: fields.category_id || '',
      price: Number(fields.price || 0), compare_at: Number(fields.compare_at || 0), stock: Number(fields.stock || 0),
      in_stock: fields.in_stock ? 1 : 0, featured: fields.featured ? 1 : 0, status: fields.status || 'draft',
      cover: String(fields.cover || '').trim(), excerpt: String(fields.excerpt || '').trim(), body: String(fields.body || ''),
      seo_title: String(fields.seo_title || '').trim(), seo_description: String(fields.seo_description || '').trim(),
    });
    sess.flash = { type: 'ok', text: 'محصول ذخیره شد.' };
    return redirect(res, sess, '/admin/products');
  }
  if (p === '/admin/products/delete' && method === 'POST') return del(req, res, sess, 'products', '/admin/products');

  if (p === '/admin/orders' && method === 'GET') {
    return html(res, sess, 200, adminT.list(ctx, 'سفارش‌ها', '', 'orders', sortRows(store.all('orders'))));
  }
  const om = p.match(/^\/admin\/orders\/([^/]+)$/);
  if (om && method === 'GET') {
    const item = store.find('orders', om[1]);
    if (!item) return redirect(res, sess, '/admin/orders');
    return html(res, sess, 200, adminT.orderView(ctx, item));
  }
  if (p === '/admin/orders/save' && method === 'POST') {
    const { fields } = parseForm(await readBody(req), req.headers['content-type']);
    if (!checkCsrf(sess, fields)) return text(res, 400, 'نشست منقضی شد.');
    const item = store.find('orders', String(fields.id || ''));
    if (item) {
      item.status = fields.status || item.status;
      item.admin_note = String(fields.admin_note || '').trim();
      store.save('orders', item);
      sess.flash = { type: 'ok', text: 'سفارش به‌روز شد.' };
    }
    return redirect(res, sess, `/admin/orders/${fields.id}`);
  }

  if (p === '/admin/pages' && method === 'GET') {
    return html(res, sess, 200, adminT.list(ctx, 'برگه‌ها', '/admin/pages/new', 'pages', sortRows(store.all('pages'))));
  }
  if (p === '/admin/pages/new' && method === 'GET') {
    return html(res, sess, 200, adminT.pageForm(ctx, { status: 'published' }));
  }
  const pg = p.match(/^\/admin\/pages\/([^/]+)$/);
  if (pg && method === 'GET') {
    const item = store.find('pages', pg[1]);
    if (!item) return redirect(res, sess, '/admin/pages');
    return html(res, sess, 200, adminT.pageForm(ctx, item));
  }
  if (p === '/admin/pages/save' && method === 'POST') {
    const { fields } = parseForm(await readBody(req), req.headers['content-type']);
    if (!checkCsrf(sess, fields)) return text(res, 400, 'نشست منقضی شد.');
    const title = String(fields.title || '').trim();
    const id = String(fields.id || '') || uid('page');
    store.save('pages', {
      id, title, slug: uniqueSlug('pages', String(fields.slug || '').trim() || slug(title), id),
      body: String(fields.body || ''), status: fields.status || 'draft', in_nav: fields.in_nav ? 1 : 0,
      seo_title: String(fields.seo_title || '').trim(), seo_description: String(fields.seo_description || '').trim(),
    });
    sess.flash = { type: 'ok', text: 'برگه ذخیره شد.' };
    return redirect(res, sess, '/admin/pages');
  }
  if (p === '/admin/pages/delete' && method === 'POST') return del(req, res, sess, 'pages', '/admin/pages');

  if (p === '/admin/categories' && method === 'GET') return html(res, sess, 200, adminT.categories(ctx, store.all('categories')));
  if (p === '/admin/categories/save' && method === 'POST') {
    const { fields } = parseForm(await readBody(req), req.headers['content-type']);
    if (!checkCsrf(sess, fields)) return text(res, 400, 'نشست منقضی شد.');
    const title = String(fields.title || '').trim();
    store.save('categories', {
      id: uid('cat'), title, slug: String(fields.slug || '').trim() || slug(title),
      kind: fields.kind || 'post', type: fields.type || 'news', status: 'published',
    });
    sess.flash = { type: 'ok', text: 'دسته ذخیره شد.' };
    return redirect(res, sess, '/admin/categories');
  }
  if (p === '/admin/categories/delete' && method === 'POST') return del(req, res, sess, 'categories', '/admin/categories');

  if (p === '/admin/media' && method === 'GET') return html(res, sess, 200, adminT.media(ctx, store.all('media').slice().reverse()));
  if (p === '/admin/media/upload' && method === 'POST') {
    const { fields, file } = parseForm(await readBody(req), req.headers['content-type']);
    if (!checkCsrf(sess, fields)) return text(res, 400, 'نشست منقضی شد.');
    const ok = { 'image/jpeg': 'jpg', 'image/png': 'png', 'image/webp': 'webp', 'image/gif': 'gif', 'image/svg+xml': 'svg' };
    if (!file || !ok[file.mime] || file.data.length > 6 * 1024 * 1024) {
      sess.flash = { type: 'err', text: 'فقط تصویر تا ۶ مگابایت.' };
      return redirect(res, sess, '/admin/media');
    }
    const name = `${new Date().toISOString().slice(0, 10).replace(/-/g, '')}-${crypto.randomBytes(4).toString('hex')}.${ok[file.mime]}`;
    const dest = path.join(ROOT, 'public', 'uploads', name);
    fs.writeFileSync(dest, file.data);
    store.save('media', { id: uid('media'), file: name, url: `/uploads/${name}`, mime: file.mime, alt: file.filename });
    sess.flash = { type: 'ok', text: 'آپلود شد.' };
    return redirect(res, sess, '/admin/media');
  }
  if (p === '/admin/media/delete' && method === 'POST') {
    const { fields } = parseForm(await readBody(req), req.headers['content-type']);
    if (!checkCsrf(sess, fields)) return text(res, 400, 'نشست منقضی شد.');
    const item = store.find('media', String(fields.id || ''));
    if (item && item.file) {
      const dest = path.join(ROOT, 'public', 'uploads', path.basename(item.file));
      if (fs.existsSync(dest)) fs.unlinkSync(dest);
    }
    store.delete('media', String(fields.id || ''));
    sess.flash = { type: 'ok', text: 'حذف شد.' };
    return redirect(res, sess, '/admin/media');
  }

  if (p === '/admin/settings' && method === 'GET') {
    return html(res, sess, 200, adminT.settings(ctx, store.find('settings', 'main') || {}));
  }
  if (p === '/admin/settings' && method === 'POST') {
    const { fields } = parseForm(await readBody(req), req.headers['content-type']);
    if (!checkCsrf(sess, fields)) return text(res, 400, 'نشست منقضی شد.');
    const s = store.find('settings', 'main') || { id: 'main' };
    [
      'site_title', 'tagline', 'seo_title', 'seo_description', 'site_url', 'og_image',
      'contact_phone', 'contact_email', 'card_number', 'card_name', 'instagram', 'telegram',
      'analytics', 'disclaimer', 'footer_note',
    ].forEach((k) => { s[k] = String(fields[k] || '').trim(); });
    s.shipping_cost = Number(fields.shipping_cost || 0);
    s.cod_enabled = fields.cod_enabled ? 1 : 0;
    s.card_enabled = fields.card_enabled ? 1 : 0;
    store.save('settings', s);
    const np = String(fields.new_password || '');
    if (np.length >= 8 && ctx.user) {
      ctx.user.password = hashPassword(np);
      store.save('users', ctx.user);
    }
    sess.flash = { type: 'ok', text: 'تنظیمات ذخیره شد.' };
    return redirect(res, sess, '/admin/settings');
  }

  return html(res, sess, 404, frontT.notFound(ctx));
}

async function del(req, res, sess, type, back) {
  const { fields } = parseForm(await readBody(req), req.headers['content-type']);
  if (!checkCsrf(sess, fields)) return text(res, 400, 'نشست منقضی شد.');
  store.delete(type, String(fields.id || ''));
  sess.flash = { type: 'ok', text: 'حذف شد.' };
  return redirect(res, sess, back);
}

function sortRows(rows) {
  return rows.slice().sort((a, b) => String(b.updated_at || b.created_at || '').localeCompare(String(a.updated_at || a.created_at || '')));
}
function postCats() { return store.where('categories', (c) => c.kind === 'post'); }
function productCats() { return store.where('categories', (c) => c.kind === 'product'); }
function escapeXml(s) {
  return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}

function createApp() {
  return (req, res) => {
    handle(req, res);
  };
}

module.exports = { createApp };
