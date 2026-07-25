<?php
/**
 * تنظیمات license-server — نسخه اصلاح‌شده (برای جایگزینی روی هاست)
 * لایسنس‌های قبلی در data/licenses.json هستند و با این فایل پاک نمی‌شوند.
 */

// ─── دیتابیس MySQL ──────────────────────────────────────────────
define( 'LS_DB_HOST', 'localhost' );
define( 'LS_DB_NAME', 'YOUR_DB_NAME' );
define( 'LS_DB_USER', 'YOUR_DB_USER' );
define( 'LS_DB_PASS', 'YOUR_DB_PASS' );

// ─── تنظیمات پرداخت زیبال ───────────────────────────────────────
define( 'ZIBAL_MERCHANT', '6a331116da557b902563c32f' );

// ─── قیمت جداگانه برای هر محصول (به ریال) ────
define( 'LS_PRICES', [
    'wccp'          => 1990000,
    'access-levels' => 999990,
    'sokhte-jet'    => 0,
    'hesabdar'      => 7990000,
    'nobat-man'     => 5990000,
    'webakery-chat' => 1500000,
] );

// ─── پلن‌های چت باکس (ماهانه / ۳ ماهه / دائمی) ───
define( 'LS_PLANS', [
    'webakery-chat' => [
        '1m' => [
            'months' => 1,
            'price'  => 1500000,
            'label'  => 'ماهانه',
            'hint'   => '۱ ماه دسترسی + آپدیت و پشتیبانی',
        ],
        '3m' => [
            'months' => 3,
            'price'  => 3500000,
            'label'  => '۳ ماهه',
            'hint'   => '۳ ماه — به‌صرفه‌تر از ۳× ماهانه',
            'badge'  => 'پیشنهادی',
        ],
        'life' => [
            'months' => 0,
            'price'  => 7990000,
            'label'  => 'دائمی',
            'hint'   => 'مادام‌العمر — یک‌بار پرداخت',
        ],
    ],
] );

define( 'LS_PROMOS', [
] );

define( 'LS_PLUGIN_LABELS', [
    'wccp'          => 'baget ادیت فیلدهای پرداخت',
    'access-levels' => 'Barbari — مدیریت دسترسی کاربران',
    'sokhte-jet'    => 'Sokhte Jet — تحلیل و بهینه‌سازی عملکرد',
    'hesabdar'      => 'Hesabdar — پرتال حسابدار',
    'nobat-man'     => 'نوبت من — رزرو نوبت مشاوره',
    'webakery-chat' => 'چت باکس — پشتیبانی آنلاین سایت',
] );

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
    'nobat-man' => [
        'icon' => '📅',
        'desc' => 'رزرو نوبت مشاوره با تقویم شمسی، پرداخت و نسخه پرو',
    ],
    'webakery-chat' => [
        'icon' => '💬',
        'desc' => 'چت باکس — ماهانه، ۳ ماهه یا دائمی + اعلان تلگرام/واتساپ',
    ],
] );

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
    'nobat-man' => [
        'version'      => '1.0.5',
        'package'      => 'https://webakery.ir/license-server/updates/nobat-man.zip',
        'requires'     => '5.8',
        'tested'       => '6.7',
        'requires_php' => '7.4',
        'changelog'    => 'نسخه ۱.۰.۴: بازه رزرو، ماه‌های فعال، درگاه زیبال، رفع تقویم.',
    ],
    'webakery-chat' => [
        'version'      => '1.4.2',
        'package'      => 'https://webakery.ir/license-server/updates/webakery-chat-box.zip',
        'requires'     => '5.8',
        'tested'       => '6.7',
        'requires_php' => '7.4',
        'changelog'    => 'نسخه ۱.۴.۲: قیمت ماهانه ۱۵۰، ۳ ماهه ۳۵۰ و دائمی ۷۹۹ هزار تومان.',
    ],
] );

define( 'LS_BASE_URL', 'https://webakery.ir' );

define( 'API_SECRET', 'change-this-to-a-random-secret' );

define( 'ADMIN_USER', 'admin' );
define( 'ADMIN_PASS', 'change-this-password' );

define( 'GOOGLE_CLIENT_ID',     'YOUR_GOOGLE_CLIENT_ID' );
define( 'GOOGLE_CLIENT_SECRET', 'YOUR_GOOGLE_CLIENT_SECRET' );
define( 'GOOGLE_REDIRECT_URI',  'https://webakery.ir/license-server/portal/google-callback.php' );
