<?php
defined( 'ABSPATH' ) || exit;

class WBSS_Plugin {

	/** @var self|null */
	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		WBSS_Install::maybe_upgrade();
		WBSS_Ajax::instance();
		WBSS_Admin::instance();

		add_filter( 'plugin_action_links_' . plugin_basename( WBSS_FILE ), array( $this, 'action_links' ) );
		add_filter( 'plugin_row_meta', array( $this, 'row_meta' ), 10, 2 );
	}

	public function action_links( $links ) {
		array_unshift(
			$links,
			'<a href="' . esc_url( admin_url( 'admin.php?page=webakery-seo-studio' ) ) . '">گزارش سئو</a>'
		);
		return $links;
	}

	public function row_meta( $links, $file ) {
		if ( plugin_basename( WBSS_FILE ) !== $file ) {
			return $links;
		}
		$links[] = '<span>نسخه ' . esc_html( WBSS_VERSION ) . '</span>';
		$links[] = '<a href="https://webakery.ir" target="_blank" rel="noopener">سازنده: webakery.ir</a>';
		return $links;
	}
}
