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
		WBCB_Install::maybe_upgrade();
		WBCB_Ajax::instance();
		WBCB_Admin::instance();
		WBCB_Frontend::instance();

		add_filter( 'plugin_action_links_' . plugin_basename( WBCB_FILE ), array( $this, 'action_links' ) );
	}

	public function action_links( $links ) {
		$custom = array(
			'<a href="' . esc_url( admin_url( 'admin.php?page=webakery-chat-box' ) ) . '">صندوق چت</a>',
			'<a href="' . esc_url( admin_url( 'admin.php?page=webakery-chat-box-settings' ) ) . '">تنظیمات</a>',
		);
		return array_merge( $custom, $links );
	}
}
