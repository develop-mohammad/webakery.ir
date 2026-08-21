# افزودن «صفحات تخفیف» بدون از دست دادن لایسنس‌های قبلی

شناسه: `webakery-discount-pages`

## مهم

| چه چیزی | کجا ذخیره می‌شود | با این تغییر چه می‌شود |
|---------|------------------|-------------------------|
| لایسنس‌های صادرشده | `license-server/data/licenses.json` | **دست نخورده می‌ماند** |
| لیست محصولات / قیمت | `license-server/config.php` | فقط یک محصول **اضافه** می‌شود |
| فایل ZIP آپدیت | `license-server/updates/*.zip` | فقط آپلود فایل |

**هرگز** کل `config.php` سرور را با فایل گیت جایگزین نکنید  
(رمز دیتابیس / درگاه / ادمین سرور از بین می‌رود).  
**هرگز** `data/licenses.json` را پاک یا جایگزین نکنید.

---

## کار روی سرور (امن)

### ۱) ZIP
نام دقیق:

`license-server/updates/webakery-discount-pages.zip`

### ۲) فقط این خطوط را به `config.php` فعلی سرور اضافه کنید

داخل آرایه `LS_PRICES` (کنار بقیه محصولات):

```php
'webakery-discount-pages'  => 2990000,   // صفحات تخفیف — ۲۹۹,۰۰۰ تومان
```

داخل آرایه `LS_PLUGIN_LABELS`:

```php
'webakery-discount-pages'  => 'صفحات تخفیف — ساخت صفحه تخفیف ووکامرس',
```

داخل آرایه `LS_PLUGIN_META`:

```php
'webakery-discount-pages' => [
    'icon' => '🏷️',
    'desc' => 'ساخت و مدیریت صفحات تخفیف برای فروشگاه ووکامرس',
],
```

داخل آرایه `LS_UPDATES`:

```php
'webakery-discount-pages' => [
    'version'      => '1.0.0',
    'package'      => 'https://webakery.ir/license-server/updates/webakery-discount-pages.zip',
    'requires'     => '5.8',
    'tested'       => '6.7',
    'requires_php' => '7.4',
    'changelog'    => 'نسخه ۱.۰.۰: انتشار اولیه صفحات تخفیف.',
],
```

### ۳) بررسی
- پنل ادمین → ساخت لایسنس → باید «صفحات تخفیف» در dropdown باشد
- لایسنس‌های قبلی در همان لیست لایسنس‌ها باقی می‌مانند

قیمت پیش‌فرض: ۲۹۹٬۰۰۰ تومان (۲٬۹۹۰٬۰۰۰ ریال) — در صورت نیاز همان یک عدد را عوض کنید.
