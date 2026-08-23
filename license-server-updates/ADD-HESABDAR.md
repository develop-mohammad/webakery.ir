# افزودن/آپدیت محصول hesabdar در license-server

## علت نیامدن دکمهٔ آپدیت در «افزونه‌ها»

وردپرس فقط وقتی دکمه را نشان می‌دهد که **نسخهٔ اعلام‌شده در سرور لایسنس** از نسخهٔ نصب‌شده روی سایت بالاتر باشد.

| مورد | مقدار |
|------|--------|
| شناسه محصول | `hesabdar` |
| فایل ZIP | `license-server/updates/hesabdar.zip` |
| URL بسته | `https://webakery.ir/license-server/updates/hesabdar.zip` |
| نسخه در `LS_UPDATES` | باید با نسخه داخل ZIP یکی باشد (مثلاً **1.10.0**) |

### کار لازم روی سرور زنده (webakery.ir)

1. فایل `hesabdar.zip` را در پوشهٔ `license-server/updates/` جایگزین کنید.
2. در `license-server/config.php` بخش `LS_UPDATES['hesabdar']['version']` را به همان نسخه ZIP برسانید (مثلاً `1.10.0`).
3. روی سایت مشتری کش را خالی کنید:
   - پیشخوان → به‌روزرسانی‌ها → «دوباره بررسی کن»، یا
   - حذف transient با نام `wbl_upd_hesabdar` (کش ۶ ساعته کلاینت لایسنس).

اگر ZIP آپدیت شده ولی `version` در config قدیمی بماند، `update_available` برابر `false` می‌شود و دکمه ظاهر نمی‌شود.

## نمونهٔ LS_UPDATES

```php
'hesabdar' => [
    'version'      => '1.10.0',
    'package'      => 'https://webakery.ir/license-server/updates/hesabdar.zip',
    'requires'     => '5.8',
    'tested'       => '6.7',
    'requires_php' => '7.4',
    'changelog'    => 'نسخه ۱.۱۰.۰: فیلتر وضعیت/دسته‌بندی در فروش محصولات + گزارش خریداران چندمحصولی.',
],
```
