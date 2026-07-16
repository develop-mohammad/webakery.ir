<?php
/**
 * Main plugin bootstrap.
 *
 * @package Hesabdar
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Core plugin class.
 */
class Hesabdar {

	/**
	 * Singleton instance.
	 *
	 * @var Hesabdar|null
	 */
	private static $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @return Hesabdar
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
		add_action( 'init', array( $this, 'init' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_public_assets' ) );

		Hesabdar_CPT::init();
		Hesabdar_Meta::init();
		Hesabdar_Settings::init();
		Hesabdar_Shortcodes::init();
		Hesabdar_Orders::init();
		Hesabdar_Invoice::init();

		if ( is_admin() ) {
			Hesabdar_Admin::init();
		}
	}

	/**
	 * Load translations.
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'hesabdar', false, dirname( HESABDAR_BASENAME ) . '/languages' );
	}

	/**
	 * Runtime init.
	 */
	public function init() {
		// Reserved for future runtime hooks.
	}

	/**
	 * Front-end styles and scripts.
	 */
	public function enqueue_public_assets() {
		wp_register_style(
			'hesabdar',
			HESABDAR_URL . 'public/css/hesabdar.css',
			array(),
			HESABDAR_VERSION
		);

		wp_register_script(
			'hesabdar',
			HESABDAR_URL . 'public/js/hesabdar.js',
			array(),
			HESABDAR_VERSION,
			true
		);

		wp_localize_script(
			'hesabdar',
			'hesabdarData',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'hesabdar_order' ),
				'i18n'    => array(
					'sending'         => __( 'در حال ارسال…', 'hesabdar' ),
					'success'         => __( 'سفارش شما ثبت شد. به زودی تماس می‌گیریم.', 'hesabdar' ),
					'error'           => __( 'ارسال ناموفق بود. دوباره تلاش کنید.', 'hesabdar' ),
					'required'        => __( 'نام و شماره تماس الزامی است.', 'hesabdar' ),
					'draftSaved'      => __( 'روی این لپ‌تاپ ذخیره شد.', 'hesabdar' ),
					'draftSaveFailed' => __( 'ذخیره روی این دستگاه ممکن نشد.', 'hesabdar' ),
					'draftRestored'   => __( 'پیش‌نویس ذخیره‌شده روی این لپ‌تاپ بازیابی شد.', 'hesabdar' ),
					'draftDownloaded' => __( 'فایل پیش‌نویس روی لپ‌تاپ دانلود شد.', 'hesabdar' ),
					'draftCleared'    => __( 'پیش‌نویس این دستگاه پاک شد.', 'hesabdar' ),
					'draftEmpty'      => __( 'چیزی برای ذخیره روی این دستگاه نیست.', 'hesabdar' ),
				),
			)
		);
	}

	/**
	 * Activation callback.
	 */
	public static function activate() {
		Hesabdar_CPT::register();
		Hesabdar_Orders::register_post_type();
		flush_rewrite_rules();

		if ( false === get_option( 'hesabdar_settings' ) ) {
			update_option(
				'hesabdar_settings',
				array(
					'store_name'     => 'حسابدار',
					'phone'          => '',
					'whatsapp'       => '',
					'address'        => '',
					'currency'       => 'تومان',
					'hours_weekday'  => '۸ صبح تا ۹ شب',
					'hours_friday'   => '۹ صبح تا ۲ بعدازظهر',
					'order_email'    => get_option( 'admin_email' ),
					'invoice_prefix' => 'HSB',
					'invoice_note'   => 'از خرید شما سپاسگزاریم.',
				)
			);
		}
	}

	/**
	 * Deactivation callback.
	 */
	public static function deactivate() {
		flush_rewrite_rules();
	}
}
