---
name: wp-plugin-dev
description: Build or modify a WordPress plugin in the webakery.ir monorepo. Use whenever the task is to create a new plugin, add a feature to an existing plugin (nobat-man, webakery-login, access-levels, hesabdar, baget, webakery-chat-box, smart-video-access-control, ...), fix plugin code, or ship a plugin ZIP.
---

# Building plugins in this monorepo

Do not re-derive the conventions — the scaffolder and linter encode them.

## 1. Start

New plugin:

```bash
bin/new-plugin.sh <slug> --prefix WBX --name "نام فارسی | English Name" --desc "توضیح کوتاه" [--license]
```

خروجی: بوت‌استرپ `<slug>/<slug>.php`، `includes/class-<prefix>-plugin.php`، سایلنسرهای `index.php`، `uninstall.php`، `readme.txt` فارسی، `languages/<slug>.pot`. با `--license` کلاینت `class-wb-license.php` کپی و `WB_License::init()` وصل می‌شود.

Existing plugin: فقط فایل‌های همان افزونه را لمس کن. `class-wb-license.php` را ویرایش نکن.

## 2. Write code

- کلاس‌ها: `includes/class-<lower-prefix>-<thing>.php`، نام کلاس `<PREFIX>_Thing`، بدون فریم‌ورک.
- ثابت‌ها: `<PREFIX>_VERSION|FILE|PATH|URL|PRODUCT` در بوت‌استرپ.
- ذخیره‌سازی: `get_option`/`update_option` با کلید `<lower_prefix>_settings`؛ جدول اختصاصی فقط با `dbDelta` و `$wpdb->prefix`.
- امنیت در هر مسیر نوشتن: `current_user_can()` + `wp_verify_nonce()`/`check_admin_referer()` + sanitize ورودی + escape خروجی (`esc_html`, `esc_attr`, `esc_url`, `wp_kses_post`).
- AJAX: `wp_ajax_<action>` + `check_ajax_referer()`؛ REST: `permission_callback` واقعی (هرگز `__return_true` برای داده حساس).
- i18n: همه رشته‌ها با text domain برابر slug؛ سپس `wp i18n make-pot <slug> <slug>/languages/<slug>.pot`.
- UI: فارسی و RTL (`dir="rtl"`)، CSS اسکوپ‌شده با کلاس ریشه افزونه، سازگار با المنتور (فونت/رنگ inherit).

## 3. Check

```bash
bin/lint.sh <slug>          # php -l + WPCS + PHPStan level 5
bin/lint.sh --fix <slug>    # اصلاح خودکار whitespace/spacing، بقیه دستی
```

باید «all checks passed» بدهد. PHPStan استاب ثابت‌های افزونه را خودش می‌سازد، پس `constant.notFound` نباید ببینی؛ اگر دیدی، ثابت را در بوت‌استرپ `define` نکرده‌ای.

## 4. Verify at runtime (never skip)

`.cursor/skills/wp-runtime-test/SKILL.md` را بخوان و اجرا کن. حداقل: فعال‌سازی بدون خطا + مسیر اصلی feature.

## 5. Ship

```bash
bin/build-zip.sh <slug>
```

سپس اگر محصول جدید است: `license-server-updates/ADD-<SLUG>.md` و یک بخش در `README.md`.

## Reference: existing prefixes

`WBL_` webakery-login · `NM_` nobat-man · `WBCB_` webakery-chat-box · `WBFS_` webakery-font-swap · `WBQN_` webakery-quiet-notices · `AL_` access-levels · `WCCP_` baget · `WAP_`/`WCI_` hesabdar · `SVAC_` smart-video-access-control
