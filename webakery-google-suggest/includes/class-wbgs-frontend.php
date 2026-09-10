<?php
defined( 'ABSPATH' ) || exit;

/**
 * شورت‌کد عمومی روی برگه/نوشته + صفحهٔ جدا /sajest/.
 */
class WBGS_Frontend {

	/** @var self|null */
	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'init', array( $this, 'add_rewrite' ) );
		add_filter( 'query_vars', array( $this, 'query_vars' ) );
		add_action( 'template_redirect', array( $this, 'maybe_render' ), 0 );
		add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue_shortcode' ) );
		foreach ( self::shortcode_tags() as $tag ) {
			add_shortcode( $tag, array( $this, 'shortcode' ) );
		}
		add_filter( 'wbl_login_redirect', array( __CLASS__, 'filter_login_redirect' ) );
	}

	/** شورت‌کد اصلی و نام مستعار — هر دو یک خروجی دارند. */
	public static function shortcode_tags() {
		return array( 'webakery_suggest', 'wbgs_suggest' );
	}

	public static function primary_shortcode() {
		return '[' . self::shortcode_tags()[0] . ']';
	}

	/**
	 * آیا متن شامل شورت‌کد سجست‌یاب است؟ بدون وردپرس هم با رجکس کار می‌کند.
	 *
	 * @param string $content
	 */
	public static function content_has_shortcode( $content ) {
		if ( ! is_string( $content ) || $content === '' ) {
			return false;
		}
		foreach ( self::shortcode_tags() as $tag ) {
			if ( function_exists( 'has_shortcode' ) && has_shortcode( $content, $tag ) ) {
				return true;
			}
			if ( preg_match( '/\[' . preg_quote( $tag, '/' ) . '(?:\s|\])/', $content ) ) {
				return true;
			}
		}
		return false;
	}

	public static function slug() {
		$s = WBGS_Plugin::settings();
		return self::sanitize_slug( isset( $s['front_slug'] ) ? $s['front_slug'] : 'sajest' );
	}

	public static function enabled() {
		$s = WBGS_Plugin::settings();
		return ! empty( $s['front_enabled'] );
	}

	public static function sanitize_slug( $slug ) {
		$slug = is_string( $slug ) ? $slug : '';
		if ( function_exists( 'sanitize_title' ) ) {
			$slug = sanitize_title( $slug );
		} else {
			$slug = strtolower( trim( $slug ) );
			$slug = preg_replace( '/[^a-z0-9\-_]/', '-', $slug );
			$slug = trim( $slug, '-' );
		}
		$reserved = array( 'wp-admin', 'wp-login', 'admin', 'login', 'xmlrpc', 'feed' );
		if ( $slug === '' || in_array( $slug, $reserved, true ) ) {
			return 'sajest';
		}
		return $slug;
	}

	public static function url() {
		if ( ! self::enabled() ) {
			return '';
		}
		if ( get_option( 'permalink_structure' ) ) {
			return home_url( user_trailingslashit( self::slug() ) );
		}
		return add_query_arg( 'wbgs_front', '1', home_url( '/' ) );
	}

	public function add_rewrite() {
		if ( ! self::enabled() ) {
			return;
		}
		$slug = self::slug();
		add_rewrite_rule( '^' . preg_quote( $slug, '/' ) . '/?$', 'index.php?wbgs_front=1', 'top' );
	}

	public function query_vars( $vars ) {
		$vars[] = 'wbgs_front';
		return $vars;
	}

	public function maybe_render() {
		if ( 1 !== (int) get_query_var( 'wbgs_front' ) ) {
			return;
		}
		if ( ! self::enabled() ) {
			return;
		}

		status_header( 200 );
		nocache_headers();
		$this->mark_return_cookie( self::url() );
		$this->enqueue_app();

		$licensed = WBGS_Plugin::licensed();
		$logged   = is_user_logged_in();
		include WBGS_PATH . 'templates/front-page.php';
		exit;
	}

	/**
	 * شورت‌کد برگهٔ معمولی — مهمان‌ها هم می‌توانند استفاده کنند مگر «اجازه مهمان» خاموش باشد.
	 * وابسته به فعال بودن صفحهٔ جدا /sajest/ نیست.
	 *
	 * @param array|string $atts
	 */
	public function shortcode( $atts = array() ) {
		if ( ! is_array( $atts ) ) {
			$atts = array();
		}
		$atts = shortcode_atts(
			array(
				'class' => '',
			),
			$atts,
			'webakery_suggest'
		);

		$this->enqueue_app();
		$this->print_assets_if_late();

		if ( ! is_user_logged_in() ) {
			$this->mark_return_cookie();
		}

		ob_start();
		$licensed    = WBGS_Plugin::licensed();
		$logged      = is_user_logged_in();
		$public      = ! empty( WBGS_Plugin::settings()['front_public'] );
		$extra_class = function_exists( 'sanitize_html_class' ) ? sanitize_html_class( $atts['class'] ) : '';
		include WBGS_PATH . 'templates/front-embed.php';
		return ob_get_clean();
	}

	public function maybe_enqueue_shortcode() {
		if ( $this->page_needs_assets() ) {
			$this->enqueue_app();
		}
	}

	private function page_needs_assets() {
		if ( ! is_singular() ) {
			return false;
		}
		global $post;
		if ( $post instanceof WP_Post && self::content_has_shortcode( $post->post_content ) ) {
			return true;
		}
		return $this->elementor_has_shortcode();
	}

	private function elementor_has_shortcode() {
		$post_id = function_exists( 'get_queried_object_id' ) ? (int) get_queried_object_id() : 0;
		if ( ! $post_id ) {
			global $post;
			$post_id = ( $post instanceof WP_Post ) ? (int) $post->ID : 0;
		}
		if ( ! $post_id ) {
			return false;
		}
		$raw = get_post_meta( $post_id, '_elementor_data', true );
		if ( ! is_string( $raw ) || $raw === '' ) {
			return false;
		}
		foreach ( self::shortcode_tags() as $tag ) {
			if ( false !== strpos( $raw, $tag ) ) {
				return true;
			}
		}
		return false;
	}

	public function enqueue_app() {
		if ( wp_script_is( 'wbgs-admin', 'enqueued' ) ) {
			return;
		}
		wp_enqueue_style( 'wbgs-admin', WBGS_URL . 'assets/css/admin.css', array(), WBGS_VERSION );
		wp_enqueue_style( 'wbgs-front', WBGS_URL . 'assets/css/front.css', array( 'wbgs-admin' ), WBGS_VERSION );
		wp_enqueue_style( 'wbgs-google', WBGS_URL . 'assets/css/google.css', array( 'wbgs-front' ), WBGS_VERSION );
		wp_enqueue_script( 'wbgs-admin', WBGS_URL . 'assets/js/admin.js', array(), WBGS_VERSION, true );
		wp_localize_script( 'wbgs-admin', 'wbgsAdmin', WBGS_Plugin::script_data( 'wbgs_front' ) );

		if ( class_exists( 'WBL_Frontend' ) ) {
			WBL_Frontend::enqueue();
		}
	}

	/**
	 * اگر شورت‌کد بعد از wp_head رندر شود (مثلاً ویجت المنتور)، استایل را همان‌جا چاپ کن.
	 */
	private function print_assets_if_late() {
		if ( ! did_action( 'wp_print_styles' ) ) {
			return;
		}
		if ( wp_style_is( 'wbgs-google', 'done' ) ) {
			return;
		}
		wp_print_styles( array( 'wbgs-admin', 'wbgs-front', 'wbgs-google' ) );
	}

	public static function has_easy_login() {
		return class_exists( 'WBL_Frontend' ) && class_exists( 'WBL_Plugin' ) && WBL_Plugin::is_usable();
	}

	public static function filter_login_redirect( $url ) {
		if ( empty( $_COOKIE['wbgs_return'] ) ) {
			return $url;
		}
		$want = esc_url_raw( wp_unslash( $_COOKIE['wbgs_return'] ) );
		$ok   = wp_validate_redirect( $want, false );
		return $ok ? $ok : $url;
	}

	private function mark_return_cookie( $url = '' ) {
		if ( headers_sent() ) {
			return;
		}
		if ( $url === '' && function_exists( 'is_singular' ) && is_singular() && function_exists( 'get_permalink' ) ) {
			$here = get_permalink();
			if ( is_string( $here ) && $here !== '' ) {
				$url = $here;
			}
		}
		if ( $url === '' ) {
			$url = self::url();
		}
		if ( $url === '' ) {
			return;
		}
		$path = defined( 'COOKIEPATH' ) ? COOKIEPATH : '/';
		$host = defined( 'COOKIE_DOMAIN' ) ? COOKIE_DOMAIN : '';
		setcookie( 'wbgs_return', $url, time() + 20 * MINUTE_IN_SECONDS, $path, $host, is_ssl(), true );
	}
}
