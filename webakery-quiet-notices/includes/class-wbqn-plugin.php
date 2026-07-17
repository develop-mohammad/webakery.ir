<?php
defined( 'ABSPATH' ) || exit;

/**
 * هسته افزونه سکوت نوتیف — سبک و بدون فشار روی سرور.
 */
class WBQN_Plugin {

	const OPTION = 'wbqn_settings';

	/** @var self|null */
	private static $instance = null;

	/** @var bool */
	private $muted = false;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public static function activate() {
		if ( ! get_option( self::OPTION ) ) {
			add_option( self::OPTION, self::defaults(), '', false );
		}
	}

	public static function defaults() {
		return array(
			'enabled'          => 1,
			'mode'             => 'all',
			'hide_for'         => 'all_caps',
			'keep_errors'      => 1,
			'keep_on_own_page' => 1,
			'hide_update_nags' => 1,
			'hide_wc_nags'     => 1,
			'css_fallback'     => 1,
		);
	}

	public static function settings() {
		return wp_parse_args( (array) get_option( self::OPTION, array() ), self::defaults() );
	}

	private function __construct() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_init', array( $this, 'maybe_mute' ), 0 );
		add_action( 'admin_head', array( $this, 'css_fallback' ), 99 );
		add_filter( 'plugin_action_links_' . plugin_basename( WBQN_FILE ), array( $this, 'action_links' ) );
		add_filter( 'plugin_row_meta', array( $this, 'row_meta' ), 10, 2 );
	}

	public function menu() {
		add_options_page(
			'سکوت نوتیف',
			'سکوت نوتیف',
			'manage_options',
			'webakery-quiet-notices',
			array( $this, 'render_page' )
		);
	}

	public function register_settings() {
		register_setting(
			'wbqn_group',
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => self::defaults(),
			)
		);
	}

	public function sanitize( $input ) {
		$d  = self::defaults();
		$in = is_array( $input ) ? $input : array();

		$mode = isset( $in['mode'] ) ? sanitize_key( $in['mode'] ) : $d['mode'];
		if ( ! in_array( $mode, array( 'all', 'plugins', 'non_core' ), true ) ) {
			$mode = $d['mode'];
		}

		$hide_for = isset( $in['hide_for'] ) ? sanitize_key( $in['hide_for'] ) : $d['hide_for'];
		if ( ! in_array( $hide_for, array( 'all_caps', 'only_editors', 'everyone' ), true ) ) {
			$hide_for = $d['hide_for'];
		}

		return array(
			'enabled'          => empty( $in['enabled'] ) ? 0 : 1,
			'mode'             => $mode,
			'hide_for'         => $hide_for,
			'keep_errors'      => empty( $in['keep_errors'] ) ? 0 : 1,
			'keep_on_own_page' => empty( $in['keep_on_own_page'] ) ? 0 : 1,
			'hide_update_nags' => empty( $in['hide_update_nags'] ) ? 0 : 1,
			'hide_wc_nags'     => empty( $in['hide_wc_nags'] ) ? 0 : 1,
			'css_fallback'     => empty( $in['css_fallback'] ) ? 0 : 1,
		);
	}

	private function should_mute() {
		if ( ! is_admin() ) {
			return false;
		}

		$s = self::settings();
		if ( empty( $s['enabled'] ) ) {
			return false;
		}

		if ( ! empty( $_GET['wbqn_show'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return false;
		}

		if ( ! empty( $s['keep_on_own_page'] ) ) {
			$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( 'webakery-quiet-notices' === $page ) {
				return false;
			}
		}

		switch ( $s['hide_for'] ) {
			case 'everyone':
				return true;
			case 'only_editors':
				return current_user_can( 'edit_posts' ) && ! current_user_can( 'manage_options' );
			case 'all_caps':
			default:
				return current_user_can( 'edit_posts' );
		}
	}

	public function maybe_mute() {
		if ( ! $this->should_mute() ) {
			return;
		}

		$this->muted = true;
		$s = self::settings();

		$this->strip_notice_hooks();
		add_action( 'admin_init', array( $this, 'strip_late' ), 999 );

		if ( ! empty( $s['hide_update_nags'] ) ) {
			remove_action( 'admin_notices', 'update_nag', 3 );
			remove_action( 'admin_notices', 'maintenance_nag', 10 );
		}

		if ( ! empty( $s['hide_wc_nags'] ) ) {
			add_filter( 'woocommerce_helper_suppress_admin_notices', '__return_true' );
			add_filter( 'woocommerce_show_admin_notice', '__return_false' );
			add_filter( 'woocommerce_allow_marketplace_suggestions', '__return_false' );
		}
	}

	private function strip_notice_hooks() {
		global $wp_filter;
		$hooks = array( 'admin_notices', 'all_admin_notices', 'user_admin_notices', 'network_admin_notices' );
		$s     = self::settings();

		foreach ( $hooks as $hook ) {
			if ( empty( $wp_filter[ $hook ] ) || ! ( $wp_filter[ $hook ] instanceof WP_Hook ) ) {
				continue;
			}

			foreach ( $wp_filter[ $hook ]->callbacks as $priority => $callbacks ) {
				foreach ( $callbacks as $id => $cb ) {
					if ( empty( $cb['function'] ) ) {
						continue;
					}
					if ( $this->is_own_callback( $cb['function'] ) ) {
						continue;
					}
					if ( 'all' !== $s['mode'] && $this->is_core_callback( $cb['function'] ) ) {
						continue;
					}
					unset( $wp_filter[ $hook ]->callbacks[ $priority ][ $id ] );
				}
			}
		}
	}

	public function strip_late() {
		if ( $this->muted ) {
			$this->strip_notice_hooks();
		}
	}

	private function is_own_callback( $fn ) {
		if ( is_array( $fn ) && isset( $fn[0] ) && $fn[0] instanceof self ) {
			return true;
		}
		return false;
	}

	private function is_core_callback( $fn ) {
		if ( is_string( $fn ) ) {
			$core = array( 'update_nag', 'maintenance_nag', 'site_admin_notice' );
			return in_array( $fn, $core, true );
		}
		if ( is_array( $fn ) && is_object( $fn[0] ) ) {
			$class = get_class( $fn[0] );
			return 0 === strpos( $class, 'WP_' );
		}
		if ( is_array( $fn ) && is_string( $fn[0] ) ) {
			return 0 === strpos( $fn[0], 'WP_' );
		}
		return false;
	}

	public function css_fallback() {
		if ( ! $this->muted ) {
			return;
		}
		$s = self::settings();
		if ( empty( $s['css_fallback'] ) ) {
			return;
		}

		$keep_errors = ! empty( $s['keep_errors'] ) && 'all' !== $s['mode'];

		echo '<style id="wbqn-hide-notices">';
		if ( $keep_errors ) {
			echo '#wpbody-content .notice:not(.notice-error):not(.error),'
				. '#wpbody-content .updated,'
				. '#wpbody-content .update-nag,'
				. '.woocommerce-message,.woocommerce-info{display:none!important}';
		} else {
			echo '#wpbody-content .notice,#wpbody-content .updated,#wpbody-content .error,'
				. '#wpbody-content .update-nag,.woocommerce-message,.woocommerce-info,'
				. '.woocommerce-error,div.fs-notice,div.jetpack-message,'
				. '.elementor-message,.e-notice{display:none!important}';
		}
		echo '</style>';
	}

	public function action_links( $links ) {
		array_unshift(
			$links,
			'<a href="' . esc_url( admin_url( 'options-general.php?page=webakery-quiet-notices' ) ) . '">تنظیمات</a>'
		);
		return $links;
	}

	public function row_meta( $links, $file ) {
		if ( plugin_basename( WBQN_FILE ) !== $file ) {
			return $links;
		}
		$links[] = '<span>نسخه ' . esc_html( WBQN_VERSION ) . '</span>';
		$links[] = '<a href="https://webakery.ir" target="_blank" rel="noopener">سازنده: webakery.ir</a>';
		return $links;
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$s = self::settings();
		include WBQN_PATH . 'includes/views/settings.php';
	}
}
