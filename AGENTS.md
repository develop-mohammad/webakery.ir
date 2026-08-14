# AGENTS.md — webakery.ir plugin monorepo

مونوریپوی افزونه‌های وردپرس فارسی/RTL. قواعد محصول در `.cursorrules` است؛ این فایل مسیر سریع کار با ابزارهای ریپو را می‌گوید.

## Toolchain (already installed on this VM)

| ابزار | کاربرد |
| --- | --- |
| `composer` + `vendor/` | PHPCS، WPCS، PHPCompatibility، PHPStan + phpstan-wordpress |
| `wp` (WP-CLI) | مدیریت سایت تست |
| MariaDB 10.11 | دیتابیس سایت تست (`/etc/init.d/mariadb start` با `sudo -n`) |

اگر `vendor/` نبود: `composer install`.

## Fast path

```bash
bin/new-plugin.sh <slug> --prefix WBX --name "نام | Name" [--license]  # اسکافولد استاندارد
bin/lint.sh [slug ...]            # php -l + WPCS + PHPStan  (--fix برای اصلاح خودکار، --quick برای سرعت)
bin/wp-test.sh install <slug>     # وردپرس واقعی + فعال‌سازی افزونه
bin/wp-test.sh eval-file test.php # اجرای اسکریپت تست داخل وردپرس
bin/wp-test.sh serve 8888         # سرو سایت برای تست مرورگری (admin/admin)
bin/build-zip.sh <slug>           # ساخت ZIP ریشه با پوشه سطح‌بالا
```

`wp-test/` و `vendor/` در `.gitignore` هستند و هرگز commit نمی‌شوند.

## Conventions worth not re-deriving

- بوت‌استرپ همیشه `<slug>/<slug>.php` است، نه `plugin.php`.
- هر پوشه PHP یک `index.php` با `// Silence is golden.` دارد.
- ثابت‌ها و کلاس‌ها با پیشوند محصول: `WBL_`, `NM_`, `WBCB_`, `WBFS_`, `AL_`, `SVAC_`.
- کلاس‌ها در `includes/class-<prefix>-*.php`؛ لایسنس مشترک `includes/class-wb-license.php` (کپی می‌شود، ویرایش نمی‌شود).
- خروجی توزیع: `<slug>.zip` در ریشه ریپو با پوشه سطح‌بالای `<slug>/` (بعضی ZIPهای قدیمی flat هستند؛ برای محصول جدید همیشه پوشه‌دار).
- بعد از تغییر افزونه، ZIP را با `bin/build-zip.sh` بازبساز.
- محصول جدید لایسنس‌دار: `license-server-updates/ADD-<SLUG>.md` بساز و در `README.md` ثبت کن.
- متن‌های UI فارسی و RTL؛ text domain = slug؛ POT با `wp i18n make-pot <slug> <slug>/languages/<slug>.pot`.

## Testing expectations

- تست runtime واقعی را به حدس ترجیح بده: `bin/wp-test.sh` سایت واقعی می‌دهد، پس منطق دسترسی/شورت‌کد/REST را همان‌جا اجرا کن.
- برای تست نقش‌محور: `bin/wp-test.sh wp user create ...` و `wp eval` با `wp_set_current_user()`.
- `bin/lint.sh` باید قبل از commit پاس شود؛ PHPStan روی level 5 با استاب‌های وردپرس اجرا می‌شود.
- افزونه‌های قدیمی (`nobat-man`, `hesabdar`, `baget`, `access-levels`, `webakery-login`, ...) ایرادهای از پیش موجود دارند؛ آن‌ها را در تسک بی‌ربط اصلاح نکن، فقط کدی که خودت لمس کردی باید پاس شود.
- warningهای «direct database call» برای جدول اختصاصی طبیعی است و باعث fail شدن lint نمی‌شود؛ فقط errorها fail می‌کنند.

## Don't

- `npm run dev` در ریشه (ریپو Node app نیست).
- commit کردن `vendor/`، `wp-test/`، یا secret واقعی.
- ویرایش `class-wb-license.php` یا بازنویسی افزونه‌های دیگر بدون درخواست صریح.

## Cursor Cloud specific instructions

- MariaDB به‌صورت خودکار بالا نیست؛ `bin/wp-test.sh up` خودش آن را با `sudo -n /etc/init.d/mariadb start` بالا می‌آورد.
- سایت تست روی `http://127.0.0.1:8888` سرو می‌شود (`bin/wp-test.sh serve`) و کاربر ادمین `admin/admin` است — برای تست مرورگری/اسکرین‌شات همین را استفاده کن.
- PHP CLI اینجا سوکت MySQL را در مسیر پیش‌فرض پیدا نمی‌کند، پس اتصال دیتابیس از `127.0.0.1` (TCP) انجام می‌شود.
