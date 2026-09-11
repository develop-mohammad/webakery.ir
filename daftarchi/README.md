# دفترچی

حسابداری دسکتاپ برای فروشگاه‌های کوچک ایرانی — سازنده: webakery.ir

## نصب روی ویندوز

1. فایل `Daftarchi-Setup-1.0.0.exe` را اجرا کن.
2. مسیر نصب را انتخاب کن (پیشنهاد: پوشهٔ کاربر، نه Program Files اگر آنتی‌ویروس سخت‌گیر است).
3. میانبر «دفترچی» روی میزکار ساخته می‌شود.

نسخهٔ بدون نصب: `Daftarchi-Portable-1.0.0.exe` را هر جا کپی کن و اجرا کن.

دیتابیس بعد از نصب اینجا ذخیره می‌شود:

`%APPDATA%\Daftarchi\daftarchi.sqlite`

بکاپ خودکار در `%APPDATA%\Daftarchi\backups`

## ساخت از روی سورس

```bash
cd daftarchi
npm install
npm run build:win
```

خروجی در پوشهٔ `release/` است.
