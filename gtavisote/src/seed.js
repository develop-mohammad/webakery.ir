'use strict';

const store = require('./store');
const { hashPassword } = require('./util');

function run(opts = {}) {
  const now = new Date().toISOString();
  const siteUrl = String(opts.site_url || 'https://gtavistore.ir').replace(/\/$/, '');
  const user = opts.username || 'admin';
  const pass = opts.password || 'change-me';
  const email = opts.email || 'admin@gtavistore.ir';

  store.replaceAll('users', [{
    id: 'user_admin',
    username: user,
    email,
    password: hashPassword(pass),
    role: 'admin',
    created_at: now,
    updated_at: now,
  }]);

  store.replaceAll('settings', [{
    id: 'main',
    site_title: 'وی‌سوت | GTA VISOTE',
    tagline: 'مرجع فارسی اخبار، شایعه و فروشگاه GTA VI',
    seo_title: 'وی‌سوت | اخبار و فروشگاه GTA VI — gtavistore.ir',
    seo_description: 'اخبار رسمی، شایعات جداشده، راهنمای بازی و فروش نسخه فیزیکی، دیجیتال، باندل کنسول و محصولات مرتبط با GTA VI.',
    site_url: siteUrl,
    og_image: '/img/og.svg',
    contact_phone: '09120000000',
    contact_email: 'shop@gtavistore.ir',
    address: 'ایران — فروش آنلاین',
    card_number: '6037-9977-0000-0000',
    card_name: 'فروشگاه وی‌سوت',
    shipping_cost: 85000,
    cod_enabled: 1,
    card_enabled: 1,
    instagram: 'https://instagram.com/gtavistore',
    telegram: 'https://t.me/gtavistore',
    analytics: '',
    disclaimer: 'وی‌سوت یک وب‌سایت غیررسمی است و هیچ وابستگی به Rockstar Games یا Take-Two Interactive ندارد. علائم تجاری Grand Theft Auto متعلق به دارندگان قانونی آن‌هاست.',
    footer_note: 'پیش‌خرید بازی اصلی فقط از فروشندگان مجاز. فایل کرک، بیلد لو رفته و محتوای غیرقانونی فروخته نمی‌شود.',
    created_at: now,
    updated_at: now,
  }]);

  store.replaceAll('categories', categories(now));
  store.replaceAll('posts', posts());
  store.replaceAll('products', products());
  store.replaceAll('pages', pages(now));
  store.replaceAll('orders', []);
  store.replaceAll('media', []);
}

function stamp(row, now) {
  return { ...row, status: 'published', created_at: now, updated_at: now };
}

function categories(now) {
  return [
    ['cat_official', 'post', 'official', 'اخبار رسمی', 'news'],
    ['cat_trailer', 'post', 'trailer', 'تریلر و گیم‌پلی', 'news'],
    ['cat_rumor', 'post', 'rumor', 'شایعه و گمانه‌زنی', 'rumor'],
    ['cat_chars', 'post', 'characters', 'شخصیت‌ها', 'guide'],
    ['cat_map', 'post', 'map', 'نقشه و شهرها', 'guide'],
    ['cat_plat', 'post', 'platforms', 'پلتفرم و نسخه', 'guide'],
    ['cat_physical', 'product', 'physical', 'نسخه فیزیکی', 'shop'],
    ['cat_digital', 'product', 'digital', 'نسخه دیجیتال', 'shop'],
    ['cat_bundle', 'product', 'bundle', 'باندل کنسول', 'shop'],
    ['cat_edition', 'product', 'edition', 'نسخه ویژه', 'shop'],
    ['cat_merch', 'product', 'merch', 'کالکشن و مرچ', 'shop'],
    ['cat_addon', 'product', 'addon', 'پک و لوازم جانبی', 'shop'],
  ].map(([id, kind, slug, title, type]) => stamp({ id, kind, slug, title, type }, now));
}

function withMeta(items) {
  return items.map((p, i) => {
    const created = new Date(Date.now() - (12 - i) * 86400000).toISOString();
    return {
      ...p,
      status: 'published',
      seo_title: '',
      seo_description: p.excerpt,
      created_at: created,
      updated_at: created,
    };
  });
}

function posts() {
  return withMeta([
    {
      id: 'post_countdown', type: 'news', category_id: 'cat_official', featured: 1,
      slug: 'gta-vi-release-19-november-2026',
      title: 'تاریخ انتشار قطعی: ۱۹ نوامبر ۲۰۲۶ روی PS5 و Xbox Series',
      excerpt: 'راک‌استار تاریخ لانچ Grand Theft Auto VI را ۱۹ نوامبر ۲۰۲۶ اعلام کرده؛ پیش‌بارگذاری دیجیتال از ۱۲ نوامبر ممکن است.',
      cover: '/img/cover-news.svg',
      body: `## چه می‌دانیم؟

راک‌استار گیمز انتشار **Grand Theft Auto VI** را برای **۱۹ نوامبر ۲۰۲۶** روی PlayStation 5 و Xbox Series X|S تأیید کرده است. نسخه PC در زمان لانچ نیست و طبق روال استودیو معمولاً دیرتر می‌آید.

## پیش‌خرید و پیش‌بارگذاری

- پیش‌خرید رسمی از ۲۵ ژوئن ۲۰۲۶ شروع شد.
- نسخه دیجیتال از **۱۲ نوامبر** قابل پیش‌بارگذاری است تا در لحظه لانچ بازی آماده باشد.
- نسخه فیزیکی فروشگاهی در این نسل اغلب شامل **کد دانلود داخل جعبه** است، نه دیسک حاوی کل بازی.

## قیمت جهانی

قیمت نسخه استاندارد در بازار جهانی **۷۹٫۹۹ دلار** اعلام شده. قیمت ریالی در فروشگاه وی‌سوت بر اساس نرخ تأمین و موجودی به‌روز می‌شود.

> این صفحه فقط جمع‌بندی خبرهای رسمی است؛ شایعه را در بخش جدا می‌گذاریم.`,
    },
    {
      id: 'post_editions', type: 'news', category_id: 'cat_official', featured: 1,
      slug: 'standard-vs-ultimate-edition',
      title: 'تفاوت نسخه استاندارد و Ultimate؛ پک Vintage Vice City',
      excerpt: 'نسخه Ultimate محتوای اضافه دارد و پیش‌خریدها پک Vintage Vice City می‌گیرند.',
      cover: '/img/cover-edition.svg',
      body: `## نسخه استاندارد

داستان کامل تک‌نفره در ایالت لئونیدا، با جیسُن و لوسیا.

## نسخه Ultimate

راک‌استار برای Ultimate به لباس، مدل مو، مأموریت، وسیله، سلاح و مکان‌های اضافه اشاره کرده. اگر اهل کلکسیون هستید این نسخه منطقی‌تر است؛ اگر فقط داستان می‌خواهید استاندارد کافی است.

## پک پیش‌خرید Vintage Vice City

پیش‌خریدها پکی با الهام از Vice City ۲۰۰۲ و تامی ورسِتی می‌گیرند.

## نکته مصرف‌کننده

راک‌استار گفته در لانچ **ریزتراکنش** داخل این نسخه‌ها نیست. نسخه فیزیکی بدون دیسک کامل، مالکیت فایل را محدود می‌کند؛ قبل از خرید جعبه را بخوانید.`,
    },
    {
      id: 'post_gameplay', type: 'news', category_id: 'cat_trailer', featured: 1,
      slug: 'what-we-know-after-trailers',
      title: 'بعد از تریلرها چه چیزی از گیم‌پلی مشخص شد؟',
      excerpt: 'دنیای باز لئونیدا، دو قهرمان قابل بازی، فعالیت‌های آزاد و سیستم اعتبار جنایی.',
      cover: '/img/cover-gameplay.svg',
      body: `## دو قهرمان

می‌توانید کنترل را بین **جیسُن دووال** و **لوسیا کامینوس** عوض کنید، جدا فعالیت کنید یا با هم بمانید.

## دنیای آزاد

علاوه بر درگیری و تعقیب، فعالیت‌هایی مثل غواصی، ورزش و پرش آزاد دیده شده. وزن و خواب شخصیت روی ظاهر تأثیر می‌گذارد.

## پروفایل جنایی

سیستمی شبیه اعتبار در Red Dead، رفتار حرفه‌ای یا خشن را برای هر شخصیت جدا ثبت می‌کند.

## نرخ فریم لانچ

راک‌استار در اوت ۲۰۲۶ تأیید کرد بازی روی هر دو کنسول در لانچ با **۳۰ فریم** اجرا می‌شود.`,
    },
    {
      id: 'post_pc', type: 'rumor', category_id: 'cat_rumor', featured: 1,
      slug: 'pc-version-timing-rumor',
      title: 'شایعه: نسخه PC شاید ۲۰۲۷ بیاید',
      excerpt: 'هیچ تاریخ رسمی برای PC نیست. تحلیل‌ها معمولاً فاصله یک‌ساله بعد از کنسول را تکرار می‌کنند.',
      cover: '/img/cover-rumor.svg',
      body: `## وضعیت رسمی

تا امروز راک‌استار تاریخ PC نداده. لانچ فقط PS5 و Xbox Series است.

## چرا شایعه ۲۰۲۷؟

الگوی GTA V و RDR2 این بوده که PC بعد از کنسول می‌آید. **این تأیید نیست.**

## توصیه وی‌سوت

اگر فقط PC دارید، پول پیش‌خرید کنسول ندهید مگر اینکه واقعاً کنسول می‌خرید.`,
    },
    {
      id: 'post_online', type: 'rumor', category_id: 'cat_rumor', featured: 0,
      slug: 'gta-online-next-gen-rumor',
      title: 'شایعه حالت آنلاین نسل بعد؛ هنوز تاریخ ندارد',
      excerpt: 'گزارش‌های قدیمی از آنلاین بزرگ حرف زده‌اند اما راک‌استار جزئیات لانچ آنلاین VI را رسمی نکرده.',
      cover: '/img/cover-rumor.svg',
      body: `## آنچه رسمی است

تمرکز بازاریابی فعلی روی **داستان تک‌نفره** است.

## آنچه شایعه است

از سال‌ها پیش گزارش‌هایی درباره آنلاین بزرگ‌تر از GTA Online وجود داشته. هیچ زمان عرضه تأیید نشده.

اگر سایتی «مود آنلاین رایگان لانچ» فروخت، احتمالاً کلاهبرداری است.`,
    },
    {
      id: 'post_lucia_jason', type: 'guide', category_id: 'cat_chars', featured: 1,
      slug: 'lucia-and-jason',
      title: 'لوسیا و جیسُن: زوج داستان لئونیدا',
      excerpt: 'معرفی دو قهرمان اصلی بدون اسپویل پایان؛ الهام از داستان‌های زوج جنایتکار.',
      cover: '/img/cover-guide.svg',
      body: `## لوسیا کامینوس

اولین قهرمان زن غیرقابل‌حذف سری اصلی. بعد از درگیری برای خانواده‌اش در لیبرتی سیتی به زندان لئونیدا می‌افتد.

## جیسُن دووال

سابقه ارتش دارد و در Keys برای قاچاقچی‌های محلی کار کرده.

## لحن داستان

راک‌استار این رابطه را شبیه زوج‌های فراری کلاسیک توصیف کرده؛ نه یک قهرمان تنها در شهر.`,
    },
    {
      id: 'post_map', type: 'guide', category_id: 'cat_map', featured: 1,
      slug: 'leonida-vice-city-map',
      title: 'نقشه لئونیدا: وایس سیتی، Keys و تالاب‌ها',
      excerpt: 'ایالت ساختگی بر اساس فلوریدا؛ وایس سیتی الهام‌گرفته از میامی است.',
      cover: '/img/cover-map.svg',
      body: `## ایالت لئونیدا

به‌جای یک شهر تکی، VI یک ایالت را نشان می‌دهد با:

- **Vice City** — نسخه داستانی میامی
- **Leonida Keys** — الهام از فلوریدا کیز
- **Grassrivers** — تالاب و طبیعت
- شهرها و پارک‌هایی مثل Port Gellhorn و Mount Kalaga

## لحن فرهنگی

دنیا فرهنگ ۲۰۲۰ آمریکا را هجو می‌کند: اینفلوئنسر، شبکه اجتماعی و دوربین بدن پلیس.

نقشه لو‌رفته را منبع قطعی گیم‌پلی ندانید.`,
    },
    {
      id: 'post_platforms', type: 'guide', category_id: 'cat_plat', featured: 0,
      slug: 'platforms-editions-buyers-guide',
      title: 'راهنمای خرید: کنسول، نسخه، باندل',
      excerpt: 'PS5 یا Xbox؟ فیزیکی یا دیجیتال؟ باندل کنسول کی می‌صرفد؟',
      cover: '/img/cover-shop.svg',
      body: `## کنسول

بازی روی PS4 و Xbox One نیست. فقط نسل نهم.

## فیزیکی یا دیجیتال

جعبه فیزیکی این نسل ممکن است فقط کد دانلود باشد. اگر اینترنت ضعیف دارید، دیجیتال پیش‌بارگذاری‌شده گاهی مطمئن‌تر است.

## باندل

اگر کنسول ندارید، باندل PS5 یا Series X همراه بازی معمولاً از خرید جدا به‌صرفه‌تر است.

## مود و فایل غیرقانونی

وی‌سوت مود کرک، سیو لو رفته یا اکانت اشتراکی نمی‌فروشد. پک‌های جانبی فقط محتوای قانونی مثل پوستر و تم دسکتاپ است.`,
    },
  ]);
}

function products() {
  const now = new Date().toISOString();
  const items = [
    { id: 'prd_ps5_std', sku: 'GVS-PS5-STD', slug: 'gta-vi-ps5-standard-physical', title: 'GTA VI نسخه استاندارد PS5 — فیزیکی', kind: 'physical', category_id: 'cat_physical', price: 4590000, compare_at: 4990000, stock: 24, in_stock: 1, featured: 1, cover: '/img/product-physical.svg', excerpt: 'جعبه فیزیکی پلی‌استیشن ۵. محتوی جعبه را قبل از پرداخت بخوانید؛ بسیاری از نسخه‌ها کد دانلود دارند.', body: 'نسخه استاندارد داستان کامل برای PS5.\n\n- مناسب کسانی که قفسه کالکشن می‌خواهند\n- پیش‌بارگذاری معمولاً از ۱۲ نوامبر\n- گارانتی اصالت فاکتور فروشگاه' },
    { id: 'prd_xbox_std', sku: 'GVS-XSX-STD', slug: 'gta-vi-xbox-standard-physical', title: 'GTA VI نسخه استاندارد Xbox Series — فیزیکی', kind: 'physical', category_id: 'cat_physical', price: 4490000, compare_at: 4890000, stock: 18, in_stock: 1, featured: 1, cover: '/img/product-xbox.svg', excerpt: 'نسخه فیزیکی سری ایکس|اس. روی Xbox One اجرا نمی‌شود.', body: 'برای دارندگان Series X یا Series S.\n\nسازگاری عقب‌رو با Xbox One ندارد.' },
    { id: 'prd_ps5_ult', sku: 'GVS-PS5-ULT', slug: 'gta-vi-ps5-ultimate', title: 'GTA VI Ultimate Edition — PS5', kind: 'edition', category_id: 'cat_edition', price: 6290000, compare_at: 6790000, stock: 12, in_stock: 1, featured: 1, cover: '/img/product-ultimate.svg', excerpt: 'نسخه ویژه با محتوای اضافه اعلام‌شده راک‌استار. پیش‌خرید پک Vintage Vice City می‌آید.', body: 'Ultimate برای کسی است که لباس، مأموریت و وسیله اضافه می‌خواهد.' },
    { id: 'prd_digital', sku: 'GVS-DIG-STD', slug: 'gta-vi-digital-code', title: 'کد دیجیتال استاندارد (PS5 یا Xbox)', kind: 'digital', category_id: 'cat_digital', price: 4390000, compare_at: 0, stock: 40, in_stock: 1, featured: 1, cover: '/img/product-digital.svg', excerpt: 'ارسال کد پس از تسویه. ریجن و پلتفرم را در یادداشت سفارش مشخص کنید.', body: 'دیجیتال یعنی بدون جعبه. مناسب پیش‌بارگذاری.\n\nفروش اکانت اینجا انجام نمی‌شود.' },
    { id: 'prd_ps5_bundle', sku: 'GVS-BND-PS5', slug: 'ps5-slim-gta-vi-bundle', title: 'باندل PS5 Slim + GTA VI Ultimate', kind: 'bundle', category_id: 'cat_bundle', price: 38990000, compare_at: 41500000, stock: 6, in_stock: 1, featured: 1, cover: '/img/product-bundle-ps.svg', excerpt: 'کنسول اسلیم به‌همراه بازی. موجودی محدود؛ رنگ کنسول را در سفارش بنویسید.', body: 'اگر کنسول ندارید این باندل معمولاً به‌صرفه‌تر از خرید جداست.' },
    { id: 'prd_xbox_bundle', sku: 'GVS-BND-XSX', slug: 'xbox-series-x-gta-vi-bundle', title: 'باندل Xbox Series X + GTA VI', kind: 'bundle', category_id: 'cat_bundle', price: 37490000, compare_at: 39900000, stock: 4, in_stock: 1, featured: 0, cover: '/img/product-bundle-xbox.svg', excerpt: 'سری ایکس یک ترابایت به‌همراه نسخه استاندارد یا ارتقا به Ultimate با اختلاف قیمت.', body: 'Game Pass شامل GTA VI در روز لانچ نیست مگر مایکروسافت بعداً اعلام کند.' },
    { id: 'prd_steelbook', sku: 'GVS-COL-STL', slug: 'leonida-steelbook', title: 'استیل‌بوک کلکسیونی لئونیدا', kind: 'merch', category_id: 'cat_merch', price: 1290000, compare_at: 0, stock: 30, in_stock: 1, featured: 0, cover: '/img/product-steel.svg', excerpt: 'جعبه فلزی طرح غروب وایس سیتی — بازی داخلش نیست.', body: 'محصول جانبی کالکشن. بازی جداگانه است.' },
    { id: 'prd_map', sku: 'GVS-COL-MAP', slug: 'leonida-poster-map', title: 'پوستر نقشه لئونیدا (۵۰×۷۰)', kind: 'merch', category_id: 'cat_merch', price: 390000, compare_at: 490000, stock: 50, in_stock: 1, featured: 1, cover: '/img/product-map.svg', excerpt: 'چاپ هنری الهام‌گرفته از جغرافیا — غیررسمی و برای کلکسیون.', body: 'این نقشه رسمی راک‌استار نیست. طرح اختصاصی وی‌سوت برای فن‌آرت.' },
    { id: 'prd_figure', sku: 'GVS-COL-FIG', slug: 'lucia-jason-figure-set', title: 'فیگور ست لوسیا و جیسُن', kind: 'merch', category_id: 'cat_merch', price: 2190000, compare_at: 0, stock: 10, in_stock: 1, featured: 0, cover: '/img/product-figure.svg', excerpt: 'ست رومیزی رزین. محصول غیررسمی فن‌مید.', body: 'مجوز راک‌استار ندارد؛ کلکسیون شخصی.' },
    { id: 'prd_guide', sku: 'GVS-COL-BK', slug: 'persian-strategy-notebook', title: 'دفترچه راهنمای فارسی لئونیدا', kind: 'addon', category_id: 'cat_addon', price: 290000, compare_at: 0, stock: 80, in_stock: 1, featured: 0, cover: '/img/product-guide.svg', excerpt: 'چک‌لیست مأموریت، نقشه خالی برای یادداشت و واژه‌نامه فارسی.', body: 'چاپ دیجیتال وی‌سوت. اسپویل فصل آخر پشت جلد جداست.' },
    { id: 'prd_controller', sku: 'GVS-ACC-PAD', slug: 'vice-controller-skin', title: 'اسکین کنترلر طرح Vice Night', kind: 'accessory', category_id: 'cat_addon', price: 185000, compare_at: 0, stock: 60, in_stock: 1, featured: 0, cover: '/img/product-pad.svg', excerpt: 'برچسب وینیل DualSense / Xbox. کنسول را خط نمی‌اندازد.', body: 'در یادداشت سفارش مدل کنترلر را بنویسید.' },
    { id: 'prd_ui_pack', sku: 'GVS-ADD-UI', slug: 'desktop-icon-wallpaper-pack', title: 'پک والپیپر و آیکون دسکتاپ وی‌سوت', kind: 'addon', category_id: 'cat_addon', price: 89000, compare_at: 0, stock: 999, in_stock: 1, featured: 0, cover: '/img/product-pack.svg', excerpt: 'دانلود دیجیتال پس از پرداخت. مود داخل فایل بازی نیست؛ فقط تم دسکتاپ و موبایل.', body: 'فایل ZIP لینک دانلود. هیچ فایل اجرایی بازی یا سیو لو رفته‌ای داخل پک نیست.' },
  ];
  return items.map((p) => ({ ...p, status: 'published', seo_title: '', seo_description: p.excerpt, created_at: now, updated_at: now }));
}

function pages(now) {
  return [
    {
      id: 'page_about', slug: 'about', title: 'درباره وی‌سوت', in_nav: 1,
      seo_title: 'درباره gtavistore.ir', seo_description: 'وی‌سوت مرجع فارسی غیررسمی GTA VI است.',
      body: `## وی‌سوت چیست؟

**GTA VISOTE** روی دامنه gtavistore.ir سه کار می‌کند:

- پوشش اخبار رسمی جدا از شایعه
- راهنمای بازی، شخصیت و خرید
- فروش محصولات مرتبط: نسخه فیزیکی و دیجیتال، باندل کنسول، کالکشن و پک قانونی

## چرا بدون وردپرس و PHP؟

یک CMS سبک با Node.js مخصوص همین سایت است. پنل شبیه وردپرس است اما ساده‌تر: نوشته، محصول، سفارش، برگه، رسانه، تنظیمات.

## غیررسمی

وابسته به راک‌استار نیستیم. علائم تجاری متعلق به مالک قانونی است.`,
    },
    {
      id: 'page_contact', slug: 'contact', title: 'تماس', in_nav: 1,
      seo_title: '', seo_description: 'ارتباط با فروشگاه وی‌سوت',
      body: `## سفارش

بعد از ثبت سفارش با شماره موبایل‌تان تماس می‌گیریم.

برای پیگیری، کد سفارش را که با GVS شروع می‌شود بفرستید.`,
    },
    {
      id: 'page_faq', slug: 'faq', title: 'سوال‌های پرتکرار', in_nav: 1,
      seo_title: 'FAQ وی‌سوت', seo_description: 'تاریخ انتشار، PC، فیزیکی، باندل و مود',
      body: `## بازی کی می‌آید؟

۱۹ نوامبر ۲۰۲۶ روی PS5 و Xbox Series.

## PC دارد؟

رسمی هنوز نه.

## مود بازی می‌فروشید؟

فایل داخل بازی، کرک و بیلد لو رفته خیر. پک والپیپر و کالکشن بله.

## پرداخت چطور است؟

کارت‌به‌کارت یا پرداخت در محل برای کالاهای فیزیکی.`,
    },
    {
      id: 'page_terms', slug: 'terms', title: 'قوانین خرید', in_nav: 0,
      seo_title: '', seo_description: 'شرایط فروش وی‌سوت',
      body: `## اصالت

کالاهای دارای لایسنس ناشر فقط از مسیر مجاز تأمین می‌شود.

## انصراف

کد دیجیتال پس از ارسال قابل لغو نیست. کالای فیزیکی مهرنشده طبق قانون حمایت از مصرف‌کننده.

## محتوا

سایت خبر و شایعه را برچسب جدا می‌زند. شایعه را خرید قطعی نکنید.`,
    },
  ].map((p) => stamp(p, now));
}

module.exports = { run };
