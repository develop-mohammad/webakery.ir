<?php
defined( 'ABSPATH' ) || exit;

/**
 * صفحه مدیریت حسابداران در پیشخوان وردپرس (فقط برای مدیر سایت).
 */
class WAP_Admin {

    public static function init() {
        add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
    }

    public static function menu() {
        add_menu_page( 'پرتال حسابدار', 'پرتال حسابدار', 'manage_options', 'wap-accountants', array( __CLASS__, 'page' ), 'dashicons-id-alt', 57 );
        add_submenu_page( 'wap-accountants', 'پیامک واریز خالص', 'پیامک واریز خالص', 'manage_options', 'wap-payment-sms', array( __CLASS__, 'sms_page' ) );
        add_submenu_page( 'wap-accountants', 'خروجی تصویری گزارش', 'خروجی تصویری', 'manage_options', 'wap-report-image', array( __CLASS__, 'report_image_page' ) );
    }

    public static function page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }

        self::ensure_accountant_role();

        $notice = '';

        if ( isset( $_GET['wap_remove'] ) && check_admin_referer( 'wap_remove_' . $_GET['wap_remove'] ) ) {
            $user = get_user_by( 'id', absint( $_GET['wap_remove'] ) );
            if ( $user ) {
                $user->remove_role( WAP_Portal::ROLE );
                $notice = 'دسترسی حسابدار از «' . esc_html( $user->display_name ) . '» حذف شد.';
            }
        }

        $accountants = get_users( array( 'role' => WAP_Portal::ROLE ) );
        $panel_url   = WAP_Portal::panel_url();
        ?>
        <div class="wrap">
            <h1>پرتال حسابدار</h1>
            <p>آدرس یکپارچه گزارش فروش:</p>
            <p>
                <code><?php echo esc_html( $panel_url ); ?></code>
                <a href="<?php echo esc_url( $panel_url ); ?>" target="_blank" class="button button-small">باز کردن</a>
            </p>
            <p class="description">یک پنل واحد برای همهٔ کاربران مجاز.</p>

            <?php if ( $notice ) : ?>
                <div class="notice notice-info"><p><?php echo esc_html( $notice ); ?></p></div>
            <?php endif; ?>

            <h2>خروجی Google Sheets</h2>
            <p style="max-width:720px;line-height:1.8">
                <strong>بدون تنظیمات گوگل کلود</strong> (مناسب ایران / تحریم): در پرتال روی
                «خروجی گوگل شیت» بزنید → فایل CSV دانلود می‌شود و Google Sheets باز می‌شود →
                از داخل شیت: <strong>File → Import → Upload</strong> همان فایل را انتخاب کنید.
                داده داخل شیت می‌نشیند. نیازی به Client ID یا Cloud Console نیست.
            </p>

            <h2>لیست حسابداران</h2>
            <p class="description">برای افزودن حسابدار جدید، از «کاربران» وردپرس نقش <code><?php echo esc_html( WAP_Portal::ROLE ); ?></code> را به کاربر بدهید.</p>
            <table class="widefat" style="max-width:640px">
                <thead><tr><th>نام</th><th>ایمیل</th><th>عملیات</th></tr></thead>
                <tbody>
                <?php if ( empty( $accountants ) ) : ?>
                    <tr><td colspan="3">هنوز حسابداری ثبت نشده است.</td></tr>
                <?php else : foreach ( $accountants as $a ) :
                    $remove_url = wp_nonce_url( add_query_arg( array( 'page' => 'wap-accountants', 'wap_remove' => $a->ID ), admin_url( 'admin.php' ) ), 'wap_remove_' . $a->ID ); ?>
                    <tr>
                        <td><?php echo esc_html( $a->display_name ); ?></td>
                        <td><?php echo esc_html( $a->user_email ); ?></td>
                        <td><a href="<?php echo esc_url( $remove_url ); ?>" class="button button-small" onclick="return confirm('حذف دسترسی حسابدار؟')">حذف دسترسی</a></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public static function sms_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }
        if ( ! class_exists( 'WAP_SMS' ) ) {
            echo '<div class="wrap"><div class="notice notice-error"><p>ماژول پیامک بارگذاری نشده است.</p></div></div>';
            return;
        }

        $notice = '';
        if ( isset( $_POST['wap_save_payment_sms'] ) && check_admin_referer( 'wap_payment_sms' ) ) {
            WAP_SMS::save( wp_unslash( $_POST['wap_sms'] ?? array() ) );
            $notice = 'تنظیمات پیامک واریز شاپرک ذخیره شد.';
        }

        $s = WAP_SMS::get();
        $flash = isset( $_GET['wap_sms_msg'] ) ? sanitize_text_field( wp_unslash( $_GET['wap_sms_msg'] ) ) : '';
        $settle_flash = isset( $_GET['wap_settle_msg'] ) ? sanitize_text_field( wp_unslash( $_GET['wap_settle_msg'] ) ) : '';
        ?>
        <div class="wrap">
            <h1>پیامک واریز خالص</h1>
            <p style="max-width:760px;line-height:1.8">
                فقط <strong>یک نوع پیامک</strong> ارسال می‌شود: وقتی واریز خالص به حساب انجام شد.
                <br>متن پیامک فقط شامل <strong>تاریخ واریز</strong> و <strong>مبلغ خالص</strong> است.
                <br>مسیر خرید مشتری ← تأیید شاپرک ← واریز به حساب در تب «واریزی خالص» پرتال قابل مشاهده است.
            </p>

            <?php if ( $notice ) : ?>
                <div class="notice notice-success is-dismissible"><p><?php echo esc_html( $notice ); ?></p></div>
            <?php endif; ?>
            <?php if ( $flash === 'ok' ) : ?>
                <div class="notice notice-success is-dismissible"><p>پیامک تست ارسال شد.</p></div>
            <?php elseif ( $flash === 'no_phone' ) : ?>
                <div class="notice notice-error is-dismissible"><p>شماره برای تست یافت نشد.</p></div>
            <?php elseif ( $flash !== '' ) : ?>
                <div class="notice notice-error is-dismissible"><p><?php echo esc_html( $flash ); ?></p></div>
            <?php endif; ?>
            <?php if ( $settle_flash !== '' ) : ?>
                <div class="notice notice-info is-dismissible"><p><?php echo esc_html( $settle_flash ); ?></p></div>
            <?php endif; ?>

            <form method="post" style="background:#fff;border:1px solid #ccd0d4;border-radius:6px;padding:16px 20px;max-width:760px">
                <?php wp_nonce_field( 'wap_payment_sms' ); ?>
                <h2 style="margin-top:0">ملی‌پیامک و گیرنده‌ها</h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th><label for="wap_sms_user">نام کاربری ملی‌پیامک</label></th>
                        <td><input id="wap_sms_user" type="text" class="regular-text" dir="ltr" name="wap_sms[username]" value="<?php echo esc_attr( $s['username'] ); ?>" autocomplete="off"></td>
                    </tr>
                    <tr>
                        <th><label for="wap_sms_pass">رمز عبور ملی‌پیامک</label></th>
                        <td>
                            <input id="wap_sms_pass" type="password" class="regular-text" dir="ltr" name="wap_sms[password]" value="" autocomplete="new-password" placeholder="<?php echo $s['password'] !== '' ? '•••••••• (برای تغییر پر کنید)' : ''; ?>">
                            <p class="description">خالی = حفظ رمز قبلی.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="wap_sms_from">خط ارسال (Sender)</label></th>
                        <td><input id="wap_sms_from" type="text" class="regular-text" dir="ltr" name="wap_sms[sender]" value="<?php echo esc_attr( $s['sender'] ); ?>" placeholder="مثلاً 3000xxxx"></td>
                    </tr>
                    <tr>
                        <th><label for="wap_sms_pattern">کد پترن (اختیاری)</label></th>
                        <td><input id="wap_sms_pattern" type="text" class="regular-text" dir="ltr" name="wap_sms[pattern]" value="<?php echo esc_attr( $s['pattern'] ); ?>"></td>
                    </tr>
                    <tr>
                        <th><label for="wap_sms_to">شماره‌های دریافت‌کننده</label></th>
                        <td>
                            <textarea id="wap_sms_to" name="wap_sms[recipients]" class="large-text" rows="3" dir="ltr" placeholder="0912xxxxxxx"><?php echo esc_textarea( $s['recipients'] ); ?></textarea>
                            <p class="description">مثلاً موبایل حسابدار — هر شماره در یک خط یا با ویرگول.</p>
                        </td>
                    </tr>
                </table>

                <h2>واریز خالص به حساب</h2>
                <p class="description" style="max-width:680px">
                    افزونه هر ساعت تسویه‌های زرین‌پال را چک می‌کند. فقط وقتی وضعیت
                    <code>تسویه شده</code> شد، <strong>یک پیامک</strong> با تاریخ و مبلغ خالص می‌فرستد.
                </p>
                <table class="form-table" role="presentation">
                    <tr>
                        <th>فعال‌سازی</th>
                        <td>
                            <label>
                                <input type="checkbox" name="wap_sms[settle_enabled]" value="1" <?php checked( (int) $s['settle_enabled'], 1 ); ?>>
                                ارسال پیامک هنگام واریز خالص به حساب
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="wap_zp_terminal">Terminal ID</label></th>
                        <td>
                            <input id="wap_zp_terminal" type="text" class="regular-text" dir="ltr" name="wap_sms[zp_terminal_id]" value="<?php echo esc_attr( $s['zp_terminal_id'] ); ?>" placeholder="مثلاً 545232">
                            <p class="description">شماره ترمینال درگاه در پنل زرین‌پال (عددی) — نه مرچنت‌کد UUID.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="wap_zp_merchant">مرچنت‌کد (UUID)</label></th>
                        <td>
                            <input id="wap_zp_merchant" type="text" class="regular-text" dir="ltr" name="wap_sms[zp_merchant_id]" value="<?php echo esc_attr( $s['zp_merchant_id'] ); ?>" placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx">
                            <p class="description">اختیاری — برای گزارش‌های تطبیق شاپرک / کارمزد.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="wap_zp_token">Access Token</label></th>
                        <td>
                            <textarea id="wap_zp_token" name="wap_sms[zp_access_token]" class="large-text" rows="3" dir="ltr" placeholder="<?php echo esc_attr( $s['zp_access_token'] !== '' ? 'توکن ذخیره شده — برای تغییر پر کنید' : 'Bearer token' ); ?>"></textarea>
                            <p class="description">توکن ذخیره‌شده نمایش داده نمی‌شود. خالی = حفظ توکن قبلی.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>متن پیامک (ثابت)</th>
                        <td>
                            <pre style="background:#f6f7f7;border:1px solid #dcdcde;padding:12px 14px;border-radius:4px;max-width:420px;white-space:pre-wrap;direction:rtl;font-family:Tahoma,sans-serif"><?php echo esc_html( WAP_SMS::fixed_settle_template() ); ?></pre>
                            <p class="description">فقط همین دو مقدار ارسال می‌شود: مبلغ خالص و تاریخ واریز. قابل ویرایش نیست.</p>
                        </td>
                    </tr>
                </table>

                <p>
                    <button type="submit" name="wap_save_payment_sms" class="button button-primary">ذخیره تنظیمات</button>
                </p>
            </form>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:16px;background:#fff;border:1px solid #ccd0d4;border-radius:6px;padding:16px 20px;max-width:760px">
                <input type="hidden" name="action" value="wap_poll_reconciles_now">
                <?php wp_nonce_field( 'wap_poll_reconciles_now' ); ?>
                <h2 style="margin-top:0">بررسی فوری واریزها</h2>
                <p>الان API زرین‌پال را چک می‌کند و برای تسویه‌های جدید <code>PAID</code> پیامک می‌فرستد.</p>
                <p><button type="submit" class="button button-primary">بررسی الان</button></p>
            </form>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:16px;background:#fff;border:1px solid #ccd0d4;border-radius:6px;padding:16px 20px;max-width:760px">
                <input type="hidden" name="action" value="wap_test_settle_sms">
                <?php wp_nonce_field( 'wap_test_settle_sms' ); ?>
                <h2 style="margin-top:0">ارسال پیامک تست (متن واریز شاپرک)</h2>
                <p>
                    <label>شماره تست (اختیاری):</label><br>
                    <input type="text" name="wap_test_phone" class="regular-text" dir="ltr" placeholder="09xxxxxxxxx">
                </p>
                <p><button type="submit" class="button">ارسال تست</button></p>
            </form>
        </div>
        <?php
    }

    public static function report_image_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }
        if ( ! class_exists( 'WAP_Report_Image' ) ) {
            echo '<div class="wrap"><div class="notice notice-error"><p>ماژول خروجی تصویری بارگذاری نشده.</p></div></div>';
            return;
        }
        $notice = '';
        if ( isset( $_POST['wap_save_report_image'] ) && check_admin_referer( 'wap_report_image_settings' ) ) {
            WAP_Report_Image::save_settings( wp_unslash( $_POST['wap_ri'] ?? array() ) );
            $notice = 'تنظیمات خروجی تصویری ذخیره شد.';
        }
        if ( isset( $_POST['wap_run_digest_now'] ) && check_admin_referer( 'wap_report_image_digest' ) ) {
            WAP_Report_Image::run_daily_digest();
            $notice = 'ارسال آزمایشی خلاصه روزانه اجرا شد.';
        }
        $s = WAP_Report_Image::settings();
        $last = get_option( 'wap_report_image_last_digest', array() );
        $ids = get_option( 'wap_report_image_archive_ids', array() );
        ?>
        <div class="wrap">
            <h1>خروجی تصویری گزارش‌ها</h1>
            <p style="max-width:760px;line-height:1.8">
                تنظیمات واترمارک/مقایسه در پرتال اعمال می‌شود. اینجا ارسال روزانه تلگرام/ایمیل، قفل بازه تاریخ،
                و محدودیت تب‌های حسابدار را مدیریت کنید.
            </p>
            <?php if ( $notice ) : ?>
                <div class="notice notice-success is-dismissible"><p><?php echo esc_html( $notice ); ?></p></div>
            <?php endif; ?>

            <form method="post" style="background:#fff;border:1px solid #ccd0d4;border-radius:6px;padding:16px 20px;max-width:780px">
                <?php wp_nonce_field( 'wap_report_image_settings' ); ?>

                <h2 style="margin-top:0">قفل بازه گزارش‌های مالی</h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th>فعال‌سازی قفل</th>
                        <td>
                            <label>
                                <input type="checkbox" name="wap_ri[date_lock_enabled]" value="1" <?php checked( (int) $s['date_lock_enabled'], 1 ); ?>>
                                بازه تاریخ در پرتال قفل شود (حسابدار نتواند عوض کند)
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th>از / تا (شمسی)</th>
                        <td>
                            <input type="text" class="regular-text" dir="ltr" name="wap_ri[date_lock_from]" value="<?php echo esc_attr( $s['date_lock_from'] ); ?>" placeholder="۱۴۰۴/۰۱/۰۱">
                            —
                            <input type="text" class="regular-text" dir="ltr" name="wap_ri[date_lock_to]" value="<?php echo esc_attr( $s['date_lock_to'] ); ?>" placeholder="۱۴۰۴/۱۲/۲۹">
                        </td>
                    </tr>
                </table>

                <h2>نقش حسابدار</h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th>محدودیت تب‌ها</th>
                        <td>
                            <label><input type="checkbox" name="wap_ri[restrict_accountant]" value="1" <?php checked( (int) $s['restrict_accountant'], 1 ); ?>> فقط تب‌های انتخابی برای حسابدار</label>
                            <div style="margin-top:8px">
                                <?php foreach ( array( 'sales' => 'گزارش مالی', 'orders' => 'سفارش‌ها', 'products' => 'محصولات', 'shaparak' => 'واریزی خالص', 'analytics' => 'داشبورد' ) as $k => $lbl ) : ?>
                                    <label style="margin-left:12px"><input type="checkbox" name="wap_ri[accountant_tabs][]" value="<?php echo esc_attr( $k ); ?>" <?php checked( in_array( $k, (array) $s['accountant_tabs'], true ) ); ?>> <?php echo esc_html( $lbl ); ?></label>
                                <?php endforeach; ?>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th>فقط دانلود</th>
                        <td>
                            <label>
                                <input type="checkbox" name="wap_ri[accountant_download_only]" value="1" <?php checked( (int) $s['accountant_download_only'], 1 ); ?>>
                                حسابدار فقط دانلود کند (بدون آرشیو در رسانه)
                            </label>
                        </td>
                    </tr>
                </table>

                <h2>ارسال روزانه (تلگرام / ایمیل)</h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th>فعال</th>
                        <td><label><input type="checkbox" name="wap_ri[daily_enabled]" value="1" <?php checked( (int) $s['daily_enabled'], 1 ); ?>> ارسال خودکار روزانه</label></td>
                    </tr>
                    <tr>
                        <th>ساعت (۰–۲۳)</th>
                        <td><input type="number" min="0" max="23" name="wap_ri[daily_hour]" value="<?php echo esc_attr( (string) $s['daily_hour'] ); ?>"></td>
                    </tr>
                    <tr>
                        <th>تلگرام</th>
                        <td>
                            <label><input type="checkbox" name="wap_ri[telegram_enabled]" value="1" <?php checked( (int) $s['telegram_enabled'], 1 ); ?>> ارسال به تلگرام</label><br>
                            <input type="text" class="regular-text" dir="ltr" name="wap_ri[telegram_bot_token]" value="" placeholder="<?php echo $s['telegram_bot_token'] !== '' ? 'توکن ذخیره شده — برای تغییر پر کنید' : 'Bot Token'; ?>" style="margin-top:6px">
                            <br>
                            <input type="text" class="regular-text" dir="ltr" name="wap_ri[telegram_chat_id]" value="<?php echo esc_attr( $s['telegram_chat_id'] ); ?>" placeholder="Chat ID" style="margin-top:6px">
                        </td>
                    </tr>
                    <tr>
                        <th>ایمیل</th>
                        <td>
                            <label><input type="checkbox" name="wap_ri[email_enabled]" value="1" <?php checked( (int) $s['email_enabled'], 1 ); ?>> ارسال ایمیل با تصویر</label><br>
                            <input type="email" class="regular-text" dir="ltr" name="wap_ri[email_to]" value="<?php echo esc_attr( $s['email_to'] ); ?>" placeholder="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>" style="margin-top:6px">
                        </td>
                    </tr>
                </table>

                <p><button type="submit" name="wap_save_report_image" class="button button-primary" value="1">ذخیره تنظیمات</button></p>
            </form>

            <form method="post" style="margin-top:16px">
                <?php wp_nonce_field( 'wap_report_image_digest' ); ?>
                <button type="submit" name="wap_run_digest_now" class="button" value="1">ارسال آزمایشی الان</button>
                <?php if ( is_array( $last ) && ! empty( $last['at'] ) ) : ?>
                    <p class="description">آخرین اجرا: <?php echo esc_html( wp_date( 'Y-m-d H:i', (int) $last['at'] ) ); ?> — تلگرام: <?php echo ! empty( $last['tg'] ) ? 'موفق' : '—'; ?> / ایمیل: <?php echo ! empty( $last['email'] ) ? 'موفق' : '—'; ?></p>
                <?php endif; ?>
            </form>

            <h2>آرشیو اخیر (کتابخانه رسانه)</h2>
            <?php if ( empty( $ids ) || ! is_array( $ids ) ) : ?>
                <p>هنوز تصویری آرشیو نشده. از پرتال دکمه «آرشیو رسانه» را بزنید.</p>
            <?php else : ?>
                <ul>
                    <?php foreach ( array_slice( $ids, 0, 10 ) as $aid ) :
                        $url = wp_get_attachment_url( (int) $aid );
                        if ( ! $url ) continue; ?>
                        <li><a href="<?php echo esc_url( $url ); ?>" target="_blank">#<?php echo (int) $aid; ?> — <?php echo esc_html( get_the_title( (int) $aid ) ); ?></a></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
        <?php
    }

    private static function ensure_accountant_role() {
        if ( get_role( WAP_Portal::ROLE ) ) {
            return;
        }
        add_role(
            WAP_Portal::ROLE,
            'حسابدار',
            array(
                'read'          => true,
                WAP_Portal::CAP => true,
            )
        );
    }
}
