<?php
defined( 'ABSPATH' ) || exit;

class DID_Plugin {

	/** @var self|null */
	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public static function activate() {
		DID_Db::install();
		DID_Db::maybe_upgrade();
		if ( ! get_option( DID_Settings::OPTION ) ) {
			add_option( DID_Settings::OPTION, DID_Settings::defaults(), '', false );
		}
		DID_Cron::ensure_schedule();
	}

	public static function deactivate() {
		DID_Cron::unschedule();
	}

	private function __construct() {
		DID_Db::maybe_upgrade();
		$this->license();
		DID_Ajax::hooks();
		DID_Admin::hooks();
		DID_Cron::register();
	}

	private function license() {
		if ( ! class_exists( 'WB_License' ) ) {
			require_once DID_PATH . 'includes/class-wb-license.php';
		}
		WB_License::init(
			array(
				'product'       => DID_PRODUCT,
				'name'          => 'دیدبانی | رصد رقبا و رتبهٔ کلیدواژه',
				'price'         => '۳۹۹,۰۰۰ تومان',
				'file'          => DID_FILE,
				'version'       => DID_VERSION,
				'trial_days'    => 7,
				'server'        => 'https://webakery.ir/license-server',
				'register_menu' => true,
				'page'          => 'admin.php?page=didbani&tab=license',
				'features'      => array(
					'کرول صفحات عمومی رقبا (robots و sitemap)',
					'رصد رتبه در بینگ با Azure API',
					'رصد رتبه در گوگل با SerpAPI / DataForSEO',
					'رصد رتبه موبایل در شهرهای ایران و نمایش رشد/افت',
					'ماتریس کیورد × دامنه × موتور جستجو',
					'به‌روزرسانی خودکار از webakery.ir',
				),
			)
		);
	}

	public static function is_usable() {
		if ( ! class_exists( 'WB_License' ) ) {
			return true;
		}
		return WB_License::is_active( DID_PRODUCT );
	}
}
