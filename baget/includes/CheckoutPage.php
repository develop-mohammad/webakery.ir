<?php
namespace WCCP;

defined( 'ABSPATH' ) || exit;

/**
 * تشخیص صفحه پرداخت ووکامرس بدون وابستگی به شناسه ثابت (مثل ۷).
 * اگر صفحه با گوتنبرگ/بلاک Checkout ساخته شده باشد، به شورت‌کد کلاسیک تبدیل می‌شود
 * تا فیلترهای فیلد Baget کار کنند.
 */
class CheckoutPage {

	const OPTION_PAGE_ID = 'wccp_checkout_page_id';
	const OPTION_FORCE   = 'wccp_force_classic_checkout';

	/** @var self|null */
	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		if ( ! class_exists( 'WooCommerce' ) && ! function_exists( 'WC' ) ) {
			return;
		}

		// تبدیل بلاک گوتنبرگ به شورت‌کد کلاسیک روی فرانت
		add_filter( 'the_content', array( $this, 'maybe_force_classic_content' ), 5 );
		// برای قالب‌هایی که content را مستقیم می‌خوانند
		add_action( 'template_redirect', array( $this, 'maybe_disable_block_checkout' ), 1 );

		if ( is_admin() ) {
			add_action( 'admin_notices', array( $this, 'admin_notice_block_checkout' ) );
			add_action( 'admin_post_wccp_fix_checkout_page', array( $this, 'handle_fix_checkout_page' ) );
			add_action( 'admin_post_wccp_convert_classic_checkout', array( $this, 'handle_convert_classic' ) );
		}
	}

	/** شناسه صفحه پرداخت انتخاب‌شده در Baget (۰ = خودکار) */
	public static function override_page_id() {
		return max( 0, (int) get_option( self::OPTION_PAGE_ID, 0 ) );
	}

	/** آیا اجبار به چک‌اوت کلاسیک فعال است؟ پیش‌فرض: بله */
	public static function force_classic_enabled() {
		$v = get_option( self::OPTION_FORCE, '1' );
		return '0' !== (string) $v && false !== $v;
	}

	/**
	 * شناسه صفحه پرداخت واقعی — بدون هاردکد.
	 * اولویت: تنظیم Baget → ووکامرس → جستجو بر اساس محتوا.
	 */
	public static function resolve_id() {
		$override = self::override_page_id();
		if ( $override > 0 && self::is_usable_page( $override ) ) {
			return $override;
		}

		$wc_id = function_exists( 'wc_get_page_id' ) ? (int) wc_get_page_id( 'checkout' ) : 0;
		if ( $wc_id > 0 && self::is_usable_page( $wc_id ) ) {
			return $wc_id;
		}

		$found = self::find_checkout_page_id();
		return $found > 0 ? $found : 0;
	}

	/** @return bool */
	public static function is_usable_page( $page_id ) {
		$page_id = (int) $page_id;
		if ( $page_id <= 0 ) {
			return false;
		}
		$post = get_post( $page_id );
		return $post && 'page' === $post->post_type && 'publish' === $post->post_status;
	}

	/** آیا محتوای صفحه چک‌اوت بلاکی (گوتنبرگ) دارد؟ */
	public static function content_has_checkout_block( $content ) {
		$content = (string) $content;
		if ( '' === $content ) {
			return false;
		}
		if ( false !== strpos( $content, 'woocommerce/checkout' ) ) {
			return true;
		}
		if ( false !== strpos( $content, 'wp:woocommerce/checkout' ) ) {
			return true;
		}
		// بلاک‌های قدیمی‌تر / cart-checkout
		if ( preg_match( '/<!--\s*wp:woocommerce\/(?:checkout|cart-checkout)/', $content ) ) {
			return true;
		}
		return false;
	}

	/** آیا شورت‌کد کلاسیک دارد؟ */
	public static function content_has_classic_shortcode( $content ) {
		$content = (string) $content;
		return false !== strpos( $content, '[woocommerce_checkout' );
	}

	/** وضعیت صفحه پرداخت فعلی */
	public static function status() {
		$id      = self::resolve_id();
		$content = $id ? (string) get_post_field( 'post_content', $id ) : '';
		$wc_id   = function_exists( 'wc_get_page_id' ) ? (int) wc_get_page_id( 'checkout' ) : 0;

		return array(
			'page_id'        => $id,
			'wc_page_id'     => $wc_id,
			'override_id'    => self::override_page_id(),
			'title'          => $id ? get_the_title( $id ) : '',
			'permalink'      => $id ? get_permalink( $id ) : '',
			'has_block'      => self::content_has_checkout_block( $content ),
			'has_shortcode'  => self::content_has_classic_shortcode( $content ),
			'force_classic'  => self::force_classic_enabled(),
			'wc_mismatch'    => ( $id > 0 && $wc_id > 0 && $id !== $wc_id ),
			'wc_missing'     => ( $wc_id <= 0 || ! self::is_usable_page( $wc_id ) ),
		);
	}

	/**
	 * جستجوی صفحه دارای شورت‌کد یا بلاک چک‌اوت.
	 * شورت‌کد کلاسیک اولویت دارد.
	 */
	public static function find_checkout_page_id() {
		$pages = get_posts(
			array(
				'post_type'      => 'page',
				'post_status'    => 'publish',
				'posts_per_page' => 50,
				'orderby'        => 'modified',
				'order'          => 'DESC',
				's'              => 'woocommerce',
			)
		);

		$block_id = 0;
		foreach ( $pages as $page ) {
			$content = (string) $page->post_content;
			if ( self::content_has_classic_shortcode( $content ) ) {
				return (int) $page->ID;
			}
			if ( ! $block_id && self::content_has_checkout_block( $content ) ) {
				$block_id = (int) $page->ID;
			}
		}

		// جستجوی گسترده‌تر بدون s (ممکن است عنوان صفحه فارسی باشد)
		if ( ! $block_id ) {
			$pages = get_posts(
				array(
					'post_type'      => 'page',
					'post_status'    => 'publish',
					'posts_per_page' => 100,
					'orderby'        => 'ID',
					'order'          => 'DESC',
				)
			);
			foreach ( $pages as $page ) {
				$content = (string) $page->post_content;
				if ( self::content_has_classic_shortcode( $content ) ) {
					return (int) $page->ID;
				}
				if ( ! $block_id && self::content_has_checkout_block( $content ) ) {
					$block_id = (int) $page->ID;
				}
			}
		}

		return $block_id;
	}

	/** آیا صفحه فعلی، صفحه پرداخت است؟ (مستقل از شناسه ثابت) */
	public static function is_current_checkout() {
		if ( function_exists( 'is_checkout' ) && is_checkout() && ( ! function_exists( 'is_order_received_page' ) || ! is_order_received_page() ) ) {
			return true;
		}
		if ( ! is_page() ) {
			return false;
		}
		$resolved = self::resolve_id();
		return $resolved > 0 && (int) get_queried_object_id() === $resolved;
	}

	/**
	 * اگر صفحه چک‌اوت بلاکی باشد و اجبار کلاسیک فعال باشد،
	 * محتوا را با [woocommerce_checkout] جایگزین کن.
	 *
	 * @param string $content
	 * @return string
	 */
	public function maybe_force_classic_content( $content ) {
		if ( is_admin() || ! self::force_classic_enabled() ) {
			return $content;
		}
		if ( ! self::is_current_checkout() ) {
			return $content;
		}
		if ( self::content_has_classic_shortcode( $content ) && ! self::content_has_checkout_block( $content ) ) {
			return $content;
		}
		if ( ! self::content_has_checkout_block( $content ) && self::content_has_classic_shortcode( $content ) ) {
			return $content;
		}
		// بلاک گوتنبرگ یا محتوای خالی/ناقص → شورت‌کد کلاسیک
		if ( self::content_has_checkout_block( $content ) || ! self::content_has_classic_shortcode( $content ) ) {
			return '[woocommerce_checkout]';
		}
		return $content;
	}

	/**
	 * جلوگیری از رندر بلاک Checkout ووکامرس وقتی اجبار کلاسیک فعال است.
	 */
	public function maybe_disable_block_checkout() {
		if ( ! self::force_classic_enabled() || ! self::is_current_checkout() ) {
			return;
		}
		// Cart/Checkout blocks package
		if ( class_exists( '\Automattic\WooCommerce\Blocks\Package' ) ) {
			add_filter( 'woocommerce_feature_block_checkout_enabled', '__return_false', 100 );
		}
		// اطمینان از اینکه WC صفحه را به‌عنوان checkout بشناسد
		$resolved = self::resolve_id();
		$wc_id    = function_exists( 'wc_get_page_id' ) ? (int) wc_get_page_id( 'checkout' ) : 0;
		if ( $resolved > 0 && $resolved !== $wc_id && self::is_usable_page( $resolved ) ) {
			add_filter(
				'woocommerce_get_checkout_page_id',
				static function () use ( $resolved ) {
					return $resolved;
				},
				100
			);
			// برخی نسخه‌ها از option مستقیم می‌خوانند
			add_filter(
				'pre_option_woocommerce_checkout_page_id',
				static function () use ( $resolved ) {
					return (string) $resolved;
				},
				100
			);
		}
	}

	/** ذخیره تنظیمات صفحه پرداخت از فرم ادمین */
	public static function save_page_settings( array $data ) {
		$page_id = max( 0, (int) ( $data['checkout_page_id'] ?? 0 ) );
		if ( $page_id > 0 && ! self::is_usable_page( $page_id ) ) {
			$page_id = 0;
		}
		update_option( self::OPTION_PAGE_ID, $page_id, false );
		update_option( self::OPTION_FORCE, ! empty( $data['force_classic'] ) ? '1' : '0', false );

		$sync_wc = ! empty( $data['sync_wc_page'] );
		if ( $sync_wc && $page_id > 0 ) {
			update_option( 'woocommerce_checkout_page_id', $page_id, false );
		} elseif ( $sync_wc && $page_id <= 0 ) {
			$found = self::find_checkout_page_id();
			if ( $found > 0 ) {
				update_option( 'woocommerce_checkout_page_id', $found, false );
			}
		}

		return self::status();
	}

	/** تبدیل دائمی محتوای صفحه به شورت‌کد کلاسیک */
	public static function convert_page_to_classic( $page_id = 0 ) {
		$page_id = $page_id > 0 ? (int) $page_id : self::resolve_id();
		if ( ! self::is_usable_page( $page_id ) ) {
			return new \WP_Error( 'page', 'صفحه پرداخت پیدا نشد.' );
		}
		$updated = wp_update_post(
			array(
				'ID'           => $page_id,
				'post_content' => '<!-- wp:shortcode -->[woocommerce_checkout]<!-- /wp:shortcode -->',
			),
			true
		);
		if ( is_wp_error( $updated ) ) {
			return $updated;
		}
		update_option( 'woocommerce_checkout_page_id', $page_id, false );
		update_option( self::OPTION_PAGE_ID, $page_id, false );
		update_option( self::OPTION_FORCE, '1', false );
		return $page_id;
	}

	public function handle_fix_checkout_page() {
		if ( ! current_user_can( Admin::admin_capability() ) && ! current_user_can( 'manage_options' ) ) {
			wp_die( 'دسترسی غیرمجاز' );
		}
		check_admin_referer( 'wccp_fix_checkout_page' );
		self::save_page_settings(
			array(
				'checkout_page_id' => (int) ( $_POST['checkout_page_id'] ?? 0 ), // phpcs:ignore
				'force_classic'    => ! empty( $_POST['force_classic'] ) ? 1 : 0,
				'sync_wc_page'     => ! empty( $_POST['sync_wc_page'] ) ? 1 : 0,
			)
		);
		add_settings_error( 'wccp_payments', 'checkout_ok', 'تنظیمات صفحه پرداخت ذخیره شد.', 'updated' );
		set_transient( 'settings_errors', get_settings_errors(), 30 );
		wp_safe_redirect( admin_url( 'admin.php?page=wccp&tab=payments' ) );
		exit;
	}

	public function handle_convert_classic() {
		if ( ! current_user_can( Admin::admin_capability() ) && ! current_user_can( 'manage_options' ) ) {
			wp_die( 'دسترسی غیرمجاز' );
		}
		check_admin_referer( 'wccp_convert_classic_checkout' );
		$page_id = (int) ( $_GET['page_id'] ?? 0 ); // phpcs:ignore
		$res     = self::convert_page_to_classic( $page_id );
		if ( is_wp_error( $res ) ) {
			add_settings_error( 'wccp_payments', 'convert_err', $res->get_error_message(), 'error' );
		} else {
			add_settings_error(
				'wccp_payments',
				'convert_ok',
				sprintf( 'صفحه پرداخت (شناسه %d) به چک‌اوت کلاسیک تبدیل شد و در ووکامرس ثبت شد.', (int) $res ),
				'updated'
			);
		}
		set_transient( 'settings_errors', get_settings_errors(), 30 );
		wp_safe_redirect( admin_url( 'admin.php?page=wccp&tab=payments' ) );
		exit;
	}

	public function admin_notice_block_checkout() {
		if ( ! current_user_can( Admin::admin_capability() ) && ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen ) {
			return;
		}
		$on_baget = ( false !== strpos( (string) $screen->id, 'wccp' ) );
		$on_wc    = in_array( $screen->id, array( 'woocommerce_page_wc-settings', 'edit-page', 'page' ), true );
		if ( ! $on_baget && ! $on_wc ) {
			return;
		}

		$status = self::status();
		if ( empty( $status['has_block'] ) && empty( $status['wc_missing'] ) && empty( $status['wc_mismatch'] ) ) {
			return;
		}

		$convert_url = wp_nonce_url(
			admin_url( 'admin-post.php?action=wccp_convert_classic_checkout&page_id=' . (int) $status['page_id'] ),
			'wccp_convert_classic_checkout'
		);
		$settings_url = admin_url( 'admin.php?page=wccp&tab=payments' );

		echo '<div class="notice notice-warning"><p><strong>Baget:</strong> ';
		if ( ! empty( $status['has_block'] ) ) {
			echo 'صفحه پرداخت با گوتنبرگ/بلاک ساخته شده و فیلدهای سفارشی Baget روی بلاک کار نمی‌کنند. ';
			echo '<a class="button button-small" href="' . esc_url( $convert_url ) . '">تبدیل به چک‌اوت کلاسیک</a> ';
		}
		if ( ! empty( $status['wc_missing'] ) || ! empty( $status['wc_mismatch'] ) ) {
			echo 'شناسه صفحه پرداخت ووکامرس با صفحه واقعی یکی نیست (دیگر ثابت ۷ فرض نمی‌شود). ';
			echo '<a href="' . esc_url( $settings_url ) . '">انتخاب صفحه پرداخت در Baget</a>';
		}
		echo '</p></div>';
	}

	/** لیست صفحات برای dropdown ادمین */
	public static function pages_for_select() {
		$pages = get_pages(
			array(
				'sort_column' => 'post_title',
				'post_status' => 'publish',
			)
		);
		$out = array();
		foreach ( (array) $pages as $page ) {
			$out[ (int) $page->ID ] = sprintf( '%s (شناسه %d)', $page->post_title, (int) $page->ID );
		}
		return $out;
	}
}
