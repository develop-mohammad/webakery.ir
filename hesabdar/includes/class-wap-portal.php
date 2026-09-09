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

    public static function current_view_public(): string {
        return self::current_view();
    }

    private static function render_image_export_buttons( string $image_label = 'report', string $target = '#wap_capture' ): void {
        $can_archive = class_exists( 'WAP_Report_Image' ) && WAP_Report_Image::can_archive();
        ?>
        <button type="button" class="wap-btn wap-btn-jpg" data-wap-export-image data-format="jpg" data-label="<?php echo esc_attr( $image_label ); ?>" data-target="<?php echo esc_attr( $target ); ?>">JPG</button>
        <button type="button" class="wap-btn wap-btn-ghost" data-wap-export-image data-format="png" data-label="<?php echo esc_attr( $image_label ); ?>" data-target="<?php echo esc_attr( $target ); ?>">PNG</button>
        <button type="button" class="wap-btn wap-btn-ghost" data-wap-export-image data-format="clipboard" data-label="<?php echo esc_attr( $image_label ); ?>" data-target="<?php echo esc_attr( $target ); ?>">کپی</button>
        <?php if ( $can_archive ) : ?>
        <button type="button" class="wap-btn wap-btn-ghost" data-wap-export-image data-format="archive" data-label="<?php echo esc_attr( $image_label ); ?>" data-target="<?php echo esc_attr( $target ); ?>">آرشیو رسانه</button>
        <?php endif; ?>
        <?php
    }

    private static function render_image_export_tools( string $image_label = 'report', string $target = '#wap_capture' ): void {
        $cfg_from = class_exists( 'WAP_Report_Image' ) ? ( WAP_Report_Image::client_config()['compareFrom'] ?? '' ) : '';
        $cfg_to   = class_exists( 'WAP_Report_Image' ) ? ( WAP_Report_Image::client_config()['compareTo'] ?? '' ) : '';
        $locked   = class_exists( 'WAP_Report_Image' ) && WAP_Report_Image::is_date_locked();
        ?>
        <div class="wap-image-export-tools" data-wap-no-capture data-wap-image-tools>
            <div class="wap-image-export-row">
                <label>محدوده
                    <select data-wap-img-scope>
                        <option value="full">کل گزارش</option>
                        <option value="cards">فقط کارت‌ها</option>
                        <option value="chart">فقط نمودار</option>
                        <option value="table">فقط جدول</option>
                    </select>
                </label>
                <label>کیفیت
                    <select data-wap-img-quality>
                        <option value="normal">عادی</option>
                        <option value="high">باکیفیت (چاپ)</option>
                    </select>
                </label>
                <label class="wap-check"><input type="checkbox" data-wap-img-compact> حالت فشرده چاپ</label>
                <label class="wap-check"><input type="checkbox" data-wap-img-multipage checked> چندصفحه‌ای اگر بلند باشد</label>
            </div>
            <div class="wap-image-export-row">
                <label>یادداشت حسابدار
                    <input type="text" data-wap-img-note placeholder="مثلاً تأیید شد — برای مدیر" maxlength="200" style="min-width:220px">
                </label>
                <label>مقایسه از
                    <input type="text" data-wap-img-compare-from value="<?php echo esc_attr( $cfg_from ); ?>" placeholder="۱۴۰۴/۰۱/۰۱" <?php disabled( $locked ); ?>>
                </label>
                <label>تا
                    <input type="text" data-wap-img-compare-to value="<?php echo esc_attr( $cfg_to ); ?>" placeholder="۱۴۰۴/۰۱/۳۱" <?php disabled( $locked ); ?>>
                </label>
            </div>
            <div class="wap-image-export-row wap-image-export-actions">
                <?php self::render_image_export_buttons( $image_label, $target ); ?>
            </div>
            <?php if ( class_exists( 'WAP_Report_Image' ) && WAP_Report_Image::is_accountant_only() ) : ?>
                <p class="wap-hint" style="margin:6px 0 0">حسابدار: دانلود تصویر مجاز است. آرشیو/ارسال خودکار طبق تنظیمات مدیر محدود می‌شود.</p>
            <?php endif; ?>
        </div>
        <?php
    }

    private static function render_export_bar( string $csv_url, string $image_label = 'report' ): void {
        ?>
        <div class="wap-export-bar" data-wap-no-capture>
            <span class="wap-export-label">خروجی گزارش:</span>
            <a class="wap-btn wap-btn-csv" href="<?php echo esc_url( $csv_url ); ?>">CSV / اکسل</a>
            <?php self::render_image_export_tools( $image_label ); ?>
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
                <div class="wap-logo"><div class="wap-logo-title"><?php echo esc_html( $title ); ?></div></div>
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

        if ( $type === 'shaparak_csv' ) {
            $report = WAP_Zarinpal_Report::build();
            WAP_Export::zarinpal_reconcile_csv( $report );
            return;
        }

        if ( $type === 'shaparak_xlsx' ) {
            $report = WAP_Zarinpal_Report::build();
            WAP_Export::zarinpal_reconcile_xlsx( $report );
            return;
        }

        if ( $type === 'products_csv' || $type === 'product_orders_csv' ) {
            $f          = WAP_Data::get_filters();
            $orders     = WAP_Data::get_orders( $f );
            $product_id = ! empty( $_GET['product_id'] ) ? (int) $_GET['product_id'] : 0;
            if ( $type === 'product_orders_csv' && $product_id ) {
                WAP_Export::product_orders_csv( $orders, $product_id );
            } else {
                WAP_Export::products_csv( $orders );
            }
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
        <meta name="theme-color" content="#1a73e8">
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
            'view'      => self::current_view(),
            'productId' => ! empty( $_GET['product_id'] ) ? (int) $_GET['product_id'] : 0,
            'query'     => array_map( 'sanitize_text_field', wp_unslash( $_GET ) ),
        );
        $img = class_exists( 'WAP_Report_Image' ) ? WAP_Report_Image::client_config() : array();
        ?>
        <script>window.WAP_TODAY = <?php echo wp_json_encode( $today ); ?>;</script>
        <script>window.WAP_SHEETS = <?php echo wp_json_encode( $params ); ?>;</script>
        <script>window.WAP_IMAGE = <?php echo wp_json_encode( $img ); ?>;</script>
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
            <div class="wap-login-box">
                <div class="wap-logo">
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
        $view = in_array( $view, array( 'sales', 'orders', 'products', 'shaparak', 'analytics' ), true ) ? $view : 'sales';
        if ( class_exists( 'WAP_Report_Image' ) && ! WAP_Report_Image::can_view_tab( $view ) ) {
            $allowed = WAP_Report_Image::allowed_tabs_for_user();
            $view = $allowed[0] ?? 'sales';
        }
        return $view;
    }

    private static function render_dashboard( $panel_type = self::PANEL_ACCOUNTANT ) {
        self::maybe_save_formula();
        $is_manager = $panel_type === self::PANEL_MANAGER;
        self::head( $is_manager ? 'پنل مدیر' : 'گزارش فروش', $panel_type );
        $view = self::current_view();
        $brand = $is_manager ? 'پرتال مدیر' : 'پرتال حسابدار';
        ?>
        <div class="wap-wrap wap-panel-<?php echo esc_attr( $panel_type ); ?>">
            <header class="wap-header" id="wap-header">
                <div class="wap-brand-wrap">
                    <div class="wap-brand"><?php echo esc_html( $brand ); ?></div>
                    <div class="wap-brand-sub"><?php
                        if ( $view === 'orders' ) {
                            echo 'لیست و جزئیات سفارش‌های پرداخت‌شده';
                        } elseif ( $view === 'products' ) {
                            echo 'تحلیل فروش به تفکیک محصول';
                        } elseif ( $view === 'shaparak' ) {
                            echo 'تطبیق واریز شاپرک، خرید ووکامرس و کارمزد زرین‌پال';
                        } elseif ( $view === 'analytics' ) {
                            echo 'نمودار فروش، منبع ورود، مشتریان ثابت و پیک خرید';
                        } else {
                            echo 'خلاصه فروش و خروجی‌های حسابداری';
                        }
                    ?></div>
                </div>
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="wap-logout-form">
                    <input type="hidden" name="action" value="wap_logout">
                    <input type="hidden" name="wap_panel" value="<?php echo esc_attr( $panel_type ); ?>">
                    <?php wp_nonce_field( 'wap_logout_action', 'wap_logout_nonce' ); ?>
                    <span class="wap-user"><?php echo esc_html( wp_get_current_user()->display_name ); ?></span>
                    <button type="submit" class="wap-btn wap-btn-ghost">خروج</button>
                </form>
            </header>

            <?php if ( self::user_has_manager_access() ) : ?>
            <nav class="wap-panel-switch" aria-label="انتخاب پنل">
                <a href="<?php echo esc_url( self::panel_url( self::PANEL_ACCOUNTANT ) ); ?>" class="wap-panel-switch-link<?php echo ! $is_manager ? ' is-active' : ''; ?>">پنل حسابدار</a>
                <a href="<?php echo esc_url( self::manager_panel_url() ); ?>" class="wap-panel-switch-link<?php echo $is_manager ? ' is-active' : ''; ?>">پنل مدیر</a>
            </nav>
            <?php endif; ?>

            <?php if ( $is_manager ) : ?>
            <div class="wap-manager-bar">
                <?php if ( current_user_can( 'manage_options' ) ) : ?>
                    <a class="wap-btn wap-btn-ghost" href="<?php echo esc_url( admin_url() ); ?>" target="_blank">پیشخوان وردپرس</a>
                    <a class="wap-btn wap-btn-ghost" href="<?php echo esc_url( admin_url( 'admin.php?page=wap-accountants' ) ); ?>" target="_blank">مدیریت حسابداران</a>
                    <a class="wap-btn wap-btn-ghost" href="<?php echo esc_url( admin_url( 'admin.php?page=wci-order-edit' ) ); ?>" target="_blank">سفارش جدید</a>
                    <a class="wap-btn wap-btn-ghost" href="<?php echo esc_url( admin_url( 'admin.php?page=wci-reports' ) ); ?>" target="_blank">گزارش مالی (پیشخوان)</a>
                <?php else : ?>
                    <a class="wap-btn wap-btn-ghost" href="<?php echo esc_url( add_query_arg( 'wap_view', 'sales', self::panel_url() ) ); ?>">گزارش مالی</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php if ( ! class_exists( 'WooCommerce' ) ) : ?>
                <div class="wap-alert">ووکامرس روی این سایت فعال نیست.</div>
            <?php else : ?>

            <nav class="wap-tabs">
                <?php
                $tabs = array(
                    'sales'     => 'گزارش مالی',
                    'analytics' => 'داشبورد',
                    'orders'    => 'سفارش‌ها',
                    'products'  => 'محصولات',
                    'shaparak'  => 'شاپرک / کارمزد',
                );
                foreach ( $tabs as $tab_key => $tab_label ) :
                    if ( class_exists( 'WAP_Report_Image' ) && ! WAP_Report_Image::can_view_tab( $tab_key ) ) {
                        continue;
                    }
                    ?>
                <a href="<?php echo esc_url( add_query_arg( 'wap_view', $tab_key, self::panel_url() ) ); ?>" class="wap-tab<?php echo $view === $tab_key ? ' wap-tab-active' : ''; ?>"><?php echo esc_html( $tab_label ); ?></a>
                <?php endforeach; ?>
            </nav>

            <?php
            try {
                if ( $view === 'orders' ) {
                    self::render_orders_tab();
                } elseif ( $view === 'products' ) {
                    self::render_products_tab();
                } elseif ( $view === 'shaparak' ) {
                    self::render_shaparak_tab();
                } elseif ( $view === 'analytics' ) {
                    self::render_analytics_tab();
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

    /**
     * نرمال‌سازی بازه اصلی + مقایسه و جمع‌آوری پیام راهنما.
     *
     * @return array{filters:array,compare_from:string,compare_to:string,notices:string[],compare_ready:bool,compare_partial:bool}
     */
    private static function prepare_date_ranges( array $f ): array {
        $notices = array();
        $primary = WAP_Jalali::normalize_range( (string) ( $f['date_from'] ?? '' ), (string) ( $f['date_to'] ?? '' ) );
        if ( ! empty( $primary['messages'] ) ) {
            $notices = array_merge( $notices, $primary['messages'] );
        }
        if ( ! $primary['invalid'] ) {
            $f['date_from'] = $primary['from'];
            $f['date_to']   = $primary['to'];
        }

        $cmp_from = WAP_Jalali::normalize_digits( sanitize_text_field( wp_unslash( $_GET['compare_from'] ?? $_REQUEST['compare_from'] ?? '' ) ) );
        $cmp_to   = WAP_Jalali::normalize_digits( sanitize_text_field( wp_unslash( $_GET['compare_to'] ?? $_REQUEST['compare_to'] ?? '' ) ) );
        // اگر فقط یکی پر است ولی بازه اصلی کامل است، سعی نکن پیام گمراه‌کننده بده
        $partial  = ( $cmp_from !== '' ) xor ( $cmp_to !== '' );
        $ready    = ( $cmp_from !== '' && $cmp_to !== '' );

        if ( $partial ) {
            $notices[] = 'برای مقایسه، هر دو فیلد «مقایسه از» و «مقایسه تا» لازم است. از «ماه مقایسه» یک ماه را انتخاب کنید یا «ماه مشابه پارسال» را بزنید.';
            $ready     = false;
            // فیلد ناقص را خالی نگه می‌داریم تا در UI مشخص باشد
            if ( $cmp_from === '' ) {
                $cmp_from = '';
            }
            if ( $cmp_to === '' ) {
                $cmp_to = '';
            }
        }

        if ( $ready ) {
            $cmp = WAP_Jalali::normalize_range( $cmp_from, $cmp_to );
            if ( ! empty( $cmp['messages'] ) ) {
                foreach ( $cmp['messages'] as $msg ) {
                    $notices[] = 'بازه مقایسه: ' . $msg;
                }
            }
            if ( $cmp['invalid'] ) {
                $ready = false;
            } else {
                $cmp_from = $cmp['from'];
                $cmp_to   = $cmp['to'];
            }
        }

        return array(
            'filters'         => $f,
            'compare_from'    => $cmp_from,
            'compare_to'      => $cmp_to,
            'notices'         => $notices,
            'compare_ready'   => $ready,
            'compare_partial' => $partial,
            'primary_invalid' => ! empty( $primary['invalid'] ),
        );
    }

    private static function render_filter_notices( array $notices, string $type = 'wap-alert' ): void {
        foreach ( $notices as $msg ) {
            $cls = $type;
            if ( strpos( $msg, 'مقایسه فعال است' ) !== false ) {
                $cls = 'wap-alert wap-alert-success';
            } elseif ( strpos( $msg, 'خودکار اصلاح' ) !== false ) {
                $cls = 'wap-alert';
            }
            echo '<div class="' . esc_attr( $cls ) . '" role="status">' . esc_html( $msg ) . '</div>';
        }
    }

    /** نوار انتخاب سریع ماه + دکمه ماه مشابه پارسال. */
    private static function render_month_pickers( bool $date_locked = false ): void {
        if ( $date_locked ) {
            return;
        }
        $months = WAP_Jalali::recent_months( 14 );
        ?>
        <div class="wap-month-bar" data-wap-no-capture>
            <div class="wap-month-bar__row">
                <span class="wap-month-bar__label">انتخاب ماه (بازه اصلی):</span>
                <div class="wap-month-chips" data-wap-month-target="primary">
                    <?php foreach ( $months as $m ) : ?>
                        <button type="button" class="wap-chip wap-chip-month" data-from="<?php echo esc_attr( $m['from'] ); ?>" data-to="<?php echo esc_attr( $m['to'] ); ?>"><?php echo esc_html( $m['label'] ); ?></button>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="wap-month-bar__row">
                <span class="wap-month-bar__label">ماه مقایسه:</span>
                <div class="wap-month-chips" data-wap-month-target="compare">
                    <?php foreach ( array_slice( $months, 0, 14 ) as $m ) : ?>
                        <button type="button" class="wap-chip wap-chip-month" data-from="<?php echo esc_attr( $m['from'] ); ?>" data-to="<?php echo esc_attr( $m['to'] ); ?>"><?php echo esc_html( $m['label'] ); ?></button>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="wap-btn wap-btn-ghost wap-btn-sm" data-wap-compare-same-last-year>ماه مشابه پارسال</button>
            </div>
            <p class="wap-month-bar__hint">با انتخاب ماه، فیلتر خودکار اعمال می‌شود. برای مقایسه سریع، «ماه مشابه پارسال» را بزنید.</p>
        </div>
        <?php
    }

    private static function render_sales_tab() {
        $prepared   = self::prepare_date_ranges( WAP_Data::get_filters() );
        $f          = $prepared['filters'];
        $notices    = $prepared['notices'];
        $cmp_from   = $prepared['compare_from'];
        $cmp_to     = $prepared['compare_to'];
        $orders     = $prepared['primary_invalid'] ? array() : WAP_Data::get_orders( $f );
        $groups_all = WAP_Data::build_rows( $orders, $f['period'] );
        $groups     = WAP_Data::apply_filter( $groups_all, $f );
        $presets    = WAP_Data::quick_presets();
        $gn         = WAP_Data::gross_vs_net( $orders );

        $overall_total = $gn['net_total'];
        $overall_count = $gn['net_count'];

        $currency = class_exists( 'WooCommerce' ) ? get_woocommerce_currency_symbol() : '';

        $groups2  = array();
        $gn2      = null;
        $aligned  = null;
        $orders2  = array();
        if ( $prepared['compare_ready'] ) {
            $f2 = array_merge( $f, array( 'date_from' => $cmp_from, 'date_to' => $cmp_to ) );
            $orders2 = WAP_Data::get_orders( $f2 );
            $groups2 = WAP_Data::apply_filter( WAP_Data::build_rows( $orders2, $f['period'] ), $f );
            $gn2     = WAP_Data::gross_vs_net( $orders2 );
        }

        if ( ! $prepared['primary_invalid'] && empty( $orders ) ) {
            $notices[] = 'در بازه «' . $f['date_from'] . '» تا «' . $f['date_to'] . '» هیچ سفارشی پیدا نشد. ماه دیگری را انتخاب کنید.';
        } elseif ( ! empty( $orders ) && empty( $groups_all ) ) {
            $paid_n = count( WAP_Data::filter_paid_orders( $orders ) );
            $notices[] = 'در این بازه ' . number_format( count( $orders ) ) . ' سفارش هست، ولی سفارش موفق/پرداخت‌شده برای گزارش نیست'
                . ( $paid_n === 0 ? ' (همه لغو/ناموفق/در انتظار هستند).' : '.' );
        } elseif ( ! empty( $groups_all ) && empty( $groups ) ) {
            $has_amt = ( $f['min_total'] !== null || $f['max_total'] !== null || $f['min_count'] !== null || $f['max_count'] !== null );
            if ( $has_amt ) {
                $notices[] = 'با فیلتر حداقل/حداکثر مبلغ یا تعداد، هیچ دوره‌ای باقی نماند. این فیلدها را خالی کنید و دوباره «اعمال فیلتر» بزنید.';
            } else {
                $notices[] = 'داده‌ای برای نمایش دوره‌ها نیست. بازه تاریخ یا وضعیت سفارش را عوض کنید.';
            }
        }
        if ( $prepared['compare_ready'] && empty( $orders2 ) ) {
            $notices[] = 'بازه مقایسه («' . $cmp_from . '» تا «' . $cmp_to . '») سفارشی ندارد؛ فقط بازه فعلی نمایش داده می‌شود.';
        } elseif ( $prepared['compare_ready'] && ! empty( $groups ) ) {
            $notices[] = 'مقایسه فعال است: «' . $f['date_from'] . ' تا ' . $f['date_to'] . '» در برابر «' . $cmp_from . ' تا ' . $cmp_to . '».';
        }

        $base_params = array_filter( $f, function( $v ) { return $v !== null && $v !== ''; } );
        if ( $cmp_from ) {
            $base_params['compare_from'] = $cmp_from;
        }
        if ( $cmp_to ) {
            $base_params['compare_to'] = $cmp_to;
        }
        $csv_url = self::export_url( $base_params, 'csv' );
        $xml_url = self::export_url( $base_params, 'xml' );
        $pdf_url = self::export_url( $base_params, 'pdf' );
        $date_locked = class_exists( 'WAP_Report_Image' ) && WAP_Report_Image::is_date_locked();
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
                    <input type="text" id="wap_date_from" name="date_from" class="wap-jcal" data-wap-jcal="primary-from" value="<?php echo esc_attr( $f['date_from'] ); ?>" placeholder="کلیک کنید — انتخاب روز یا ماه" autocomplete="off" <?php echo $date_locked ? 'readonly data-wap-date-locked="1"' : ''; ?>>
                </div>
                <div class="wap-field wap-field-date">
                    <label>تا تاریخ (شمسی)</label>
                    <input type="text" id="wap_date_to" name="date_to" class="wap-jcal" data-wap-jcal="primary-to" value="<?php echo esc_attr( $f['date_to'] ); ?>" placeholder="کلیک کنید — انتخاب روز یا ماه" autocomplete="off" <?php echo $date_locked ? 'readonly data-wap-date-locked="1"' : ''; ?>>
                </div>
                <div class="wap-field wap-field-date">
                    <label>مقایسه از</label>
                    <input type="text" id="wap_compare_from" name="compare_from" class="wap-jcal" data-wap-jcal="compare-from" value="<?php echo esc_attr( $cmp_from ); ?>" placeholder="ماه مشابه — از" autocomplete="off" <?php echo $date_locked ? 'readonly data-wap-date-locked="1"' : ''; ?>>
                </div>
                <div class="wap-field wap-field-date">
                    <label>مقایسه تا</label>
                    <input type="text" id="wap_compare_to" name="compare_to" class="wap-jcal" data-wap-jcal="compare-to" value="<?php echo esc_attr( $cmp_to ); ?>" placeholder="ماه مشابه — تا" autocomplete="off" <?php echo $date_locked ? 'readonly data-wap-date-locked="1"' : ''; ?>>
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
            <?php self::render_month_pickers( $date_locked ); ?>
            <?php self::render_filter_notices( $notices ); ?>

            <div class="wap-export-bar" data-wap-no-capture>
                <span class="wap-export-label">خروجی گزارش:</span>
                <a class="wap-btn wap-btn-csv" href="<?php echo esc_url( $csv_url ); ?>">CSV</a>
                <a class="wap-btn wap-btn-xml" href="<?php echo esc_url( $xml_url ); ?>">XML</a>
                <a class="wap-btn wap-btn-pdf" href="<?php echo esc_url( $pdf_url ); ?>" target="_blank">چاپ / PDF</a>
                <?php self::render_image_export_tools( 'sales' ); ?>
            </div>

            <div id="wap_capture" class="wap-capture">
            <div class="wap-cards" data-wap-capture-part="cards">
                <div class="wap-card">
                    <span class="wap-card-label">فروش ناخالص</span>
                    <span class="wap-card-value"><?php echo esc_html( number_format( $gn['gross_total'] ) . ' ' . $currency ); ?></span>
                    <span class="wap-card-accent"><?php echo esc_html( number_format( $gn['gross_count'] ) ); ?> سفارش (همه)</span>
                </div>
                <div class="wap-card wap-card-net">
                    <span class="wap-card-label">فروش خالص (موفق)</span>
                    <span class="wap-card-value"><?php echo esc_html( number_format( $gn['net_total'] ) . ' ' . $currency ); ?></span>
                    <span class="wap-card-accent"><?php echo esc_html( number_format( $gn['net_count'] ) ); ?> سفارش موفق</span>
                </div>
                <div class="wap-card wap-card-accent">
                    <span class="wap-card-label">میانگین سفارش موفق</span>
                    <span class="wap-card-value"><?php echo esc_html( number_format( $overall_count > 0 ? $overall_total / $overall_count : 0 ) . ' ' . $currency ); ?></span>
                </div>
            </div>
            <?php if ( $gn2 ) : ?>
            <div class="wap-cards wap-compare-cards" data-wap-capture-part="cards">
                <div class="wap-card">
                    <span class="wap-card-label">مقایسه ناخالص (<?php echo esc_html( $cmp_from . ' تا ' . $cmp_to ); ?>)</span>
                    <span class="wap-card-value"><?php echo esc_html( number_format( $gn2['gross_total'] ) . ' ' . $currency ); ?></span>
                    <span class="wap-card-accent">Δ ناخالص: <?php echo esc_html( number_format( $gn['gross_total'] - $gn2['gross_total'] ) ); ?></span>
                </div>
                <div class="wap-card wap-card-net">
                    <span class="wap-card-label">مقایسه خالص</span>
                    <span class="wap-card-value"><?php echo esc_html( number_format( $gn2['net_total'] ) . ' ' . $currency ); ?></span>
                    <span class="wap-card-accent">Δ خالص: <?php echo esc_html( number_format( $gn['net_total'] - $gn2['net_total'] ) ); ?></span>
                </div>
            </div>
            <?php endif; ?>

            <?php
            if ( class_exists( 'WAP_Chart' ) ) :
                if ( $prepared['compare_ready'] ) {
                    // مثل Search Console: دو خط روزانه روی‌هم (روز ۱ با روز ۱)
                    $overlay = WAP_Chart::build_date_overlay(
                        $orders,
                        $orders2,
                        (string) $f['date_from'],
                        (string) $f['date_to'],
                        (string) $cmp_from,
                        (string) $cmp_to
                    );
                    $aligned = WAP_Chart::align_period_series(
                        ! empty( $groups ) ? $groups : array(),
                        ! empty( $groups2 ) ? $groups2 : array()
                    );
                    WAP_Chart::render_gsc_line(
                        $overlay,
                        array(
                            'title'         => 'مقایسه فروش — سبک Search Console',
                            'legend_a'      => $f['date_from'] . ' تا ' . $f['date_to'],
                            'legend_b'      => $cmp_from . ' تا ' . $cmp_to,
                            'dual'          => true,
                            'show_metrics'  => true,
                            'height'        => 360,
                        )
                    );
                } elseif ( ! empty( $groups ) ) {
                    $aligned = null;
                    WAP_Chart::render_gsc_line(
                        array(
                            'labels' => array_column( array_values( $groups ), 'label' ),
                            'a'      => array_map( 'floatval', array_column( array_values( $groups ), 'total' ) ),
                        ),
                        array(
                            'title'    => 'روند فروش بر اساس دوره',
                            'legend_a' => $f['date_from'] . ' تا ' . $f['date_to'],
                            'dual'     => false,
                            'height'   => 300,
                        )
                    );
                }
            endif;
            ?>

            <div class="wap-table-wrap" data-wap-capture-part="table">
                <table class="wap-table">
                    <?php if ( ! empty( $aligned ) && ! empty( $aligned['rows'] ) ) : ?>
                    <thead><tr><th>دوره (فعلی / مقایسه)</th><th>فروش فعلی</th><th>فروش مقایسه</th><th>Δ مبلغ</th><th>Δ٪</th><th>تعداد فعلی</th><th>تعداد مقایسه</th></tr></thead>
                    <tbody>
                    <?php foreach ( $aligned['rows'] as $row ) :
                        $up = $row['delta_total'] >= 0; ?>
                        <tr>
                            <td><?php echo esc_html( $row['label'] ); ?></td>
                            <td><strong><?php echo esc_html( number_format( $row['total_a'] ) ); ?></strong></td>
                            <td><?php echo esc_html( number_format( $row['total_b'] ) ); ?></td>
                            <td class="<?php echo $up ? 'wap-delta-up' : 'wap-delta-down'; ?>"><?php echo esc_html( ( $up ? '+' : '' ) . number_format( $row['delta_total'] ) ); ?></td>
                            <td class="<?php echo $up ? 'wap-delta-up' : 'wap-delta-down'; ?>"><?php echo esc_html( ( $up ? '+' : '' ) . number_format( $row['delta_pct'], 1 ) . '%' ); ?></td>
                            <td><?php echo esc_html( number_format( $row['count_a'] ) ); ?></td>
                            <td><?php echo esc_html( number_format( $row['count_b'] ) ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <?php else : ?>
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
                    <?php endif; ?>
                </table>
            </div>
            </div><!-- #wap_capture -->
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

        <div class="wap-export-bar" data-wap-no-capture>
            <span class="wap-export-label">خروجی گزارش:</span>
            <a class="wap-btn wap-btn-csv" href="<?php echo esc_url( $csv_url ); ?>">CSV</a>
            <?php self::render_image_export_tools( 'orders' ); ?>
        <?php if ( $can_edit_orders ) : ?>
            <a class="wap-btn wap-btn-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=wci-order-edit' ) ); ?>" target="_blank">سفارش جدید</a>
        <?php endif; ?>
        </div>

        <div id="wap_capture" class="wap-capture">
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

        <form method="post" id="wap-orders-bulk-form" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <?php wp_nonce_field( 'wci_bulk_orders' ); ?>
            <input type="hidden" name="action" value="wap_bulk_orders">
            <input type="hidden" name="wap_return_url" value="<?php echo esc_url( add_query_arg( 'wap_view', 'orders', self::panel_url() ) ); ?>">
            <input type="hidden" name="wap_view" value="orders">
            <?php foreach ( $f as $fk => $fv ) : ?>
                <input type="hidden" name="<?php echo esc_attr( $fk ); ?>" value="<?php echo esc_attr( (string) $fv ); ?>">
            <?php endforeach; ?>

        <?php if ( $can_bulk ) : ?>
        <div class="wap-bulk-bar" data-wap-no-capture>
            <select name="wci_bulk_action" class="wap-bulk-select">
                <?php foreach ( WAP_Order_Service::bulk_action_options() as $value => $label ) : ?>
                    <option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $label ); ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" name="wci_bulk_apply" class="wap-btn wap-btn-primary" value="1">اجرا</button>
        </div>
        <?php endif; ?>

        <div class="wap-table-wrap">
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
        <div class="wap-bulk-bar wap-bulk-bar-bottom" data-wap-no-capture>
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
        </div><!-- #wap_capture -->

        <?php if ( $total_pages > 1 ) : ?>
        <div class="wap-pagination" data-wap-no-capture>
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
        $prepared   = self::prepare_date_ranges( WAP_Data::get_filters() );
        $f          = $prepared['filters'];
        $notices    = $prepared['notices'];
        $cmp_from   = $prepared['compare_from'];
        $cmp_to     = $prepared['compare_to'];
        $product_id = ! empty( $_GET['product_id'] ) ? (int) $_GET['product_id'] : 0;
        $orders     = $prepared['primary_invalid'] ? array() : WAP_Data::get_orders( $f );
        $presets    = WAP_Data::quick_presets();
        $currency   = get_woocommerce_currency_symbol();
        if ( ! $prepared['primary_invalid'] && empty( $orders ) ) {
            $notices[] = 'در بازه «' . $f['date_from'] . '» تا «' . $f['date_to'] . '» هیچ سفارشی پیدا نشد. ماه را از لیست زیر انتخاب کنید.';
        }
        $base_params = array_filter( $f, function( $v ) { return $v !== null && $v !== ''; } );
        if ( $cmp_from ) {
            $base_params['compare_from'] = $cmp_from;
        }
        if ( $cmp_to ) {
            $base_params['compare_to'] = $cmp_to;
        }
        $export_params = array_merge( $base_params, array( 'wap_view' => 'products' ) );
        if ( $product_id ) {
            $export_params['product_id'] = $product_id;
        }
        $products_csv_url = self::export_url(
            $export_params,
            $product_id ? 'product_orders_csv' : 'products_csv'
        );
        $date_locked = class_exists( 'WAP_Report_Image' ) && WAP_Report_Image::is_date_locked();
        ?>
        <form method="get" action="<?php echo esc_url( self::panel_url() ); ?>" class="wap-filters">
            <input type="hidden" name="wap_view" value="products">
            <?php if ( $product_id ) : ?><input type="hidden" name="product_id" value="<?php echo esc_attr( $product_id ); ?>"><?php endif; ?>
            <div class="wap-field wap-field-date">
                <label>از تاریخ (شمسی)</label>
                <input type="text" id="wap_date_from" name="date_from" class="wap-jcal" data-wap-jcal="primary-from" value="<?php echo esc_attr( $f['date_from'] ); ?>" placeholder="کلیک کنید — انتخاب روز یا ماه" autocomplete="off" <?php echo $date_locked ? 'readonly data-wap-date-locked="1"' : ''; ?>>
            </div>
            <div class="wap-field wap-field-date">
                <label>تا تاریخ (شمسی)</label>
                <input type="text" id="wap_date_to" name="date_to" class="wap-jcal" data-wap-jcal="primary-to" value="<?php echo esc_attr( $f['date_to'] ); ?>" placeholder="کلیک کنید — انتخاب روز یا ماه" autocomplete="off" <?php echo $date_locked ? 'readonly data-wap-date-locked="1"' : ''; ?>>
            </div>
            <div class="wap-field wap-field-date">
                <label>مقایسه از</label>
                <input type="text" id="wap_compare_from" name="compare_from" class="wap-jcal" data-wap-jcal="compare-from" value="<?php echo esc_attr( $cmp_from ); ?>" placeholder="ماه مشابه — از" autocomplete="off" <?php echo $date_locked ? 'readonly data-wap-date-locked="1"' : ''; ?>>
            </div>
            <div class="wap-field wap-field-date">
                <label>مقایسه تا</label>
                <input type="text" id="wap_compare_to" name="compare_to" class="wap-jcal" data-wap-jcal="compare-to" value="<?php echo esc_attr( $cmp_to ); ?>" placeholder="ماه مشابه — تا" autocomplete="off" <?php echo $date_locked ? 'readonly data-wap-date-locked="1"' : ''; ?>>
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
        <?php self::render_month_pickers( $date_locked ); ?>
        <?php self::render_filter_notices( $notices ); ?>

        <?php if ( $product_id ) :
            $product      = wc_get_product( $product_id );
            $product_name = $product ? $product->get_name() : ( '#' . $product_id );
            $rows         = WAP_Data::get_product_drilldown( $orders, $product_id );
            $total_qty = 0; $total_revenue = 0.0;
            foreach ( $rows as $r ) { $total_qty += $r['qty']; $total_revenue += $r['revenue']; }
            ?>
            <h2 class="wap-section-title">سفارش‌های محصول: <?php echo esc_html( $product_name ); ?>
                <a href="<?php echo esc_url( add_query_arg( array_merge( $base_params, array( 'wap_view' => 'products' ) ), self::panel_url() ) ); ?>" class="wap-btn wap-btn-ghost" style="font-size:12px">← بازگشت</a>
            </h2>
            <?php self::render_export_bar( $products_csv_url, 'products' ); ?>
            <div id="wap_capture" class="wap-capture">
            <div class="wap-cards">
                <div class="wap-card">
                    <span class="wap-card-label">تعداد سفارش</span><span class="wap-card-value"><?php echo esc_html( number_format( count( $rows ) ) ); ?></span></div>
                <div class="wap-card wap-card-accent">
                    <span class="wap-card-label">تعداد فروخته‌شده</span><span class="wap-card-value"><?php echo esc_html( number_format( $total_qty ) ); ?></span></div>
                <div class="wap-card wap-card-net">
                    <span class="wap-card-label">درآمد کل</span><span class="wap-card-value"><?php echo esc_html( number_format( $total_revenue ) . ' ' . $currency ); ?></span></div>
            </div>
            <div class="wap-table-wrap">
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
            </div><!-- #wap_capture -->
        <?php else :
            $products      = WAP_Data::get_product_sales( $orders );
            $total_qty     = array_sum( array_column( $products, 'qty' ) );
            $total_revenue = array_sum( array_column( $products, 'revenue' ) );
            $products2     = array();
            $aligned_prod  = null;
            if ( $prepared['compare_ready'] ) {
                $f2 = array_merge( $f, array( 'date_from' => $cmp_from, 'date_to' => $cmp_to ) );
                $orders2 = WAP_Data::get_orders( $f2 );
                $products2 = WAP_Data::get_product_sales( $orders2 );
                if ( empty( $orders2 ) ) {
                    echo '<div class="wap-alert" role="status">بازه مقایسه («' . esc_html( $cmp_from ) . '» تا «' . esc_html( $cmp_to ) . '») سفارشی ندارد.</div>';
                }
                if ( class_exists( 'WAP_Chart' ) && ! empty( $products2 ) ) {
                    $aligned_prod = WAP_Chart::align_product_series( $products, $products2, 15 );
                }
            }
            ?>
            <?php self::render_export_bar( $products_csv_url, 'products' ); ?>
            <div id="wap_capture" class="wap-capture">
            <div class="wap-cards" data-wap-capture-part="cards">
                <div class="wap-card">
                    <span class="wap-card-label">تعداد محصولات فروخته‌شده</span><span class="wap-card-value"><?php echo esc_html( number_format( count( $products ) ) ); ?></span></div>
                <div class="wap-card wap-card-accent">
                    <span class="wap-card-label">مجموع فروش</span><span class="wap-card-value"><?php echo esc_html( number_format( $total_revenue ) . ' ' . $currency ); ?></span></div>
                <div class="wap-card wap-card-net">
                    <span class="wap-card-label">تعداد کل اقلام</span><span class="wap-card-value"><?php echo esc_html( number_format( $total_qty ) ); ?></span></div>
            </div>

            <?php
            if ( class_exists( 'WAP_Chart' ) && ! empty( $products ) ) {
                if ( $aligned_prod ) {
                    WAP_Chart::render_gsc_product_compare(
                        $aligned_prod,
                        array(
                            'title'    => 'مقایسه فروش محصولات',
                            'legend_a' => $f['date_from'] . ' تا ' . $f['date_to'],
                            'legend_b' => $cmp_from . ' تا ' . $cmp_to,
                        )
                    );
                    // روند درآمد محصولات برتر (فعلی در برابر مقایسه) به‌صورت خطی GSC
                    WAP_Chart::render_gsc_line(
                        array(
                            'labels' => array_map( function( $r ) {
                                return function_exists( 'mb_strimwidth' ) ? mb_strimwidth( $r['name'], 0, 16, '…' ) : substr( $r['name'], 0, 14 );
                            }, $aligned_prod['rows'] ),
                            'a' => $aligned_prod['a'],
                            'b' => $aligned_prod['b'],
                        ),
                        array(
                            'title'    => 'روند مقایسه‌ای درآمد محصولات',
                            'legend_a' => 'بازه فعلی',
                            'legend_b' => 'بازه مقایسه',
                            'dual'     => true,
                            'height'   => 280,
                        )
                    );
                } else {
                    $top = array_slice( $products, 0, 12 );
                    WAP_Chart::render_gsc_line(
                        array(
                            'labels' => array_map( function( $p ) {
                                return function_exists( 'mb_strimwidth' ) ? mb_strimwidth( $p['name'], 0, 16, '…' ) : substr( $p['name'], 0, 14 );
                            }, $top ),
                            'a' => array_map( 'floatval', array_column( $top, 'revenue' ) ),
                        ),
                        array(
                            'title'    => 'برترین محصولات بر اساس درآمد',
                            'legend_a' => $f['date_from'] . ' تا ' . $f['date_to'],
                            'dual'     => false,
                            'height'   => 280,
                        )
                    );
                }
            }
            ?>

            <div class="wap-table-wrap" data-wap-capture-part="table">
                <table class="wap-table">
                    <?php if ( $aligned_prod ) : ?>
                    <thead><tr><th>#</th><th>نام محصول</th><th>فروش فعلی</th><th>فروش مقایسه</th><th>Δ مبلغ</th><th>Δ٪</th><th>تعداد فعلی</th><th>تعداد مقایسه</th></tr></thead>
                    <tbody>
                    <?php $i = 1; foreach ( $aligned_prod['rows'] as $r ) :
                        $url = add_query_arg( array_merge( $base_params, array( 'wap_view' => 'products', 'product_id' => $r['pid'] ) ), self::panel_url() );
                        $up = $r['delta'] >= 0;
                        ?>
                        <tr>
                            <td><?php echo $i++; ?></td>
                            <td><a href="<?php echo esc_url( $url ); ?>"><strong><?php echo esc_html( $r['name'] ); ?></strong></a></td>
                            <td><strong><?php echo esc_html( number_format( $r['revenue_a'] ) ); ?></strong></td>
                            <td><?php echo esc_html( number_format( $r['revenue_b'] ) ); ?></td>
                            <td class="<?php echo $up ? 'wap-delta-up' : 'wap-delta-down'; ?>"><?php echo esc_html( ( $up ? '+' : '' ) . number_format( $r['delta'] ) ); ?></td>
                            <td class="<?php echo $up ? 'wap-delta-up' : 'wap-delta-down'; ?>"><?php echo esc_html( ( $up ? '+' : '' ) . number_format( $r['delta_pct'], 1 ) . '%' ); ?></td>
                            <td><?php echo esc_html( number_format( $r['qty_a'] ) ); ?></td>
                            <td><?php echo esc_html( number_format( $r['qty_b'] ) ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <?php else : ?>
                    <thead><tr><th>#</th><th>نام محصول</th><th>SKU</th><th>تعداد فروخته‌شده</th><th>تعداد سفارشات</th><th>درآمد کل</th></tr></thead>
                    <tbody>
                    <?php if ( empty( $products ) ) : ?>
                        <tr><td colspan="6" class="wap-empty">محصولی یافت نشد.</td></tr>
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
                    <?php endif; ?>
                </table>
            </div>
            </div><!-- #wap_capture -->
        <?php endif;
    }

    private static function render_shaparak_tab() {
        if ( ! class_exists( 'WAP_Zarinpal_Report' ) ) {
            echo '<div class="wap-alert">ماژول گزارش شاپرک بارگذاری نشده است.</div>';
            return;
        }
        $report  = WAP_Zarinpal_Report::build();
        $f       = $report['filters'];
        $s       = $report['summary'];
        $presets = WAP_Data::quick_presets();
        $currency = function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : 'تومان';
        $csv_url = self::export_url( array_merge( $f, array( 'wap_view' => 'shaparak' ) ), 'shaparak_csv' );
        $xlsx_url = self::export_url( array_merge( $f, array( 'wap_view' => 'shaparak' ) ), 'shaparak_xlsx' );
        ?>
        <form method="get" class="wap-filters" action="<?php echo esc_url( self::panel_url() ); ?>">
            <input type="hidden" name="wap_view" value="shaparak">
            <div class="wap-field wap-field-date">
                <label>از تاریخ (شمسی)</label>
                <input type="text" id="wap_date_from" name="date_from" value="<?php echo esc_attr( $f['date_from'] ); ?>" placeholder="۱۴۰۴/۰۱/۰۱" autocomplete="off">
            </div>
            <div class="wap-field wap-field-date">
                <label>تا تاریخ (شمسی)</label>
                <input type="text" id="wap_date_to" name="date_to" value="<?php echo esc_attr( $f['date_to'] ); ?>" placeholder="۱۴۰۴/۱۲/۲۹" autocomplete="off">
            </div>
            <div class="wap-field">
                <label class="wap-check" style="display:flex;align-items:center;gap:6px;margin-top:22px">
                    <input type="hidden" name="only_paid" value="0">
                    <input type="checkbox" name="only_paid" value="1" <?php checked( ! empty( $f['only_paid'] ) ); ?>>
                    فقط سفارش‌های موفق
                </label>
            </div>
            <div class="wap-field wap-field-actions">
                <button type="submit" class="wap-btn wap-btn-primary">اعمال فیلتر</button>
                <a href="<?php echo esc_url( add_query_arg( 'wap_view', 'shaparak', self::panel_url() ) ); ?>" class="wap-btn wap-btn-ghost">پاک کردن</a>
            </div>
        </form>

        <div class="wap-presets" id="wap_presets">
            <?php foreach ( $presets as $label => $range ) : ?>
                <button type="button" class="wap-chip" data-from="<?php echo esc_attr( $range[0] ); ?>" data-to="<?php echo esc_attr( $range[1] ); ?>"><?php echo esc_html( $label ); ?></button>
            <?php endforeach; ?>
        </div>

        <p class="wap-hint" style="margin:8px 0 16px;line-height:1.8;color:#475569">
            <?php echo esc_html( WAP_Zarinpal_Fee::tariff_note() ); ?>
            واریز شاپرک معمولاً یک روز کاری بعد از خرید است؛ اختلاف بازهٔ سفارش و واریز طبیعی است.
        </p>

        <?php if ( $report['error'] !== '' ) : ?>
            <div class="wap-alert"><?php echo esc_html( $report['error'] ); ?></div>
        <?php endif; ?>

        <div class="wap-export-bar" data-wap-no-capture>
            <span class="wap-export-label">خروجی گزارش:</span>
            <a class="wap-btn wap-btn-csv" href="<?php echo esc_url( $xlsx_url ); ?>">اکسل (.xlsx)</a>
            <a class="wap-btn wap-btn-ghost" href="<?php echo esc_url( $csv_url ); ?>">CSV</a>
            <?php self::render_image_export_tools( 'shaparak' ); ?>
        </div>

        <div id="wap_capture" class="wap-capture">
        <div class="wap-cards">
            <div class="wap-card">
                <div class="wap-card-label">خرید ووکامرس (زرین‌پال)</div>
                <div class="wap-card-value"><?php echo esc_html( number_format( $s['wc_gross'] ) ); ?> <small><?php echo esc_html( $currency ); ?></small></div>
                <div class="wap-card-accent"><?php echo esc_html( number_format( $s['wc_count'] ) ); ?> سفارش</div>
            </div>
            <div class="wap-card">
                <div class="wap-card-label">کارمزد زرین‌پال</div>
                <div class="wap-card-value"><?php echo esc_html( number_format( $s['wc_fee'] ) ); ?> <small><?php echo esc_html( $currency ); ?></small></div>
                <div class="wap-card-accent">تعرفه رسمی ۰٫۵٪+۵۰۰</div>
            </div>
            <div class="wap-card wap-card-net">
                <div class="wap-card-label">خالص مورد انتظار</div>
                <div class="wap-card-value"><?php echo esc_html( number_format( $s['wc_net'] ) ); ?> <small><?php echo esc_html( $currency ); ?></small></div>
                <div class="wap-card-accent">خرید − کارمزد</div>
            </div>
            <div class="wap-card">
                <div class="wap-card-label">واریز شاپرک (عین پنل زرین‌پال)</div>
                <div class="wap-card-value"><?php echo esc_html( number_format( $s['settle_total_rial'] ?? ( $s['settle_total'] * 10 ) ) ); ?> <small>ریال</small></div>
                <div class="wap-card-accent"><?php echo esc_html( number_format( $s['settle_count'] ) ); ?> تسویه — معادل <?php echo esc_html( number_format( $s['settle_total'] ) ); ?> تومان — Δ خالص: <?php echo esc_html( number_format( $s['diff_net_settle'] ) ); ?></div>
            </div>
        </div>

        <div class="wap-table-wrap" style="margin-bottom:24px">
            <h3 style="margin:0 0 10px">خریدهای ووکامرس (درگاه زرین‌پال)</h3>
            <table class="wap-table">
                <thead>
                    <tr>
                        <th>سفارش</th>
                        <th>تاریخ</th>
                        <th>خریدار</th>
                        <th>وضعیت</th>
                        <th>مبلغ</th>
                        <th>کارمزد</th>
                        <th>خالص</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ( empty( $report['orders'] ) ) : ?>
                    <tr><td colspan="7" class="wap-empty">سفارش زرین‌پالی در این بازه نیست.</td></tr>
                <?php else : foreach ( $report['orders'] as $o ) : ?>
                    <tr>
                        <td><strong>#<?php echo esc_html( $o['order_number'] ); ?></strong></td>
                        <td dir="ltr"><?php echo esc_html( $o['date_jalali'] ); ?></td>
                        <td><?php echo esc_html( $o['customer'] ); ?></td>
                        <td><?php echo esc_html( $o['status_label'] ); ?></td>
                        <td><?php echo esc_html( number_format( $o['gross'] ) ); ?></td>
                        <td><?php echo esc_html( number_format( $o['fee'] ) ); ?></td>
                        <td><strong><?php echo esc_html( number_format( $o['net'] ) ); ?></strong></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

        <div class="wap-table-wrap">
            <h3 style="margin:0 0 10px">واریزهای شاپرک به حساب (دقیقاً از API زرین‌پال — وضعیت PAID)</h3>
            <table class="wap-table">
                <thead>
                    <tr>
                        <th>شناسه تسویه</th>
                        <th>تاریخ واریز</th>
                        <th>مبلغ ریال (عین پنل)</th>
                        <th>معادل تومان</th>
                        <th>شناسه ارجاع بانکی</th>
                        <th>وضعیت</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ( empty( $report['settles'] ) ) : ?>
                    <tr><td colspan="6" class="wap-empty">واریز PAID در این بازه یافت نشد یا API تنظیم نشده است.</td></tr>
                <?php else : foreach ( $report['settles'] as $r ) : ?>
                    <tr>
                        <td dir="ltr"><?php echo esc_html( $r['id'] ); ?></td>
                        <td dir="ltr"><?php echo esc_html( $r['date_jalali'] ?: $r['reconciled_at'] ); ?></td>
                        <td dir="ltr"><strong><?php echo esc_html( number_format( $r['amount_rial'] ) ); ?></strong></td>
                        <td><?php echo esc_html( number_format( $r['amount'] ) ); ?></td>
                        <td dir="ltr" style="font-size:12px"><?php echo esc_html( $r['reference_id'] ); ?></td>
                        <td><?php echo esc_html( $r['status'] ); ?></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        </div><!-- #wap_capture -->
        <?php
    }

    private static function render_analytics_tab() {
        if ( ! class_exists( 'WAP_Analytics' ) ) {
            echo '<div class="wap-alert">ماژول داشبورد بارگذاری نشده است.</div>';
            return;
        }
        $data = WAP_Analytics::build();
        $f    = $data['filters'];
        $gn   = $data['gross_net'];
        $fees = $data['fees'];
        $presets = WAP_Data::quick_presets();
        $currency = function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : 'تومان';
        $max_hour = max( 1, max( array_column( $data['peak_hours'], 'count' ) ) );
        $max_day  = max( 1, max( array_column( $data['peak_days'], 'count' ) ) );
        $max_gw   = max( 1, (float) max( array_merge( array( 1 ), array_column( $data['gateways'], 'total' ) ) ) );
        $max_tr   = max( 1, (float) max( array_merge( array( 1 ), array_column( $data['traffic'], 'total' ) ) ) );
        $max_prod = max( 1, (float) max( array_merge( array( 1 ), array_column( $data['top_products'], 'revenue' ) ) ) );
        ?>
        <form method="get" class="wap-filters" action="<?php echo esc_url( self::panel_url() ); ?>">
            <input type="hidden" name="wap_view" value="analytics">
            <div class="wap-field wap-field-date">
                <label>از تاریخ (شمسی)</label>
                <input type="text" id="wap_date_from" name="date_from" value="<?php echo esc_attr( $f['date_from'] ); ?>" placeholder="۱۴۰۴/۰۱/۰۱" autocomplete="off">
            </div>
            <div class="wap-field wap-field-date">
                <label>تا تاریخ (شمسی)</label>
                <input type="text" id="wap_date_to" name="date_to" value="<?php echo esc_attr( $f['date_to'] ); ?>" placeholder="۱۴۰۴/۱۲/۲۹" autocomplete="off">
            </div>
            <div class="wap-field wap-field-actions">
                <button type="submit" class="wap-btn wap-btn-primary">اعمال</button>
                <a class="wap-btn wap-btn-ghost" href="<?php echo esc_url( add_query_arg( 'wap_view', 'analytics', self::panel_url() ) ); ?>">پاک کردن</a>
            </div>
        </form>
        <div class="wap-presets" id="wap_presets">
            <?php foreach ( $presets as $label => $range ) : ?>
                <button type="button" class="wap-chip" data-from="<?php echo esc_attr( $range[0] ); ?>" data-to="<?php echo esc_attr( $range[1] ); ?>"><?php echo esc_html( $label ); ?></button>
            <?php endforeach; ?>
        </div>

        <div class="wap-export-bar" data-wap-no-capture>
            <span class="wap-export-label">خروجی تصویری داشبورد:</span>
            <?php self::render_image_export_tools( 'analytics' ); ?>
        </div>

        <div id="wap_capture" class="wap-capture">
        <div class="wap-cards">
            <div class="wap-card">
                <div class="wap-card-label">فروش ناخالص</div>
                <div class="wap-card-value"><?php echo esc_html( number_format( $gn['gross_total'] ) ); ?> <small><?php echo esc_html( $currency ); ?></small></div>
                <div class="wap-card-accent"><?php echo esc_html( number_format( $gn['gross_count'] ) ); ?> سفارش</div>
            </div>
            <div class="wap-card wap-card-net">
                <div class="wap-card-label">فروش خالص (موفق)</div>
                <div class="wap-card-value"><?php echo esc_html( number_format( $gn['net_total'] ) ); ?> <small><?php echo esc_html( $currency ); ?></small></div>
                <div class="wap-card-accent"><?php echo esc_html( number_format( $gn['net_count'] ) ); ?> سفارش موفق</div>
            </div>
            <div class="wap-card">
                <div class="wap-card-label">کارمزد تخمینی درگاه‌ها</div>
                <div class="wap-card-value"><?php echo esc_html( number_format( $fees['fee'] ) ); ?> <small><?php echo esc_html( $currency ); ?></small></div>
                <div class="wap-card-accent">خالص پس از کارمزد: <?php echo esc_html( number_format( $fees['net'] ) ); ?></div>
            </div>
            <div class="wap-card wap-card-accent">
                <div class="wap-card-label">مشتریان ثابت (≥۳ خرید)</div>
                <div class="wap-card-value"><?php echo esc_html( number_format( $data['loyal']['count'] ) ); ?></div>
                <div class="wap-card-accent">در بازه انتخابی</div>
            </div>
        </div>

        <div class="wap-analytics-grid">
            <section class="wap-chart-card">
                <h3 class="wap-section-title">درگاه‌های پرداخت</h3>
                <p class="wap-hint">زرین‌پال، زیبال، ترب‌پی، اسنپ‌پی، دیجی‌پی، آیدی‌پی — با کارمزد تخمینی طبق تعرفه</p>
                <?php if ( empty( $data['gateways'] ) ) : ?>
                    <div class="wap-empty">داده‌ای نیست.</div>
                <?php else : foreach ( $data['gateways'] as $g ) :
                    $pct = (int) round( $g['total'] / $max_gw * 100 ); ?>
                    <div class="wap-hbar">
                        <div class="wap-hbar-label"><?php echo esc_html( $g['label'] ); ?> <small>(<?php echo esc_html( $g['note'] ); ?>)</small></div>
                        <div class="wap-hbar-track"><div class="wap-hbar-fill" style="width:<?php echo $pct; ?>%"></div></div>
                        <div class="wap-hbar-val"><?php echo esc_html( number_format( $g['total'] ) ); ?> — <?php echo esc_html( number_format( $g['count'] ) ); ?> سفارش — کارمزد ~<?php echo esc_html( number_format( $g['fee'] ) ); ?></div>
                    </div>
                <?php endforeach; endif; ?>
            </section>

            <section class="wap-chart-card">
                <h3 class="wap-section-title">منبع ورود (گوگل / سوشال / …)</h3>
                <p class="wap-hint">از سفارش‌های جدید با ردیابی کوکی؛ سفارش‌های قدیمی «ورود مستقیم» نشان داده می‌شوند.</p>
                <?php if ( empty( $data['traffic'] ) ) : ?>
                    <div class="wap-empty">داده‌ای نیست.</div>
                <?php else : foreach ( $data['traffic'] as $t ) :
                    $pct = (int) round( $t['total'] / $max_tr * 100 ); ?>
                    <div class="wap-hbar">
                        <div class="wap-hbar-label"><?php echo esc_html( $t['label'] ); ?></div>
                        <div class="wap-hbar-track"><div class="wap-hbar-fill wap-hbar-traffic" style="width:<?php echo $pct; ?>%"></div></div>
                        <div class="wap-hbar-val"><?php echo esc_html( number_format( $t['total'] ) ); ?> — <?php echo esc_html( number_format( $t['count'] ) ); ?> سفارش</div>
                    </div>
                <?php endforeach; endif; ?>
            </section>
        </div>

        <?php if ( class_exists( 'WAP_Chart' ) && ! empty( $data['top_products'] ) ) :
            $top10 = array_slice( $data['top_products'], 0, 10 );
            WAP_Chart::render_gsc_line(
                array(
                    'labels' => array_map( function( $p ) {
                        return function_exists( 'mb_strimwidth' ) ? mb_strimwidth( $p['name'], 0, 16, '…' ) : substr( $p['name'], 0, 14 );
                    }, $top10 ),
                    'a' => array_map( 'floatval', array_column( $top10, 'revenue' ) ),
                ),
                array(
                    'title'    => 'پرفروش‌ترین محصولات',
                    'legend_a' => 'درآمد',
                    'dual'     => false,
                    'height'   => 280,
                )
            );
        else : ?>
        <section class="wap-chart-card">
            <h3 class="wap-section-title">پرفروش‌ترین محصولات</h3>
            <div class="wap-empty">محصولی نیست.</div>
        </section>
        <?php endif; ?>

        <div class="wap-peak-calendar-wrap">
            <section class="wap-chart-card wap-peak-cal-section">
                <h3 class="wap-section-title">پیک خرید — تقویم شمسی</h3>
                <p class="wap-hint">هر خانه یک روز است؛ رنگ پررنگ‌تر یعنی خرید بیشتر در آن روز.</p>
                <?php
                $cal = $data['peak_calendar'] ?? array( 'months' => array(), 'max_count' => 1, 'weekdays' => array( 'ش', 'ی', 'د', 'س', 'چ', 'پ', 'ج' ) );
                $cal_max = max( 1, (int) ( $cal['max_count'] ?? 1 ) );
                if ( empty( $cal['months'] ) ) :
                    ?>
                    <div class="wap-empty">در این بازه سفارشی برای تقویم نیست.</div>
                <?php else : ?>
                    <div class="wap-peak-cal-months">
                        <?php foreach ( $cal['months'] as $month ) : ?>
                            <div class="wap-peak-cal">
                                <div class="wap-peak-cal__head"><?php echo esc_html( $month['label'] ); ?></div>
                                <div class="wap-peak-cal__weekdays">
                                    <?php foreach ( $cal['weekdays'] as $wd ) : ?>
                                        <span><?php echo esc_html( $wd ); ?></span>
                                    <?php endforeach; ?>
                                </div>
                                <div class="wap-peak-cal__grid">
                                    <?php foreach ( $month['days'] as $cell ) :
                                        if ( ! empty( $cell['blank'] ) ) : ?>
                                            <div class="wap-peak-cal__cell is-blank"></div>
                                        <?php else :
                                            $cnt = (int) ( $cell['count'] ?? 0 );
                                            $tone = WAP_Analytics::heat_tone( $cnt, $cal_max );
                                            $cls = 'wap-peak-cal__cell is-level-' . (int) $tone['level'];
                                            if ( empty( $cell['in_range'] ) ) {
                                                $cls .= ' is-out';
                                            }
                                            if ( ! empty( $cell['is_today'] ) ) {
                                                $cls .= ' is-today';
                                            }
                                            if ( $cnt > 0 ) {
                                                $cls .= ' has-orders';
                                            }
                                            $title = sprintf(
                                                '%d/%02d/%02d — %s سفارش — %s',
                                                (int) $cell['y'],
                                                (int) $cell['m'],
                                                (int) $cell['d'],
                                                number_format( $cnt ),
                                                number_format( (float) ( $cell['total'] ?? 0 ) )
                                            );
                                            ?>
                                            <div class="<?php echo esc_attr( $cls ); ?>"
                                                 style="background:<?php echo esc_attr( $tone['bg'] ); ?>;color:<?php echo esc_attr( $tone['fg'] ); ?>;border-color:<?php echo esc_attr( $tone['border'] ); ?>"
                                                 title="<?php echo esc_attr( $title ); ?>">
                                                <span class="wap-peak-cal__day"><?php echo esc_html( (string) (int) $cell['d'] ); ?></span>
                                                <?php if ( $cnt > 0 ) : ?>
                                                    <strong class="wap-peak-cal__count"><?php echo esc_html( (string) $cnt ); ?></strong>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif;
                                    endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="wap-peak-cal-legend" aria-hidden="true">
                        <span>کم</span>
                        <span class="wap-peak-cal-legend__swatch is-l1"></span>
                        <span class="wap-peak-cal-legend__swatch is-l2"></span>
                        <span class="wap-peak-cal-legend__swatch is-l3"></span>
                        <span class="wap-peak-cal-legend__swatch is-l4"></span>
                        <span class="wap-peak-cal-legend__swatch is-l5"></span>
                        <span>زیاد</span>
                    </div>
                <?php endif; ?>
            </section>
        </div>

        <div class="wap-analytics-grid">
            <section class="wap-chart-card">
                <h3 class="wap-section-title">پیک خرید — ساعت روز</h3>
                <p class="wap-hint">نمای شبانه‌روزی مثل تقویم روزانه؛ ستون پررنگ‌تر = ساعت شلوغ‌تر.</p>
                <div class="wap-peak-dayview">
                    <?php
                    $best_h = 0;
                    $best_c = -1;
                    foreach ( $data['peak_hours'] as $h ) {
                        if ( (int) $h['count'] > $best_c ) {
                            $best_c = (int) $h['count'];
                            $best_h = (int) $h['hour'];
                        }
                    }
                    foreach ( $data['peak_hours'] as $h ) :
                        $tone = WAP_Analytics::heat_tone( (int) $h['count'], (int) $max_hour );
                        $pct = (int) $h['count'] > 0 ? max( 8, (int) round( ( (int) $h['count'] / $max_hour ) * 100 ) ) : 0;
                        $is_peak = (int) $h['hour'] === $best_h && $best_c > 0;
                        ?>
                        <div class="wap-peak-dayview__row<?php echo $is_peak ? ' is-peak' : ''; ?>" title="<?php echo esc_attr( sprintf( '%02d:00 — %d سفارش — %s', $h['hour'], $h['count'], number_format( $h['total'] ) ) ); ?>">
                            <span class="wap-peak-dayview__hour"><?php echo esc_html( sprintf( '%02d:00', $h['hour'] ) ); ?></span>
                            <div class="wap-peak-dayview__track">
                                <div class="wap-peak-dayview__fill" style="width:<?php echo esc_attr( (string) $pct ); ?>%;background:<?php echo esc_attr( $tone['bg'] === '#ffffff' ? '#dadce0' : $tone['bg'] ); ?>"></div>
                            </div>
                            <span class="wap-peak-dayview__count" style="color:<?php echo esc_attr( $tone['level'] >= 3 ? '#0b57d0' : '#202124' ); ?>"><?php echo esc_html( (string) $h['count'] ); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
            <section class="wap-chart-card">
                <h3 class="wap-section-title">پیک خرید — روزهای هفته</h3>
                <p class="wap-hint">هفتهٔ تقویمی ایران (از شنبه).</p>
                <div class="wap-peak-weekcal">
                    <div class="wap-peak-weekcal__head">
                        <?php foreach ( $data['peak_days'] as $d ) : ?>
                            <span><?php echo esc_html( mb_substr( $d['label'], 0, 1 ) ); ?></span>
                        <?php endforeach; ?>
                    </div>
                    <div class="wap-peak-weekcal__body">
                        <?php foreach ( $data['peak_days'] as $d ) :
                            $tone = WAP_Analytics::heat_tone( (int) $d['count'], (int) $max_day );
                            ?>
                            <div class="wap-peak-weekcal__cell is-level-<?php echo (int) $tone['level']; ?><?php echo $d['count'] > 0 ? ' has-orders' : ''; ?>"
                                 style="background:<?php echo esc_attr( $tone['bg'] ); ?>;color:<?php echo esc_attr( $tone['fg'] ); ?>;border-color:<?php echo esc_attr( $tone['border'] ); ?>"
                                 title="<?php echo esc_attr( $d['label'] . ' — ' . $d['count'] . ' سفارش — ' . number_format( $d['total'] ) ); ?>">
                                <span class="wap-peak-weekcal__label"><?php echo esc_html( $d['label'] ); ?></span>
                                <strong><?php echo esc_html( (string) $d['count'] ); ?></strong>
                                <small><?php echo esc_html( number_format( $d['total'] ) ); ?></small>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>
        </div>

        <section class="wap-chart-card">
            <h3 class="wap-section-title">مشتریان ثابت (حداقل ۳ خرید در بازه)</h3>
            <div class="wap-table-wrap">
                <table class="wap-table">
                    <thead><tr><th>نام</th><th>تلفن</th><th>ایمیل</th><th>تعداد خرید</th><th>جمع مبلغ</th></tr></thead>
                    <tbody>
                    <?php if ( empty( $data['loyal']['rows'] ) ) : ?>
                        <tr><td colspan="5" class="wap-empty">مشتری ثابتی با این آستانه یافت نشد.</td></tr>
                    <?php else : foreach ( $data['loyal']['rows'] as $c ) : ?>
                        <tr>
                            <td><?php echo esc_html( $c['name'] ); ?></td>
                            <td dir="ltr"><?php echo esc_html( $c['phone'] ); ?></td>
                            <td dir="ltr"><?php echo esc_html( $c['email'] ); ?></td>
                            <td><strong><?php echo esc_html( number_format( $c['count'] ) ); ?></strong></td>
                            <td><?php echo esc_html( number_format( $c['total'] ) ); ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
        </div><!-- #wap_capture -->
        <?php
    }
}
