# نصب افزونه کروم — خیلی مهم

## این فایل برای Chrome است (نه وردپرس)
- `webakery-speed-chrome.zip` = افزونه **کروم**
- `webakery-speed.zip` = پلاگین **وردپرس**

## قبل از نصب چک کنید
در پوشه‌ای که انتخاب می‌کنید این فایل‌ها را ببینید:
- `manifest.json`  ← حتماً باید باشد
- پوشه `popup`
- پوشه `icons`

اگر `manifest.json` نیست = پوشه اشتباه است.

## روش نصب

### ویندوز
1. ZIP را Extract کنید
2. روی `INSTALL-WINDOWS.bat` دوبل‌کلیک کنید
3. در Chrome: **Load unpacked**
4. همان پوشه‌ای را انتخاب کنید که `manifest.json` داخلش است

### مک / لینوکس
```bash
chmod +x INSTALL-MAC-LINUX.sh
./INSTALL-MAC-LINUX.sh
```

### دستی
1. `chrome://extensions`
2. **Developer mode** روشن
3. **Load unpacked**
4. انتخاب پوشه

## خطاهای رایج

| مشکل | راه‌حل |
|------|--------|
| Could not load manifest | پوشه اشتباه — جایی را انتخاب کنید که manifest.json هست |
| short_name too long | نسخه 1.0.4+ را دانلود کنید |
| فقط ZIP دارم | اول Extract کنید — ZIP مستقیم نصب نمی‌شود |
| افزونه نیست | Developer mode خاموش است |
| موبایل | افزونه کروم روی موبایل نصب نمی‌شود — فقط دسکتاپ |

## دانلود آخرین نسخه
https://github.com/develop-mohammad/webakery.ir/raw/cursor/webakery-wordpress-plugin-45a5/webakery-speed-chrome.zip

بعد از Extract اگر داخل پوشه دوباره یک پوشه دیگر دیدید، همان پوشه داخلی را Load unpacked کنید.
