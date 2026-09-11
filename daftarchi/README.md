# دفترچی

حسابداری دسکتاپ برای فروشگاه‌های کوچک ایرانی — سازنده: webakery.ir

## نصب روی ویندوز

از GitHub Actions (workflow `daftarchi-windows`) فایل `Daftarchi-Windows` را دانلود کن: یا ZIP را باز کن و `Daftarchi.exe` را اجرا کن، یا نصب‌کنندهٔ `Daftarchi-Setup-1.0.0.exe` را بزن.

اگر ویندوز SmartScreen نشان داد: **More info** → **Run anyway**.

کل پوشه را با هم نگه دار (dllها و `resources` کنار exe لازم‌اند).

دیتابیس بعد از اجرا اینجا ذخیره می‌شود:

`%APPDATA%\Daftarchi\daftarchi.sqlite`

بکاپ خودکار در `%APPDATA%\Daftarchi\backups`

نصب‌کنندهٔ NSIS (`.exe`) روی خود ویندوز با `npm run build:win -- --nsis` ساخته می‌شود. از لینوکس فقط ZIP تولید می‌شود.

## ساخت از روی سورس

```bash
cd daftarchi
npm install
npm run build:win
```

خروجی در پوشهٔ `release/` است.

## اتصال به سایت و دریافت کالاها

1. در ووکامرس: تنظیمات ← پیشرفته ← REST API ← کلید جدید (خواندن/نوشتن).
2. در دفترچی منوی «ووکامرس»: آدرس سایت، Consumer Key و Secret را بگذار.
3. «تست اتصال» بزن؛ بعد «دریافت همه کالاهای سایت».

کالاهای ساده، متغیر (هر تنوع جدا)، بدون SKU، پیش‌نویس و خصوصی وارد می‌شوند. قیمت فروش بعد از اولین دریافت مال دفترچی است؛ قیمت خرید فقط محلی است.
