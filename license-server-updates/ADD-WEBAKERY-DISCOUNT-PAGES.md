# افزودن «صفحات تخفیف» به سرور لایسنس

شناسه محصول: `webakery-discount-pages`

## چرا فقط ZIP کافی نیست؟

لیست محصولات پنل ادمین / صفحه پرداخت از **`config.php`** خوانده می‌شود  
(`LS_PRICES` + `LS_PLUGIN_LABELS` + `LS_PLUGIN_META` + `LS_UPDATES`).  
گذاشتن فایل داخل `updates/` به‌تنهایی محصول را در لیست لایسنس‌ها نشان نمی‌دهد.

## ۱) آپلود ZIP

نام فایل باید دقیقاً این باشد:

`license-server/updates/webakery-discount-pages.zip`

(نه `webakery-discount-pages (6).zip`)

## ۲) ثبت در `config.php` روی سرور

این ورودی‌ها اضافه شده‌اند (قیمت پیش‌فرض ۲۹۹٬۰۰۰ تومان = ۲٬۹۹۰٬۰۰۰ ریال):

- `LS_PRICES['webakery-discount-pages']`
- `LS_PLUGIN_LABELS['webakery-discount-pages']`
- `LS_PLUGIN_META['webakery-discount-pages']`
- `LS_UPDATES['webakery-discount-pages']`

فایل به‌روزشده را از ریپو روی سرور کپی کنید، یا همان کلیدها را دستی در `config.php` سرور بگذارید.  
**مراقب باشید** رمز دیتابیس / درگاه / `ADMIN_*` واقعی سرور را با placeholder گیت جایگزین نکنید — فقط بلوک‌های محصول را ادغام کنید.

## ۳) بررسی

1. پنل ادمین → ساخت لایسنس دستی → در dropdown محصول باید «صفحات تخفیف» دیده شود  
2. صفحه پرداخت:  
   `https://webakery.ir/license-server/pay/?plugin=webakery-discount-pages`

## نکات

- اگر افزونه داخل خودش شناسه لایسنس دیگری دارد، باید با `webakery-discount-pages` یکی باشد.
- قیمت را در صورت نیاز در `LS_PRICES` عوض کنید.
