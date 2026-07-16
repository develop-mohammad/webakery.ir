<?php
/**
 * Main plugin bootstrap.
 *
 * @package Webakery
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Core plugin class.
 */
class Webakery {

	/**
	 * Singleton instance.
	 *
	 * @var Webakery|null
	 */
	private static $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @return Webakery
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

		Webakery_CPT::init();
		Webakery_Meta::init();
		Webakery_Settings::init();
		Webakery_Shortcodes::init();
		Webakery_Orders::init();

		if ( is_admin() ) {
			Webakery_Admin::init();
		}
	}

	/**
	 * Load translations.
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'webakery', false, dirname( WEBAKERY_BASENAME ) . '/languages' );
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
			'webakery',
			WEBAKERY_URL . 'public/css/webakery.css',
			array(),
			WEBAKERY_VERSION
		);

		wp_register_script(
			'webakery',
			WEBAKERY_URL . 'public/js/webakery.js',
			array(),
			WEBAKERY_VERSION,
			true
		);

		wp_localize_script(
			'webakery',
			'webakeryData',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'webakery_order' ),
				'i18n'    => array(
					'sending'         => __( 'در حال ارسال…', 'webakery' ),
					'success'         => __( 'سفارش شما ثبت شد. به زودی تماس می‌گیریم.', 'webakery' ),
					'error'           => __( 'ارسال ناموفق بود. دوباره تلاش کنید.', 'webakery' ),
					'required'        => __( 'نام و شماره تماس الزامی است.', 'webakery' ),
					'draftSaved'      => __( 'روی این لپ‌تاپ ذخیره شد.', 'webakery' ),
					'draftSaveFailed' => __( 'ذخیره روی این دستگاه ممکن نشد.', 'webakery' ),
					'draftRestored'   => __( 'پیش‌نویس ذخیره‌شده روی این لپ‌تاپ بازیابی شد.', 'webakery' ),
					'draftDownloaded' => __( 'فایل پیش‌نویس روی لپ‌تاپ دانلود شد.', 'webakery' ),
					'draftCleared'    => __( 'پیش‌نویس این دستگاه پاک شد.', 'webakery' ),
					'draftEmpty'      => __( 'چیزی برای ذخیره روی این دستگاه نیست.', 'webakery' ),
				),
			)
		);
	}

	/**
	 * Activation callback.
	 */
	public static function activate() {
		Webakery_CPT::register();
		Webakery_Orders::register_post_type();
		flush_rewrite_rules();

		if ( false === get_option( 'webakery_settings' ) ) {
			update_option(
				'webakery_settings',
				array(
					'store_name'    => 'Webakery',
					'phone'         => '',
					'whatsapp'      => '',
					'address'       => '',
					'currency'      => 'تومان',
					'hours_weekday' => '۸ صبح تا ۹ شب',
					'hours_friday'  => '۹ صبح تا ۲ بعدازظهر',
					'order_email'   => get_option( 'admin_email' ),
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
