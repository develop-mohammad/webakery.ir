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
			'scope'            => 'all_admin',
			'mode'             => 'all',
			'hide_for'         => 'all_caps',
			'keep_errors'      => 1,
			'keep_on_own_page' => 1,
			'hide_update_nags' => 1,
			'hide_wc_nags'     => 1,
			'hide_settings_errors' => 1,
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
		add_action( 'admin_notices', array( $this, 'filter_settings_errors' ), 0 );
		add_action( 'network_admin_notices', array( $this, 'filter_settings_errors' ), 0 );
		add_action( 'user_admin_notices', array( $this, 'filter_settings_errors' ), 0 );
		add_filter( 'admin_body_class', array( $this, 'body_class' ) );
		add_action( 'admin_head', array( $this, 'css_fallback' ), 99 );
		add_filter( 'plugin_action_links_' . plugin_basename( WBQN_FILE ), array( $this, 'action_links' ) );
		add_filter( 'plugin_row_meta', array( $this, 'row_meta' ), 10, 2 );
	}

	private function is_target_screen() {
		$s = self::settings();
		if ( 'dashboard_only' !== ( $s['scope'] ?? 'all_admin' ) ) {
			return true;
		}
		global $pagenow;
		if ( in_array( $pagenow, array( 'index.php', 'admin.php' ), true ) ) {
			if ( 'index.php' === $pagenow ) {
				return true;
			}
			$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore
			return '' === $page;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		return $screen && 'dashboard' === $screen->id;
	}

	public function menu() {
		add_options_page(
			'حذف نوتیف پیشخوان',
			'حذف نوتیف',
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

		$scope = isset( $in['scope'] ) ? sanitize_key( $in['scope'] ) : $d['scope'];
		if ( ! in_array( $scope, array( 'all_admin', 'dashboard_only' ), true ) ) {
			$scope = $d['scope'];
		}

		return array(
			'enabled'          => empty( $in['enabled'] ) ? 0 : 1,
			'scope'            => $scope,
			'mode'             => $mode,
			'hide_for'         => $hide_for,
			'keep_errors'      => empty( $in['keep_errors'] ) ? 0 : 1,
			'keep_on_own_page' => empty( $in['keep_on_own_page'] ) ? 0 : 1,
			'hide_update_nags' => empty( $in['hide_update_nags'] ) ? 0 : 1,
			'hide_wc_nags'     => empty( $in['hide_wc_nags'] ) ? 0 : 1,
			'hide_settings_errors' => empty( $in['hide_settings_errors'] ) ? 0 : 1,
			'css_fallback'     => empty( $in['css_fallback'] ) ? 0 : 1,
		);
	}

	private function should_mute() {
		if ( ! is_admin() ) {
			return false;
		}

		if ( ! $this->is_target_screen() ) {
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

	public function filter_settings_errors() {
		if ( ! $this->should_mute() ) {
			return;
		}
		$s = self::settings();
		if ( empty( $s['hide_settings_errors'] ) ) {
			return;
		}
		global $wp_settings_errors;
		if ( ! is_array( $wp_settings_errors ) ) {
			return;
		}
		if ( ! empty( $s['keep_errors'] ) ) {
			$wp_settings_errors = array_values( array_filter(
				$wp_settings_errors,
				static function ( $err ) {
					return isset( $err['type'] ) && 'error' === $err['type'];
				}
			) );
			return;
		}
		$wp_settings_errors = array();
	}

	public function body_class( $classes ) {
		if ( $this->muted ) {
			$classes .= ' wbqn-muted';
		}
		return $classes;
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
			echo 'body.wbqn-muted #wpbody-content .notice:not(.notice-error):not(.error),'
				. 'body.wbqn-muted #wpbody-content .updated,'
				. 'body.wbqn-muted #wpbody-content .update-nag,'
				. 'body.wbqn-muted #wpbody-content #message.updated,'
				. 'body.wbqn-muted #wpbody-content .settings-error:not(.error),'
				. 'body.wbqn-muted #wpbody-content [id^="setting-error-"]:not(.error),'
				. 'body.wbqn-muted .woocommerce-message,body.wbqn-muted .woocommerce-info,'
				. 'body.wbqn-muted .alert:not(.alert-danger),'
				. 'body.wbqn-muted .admin-notice,body.wbqn-muted .plugin-notice{display:none!important}';
		} else {
			echo 'body.wbqn-muted #wpbody-content .notice,body.wbqn-muted #wpbody-content .updated,'
				. 'body.wbqn-muted #wpbody-content .error,body.wbqn-muted #wpbody-content .update-nag,'
				. 'body.wbqn-muted #wpbody-content #message,body.wbqn-muted #wpbody-content .settings-error,'
				. 'body.wbqn-muted #wpbody-content [id^="setting-error-"],'
				. 'body.wbqn-muted .woocommerce-message,body.wbqn-muted .woocommerce-info,'
				. 'body.wbqn-muted .woocommerce-error,body.wbqn-muted div.fs-notice,'
				. 'body.wbqn-muted div.jetpack-message,body.wbqn-muted .elementor-message,'
				. 'body.wbqn-muted .e-notice,body.wbqn-muted .alert,body.wbqn-muted .admin-notice,'
				. 'body.wbqn-muted .plugin-notice,body.wbqn-muted .wrap > .notice,'
				. 'body.wbqn-muted .wrap > .updated,body.wbqn-muted .wrap > .error{display:none!important}';
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
