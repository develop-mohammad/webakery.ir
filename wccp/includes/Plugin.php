<?php
namespace WCCP;

defined( 'ABSPATH' ) || exit;

class Plugin {

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

		add_filter( 'plugin_action_links_' . plugin_basename( WCCP_FILE ), array( $this, 'action_links' ) );
		add_filter( 'plugin_row_meta', array( $this, 'row_meta' ), 10, 2 );

		Admin::instance();
		Ajax::instance();
		Checkout::instance();
		OnlineProducts::instance();
	}

	private function boot_license() {
		if ( ! class_exists( 'WB_License' ) ) {
			require_once WCCP_PATH . 'includes/class-wb-license.php';
		}
		if ( ! class_exists( 'WB_License' ) || ! method_exists( 'WB_License', 'init' ) ) {
			return;
		}
		WB_License::init(
			array(
				'product'    => WCCP_PRODUCT,
				'name'       => 'Baget | ادیت فیلدهای پرداخت',
				'price'      => '۱۹۹,۰۰۰ تومان',
				'file'       => WCCP_FILE,
				'version'    => WCCP_VERSION,
				'trial_days' => 3,
				'page'       => 'admin.php?page=wccp&tab=license',
				'features'   => array(
					'ویرایش و جابه‌جایی فیلدهای checkout',
					'فیلد رادیو، چندگزینه‌ای و dropdown',
					'محصولات آنلاین با لینک پرداخت',
					'به‌روزرسانی خودکار از webakery.ir',
				),
			)
		);
	}

	/** @param string[] $links */
	public function action_links( $links ) {
		array_unshift(
			$links,
			'<a href="' . esc_url( admin_url( 'admin.php?page=wccp' ) ) . '"><strong>تنظیمات فیلدها</strong></a>',
			'<a href="' . esc_url( admin_url( 'admin.php?page=wccp&tab=license' ) ) . '">لایسنس</a>'
		);
		return $links;
	}

	/** @param string[] $links */
	public function row_meta( $links, $file ) {
		if ( plugin_basename( WCCP_FILE ) !== $file ) {
			return $links;
		}
		$links[] = '<a href="' . esc_url( admin_url( 'admin.php?page=wccp' ) ) . '">پیشخوان Baget</a>';
		$links[] = '<a href="' . esc_url( admin_url( 'edit.php?post_type=wccp_product' ) ) . '">محصولات آنلاین</a>';
		return $links;
	}

	public static function activate() {
		if ( false === get_option( Fields::ACTIVE_OPTION, false ) ) {
			update_option( Fields::ACTIVE_OPTION, Fields::default_active(), false );
		}
		OnlineProducts::register_cpt();
		flush_rewrite_rules();
	}
}
