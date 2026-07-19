=== پنل سرعت | WebAkery Speed ===
Contributors: webakery.ir
Requires at least: 5.8
Requires PHP: 7.4
Stable tag: 1.2.0
License: GPLv2 or later

افزونه یکپارچه سرعت: اولویت‌های CWV، دریافت از گوگل، اصلاح خودکار، فونت سوییپ.

== Description ==

* پنل اولویت‌ها + اسکن HTML
* دریافت خودکار Core Web Vitals از PageSpeed Insights API (منبع فیلد دیتا = CrUX / مشابه Search Console)
* اصلاح خودکار موارد امن: ابعاد تصویر، lazy، LCP priority، async CSS آیکون
* فونت سوییپ اجباری
* بافر سازگار با WP Rocket (rocket_buffer)
* بررسی زنده وضعیت اعمال + هشدار 404 بودن IRANSansX

== Changelog ==

= 1.2.0 =
* rocket_buffer + بافر واحد برای اعمال قطعی روی HTML کش‌شده
* بازنویسی Used CSS پرف‌مترز با font-display:swap
* حذف preload آیکون‌فونت‌ها؛ فقط IRANSans
* هشدار و Health Check وقتی فایل فونت روی سرور 404 است

= 1.1.0 =
* منوی گوگل / CWV با دریافت خودکار LCP/INP/CLS
* منوی اصلاح خودکار بر اساس اسکن
* مارکر WBS_AUTOFIX=1 در HTML

= 1.0.0 =
* ادغام Font Swap + Speed Board
