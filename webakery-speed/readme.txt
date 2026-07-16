=== Webakery Speed ===
Contributors: webakery
Tags: pagespeed, performance, lighthouse, optimization, speed
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.1
License: GPLv2 or later

دریافت خطاهای Google PageSpeed و اعمال اصلاحات امن بدون خراب کردن سایت.

== Description ==

* اسکن با Google PageSpeed Insights API
* وارد کردن گزارش JSON
* نمایش خطاها و اصلاح پیشنهادی
* اعمال اصلاحات امن (defer JS، lazy load، font-display و...)
* خاموش کردن فوری همه اصلاحات
* فقط روی فرانت‌اند اعمال می‌شود، نه پیشخوان

== Installation ==

1. پوشه `webakery-speed` را در `wp-content/plugins/` قرار دهید
2. افزونه را فعال کنید
3. منوی **PageSpeed** → کلید API را وارد کنید → **اسکن PageSpeed**
4. **اعمال اصلاحات امن پیشنهادی** را بزنید

== Frequently Asked Questions ==

= اگر سایت خراب شد؟ =
دکمه **خاموش کردن همه اصلاحات** را بزنید یا افزونه را غیرفعال کنید.

= کلید API از کجا؟ =
Google Cloud Console → PageSpeed Insights API

== Changelog ==

= 1.0.1 =
* Preload فونت‌های woff2/woff و CSS فونت Google
* فیلد دستی برای آدرس فونت‌های preload

= 1.0.0 =
* نسخه اولیه
