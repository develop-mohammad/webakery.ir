<?php
defined( 'ABSPATH' ) || exit;

class WBL_Plugin {

	/** @var self|null */
	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public static function activate() {
		if ( ! get_option( WBL_Settings::OPTION ) ) {
			add_option( WBL_Settings::OPTION, WBL_Settings::defaults(), '', false );
		}
	}

	private function __construct() {
		$this->license();
		WBL_Ajax::hooks();
		WBL_Google::hooks();
		WBL_Frontend::hooks();
		WBL_Admin::hooks();
		if ( did_action( 'elementor/loaded' ) ) {
			$this->load_elementor();
		} else {
			add_action( 'elementor/loaded', array( $this, 'load_elementor' ) );
		}
	}

	public function load_elementor() {
		require_once WBL_PATH . 'includes/class-wbl-elementor.php';
		WBL_Elementor::hooks();
	}

	private function license() {
		if ( ! class_exists( 'WB_License' ) ) {
			require_once WBL_PATH . 'includes/class-wb-license.php';
		}
		WB_License::init(
			array(
				'product'       => WBL_PRODUCT,
				'name'          => 'ورود آسان | لاگین پیامکی و جیمیل',
				'price'         => '۲۴۹,۰۰۰ تومان',
				'file'          => WBL_FILE,
				'version'       => WBL_VERSION,
				'trial_days'    => 7,
				'server'        => 'https://webakery.ir/license-server',
				'register_menu' => true,
				'page'          => 'admin.php?page=webakery-login&tab=license',
				'features'      => array(
					'ورود با شماره موبایل و کد OTP',
					'ورود با جیمیل (Google OAuth)',
					'ملی‌پیامک، کاوه‌نگار، IPPanel، قاصدک',
					'شورت‌کد [webakery_login]',
					'به‌روزرسانی خودکار از webakery.ir',
				),
			)
		);
	}

	public static function is_usable() {
		if ( ! class_exists( 'WB_License' ) ) {
			return true;
		}
		return WB_License::is_active( WBL_PRODUCT );
	}
}
