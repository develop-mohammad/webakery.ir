# webakery.ir


## افزودن Hesabdar واقعی

افزونه اشتباه قبلی حذف شد. سورس واقعی را طبق [HESABDAR-GIT.md](./HESABDAR-GIT.md) داخل پوشه `hesabdar/` بگذارید و push کنید.

افزونه‌های وردپرس این ریپو:

1. **Hesabdar (حسابدار)** — محصولات، سفارش‌ها و فاکتور
2. **Webakery Speed** — دریافت خطاهای Google PageSpeed و رفع امن آن‌ها

## دانلود پلاگین‌ها

### Hesabdar (حسابدار)
- [`hesabdar.zip`](./hesabdar.zip)

### Webakery Speed (PageSpeed)
- [`webakery-speed.zip`](./webakery-speed.zip)

### افزونه کروم Webakery Speed (تحلیل صفحه فعلی)
- [`webakery-speed-chrome.zip`](./webakery-speed-chrome.zip) — **فقط کروم، نه وردپرس**

## نصب Hesabdar

1. `hesabdar.zip` را باز کنید.
2. پوشه `hesabdar` را در `wp-content/plugins/` بگذارید و فعال کنید.
3. منوی **حسابدار** در پیشخوان ظاهر می‌شود.

### فاکتور سفارش‌ها

در پیشخوان → حسابدار → **سفارش‌ها**:
- ستون **فاکتور** کنار هر سفارش: **مشاهده** و **دانلود**
- داخل صفحه سفارش هم دکمه‌های مشاهده/دانلود هست
- از صفحه فاکتور می‌توانید **چاپ / ذخیره PDF** بگیرید

### شورت‌کدها

| شورت‌کد | کاربرد |
| --- | --- |
| `[hesabdar_products]` | نمایش شبکه محصولات |
| `[hesabdar_products featured="1" limit="6"]` | محصولات ویژه |
| `[hesabdar_hours]` | ساعات کاری |
| `[hesabdar_info]` | اطلاعات فروشگاه |
| `[hesabdar_order]` | فرم ثبت سفارش |

## نصب افزونه کروم (مهم)

ZIP مستقیم نصب نمی‌شود. حتماً Extract کنید.

1. ZIP را Extract کنید
2. پوشه‌ای را پیدا کنید که **`manifest.json`** داخلش است
3. `chrome://extensions` → Developer mode → **Load unpacked**
4. همان پوشه را انتخاب کنید

راهنما: [webakery-speed-chrome/INSTALL-FA.md](./webakery-speed-chrome/INSTALL-FA.md)

## نصب Webakery Speed (PageSpeed)

1. `webakery-speed.zip` را باز کنید.
2. پوشه `webakery-speed` را در `wp-content/plugins/` بگذارید و فعال کنید.

## ساختار

```
hesabdar/                 # حسابدار — محصولات، سفارش، فاکتور
webakery-speed/           # PageSpeed و بهینه‌سازی امن
webakery-speed-chrome/    # افزونه کروم مکمل
```
