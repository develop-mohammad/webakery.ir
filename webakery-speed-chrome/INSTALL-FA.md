# راهنمای نصب افزونه کروم Webakery Speed

## مهم: ZIP را مستقیم نصب نکنید
کروم فایل `.zip` را مثل فایل `.crx` نصب نمی‌کند.
حتماً باید **Extract / باز کردن** کنید و بعد **Load unpacked** بزنید.

## مراحل نصب (گام‌به‌گام)

### ۱) دانلود و Extract
1. این فایل را دانلود کنید:
   https://github.com/develop-mohammad/webakery.ir/raw/cursor/webakery-wordpress-plugin-45a5/webakery-speed-chrome.zip
2. روی ZIP راست‌کلیک → **Extract All** / **باز کردن**
3. داخل پوشه بازشده باید فایل `manifest.json` را ببینید

### ۲) انتخاب پوشه درست
وقتی Extract می‌کنید معمولاً این ساختار می‌آید:

```
webakery-speed-chrome/
  manifest.json      ← این فایل باید همین‌جا باشد
  popup/
  background/
  icons/
  ...
```

در مرحله Load unpacked باید **همین پوشه** `webakery-speed-chrome` را انتخاب کنید.

اشتباه رایج:
- انتخاب پوشه بالاتر (که فقط ZIP داخلش است)
- انتخاب خود فایل ZIP

### ۳) Load unpacked در کروم
1. آدرس را بزنید: `chrome://extensions`
2. بالا سمت راست: **Developer mode** را روشن کنید
3. دکمه **Load unpacked** / **بارگذاری افزونه بدون بسته‌بندی**
4. پوشه `webakery-speed-chrome` را انتخاب کنید
5. باید افزونه **Webakery Speed Analyzer** ظاهر شود

## اگر خطا داد

| پیام خطا | راه‌حل |
|---|---|
| Could not load manifest | پوشه اشتباه است؛ پوشه‌ای را انتخاب کنید که `manifest.json` داخلش باشد |
| _locales missing | نسخه جدید ZIP را دوباره دانلود کنید (v1.0.1+) |
| Manifest file is missing | ZIP را Extract نکرده‌اید |
| پوشه خالی است | یک سطح پایین‌تر بروید تا `manifest.json` را ببینید |

## تست بعد از نصب
1. یک صفحه سایت باز کنید (مثلاً صفحه اصلی سایت خودتان)
2. آیکن افزونه را بزنید
3. **تحلیل این صفحه** را بزنید

## Edge (اختیاری)
در `edge://extensions` همان مراحل بالا را انجام دهید.
