# دفترچی

حسابداری دسکتاپ برای فروشگاه‌های کوچک ایرانی — سازنده: webakery.ir

## نصب روی ویندوز

فایل `Daftarchi-1.0.0-win-x64.zip` را دانلود و از حالت فشرده خارج کن، بعد `Daftarchi.exe` را اجرا کن.

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
