'use strict';

const http = require('http');
const { spawn } = require('child_process');
const { URL } = require('url');

const PORT = Number(process.env.CHECK_PORT || 8799);
const BASE = `http://127.0.0.1:${PORT}`;

function req(method, path, { body, cookie, type } = {}) {
  return new Promise((resolve, reject) => {
    const u = new URL(path, BASE);
    const payload = body ? Buffer.from(body) : null;
    const headers = { Accept: 'text/html,*/*' };
    if (cookie) headers.Cookie = cookie;
    if (payload) {
      headers['Content-Type'] = type || 'application/x-www-form-urlencoded';
      headers['Content-Length'] = String(payload.length);
    }
    const r = http.request({
      hostname: u.hostname,
      port: u.port,
      path: u.pathname + u.search,
      method,
      headers,
    }, (res) => {
      const chunks = [];
      res.on('data', (c) => chunks.push(c));
      res.on('end', () => {
        const set = res.headers['set-cookie'] || [];
        const gvs = String(set.find((s) => s.startsWith('gvs=')) || '').split(';')[0];
        resolve({
          status: res.statusCode,
          location: res.headers.location || '',
          cookie: gvs || cookie || '',
          body: Buffer.concat(chunks).toString('utf8'),
        });
      });
    });
    r.on('error', reject);
    if (payload) r.write(payload);
    r.end();
  });
}

function csrf(html) {
  const m = /name="_csrf" value="([^"]+)"/.exec(html);
  return m ? m[1] : '';
}

function assert(cond, msg) {
  if (!cond) throw new Error(msg);
}

async function waitUp() {
  for (let i = 0; i < 40; i += 1) {
    try {
      await req('GET', '/css/front.css');
      return;
    } catch {
      await new Promise((r) => setTimeout(r, 150));
    }
  }
  throw new Error('server did not start');
}

async function main() {
  const child = spawn(process.execPath, ['server.js'], {
    cwd: require('path').join(__dirname, '..'),
    env: { ...process.env, PORT: String(PORT), HOST: '127.0.0.1' },
    stdio: ['ignore', 'pipe', 'pipe'],
  });
  let out = '';
  child.stdout.on('data', (d) => { out += d; });
  child.stderr.on('data', (d) => { out += d; });
  try {
    await waitUp();
    const css = await req('GET', '/css/front.css');
    assert(css.status === 200 && css.body.includes('--pink'), 'css');
    const health = await req('GET', '/health');
    assert(health.status === 200 && health.body.includes('ok'), 'health');

    let home = await req('GET', '/');
    let cookie = home.cookie;
    if (home.status === 302 && home.location.includes('install')) {
      const inst = await req('GET', '/install');
      cookie = inst.cookie;
      const token = csrf(inst.body);
      assert(token, 'install csrf');
      const posted = await req('POST', '/install', {
        cookie,
        body: new URLSearchParams({
          _csrf: token,
          site_url: BASE,
          email: 'admin@gtavistore.ir',
          username: 'admin',
          password: 'visote-admin-1',
          password2: 'visote-admin-1',
        }).toString(),
      });
      assert(posted.status === 302 && posted.location.includes('/admin'), `install redirect ${posted.status} ${posted.location}`);
      cookie = posted.cookie || cookie;
      home = await req('GET', '/', { cookie });
    }
    if (home.status === 302) home = await req('GET', home.location || '/', { cookie });
    assert(home.status === 200, `home ${home.status}`);
    assert(home.body.includes('VISOTE'), 'brand');
    assert(home.body.includes('gtavistore.ir'), 'domain');
    assert(home.body.includes('شایعه'), 'rumor nav');
    assert(home.body.includes('فروشگاه'), 'shop nav');
    assert(home.body.includes('application/ld+json'), 'jsonld');

    const news = await req('GET', '/news');
    assert(news.status === 200 && news.body.includes('اخبار GTA VI'), 'news');
    const article = await req('GET', '/news/gta-vi-release-19-november-2026');
    assert(article.status === 200 && article.body.includes('۱۹ نوامبر'), 'article');
    const rumor = await req('GET', '/rumors/pc-version-timing-rumor');
    assert(rumor.status === 200 && rumor.body.includes('شایعه است'), 'rumor badge');
    const shop = await req('GET', '/shop');
    assert(shop.status === 200 && shop.body.includes('باندل'), 'shop');
    const product = await req('GET', '/product/gta-vi-ps5-standard-physical', { cookie });
    assert(product.status === 200 && product.body.includes('افزودن به سبد'), 'product');
    const sm = await req('GET', '/sitemap.xml');
    assert(sm.status === 200 && sm.body.includes('<urlset'), 'sitemap');
    const robots = await req('GET', '/robots.txt');
    assert(robots.status === 200 && robots.body.includes('Disallow: /admin'), 'robots');
    const about = await req('GET', '/p/about');
    assert(about.status === 200 && about.body.includes('بدون وردپرس'), 'about');
    const miss = await req('GET', '/does-not-exist');
    assert(miss.status === 404, '404');

    const token = csrf(product.body);
    const add = await req('POST', '/cart/add', {
      cookie,
      body: new URLSearchParams({ _csrf: token, product_id: 'prd_ps5_std', qty: '1' }).toString(),
    });
    assert(add.status === 302, 'cart add');
    cookie = add.cookie || cookie;
    const cart = await req('GET', '/cart', { cookie: add.cookie || cookie });
    assert(cart.status === 200 && cart.body.includes('GTA VI نسخه استاندارد PS5'), 'cart line');

    const loginPage = await req('GET', '/admin/login');
    const login = await req('POST', '/admin/login', {
      cookie: loginPage.cookie,
      body: new URLSearchParams({
        _csrf: csrf(loginPage.body),
        username: 'admin',
        password: 'visote-admin-1',
      }).toString(),
    });
    if (login.status === 302) {
      const dash = await req('GET', '/admin', { cookie: login.cookie || loginPage.cookie });
      assert(dash.status === 200 && dash.body.includes('داشبورد'), 'admin dash');
    }

    console.log('self-check ok');
  } finally {
    child.kill('SIGTERM');
  }
}

main().catch((err) => {
  console.error('self-check failed:', err.message);
  process.exit(1);
});
