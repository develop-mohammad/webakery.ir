# افزودن محصول «انقضای کالا پرو» به سرور لایسنس

شناسه محصول (product slug): `webakery-expiry`

شماره‌گذاری کلید لایسنس (تولید خودکار در `LicenseManager::generate_key`):

`WEBAKE-XXXX-XXXX-XXXX-XXXX`

پیشوند از ۶ حرف اول شناسه بدون خط تیره ساخته می‌شود: `webakery-expiry` → `WEBAKE`.

## مشخصات محصول

| فیلد | مقدار |
|------|--------|
| شناسه | `webakery-expiry` |
| نام | انقضای کالا پرو |
| قیمت | ۸۰۰٬۰۰۰ تومان (۸٬۰۰۰٬۰۰۰ ریال) |
| نسخه فعلی | **1.0.8** |
| سازنده | webakery.ir — محمد حاجی مهدیخانی |
| بسته آپدیت | `license-server/updates/webakery-expiry-pro.zip` |
| URL بسته | `https://webakery.ir/license-server/updates/webakery-expiry-pro.zip` |
| دوره آزمایشی | ۳ روز |

## بخش‌های config.php

```php
// LS_PRICES (ریال)
'webakery-expiry' => 8000000, // انقضای کالا پرو — ۸۰۰٬۰۰۰ تومان

// LS_PLUGIN_LABELS
'webakery-expiry' => 'انقضای کالا پرو — بچ قیمت و انقضا',

// LS_PLUGIN_META
'webakery-expiry' => [
    'icon' => '📆',
    'desc' => 'قیمت رزرو، موجودی و تاریخ انقضا با سوییچ خودکار',
],

// LS_UPDATES
'webakery-expiry' => [
        'version'      => '1.0.8',
        'package'      => 'https://webakery.ir/license-server/updates/webakery-expiry-pro.zip',
        'requires'     => '5.8',
        'tested'       => '6.7',
        'requires_php' => '7.4',
        'changelog'    => 'نسخه ۱.۰.۸: خاموش کردن آسان تایمر تا پایان کمپین (تنظیمات یا خود محصول).',
],
```

در `license-server/includes/UpdateManager.php` نگاشت ZIP:

```php
'webakery-expiry' => 'webakery-expiry-pro.zip',
```

پس از آپلود روی سرور، فایل `config.php` و `updates/webakery-expiry-pro.zip` را جایگزین کنید.

نسخه رایگان بدون لایسنس در ریشه ریپو: `webakery-expiry.zip` — روی سرور لایسنس ثبت نمی‌شود.
