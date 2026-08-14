---
name: wp-runtime-test
description: Verify WordPress plugin behaviour on a real local WordPress site (MariaDB + WP-CLI + PHP server) instead of guessing. Use when a plugin change needs runtime proof — activation, roles/capabilities, shortcodes, blocks, REST routes, custom tables, admin screens, or a browser screenshot/video artifact.
---

# Runtime testing with the disposable WP site

`bin/wp-test.sh` یک وردپرس واقعی در `wp-test/` (git-ignored) می‌سازد: MariaDB + WP-CLI + سرور PHP، ادمین `admin/admin`.

## Boot and install

```bash
bin/wp-test.sh install <slug>        # up + sync + activate (اولین بار وردپرس را دانلود می‌کند)
bin/wp-test.sh sync <slug>           # بعد از هر ویرایش کد، فایل‌ها را دوباره کپی کن
bin/wp-test.sh wp <args...>          # هر دستور WP-CLI روی همین سایت
```

اگر فعال‌سازی خطا داد، همان خطا را بخوان: WP-CLI متن fatal را نشان می‌دهد.

## Assert with a PHP file (بهترین ابزار برای منطق دسترسی)

`/tmp/check.php` بنویس و اجرا کن: `bin/wp-test.sh eval-file /tmp/check.php`

```php
<?php
// نمونه: ساخت نقش و کاربر، سپس بررسی یک تابع دسترسی افزونه
add_role( 'premium', 'Premium', array( 'read' => true ) );
$uid = wp_insert_user( array( 'user_login' => 'p1', 'user_pass' => 'p', 'role' => 'premium' ) );

wp_set_current_user( $uid );
$out = do_shortcode( '[protected_video id="' . $video_id . '"]' );
printf( "premium sees player: %s\n", str_contains( $out, '<iframe' ) ? 'yes' : 'no' );

wp_set_current_user( 0 );
$out = do_shortcode( '[protected_video id="' . $video_id . '"]' );
printf( "guest sees url leak: %s\n", str_contains( $out, 'http' ) ? 'LEAK' : 'no' );
```

نکته‌های پرکاربرد:

- تغییر کاربر: `wp_set_current_user( $id )` / مهمان: `wp_set_current_user( 0 )`.
- ساخت داده: `wp_insert_post`, `wp_insert_user`, `update_post_meta`, `wp_set_object_terms`.
- جدول اختصاصی: `$wpdb->get_results( "SELECT * FROM {$wpdb->prefix}<table>" )`.
- REST داخلی: `rest_do_request( new WP_REST_Request( 'GET', '/<ns>/v1/<route>' ) )` — بدون شبکه.
- کرون/زمان: `update_option( 'timezone_string', 'Asia/Tehran' )`، و برای تست انقضا metaی تاریخ را در گذشته بنویس.

## CLI shortcuts

```bash
bin/wp-test.sh wp user create premium1 p1@test.local --role=premium --user_pass=p
bin/wp-test.sh wp post create --post_type=svac_video --post_title='Test' --post_status=publish --porcelain
bin/wp-test.sh wp db query 'SELECT * FROM wp_video_access_logs ORDER BY id DESC LIMIT 5'
bin/wp-test.sh wp option get svac_settings --format=json
bin/wp-test.sh wp plugin list --status=active
```

خطاهای PHP در `wp-test/wp-content/debug.log` است (`WP_DEBUG_LOG` روشن).

## Browser / artifacts

```bash
bin/wp-test.sh serve 8888    # http://127.0.0.1:8888 و /wp-admin (admin/admin)
bin/wp-test.sh stop
```

برای شاهد بصری: صفحه‌ای با شورت‌کد بساز (`wp post create --post_content='[shortcode]'`)، سپس با computer use اسکرین‌شات/ویدیو بگیر و در `/opt/cursor/artifacts/` ذخیره کن. سرور را بعد از تست روشن بگذار تا کاربر ادامه دهد.

## Cleanup

سایت را نگه دار (ارزان است). فقط اگر state خراب شد: `bin/wp-test.sh destroy` و بعد `install` دوباره.
