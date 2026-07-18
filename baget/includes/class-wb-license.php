<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/* ═══════════════════════════════════════════════════════════════
   کلاینت لایسنس WB_License — تک‌فایلی، همراه خودِ افزونه
   (این کلاس با سرور لایسنس webakery.ir صحبت می‌کند، ولی خودِ این
   فایل باید همیشه همراه خودِ افزونه، روی همین سایت باشد)
   ═══════════════════════════════════════════════════════════════ */
if ( ! class_exists( 'WB_License' ) ) :

class WB_License {

    const SERVER_DEFAULT = 'https://webakery.ir/license-server';
    const MENU_SLUG      = 'wb-licenses';

    /** @var array<string,array> محصولات ثبت‌شده */
    protected static $products    = [];
    protected static $hooked      = false;
    protected static $css_printed = false;

    /* ─── ثبت یک محصول ─────────────────────────────────────────── */
    public static function init( array $cfg ) {
        $cfg = array_merge( [
            'product'       => '',
            'name'          => '',
            'price'         => '۹۹,۹۹۹ تومان',
            'file'          => '',
            'version'       => '',     // خالی = از هدر افزونه خوانده می‌شود
            'trial_days'    => 3,
            'server'        => self::SERVER_DEFAULT,
            'register_menu' => true,   // منوی مرکزی «لایسنس افزونه‌ها»
            'page'          => '',     // صفحه بازگشت اختصاصی (مثلاً admin.php?page=xxx&tab=license)
            'features'      => [ 'به‌روزرسانی خودکار', 'پشتیبانی فنی', 'فعال‌سازی روی ۱ دامنه' ],
        ], $cfg );

        $slug = sanitize_key( $cfg['product'] );
        if ( ! $slug ) return;
        $cfg['product'] = $slug;

        // نسخه و شناسه‌ی افزونه (برای به‌روزرسانی خودکار)
        if ( $cfg['file'] ) {
            $cfg['basename'] = plugin_basename( $cfg['file'] );
            if ( $cfg['version'] === '' && function_exists( 'get_file_data' ) ) {
                $d = get_file_data( $cfg['file'], [ 'Version' => 'Version' ] );
                $cfg['version'] = $d['Version'] ?? '';
            }
        }
        self::$products[ $slug ] = $cfg;

        // ثبت زمان نصب هنگام فعال‌سازی افزونه
        if ( $cfg['file'] ) {
            register_activation_hook( $cfg['file'], function () use ( $slug ) {
                if ( ! get_option( self::o( $slug, 'install_time' ) ) ) {
                    add_option( self::o( $slug, 'install_time' ), time() );
                }
            } );
        }

        // هوک‌های عمومی فقط یک بار
        if ( ! self::$hooked ) {
            self::$hooked = true;
            add_action( 'admin_init',    [ __CLASS__, 'admin_init' ] );
            add_action( 'admin_menu',    [ __CLASS__, 'admin_menu' ], 80 );
            add_action( 'admin_notices', [ __CLASS__, 'admin_notice' ] );
            add_action( 'admin_post_wb_license_save',       [ __CLASS__, 'handle_save' ] );
            add_action( 'admin_post_wb_license_deactivate', [ __CLASS__, 'handle_deactivate' ] );
            // به‌روزرسانی خودکار افزونه از سرور
            add_filter( 'pre_set_site_transient_update_plugins', [ __CLASS__, 'check_update' ] );
            add_filter( 'plugins_api', [ __CLASS__, 'plugins_api' ], 20, 3 );
        }
    }

    /* ─── وضعیت ────────────────────────────────────────────────── */
    public static function is_valid( $slug ) {
        return get_option( self::o( $slug, 'status' ) ) === 'valid';
    }
    public static function trial_active( $slug ) {
        $t = (int) get_option( self::o( $slug, 'install_time' ), 0 );
        if ( ! $t ) return true;
        return time() < $t + self::days( $slug ) * DAY_IN_SECONDS;
    }
    public static function trial_days_left( $slug ) {
        $t = (int) get_option( self::o( $slug, 'install_time' ), 0 );
        if ( ! $t ) return self::days( $slug );
        $left = ( $t + self::days( $slug ) * DAY_IN_SECONDS ) - time();
        return max( 0, (int) ceil( $left / DAY_IN_SECONDS ) );
    }
    /** آیا افزونه قابل استفاده است؟ (لایسنس معتبر یا دوره آزمایشی) */
    public static function is_active( $slug ) {
        return self::is_valid( $slug ) || self::trial_active( $slug );
    }

    /* ─── فعال‌سازی / اعتبارسنجی ───────────────────────────────── */
    public static function activate( $slug, $key ) {
        $key = trim( (string) $key );
        if ( $key === '' ) return [ 'ok' => false, 'message' => 'کلید لایسنس را وارد کنید.' ];
        $domain = self::site_domain();

        $act = self::api( $slug, 'activate', [ 'license_key' => $key, 'domain' => $domain, 'product' => $slug ] );
        $val = self::api( $slug, 'validate', [ 'license_key' => $key, 'domain' => $domain, 'product' => $slug ] );

        if ( ! empty( $val['valid'] ) ) {
            update_option( self::o( $slug, 'key' ),        $key );
            update_option( self::o( $slug, 'status' ),     'valid' );
            update_option( self::o( $slug, 'last_check' ), time() );
            update_option( self::o( $slug, 'info' ), [
                'email'      => $val['email']      ?? '',
                'expires_at' => $val['expires_at'] ?? null,
            ] );
            return [ 'ok' => true, 'message' => 'لایسنس با موفقیت فعال شد. ✅' ];
        }

        if ( isset( $act['success'] ) && ! $act['success'] && ! empty( $act['message'] ) ) {
            $msg = $act['message'];
        } else {
            $msg = self::err_fa( $val['error'] ?? '' );
        }
        return [ 'ok' => false, 'message' => $msg ];
    }

    protected static function maybe_revalidate( $slug ) {
        if ( ! self::is_valid( $slug ) ) return;
        $last = (int) get_option( self::o( $slug, 'last_check' ), 0 );
        if ( time() - $last < 12 * HOUR_IN_SECONDS ) return;
        $key = get_option( self::o( $slug, 'key' ) );
        if ( ! $key ) return;

        $val = self::api( $slug, 'validate', [ 'license_key' => $key, 'domain' => self::site_domain(), 'product' => $slug ] );
        if ( ! empty( $val['_neterr'] ) ) return;   // خطای شبکه → قفل نکن
        update_option( self::o( $slug, 'last_check' ), time() );
        if ( empty( $val['valid'] ) ) update_option( self::o( $slug, 'status' ), 'invalid' );
    }

    /* ─── هوک‌ها ───────────────────────────────────────────────── */
    public static function admin_init() {
        foreach ( self::$products as $slug => $cfg ) {
            if ( ! get_option( self::o( $slug, 'install_time' ) ) ) {
                update_option( self::o( $slug, 'install_time' ), time() );
            }
            // پس از به‌روزرسانی افزونه: وضعیت پرو را خودکار دوباره بررسی کن (بدون نیاز به ورود مجدد لایسنس)
            $ver = (string) ( $cfg['version'] ?? '' );
            if ( get_option( self::o( $slug, 'ver' ) ) !== $ver ) {
                update_option( self::o( $slug, 'ver' ), $ver );
                delete_transient( 'wbl_upd_' . $slug );
                if ( self::is_valid( $slug ) ) update_option( self::o( $slug, 'last_check' ), 0 ); // بازبینی فوری
            }
        }

        // فعال‌سازی خودکار پس از بازگشت از درگاه پرداخت
        if ( current_user_can( 'manage_options' )
             && ! empty( $_GET['wccp_activate'] )
             && ! empty( $_GET['wccp_key'] )
             && ! empty( $_GET['wbl_product'] ) ) {
            $slug = sanitize_key( $_GET['wbl_product'] );
            if ( isset( self::$products[ $slug ] ) ) {
                $res = self::activate( $slug, sanitize_text_field( wp_unslash( $_GET['wccp_key'] ) ) );
                wp_safe_redirect( self::result_url( $slug, $res ) );
                exit;
            }
        }

        foreach ( self::$products as $slug => $cfg ) self::maybe_revalidate( $slug );
    }

    public static function admin_menu() {
        $need = false;
        foreach ( self::$products as $c ) { if ( $c['register_menu'] ) { $need = true; break; } }
        if ( ! $need ) return;
        add_menu_page(
            'لایسنس افزونه‌ها', 'لایسنس افزونه‌ها', 'manage_options',
            self::MENU_SLUG, [ __CLASS__, 'render_admin_page' ], 'dashicons-admin-network', 80
        );
    }

    public static function admin_notice() {
        if ( ! current_user_can( 'manage_options' ) ) return;
        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
        // روی خود صفحه لایسنس بنر تکراری نشان نده
        if ( $screen && strpos( (string) $screen->id, self::MENU_SLUG ) !== false ) return;

        foreach ( self::$products as $slug => $cfg ) {
            if ( self::is_valid( $slug ) ) continue;
            $url = esc_url( self::page_url( $slug ) );
            if ( self::trial_active( $slug ) ) {
                $d = (int) self::trial_days_left( $slug );
                echo '<div class="notice notice-warning" style="border-right-color:#7c3aed"><p>'
                    . '⏳ <strong>' . esc_html( $cfg['name'] ?: $slug ) . '</strong>: '
                    . $d . ' روز از دوره آزمایشی باقی مانده — '
                    . '<a href="' . $url . '"><strong>فعال‌سازی لایسنس</strong></a></p></div>';
            } else {
                echo '<div class="notice notice-error"><p>'
                    . '🔒 دوره آزمایشی <strong>' . esc_html( $cfg['name'] ?: $slug ) . '</strong> تمام شد و افزونه قفل شده است — '
                    . '<a href="' . $url . '"><strong>تهیه لایسنس</strong></a></p></div>';
            }
        }
    }

    public static function handle_save() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'دسترسی غیرمجاز' );
        check_admin_referer( 'wb_license_save' );
        $slug = sanitize_key( $_POST['product'] ?? '' );
        if ( ! isset( self::$products[ $slug ] ) ) wp_die( 'محصول نامعتبر' );
        $res = self::activate( $slug, sanitize_text_field( wp_unslash( $_POST['license_key'] ?? '' ) ) );
        wp_safe_redirect( self::result_url( $slug, $res ) );
        exit;
    }

    public static function handle_deactivate() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'دسترسی غیرمجاز' );
        check_admin_referer( 'wb_license_deactivate' );
        $slug = sanitize_key( $_POST['product'] ?? '' );
        if ( ! isset( self::$products[ $slug ] ) ) wp_die( 'محصول نامعتبر' );
        $key = get_option( self::o( $slug, 'key' ) );
        if ( $key ) self::api( $slug, 'deactivate', [ 'license_key' => $key, 'domain' => self::site_domain() ] );
        delete_option( self::o( $slug, 'key' ) );
        delete_option( self::o( $slug, 'info' ) );
        update_option( self::o( $slug, 'status' ), 'none' );
        wp_safe_redirect( self::page_url( $slug ) . '&wbl_msg=deactivated' );
        exit;
    }

    /* ─── صفحه منوی مرکزی ──────────────────────────────────────── */
    public static function render_admin_page() {
        echo '<div class="wrap"><h1 style="margin-bottom:18px">🔑 لایسنس افزونه‌ها</h1>';
        echo self::notice_html();
        $any = false;
        foreach ( self::$products as $slug => $cfg ) {
            if ( ! $cfg['register_menu'] ) continue;
            $any = true;
            echo self::render_box( $slug );
        }
        if ( ! $any ) echo '<p>هیچ افزونه‌ی لایسنس‌داری ثبت نشده است.</p>';
        echo '</div>';
    }

    /* ─── باکس پرداخت/لایسنس (UI جذاب) ─────────────────────────── */
    public static function render_box( $slug ) {
        if ( ! isset( self::$products[ $slug ] ) ) return '';
        $cfg    = self::$products[ $slug ];
        $status = get_option( self::o( $slug, 'status' ), 'none' );
        $info   = get_option( self::o( $slug, 'info' ), [] );
        $key    = (string) get_option( self::o( $slug, 'key' ), '' );
        $valid  = self::is_valid( $slug );
        $trial  = self::trial_active( $slug );
        $left   = self::trial_days_left( $slug );
        $days   = self::days( $slug );
        $pct    = $days ? max( 0, min( 100, round( $left / $days * 100 ) ) ) : 0;
        $pay    = esc_url( self::pay_url( $slug ) );
        $post   = esc_url( admin_url( 'admin-post.php' ) );
        $portal = esc_url( rtrim( $cfg['server'], '/' ) . '/portal/' );

        $state = $valid ? 'valid' : ( $trial ? 'trial' : 'expired' );

        ob_start();
        echo self::css();
        ?>
        <div class="wbl-box wbl-<?php echo $state; ?>">

            <div class="wbl-hero">
                <div class="wbl-hero-glow"></div>
                <div class="wbl-hero-row">
                    <div class="wbl-hero-icon">🔑</div>
                    <div class="wbl-hero-main">
                        <div class="wbl-hero-name"><?php echo esc_html( $cfg['name'] ?: $slug ); ?></div>
                        <?php if ( $valid ) : ?>
                            <span class="wbl-pill wbl-pill-ok">✓ لایسنس فعال</span>
                        <?php elseif ( $trial ) : ?>
                            <span class="wbl-pill wbl-pill-trial">⏳ دوره آزمایشی</span>
                        <?php else : ?>
                            <span class="wbl-pill wbl-pill-lock">🔒 قفل شده</span>
                        <?php endif; ?>
                    </div>
                    <?php if ( ! $valid ) : ?>
                    <div class="wbl-hero-price">
                        <span class="wbl-price"><?php echo esc_html( $cfg['price'] ); ?></span>
                        <span class="wbl-price-sub">پرداخت یکباره</span>
                    </div>
                    <?php endif; ?>
                </div>

                <?php if ( ! $valid ) : ?>
                <div class="wbl-trial-bar">
                    <div class="wbl-trial-fill" style="width:<?php echo (int) $pct; ?>%"></div>
                </div>
                <div class="wbl-trial-text">
                    <?php if ( $trial ) : ?>
                        <?php echo (int) $left; ?> روز از دوره آزمایشی <?php echo (int) $days; ?> روزه باقی مانده
                    <?php else : ?>
                        دوره آزمایشی <?php echo (int) $days; ?> روزه به پایان رسیده است
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>

            <div class="wbl-body">

                <?php if ( $valid ) : ?>

                    <div class="wbl-valid-card">
                        <div class="wbl-valid-icon">✅</div>
                        <div>
                            <div class="wbl-valid-title">این افزونه فعال است</div>
                            <?php if ( ! empty( $info['email'] ) ) : ?>
                                <div class="wbl-valid-sub"><?php echo esc_html( $info['email'] ); ?></div>
                            <?php endif; ?>
                            <div class="wbl-valid-sub"><?php echo ! empty( $info['expires_at'] ) ? 'انقضا: ' . esc_html( $info['expires_at'] ) : '♾ مادام‌العمر'; ?></div>
                        </div>
                    </div>
                    <div class="wbl-key-show"><?php echo esc_html( $key ); ?></div>
                    <form method="post" action="<?php echo $post; ?>" style="text-align:center;margin-top:12px">
                        <?php wp_nonce_field( 'wb_license_deactivate' ); ?>
                        <input type="hidden" name="action"  value="wb_license_deactivate">
                        <input type="hidden" name="product" value="<?php echo esc_attr( $slug ); ?>">
                        <button type="submit" class="wbl-btn-ghost" onclick="return confirm('لایسنس از این سایت حذف شود؟')">حذف لایسنس از این سایت</button>
                    </form>

                <?php else : ?>

                    <ul class="wbl-features">
                        <?php foreach ( (array) $cfg['features'] as $f ) : ?>
                            <li><span>✓</span> <?php echo esc_html( $f ); ?></li>
                        <?php endforeach; ?>
                    </ul>

                    <a href="<?php echo $pay; ?>" class="wbl-btn-pay">
                        <span class="wbl-btn-pay-ic">💳</span>
                        پرداخت و فعال‌سازی آنی
                        <span class="wbl-btn-pay-amt"><?php echo esc_html( $cfg['price'] ); ?></span>
                    </a>
                    <div class="wbl-secure">🔒 پرداخت امن از طریق درگاه زیبال — بعد از پرداخت، لایسنس خودکار روی همین سایت فعال می‌شود</div>

                    <details class="wbl-have-key">
                        <summary>از قبل کلید لایسنس دارید؟</summary>
                        <form method="post" action="<?php echo $post; ?>" class="wbl-key-form">
                            <?php wp_nonce_field( 'wb_license_save' ); ?>
                            <input type="hidden" name="action"  value="wb_license_save">
                            <input type="hidden" name="product" value="<?php echo esc_attr( $slug ); ?>">
                            <input type="text" name="license_key" placeholder="XXXXXX-XXXX-XXXX-XXXX-XXXX"
                                   value="<?php echo esc_attr( $key ); ?>" required>
                            <button type="submit" class="wbl-btn-activate">فعال‌سازی</button>
                        </form>
                        <p class="wbl-portal-hint">کلید را از ایمیل خرید یا <a href="<?php echo $portal; ?>" target="_blank" rel="noopener">پورتال مشتری</a> دریافت کنید.</p>
                    </details>

                <?php endif; ?>

            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /* ─── پیام نتیجه (فعال‌سازی/خطا) ──────────────────────────── */
    protected static function notice_html() {
        $m = sanitize_key( $_GET['wbl_msg'] ?? '' );
        if ( $m === 'activated' )   return '<div class="notice notice-success is-dismissible"><p>✅ لایسنس با موفقیت فعال شد!</p></div>';
        if ( $m === 'deactivated' ) return '<div class="notice notice-info is-dismissible"><p>🔓 لایسنس از این سایت حذف شد.</p></div>';
        if ( $m === 'error' ) {
            $err = isset( $_GET['wbl_err'] ) ? esc_html( wp_unslash( $_GET['wbl_err'] ) ) : 'فعال‌سازی ناموفق بود.';
            return '<div class="notice notice-error is-dismissible"><p>❌ ' . $err . '</p></div>';
        }
        return '';
    }

    /* ─── ابزارها ──────────────────────────────────────────────── */
    protected static function o( $slug, $k ) { return 'wbl_' . $slug . '_' . $k; }
    protected static function days( $slug )  { return max( 0, (int) ( self::$products[ $slug ]['trial_days'] ?? 3 ) ); }

    public static function site_domain() {
        $host = wp_parse_url( home_url(), PHP_URL_HOST );
        return strtolower( preg_replace( '/^www\./i', '', (string) $host ) );
    }

    protected static function page_url( $slug ) {
        $cfg  = self::$products[ $slug ];
        $base = $cfg['page'] ?: ( 'admin.php?page=' . self::MENU_SLUG );
        $sep  = strpos( $base, '?' ) !== false ? '&' : '?';
        return admin_url( $base . $sep . 'wbl_product=' . rawurlencode( $slug ) );
    }

    protected static function result_url( $slug, array $res ) {
        $u = self::page_url( $slug ) . '&wbl_msg=' . ( $res['ok'] ? 'activated' : 'error' );
        if ( empty( $res['ok'] ) ) $u .= '&wbl_err=' . rawurlencode( $res['message'] );
        return $u;
    }

    public static function pay_url( $slug ) {
        $cfg = self::$products[ $slug ];
        return rtrim( $cfg['server'], '/' ) . '/pay/?' . http_build_query( [
            'plugin' => $slug,
            'domain' => self::site_domain(),
            'return' => self::page_url( $slug ),
        ] );
    }

    protected static function api( $slug, $action, array $body ) {
        $server = rtrim( self::$products[ $slug ]['server'] ?? self::SERVER_DEFAULT, '/' );
        $resp = wp_remote_post( $server . '/api/?action=' . rawurlencode( $action ), [
            'timeout' => 15,
            'headers' => [ 'Content-Type' => 'application/json' ],
            'body'    => wp_json_encode( $body ),
        ] );
        if ( is_wp_error( $resp ) ) {
            return [ 'success' => false, 'valid' => false, '_neterr' => true, 'message' => $resp->get_error_message() ];
        }
        $data = json_decode( wp_remote_retrieve_body( $resp ), true );
        return is_array( $data ) ? $data : [ 'success' => false, 'valid' => false, 'message' => 'پاسخ نامعتبر از سرور لایسنس.' ];
    }

    /* ─── به‌روزرسانی خودکار افزونه ────────────────────────────── */

    /** دریافت اطلاعات آخرین نسخه از سرور (با کش ۶ ساعته) */
    protected static function fetch_update( $slug ) {
        $cache = get_transient( 'wbl_upd_' . $slug );
        if ( $cache !== false ) return is_array( $cache ) ? $cache : [];

        $r = self::api( $slug, 'update', [
            'product'     => $slug,
            'version'     => self::$products[ $slug ]['version'] ?? '0',
            'domain'      => self::site_domain(),
            'license_key' => (string) get_option( self::o( $slug, 'key' ), '' ),
        ] );
        $data = ( ! empty( $r['success'] ) && ! empty( $r['version'] ) ) ? $r : [];
        set_transient( 'wbl_upd_' . $slug, $data, 6 * HOUR_IN_SECONDS );
        return $data;
    }

    /** تزریق نسخه‌ی جدید به لیست به‌روزرسانی‌های وردپرس */
    public static function check_update( $transient ) {
        if ( ! is_object( $transient ) ) return $transient;

        foreach ( self::$products as $slug => $cfg ) {
            if ( empty( $cfg['basename'] ) ) continue;
            $info = self::fetch_update( $slug );
            if ( empty( $info['version'] ) || empty( $info['package'] ) ) continue;

            $current = $cfg['version'] ?? '0';
            if ( version_compare( $info['version'], $current, '>' ) ) {
                if ( ! isset( $transient->response ) || ! is_array( $transient->response ) ) {
                    $transient->response = [];
                }
                $transient->response[ $cfg['basename'] ] = (object) [
                    'slug'         => dirname( $cfg['basename'] ),
                    'plugin'       => $cfg['basename'],
                    'new_version'  => $info['version'],
                    'package'      => $info['package'],
                    'url'          => $info['homepage']     ?? '',
                    'tested'       => $info['tested']       ?? '',
                    'requires'     => $info['requires']     ?? '',
                    'requires_php' => $info['requires_php'] ?? '',
                ];
            }
        }
        return $transient;
    }

    /** پنجره‌ی «مشاهده جزئیات» افزونه */
    public static function plugins_api( $res, $action, $args ) {
        if ( $action !== 'plugin_information' || empty( $args->slug ) ) return $res;

        foreach ( self::$products as $slug => $cfg ) {
            if ( empty( $cfg['basename'] ) || dirname( $cfg['basename'] ) !== $args->slug ) continue;
            $info = self::fetch_update( $slug );
            if ( empty( $info['version'] ) ) return $res;

            return (object) [
                'name'          => $cfg['name'] ?: $slug,
                'slug'          => $args->slug,
                'version'       => $info['version'],
                'requires'      => $info['requires']     ?? '',
                'tested'        => $info['tested']       ?? '',
                'requires_php'  => $info['requires_php'] ?? '',
                'download_link' => $info['package']      ?? '',
                'homepage'      => $info['homepage']     ?? '',
                'sections'      => [ 'changelog' => $info['changelog'] ?? '' ],
            ];
        }
        return $res;
    }

    protected static function err_fa( $code ) {
        $map = [
            'license_not_found'    => 'کلید لایسنس یافت نشد.',
            'license_revoked'      => 'این لایسنس غیرفعال شده است.',
            'license_expired'      => 'اعتبار این لایسنس منقضی شده است.',
            'product_mismatch'     => 'این لایسنس برای این افزونه نیست.',
            'domain_not_activated' => 'این لایسنس روی این دامنه فعال نشده است.',
            'already_activated'    => 'این لایسنس روی دامنه دیگری فعال است.',
        ];
        return $map[ $code ] ?? 'خطا در فعال‌سازی لایسنس.';
    }

    /* ─── CSS (یک بار چاپ می‌شود) ──────────────────────────────── */
    protected static function css() {
        if ( self::$css_printed ) return '';
        self::$css_printed = true;
        return <<<CSS
<style>
.wbl-box{max-width:520px;margin:0 auto 22px;background:#fff;border-radius:20px;overflow:hidden;
 box-shadow:0 10px 40px rgba(76,29,149,.12);font-family:Vazirmatn,Tahoma,sans-serif;direction:rtl}
.wbl-box *{box-sizing:border-box}
.wbl-hero{position:relative;padding:24px 24px 20px;color:#fff;overflow:hidden;
 background:linear-gradient(135deg,#7c3aed 0%,#4f46e5 55%,#2563eb 100%)}
.wbl-valid .wbl-hero{background:linear-gradient(135deg,#059669 0%,#0d9488 100%)}
.wbl-expired .wbl-hero{background:linear-gradient(135deg,#be123c 0%,#9333ea 100%)}
.wbl-hero-glow{position:absolute;top:-60px;left:-40px;width:200px;height:200px;border-radius:50%;
 background:rgba(255,255,255,.15);filter:blur(20px)}
.wbl-hero-row{position:relative;display:flex;align-items:center;gap:14px}
.wbl-hero-icon{font-size:36px;background:rgba(255,255,255,.18);width:58px;height:58px;border-radius:16px;
 display:flex;align-items:center;justify-content:center;flex-shrink:0}
.wbl-hero-main{flex:1;min-width:0}
.wbl-hero-name{font-size:18px;font-weight:800;margin-bottom:6px;line-height:1.4}
.wbl-pill{display:inline-block;font-size:11.5px;font-weight:700;padding:3px 12px;border-radius:99px;background:rgba(255,255,255,.22)}
.wbl-hero-price{text-align:center;flex-shrink:0}
.wbl-price{display:block;font-size:19px;font-weight:800;white-space:nowrap}
.wbl-price-sub{display:block;font-size:10.5px;opacity:.8;margin-top:2px}
.wbl-trial-bar{position:relative;height:7px;background:rgba(255,255,255,.22);border-radius:99px;margin-top:18px;overflow:hidden}
.wbl-trial-fill{height:100%;background:#fff;border-radius:99px;transition:width .4s}
.wbl-trial-text{font-size:12px;opacity:.92;margin-top:8px;text-align:center}
.wbl-body{padding:22px 24px 24px}
.wbl-features{list-style:none;margin:0 0 18px;padding:0;display:grid;gap:9px}
.wbl-features li{display:flex;align-items:center;gap:9px;font-size:13.5px;color:#334155;font-weight:500}
.wbl-features li span{width:20px;height:20px;border-radius:50%;background:#ede9fe;color:#7c3aed;
 display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:800;flex-shrink:0}
.wbl-btn-pay{display:flex;align-items:center;justify-content:center;gap:10px;width:100%;
 padding:16px;border:none;border-radius:14px;cursor:pointer;text-decoration:none;
 font-family:inherit;font-size:16px;font-weight:800;color:#fff;position:relative;
 background:linear-gradient(135deg,#7c3aed,#2563eb);box-shadow:0 8px 22px rgba(124,58,237,.4);
 transition:transform .15s,box-shadow .15s}
.wbl-btn-pay:hover{transform:translateY(-2px);box-shadow:0 12px 28px rgba(124,58,237,.5);color:#fff}
.wbl-btn-pay-ic{font-size:20px}
.wbl-btn-pay-amt{position:absolute;left:16px;font-size:12.5px;font-weight:700;background:rgba(255,255,255,.22);
 padding:3px 10px;border-radius:99px}
.wbl-secure{text-align:center;font-size:11.5px;color:#94a3b8;margin-top:12px;line-height:1.7}
.wbl-have-key{margin-top:18px;border-top:1px dashed #e2e8f0;padding-top:14px}
.wbl-have-key summary{cursor:pointer;font-size:12.5px;color:#7c3aed;font-weight:700;list-style:none;text-align:center}
.wbl-have-key summary::-webkit-details-marker{display:none}
.wbl-key-form{display:flex;gap:8px;margin-top:12px;flex-wrap:wrap}
.wbl-key-form input{flex:1;min-width:210px;padding:11px 13px;border:1.6px solid #e2e8f0;border-radius:10px;
 direction:ltr;font-family:monospace;font-size:13px;outline:none;transition:border-color .15s}
.wbl-key-form input:focus{border-color:#7c3aed;box-shadow:0 0 0 3px rgba(124,58,237,.12)}
.wbl-btn-activate{padding:11px 20px;border:none;border-radius:10px;cursor:pointer;font-family:inherit;
 font-size:13.5px;font-weight:700;color:#fff;background:#7c3aed;transition:background .15s}
.wbl-btn-activate:hover{background:#6d28d9}
.wbl-portal-hint{font-size:11.5px;color:#94a3b8;margin-top:10px;text-align:center}
.wbl-portal-hint a{color:#7c3aed;font-weight:700}
.wbl-valid-card{display:flex;align-items:center;gap:14px;background:#ecfdf5;border:1.5px solid #6ee7b7;
 border-radius:14px;padding:16px}
.wbl-valid-icon{font-size:30px}
.wbl-valid-title{font-size:15px;font-weight:800;color:#065f46}
.wbl-valid-sub{font-size:12.5px;color:#047857;margin-top:2px}
.wbl-key-show{direction:ltr;font-family:monospace;font-size:13px;color:#065f46;background:#f0fdf4;
 border:1px solid #bbf7d0;border-radius:10px;padding:11px;margin-top:12px;text-align:center;word-break:break-all;font-weight:700}
.wbl-btn-ghost{background:#fff;color:#ef4444;border:1.5px solid #fecaca;border-radius:10px;padding:9px 18px;
 cursor:pointer;font-family:inherit;font-size:12.5px;font-weight:700;transition:background .15s}
.wbl-btn-ghost:hover{background:#fef2f2}
</style>
CSS;
    }
}

endif;
