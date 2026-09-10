# افزودن محصول «قرارداد نهال» به سرور لایسنس

شناسه محصول: `nahal-cowork`

## مشخصات

| فیلد | مقدار |
|------|--------|
| product | `nahal-cowork` |
| name | قرارداد نهال \| فضای کار، سالن و پذیرش |
| price | ۳۹۹,۰۰۰ تومان (۳۹۹۰۰۰۰ ریال) |
| version | 1.2.0 |
| zip | `nahal-cowork.zip` |

## بخش‌های config.php

```php
// LS_PRICES
'nahal-cowork' => 3990000,

// LS_PLUGIN_LABELS
'nahal-cowork' => 'قرارداد نهال — فضای کار، سالن و پذیرش',

// LS_PLUGIN_META
'nahal-cowork' => [
    'icon' => '🌿',
    'desc' => 'قرارداد فضای کار، اجاره سالن، پذیرش فراگیر و فرم‌ساز با ثبت در حسابدار',
],

// LS_UPDATES
'nahal-cowork' => [
    'version'      => '1.2.0',
    'package'      => 'https://webakery.ir/license-server/updates/nahal-cowork.zip',
    'requires'     => '5.8',
    'tested'       => '6.7',
    'requires_php' => '7.4',
    'changelog'    => 'نسخه ۱.۲.۰: فرم‌ساز سفارشی + ثبت پرداخت در ووکامرس و حسابدار.',
],
```

فایل `nahal-cowork.zip` ریشه ریپو را در پوشه `updates/` سرور بگذارید.
صفحه بازگشت: `admin.php?page=nahal-cowork&tab=license`
دوره آزمایشی: ۷ روز.
