=== Webakery ===
Contributors: webakery
Tags: bakery, products, orders, invoices, rtl, persian
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

کاتالوگ محصولات، ساعات کاری، فرم سفارش و فاکتور برای نانوایی و شیرینی‌فروشی.

== Description ==

Webakery یک پلاگین وردپرس برای مدیریت محصولات نانوایی/شیرینی‌فروشی است:

* نوع نوشته سفارشی محصولات
* دسته‌بندی محصولات
* فیلدهای قیمت، واحد، موجودی و محصول ویژه
* شورت‌کد نمایش محصولات
* شورت‌کد ساعات کاری و اطلاعات فروشگاه
* فرم ثبت سفارش با ذخیره در پیشخوان و ارسال ایمیل
* فاکتور در کنار سفارش‌ها برای مدیر و حسابدار
* نقش‌های «مدیر فروشگاه» و «حسابدار»
* رابط فارسی و RTL

== Installation ==

1. پوشه `webakery-plugin` را در مسیر `/wp-content/plugins/` آپلود کنید.
2. از منوی افزونه‌ها، Webakery را فعال کنید.
3. به منوی Webakery بروید و محصولات و تنظیمات را تکمیل کنید.
4. برای پنل حسابدار/مدیر، از کاربران وردپرس نقش «حسابدار» یا «مدیر فروشگاه» بدهید.
5. شورت‌کدها را در برگه‌ها قرار دهید.

== Shortcodes ==

* `[webakery_products]`
* `[webakery_products featured="1" limit="6" columns="3"]`
* `[webakery_products category="bread"]`
* `[webakery_hours]`
* `[webakery_info]`
* `[webakery_order]`

== Changelog ==

= 1.1.0 =
* فاکتور در کنار سفارش‌ها در منوی Webakery
* ایجاد فاکتور از سفارش، چاپ/PDF مرورگر، خروجی CSV
* نقش‌های مدیر فروشگاه و حسابدار + پنل فروشگاه

= 1.0.1 =
* ذخیره پیش‌نویس فرم سفارش روی لپ‌تاپ/مرورگر
* دانلود فایل پیش‌نویس JSON
* دانلود CSV سفارش‌ها از پیشخوان

= 1.0.0 =
* نسخه اولیه
