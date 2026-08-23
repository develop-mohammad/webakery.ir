<?php
defined( 'ABSPATH' ) || exit;

/**
 * پرتال مستقل حسابدار — بدون دسترسی به پیشخوان وردپرس.
 */
class WAP_Portal {

    const ROLE = 'wci_accountant';
    const CAP  = 'wap_view_reports';

    const PANEL_ACCOUNTANT = 'accountant';
    const PANEL_MANAGER    = 'manager';

    public static function panel_url( $type = null ) {
        if ( $type === null ) {
            $type = self::current_panel_type();
        }
        return home_url( $type === self::PANEL_MANAGER ? '/manager-panel/' : '/accountant-panel/' );
    }

    public static function manager_panel_url() {
        return self::panel_url( self::PANEL_MANAGER );
    }

    public static function init_ajax(): void {
        add_action( 'wp_ajax_wap_search_products', array( __CLASS__, 'ajax_search_products' ) );
    }

    public static function ajax_search_products(): void {
        if ( ! is_user_logged_in() || ! self::current_user_allowed() ) {
            wp_send_json_error( array( 'message' => 'Unauthorized' ), 403 );
        }
        if ( ! check_ajax_referer( 'wap_portal_search', 'nonce', false ) ) {
            wp_send_json_error( array( 'message' => 'Invalid nonce' ), 403 );
        }
        if ( ! class_exists( 'WAP_Order_Service' ) ) {
            wp_send_json_success( array() );
        }
        $term = isset( $_REQUEST['term'] ) ? wp_unslash( (string) $_REQUEST['term'] ) : '';
        $term = sanitize_text_field( $term );
        // sanitize_text_field گاهی فاصله‌های خاص را تغییر می‌دهد؛ نرمال فارسی در search_products انجام می‌شود
        wp_send_json_success( WAP_Order_Service::search_products( $term ) );
    }

    public static function current_panel_type() {
        $panel = get_query_var( 'wap_panel' );
        if ( $panel === self::PANEL_MANAGER || $panel === 'manager' ) {
            return self::PANEL_MANAGER;
        }
        return self::PANEL_ACCOUNTANT;
    }

    private static function export_url( array $params, string $export_type ): string {
        return add_query_arg(
            array_merge( $params, array( 'action' => 'wap_export', 'wap_export' => $export_type ) ),
            admin_url( 'admin-post.php' )
        );
    }

    /**
     * مشاهده یا دانلود فاکتور از پرتال (بدون نیاز به پیشخوان).
     */
    public static function handle_invoice_admin_post() {
        if ( ! is_user_logged_in() || ! self::current_user_allowed() ) {
            wp_die( 'دسترسی غیرمجاز.' );
        }
        if ( ! class_exists( 'WooCommerce' ) || ! class_exists( 'WCI_Invoice' ) ) {
            wp_die( 'فاکتور در دسترس نیست.' );
        }
        $order_id = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0;
        if ( $order_id <= 0 ) {
            wp_die( 'سفارش نامعتبر است.' );
        }
        check_admin_referer( 'wap_invoice_' . $order_id );

        $invoice = new WCI_Invoice( $order_id );
        if ( ! empty( $_GET['download'] ) ) {
            $invoice->download();
        } else {
            $invoice->render();
            exit;
        }
    }

    private static function render_export_bar( string $csv_url, bool $sheets = true ): void {
        ?>
        <div class="wap-export-bar">
            <span class="wap-export-label">خروجی گزارش:</span>
            <a class="wap-btn wap-btn-csv" href="<?php echo esc_url( $csv_url ); ?>">📥 CSV</a>
            <?php if ( $sheets ) : ?>
                <button type="button" class="wap-btn wap-btn-sheets" data-wap-sheets>📊 خروجی گوگل شیت</button>
            <?php endif; ?>
        </div>
        <?php
    }

    public static function render( $panel_type = null ) {
        nocache_headers();

        if ( function_exists( 'wp_raise_memory_limit' ) ) {
            wp_raise_memory_limit( 'admin' );
        }
        @ini_set( 'max_execution_time', '120' );

        $panel_type = $panel_type ?: self::current_panel_type();
        if ( ! in_array( $panel_type, array( self::PANEL_ACCOUNTANT, self::PANEL_MANAGER ), true ) ) {
            $panel_type = self::PANEL_ACCOUNTANT;
        }

        if ( ! is_user_logged_in() ) {
            self::render_login( $panel_type );
            return;
        }

        if ( $panel_type === self::PANEL_MANAGER && ! self::user_has_manager_access() ) {
            wp_safe_redirect( self::panel_url( self::PANEL_ACCOUNTANT ) );
            exit;
        }

        if ( ! self::user_has_access() ) {
            self::render_login( $panel_type );
            return;
        }

        if ( ! wap_is_active() ) {
            self::render_license_locked( $panel_type );
            return;
        }

        self::render_dashboard( $panel_type );
    }

    /**
     * هندل admin-post برای کارهای دسته‌جمعی پرتال — بدون رندر HTML پرتال (رفع صفحه سفید).
     */
    public static function handle_bulk_orders_admin_post() {
        if ( ! is_user_logged_in() || ! self::current_user_allowed() ) {
            wp_die( 'دسترسی غیرمجاز.' );
        }
        if (
            ! class_exists( 'WAP_Order_Service' )
            || ! isset( $_POST['wci_bulk_apply'] )
            || ! check_admin_referer( 'wci_bulk_orders' )
        ) {
            wp_die( 'درخواست نامعتبر است.' );
        }

        $bulk_action = WAP_Order_Service::bulk_action_from_request();
        $bulk_ids    = class_exists( 'WCI_Bulk_Invoice' )
            ? WCI_Bulk_Invoice::parse_order_ids( wp_unslash( $_POST ) )
            : array_map( 'absint', (array) ( $_POST['order_ids'] ?? array() ) );

        if ( in_array( $bulk_action, array( 'print_invoices_filtered', 'download_invoices_filtered' ), true ) ) {
            $tmp_f = WAP_Data::get_order_list_filters();
            list( , , $all_for_print ) = WAP_Data::get_filtered_order_list( $tmp_f );
            $bulk_ids = array();
            foreach ( (array) $all_for_print as $o ) {
                if ( is_object( $o ) && method_exists( $o, 'get_id' ) ) {
                    $bulk_ids[] = (int) $o->get_id();
                }
            }
        }

        $result = WAP_Order_Service::process_bulk_action( $bulk_action, $bulk_ids );

        if (
            ! empty( $result['ok'] )
            && ! empty( $result['order_ids'] )
            && ! empty( $result['mode'] )
            && class_exists( 'WCI_Bulk_Invoice' )
        ) {
            WCI_Bulk_Invoice::serve( (array) $result['order_ids'], (string) $result['mode'] );
        }

        $return = isset( $_POST['wap_return_url'] )
            ? esc_url_raw( wp_unslash( $_POST['wap_return_url'] ) )
            : self::panel_url();
        if ( ! $return ) {
            $return = self::panel_url();
        }

        $redirect = add_query_arg(
            array(
                'wap_view'    => 'orders',
                'wap_bulk_ok' => ! empty( $result['ok'] ) ? '1' : '0',
                'wap_bulk_msg'=> rawurlencode( (string) ( $result['message'] ?? '' ) ),
            ),
            $return
        );
        wp_safe_redirect( $redirect );
        exit;
    }

    private static function render_license_locked( $panel_type = self::PANEL_ACCOUNTANT ) {
        self::head( 'لایسنس لازم است', $panel_type );
        $title = $panel_type === self::PANEL_MANAGER ? 'پرتال مدیر' : 'پرتال حسابدار';
        ?>
        <div class="wap-login-wrap">
            <div class="wap-login-box">
                <div class="wap-logo">🧾 <?php echo esc_html( $title ); ?></div>
                <div class="wap-alert">دوره‌ی آزمایشی این افزونه تمام شده — برای ادامه‌ی استفاده، لایسنس را از پیشخوان وردپرس (مدیر سایت) فعال کنید.</div>
            </div>
        </div>
        <?php
        self::foot();
    }

    public static function current_user_allowed() {
        return self::user_has_access( wp_get_current_user() );
    }

    // آیا این کاربر (نقش اختصاصی حسابدار، مدیر سایت، یا یکی از نقش‌های مجاز انتخاب‌شده) دسترسی به پرتال دارد؟
    public static function user_has_access( $user = null ) {
        if ( ! $user ) {
            $user = wp_get_current_user();
        }
        if ( ! $user || ! $user->exists() ) return false;
        $roles = (array) $user->roles;
        if ( in_array( self::ROLE, $roles, true ) || in_array( 'administrator', $roles, true ) ) return true;
        $allowed = get_option( 'wap_allowed_roles', array() );
        if ( ! is_array( $allowed ) ) {
            $allowed = array();
        }
        return (bool) array_intersect( $roles, $allowed );
    }

    /** مدیر سایت یا نقش‌های مجاز در تنظیمات — دسترسی به پنل مدیر */
    public static function user_has_manager_access( $user = null ) {
        if ( ! $user ) {
            $user = wp_get_current_user();
        }
        if ( ! $user || ! $user->exists() ) {
            return false;
        }
        $roles = (array) $user->roles;
        if ( in_array( 'administrator', $roles, true ) ) {
            return true;
        }
        $allowed = get_option( 'wap_allowed_roles', array() );
        if ( ! is_array( $allowed ) ) {
            $allowed = array();
        }
        return (bool) array_intersect( $roles, $allowed );
    }

    /**
     * خروجی‌ها از طریق admin-post.php سرو می‌شوند (نه مستقیم از خود آدرس /accountant-panel/)
     * — همان مسیر مطمئنی که ورود/خروج حسابدار هم استفاده می‌کند، تا وابسته به رفتار
     * rewrite سفارشی صفحه (که با پارامترهای اضافه در query string قابل‌اعتماد نبود) نباشد.
     */
    public static function handle_export_admin_post() {
        if ( ! is_user_logged_in() || ! self::current_user_allowed() ) {
            wp_die( 'دسترسی غیرمجاز.' );
        }
        self::handle_export();
        exit;
    }

    private static function handle_export() {
        $type = sanitize_text_field( $_GET['wap_export'] );

        if ( $type === 'orders_csv' ) {
            $f = WAP_Data::get_order_list_filters();
            list( , , $all_orders ) = WAP_Data::get_filtered_order_list( $f );
            WAP_Export::orders_csv( $all_orders );
            return;
        }

        if ( $type === 'products_csv' || $type === 'product_orders_csv' ) {
            $f          = WAP_Data::get_filters();
            $orders     = WAP_Data::get_orders( $f );
            $paid_only  = WAP_Data::should_require_paid( $f );
            $product_id = ! empty( $_GET['product_id'] ) ? (int) $_GET['product_id'] : 0;
            if ( $type === 'product_orders_csv' && $product_id ) {
                WAP_Export::product_orders_csv( $orders, $product_id, $paid_only );
            } else {
                WAP_Export::products_csv( $orders, $paid_only, (int) ( $f['product_cat'] ?? 0 ) );
            }
            return;
        }

        if ( $type === 'buyers_csv' ) {
            $f          = WAP_Data::get_filters();
            $product_ids = ! empty( $f['product_ids'] ) ? $f['product_ids'] : WAP_Data::parse_ids( $_GET['product_ids'] ?? array() );
            $orders     = WAP_Data::get_orders( $f );
            $buyers     = WAP_Data::get_buyers_by_products( $orders, $product_ids, WAP_Data::should_require_paid( $f ) );
            WAP_Export::buyers_csv( $buyers );
            return;
        }

        $f      = WAP_Data::get_filters();
        $orders = WAP_Data::get_orders( $f );
        $groups = WAP_Data::apply_filter( WAP_Data::build_rows( $orders, $f['period'] ), $f );

        switch ( $type ) {
            case 'csv':
                WAP_Export::csv( $groups );
                break;
            case 'xml':
                WAP_Export::xml( $groups );
                break;
            case 'pdf':
                $summary = 'بازه: ' . ( $f['date_from'] ?: '—' ) . ' تا ' . ( $f['date_to'] ?: '—' );
                WAP_Export::print_view( $groups, $summary );
                break;
        }
    }

    private static function head( $title, $panel_type = self::PANEL_ACCOUNTANT ) {
        ?>
        <!DOCTYPE html>
        <html dir="rtl" lang="fa" class="wap-panel-<?php echo esc_attr( $panel_type ); ?>">
        <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex, nofollow">
        <meta name="theme-color" content="<?php echo $panel_type === self::PANEL_MANAGER ? '#2563eb' : '#059669'; ?>">
        <title><?php echo esc_html( $title ); ?></title>
        <link rel="preconnect" href="https://fonts.bunny.net" crossorigin>
        <link rel="stylesheet" href="https://fonts.bunny.net/css?family=vazirmatn:400,500,600,700&display=swap">
        <link rel="stylesheet" href="<?php echo esc_url( WAP_URL . 'assets/style.css?v=' . WAP_VERSION ); ?>">
        </head>
        <body class="wap-body wap-body-<?php echo esc_attr( $panel_type ); ?>">
        <?php
    }

    private static function foot() {
        $today = WAP_Jalali::today();
        $params = array(
            'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
            'nonce'     => wp_create_nonce( 'wap_sheets_export' ),
            'searchNonce' => wp_create_nonce( 'wap_portal_search' ),
            'view'      => self::current_view(),
            'productId' => ! empty( $_GET['product_id'] ) ? (int) $_GET['product_id'] : 0,
            'query'     => array_map( 'sanitize_text_field', wp_unslash( $_GET ) ),
        );
        ?>
        <script>window.WAP_TODAY = <?php echo wp_json_encode( $today ); ?>;</script>
        <script>window.WAP_SHEETS = <?php echo wp_json_encode( $params ); ?>;</script>
        <script src="<?php echo esc_url( WAP_URL . 'assets/jalali-calendar.js?v=' . WAP_VERSION ); ?>"></script>
        <script src="<?php echo esc_url( WAP_URL . 'assets/app.js?v=' . WAP_VERSION ); ?>"></script>
        </body></html>
        <?php
    }

    private static function render_login( $panel_type = self::PANEL_ACCOUNTANT ) {
        $is_manager = $panel_type === self::PANEL_MANAGER;
        self::head( $is_manager ? 'ورود مدیر' : 'ورود حسابدار', $panel_type );
        $error = isset( $_GET['wap_error'] );
        $title = $is_manager ? 'پرتال مدیر' : 'پرتال حسابدار';
        ?>
        <div class="wap-login-wrap">
            <div class="wap-login-bg" aria-hidden="true">
                <span class="wap-login-orb wap-login-orb--1"></span>
                <span class="wap-login-orb wap-login-orb--2"></span>
            </div>
            <div class="wap-login-box">
                <div class="wap-logo">
                    <div class="wap-logo-icon"><?php echo $is_manager ? '🛡️' : '🧾'; ?></div>
                    <div class="wap-logo-title"><?php echo esc_html( $title ); ?></div>
                    <div class="wap-logo-sub"><?php echo $is_manager ? 'مدیریت فروش، گزارش‌ها و دسترسی پیشخوان' : 'گزارش مالی، سفارش‌ها و خروجی حسابداری'; ?></div>
                </div>
                <?php if ( $error ) : ?>
                    <div class="wap-alert">نام کاربری، رمز عبور یا دسترسی نامعتبر است.</div>
                <?php endif; ?>
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                    <input type="hidden" name="action" value="wap_login">
                    <input type="hidden" name="wap_panel" value="<?php echo esc_attr( $panel_type ); ?>">
                    <?php wp_nonce_field( 'wap_login_action', 'wap_login_nonce' ); ?>
                    <label>نام کاربری یا ایمیل</label>
                    <input type="text" name="wap_user" required autofocus>
                    <label>رمز عبور</label>
                    <input type="password" name="wap_pass" required>
                    <button type="submit" class="wap-btn wap-btn-primary">ورود</button>
                </form>
                <?php if ( $is_manager ) : ?>
                    <p class="wap-login-alt"><a href="<?php echo esc_url( self::panel_url( self::PANEL_ACCOUNTANT ) ); ?>">ورود از پنل حسابدار</a></p>
                <?php else : ?>
                    <p class="wap-login-alt"><a href="<?php echo esc_url( self::manager_panel_url() ); ?>">ورود از پنل مدیر</a></p>
                <?php endif; ?>
            </div>
        </div>
        <?php
        self::foot();
    }

    // ذخیره فرمول سفارشی حسابدار (POST مستقیم به همین صفحه)
    private static function maybe_save_formula() {
        if ( ! isset( $_POST['wap_save_formula'] ) ) return;
        if ( ! isset( $_POST['wap_formula_nonce'] ) || ! wp_verify_nonce( $_POST['wap_formula_nonce'], 'wap_save_formula' ) ) return;
        update_option( 'wap_custom_formula', sanitize_text_field( wp_unslash( $_POST['wap_formula'] ?? '' ) ) );
    }

    private static function current_view(): string {
        $raw = '';
        if ( isset( $_POST['wap_view'] ) ) {
            $raw = wp_unslash( $_POST['wap_view'] );
        } elseif ( isset( $_GET['wap_view'] ) ) {
            $raw = wp_unslash( $_GET['wap_view'] );
        }
        $view = sanitize_text_field( $raw ?: 'sales' );
        return in_array( $view, array( 'sales', 'orders', 'products', 'buyers' ), true ) ? $view : 'sales';
    }

    private static function render_dashboard( $panel_type = self::PANEL_ACCOUNTANT ) {
        self::maybe_save_formula();
        $is_manager = $panel_type === self::PANEL_MANAGER;
        self::head( $is_manager ? 'پنل مدیر' : 'گزارش فروش', $panel_type );
        $view = self::current_view();
        $brand = $is_manager ? '🛡️ پرتال مدیر' : '🧾 پرتال حسابدار';
        ?>
        <div class="wap-wrap wap-panel-<?php echo esc_attr( $panel_type ); ?>">
            <header class="wap-header" id="wap-header">
                <div class="wap-brand-wrap">
                    <div class="wap-brand"><?php echo esc_html( $brand ); ?></div>
                    <div class="wap-brand-sub"><?php
                        if ( $view === 'orders' ) {
                            echo 'لیست و جزئیات سفارش‌های پرداخت‌شده';
                        } elseif ( $view === 'products' ) {
                            echo 'تعداد فروش محصول بر اساس بازه، وضعیت و دسته‌بندی';
                        } elseif ( $view === 'buyers' ) {
                            echo 'مشتریانی که محصولات انتخابی را خریده‌اند';
                        } else {
                            echo 'خلاصه فروش و خروجی‌های حسابداری';
                        }
                    ?></div>
                </div>
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="wap-logout-form">
                    <input type="hidden" name="action" value="wap_logout">
                    <input type="hidden" name="wap_panel" value="<?php echo esc_attr( $panel_type ); ?>">
                    <?php wp_nonce_field( 'wap_logout_action', 'wap_logout_nonce' ); ?>
                    <span class="wap-user">👤 <?php echo esc_html( wp_get_current_user()->display_name ); ?></span>
                    <button type="submit" class="wap-btn wap-btn-ghost">خروج</button>
                </form>
            </header>

            <?php if ( self::user_has_manager_access() ) : ?>
            <nav class="wap-panel-switch" aria-label="انتخاب پنل">
                <a href="<?php echo esc_url( self::panel_url( self::PANEL_ACCOUNTANT ) ); ?>" class="wap-panel-switch-link<?php echo ! $is_manager ? ' is-active' : ''; ?>">🧾 پنل حسابدار</a>
                <a href="<?php echo esc_url( self::manager_panel_url() ); ?>" class="wap-panel-switch-link<?php echo $is_manager ? ' is-active' : ''; ?>">🛡️ پنل مدیر</a>
            </nav>
            <?php endif; ?>

            <?php if ( $is_manager ) : ?>
            <div class="wap-manager-bar">
                <?php if ( current_user_can( 'manage_options' ) ) : ?>
                    <a class="wap-btn wap-btn-ghost" href="<?php echo esc_url( admin_url() ); ?>" target="_blank">🏠 پیشخوان وردپرس</a>
                    <a class="wap-btn wap-btn-ghost" href="<?php echo esc_url( admin_url( 'admin.php?page=wap-accountants' ) ); ?>" target="_blank">👥 مدیریت حسابداران</a>
                    <a class="wap-btn wap-btn-ghost" href="<?php echo esc_url( admin_url( 'admin.php?page=wci-order-edit' ) ); ?>" target="_blank">➕ سفارش جدید</a>
                    <a class="wap-btn wap-btn-ghost" href="<?php echo esc_url( admin_url( 'admin.php?page=wci-reports' ) ); ?>" target="_blank">📊 گزارش مالی (پیشخوان)</a>
                <?php else : ?>
                    <a class="wap-btn wap-btn-ghost" href="<?php echo esc_url( add_query_arg( 'wap_view', 'sales', self::panel_url() ) ); ?>">📊 گزارش مالی</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php if ( ! class_exists( 'WooCommerce' ) ) : ?>
                <div class="wap-alert">ووکامرس روی این سایت فعال نیست.</div>
            <?php else : ?>

            <nav class="wap-tabs">
                <a href="<?php echo esc_url( add_query_arg( 'wap_view', 'sales', self::panel_url() ) ); ?>" class="wap-tab<?php echo $view === 'sales' ? ' wap-tab-active' : ''; ?>">📈 گزارش مالی</a>
                <a href="<?php echo esc_url( add_query_arg( 'wap_view', 'orders', self::panel_url() ) ); ?>" class="wap-tab<?php echo $view === 'orders' ? ' wap-tab-active' : ''; ?>">🧾 لیست سفارش‌ها</a>
                <a href="<?php echo esc_url( add_query_arg( 'wap_view', 'products', self::panel_url() ) ); ?>" class="wap-tab<?php echo $view === 'products' ? ' wap-tab-active' : ''; ?>">📦 فروش محصولات</a>
                <a href="<?php echo esc_url( add_query_arg( 'wap_view', 'buyers', self::panel_url() ) ); ?>" class="wap-tab<?php echo $view === 'buyers' ? ' wap-tab-active' : ''; ?>">👥 خریداران محصول</a>
            </nav>

            <?php
            try {
                if ( $view === 'orders' ) {
                    self::render_orders_tab();
                } elseif ( $view === 'products' ) {
                    self::render_products_tab();
                } elseif ( $view === 'buyers' ) {
                    self::render_buyers_tab();
                } else {
                    self::render_sales_tab();
                }
            } catch ( Throwable $e ) {
                if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                    echo '<div class="wap-alert">' . esc_html( 'Hesabdar: ' . $e->getMessage() ) . '</div>';
                } else {
                    echo '<div class="wap-alert">بارگذاری گزارش با خطا مواجه شد. بازه تاریخ را کوچک‌تر کنید یا با مدیر سایت تماس بگیرید.</div>';
                }
                error_log( 'Hesabdar portal tab error: ' . $e->getMessage() );
            }
            ?>

            <?php endif; ?>
        </div>
        <?php
        self::foot();
    }

    private static function render_sales_tab() {
        $f          = WAP_Data::get_filters();
        $orders     = WAP_Data::get_orders( $f );
        $groups_all = WAP_Data::build_rows( $orders, $f['period'] );
        $groups     = WAP_Data::apply_filter( $groups_all, $f );
        $presets    = WAP_Data::quick_presets();
        $gn         = WAP_Data::gross_vs_net( $orders );

        $overall_total = $gn['net_total'];
        $overall_count = $gn['net_count'];

        $currency = class_exists( 'WooCommerce' ) ? get_woocommerce_currency_symbol() : '';

        // فرمول سفارشی
        $formula      = get_option( 'wap_custom_formula', '' );
        $formula_vars = array(
            'GROSS'       => $gn['gross_total'],
            'GROSS_COUNT' => $gn['gross_count'],
            'NET'         => $gn['net_total'],
            'NET_COUNT'   => $gn['net_count'],
            'REFUNDED'    => $gn['gross_total'] - $gn['net_total'],
            'AVG'         => $gn['net_count'] > 0 ? $gn['net_total'] / $gn['net_count'] : 0,
        );
        $formula_result = $formula !== '' ? WAP_Formula::evaluate( $formula, $formula_vars ) : null;

        $base_params = array_filter( $f, function( $v ) { return $v !== null && $v !== ''; } );
        $csv_url = self::export_url( $base_params, 'csv' );
        $xml_url = self::export_url( $base_params, 'xml' );
        $pdf_url = self::export_url( $base_params, 'pdf' );
        ?>
            <form method="get" action="<?php echo esc_url( self::panel_url() ); ?>" class="wap-filters">
                <div class="wap-field">
                    <label>گروه‌بندی</label>
                    <select name="period">
                        <?php foreach ( array( 'day' => 'روزانه', 'week' => 'هفتگی', 'month' => 'ماهانه', 'quarter' => 'فصلی', 'year' => 'سالانه' ) as $val => $lbl ) : ?>
                            <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $f['period'], $val ); ?>><?php echo esc_html( $lbl ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="wap-field">
                    <label>وضعیت سفارش</label>
                    <select name="order_status">
                        <option value="">همه</option>
                        <?php foreach ( wc_get_order_statuses() as $slug => $label ) :
                            $val = str_replace( 'wc-', '', $slug ); ?>
                            <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $f['order_status'], $val ); ?>><?php echo esc_html( $label ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="wap-field wap-field-date">
                    <label>از تاریخ (شمسی)</label>
                    <input type="text" id="wap_date_from" name="date_from" value="<?php echo esc_attr( $f['date_from'] ); ?>" placeholder="۱۴۰۳/۰۱/۰۱" autocomplete="off">
                </div>
                <div class="wap-field wap-field-date">
                    <label>تا تاریخ (شمسی)</label>
                    <input type="text" id="wap_date_to" name="date_to" value="<?php echo esc_attr( $f['date_to'] ); ?>" placeholder="۱۴۰۳/۱۲/۲۹" autocomplete="off">
                </div>
                <div class="wap-field">
                    <label>مبلغ فروش دوره (حداقل / حداکثر)</label>
                    <div class="wap-inline">
                        <input type="number" step="any" name="min_total" value="<?php echo esc_attr( $f['min_total'] ?? '' ); ?>" placeholder="حداقل">
                        <input type="number" step="any" name="max_total" value="<?php echo esc_attr( $f['max_total'] ?? '' ); ?>" placeholder="حداکثر">
                    </div>
                </div>
                <div class="wap-field">
                    <label>تعداد فروش دوره (حداقل / حداکثر)</label>
                    <div class="wap-inline">
                        <input type="number" name="min_count" value="<?php echo esc_attr( $f['min_count'] ?? '' ); ?>" placeholder="حداقل">
                        <input type="number" name="max_count" value="<?php echo esc_attr( $f['max_count'] ?? '' ); ?>" placeholder="حداکثر">
                    </div>
                </div>
                <div class="wap-field wap-field-actions">
                    <button type="submit" class="wap-btn wap-btn-primary">اعمال فیلتر</button>
                    <a href="<?php echo esc_url( self::panel_url() ); ?>" class="wap-btn wap-btn-ghost">پاک کردن</a>
                </div>
            </form>

            <div class="wap-presets" id="wap_presets">
                <?php foreach ( $presets as $label => $range ) : ?>
                    <button type="button" class="wap-chip" data-from="<?php echo esc_attr( $range[0] ); ?>" data-to="<?php echo esc_attr( $range[1] ); ?>"><?php echo esc_html( $label ); ?></button>
                <?php endforeach; ?>
            </div>

            <div class="wap-cards">
                <div class="wap-card">
                    <span class="wap-card-icon">📦</span>
                    <span class="wap-card-label">تعداد سفارش موفق</span>
                    <span class="wap-card-value"><?php echo esc_html( number_format( $overall_count ) ); ?></span>
                </div>
                <div class="wap-card wap-card-accent">
                    <span class="wap-card-icon">💰</span>
                    <span class="wap-card-label">مجموع فروش (سفارش‌های موفق)</span>
                    <span class="wap-card-value"><?php echo esc_html( number_format( $overall_total ) . ' ' . $currency ); ?></span>
                </div>
                <div class="wap-card wap-card-net">
                    <span class="wap-card-icon">📊</span>
                    <span class="wap-card-label">میانگین هر سفارش موفق</span>
                    <span class="wap-card-value"><?php echo esc_html( number_format( $overall_count > 0 ? $overall_total / $overall_count : 0 ) . ' ' . $currency ); ?></span>
                </div>
            </div>

            <?php if ( ! empty( $groups ) ) : $max_total = max( array_column( $groups, 'total' ) ); ?>
            <div class="wap-chart-card">
                <h3 class="wap-section-title">📈 نمودار فروش بر اساس دوره</h3>
                <div class="wap-chart">
                    <?php foreach ( $groups as $g ) :
                        $pct = $max_total > 0 ? round( $g['total'] / $max_total * 100 ) : 0; ?>
                        <div class="wap-bar-col">
                            <div class="wap-bar-track">
                                <div class="wap-bar-fill" style="height:<?php echo (int) $pct; ?>%">
                                    <span class="wap-bar-tip"><?php echo esc_html( number_format( $g['total'] ) ); ?></span>
                                </div>
                            </div>
                            <span class="wap-bar-label"><?php echo esc_html( $g['label'] ); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <div class="wap-formula-card">
                <h3 class="wap-section-title">🧮 فرمول سفارشی (شبیه اکسل)</h3>
                <p class="wap-formula-hint">
                    متغیرهای در دسترس برای بازه فعلی: <code>GROSS</code> (فروش ناخالص)، <code>NET</code> (فروش خالص)، <code>GROSS_COUNT</code>، <code>NET_COUNT</code>، <code>REFUNDED</code> (مبلغ لغو/مستردشده)، <code>AVG</code> (میانگین سفارش خالص).
                    توابع: <code>ROUND(x,n)</code>، <code>ABS(x)</code>، <code>MIN(...)</code>، <code>MAX(...)</code>، <code>SUM(...)</code>، <code>IF(شرط، آنگاه، وگرنه)</code>.
                    مثال: <code>ROUND((NET - REFUNDED) / NET_COUNT, 0)</code>
                </p>
                <form method="post" action="<?php echo esc_url( add_query_arg( $base_params, self::panel_url() ) ); ?>" class="wap-formula-form">
                    <?php wp_nonce_field( 'wap_save_formula', 'wap_formula_nonce' ); ?>
                    <input type="text" name="wap_formula" class="wap-formula-input" placeholder="مثال: ROUND(NET / NET_COUNT, 0)" value="<?php echo esc_attr( $formula ); ?>" dir="ltr">
                    <button type="submit" name="wap_save_formula" value="1" class="wap-btn wap-btn-primary">محاسبه و ذخیره</button>
                </form>
                <?php if ( $formula_result !== null ) : ?>
                    <?php if ( $formula_result['ok'] ) : ?>
                        <div class="wap-formula-result">نتیجه: <strong><?php echo esc_html( number_format( $formula_result['value'], 2 ) ); ?></strong></div>
                    <?php else : ?>
                        <div class="wap-formula-result wap-formula-error">خطا در فرمول: <?php echo esc_html( $formula_result['error'] ); ?></div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <div class="wap-export-bar">
                <span class="wap-export-label">خروجی گزارش:</span>
                <a class="wap-btn wap-btn-csv" href="<?php echo esc_url( $csv_url ); ?>">📥 CSV</a>
                <a class="wap-btn wap-btn-xml" href="<?php echo esc_url( $xml_url ); ?>">XML</a>
                <a class="wap-btn wap-btn-pdf" href="<?php echo esc_url( $pdf_url ); ?>" target="_blank">PDF</a>
                <button type="button" class="wap-btn wap-btn-jpeg" id="wap_export_jpeg">JPEG</button>
                <button type="button" class="wap-btn wap-btn-sheets" data-wap-sheets>📊 خروجی گوگل شیت</button>
            </div>

            <div class="wap-table-wrap" id="wap_capture">
                <table class="wap-table">
                    <thead><tr><th>دوره</th><th>تعداد فروش</th><th>مبلغ فروش</th><th>میانگین هر سفارش</th></tr></thead>
                    <tbody>
                    <?php if ( empty( $groups ) ) : ?>
                        <tr><td colspan="4" class="wap-empty">داده‌ای برای این بازه/فیلتر یافت نشد.</td></tr>
                    <?php else : foreach ( $groups as $g ) :
                        $avg = $g['count'] > 0 ? $g['total'] / $g['count'] : 0; ?>
                        <tr>
                            <td><?php echo esc_html( $g['label'] ); ?></td>
                            <td><?php echo esc_html( number_format( $g['count'] ) ); ?></td>
                            <td><?php echo esc_html( number_format( $g['total'] ) . ' ' . $currency ); ?></td>
                            <td><?php echo esc_html( number_format( $avg ) . ' ' . $currency ); ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        <?php
    }

    private static function render_orders_tab() {
        $bulk_msg = '';
        if ( isset( $_GET['wap_bulk_msg'] ) ) {
            $bulk_msg = sanitize_text_field( wp_unslash( rawurldecode( (string) $_GET['wap_bulk_msg'] ) ) );
        }

        $f              = WAP_Data::get_order_list_filters();
        list( $orders, $total, $all_orders ) = WAP_Data::get_filtered_order_list( $f );
        $presets        = WAP_Data::quick_presets();
        $payment_methods = WAP_Data::get_payment_methods();
        $status_counts  = WAP_Data::status_breakdown( $all_orders );
        $currency       = get_woocommerce_currency_symbol();
        $per_page       = $f['per_page'];
        $paged          = $f['paged'];
        $total_pages    = $per_page > 0 ? (int) ceil( $total / $per_page ) : 1;

        $base_params = array_filter( $f, function( $v ) { return $v !== null && $v !== ''; } );
        unset( $base_params['paged'] );
        $csv_url = self::export_url( $base_params, 'orders_csv' );
        $can_edit_orders = current_user_can( 'manage_options' ) && class_exists( 'WAP_Order_Service' ) && WAP_Order_Service::can_manage();
        $can_bulk        = class_exists( 'WAP_Order_Service' ) && WAP_Order_Service::can_change_status();
        ?>
        <?php if ( $bulk_msg !== '' ) : ?>
            <div class="wap-alert <?php echo ! empty( $_GET['wap_bulk_ok'] ) ? 'wap-alert-success' : 'wap-alert-error'; ?>"><?php echo esc_html( $bulk_msg ); ?></div>
        <?php endif; ?>
        <form method="get" action="<?php echo esc_url( self::panel_url() ); ?>" class="wap-filters">
            <input type="hidden" name="wap_view" value="orders">
            <div class="wap-field">
                <label>جستجو</label>
                <input type="text" name="s" value="<?php echo esc_attr( $f['s'] ); ?>" placeholder="نام، ایمیل، شماره تماس...">
            </div>
            <div class="wap-field">
                <label>روش پرداخت</label>
                <select name="payment_method">
                    <option value="">همه</option>
                    <?php foreach ( $payment_methods as $m ) : ?>
                        <option value="<?php echo esc_attr( $m ); ?>" <?php selected( $f['payment_method'], $m ); ?>><?php echo esc_html( WAP_Data::payment_label( $m ) ); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="wap-field">
                <label>وضعیت سفارش</label>
                <select name="order_status">
                    <option value="">همه</option>
                    <?php foreach ( wc_get_order_statuses() as $slug => $label ) :
                        $val = str_replace( 'wc-', '', $slug ); ?>
                        <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $f['order_status'], $val ); ?>><?php echo esc_html( $label ); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="wap-field wap-field-date">
                <label>از تاریخ (شمسی)</label>
                <input type="text" id="wap_date_from" name="date_from" value="<?php echo esc_attr( $f['date_from'] ); ?>" placeholder="۱۴۰۳/۰۱/۰۱" autocomplete="off">
            </div>
            <div class="wap-field wap-field-date">
                <label>تا تاریخ (شمسی)</label>
                <input type="text" id="wap_date_to" name="date_to" value="<?php echo esc_attr( $f['date_to'] ); ?>" placeholder="۱۴۰۳/۱۲/۲۹" autocomplete="off">
            </div>
            <div class="wap-field">
                <label>هر صفحه</label>
                <select name="per_page">
                    <?php foreach ( array( 10, 25, 50, 100 ) as $n ) : ?>
                        <option value="<?php echo $n; ?>" <?php selected( $per_page, $n ); ?>><?php echo $n; ?> مورد</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="wap-field wap-field-actions">
                <button type="submit" class="wap-btn wap-btn-primary">اعمال فیلتر</button>
                <a href="<?php echo esc_url( add_query_arg( 'wap_view', 'orders', self::panel_url() ) ); ?>" class="wap-btn wap-btn-ghost">پاک کردن</a>
            </div>
        </form>

        <div class="wap-presets" id="wap_presets">
            <?php foreach ( $presets as $label => $range ) : ?>
                <button type="button" class="wap-chip" data-from="<?php echo esc_attr( $range[0] ); ?>" data-to="<?php echo esc_attr( $range[1] ); ?>"><?php echo esc_html( $label ); ?></button>
            <?php endforeach; ?>
        </div>

        <?php if ( ! empty( $status_counts ) ) : ?>
        <div class="wap-chart-card wap-donut-card">
            <h3 class="wap-section-title">🥧 وضعیت سفارش‌ها</h3>
            <?php
            $total_for_donut = array_sum( $status_counts );
            $gradient_parts  = array();
            $palette = array( '#1d7044', '#34c98a', '#2e86de', '#e08e0b', '#c0392b', '#6c5ce7', '#97a5b0', '#8a94a3' );
            $angle = 0; $i = 0;
            foreach ( $status_counts as $status => $count ) {
                $slice_deg = $total_for_donut > 0 ? ( $count / $total_for_donut ) * 360 : 0;
                $color = $palette[ $i % count( $palette ) ];
                $gradient_parts[] = $color . ' ' . round( $angle, 2 ) . 'deg ' . round( $angle + $slice_deg, 2 ) . 'deg';
                $angle += $slice_deg;
                $i++;
            }
            ?>
            <div class="wap-donut-wrap">
                <div class="wap-donut" style="background:conic-gradient(<?php echo esc_attr( implode( ', ', $gradient_parts ) ); ?>)">
                    <div class="wap-donut-hole"><?php echo esc_html( number_format( $total_for_donut ) ); ?><span>سفارش</span></div>
                </div>
                <ul class="wap-donut-legend">
                    <?php $i = 0; foreach ( $status_counts as $status => $count ) :
                        $color = $palette[ $i % count( $palette ) ]; $i++; ?>
                        <li><span class="wap-legend-dot" style="background:<?php echo esc_attr( $color ); ?>"></span> <?php echo esc_html( wc_get_order_status_name( $status ) ); ?> — <strong><?php echo esc_html( number_format( $count ) ); ?></strong></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
        <?php endif; ?>

        <div class="wap-export-bar">
            <span class="wap-export-label">خروجی گزارش:</span>
            <a class="wap-btn wap-btn-csv" href="<?php echo esc_url( $csv_url ); ?>">📥 CSV</a>
            <button type="button" class="wap-btn wap-btn-sheets" data-wap-sheets>📊 خروجی گوگل شیت</button>
        <?php if ( $can_edit_orders ) : ?>
            <a class="wap-btn wap-btn-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=wci-order-edit' ) ); ?>" target="_blank">➕ سفارش جدید</a>
        <?php endif; ?>
        </div>

        <form method="post" id="wap-orders-bulk-form" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <?php wp_nonce_field( 'wci_bulk_orders' ); ?>
            <input type="hidden" name="action" value="wap_bulk_orders">
            <input type="hidden" name="wap_return_url" value="<?php echo esc_url( add_query_arg( 'wap_view', 'orders', self::panel_url() ) ); ?>">
            <input type="hidden" name="wap_view" value="orders">
            <?php foreach ( $f as $fk => $fv ) : ?>
                <input type="hidden" name="<?php echo esc_attr( $fk ); ?>" value="<?php echo esc_attr( (string) $fv ); ?>">
            <?php endforeach; ?>

        <?php if ( $can_bulk ) : ?>
        <div class="wap-bulk-bar">
            <select name="wci_bulk_action" class="wap-bulk-select">
                <?php foreach ( WAP_Order_Service::bulk_action_options() as $value => $label ) : ?>
                    <option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $label ); ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" name="wci_bulk_apply" class="wap-btn wap-btn-primary" value="1">اجرا</button>
        </div>
        <?php endif; ?>

        <div class="wap-table-wrap" id="wap_capture">
            <table class="wap-table wap-orders-table">
                <thead><tr>
                <?php if ( $can_bulk ) : ?><th class="wap-check-col"><input type="checkbox" id="wap-cb-select-all"></th><?php endif; ?>
                <th>#</th><th>نام / نام خانوادگی</th><th>شماره تماس</th><th>شهر</th><th>محصول</th><th>وضعیت</th><th>مبلغ کل</th><th>تاریخ</th><?php WAP_Baget_Fields::render_table_headers(); ?><th>فاکتور</th><?php if ( $can_edit_orders ) : ?><th>عملیات</th><?php endif; ?></tr></thead>
                <tbody>
                <?php if ( empty( $orders ) ) : ?>
                    <tr><td colspan="<?php echo esc_attr( (string) ( 9 + WAP_Baget_Fields::table_column_count() + ( $can_edit_orders ? 1 : 0 ) + ( $can_bulk ? 1 : 0 ) ) ); ?>" class="wap-empty">سفارشی یافت نشد.</td></tr>
                <?php else : foreach ( $orders as $order ) : ?>
                    <tr>
                        <?php if ( $can_bulk ) : ?>
                        <td class="wap-check-col"><input type="checkbox" name="order_ids[]" value="<?php echo esc_attr( (string) $order->get_id() ); ?>"></td>
                        <?php endif; ?>
                        <td><?php if ( $can_edit_orders ) : ?><a href="<?php echo esc_url( WAP_Order_Service::edit_url( $order->get_id() ) ); ?>" target="_blank">#<?php echo esc_html( $order->get_order_number() ); ?></a><?php else : ?>#<?php echo esc_html( $order->get_order_number() ); ?><?php endif; ?></td>
                        <td><?php echo esc_html( $order->get_formatted_billing_full_name() ); ?></td>
                        <td style="direction:ltr;text-align:right"><?php echo esc_html( $order->get_billing_phone() ); ?></td>
                        <td><?php echo esc_html( $order->get_billing_city() ); ?></td>
                        <td class="wap-products-cell"><?php foreach ( WAP_Data::order_products_lines( $order ) as $line ) : ?><div class="wap-product-line"><?php echo esc_html( $line ); ?></div><?php endforeach; ?></td>
                        <td><?php
                        if ( class_exists( 'WAP_Order_Service' ) ) {
                            WAP_Order_Service::render_status_badge( $order, 'wap' );
                        } else {
                            ?><span class="wap-status wap-status-<?php echo esc_attr( $order->get_status() ); ?>"><?php echo esc_html( wc_get_order_status_name( $order->get_status() ) ); ?></span><?php
                        }
                        ?></td>
                        <td><strong><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></strong></td>
                        <td class="wap-date-cell"><?php echo function_exists( 'wci_order_date_cell' ) ? wci_order_date_cell( $order->get_date_created(), true ) : esc_html( wc_format_datetime( $order->get_date_created() ) ); ?></td>
                        <?php WAP_Baget_Fields::render_table_cells( $order ); ?>
                        <td class="wap-invoice-actions">
                            <?php if ( class_exists( 'WCI_Invoice' ) ) : ?>
                                <a class="wap-btn wap-btn-ghost" href="<?php echo esc_url( WCI_Invoice::portal_url( $order->get_id(), false ) ); ?>" target="_blank" rel="noopener noreferrer">مشاهده</a>
                                <a class="wap-btn wap-btn-primary" href="<?php echo esc_url( WCI_Invoice::portal_url( $order->get_id(), true ) ); ?>">دانلود</a>
                            <?php else : ?>
                                —
                            <?php endif; ?>
                        </td>
                        <?php if ( $can_edit_orders ) : ?>
                        <td><a class="wap-btn wap-btn-ghost" href="<?php echo esc_url( WAP_Order_Service::edit_url( $order->get_id() ) ); ?>" target="_blank">ویرایش</a></td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ( $can_bulk ) : ?>
        <div class="wap-bulk-bar wap-bulk-bar-bottom">
            <select name="wci_bulk_action2" class="wap-bulk-select">
                <?php foreach ( WAP_Order_Service::bulk_action_options() as $value => $label ) : ?>
                    <option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $label ); ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" name="wci_bulk_apply" class="wap-btn wap-btn-primary" value="1">اجرا</button>
        </div>
        <script>
        (function(){
            var form = document.getElementById('wap-orders-bulk-form');
            var all = document.getElementById('wap-cb-select-all');
            if (all) {
                all.addEventListener('change', function(){
                    document.querySelectorAll('#wap-orders-bulk-form input[name="order_ids[]"]').forEach(function(cb){ cb.checked = all.checked; });
                });
            }
            if (form) {
                form.addEventListener('submit', function(){
                    var ids = [];
                    form.querySelectorAll('input[name="order_ids[]"]:checked').forEach(function(cb){ ids.push(cb.value); });
                    var h = document.getElementById('wap-order-ids-json');
                    if (!h) {
                        h = document.createElement('input');
                        h.type = 'hidden';
                        h.name = 'order_ids_json';
                        h.id = 'wap-order-ids-json';
                        form.appendChild(h);
                    }
                    h.value = JSON.stringify(ids);

                    var a1 = form.querySelector('select[name="wci_bulk_action"]');
                    var a2 = form.querySelector('select[name="wci_bulk_action2"]');
                    // فقط یک select فعال بماند (بالا/پایین)
                    if (a2 && a2.value) {
                        if (a1) { a1.disabled = true; }
                    } else if (a1 && a1.value) {
                        if (a2) { a2.disabled = true; }
                    }

                    var act = ((a2 && a2.value) ? a2.value : '') || ((a1 && a1.value) ? a1.value : '');
                    if (act.indexOf('download_invoices') === 0 || act.indexOf('print_invoices') === 0) {
                        form.target = '_blank';
                    } else {
                        form.target = '';
                    }
                });
            }
        })();
        </script>
        <?php endif; ?>
        </form>

        <?php if ( $total_pages > 1 ) : ?>
        <div class="wap-pagination">
            <span class="wap-count">نمایش <?php echo esc_html( number_format( ( $paged - 1 ) * $per_page + 1 ) ); ?>–<?php echo esc_html( number_format( min( $total, $paged * $per_page ) ) ); ?> از <?php echo esc_html( number_format( $total ) ); ?> سفارش</span>
            <div class="wap-pages">
                <?php
                $start = max( 1, $paged - 3 );
                $end   = min( $total_pages, $paged + 3 );
                for ( $p = $start; $p <= $end; $p++ ) :
                    $url = add_query_arg( array_merge( $f, array( 'wap_view' => 'orders', 'paged' => $p ) ), self::panel_url() );
                    ?>
                    <a href="<?php echo esc_url( $url ); ?>" class="wap-btn <?php echo $p === $paged ? 'wap-btn-primary' : 'wap-btn-ghost'; ?>"><?php echo esc_html( $p ); ?></a>
                <?php endfor; ?>
            </div>
        </div>
        <?php endif;
    }

    private static function render_products_tab() {
        $f           = WAP_Data::get_filters();
        $product_id  = ! empty( $_GET['product_id'] ) ? (int) $_GET['product_id'] : 0;
        $orders      = WAP_Data::get_orders( $f );
        $paid_only   = WAP_Data::should_require_paid( $f );
        $product_cat = (int) ( $f['product_cat'] ?? 0 );
        $categories  = WAP_Data::get_product_categories();
        $presets     = WAP_Data::quick_presets();
        $currency    = get_woocommerce_currency_symbol();
        $base_params = array_filter( $f, function( $v ) {
            if ( $v === null || $v === '' || $v === array() || $v === 0 ) {
                return false;
            }
            return true;
        } );
        $export_params = array_merge( $base_params, array( 'wap_view' => 'products' ) );
        if ( $product_id ) {
            $export_params['product_id'] = $product_id;
        }
        if ( $product_cat ) {
            $export_params['product_cat'] = $product_cat;
        }
        $products_csv_url = self::export_url(
            $export_params,
            $product_id ? 'product_orders_csv' : 'products_csv'
        );
        ?>
        <form method="get" action="<?php echo esc_url( self::panel_url() ); ?>" class="wap-filters">
            <input type="hidden" name="wap_view" value="products">
            <?php if ( $product_id ) : ?><input type="hidden" name="product_id" value="<?php echo esc_attr( $product_id ); ?>"><?php endif; ?>
            <div class="wap-field wap-field-date">
                <label>از تاریخ (شمسی)</label>
                <input type="text" id="wap_date_from" name="date_from" value="<?php echo esc_attr( $f['date_from'] ); ?>" placeholder="۱۴۰۳/۰۱/۰۱" autocomplete="off">
            </div>
            <div class="wap-field wap-field-date">
                <label>تا تاریخ (شمسی)</label>
                <input type="text" id="wap_date_to" name="date_to" value="<?php echo esc_attr( $f['date_to'] ); ?>" placeholder="۱۴۰۳/۱۲/۲۹" autocomplete="off">
            </div>
            <div class="wap-field">
                <label>وضعیت سفارش</label>
                <select name="order_status">
                    <option value="">همه وضعیت‌ها</option>
                    <option value="__paid__" <?php selected( $f['order_status'], '__paid__' ); ?>>فقط موفق</option>
                    <?php foreach ( wc_get_order_statuses() as $slug => $label ) :
                        $val = str_replace( 'wc-', '', $slug ); ?>
                        <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $f['order_status'], $val ); ?>><?php echo esc_html( $label ); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="wap-field">
                <label>دسته‌بندی محصول</label>
                <select name="product_cat">
                    <option value="0">همه دسته‌ها</option>
                    <?php foreach ( $categories as $cat ) : ?>
                        <option value="<?php echo esc_attr( $cat['id'] ); ?>" <?php selected( $product_cat, $cat['id'] ); ?>><?php echo esc_html( $cat['name'] ); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="wap-field wap-field-actions">
                <button type="submit" class="wap-btn wap-btn-primary">اعمال فیلتر</button>
                <a href="<?php echo esc_url( add_query_arg( 'wap_view', 'products', self::panel_url() ) ); ?>" class="wap-btn wap-btn-ghost">پاک کردن</a>
            </div>
        </form>

        <div class="wap-presets" id="wap_presets">
            <?php foreach ( $presets as $label => $range ) : ?>
                <button type="button" class="wap-chip" data-from="<?php echo esc_attr( $range[0] ); ?>" data-to="<?php echo esc_attr( $range[1] ); ?>"><?php echo esc_html( $label ); ?></button>
            <?php endforeach; ?>
        </div>

        <?php if ( $product_id ) :
            $product      = wc_get_product( $product_id );
            $product_name = $product ? $product->get_name() : ( '#' . $product_id );
            $rows         = WAP_Data::get_product_drilldown( $orders, $product_id, $paid_only );
            $total_qty = 0; $total_revenue = 0.0;
            foreach ( $rows as $r ) { $total_qty += $r['qty']; $total_revenue += $r['revenue']; }
            ?>
            <h2 class="wap-section-title">سفارش‌های محصول: <?php echo esc_html( $product_name ); ?>
                <a href="<?php echo esc_url( add_query_arg( array_merge( $base_params, array( 'wap_view' => 'products' ) ), self::panel_url() ) ); ?>" class="wap-btn wap-btn-ghost" style="font-size:12px">← بازگشت</a>
            </h2>
            <div class="wap-cards">
                <div class="wap-card"><span class="wap-card-icon">📦</span><span class="wap-card-label">تعداد سفارش</span><span class="wap-card-value"><?php echo esc_html( number_format( count( $rows ) ) ); ?></span></div>
                <div class="wap-card wap-card-accent"><span class="wap-card-icon">🔢</span><span class="wap-card-label">تعداد فروخته‌شده</span><span class="wap-card-value"><?php echo esc_html( number_format( $total_qty ) ); ?></span></div>
                <div class="wap-card wap-card-net"><span class="wap-card-icon">💰</span><span class="wap-card-label">درآمد کل</span><span class="wap-card-value"><?php echo esc_html( number_format( $total_revenue ) . ' ' . $currency ); ?></span></div>
            </div>
            <?php self::render_export_bar( $products_csv_url ); ?>
            <div class="wap-table-wrap" id="wap_capture">
                <table class="wap-table">
                    <thead><tr><th>#</th><th>نام خریدار</th><th>تلفن</th><th>تاریخ</th><th>تعداد</th><th>مبلغ</th><th>وضعیت</th><?php WAP_Baget_Fields::render_table_headers(); ?></tr></thead>
                    <tbody>
                    <?php if ( empty( $rows ) ) : ?>
                        <tr><td colspan="<?php echo esc_attr( 7 + WAP_Baget_Fields::table_column_count() ); ?>" class="wap-empty">سفارشی برای این محصول یافت نشد.</td></tr>
                    <?php else : foreach ( $rows as $row ) : $o = $row['order']; ?>
                        <tr>
                            <td>#<?php echo esc_html( $o->get_order_number() ); ?></td>
                            <td><?php echo esc_html( $o->get_formatted_billing_full_name() ); ?></td>
                            <td style="direction:ltr;text-align:right"><?php echo esc_html( $o->get_billing_phone() ); ?></td>
                            <td><?php echo esc_html( wc_format_datetime( $o->get_date_created() ) ); ?></td>
                            <td><strong><?php echo esc_html( number_format( $row['qty'] ) ); ?></strong></td>
                            <td><strong><?php echo esc_html( number_format( $row['revenue'] ) . ' ' . $currency ); ?></strong></td>
                            <td><?php
                            if ( class_exists( 'WAP_Order_Service' ) ) {
                                WAP_Order_Service::render_status_cell( $o, 'wap' );
                            } else {
                                ?><span class="wap-status wap-status-<?php echo esc_attr( $o->get_status() ); ?>"><?php echo esc_html( wc_get_order_status_name( $o->get_status() ) ); ?></span><?php
                            }
                            ?></td>
                            <?php WAP_Baget_Fields::render_table_cells( $o ); ?>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        <?php else :
            $products      = WAP_Data::get_product_sales( $orders, $paid_only, $product_cat );
            $total_qty     = array_sum( array_column( $products, 'qty' ) );
            $total_revenue = array_sum( array_column( $products, 'revenue' ) );
            ?>
            <div class="wap-cards">
                <div class="wap-card"><span class="wap-card-icon">🛍️</span><span class="wap-card-label">تعداد محصولات فروخته‌شده</span><span class="wap-card-value"><?php echo esc_html( number_format( count( $products ) ) ); ?></span></div>
                <div class="wap-card wap-card-accent"><span class="wap-card-icon">🔢</span><span class="wap-card-label">تعداد کل اقلام فروخته‌شده</span><span class="wap-card-value"><?php echo esc_html( number_format( $total_qty ) ); ?></span></div>
                <div class="wap-card wap-card-net"><span class="wap-card-icon">💰</span><span class="wap-card-label">مجموع فروش</span><span class="wap-card-value"><?php echo esc_html( number_format( $total_revenue ) . ' ' . $currency ); ?></span></div>
            </div>

            <?php if ( ! empty( $products ) ) :
                $top = array_slice( $products, 0, 12 );
                $max_rev = max( array_column( $top, 'revenue' ) );
                ?>
                <div class="wap-chart-card">
                    <h3 class="wap-section-title">📊 نمودار برترین محصولات (بر اساس درآمد)</h3>
                    <div class="wap-chart">
                        <?php foreach ( $top as $p ) :
                            $pct = $max_rev > 0 ? round( $p['revenue'] / $max_rev * 100 ) : 0;
                            $url = add_query_arg( array_merge( $base_params, array( 'wap_view' => 'products', 'product_id' => $p['pid'] ) ), self::panel_url() );
                            ?>
                            <div class="wap-bar-col">
                                <div class="wap-bar-track">
                                    <a href="<?php echo esc_url( $url ); ?>" class="wap-bar-fill" style="height:<?php echo (int) $pct; ?>%">
                                        <span class="wap-bar-tip"><?php echo esc_html( number_format( $p['revenue'] ) ); ?></span>
                                    </a>
                                </div>
                                <span class="wap-bar-label" title="<?php echo esc_attr( $p['name'] ); ?>"><?php echo esc_html( function_exists( 'mb_strimwidth' ) ? mb_strimwidth( $p['name'], 0, 12, '…' ) : ( strlen( $p['name'] ) > 12 ? substr( $p['name'], 0, 10 ) . '…' : $p['name'] ) ); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php self::render_export_bar( $products_csv_url ); ?>
            <div class="wap-table-wrap" id="wap_capture">
                <table class="wap-table">
                    <thead><tr><th>#</th><th>نام محصول</th><th>SKU</th><th>تعداد فروخته‌شده</th><th>تعداد سفارشات</th><th>درآمد کل</th></tr></thead>
                    <tbody>
                    <?php if ( empty( $products ) ) : ?>
                        <tr><td colspan="6" class="wap-empty">محصولی با این فیلترها یافت نشد.</td></tr>
                    <?php else : $i = 1; foreach ( $products as $p ) :
                        $url = add_query_arg( array_merge( $base_params, array( 'wap_view' => 'products', 'product_id' => $p['pid'] ) ), self::panel_url() ); ?>
                        <tr>
                            <td><?php echo $i++; ?></td>
                            <td><a href="<?php echo esc_url( $url ); ?>"><strong><?php echo esc_html( $p['name'] ); ?></strong></a></td>
                            <td><?php echo esc_html( $p['sku'] ); ?></td>
                            <td><strong><?php echo esc_html( number_format( $p['qty'] ) ); ?></strong></td>
                            <td><?php echo esc_html( number_format( $p['orders'] ) ); ?></td>
                            <td><strong><?php echo esc_html( number_format( $p['revenue'] ) . ' ' . $currency ); ?></strong></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        <?php endif;
    }

    private static function render_buyers_tab() {
        $f           = WAP_Data::get_filters();
        $product_ids = ! empty( $f['product_ids'] ) ? $f['product_ids'] : array();
        $presets     = WAP_Data::quick_presets();
        $currency    = get_woocommerce_currency_symbol();
        $labels      = WAP_Data::product_labels( $product_ids );
        $has_query   = ! empty( $product_ids );

        $base_params = array(
            'wap_view'     => 'buyers',
            'date_from'    => $f['date_from'],
            'date_to'      => $f['date_to'],
            'order_status' => $f['order_status'],
        );
        foreach ( $product_ids as $pid ) {
            $base_params['product_ids'][] = $pid;
        }

        $buyers = array();
        if ( $has_query ) {
            $orders  = WAP_Data::get_orders( $f );
            $buyers  = WAP_Data::get_buyers_by_products( $orders, $product_ids, WAP_Data::should_require_paid( $f ) );
        }

        $total_qty     = $has_query ? array_sum( array_column( $buyers, 'qty' ) ) : 0;
        $total_revenue = $has_query ? array_sum( array_column( $buyers, 'revenue' ) ) : 0.0;
        $csv_url       = self::export_url( $base_params, 'buyers_csv' );
        ?>
        <form method="get" action="<?php echo esc_url( self::panel_url() ); ?>" class="wap-filters" id="wap_buyers_form">
            <input type="hidden" name="wap_view" value="buyers">
            <div class="wap-field wap-field-date">
                <label>از تاریخ (شمسی)</label>
                <input type="text" id="wap_date_from" name="date_from" value="<?php echo esc_attr( $f['date_from'] ); ?>" placeholder="۱۴۰۳/۰۱/۰۱" autocomplete="off">
            </div>
            <div class="wap-field wap-field-date">
                <label>تا تاریخ (شمسی)</label>
                <input type="text" id="wap_date_to" name="date_to" value="<?php echo esc_attr( $f['date_to'] ); ?>" placeholder="۱۴۰۳/۱۲/۲۹" autocomplete="off">
            </div>
            <div class="wap-field">
                <label>وضعیت سفارش</label>
                <select name="order_status">
                    <option value="">همه وضعیت‌ها (موفق + لغو شده + …)</option>
                    <option value="__paid__" <?php selected( $f['order_status'], '__paid__' ); ?>>فقط موفق</option>
                    <?php foreach ( wc_get_order_statuses() as $slug => $label ) :
                        $val = str_replace( 'wc-', '', $slug ); ?>
                        <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $f['order_status'], $val ); ?>><?php echo esc_html( $label ); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="wap-field wap-field-wide">
                <label>محصولات (یک یا چند مورد)</label>
                <div class="wap-product-picker" id="wap_product_picker"
                     data-selected="<?php echo esc_attr( wp_json_encode( array_map( function( $id ) use ( $labels ) {
                         return array( 'id' => $id, 'name' => $labels[ $id ] ?? ( '#' . $id ) );
                     }, $product_ids ) ) ); ?>">
                    <div class="wap-product-chips" id="wap_product_chips"></div>
                    <div class="wap-product-search-wrap">
                        <input type="text" id="wap_product_search" class="wap-product-search" placeholder="جستجوی محصول (نام، SKU یا شناسه)…" autocomplete="off">
                        <div id="wap_product_results" class="wap-ac-results" hidden></div>
                    </div>
                    <div id="wap_product_ids_inputs"></div>
                </div>
                <p class="wap-hint">حداقل یک محصول انتخاب کنید؛ مشتریانی که هر کدام از این محصولات را خریده باشند در لیست می‌آیند.</p>
            </div>
            <div class="wap-field wap-field-actions">
                <button type="submit" class="wap-btn wap-btn-primary">نمایش خریداران</button>
                <a href="<?php echo esc_url( add_query_arg( 'wap_view', 'buyers', self::panel_url() ) ); ?>" class="wap-btn wap-btn-ghost">پاک کردن</a>
            </div>
        </form>

        <div class="wap-presets" id="wap_presets">
            <?php foreach ( $presets as $label => $range ) : ?>
                <button type="button" class="wap-chip" data-from="<?php echo esc_attr( $range[0] ); ?>" data-to="<?php echo esc_attr( $range[1] ); ?>"><?php echo esc_html( $label ); ?></button>
            <?php endforeach; ?>
        </div>

        <?php if ( ! $has_query ) : ?>
            <div class="wap-alert wap-alert-info">برای مشاهدهٔ لیست مشتریان، ابتدا یک یا چند محصول را انتخاب و فیلتر را اعمال کنید.</div>
        <?php else : ?>
            <?php if ( ! empty( $labels ) ) : ?>
                <p class="wap-selected-products">محصولات انتخابی:
                    <?php echo esc_html( implode( '، ', array_values( $labels ) ) ); ?>
                </p>
            <?php endif; ?>
            <div class="wap-cards">
                <div class="wap-card"><span class="wap-card-icon">👥</span><span class="wap-card-label">تعداد مشتریان</span><span class="wap-card-value"><?php echo esc_html( number_format( count( $buyers ) ) ); ?></span></div>
                <div class="wap-card wap-card-accent"><span class="wap-card-icon">🔢</span><span class="wap-card-label">مجموع اقلام خریداری‌شده</span><span class="wap-card-value"><?php echo esc_html( number_format( $total_qty ) ); ?></span></div>
                <div class="wap-card wap-card-net"><span class="wap-card-icon">💰</span><span class="wap-card-label">مبلغ محصولات انتخابی</span><span class="wap-card-value"><?php echo esc_html( number_format( $total_revenue ) . ' ' . $currency ); ?></span></div>
            </div>
            <?php self::render_export_bar( $csv_url ); ?>
            <div class="wap-table-wrap" id="wap_capture">
                <table class="wap-table">
                    <thead>
                    <tr>
                        <th>#</th>
                        <th>نام مشتری</th>
                        <th>تلفن</th>
                        <th>ایمیل</th>
                        <th>شهر</th>
                        <th>تعداد سفارش</th>
                        <th>وضعیت‌ها</th>
                        <th>تعداد اقلام</th>
                        <th>مبلغ</th>
                        <th>آخرین خرید</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if ( empty( $buyers ) ) : ?>
                        <tr><td colspan="10" class="wap-empty">مشتری‌ای با این فیلترها یافت نشد. بازه تاریخ را بزرگ‌تر کنید یا وضعیت را روی «همه وضعیت‌ها» بگذارید.</td></tr>
                    <?php else : $i = 1; foreach ( $buyers as $b ) : ?>
                        <tr>
                            <td><?php echo (int) $i++; ?></td>
                            <td><strong><?php echo esc_html( $b['name'] !== '' ? $b['name'] : '—' ); ?></strong></td>
                            <td style="direction:ltr;text-align:right"><?php echo esc_html( $b['phone'] !== '' ? $b['phone'] : '—' ); ?></td>
                            <td><?php echo esc_html( $b['email'] !== '' ? $b['email'] : '—' ); ?></td>
                            <td><?php echo esc_html( $b['city'] !== '' ? $b['city'] : '—' ); ?></td>
                            <td><?php echo esc_html( number_format( $b['orders_count'] ) ); ?></td>
                            <td><?php
                            $status_bits = array();
                            foreach ( (array) ( $b['statuses'] ?? array() ) as $st => $cnt ) {
                                $status_bits[] = wc_get_order_status_name( $st ) . ' (' . number_format( (int) $cnt ) . ')';
                            }
                            echo esc_html( $status_bits ? implode( '، ', $status_bits ) : '—' );
                            ?></td>
                            <td><strong><?php echo esc_html( number_format( $b['qty'] ) ); ?></strong></td>
                            <td><strong><?php echo esc_html( number_format( $b['revenue'] ) . ' ' . $currency ); ?></strong></td>
                            <td><?php echo esc_html( $b['last_order_ts'] ? date_i18n( 'Y/m/d H:i', $b['last_order_ts'] ) : '—' ); ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        <?php endif;
    }
}
