# افزودن محصول nobat-man به license-server

وضعیت: در `license-server/config.php` ثبت شده است.

## مشخصات محصول

| فیلد | مقدار |
|------|--------|
| شناسه | `nobat-man` |
| قیمت | ۵۹۹,۰۰۰ تومان (۵۹۹۰۰۰۰ ریال) |
| نسخه فعلی | **1.0.3** |
| بسته آپدیت | `license-server/updates/nobat-man.zip` |
| URL بسته | `https://webakery.ir/license-server/updates/nobat-man.zip` |

## بخش‌های config.php

```php
// LS_PRICES
'nobat-man' => 5990000,

// LS_PLUGIN_LABELS
'nobat-man' => 'نوبت من — رزرو نوبت مشاوره',

// LS_PLUGIN_META
'nobat-man' => [
    'icon' => '📅',
    'desc' => 'رزرو نوبت مشاوره با تقویم شمسی، پرداخت و نسخه پرو',
],

// LS_UPDATES
'nobat-man' => [
    'version'      => '1.0.3',
    'package'      => 'https://webakery.ir/license-server/updates/nobat-man.zip',
    'requires'     => '5.8',
    'tested'       => '6.7',
    'requires_php' => '7.4',
    'changelog'    => 'نسخه ۱.۰.۳: بنر هیرو روشن و شیک‌تر؛ متن سفید خوانا.',
],
```

پس از آپلود روی سرور، فایل `config.php` و `updates/nobat-man.zip` را جایگزین کنید.
