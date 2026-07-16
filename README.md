# webakery.ir

پلاگین وردپرس **Webakery** برای مدیریت محصولات نانوایی و شیرینی‌فروشی.

## دانلود پلاگین

### روش ۱ — فایل ZIP آماده
از همین ریپو فایل زیر را دانلود کنید:

- [`webakery-plugin.zip`](./webakery-plugin.zip)

لینک مستقیم GitHub (شاخه فعلی):

https://github.com/develop-mohammad/webakery.ir/raw/cursor/webakery-wordpress-plugin-45a5/webakery-plugin.zip

### روش ۲ — دانلود کل پروژه از GitHub
1. بروید به: https://github.com/develop-mohammad/webakery.ir
2. دکمه سبز **Code**
3. **Download ZIP**

## نصب

1. فایل `webakery-plugin.zip` را از حالت فشرده خارج کنید.
2. پوشه `webakery-plugin` را در `wp-content/plugins/` کپی کنید.
3. در پیشخوان وردپرس، افزونه **Webakery** را فعال کنید.
4. از منوی **Webakery** محصولات را اضافه کنید و تنظیمات فروشگاه را تکمیل کنید.

> نکته: دکمه‌های «ذخیره/دانلود روی لپ‌تاپ» داخل **فرم سفارش سایت** هستند (بعد از نصب پلاگین و گذاشتن شورت‌کد `[webakery_order]`). آن دکمه‌ها برای دانلود خودِ پلاگین نیستند.

## شورت‌کدها

| شورت‌کد | کاربرد |
| --- | --- |
| `[webakery_products]` | نمایش شبکه محصولات |
| `[webakery_products featured="1" limit="6"]` | محصولات ویژه |
| `[webakery_products category="bread" columns="3"]` | فیلتر بر اساس دسته |
| `[webakery_hours]` | ساعات کاری |
| `[webakery_info]` | نام، تلفن، واتساپ، آدرس |
| `[webakery_order]` | فرم ثبت سفارش |

## امکانات

- نوع نوشته سفارشی محصولات + دسته‌بندی
- قیمت، واحد، زمان آماده‌سازی، موجودی، محصول ویژه
- ذخیره سفارش‌ها در پیشخوان و ارسال ایمیل اعلان
- ذخیره پیش‌نویس فرم روی لپ‌تاپ/مرورگر (`localStorage`) + دانلود فایل JSON
- دانلود CSV سفارش‌ها از پیشخوان روی لپ‌تاپ
- استایل فرانت RTL و فارسی

## ساختار

```
webakery-plugin/
├── webakery.php
├── includes/
├── admin/
├── public/
└── uninstall.php
```
