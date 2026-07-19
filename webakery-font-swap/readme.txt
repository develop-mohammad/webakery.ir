=== فونت سوییپ | Font Swap ===
Contributors: webakery.ir
Requires at least: 5.8
Requires PHP: 7.4
Stable tag: 1.3.0
License: GPLv2 or later

بهینه‌سازی اجباری فونت‌ها: فقط woff2، حذف TTF/Google preload، font-display:swap برای IRANSansX.

== Description ==

* حالت اجباری: با روشن بودن افزونه همه بهینه‌سازی‌ها همیشه اعمال می‌شوند
* تشخیص فونت‌های تم و پلاگین‌ها + مسیر قطعی IRANSansX
* تزریق اجباری @font-face برای IRANSansX Regular/Bold با font-display:swap
* Preload اولویت با فونت متن (نه Font Awesome)
* بازنویسی CSS فونت محلی (enqueue + لینک ثابت HTML) با font-display:swap
* حذف preloadهای مضر و Google Fonts

== Changelog ==

= 1.3.0 =
* حالت اجباری: همه قابلیت‌ها همیشه ON وقتی افزونه فعال است
* پنل ادمین فقط کلید اصلی + حداکثر preload
* تزریق اجباری IRANSansX در head
* بازنویسی لینک‌های ثابت CSS فونت در HTML نهایی
* مارکر WBFS_FORCE_MODE=1 در View Source

= 1.2.0 =
* پشتیبانی اختصاصی IRANSansX (med-persian)
* تزریق font-display:swap داخل CSS لینک‌شده (تشخیص ابزارهایی مثل ShetabWP)
* اولویت preload با فونت متن فارسی؛ کاهش اولویت آیکون‌فونت‌ها

= 1.1.0 =
* فقط preload woff2، حذف Google/TTF preload

= 1.0.0 =
* انتشار اولیه
