<?php
defined( 'ABSPATH' ) || exit;

class WBCB_Plugin {

	/** @var self|null */
	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->boot_license();
		add_action( 'admin_post_wb_license_save', array( $this, 'boot_license' ), 1 );
		add_action( 'admin_post_wb_license_deactivate', array( $this, 'boot_license' ), 1 );

		WBCB_Install::maybe_upgrade();
		WBCB_Ajax::instance();
		WBCB_Admin::instance();
		WBCB_Frontend::instance();

		add_filter( 'plugin_action_links_' . plugin_basename( WBCB_FILE ), array( $this, 'action_links' ) );
		add_filter( 'plugin_row_meta', array( $this, 'row_meta' ), 10, 2 );
	}

	/** ثبت لایسنس خرید / فعال‌سازی / آپدیت */
	public function boot_license() {
		try {
			if ( ! class_exists( 'WB_License', false ) ) {
				$file = WBCB_PATH . 'includes/class-wb-license.php';
				if ( ! is_readable( $file ) ) {
					return;
				}
				require_once $file;
			}
			if ( ! class_exists( 'WB_License', false ) || ! method_exists( 'WB_License', 'init' ) ) {
				return;
			}

			WB_License::init(
				array(
					'product'       => WBCB_PRODUCT,
					'name'          => 'چت باکس | Webakery Chat',
					'price'         => 'از ۱۵۰,۰۰۰ تومان',
					'price_sub'     => 'ماهانه / ۳ ماهه / دائمی',
					'plans'         => array(
						array(
							'id'    => '1m',
							'label' => 'ماهانه',
							'price' => '۱۵۰,۰۰۰ تومان',
						),
						array(
							'id'    => '3m',
							'label' => '۳ ماهه',
							'price' => '۳۵۰,۰۰۰ تومان',
							'badge' => 'پیشنهادی',
						),
						array(
							'id'    => 'life',
							'label' => 'دائمی',
							'price' => '۷۹۹,۰۰۰ تومان',
						),
					),
					'file'          => WBCB_FILE,
					'version'       => WBCB_VERSION,
					'trial_days'    => 3,
					'server'        => 'https://webakery.ir/license-server',
					'register_menu' => true,
					'page'          => 'admin.php?page=webakery-chat-box-license',
					'features'      => array(
						'ویجت چت RTL روی سایت',
						'صندوق پیام در پیشخوان',
						'اعلان تلگرام، واتساپ و ایمیل',
						'نمایش نام و عکس محصول ووکامرس',
						'به‌روزرسانی در دوره اشتراک / دائمی',
						'تمدید ماهانه، ۳ ماهه یا ارتقا به دائمی',
					),
				)
			);

			$opt = 'wbl_' . WBCB_PRODUCT . '_install_time';
			if ( ! get_option( $opt ) ) {
				update_option( $opt, time(), false );
			}
		} catch ( Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
		}
	}

	/** لایسنس معتبر یا دوره آزمایشی */
	public static function is_licensed() {
		if ( ! class_exists( 'WB_License', false ) ) {
			return true; // اگر فایل لایسنس نباشد، مانع استفاده نشویم
		}
		return WB_License::is_active( WBCB_PRODUCT );
	}

	public function action_links( $links ) {
		$custom = array(
			'<a href="' . esc_url( admin_url( 'admin.php?page=webakery-chat-box' ) ) . '">صندوق چت</a>',
			'<a href="' . esc_url( admin_url( 'admin.php?page=webakery-chat-box-settings' ) ) . '">تنظیمات</a>',
			'<a href="' . esc_url( admin_url( 'admin.php?page=webakery-chat-box-license' ) ) . '" style="color:#6d28d9;font-weight:700">خرید / لایسنس</a>',
		);
		return array_merge( $custom, $links );
	}

	public function row_meta( $links, $file ) {
		if ( plugin_basename( WBCB_FILE ) !== $file ) {
			return $links;
		}
		$links[] = '<a href="' . esc_url( admin_url( 'admin.php?page=webakery-chat-box-license' ) ) . '">فعال‌سازی لایسنس</a>';
		$links[] = '<a href="https://webakery.ir" target="_blank" rel="noopener">webakery.ir</a>';
		return $links;
	}
}
