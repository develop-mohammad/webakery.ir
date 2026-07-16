<?php
/**
 * تنظیمات license-server
 * این فایل را ویرایش کنید و اطلاعات دیتابیس MySQL خود را وارد کنید.
 */

// ─── دیتابیس MySQL ──────────────────────────────────────────────
define( 'LS_DB_HOST', 'localhost' );
define( 'LS_DB_NAME', 'YOUR_DB_NAME' );   // نام دیتابیس MySQL
define( 'LS_DB_USER', 'YOUR_DB_USER' );   // نام کاربری دیتابیس
define( 'LS_DB_PASS', 'YOUR_DB_PASS' );   // رمز عبور دیتابیس

// ─── تنظیمات پرداخت زیبال ───────────────────────────────────────
define( 'ZIBAL_MERCHANT', 'YOUR_ZIBAL_MERCHANT' );

// ─── قیمت جداگانه برای هر محصول (به ریال) — این «قیمت اصلی/همیشگی» است ────
define( 'LS_PRICES', [
    'wccp'          => 1990000,   // BAGET — ۱۹۹,۰۰۰ تومان
    'access-levels' => 999990,    // Barbari — ۹۹,۹۹۹ تومان
    'sokhte-jet'    => 0,         // TODO: قیمت واقعی سوخت جت رو قبل از انتشار اینجا بذارید (به ریال)
    'hesabdar'      => 7990000,   // حسابدار — ۷۹۹,۰۰۰ تومان (قیمتِ همیشگی، بعد از پایانِ تخفیف)
] );

// ─── تخفیفِ زمان‌دار — تا لحظه‌ی «until» با قیمتِ «price» فروخته می‌شود،
// بعدش خودکار (بدون نیاز به هیچ کاری) قیمت به LS_PRICES برمی‌گردد ───────
define( 'LS_PROMOS', [
    'hesabdar' => [
        'price' => 4990000,      // ۴۹۹,۰۰۰ تومان
        'until' => 1783561994,   // 2026-07-09 01:53 UTC — دقیقاً ۷۲ ساعت از لحظه‌ی ساختِ این تخفیف
    ],
] );

// ─── نام نمایشی محصولات (در صفحه پرداخت و پورتال) ───────────────
define( 'LS_PLUGIN_LABELS', [
    'wccp'          => 'baget ادیت فیلدهای پرداخت',
    'access-levels' => 'Barbari — مدیریت دسترسی کاربران',
    'sokhte-jet'    => 'Sokhte Jet — تحلیل و بهینه‌سازی عملکرد',
    'hesabdar'      => 'Hesabdar — پرتال حسابدار',
] );

// ─── توضیح کوتاه + آیکون (صفحه پرداخت — سایر محصولات) ───────────
define( 'LS_PLUGIN_META', [
    'wccp' => [
        'icon' => '🛒',
        'desc' => 'ویرایش و سفارشی‌سازی فیلدهای صفحه پرداخت ووکامرس',
    ],
    'access-levels' => [
        'icon' => '🔐',
        'desc' => 'کنترل دسترسی کاربران به افزونه‌ها و بخش‌های وردپرس',
    ],
    'sokhte-jet' => [
        'icon' => '⚡',
        'desc' => 'تحلیل سرعت سایت و پیشنهاد بهینه‌سازی عملکرد',
    ],
    'hesabdar' => [
        'icon' => '📊',
        'desc' => 'پرتال حسابدار، فاکتور و گزارش‌گیری برای فروشگاه',
    ],
] );

// ─── به‌روزرسانی خودکار افزونه‌ها ───────────────────────────────
// نسخهٔ سرور باید از نسخهٔ نصب‌شده روی سایت مشتری بالاتر باشد تا در «افزونه‌ها» آپدیت نمایش داده شود.
define( 'LS_UPDATES', [
    'wccp' => [
        'version'      => '1.3.3',
        'package'      => 'https://webakery.ir/license-server/updates/wccp.zip',
        'requires'     => '5.8',
        'tested'       => '6.6',
        'requires_php' => '7.4',
        'changelog'    => 'نسخه ۱.۳.۳: تست سیستم آپدیت خودکار.',
    ],
    'access-levels' => [
        'version'      => '1.5.8',
        'package'      => 'https://webakery.ir/license-server/updates/access-levels.zip',
        'requires'     => '5.0',
        'tested'       => '6.6',
        'requires_php' => '7.4',
        'changelog'    => 'نسخه ۱.۵.۸: اصلاح قیمت، رفع باگ کلیک دسترسی افزونه‌ها، جدول کاربران جمع‌وجورتر.',
    ],
    'sokhte-jet' => [
        'version'      => '1.0.0',
        'package'      => 'https://webakery.ir/license-server/updates/sokhte-jet.zip',
        'requires'     => '5.0',
        'tested'       => '6.6',
        'requires_php' => '7.4',
        'changelog'    => 'نسخه اولیه.',
    ],
    'hesabdar' => [
        'version'      => '1.9.1',
        'package'      => 'https://webakery.ir/license-server/updates/hesabdar.zip',
        'requires'     => '5.8',
        'tested'       => '6.6',
        'requires_php' => '7.4',
        'changelog'    => 'نسخه ۱.۹.۱: کارهای دسته‌جمعی تغییر وضعیت، لیست سفارش با ستون محصول، ویرایش/ایجاد سفارش.',
    ],
] );

// ─── آدرس سرور ──────────────────────────────────────────────────
define( 'LS_BASE_URL', 'https://webakery.ir' );

// ─── کلید امنیتی API ─────────────────────────────────────────────
define( 'API_SECRET', 'change-this-to-a-random-secret' );

// ─── پنل ادمین (مقادیر پیش‌فرض / اولیه) ─────────────────────────
// بعد از اولین تغییر از مسیر /license-server/admin/?tab=account
// یوزرنیم و پسورد در data/admin-auth.json ذخیره می‌شود و این دو مقدار
// فقط به‌عنوان fallback استفاده می‌شوند — بقیه تنظیمات این فایل دست‌نخورده می‌ماند.
define( 'ADMIN_USER', 'admin' );
define( 'ADMIN_PASS', 'change-this-password' );

// ─── Google OAuth — پورتال مشتری ────────────────────────────────
// مقادیر واقعی را فقط روی سرور در این فایل بگذارید (در گیت placeholder است).
define( 'GOOGLE_CLIENT_ID',     'YOUR_GOOGLE_CLIENT_ID' );
define( 'GOOGLE_CLIENT_SECRET', 'YOUR_GOOGLE_CLIENT_SECRET' );
define( 'GOOGLE_REDIRECT_URI',  'https://webakery.ir/license-server/portal/google-callback.php' );
