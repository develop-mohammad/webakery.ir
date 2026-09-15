# روزم (Roozam)

وب‌اپلیکیشن مستقل برنامه‌ریزی روزانه — فارسی و RTL.

## امکانات

- برنامه‌ریزی هوشمند روز (عادت‌ها + کارها بر اساس اولویت و مدت)
- تقویم شمسی و تایم‌لاین روزانه
- عادت‌های تکرارشونده
- ذخیره در مرورگر (localStorage)
- خروجی / بازیابی پشتیبان JSON
- قابل نصب به‌صورت PWA (آفلاین)

## اجرا

از پوشه `roozam`:

```bash
npm start
```

سپس باز کنید: [http://127.0.0.1:8765](http://127.0.0.1:8765)

یا هر سرور استاتیک دیگر روی همین پوشه.

## ساختار

```
roozam/
  index.html
  manifest.webmanifest
  sw.js
  assets/css/app.css
  assets/js/{jalali,planner,app}.js
  assets/icons/icon.svg
```

سازنده: [webakery.ir](https://webakery.ir)
