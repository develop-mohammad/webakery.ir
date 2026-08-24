<?php
namespace WCCP;

defined( 'ABSPATH' ) || exit;

/**
 * قفل اجباری اسکرول افقی روی کل فرانت سایت.
 */
class NoHScroll {

	/** @var self|null */
	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		if ( is_admin() && ! wp_doing_ajax() ) {
			return;
		}
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ), 1 );
		add_action( 'wp_head', array( $this, 'inline_critical' ), 0 );
	}

	/** CSS حیاتی خیلی زود — حتی قبل از صف قالب */
	public function inline_critical() {
		echo "<style id=\"wccp-no-hscroll-critical\">"
			. "html,body{overflow-x:hidden!important;max-width:100%!important;width:100%!important;overscroll-behavior-x:none!important}"
			. "body{position:relative!important}"
			. "</style>\n";
	}

	public function enqueue() {
		wp_enqueue_style(
			'wccp-no-hscroll',
			WCCP_URL . 'assets/no-hscroll.css',
			array(),
			WCCP_VERSION
		);
		wp_enqueue_script(
			'wccp-no-hscroll',
			WCCP_URL . 'assets/no-hscroll.js',
			array(),
			WCCP_VERSION,
			true
		);
	}

	/** CSS خام برای صفحات مستقل (مثل /pay/…) */
	public static function inline_css_string() {
		$file = WCCP_PATH . 'assets/no-hscroll.css';
		$css  = is_readable( $file ) ? (string) file_get_contents( $file ) : '';
		$crit = 'html,body{overflow-x:hidden!important;max-width:100%!important;width:100%!important;overscroll-behavior-x:none!important}body{position:relative!important}';
		return $crit . $css;
	}

	/** JS خام برای صفحات مستقل */
	public static function inline_js_string() {
		$file = WCCP_PATH . 'assets/no-hscroll.js';
		return is_readable( $file ) ? (string) file_get_contents( $file ) : '';
	}
}
