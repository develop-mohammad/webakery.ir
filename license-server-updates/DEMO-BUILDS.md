# نسخه دمو افزونه‌ها (قبل از خرید)

برای هر افزونهٔ پولی یک ZIP دمو ساخته می‌شود تا مشتری/مارکت قبل از خرید بتواند کامل تست کند.

## ویژگی نسخه دمو

- بدون نیاز به لایسنس
- همه امکانات باز است
- بنر آبی «نسخه دمو» در پیشخوان + لینک خرید به webakery.ir
- به‌روزرسانی خودکار از سرور لایسنس **غیرفعال** است (تا ZIP فروش جایگزین دمو نشود)
- فایل `DEMO-FA.txt` داخل هر ZIP

## ساخت ZIPها

```bash
bash tools/build-demo-zips.sh
```

خروجی در ریشه ریپو:

| فایل | محصول |
|------|--------|
| `hesabdar-demo.zip` | حسابدار |
| `nobat-man-demo.zip` | نوبت من |
| `access-levels-demo.zip` | Barbari |
| `baget-demo.zip` | Baget |
| `webakery-chat-box-demo.zip` | چت باکس |

سکوت نوتیف رایگان است و دمو جدا ندارد.

## آپلود روی هاست

مسیر ثابت:

```text
public_html/license-server/demos/
```

فایل‌ها:

- `index.php` (صفحه لیست)
- `hesabdar-demo.zip`
- `nobat-man-demo.zip`
- `access-levels-demo.zip`
- `baget-demo.zip`
- `webakery-chat-box-demo.zip`

لینک مشتری:

```text
https://webakery.ir/license-server/demos/
https://webakery.ir/license-server/demos/?plugin=hesabdar
```

راهنمای آپلود: `license-server/demos/UPLOAD-FA.txt`

در صفحه پرداخت هم لینک «دانلود نسخه دمو رایگان» اضافه شده است.

## تفاوت با دوره آزمایشی نسخه اصلی

| | دوره آزمایشی (ZIP فروش) | نسخه دمو |
|--|-------------------------|----------|
| مدت | ۳ یا ۷ روز | بدون محدودیت زمانی |
| لایسنس | بعد از انقضا قفل | لازم نیست |
| مناسب | مشتری بعد از نصب روی سایت خودش | تست قبل از خرید / بررسی مارکت |
| آپدیت خودکار | بله (با لایسنس) | خیر |

## توسعه

ثابت دمو در فایل اصلی هر افزونه (پیش‌فرض `false`):

- `HESABDAR_DEMO`
- `NM_DEMO`
- `AL_DEMO`
- `WCCP_DEMO`
- `WBCB_DEMO`

اسکریپت ساخت، فقط داخل کپی موقت آن را `true` می‌کند؛ سورس فروش دست‌نخورده می‌ماند.
