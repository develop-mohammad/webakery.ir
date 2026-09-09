# افزودن محصول «کانال‌یار» به سرور لایسنس

شناسه محصول (product slug): `webakery-channel`

## ۱) ثبت محصول در `config.php` سرور لایسنس

در آرایه‌ها این ورودی‌ها را اضافه کنید:

```php
// LS_PRICES (ریال)
'webakery-channel' => 2490000, // ۲۴۹,۰۰۰ تومان

// LS_PLUGIN_LABELS
'webakery-channel' => 'کانال‌یار — ربات تلگرام کانال',

// LS_PLUGIN_META
'webakery-channel' => [
	'icon' => '📢',
	'desc' => 'ارسال مطلب به کانال تلگرام + ایده رشد از محتوای سایت',
],

// LS_UPDATES
'webakery-channel' => [
	'version'      => '1.0.0',
	'package'      => 'https://webakery.ir/license-server/updates/webakery-channel.zip',
	'requires'     => '5.8',
	'tested'       => '6.7',
	'requires_php' => '7.4',
	'changelog'    => '<h4>1.0.0</h4><ul><li>انتشار اولیه</li></ul>',
],
```

## ۲) بارگذاری فایل به‌روزرسانی

فایل `webakery-channel.zip` (از ریشه ریپو) را در پوشه `updates/`
سرور لایسنس بگذارید.

## ۳) بررسی

```
POST https://webakery.ir/license-server/api/?action=update
{ "product": "webakery-channel", "version": "0.9.0", "domain": "example.com" }
```

پاسخ باید `success: true` و `version: 1.0.0` داشته باشد.

## نکات

- کلاینت لایسنس داخل افزونه: `includes/class-wb-license.php`
- صفحه بازگشت: `admin.php?page=webakery-channel&tab=license`
- دوره آزمایشی: ۷ روز. پیش‌نمایش ایده بدون لایسنس کار می‌کند؛ ارسال به کانال قفل می‌شود.
