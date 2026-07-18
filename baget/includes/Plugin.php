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
		add_filter( 'plugin_action_links_' . plugin_basename( WCCP_FILE ), array( $this, 'action_links' ) );
		add_filter( 'plugin_row_meta', array( $this, 'row_meta' ), 10, 2 );

		$this->boot_admin();
		$this->boot_frontend();
		$this->boot_checkout();

		add_action( 'init', array( $this, 'boot_license' ), 20 );
	}

	private function boot_admin() {
		if ( ! is_admin() ) {
			return;
		}
		try {
			if ( class_exists( __NAMESPACE__ . '\\Admin' ) ) {
				Admin::instance();
			}
			if ( class_exists( __NAMESPACE__ . '\\Ajax' ) ) {
				Ajax::instance();
			}
		} catch ( \Throwable $e ) {
			$this->notice( 'Admin: ' . $e->getMessage() );
		}
	}

	private function boot_frontend() {
		try {
			if ( class_exists( __NAMESPACE__ . '\\OnlineProducts' ) ) {
				OnlineProducts::instance();
			}
		} catch ( \Throwable $e ) {
			$this->notice( 'OnlineProducts: ' . $e->getMessage() );
		}
	}

	private function boot_checkout() {
		$start = static function () {
			try {
				if ( class_exists( 'WooCommerce' ) && class_exists( __NAMESPACE__ . '\\Checkout' ) ) {
					Checkout::instance();
				}
			} catch ( \Throwable $e ) {
				// silent on frontend
			}
		};

		if ( class_exists( 'WooCommerce' ) ) {
			$start();
		} else {
			add_action( 'woocommerce_loaded', $start, 5 );
		}
	}

	public function boot_license() {
		try {
			if ( ! class_exists( 'WB_License' ) ) {
				$file = WCCP_PATH . 'includes/class-wb-license.php';
				if ( ! is_readable( $file ) ) {
					return;
				}
				require_once $file;
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
		} catch ( \Throwable $e ) {
			$this->notice( 'License: ' . $e->getMessage() );
		}
	}

	/** @param string $message */
	private function notice( $message ) {
		if ( ! is_admin() ) {
			return;
		}
		add_action(
			'admin_notices',
			static function () use ( $message ) {
				if ( ! current_user_can( 'manage_options' ) ) {
					return;
				}
				echo '<div class="notice notice-error"><p><strong>Baget:</strong> '
					. esc_html( $message ) . '</p></div>';
			}
		);
	}

	/** @param string[] $links */
	public function action_links( $links ) {
		if ( ! is_array( $links ) ) {
			$links = array();
		}
		array_unshift(
			$links,
			'<a href="' . esc_url( admin_url( 'admin.php?page=wccp' ) ) . '"><strong>تنظیمات فیلدها</strong></a>'
		);
		return $links;
	}

	/** @param string[] $links */
	public function row_meta( $links, $file ) {
		if ( plugin_basename( WCCP_FILE ) !== $file ) {
			return $links;
		}
		$links[] = '<a href="' . esc_url( admin_url( 'admin.php?page=wccp' ) ) . '">پیشخوان Baget</a>';
		return $links;
	}

	public static function activate() {
		try {
			if ( false === get_option( Fields::ACTIVE_OPTION, false ) ) {
				update_option( Fields::ACTIVE_OPTION, Fields::default_active(), false );
			}
			if ( class_exists( __NAMESPACE__ . '\\OnlineProducts' ) ) {
				OnlineProducts::register_cpt();
			}
			flush_rewrite_rules();
		} catch ( \Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
		}
	}
}
