<?php
defined( 'ABSPATH' ) || exit;

/**
 * یک بافر HTML واحد که هم با ob_start و هم با فیلتر WP Rocket کار می‌کند.
 */
class WBS_Buffer {

	/** @var self|null */
	private static $instance = null;

	/** @var bool */
	private $buffering = false;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'template_redirect', array( $this, 'start' ), -10000 );
		// WP Rocket مسیر اصلی خروجی کش‌شده.
		add_filter( 'rocket_buffer', array( $this, 'filter' ), 20 );
	}

	public function start() {
		if ( $this->buffering || is_admin() || is_feed() || is_preview() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}
		// اگر Rocket فعال است، عمدتاً از rocket_buffer استفاده می‌کنیم؛ ob هم به‌عنوان پشتیبان می‌ماند.
		$this->buffering = true;
		ob_start( array( $this, 'filter' ) );
	}

	/**
	 * @param string $html
	 * @return string
	 */
	public function filter( $html ) {
		if ( ! is_string( $html ) || strlen( $html ) < 50 || false === stripos( $html, '<html' ) ) {
			return $html;
		}

		// فونت‌ها اول (swap / preload cleanup / IRANSans).
		if ( class_exists( 'WBS_Fonts' ) ) {
			$html = WBS_Fonts::instance()->filter_html( $html );
		}
		// بعد AutoFix تصاویر/آیکون.
		if ( class_exists( 'WBS_AutoFix' ) ) {
			$html = WBS_AutoFix::instance()->filter_html( $html );
		}

		if ( false === stripos( $html, 'WBS_APPLIED=1' ) ) {
			$html = preg_replace( '#</head>#i', "<!-- WBS_APPLIED=1 -->\n</head>", $html, 1 );
		}

		return $html;
	}
}
