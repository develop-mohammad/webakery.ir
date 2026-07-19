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
		add_action( 'admin_post_wccp_save_template', array( $this, 'handle_save_template' ) );
		add_action( 'admin_post_wccp_delete_template', array( $this, 'handle_delete_template' ) );
		add_action( 'admin_post_wccp_save_wc_templates', array( $this, 'handle_save_wc_templates' ) );
		add_action( 'admin_post_wccp_save_payments', array( $this, 'handle_save_payments' ) );
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
		add_submenu_page( 'wccp', 'قالب‌ها و فیلدها', 'قالب‌ها و فیلدها', $cap, 'wccp', array( $this, 'render_page' ) );
		add_submenu_page( 'wccp', 'ساخت قالب', 'ساخت قالب', $cap, 'wccp-templates', array( $this, 'render_templates_redirect' ) );
		add_submenu_page( 'wccp', 'محصولات فروشگاه', 'محصولات فروشگاه', $cap, 'wccp-wc-products', array( $this, 'render_wc_products_redirect' ) );
		add_submenu_page( 'wccp', 'لینک پرداخت', 'لینک پرداخت', $cap, 'edit.php?post_type=wccp_product' );
		add_submenu_page( 'wccp', 'افزودن لینک پرداخت', 'افزودن لینک پرداخت', $cap, 'post-new.php?post_type=wccp_product' );
		add_submenu_page( 'wccp', 'پرداخت', 'پرداخت', $cap, 'wccp-payments', array( $this, 'render_payments_redirect' ) );
		add_submenu_page( 'wccp', 'خرید و لایسنس', 'خرید و لایسنس', $cap, 'wccp-license', array( $this, 'render_license_page' ) );
	}

	public function render_payments_redirect() {
		wp_safe_redirect( admin_url( 'admin.php?page=wccp&tab=payments' ) );
		exit;
	}

	public function render_templates_redirect() {
		wp_safe_redirect( admin_url( 'admin.php?page=wccp&tab=templates' ) );
		exit;
	}

	public function render_wc_products_redirect() {
		wp_safe_redirect( admin_url( 'admin.php?page=wccp&tab=wc-products' ) );
		exit;
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

		$default_tpl = Templates::default_key();
		$current_tpl = isset( $_GET['tpl'] ) ? sanitize_key( wp_unslash( $_GET['tpl'] ) ) : $default_tpl; // phpcs:ignore
		if ( ! isset( Templates::all()[ $current_tpl ] ) ) {
			$current_tpl = $default_tpl;
		}

		if ( $product_id ) {
			$active = OnlineProducts::product_active_fields( $product_id );
		} else {
			$active = Templates::fields_for( $current_tpl );
		}

		wp_localize_script(
			'wccp-admin',
			'WCCP_ADMIN',
			array(
				'ajax'         => admin_url( 'admin-ajax.php' ),
				'nonce'        => wp_create_nonce( 'wccp_admin' ),
				'productId'    => $product_id,
				'active'       => $active,
				'available'    => array_values( array_diff( array_keys( CustomFields::merged_with_defaults() ), $active ) ),
				'fields'       => CustomFields::merged_with_defaults(),
				'templateKey'  => $product_id ? '' : $current_tpl,
				'defaultTpl'   => $default_tpl,
				'templates'    => Templates::all(),
				'defaultKeys'  => array_keys( Fields::defaults() ),
				'hidden'       => CustomFields::hidden_default_keys(),
				'defaultDefs'  => Fields::defaults(),
				'i18n'         => array(
					'saved'     => 'ذخیره شد',
					'saving'    => 'در حال ذخیره…',
					'error'     => 'خطا در ذخیره',
					'confirm'   => 'این فیلد حذف شود؟',
					'confirm_default' => 'فیلد پیش‌فرض مخفی شود؟ بعداً قابل بازیابی است.',
					'star_ok'   => 'قالب پیش‌فرض checkout تنظیم شد',
					'save_tpl'  => 'ذخیره فیلدهای این قالب',
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
		if ( 'templates' === $tab ) {
			$this->render_templates_page();
			return;
		}
		if ( 'wc-products' === $tab ) {
			$this->render_wc_products_page();
			return;
		}
		if ( 'payments' === $tab ) {
			$this->render_payments_page();
			return;
		}

		$this->render_fields_page();
	}

	public function render_payments_page() {
		if ( ! current_user_can( self::admin_capability() ) && ! current_user_can( 'manage_options' ) ) {
			wp_die( 'دسترسی غیرمجاز' );
		}
		$tab = 'payments';
		include WCCP_PATH . 'templates/admin-payments.php';
	}

	public function handle_save_payments() {
		if ( ! current_user_can( self::admin_capability() ) && ! current_user_can( 'manage_options' ) ) {
			wp_die( 'دسترسی غیرمجاز' );
		}
		check_admin_referer( 'wccp_save_payments' );
		$saved = Payments::save_settings(
			array(
				'zarinpal_merchant' => wp_unslash( $_POST['zarinpal_merchant'] ?? '' ), // phpcs:ignore
				'sandbox'           => ! empty( $_POST['sandbox'] ) ? 1 : 0,
			)
		);
		$msg = Payments::looks_like_merchant( $saved['zarinpal_merchant'] )
			? 'تنظیمات پرداخت ذخیره شد. مرچنت زرین‌پال فعال است.'
			: 'ذخیره شد، ولی مرچنت‌کد معتبر به‌نظر نمی‌رسد. کد ۳۶ کاراکتری زرین‌پال را بررسی کنید.';
		add_settings_error( 'wccp_payments', 'ok', $msg, Payments::looks_like_merchant( $saved['zarinpal_merchant'] ) ? 'updated' : 'error' );
		set_transient( 'settings_errors', get_settings_errors(), 30 );
		wp_safe_redirect( admin_url( 'admin.php?page=wccp&tab=payments' ) );
		exit;
	}

	public function render_wc_products_page() {
		if ( ! current_user_can( self::admin_capability() ) && ! current_user_can( 'manage_options' ) ) {
			wp_die( 'دسترسی غیرمجاز' );
		}
		$tab = 'wc-products';
		include WCCP_PATH . 'templates/admin-wc-products.php';
	}

	public function render_fields_page() {
		$tab = 'fields';
		include WCCP_PATH . 'templates/admin-fields.php';
	}

	public function render_templates_page() {
		if ( ! current_user_can( self::admin_capability() ) && ! current_user_can( 'manage_options' ) ) {
			wp_die( 'دسترسی غیرمجاز' );
		}
		$tab = 'templates';
		include WCCP_PATH . 'templates/admin-templates.php';
	}

	public function render_license_page() {
		if ( ! current_user_can( 'manage_options' ) && ! current_user_can( self::admin_capability() ) ) {
			wp_die( 'دسترسی غیرمجاز' );
		}
		if ( isset( $_GET['page'] ) && 'wccp-license' === sanitize_key( wp_unslash( $_GET['page'] ) ) ) { // phpcs:ignore
			if ( ! isset( $_GET['tab'] ) ) { // phpcs:ignore
				wp_safe_redirect( admin_url( 'admin.php?page=wccp&tab=license' ) );
				exit;
			}
		}
		$tab = 'license';
		include WCCP_PATH . 'templates/admin-license.php';
	}

	public function handle_save_template() {
		if ( ! current_user_can( self::admin_capability() ) && ! current_user_can( 'manage_options' ) ) {
			wp_die( 'دسترسی غیرمجاز' );
		}
		check_admin_referer( 'wccp_save_template' );
		$key = sanitize_key( wp_unslash( $_POST['key'] ?? '' ) );
		$res = Templates::save_custom( wp_unslash( $_POST ), $key ); // phpcs:ignore
		if ( is_wp_error( $res ) ) {
			add_settings_error( 'wccp_templates', 'err', $res->get_error_message(), 'error' );
			set_transient( 'settings_errors', get_settings_errors(), 30 );
			wp_safe_redirect( admin_url( 'admin.php?page=wccp&tab=templates' ) );
			exit;
		}
		add_settings_error( 'wccp_templates', 'ok', 'قالب ذخیره شد.', 'updated' );
		set_transient( 'settings_errors', get_settings_errors(), 30 );
		wp_safe_redirect( admin_url( 'admin.php?page=wccp&tab=templates&edit=' . rawurlencode( $res ) ) );
		exit;
	}

	public function handle_delete_template() {
		if ( ! current_user_can( self::admin_capability() ) && ! current_user_can( 'manage_options' ) ) {
			wp_die( 'دسترسی غیرمجاز' );
		}
		check_admin_referer( 'wccp_delete_template' );
		$res = Templates::delete_custom( sanitize_key( wp_unslash( $_POST['key'] ?? '' ) ) );
		if ( is_wp_error( $res ) ) {
			add_settings_error( 'wccp_templates', 'err', $res->get_error_message(), 'error' );
		} else {
			add_settings_error( 'wccp_templates', 'ok', 'قالب حذف شد.', 'updated' );
		}
		set_transient( 'settings_errors', get_settings_errors(), 30 );
		wp_safe_redirect( admin_url( 'admin.php?page=wccp&tab=templates' ) );
		exit;
	}

	public function handle_save_wc_templates() {
		if ( ! current_user_can( self::admin_capability() ) && ! current_user_can( 'manage_options' ) ) {
			wp_die( 'دسترسی غیرمجاز' );
		}
		check_admin_referer( 'wccp_save_wc_templates' );

		$map  = isset( $_POST['wccp_tpl'] ) && is_array( $_POST['wccp_tpl'] ) ? wp_unslash( $_POST['wccp_tpl'] ) : array(); // phpcs:ignore
		$all  = Templates::all();
		$saved = 0;
		foreach ( $map as $product_id => $tpl_key ) {
			$product_id = (int) $product_id;
			$tpl_key    = sanitize_key( (string) $tpl_key );
			if ( $product_id <= 0 || get_post_type( $product_id ) !== 'product' ) {
				continue;
			}
			if ( $tpl_key === '' || ! isset( $all[ $tpl_key ] ) ) {
				delete_post_meta( $product_id, Templates::WC_PRODUCT_META );
				$saved++;
				continue;
			}
			Templates::set_wc_product_template( $product_id, $tpl_key );
			$saved++;
		}

		add_settings_error( 'wccp_wc_products', 'ok', sprintf( 'قالب %d محصول ذخیره شد.', $saved ), 'updated' );
		set_transient( 'settings_errors', get_settings_errors(), 30 );

		$paged  = max( 1, (int) ( $_POST['paged'] ?? 1 ) );
		$search = isset( $_POST['s'] ) ? sanitize_text_field( wp_unslash( $_POST['s'] ) ) : '';
		$url    = admin_url( 'admin.php?page=wccp&tab=wc-products&paged=' . $paged );
		if ( $search !== '' ) {
			$url = add_query_arg( 's', rawurlencode( $search ), $url );
		}
		wp_safe_redirect( $url );
		exit;
	}

	public function metaboxes() {
		add_meta_box( 'wccp_product_fields', 'فیلدهای محصول آنلاین', array( $this, 'render_product_fields_box' ), 'wccp_product', 'normal', 'high' );
		add_meta_box( 'wccp_product_settings', 'تنظیمات، قالب و لینک', array( $this, 'render_product_settings_box' ), 'wccp_product', 'side', 'high' );
	}

	public function render_product_fields_box( $post ) {
		$fields    = CustomFields::merged_with_defaults();
		$active    = OnlineProducts::product_active_fields( $post->ID );
		$available = array_values( array_diff( array_keys( $fields ), $active ) );
		echo '<div class="wccp-topbar" style="margin-top:0">';
		echo '<p class="wccp-muted">سوالات و فیلدهای این لینک پرداخت را تنظیم کنید، بعد ذخیره بزنید.</p>';
		echo '<div class="wccp-topbar-actions">';
		echo '<button type="button" class="button" id="wccp-add-info">+ متن ساده</button>';
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
		$price    = (int) get_post_meta( $post->ID, '_wccp_price', true );
		$selected = Templates::product_template_key( $post->ID );
		$link     = get_permalink( $post );
		$all      = Templates::all();
		$tpl_fields = Templates::fields_for( $selected );
		wp_nonce_field( 'wccp_product_meta', 'wccp_product_nonce' );

		echo '<p><label><strong>قیمت (تومان)</strong><br><input type="number" name="wccp_price" min="1000" step="1000" value="' . esc_attr( (string) $price ) . '" class="widefat" /></label></p>';
		if ( ! Payments::zarinpal_enabled() ) {
			echo '<p class="description" style="color:#b91c1c">مرچنت زرین‌پال تنظیم نشده. برای پرداخت مستقیم به <a href="' . esc_url( admin_url( 'admin.php?page=wccp&tab=payments' ) ) . '">Baget ← پرداخت</a> بروید.</p>';
		} else {
			echo '<p class="description" style="color:#15803d">✓ مرچنت زرین‌پال فعال است — دکمه «ادامه و پرداخت» به درگاه وصل می‌شود.</p>';
		}

		echo '<p><label><strong>قالب صفحه پرداخت</strong><br>';
		echo '<select name="wccp_template" class="widefat" id="wccp-product-template">';
		foreach ( $all as $key => $tpl ) {
			$count = count( Templates::sanitize_fields( $tpl['fields'] ?? array() ) );
			echo '<option value="' . esc_attr( $key ) . '" ' . selected( $selected, $key, false ) . '>'
				. esc_html( $tpl['label'] ) . ' (' . (int) $count . ' فیلد)'
				. '</option>';
		}
		echo '</select></label></p>';

		echo '<p><label style="display:flex;gap:8px;align-items:flex-start">';
		echo '<input type="checkbox" name="wccp_apply_template_fields" value="1" checked />';
		echo '<span><strong>اعمال فیلدهای این قالب روی محصول</strong><br><small class="description">با ذخیره، فیلدهای اختصاصی قالب جایگزین فیلدهای فعلی محصول می‌شود.</small></span>';
		echo '</label></p>';

		echo '<p class="description"><a href="' . esc_url( admin_url( 'admin.php?page=wccp&tab=templates&edit=' . rawurlencode( $selected ) ) ) . '">ویرایش فیلدهای این قالب</a> · ';
		echo '<a href="' . esc_url( admin_url( 'admin.php?page=wccp&tab=templates' ) ) . '">+ قالب جدید</a></p>';

		if ( isset( $all[ $selected ] ) ) {
			$t = $all[ $selected ];
			echo '<div class="wccp-tpl-mini-preview" style="background:' . esc_attr( $t['background'] ) . ';border-radius:10px;padding:10px;margin:8px 0 12px">';
			echo '<div style="background:' . esc_attr( $t['card'] ) . ';border-radius:8px;padding:10px;border-top:3px solid ' . esc_attr( $t['primary'] ) . '">';
			echo '<div style="font-size:12px;font-weight:700;color:' . esc_attr( $t['text'] ) . '">' . esc_html( $t['label'] ) . '</div>';
			echo '<div style="font-size:11px;color:' . esc_attr( $t['muted'] ) . ';margin-top:4px">' . esc_html( (string) count( $tpl_fields ) ) . ' فیلد اختصاصی</div>';
			echo '<div style="margin-top:8px;background:' . esc_attr( $t['primary'] ) . ';color:' . esc_attr( $t['button_text'] ) . ';text-align:center;border-radius:6px;padding:6px;font-size:11px;font-weight:700">دکمه پرداخت</div>';
			echo '</div></div>';
		}

		echo '<p><strong>پیش‌نمایش لینک</strong><br><code style="word-break:break-all" dir="ltr">' . esc_html( (string) $link ) . '</code></p>';
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
		if ( isset( $_POST['wccp_template'] ) ) {
			$key = sanitize_key( wp_unslash( $_POST['wccp_template'] ) );
			$all = Templates::all();
			if ( isset( $all[ $key ] ) ) {
				update_post_meta( $post_id, '_wccp_template', $key );
				if ( ! empty( $_POST['wccp_apply_template_fields'] ) ) {
					Templates::apply_to_product( $post_id, $key );
				}
			}
		}
		// فقط وقتی «اعمال فیلدهای قالب» تیک نخورده، ترتیب دستی برد را نگه دار
		if ( empty( $_POST['wccp_apply_template_fields'] ) && isset( $_POST['wccp_active_fields'] ) ) {
			$raw     = wp_unslash( $_POST['wccp_active_fields'] );
			$decoded = is_string( $raw ) ? json_decode( $raw, true ) : $raw;
			if ( is_array( $decoded ) ) {
				update_post_meta( $post_id, '_wccp_active_fields', array_map( 'sanitize_key', $decoded ) );
			}
		}
	}
}
