# افزودن محصول webakery-seo-studio به license-server

وضعیت: محصول داخلی / لوکال است. در `license-server/config.php` هنوز قیمت تجاری ثبت نشده.

## مشخصات محصول

| فیلد | مقدار |
|------|--------|
| شناسه | `webakery-seo-studio` |
| نام | سئو استودیو \| Webakery SEO Studio |
| نسخه فعلی | **1.0.0** |
| مسیر | `webakery-seo-studio/` |
| ZIP | `webakery-seo-studio.zip` |
| لایسنس | فعلاً بدون قفل — برای گزارش سئوی خود سایت |

اگر بعداً فروخته شد، این بخش‌ها را به `config.php` اضافه کنید:

```php
// LS_PRICES — تومان × ۱۰ = ریال
'webakery-seo-studio' => 0, // TODO قیمت

// LS_PLUGIN_LABELS
'webakery-seo-studio' => 'سئو استودیو — گزارش مصور سئو',

// LS_PLUGIN_META
'webakery-seo-studio' => [
    'icon' => '📈',
    'desc' => 'داشبورد لوکال رتبه، کیورد، محتوا، تکنیکال، بک‌لینک و رپورتاژ',
],

// LS_UPDATES
'webakery-seo-studio' => [
    'version'      => '1.0.0',
    'package'      => 'https://webakery.ir/license-server/updates/webakery-seo-studio.zip',
    'requires'     => '5.8',
    'tested'       => '6.7',
    'requires_php' => '7.4',
    'changelog'    => 'نسخه اولیه: گزارش مصور سئو به‌صورت لوکال.',
],
```
