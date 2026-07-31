<?php
defined( 'ABSPATH' ) || exit;

/**
 * هسته افزونه — رایگان، بدون لایسنس.
 */
class WBGP_Plugin {

	/** @var self|null */
	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public static function activate() {
		$defaults = WBGP_Settings::defaults();
		if ( false === get_option( 'wbgp_settings', false ) ) {
			add_option( 'wbgp_settings', $defaults, '', false );
		}
	}

	private function __construct() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action( 'admin_notices', array( $this, 'need_woo' ) );
			return;
		}

		WBGP_Settings::init();
		WBGP_Fees::init();

		add_filter( 'plugin_action_links_' . plugin_basename( WBGP_FILE ), array( $this, 'action_links' ) );
	}

	public function need_woo() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		echo '<div class="notice notice-error"><p><strong>قیمت‌گذاری درگاه:</strong> برای کار کردن این افزونه باید ووکامرس فعال باشد.</p></div>';
	}

	public function action_links( $links ) {
		$url = admin_url( 'admin.php?page=wbgp-settings' );
		array_unshift( $links, '<a href="' . esc_url( $url ) . '">تنظیمات</a>' );
		$links[] = '<a href="https://webakery.ir" target="_blank" rel="noopener">webakery.ir</a>';
		return $links;
	}
}
