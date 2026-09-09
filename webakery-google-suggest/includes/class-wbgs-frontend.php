<?php
defined( 'ABSPATH' ) || exit;

/**
 * صفحهٔ جدا روی سایت (بدون قالب وردپرس) با ورود موبایل/جیمیل و سپس استخراج.
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
		add_shortcode( 'webakery_suggest', array( $this, 'shortcode' ) );
		add_shortcode( 'wbgs_suggest', array( $this, 'shortcode' ) );
		add_filter( 'wbl_login_redirect', array( __CLASS__, 'filter_login_redirect' ) );
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
		$this->mark_return_cookie();
		$this->enqueue_app();

		$licensed = WBGS_Plugin::licensed();
		$logged   = is_user_logged_in();
		include WBGS_PATH . 'templates/front-page.php';
		exit;
	}

	public function shortcode() {
		if ( ! WBGS_Plugin::licensed() && ! current_user_can( 'manage_options' ) ) {
			return '';
		}
		$this->enqueue_app();
		if ( ! is_user_logged_in() ) {
			$this->mark_return_cookie();
		}
		ob_start();
		$licensed = WBGS_Plugin::licensed();
		$logged   = is_user_logged_in();
		$public   = ! empty( WBGS_Plugin::settings()['front_public'] );
		include WBGS_PATH . 'templates/front-embed.php';
		return ob_get_clean();
	}

	public function maybe_enqueue_shortcode() {
		if ( ! is_singular() ) {
			return;
		}
		global $post;
		if ( ! ( $post instanceof WP_Post ) ) {
			return;
		}
		if ( has_shortcode( $post->post_content, 'webakery_suggest' ) || has_shortcode( $post->post_content, 'wbgs_suggest' ) ) {
			$this->enqueue_app();
		}
	}

	public function enqueue_app() {
		wp_enqueue_style( 'wbgs-admin', WBGS_URL . 'assets/css/admin.css', array(), WBGS_VERSION );
		wp_enqueue_style( 'wbgs-front', WBGS_URL . 'assets/css/front.css', array( 'wbgs-admin' ), WBGS_VERSION );
		wp_enqueue_style( 'wbgs-google', WBGS_URL . 'assets/css/google.css', array( 'wbgs-front' ), WBGS_VERSION );
		wp_enqueue_script( 'wbgs-admin', WBGS_URL . 'assets/js/admin.js', array(), WBGS_VERSION, true );
		wp_localize_script( 'wbgs-admin', 'wbgsAdmin', WBGS_Plugin::script_data( 'wbgs_front' ) );

		if ( class_exists( 'WBL_Frontend' ) ) {
			WBL_Frontend::enqueue();
		}
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

	private function mark_return_cookie() {
		if ( headers_sent() ) {
			return;
		}
		$url  = self::url();
		$path = defined( 'COOKIEPATH' ) ? COOKIEPATH : '/';
		$host = defined( 'COOKIE_DOMAIN' ) ? COOKIE_DOMAIN : '';
		setcookie( 'wbgs_return', $url, time() + 20 * MINUTE_IN_SECONDS, $path, $host, is_ssl(), true );
	}
}
