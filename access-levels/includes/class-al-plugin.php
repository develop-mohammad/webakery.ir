<?php
defined( 'ABSPATH' ) || exit;

class AL_Plugin {

	/** @var self|null */
	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public static function activate() {
		if ( ! get_option( 'al_role_rules' ) ) {
			update_option( 'al_role_rules', array(), false );
		}
	}

	private function __construct() {
		$this->boot_license();
		require_once AL_PATH . 'includes/class-al-access.php';
		require_once AL_PATH . 'includes/class-al-admin.php';

		AL_Access::register();
		if ( is_admin() ) {
			AL_Admin::instance();
		}
	}

	private function boot_license() {
		require_once AL_PATH . 'includes/class-wb-license.php';
		WB_License::init( array(
			'product'    => AL_PRODUCT,
			'name'       => 'Barbari — مدیریت دسترسی',
			'price'      => '۹۹,۹۹۹ تومان',
			'file'       => AL_FILE,
			'version'    => AL_VERSION,
			'trial_days' => 7,
			'page'       => 'admin.php?page=access-levels&tab=license',
			'demo_constant' => 'AL_DEMO',
			'buy_url'    => 'https://webakery.ir',
			'features'   => array(
				'محدودیت منوهای پیشخوان برای هر نقش',
				'مخفی کردن افزونه‌ها از کاربران خاص',
				'جدول کاربران فشرده',
				'به‌روزرسانی خودکار از webakery.ir',
			),
		) );
	}

	public static function licensed() {
		if ( defined( 'AL_DEMO' ) && AL_DEMO ) {
			return true;
		}
		return class_exists( 'WB_License' ) && WB_License::is_active( AL_PRODUCT );
	}
}
