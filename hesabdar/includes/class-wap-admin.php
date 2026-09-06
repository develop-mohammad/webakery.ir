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
        add_submenu_page( 'wap-accountants', 'اطلاع‌رسانی پیامک', 'اطلاع‌رسانی پیامک', 'manage_options', 'wap-payment-sms', array( __CLASS__, 'sms_page' ) );
    }

    public static function page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }

        self::ensure_accountant_role();

        $notice = '';

        if ( isset( $_POST['wap_add_accountant'] ) && check_admin_referer( 'wap_manage_accountants' ) ) {
            $mode = sanitize_text_field( $_POST['wap_mode'] ?? 'existing' );
            if ( $mode === 'existing' ) {
                $user_input = sanitize_text_field( $_POST['wap_existing_user'] ?? '' );
                $user = is_email( $user_input ) ? get_user_by( 'email', $user_input ) : get_user_by( 'login', $user_input );
                if ( $user ) {
                    $user->add_role( WAP_Portal::ROLE );
                    $notice = 'کاربر «' . esc_html( $user->display_name ) . '» به‌عنوان حسابدار تنظیم شد.';
                } else {
                    $notice = 'کاربری با این نام کاربری/ایمیل یافت نشد.';
                }
            } else {
                $login = sanitize_user( $_POST['wap_new_login'] ?? '' );
                $email = sanitize_email( $_POST['wap_new_email'] ?? '' );
                $pass  = $_POST['wap_new_pass'] ?? '';
                if ( $login && $email && $pass && ! username_exists( $login ) && ! email_exists( $email ) ) {
                    $uid = wp_insert_user( array(
                        'user_login' => $login,
                        'user_email' => $email,
                        'user_pass'  => $pass,
                        'role'       => WAP_Portal::ROLE,
                    ) );
                    $notice = is_wp_error( $uid ) ? $uid->get_error_message() : 'حساب حسابدار «' . esc_html( $login ) . '» ایجاد شد.';
                } else {
                    $notice = 'اطلاعات نامعتبر یا نام کاربری/ایمیل تکراری است.';
                }
            }
        }

        if ( isset( $_GET['wap_remove'] ) && check_admin_referer( 'wap_remove_' . $_GET['wap_remove'] ) ) {
            $user = get_user_by( 'id', absint( $_GET['wap_remove'] ) );
            if ( $user ) {
                $user->remove_role( WAP_Portal::ROLE );
                $notice = 'دسترسی حسابدار از «' . esc_html( $user->display_name ) . '» حذف شد.';
            }
        }

        if ( isset( $_POST['wap_save_roles'] ) && check_admin_referer( 'wap_manage_roles' ) ) {
            $selected = isset( $_POST['wap_allowed_roles'] ) ? array_map( 'sanitize_key', (array) $_POST['wap_allowed_roles'] ) : array();
            update_option( 'wap_allowed_roles', $selected );
            $notice = 'دسترسی نقش‌ها به پرتال حسابدار به‌روزرسانی شد.';
        }

        $accountants   = get_users( array( 'role' => WAP_Portal::ROLE ) );
        $panel_url     = WAP_Portal::panel_url();
        $manager_url   = WAP_Portal::manager_panel_url();
        $allowed_roles = get_option( 'wap_allowed_roles', array() );
        if ( ! is_array( $allowed_roles ) ) {
            $allowed_roles = array();
        }
        $editable = array();
        if ( function_exists( 'wp_roles' ) ) {
            $roles_obj = wp_roles();
            if ( $roles_obj && is_object( $roles_obj ) && method_exists( $roles_obj, 'get_names' ) ) {
                $names = $roles_obj->get_names();
                if ( is_array( $names ) ) {
                    $editable = $names;
                }
            }
        }
        ?>
        <div class="wrap">
            <h1>پرتال حسابدار</h1>
            <p>دو آدرس مستقل برای گزارش فروش:</p>
            <table class="widefat" style="max-width:720px;margin-bottom:16px">
                <thead><tr><th>پنل</th><th>آدرس</th><th>مخاطب</th></tr></thead>
                <tbody>
                    <tr>
                        <td><strong>پنل حسابدار</strong></td>
                        <td><code><?php echo esc_html( $panel_url ); ?></code> <a href="<?php echo esc_url( $panel_url ); ?>" target="_blank" class="button button-small">باز کردن</a></td>
                        <td>حسابدار (بدون پیشخوان)</td>
                    </tr>
                    <tr>
                        <td><strong>پنل مدیر</strong></td>
                        <td><code><?php echo esc_html( $manager_url ); ?></code> <a href="<?php echo esc_url( $manager_url ); ?>" target="_blank" class="button button-small">باز کردن</a></td>
                        <td>مدیر سایت + نقش‌های مجاز (با لینک پیشخوان)</td>
                    </tr>
                </tbody>
            </table>
            <p>حسابداران فقط از <strong>پنل حسابدار</strong> استفاده می‌کنند. مدیران و نقش‌های انتخاب‌شده در پایین می‌توانند بین هر دو پنل جابه‌جا شوند.</p>

            <?php if ( $notice ) : ?>
                <div class="notice notice-info"><p><?php echo esc_html( $notice ); ?></p></div>
            <?php endif; ?>

            <h2>افزودن حسابدار</h2>
            <form method="post" style="background:#fff;border:1px solid #ddd;border-radius:6px;padding:16px;max-width:560px">
                <?php wp_nonce_field( 'wap_manage_accountants' ); ?>
                <p>
                    <label><input type="radio" name="wap_mode" value="existing" checked> انتخاب از کاربران موجود</label>
                    &nbsp;&nbsp;
                    <label><input type="radio" name="wap_mode" value="new"> ساخت کاربر جدید</label>
                </p>
                <p>
                    <label>نام کاربری یا ایمیل کاربر موجود:</label><br>
                    <input type="text" name="wap_existing_user" class="regular-text">
                </p>
                <hr>
                <p>
                    <label>نام کاربری جدید:</label><br>
                    <input type="text" name="wap_new_login" class="regular-text">
                </p>
                <p>
                    <label>ایمیل:</label><br>
                    <input type="email" name="wap_new_email" class="regular-text">
                </p>
                <p>
                    <label>رمز عبور:</label><br>
                    <input type="text" name="wap_new_pass" class="regular-text">
                </p>
                <p><button type="submit" name="wap_add_accountant" class="button button-primary">ذخیره</button></p>
            </form>

            <h2>دسترسی بر اساس نقش</h2>
            <p>نقش‌های انتخاب‌شده به <strong>هر دو پنل</strong> (حسابدار + مدیر) دسترسی دارند و همچنان به پیشخوان وردپرس دسترسی عادی خود را حفظ می‌کنند.</p>
            <form method="post" style="background:#fff;border:1px solid #ddd;border-radius:6px;padding:16px;max-width:560px">
                <?php wp_nonce_field( 'wap_manage_roles' ); ?>
                <?php foreach ( $editable as $slug => $label ) :
                    if ( in_array( $slug, array( 'administrator', WAP_Portal::ROLE ), true ) ) continue; ?>
                    <p>
                        <label>
                            <input type="checkbox" name="wap_allowed_roles[]" value="<?php echo esc_attr( $slug ); ?>" <?php checked( in_array( $slug, $allowed_roles, true ) ); ?>>
                            <?php echo esc_html( $label ); ?>
                        </label>
                    </p>
                <?php endforeach; ?>
                <p><button type="submit" name="wap_save_roles" class="button button-primary">ذخیره دسترسی نقش‌ها</button></p>
            </form>

            <h2>خروجی Google Sheets</h2>
            <p style="max-width:720px;line-height:1.8">
                <strong>بدون تنظیمات گوگل کلود</strong> (مناسب ایران / تحریم): در پرتال روی
                «خروجی گوگل شیت» بزنید → فایل CSV دانلود می‌شود و Google Sheets باز می‌شود →
                از داخل شیت: <strong>File → Import → Upload</strong> همان فایل را انتخاب کنید.
                داده داخل شیت می‌نشیند. نیازی به Client ID یا Cloud Console نیست.
            </p>

            <h2>لیست حسابداران</h2>
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
            $notice = 'تنظیمات پیامک ذخیره شد.';
        }

        $s = WAP_SMS::get();
        $flash = isset( $_GET['wap_sms_msg'] ) ? sanitize_text_field( wp_unslash( $_GET['wap_sms_msg'] ) ) : '';
        $settle_flash = isset( $_GET['wap_settle_msg'] ) ? sanitize_text_field( wp_unslash( $_GET['wap_settle_msg'] ) ) : '';
        ?>
        <div class="wrap">
            <h1>اطلاع‌رسانی پیامک</h1>
            <p style="max-width:760px;line-height:1.8">
                دو حالت جداگانه دارید:
                <br>۱) <strong>پرداخت مشتری</strong> — لحظهٔ تأیید سفارش در ووکامرس
                <br>۲) <strong>واریز شاپرک به حساب</strong> — با پایش API تسویهٔ زرین‌پال (وضعیت PAID)
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
                <h2 style="margin-top:0">ملی‌پیامک و گیرنده‌ها (مشترک)</h2>
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

                <h2>۱) پیامک بعد از پرداخت مشتری</h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th>فعال‌سازی</th>
                        <td>
                            <label>
                                <input type="checkbox" name="wap_sms[enabled]" value="1" <?php checked( (int) $s['enabled'], 1 ); ?>>
                                ارسال پس از پرداخت موفق سفارش
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th>فقط زرین‌پال</th>
                        <td>
                            <label>
                                <input type="checkbox" name="wap_sms[only_zarinpal]" value="1" <?php checked( (int) $s['only_zarinpal'], 1 ); ?>>
                                فقط روش پرداخت زرین‌پال
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="wap_sms_msg">متن پیامک پرداخت</label></th>
                        <td>
                            <textarea id="wap_sms_msg" name="wap_sms[message]" class="large-text" rows="4"><?php echo esc_textarea( $s['message'] ); ?></textarea>
                            <p class="description"><code>{order_id}</code> <code>{total}</code> <code>{customer}</code> <code>{phone}</code> <code>{payment}</code> <code>{status}</code></p>
                        </td>
                    </tr>
                </table>

                <h2>۲) پیامک واریز شاپرک به حساب (تسویه زرین‌پال)</h2>
                <p class="description" style="max-width:680px">
                    زرین‌پال وب‌هوک لحظه‌ای برای تسویه ندارد. افزونه هر ساعت لیست تسویه‌های وضعیت
                    <code>PAID</code> را از API می‌گیرد و برای موارد جدید پیامک می‌فرستد.
                    <br><strong>مرچنت‌کد UUID کافی نیست</strong> — به <code>terminal_id</code> عددی و
                    <code>Access Token</code> (OAuth پنل API زرین‌پال) نیاز دارید. از پشتیبانی زرین‌پال
                    <code>client_id/client_secret</code> بگیرید و Access Token بسازید.
                </p>
                <table class="form-table" role="presentation">
                    <tr>
                        <th>فعال‌سازی تسویه</th>
                        <td>
                            <label>
                                <input type="checkbox" name="wap_sms[settle_enabled]" value="1" <?php checked( (int) $s['settle_enabled'], 1 ); ?>>
                                پایش تسویه و ارسال پیامک هنگام واریز (PAID)
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
                            <p class="description">برای محاسبه کارمزد با API رسمی <code>feeCalculation</code> و گزارش تطبیق شاپرک.</p>
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
                        <th><label for="wap_settle_msg">متن پیامک واریز</label></th>
                        <td>
                            <textarea id="wap_settle_msg" name="wap_sms[settle_message]" class="large-text" rows="4"><?php echo esc_textarea( $s['settle_message'] ); ?></textarea>
                            <p class="description"><code>{amount}</code> <code>{amount_rial}</code> <code>{reference_id}</code> <code>{reconcile_id}</code> <code>{reconciled_at}</code> <code>{status}</code></p>
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
                <h2 style="margin-top:0">بررسی فوری تسویه‌ها</h2>
                <p>الان API زرین‌پال را چک می‌کند و برای تسویه‌های جدید <code>PAID</code> پیامک می‌فرستد.</p>
                <p><button type="submit" class="button button-primary">بررسی الان</button></p>
            </form>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:16px;background:#fff;border:1px solid #ccd0d4;border-radius:6px;padding:16px 20px;max-width:760px">
                <input type="hidden" name="action" value="wap_test_payment_sms">
                <?php wp_nonce_field( 'wap_test_payment_sms' ); ?>
                <h2 style="margin-top:0">ارسال پیامک تست (متن پرداخت)</h2>
                <p>
                    <label>شماره تست (اختیاری):</label><br>
                    <input type="text" name="wap_test_phone" class="regular-text" dir="ltr" placeholder="09xxxxxxxxx">
                </p>
                <p><button type="submit" class="button">ارسال تست</button></p>
            </form>
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
