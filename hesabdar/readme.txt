=== Hesabdar ===
Contributors: webakery
Tags: accounting, orders, invoice, products, rtl, persian
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

افزونه حسابدار (Hesabdar) — مدیریت محصولات، سفارش‌ها و فاکتور.

== Description ==

Hesabdar (حسابدار) یک پلاگین وردپرس برای مدیریت فروش است:

* نوع نوشته سفارشی محصولات
* دسته‌بندی محصولات
* فیلدهای قیمت، واحد، موجودی و محصول ویژه
* شورت‌کد نمایش محصولات
* شورت‌کد ساعات کاری و اطلاعات فروشگاه
* فرم ثبت سفارش با ذخیره در پیشخوان و ارسال ایمیل
* فاکتور برای هر سفارش (مشاهده و دانلود)
* رابط فارسی و RTL

== Installation ==

1. پوشه `hesabdar` را در مسیر `/wp-content/plugins/` آپلود کنید.
2. از منوی افزونه‌ها، Hesabdar را فعال کنید.
3. به منوی «حسابدار» بروید و محصولات و تنظیمات را تکمیل کنید.
4. شورت‌کدها را در برگه‌ها قرار دهید.

== Shortcodes ==

* `[hesabdar_products]`
* `[hesabdar_products featured="1" limit="6" columns="3"]`
* `[hesabdar_products category="bread"]`
* `[hesabdar_hours]`
* `[hesabdar_info]`
* `[hesabdar_order]`

== Changelog ==

= 1.1.0 =
* تغییر نام افزونه به Hesabdar (حسابدار)
* ستون فاکتور در لیست سفارش‌ها با لینک مشاهده و دانلود
* صفحه فاکتور چاپی فارسی (RTL) با امکان چاپ/PDF
* تنظیمات پیشوند و یادداشت فاکتور
* ذخیره قیمت واحد هنگام ثبت سفارش برای مبلغ دقیق فاکتور

= 1.0.1 =
* ذخیره پیش‌نویس فرم سفارش روی لپ‌تاپ/مرورگر
* دانلود فایل پیش‌نویس JSON
* دانلود CSV سفارش‌ها از پیشخوان

= 1.0.0 =
* نسخه اولیه
