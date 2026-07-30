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

		// صفحه ورود با شورت‌کد (فقط اگر قبلاً ساخته نشده).
		$page_id = (int) WBL_Settings::get( 'login_page_id', 0 );
		if ( ! $page_id || ! get_post( $page_id ) ) {
			$existing = get_page_by_path( 'vorod' );
			if ( $existing ) {
				$page_id = (int) $existing->ID;
			} else {
				$page_id = wp_insert_post(
					array(
						'post_title'   => 'ورود / ثبت‌نام',
						'post_name'    => 'vorod',
						'post_status'  => 'publish',
						'post_type'    => 'page',
						'post_content' => '[webakery_login]',
					),
					true
				);
			}
			if ( ! is_wp_error( $page_id ) && $page_id ) {
				$s                   = WBL_Settings::all();
				$s['login_page_id']  = (int) $page_id;
				update_option( WBL_Settings::OPTION, $s, false );
			}
		}

		flush_rewrite_rules( false );
	}

	private function __construct() {
		$this->license();
		add_action( 'admin_post_wb_license_save', array( $this, 'license' ), 1 );
		add_action( 'admin_post_wb_license_deactivate', array( $this, 'license' ), 1 );

		WBL_Ajax::hooks();
		WBL_Google::hooks();
		WBL_Frontend::hooks();
		WBL_Admin::hooks();
	}

	public function license() {
		if ( ! class_exists( 'WB_License', false ) ) {
			require_once WBL_PATH . 'includes/class-wb-license.php';
		}
		if ( ! class_exists( 'WB_License', false ) ) {
			return;
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
				'page'          => 'admin.php?page=webakery-login',
				'features'      => array(
					'ورود / ثبت‌نام با شماره موبایل و کد OTP',
					'ورود با جیمیل (Google OAuth)',
					'ملی‌پیامک، کاوه‌نگار، IPPanel، قاصدک',
					'شورت‌کد [webakery_login]',
					'محدودیت نرخ و امنیت OTP',
					'به‌روزرسانی خودکار از webakery.ir',
				),
			)
		);
	}

	public static function is_usable() {
		if ( ! class_exists( 'WB_License', false ) ) {
			return true;
		}
		return WB_License::is_active( WBL_PRODUCT );
	}
}
