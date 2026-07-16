<?php
/**
 * Plugin Name: Hesabdar
 * Description: مدیریت کامل مشتریان و فروش ووکامرس (سفارش‌ها، ایجاد/ویرایش سفارش، محصولات، گزارش مالی، فاکتور) از داخل پیشخوان + پرتال مستقل و مینیمال ورود حسابدار بدون دسترسی به پیشخوان.
 * Version:     1.11.3
 * Plugin URI:  https://webakery.ir
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Tested up to: 6.6
 * WC requires at least: 6.0
 * Author:      WEBAKERY.IR
 * Author URI:  https://webakery.ir
 * Text Domain: wap
 * License:     GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

defined( 'ABSPATH' ) || exit;

if ( defined( 'HESABDAR_LOADED' ) ) {
	return;
}
define( 'HESABDAR_LOADED', true );

define( 'WAP_VERSION', '1.11.3' );
define( 'WAP_PATH', plugin_dir_path( __FILE__ ) );
define( 'WAP_URL', plugin_dir_url( __FILE__ ) );

if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
	add_action( 'admin_notices', function() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		echo '<div class="notice notice-error"><p><strong>Hesabdar:</strong> '
			. 'این افزونه به PHP 7.4 یا بالاتر نیاز دارد. نسخهٔ فعلی سرور: '
			. esc_html( PHP_VERSION ) . '</p></div>';
	} );
	return;
}

/**
 * بارگذاری امن فایل‌های includes.
 */
if ( ! function_exists( 'hesabdar_require_include' ) ) {
function hesabdar_require_include( $file ) {
	$path = WAP_PATH . 'includes/' . ltrim( $file, '/' );
	if ( ! is_readable( $path ) ) {
		return false;
	}
	require_once $path;
	return true;
}
}

if ( ! function_exists( 'hesabdar_admin_error' ) ) {
function hesabdar_admin_error( $message ) {
	add_action( 'admin_notices', function() use ( $message ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		echo '<div class="notice notice-error"><p><strong>Hesabdar:</strong> ' . wp_kses_post( $message ) . '</p></div>';
	} );
}
}

if ( ! function_exists( 'hesabdar_register_baget_fields_stub' ) ) {
function hesabdar_register_baget_fields_stub() {
	hesabdar_require_include( 'class-wap-baget-fields-stub.php' );
}
}

if ( ! function_exists( 'hesabdar_bootstrap_core' ) ) {
function hesabdar_bootstrap_core() {
	$required = array(
		'class-wb-license.php',
		'class-wap-jalali.php',
		'class-wap-data.php',
		'class-wap-formula.php',
		'class-wap-export.php',
		'class-wap-google-sheets.php',
		'class-wap-portal.php',
		'class-wap-admin.php',
	);
	foreach ( $required as $file ) {
		if ( ! hesabdar_require_include( $file ) ) {
			hesabdar_admin_error(
				'فایل ضروری افزونه یافت نشد (<code>' . esc_html( $file ) . '</code>). '
				. 'پوشه hesabdar را حذف کنید و ZIP کامل v' . WAP_VERSION . ' را دوباره آپلود کنید.'
			);
			return false;
		}
	}
	if ( ! hesabdar_require_include( 'class-wap-baget-fields.php' ) ) {
		hesabdar_register_baget_fields_stub();
		hesabdar_admin_error(
			'فایل <code>class-wap-baget-fields.php</code> یافت نشد — افزونه بدون ستون‌های Baget اجرا می‌شود. ZIP کامل را دوباره آپلود کنید.'
		);
	}
	return true;
}
}

/**
 * آیا افزونهٔ قدیمی WCI (WooCommerce Customer Info) هنوز فعال است؟
 */
if ( ! function_exists( 'hesabdar_legacy_wci_conflict' ) ) {
function hesabdar_legacy_wci_conflict() {
	if ( defined( 'HESABDAR_WCI_INTERNAL' ) ) {
		return false;
	}
	if ( function_exists( 'wci_orders_page' ) ) {
		return true;
	}
	if ( ! function_exists( 'is_plugin_active' ) ) {
		if ( ! function_exists( 'get_plugins' ) && is_readable( ABSPATH . 'wp-admin/includes/plugin.php' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
	}
	if ( ! function_exists( 'is_plugin_active' ) ) {
		return false;
	}
	$legacy_slugs = array(
		'woocommerce-customer-info/woocommerce-customer-info.php',
		'woocommerce-customer-info-pro/woocommerce-customer-info-pro.php',
		'wci-pro/wci-pro.php',
		'woo-customer-info/woo-customer-info.php',
		'customer-info/customer-info.php',
		'wci/wci.php',
	);
	foreach ( $legacy_slugs as $slug ) {
		if ( is_plugin_active( $slug ) ) {
			return true;
		}
	}
	return false;
}
}

$core_ok = hesabdar_bootstrap_core();

if ( ! function_exists( 'hesabdar_should_defer_wci_load' ) ) {
function hesabdar_should_defer_wci_load() {
	if ( get_transient( 'hesabdar_defer_wci' ) ) {
		return true;
	}
	if ( defined( 'WP_SANDBOX_SCRAPING' ) && WP_SANDBOX_SCRAPING ) {
		return true;
	}
	if ( is_admin() && isset( $_GET['action'] ) ) {
		$action = sanitize_key( wp_unslash( $_GET['action'] ) );
		if ( in_array( $action, array( 'upload-plugin', 'install-plugin', 'activate', 'activate-selected' ), true ) ) {
			return true;
		}
	}
	return (bool) doing_action( 'activate_plugin' );
}
}

if ( ! function_exists( 'hesabdar_load_wci_module' ) ) {
function hesabdar_load_wci_module() {
	static $loaded = false;
	if ( $loaded || empty( $GLOBALS['hesabdar_core_ok'] ) ) {
		return;
	}
	if ( hesabdar_should_defer_wci_load() ) {
		return;
	}
	try {
		if ( hesabdar_legacy_wci_conflict() ) {
			return;
		}
		if ( ! defined( 'HESABDAR_WCI_INTERNAL' ) ) {
			define( 'HESABDAR_WCI_INTERNAL', true );
		}
		$wci_files = array(
			'class-wci-exporter.php',
			'class-wci-invoice.php',
			'class-wci-tracking.php',
			'class-wap-order-service.php',
			'class-wci-admin-pages.php',
			'class-wci-order-edit.php',
		);
		foreach ( $wci_files as $file ) {
			if ( ! hesabdar_require_include( $file ) ) {
				hesabdar_admin_error( 'بخش WCI بارگذاری نشد (<code>' . esc_html( $file ) . '</code>).' );
				return;
			}
		}
		if ( class_exists( 'WCI_Tracking' ) ) {
			WCI_Tracking::init();
		}
		hesabdar_register_wci_hooks();
		$loaded = true;
		delete_transient( 'hesabdar_defer_wci' );
	} catch ( Throwable $e ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			hesabdar_admin_error( 'خطا در بارگذاری WCI: ' . esc_html( $e->getMessage() ) );
		}
	}
}
}

if ( ! function_exists( 'hesabdar_schedule_wci_module' ) ) {
function hesabdar_schedule_wci_module() {
	if ( empty( $GLOBALS['hesabdar_core_ok'] ) ) {
		return;
	}
	// باید قبل از admin_menu لود شود (admin_init دیر است و منوها ثبت نمی‌شوند)
	add_action( 'init', 'hesabdar_load_wci_module', 5 );
}
}
add_action( 'plugins_loaded', 'hesabdar_schedule_wci_module', 20 );

$GLOBALS['hesabdar_core_ok'] = $core_ok;
if ( ! $core_ok ) {
	return;
}

/* ═══════════════════════════════════════════════════════════════
   سیستم لایسنس و اشتراک — همون کلاینت WB_License که در Barbari/BAGET/
   Sokhte Jet استفاده می‌شود، با شناسه‌ی محصول 'hesabdar'
   ═══════════════════════════════════════════════════════════════ */
if ( class_exists( 'WB_License' ) && method_exists( 'WB_License', 'init' ) ) {
	WB_License::init( [
	    'product'       => 'hesabdar',
	    'name'          => 'Hesabdar',
	    'price'         => '۴۹۹,۰۰۰ تومان',
	    'file'          => __FILE__,
	    'trial_days'    => 3,
	    'server'        => 'https://webakery.ir/license-server',
	    'register_menu' => true,
	    'features'      => [
	        'پرتال مستقل حسابدار بدون دسترسی به پیشخوان',
	        'ایجاد و ویرایش سفارش بدون ورود به ووکامرس',
	        'گزارش فروش، سفارش‌ها و محصولات ووکامرس',
	        'خروجی CSV/XML/PDF/JPEG',
	    ],
	] );
}

if ( ! function_exists( 'wap_is_active' ) ) {
	function wap_is_active() {
		if ( ! class_exists( 'WB_License' ) ) {
			return true;
		}
		if ( method_exists( 'WB_License', 'is_active' ) ) {
			return (bool) WB_License::is_active( 'hesabdar' );
		}
		if ( method_exists( 'WB_License', 'is_valid' ) && WB_License::is_valid( 'hesabdar' ) ) {
			return true;
		}
		if ( method_exists( 'WB_License', 'trial_active' ) ) {
			return (bool) WB_License::trial_active( 'hesabdar' );
		}
		return true;
	}
}

/** دسترسی به منوی مشتریان/سفارش‌ها — مدیر سایت یا مدیر فروشگاه */
if ( ! function_exists( 'hesabdar_menu_cap' ) ) {
	function hesabdar_menu_cap() {
		if ( class_exists( 'WooCommerce' ) ) {
			return 'manage_woocommerce';
		}
		return 'manage_options';
	}
}

if ( ! function_exists( 'hesabdar_user_can_wci' ) ) {
	function hesabdar_user_can_wci() {
		return current_user_can( 'manage_options' ) || current_user_can( 'manage_woocommerce' );
	}
}

if ( ! function_exists( 'hesabdar_wci_missing_page' ) ) {
	function hesabdar_wci_missing_page() {
		echo '<div class="wrap"><div class="notice notice-error"><p><strong>Hesabdar:</strong> '
			. 'بخش سفارش‌ها بارگذاری نشده است. افزونه را غیرفعال کنید، پوشه <code>hesabdar</code> را حذف کنید و ZIP کامل را دوباره نصب کنید.</p></div></div>';
	}
}

if ( ! function_exists( 'hesabdar_wci_page_cb' ) ) {
	function hesabdar_wci_page_cb( $callback ) {
		return ( is_string( $callback ) && function_exists( $callback ) ) ? $callback : 'hesabdar_wci_missing_page';
	}
}

// ─── فعال‌سازی/غیرفعال‌سازی ────────────────────────────────────────────────────
register_activation_hook( __FILE__, function() {
    set_transient( 'hesabdar_defer_wci', 1, 5 * MINUTE_IN_SECONDS );

    if ( class_exists( 'WAP_Portal' ) && ! get_role( WAP_Portal::ROLE ) ) {
        add_role( WAP_Portal::ROLE, 'حسابدار', array(
            'read'             => true,
            WAP_Portal::CAP    => true,
        ) );
    }

    if ( function_exists( 'wap_register_rewrite' ) ) {
        wap_register_rewrite();
    }

    // flush_rewrite_rules() گاهی هنگام activate باعث timeout/fatal می‌شود — درخواست بعد انجام می‌شود
    delete_option( 'rewrite_rules' );

    if ( ! get_option( 'wci_invoice_settings' ) ) {
        add_option( 'wci_invoice_settings', array(
            'company_name'    => get_bloginfo( 'name' ) ?: 'WEBAKERY.IR',
            'company_address' => '',
            'company_phone'   => '',
            'footer_text'     => 'با تشکر از خرید شما',
            'logo_url'        => '',
        ) );
    }
} );

register_deactivation_hook( __FILE__, function() {
    flush_rewrite_rules();
} );

// ─── هشدار نبودِ ووکامرس (بخش مدیریت مشتریان/سفارش‌ها به آن نیاز دارد) ────────
add_action( 'admin_notices', function() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	if ( hesabdar_legacy_wci_conflict() ) {
		echo '<div class="notice notice-error"><p><strong>Hesabdar:</strong> '
			. 'افزونهٔ قدیمی «WooCommerce Customer Info» هنوز فعال است. '
			. 'Hesabdar همان امکانات را داخل خودش دارد — ابتدا افزونهٔ قدیمی WCI را غیرفعال و حذف کنید، سپس Hesabdar را دوباره فعال کنید.</p></div>';
		return;
	}
	if ( class_exists( 'WooCommerce' ) ) {
		return;
	}
	echo '<div class="notice notice-warning"><p><strong>Hesabdar:</strong> برای استفاده از بخش مدیریت مشتریان و سفارش‌ها، ووکامرس باید نصب و فعال باشد.</p></div>';
} );

if ( ! function_exists( 'hesabdar_register_wci_hooks' ) ) {
function hesabdar_register_wci_hooks() {
	static $registered = false;
	if ( $registered || ! function_exists( 'wci_orders_page' ) ) {
		return;
	}
	$registered = true;
	$cap = hesabdar_menu_cap();
	add_action( 'admin_menu', function() use ( $cap ) {
		if ( ! class_exists( 'WooCommerce' ) || ! wap_is_active() ) {
			return;
		}
		add_menu_page( 'سفارش‌ها', 'سفارش‌ها', $cap, 'wci-orders', hesabdar_wci_page_cb( 'wci_orders_page' ), 'dashicons-clipboard', 56 );
		add_submenu_page( 'wci-orders', 'سفارش‌ها', 'لیست سفارش‌ها', $cap, 'wci-orders', hesabdar_wci_page_cb( 'wci_orders_page' ) );
		add_submenu_page( 'wci-orders', 'ویرایش سفارش', '—', $cap, 'wci-order-edit', hesabdar_wci_page_cb( 'wci_order_edit_page' ) );
		add_submenu_page( 'wci-orders', 'فروش محصولات', 'فروش محصولات', $cap, 'wci-products', hesabdar_wci_page_cb( 'wci_products_page' ) );
		add_submenu_page( 'wci-orders', 'گزارش مالی', 'گزارش مالی', $cap, 'wci-reports', hesabdar_wci_page_cb( 'wci_reports_page' ) );
		add_submenu_page( 'wci-orders', 'تنظیمات فاکتور', 'تنظیمات فاکتور', $cap, 'wci-settings', hesabdar_wci_page_cb( 'wci_settings_page' ) );
	} );

	if ( class_exists( 'WAP_Order_Service' ) && method_exists( 'WAP_Order_Service', 'init_ajax' ) ) {
		WAP_Order_Service::init_ajax();
	}

	add_action( 'admin_enqueue_scripts', function( $hook ) {
		if ( strpos( $hook, 'wci' ) === false ) {
			return;
		}
		wp_enqueue_script( 'jquery' );
		wp_enqueue_style( 'wci-admin', WAP_URL . 'assets/wci-admin.css', array(), WAP_VERSION );
		wp_enqueue_script( 'wci-jalali-calendar', WAP_URL . 'assets/jalali-calendar.js', array(), WAP_VERSION, true );

		if ( strpos( $hook, 'wci-order-edit' ) !== false ) {
			wp_enqueue_script(
				'wci-order-edit',
				WAP_URL . 'assets/wci-order-edit.js',
				array( 'jquery' ),
				WAP_VERSION,
				true
			);
			wp_localize_script( 'wci-order-edit', 'wciOrderEdit', array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'wci_order_edit' ),
			) );
		}
	} );

	add_action( 'admin_post_wci_export_csv', function() {
		if ( ! hesabdar_user_can_wci() || ! wap_is_active() ) {
			wp_die( 'Unauthorized' );
		}
		$result   = wci_get_filtered_orders( true );
		$exporter = new WCI_Exporter( $result[0] );
		$exporter->export_csv();
	} );

	add_action( 'admin_post_wci_export_products_csv', function() {
		if ( ! hesabdar_user_can_wci() || ! wap_is_active() ) {
			wp_die( 'Unauthorized' );
		}
		wci_export_products_csv();
	} );

	add_action( 'admin_post_wci_export_pdf', function() {
		if ( ! hesabdar_user_can_wci() || ! wap_is_active() ) {
			wp_die( 'Unauthorized' );
		}
		$result   = wci_get_filtered_orders( true );
		$exporter = new WCI_Exporter( $result[0] );
		$exporter->export_pdf();
	} );

	add_action( 'admin_post_wci_export_report_csv', function() {
		if ( ! hesabdar_user_can_wci() || ! wap_is_active() ) {
			wp_die( 'Unauthorized' );
		}
		check_admin_referer( 'wci_reports_export' );
		wci_export_report_csv();
	} );

	add_action( 'admin_init', function() {
		if ( ! isset( $_GET['page'] ) || strpos( $_GET['page'], 'wci' ) === false ) {
			return;
		}
		if ( ! hesabdar_user_can_wci() || ! wap_is_active() ) {
			return;
		}
		if ( isset( $_GET['wci_invoice'] ) && isset( $_GET['order_id'] ) ) {
			$invoice = new WCI_Invoice( absint( $_GET['order_id'] ) );
			if ( ! empty( $_GET['wci_invoice_download'] ) ) {
				$invoice->download();
			} else {
				$invoice->render();
			}
			exit;
		}
	} );
}
}

// ─── مسیر اختصاصی /accountant-panel/ ──────────────────────────────────────────
if ( ! function_exists( 'wap_register_rewrite' ) ) {
function wap_register_rewrite() {
    add_rewrite_rule( '^accountant-panel/?$', 'index.php?wap_panel=accountant', 'top' );
    add_rewrite_rule( '^manager-panel/?$', 'index.php?wap_panel=manager', 'top' );
}
}
add_action( 'init', 'wap_register_rewrite' );

add_filter( 'query_vars', function( $vars ) {
    $vars[] = 'wap_panel';
    return $vars;
} );

add_action( 'template_redirect', function() {
    $panel = get_query_var( 'wap_panel' );
    if ( ! $panel ) {
        return;
    }
    if ( ! class_exists( 'WAP_Portal' ) ) {
        status_header( 503 );
        wp_die(
            'افزونه Hesabdar به‌درستی بارگذاری نشده. پوشه hesabdar را حذف و ZIP کامل v' . WAP_VERSION . ' را دوباره آپلود کنید.',
            'Hesabdar',
            array( 'response' => 503 )
        );
    }
    if ( $panel === '1' ) {
        $panel = WAP_Portal::PANEL_ACCOUNTANT;
    }
    if ( ! in_array( $panel, array( WAP_Portal::PANEL_ACCOUNTANT, WAP_Portal::PANEL_MANAGER ), true ) ) {
        return;
    }
    try {
        WAP_Portal::render( $panel );
    } catch ( Throwable $e ) {
        $detail = $e->getMessage() . ' @ ' . basename( $e->getFile() ) . ':' . $e->getLine();
        error_log( 'Hesabdar portal error: ' . $detail );
        set_transient( 'hesabdar_last_portal_error', $detail, HOUR_IN_SECONDS );
        // پیام واقعی نمایش داده می‌شود تا علت مشخص باشد (پرتال خصوصی است)
        wp_die(
            '<p><strong>خطای پرتال Hesabdar</strong></p>'
            . '<p dir="ltr" style="text-align:left;font-family:monospace;font-size:13px;background:#f6f7f7;padding:12px;border-radius:6px">'
            . esc_html( $detail )
            . '</p>'
            . '<p>این متن را برای پشتیبانی بفرستید. نسخه: ' . esc_html( WAP_VERSION ) . '</p>',
            'Hesabdar',
            array( 'response' => 500 )
        );
    }
    exit;
}, 1 );

// ─── خروجی گزارش (CSV/XML/PDF) — از طریق admin-post.php ───────────────────────
add_action( 'admin_post_wap_export', array( 'WAP_Portal', 'handle_export_admin_post' ) );
add_action( 'admin_post_wap_invoice', array( 'WAP_Portal', 'handle_invoice_admin_post' ) );

// Google Sheets — داده برای ساخت شیت در مرورگر (ورود با گوگل)
add_action( 'admin_post_wap_sheets_csv', array( 'WAP_Google_Sheets', 'handle_temp_csv_download' ) );
add_action( 'admin_post_nopriv_wap_sheets_csv', array( 'WAP_Google_Sheets', 'handle_temp_csv_download' ) );
add_action( 'wp_ajax_wap_export_google_sheets', array( 'WAP_Google_Sheets', 'handle_ajax_export' ) );
add_action( 'wp_ajax_wap_save_google_client_id', array( 'WAP_Google_Sheets', 'handle_ajax_save_client_id' ) );

// ─── ورود / خروج ──────────────────────────────────────────────────────────────
add_action( 'admin_post_nopriv_wap_login', 'wap_handle_login' );
add_action( 'admin_post_wap_login', 'wap_handle_login' );
if ( ! function_exists( 'wap_handle_login' ) ) {
function wap_handle_login() {
    if ( ! isset( $_POST['wap_login_nonce'] ) || ! wp_verify_nonce( $_POST['wap_login_nonce'], 'wap_login_action' ) ) {
        wp_die( 'درخواست نامعتبر است.' );
    }
    $creds = array(
        'user_login'    => sanitize_text_field( $_POST['wap_user'] ?? '' ),
        'user_password' => $_POST['wap_pass'] ?? '',
        'remember'      => true,
    );
    $user = wp_signon( $creds, is_ssl() );

    $panel_type = sanitize_key( $_POST['wap_panel'] ?? WAP_Portal::PANEL_ACCOUNTANT );
    if ( ! in_array( $panel_type, array( WAP_Portal::PANEL_ACCOUNTANT, WAP_Portal::PANEL_MANAGER ), true ) ) {
        $panel_type = WAP_Portal::PANEL_ACCOUNTANT;
    }
    $panel_url = WAP_Portal::panel_url( $panel_type );

    if ( is_wp_error( $user ) || ! WAP_Portal::user_has_access( $user ) ) {
        if ( ! is_wp_error( $user ) ) { wp_logout(); }
        wp_safe_redirect( add_query_arg( 'wap_error', '1', $panel_url ) );
        exit;
    }

    if ( $panel_type === WAP_Portal::PANEL_MANAGER && ! WAP_Portal::user_has_manager_access( $user ) ) {
        wp_logout();
        wp_safe_redirect( add_query_arg( 'wap_error', '1', WAP_Portal::panel_url( WAP_Portal::PANEL_ACCOUNTANT ) ) );
        exit;
    }

    wp_safe_redirect( $panel_url );
    exit;
}
}

add_action( 'admin_post_wap_logout', function() {
    if ( ! isset( $_POST['wap_logout_nonce'] ) || ! wp_verify_nonce( $_POST['wap_logout_nonce'], 'wap_logout_action' ) ) {
        wp_die( 'درخواست نامعتبر است.' );
    }
    wp_logout();
    $panel_type = sanitize_key( $_POST['wap_panel'] ?? WAP_Portal::PANEL_ACCOUNTANT );
    if ( ! in_array( $panel_type, array( WAP_Portal::PANEL_ACCOUNTANT, WAP_Portal::PANEL_MANAGER ), true ) ) {
        $panel_type = WAP_Portal::PANEL_ACCOUNTANT;
    }
    wp_safe_redirect( WAP_Portal::panel_url( $panel_type ) );
    exit;
} );

// ─── مسدود کردن دسترسی حسابدار به پیشخوان وردپرس ──────────────────────────────
add_action( 'admin_init', function() {
    if ( wp_doing_ajax() ) return;
    // admin-post.php میزبان اکشن‌های ورود/خروج پرتال است — نباید مسدود شود
    if ( in_array( $GLOBALS['pagenow'] ?? '', array( 'admin-post.php', 'admin-ajax.php' ), true ) ) return;
    $user = wp_get_current_user();
    if ( ! $user || ! $user->exists() ) return;
    $roles = (array) $user->roles;
    if ( in_array( WAP_Portal::ROLE, $roles, true ) && ! in_array( 'administrator', $roles, true ) ) {
        wp_safe_redirect( WAP_Portal::panel_url() );
        exit;
    }
} );

add_filter( 'show_admin_bar', function( $show ) {
    $user = wp_get_current_user();
    if ( $user && in_array( WAP_Portal::ROLE, (array) $user->roles, true ) && ! in_array( 'administrator', (array) $user->roles, true ) ) {
        return false;
    }
    return $show;
} );

// اگر حسابدار از فرم استاندارد ورود وردپرس وارد شود، به‌جای پیشخوان به پرتال هدایت شود
add_filter( 'login_redirect', function( $redirect_to, $requested_redirect_to, $user ) {
    if ( ! $user instanceof WP_User ) {
        return $redirect_to;
    }
    $roles = (array) $user->roles;
    if ( in_array( WAP_Portal::ROLE, $roles, true ) && ! in_array( 'administrator', $roles, true ) && ! WAP_Portal::user_has_manager_access( $user ) ) {
        return WAP_Portal::panel_url( WAP_Portal::PANEL_ACCOUNTANT );
    }
    return $redirect_to;
}, 10, 3 );

WAP_Admin::init();
