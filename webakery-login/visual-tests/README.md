# تست بصری ورود آسان

تست‌های بصری Canvas UI با Playwright (حالات موبایل/کد/خطا + دسکتاپ/موبایل).

## پیش‌نیاز
سرور پیش‌نمایش باید بالا باشد:

```bash
cd /workspace/webakery-login
python3 -m http.server 8765 --bind 127.0.0.1
```

## اجرا

```bash
cd visual-tests
npm install
npx playwright install chromium
npm run test:visual
```

خروجی اسکرین‌شات و گزارش:
`/opt/cursor/artifacts/visual-tests/`
