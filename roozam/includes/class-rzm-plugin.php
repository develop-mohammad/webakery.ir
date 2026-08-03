<?php
defined( 'ABSPATH' ) || exit;

class RZM_Plugin {

	/** @var self|null */
	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public static function activate() {
		if ( ! get_option( RZM_Settings::OPTION ) ) {
			add_option( RZM_Settings::OPTION, RZM_Settings::defaults(), '', false );
		}
	}

	private function __construct() {
		$this->license();
		RZM_Ajax::hooks();
		RZM_Frontend::hooks();
		if ( is_admin() ) {
			RZM_Admin::hooks();
		}
	}

	private function license() {
		if ( ! class_exists( 'WB_License' ) ) {
			require_once RZM_PATH . 'includes/class-wb-license.php';
		}
		WB_License::init(
			array(
				'product'       => RZM_PRODUCT,
				'name'          => 'روزم | برنامه‌ریز روزانه',
				'price'         => '۱۹۹,۰۰۰ تومان',
				'file'          => RZM_FILE,
				'version'       => RZM_VERSION,
				'trial_days'    => 7,
				'server'        => 'https://webakery.ir/license-server',
				'register_menu' => true,
				'page'          => 'admin.php?page=roozam&tab=license',
				'features'      => array(
					'برنامه‌ریزی هوشمند روزانه',
					'عادت‌های تکرارشونده',
					'تقویم شمسی و رابط فارسی',
					'شورت‌کد [roozam]',
					'به‌روزرسانی خودکار از webakery.ir',
				),
			)
		);
	}

	public static function is_usable() {
		if ( ! class_exists( 'WB_License' ) ) {
			return true;
		}
		return WB_License::is_active( RZM_PRODUCT );
	}
}
