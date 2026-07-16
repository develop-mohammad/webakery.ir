# webakery.ir

دو پلاگین وردپرس در این ریپو:

1. **Webakery** — کاتالوگ محصولات و سفارش نانوایی
2. **Webakery Speed** — دریافت خطاهای Google PageSpeed و رفع امن آن‌ها

## دانلود پلاگین‌ها

### Webakery (محصولات و سفارش)
- [`webakery-plugin.zip`](./webakery-plugin.zip)

### Webakery Speed (PageSpeed)
- [`webakery-speed.zip`](./webakery-speed.zip)

### افزونه کروم Webakery Speed (تحلیل صفحه فعلی)
- [`webakery-speed-chrome.zip`](./webakery-speed-chrome.zip) — **فقط کروم، نه وردپرس**

## نصب افزونه کروم (مهم)

ZIP مستقیم نصب نمی‌شود. حتماً Extract کنید.

1. ZIP را Extract کنید
2. پوشه‌ای را پیدا کنید که **`manifest.json`** داخلش است
3. `chrome://extensions` → Developer mode → **Load unpacked**
4. همان پوشه را انتخاب کنید

ویندوز: بعد از Extract روی `INSTALL-WINDOWS.bat` دوبل‌کلیک کنید.

راهنما: [webakery-speed-chrome/INSTALL-FA.md](./webakery-speed-chrome/INSTALL-FA.md)

### روش جایگزین — دانلود کل پروژه
1. https://github.com/develop-mohammad/webakery.ir
2. **Code** → **Download ZIP**

## نصب Webakery

1. `webakery-plugin.zip` را باز کنید.
2. پوشه را در `wp-content/plugins/` بگذارید و فعال کنید.

## فاکتور سفارش‌ها

در پیشخوان → Webakery → **سفارش‌ها**:
- ستون **فاکتور** کنار هر سفارش: **مشاهده** و **دانلود**
- داخل صفحه سفارش هم دکمه‌های مشاهده/دانلود هست
- از صفحه فاکتور می‌توانید **چاپ / ذخیره PDF** بگیرید
- تنظیمات پیشوند و یادداشت فاکتور در **تنظیمات Webakery**

## نصب Webakery Speed (PageSpeed)

1. `webakery-speed.zip` را باز کنید.
2. پوشه `webakery-speed` را در `wp-content/plugins/` بگذارید و فعال کنید.
3. منوی **PageSpeed** در پیشخوان:
   - کلید API گوگل را وارد کنید ([Google Cloud](https://console.cloud.google.com/apis/library/pagespeedonline.googleapis.com))
   - **اسکن PageSpeed** را بزنید
   - **اعمال اصلاحات امن پیشنهادی** را بزنید
4. اگر سایت به‌هم خورد: **خاموش کردن همه اصلاحات** (یا غیرفعال کردن افزونه)

### چرا سایت خراب نمی‌شود؟
- اصلاحات فقط روی **فرانت‌اند** اعمال می‌شوند (نه پیشخوان)
- هر اصلاح **جداگانه** قابل خاموش/روشن است
- دکمه **خاموش کردن فوری** همه بهینه‌سازی‌ها را متوقف می‌کند
- غیرفعال کردن افزونه = برگشت کامل به حالت قبل

> نکته: دکمه‌های «ذخیره روی لپ‌تاپ» داخل فرم سفارش Webakery هستند، نه برای دانلود پلاگین.

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
- فاکتور هر سفارش: مشاهده، دانلود، چاپ/PDF
- ذخیره پیش‌نویس فرم روی لپ‌تاپ/مرورگر (`localStorage`) + دانلود فایل JSON
- دانلود CSV سفارش‌ها از پیشخوان روی لپ‌تاپ
- استایل فرانت RTL و فارسی

## ساختار

```
webakery-plugin/          # کاتالوگ و سفارش
webakery-speed/           # PageSpeed و بهینه‌سازی امن
webakery-speed-chrome/    # افزونه کروم مکمل
```
