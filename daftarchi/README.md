# دفترچی

حسابداری دسکتاپ برای فروشگاه‌های کوچک ایرانی — سازنده: webakery.ir

## نصب روی ویندوز

ویندوز ۱۰ یا ۱۱ **۶۴بیتی** لازم است.

دانلود از صفحهٔ انتشار:

https://github.com/develop-mohammad/webakery.ir/releases/tag/daftarchi-windows

- `Daftarchi-Portable-1.0.0.exe` — بدون نصب، دوبار کلیک
- `Daftarchi-Setup-1.0.0.exe` — نصب یک‌کلیکی + میانبر میزکار
- `Daftarchi-1.0.0-win-x64.zip` — استخراج کن، بعد `Install-Daftarchi.cmd`

اگر SmartScreen آمد: **More info** → **Run anyway**.

دیتابیس: `%APPDATA%\Daftarchi\daftarchi.sqlite`

## ساخت از روی سورس

```bash
cd daftarchi
npm install
npm run build:win -- --nsis
```

خروجی در پوشهٔ `release/` است.

## اتصال به سایت و دریافت کالاها

1. در ووکامرس: تنظیمات ← پیشرفته ← REST API ← کلید جدید (خواندن/نوشتن).
2. در دفترچی منوی «ووکامرس»: آدرس سایت، Consumer Key و Secret را بگذار.
3. «تست اتصال» بزن؛ بعد «دریافت همه کالاهای سایت».

کالاهای ساده، متغیر (هر تنوع جدا)، بدون SKU، پیش‌نویس و خصوصی وارد می‌شوند. قیمت فروش بعد از اولین دریافت مال دفترچی است؛ قیمت خرید فقط محلی است.
