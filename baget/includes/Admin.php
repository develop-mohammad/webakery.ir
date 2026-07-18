<?php
namespace WCCP;

defined( 'ABSPATH' ) || exit;

class Admin {

	/** @var self|null */
	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'add_meta_boxes', array( $this, 'metaboxes' ) );
		add_action( 'save_post_wccp_product', array( $this, 'save_product_meta' ) );
	}

	/** @return string */
	public static function admin_capability() {
		if ( class_exists( 'WooCommerce' ) ) {
			return 'manage_woocommerce';
		}
		return 'manage_options';
	}

	public function menu() {
		$cap = self::admin_capability();

		add_menu_page(
			'Baget — فیلدهای پرداخت',
			'Baget',
			$cap,
			'wccp',
			array( $this, 'render_page' ),
			'dashicons-forms',
			56
		);
		add_submenu_page( 'wccp', 'فیلدها', 'فیلدها', $cap, 'wccp', array( $this, 'render_page' ) );
		add_submenu_page( 'wccp', 'محصولات آنلاین', 'محصولات آنلاین', $cap, 'edit.php?post_type=wccp_product' );
		add_submenu_page( 'wccp', 'افزودن محصول', 'افزودن محصول', $cap, 'post-new.php?post_type=wccp_product' );
	}

	public function assets( $hook ) {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$load   = false;
		if ( false !== strpos( (string) $hook, 'wccp' ) ) {
			$load = true;
		}
		if ( $screen && 'wccp_product' === $screen->post_type ) {
			$load = true;
		}
		if ( ! $load ) {
			return;
		}

		wp_enqueue_style( 'wccp-admin', WCCP_URL . 'assets/admin.css', array(), WCCP_VERSION );
		wp_enqueue_script( 'wccp-admin', WCCP_URL . 'assets/admin.js', array(), WCCP_VERSION, true );

		$product_id = 0;
		if ( $screen && 'wccp_product' === $screen->post_type && ! empty( $_GET['post'] ) ) { // phpcs:ignore
			$product_id = (int) $_GET['post']; // phpcs:ignore
		}

		$active = $product_id ? OnlineProducts::product_active_fields( $product_id ) : Fields::get_active_keys();

		wp_localize_script(
			'wccp-admin',
			'WCCP_ADMIN',
			array(
				'ajax'       => admin_url( 'admin-ajax.php' ),
				'nonce'      => wp_create_nonce( 'wccp_admin' ),
				'productId'  => $product_id,
				'active'     => $active,
				'available'  => array_values( array_diff( array_keys( CustomFields::merged_with_defaults() ), $active ) ),
				'fields'     => CustomFields::merged_with_defaults(),
				'i18n'       => array(
					'saved'   => 'ذخیره شد',
					'saving'  => 'در حال ذخیره…',
					'error'   => 'خطا در ذخیره',
					'confirm' => 'این فیلد سفارشی حذف شود؟',
				),
			)
		);
	}

	public function render_page() {
		if ( ! current_user_can( self::admin_capability() ) && ! current_user_can( 'manage_options' ) ) {
			wp_die( 'دسترسی غیرمجاز' );
		}

		$tab = sanitize_key( wp_unslash( $_GET['tab'] ?? 'fields' ) ); // phpcs:ignore
		if ( 'license' === $tab ) {
			$this->render_license_page();
			return;
		}

		$this->render_fields_page();
	}

	public function render_fields_page() {
		$fields    = CustomFields::merged_with_defaults();
		$active    = Fields::get_active_keys();
		$available = Fields::get_available_keys();
		$tab       = 'fields';
		include WCCP_PATH . 'templates/admin-fields.php';
	}

	public function render_license_page() {
		if ( ! current_user_can( 'manage_options' ) && ! current_user_can( self::admin_capability() ) ) {
			wp_die( 'دسترسی غیرمجاز' );
		}
		$tab = 'license';
		include WCCP_PATH . 'templates/admin-license.php';
	}

	public function metaboxes() {
		add_meta_box( 'wccp_product_fields', 'فیلدهای محصول آنلاین', array( $this, 'render_product_fields_box' ), 'wccp_product', 'normal', 'high' );
		add_meta_box( 'wccp_product_settings', 'تنظیمات و لینک', array( $this, 'render_product_settings_box' ), 'wccp_product', 'side', 'high' );
	}

	public function render_product_fields_box( $post ) {
		$fields    = CustomFields::merged_with_defaults();
		$active    = OnlineProducts::product_active_fields( $post->ID );
		$available = array_values( array_diff( array_keys( $fields ), $active ) );
		echo '<div class="wccp-topbar" style="margin-top:0">';
		echo '<p class="wccp-muted">سوالات و فیلدهای این لینک پرداخت را تنظیم کنید، بعد ذخیره بزنید.</p>';
		echo '<div class="wccp-topbar-actions">';
		echo '<button type="button" class="button" id="wccp-add-radio">+ سوال رادیو</button>';
		echo '<button type="button" class="button" id="wccp-add-checkboxes">+ چندگزینه‌ای</button>';
		echo '<button type="button" class="wccp-btn-save" id="wccp-save-btn"><span class="dashicons dashicons-yes"></span> ذخیره تنظیمات</button>';
		echo '</div></div>';
		echo '<div class="wccp-app" data-mode="product" data-product-id="' . esc_attr( (string) $post->ID ) . '">';
		include WCCP_PATH . 'templates/admin-fields-board.php';
		echo '</div>';
		echo '<div id="wccp-toast" class="wccp-toast" hidden></div>';
		include WCCP_PATH . 'templates/admin-field-modal.php';
	}

	public function render_product_settings_box( $post ) {
		$price = (int) get_post_meta( $post->ID, '_wccp_price', true );
		$link  = get_permalink( $post );
		wp_nonce_field( 'wccp_product_meta', 'wccp_product_nonce' );
		echo '<p><label>قیمت (تومان)<br><input type="number" name="wccp_price" value="' . esc_attr( (string) $price ) . '" class="widefat" /></label></p>';
		echo '<p><strong>پیش‌نمایش لینک</strong><br><code style="word-break:break-all" dir="ltr">' . esc_html( $link ) . '</code></p>';
		echo '<p><a class="button" target="_blank" href="' . esc_url( $link ) . '">باز کردن لینک</a></p>';
	}

	public function save_product_meta( $post_id ) {
		if ( ! isset( $_POST['wccp_product_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wccp_product_nonce'] ) ), 'wccp_product_meta' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( isset( $_POST['wccp_price'] ) ) {
			update_post_meta( $post_id, '_wccp_price', (int) $_POST['wccp_price'] );
		}
		if ( isset( $_POST['wccp_active_fields'] ) ) {
			$raw = wp_unslash( $_POST['wccp_active_fields'] );
			$decoded = is_string( $raw ) ? json_decode( $raw, true ) : $raw;
			if ( is_array( $decoded ) ) {
				update_post_meta( $post_id, '_wccp_active_fields', array_map( 'sanitize_key', $decoded ) );
			}
		}
	}
}
