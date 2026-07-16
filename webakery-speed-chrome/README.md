# Webakery Speed — افزونه کروم

مکمل پلاگین وردپرس **Webakery Speed**. صفحه‌ای که الان داخلش هستید را تحلیل می‌کند.

## نصب (Developer Mode)

1. فایل `webakery-speed-chrome.zip` را باز کنید
2. در کروم بروید به `chrome://extensions`
3. **Developer mode** را روشن کنید
4. **Load unpacked** → پوشه `webakery-speed-chrome`
   - یا ZIP را extract کنید و همان پوشه را انتخاب کنید

## استفاده

1. روی هر صفحه سایت بروید
2. آیکن **Webakery Speed** را بزنید
3. **تحلیل این صفحه** → بررسی سریع DOM (بدون API)
4. **اسکن PageSpeed** → نیاز به کلید API در تنظیمات
5. **کپی JSON برای وردپرس** → برای import در پلاگین

## تنظیمات

راست‌کلیک روی آیکن → **Options**:
- کلید API PageSpeed (برای اسکن رسمی گوگل)
- موبایل / دسکتاپ
- لینک پیشخوان وردپرس (اختیاری)

## چه چیزهایی را چک می‌کند؟

- اسکریپت/CSS مسدودکننده رندر
- تصاویر بدون lazy load
- تصاویر بدون width/height
- فونت بدون `display=swap`
- فونت بدون preload
- نبود preconnect برای Google Fonts
- تصویر بزرگ بدون preload (LCP)
- اسکریپت emoji وردپرس

## ارتباط با پلاگین وردپرس

اصلاحات پیشنهادی همان slugهای پلاگین هستند:
`defer_js`, `lazyload`, `preload_fonts`, ...

JSON خروجی را در وردپرس **Webakery Speed → وارد کردن گزارش** استفاده کنید.

## دانلود

https://github.com/develop-mohammad/webakery.ir/raw/cursor/webakery-wordpress-plugin-45a5/webakery-speed-chrome.zip
