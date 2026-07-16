<?php
/**
 * Store manager and accountant roles.
 *
 * @package Webakery
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers مدیر / حسابدار roles and their capabilities.
 */
class Webakery_Roles {

	const MANAGER_ROLE    = 'wbk_manager';
	const ACCOUNTANT_ROLE = 'wbk_accountant';

	/**
	 * Hook registrations.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'panel_menu' ), 5 );
		add_action( 'admin_menu', array( __CLASS__, 'trim_staff_menus' ), 999 );
		add_action( 'admin_init', array( __CLASS__, 'maybe_redirect_panel_home' ) );
	}

	/**
	 * Create or refresh custom roles.
	 */
	public static function install() {
		$common = array(
			'read'                   => true,
			'upload_files'           => true,
			'edit_posts'             => true,
			'edit_others_posts'      => true,
			'edit_published_posts'   => true,
			'publish_posts'          => true,
			'delete_posts'           => true,
			'delete_others_posts'    => true,
			'delete_published_posts' => true,
			'read_private_posts'     => true,
		);

		remove_role( self::MANAGER_ROLE );
		add_role(
			self::MANAGER_ROLE,
			__( 'مدیر فروشگاه', 'webakery' ),
			array_merge(
				$common,
				array(
					'manage_categories' => true,
					'edit_pages'        => true,
					'edit_others_pages' => true,
					'publish_pages'     => true,
				)
			)
		);

		remove_role( self::ACCOUNTANT_ROLE );
		add_role(
			self::ACCOUNTANT_ROLE,
			__( 'حسابدار', 'webakery' ),
			$common
		);

		$admin = get_role( 'administrator' );
		if ( $admin ) {
			$admin->add_cap( 'wbk_manage_panel' );
		}

		$manager = get_role( self::MANAGER_ROLE );
		if ( $manager ) {
			$manager->add_cap( 'wbk_manage_panel' );
		}

		$accountant = get_role( self::ACCOUNTANT_ROLE );
		if ( $accountant ) {
			$accountant->add_cap( 'wbk_manage_panel' );
		}
	}

	/**
	 * Whether current user is store manager or accountant (not WP administrator).
	 *
	 * @return bool
	 */
	public static function is_staff_panel_user() {
		$user = wp_get_current_user();
		if ( ! $user || empty( $user->roles ) ) {
			return false;
		}

		return (bool) array_intersect(
			array( self::MANAGER_ROLE, self::ACCOUNTANT_ROLE ),
			(array) $user->roles
		);
	}

	/**
	 * Top-level panel menu for manager/accountant.
	 */
	public static function panel_menu() {
		if ( ! current_user_can( 'wbk_manage_panel' ) ) {
			return;
		}

		add_menu_page(
			__( 'پنل Webakery', 'webakery' ),
			__( 'پنل فروشگاه', 'webakery' ),
			'wbk_manage_panel',
			'webakery-panel',
			array( __CLASS__, 'render_panel' ),
			'dashicons-store',
			3
		);

		add_submenu_page(
			'webakery-panel',
			__( 'سفارش‌ها', 'webakery' ),
			__( 'سفارش‌ها', 'webakery' ),
			'wbk_manage_panel',
			'edit.php?post_type=wbk_order'
		);

		add_submenu_page(
			'webakery-panel',
			__( 'فاکتورها', 'webakery' ),
			__( 'فاکتورها', 'webakery' ),
			'wbk_manage_panel',
			'edit.php?post_type=wbk_invoice'
		);

		if ( current_user_can( 'manage_options' ) || self::current_user_is_manager() ) {
			add_submenu_page(
				'webakery-panel',
				__( 'محصولات', 'webakery' ),
				__( 'محصولات', 'webakery' ),
				'edit_posts',
				'edit.php?post_type=wbk_product'
			);
		}
	}

	/**
	 * @return bool
	 */
	public static function current_user_is_manager() {
		$user = wp_get_current_user();
		return $user && in_array( self::MANAGER_ROLE, (array) $user->roles, true );
	}

	/**
	 * @return bool
	 */
	public static function current_user_is_accountant() {
		$user = wp_get_current_user();
		return $user && in_array( self::ACCOUNTANT_ROLE, (array) $user->roles, true );
	}

	/**
	 * Hide noisy WP menus for store staff roles.
	 */
	public static function trim_staff_menus() {
		if ( ! self::is_staff_panel_user() ) {
			return;
		}

		$remove = array(
			'index.php',
			'edit.php',
			'edit-comments.php',
			'tools.php',
			'upload.php',
		);

		// Accountants focus on orders/invoices; managers keep products via panel + Webakery.
		if ( self::current_user_is_accountant() ) {
			$remove[] = 'edit.php?post_type=wbk_product';
		}

		foreach ( $remove as $slug ) {
			remove_menu_page( $slug );
		}
	}

	/**
	 * Send staff users to the store panel after login/dashboard.
	 */
	public static function maybe_redirect_panel_home() {
		if ( ! is_admin() || ! self::is_staff_panel_user() || wp_doing_ajax() ) {
			return;
		}

		global $pagenow;
		if ( 'index.php' === $pagenow && empty( $_GET['page'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			wp_safe_redirect( admin_url( 'admin.php?page=webakery-panel' ) );
			exit;
		}
	}

	/**
	 * Panel home for manager/accountant.
	 */
	public static function render_panel() {
		if ( ! current_user_can( 'wbk_manage_panel' ) ) {
			wp_die( esc_html__( 'اجازه دسترسی ندارید.', 'webakery' ) );
		}

		$orders_count = (int) wp_count_posts( 'wbk_order' )->publish;
		$invoice_counts = wp_count_posts( 'wbk_invoice' );
		$invoices_count = isset( $invoice_counts->publish ) ? (int) $invoice_counts->publish : 0;

		$role_label = self::current_user_is_accountant()
			? __( 'حسابدار', 'webakery' )
			: ( self::current_user_is_manager() ? __( 'مدیر فروشگاه', 'webakery' ) : __( 'مدیر', 'webakery' ) );
		?>
		<div class="wrap wbk-admin wbk-panel" dir="rtl">
			<h1><?php esc_html_e( 'پنل فروشگاه Webakery', 'webakery' ); ?></h1>
			<p class="wbk-admin__lead">
				<?php
				printf(
					/* translators: %s: role label */
					esc_html__( 'خوش آمدید. شما با نقش %s وارد شده‌اید. سفارش‌ها و فاکتورها را از اینجا مدیریت کنید.', 'webakery' ),
					esc_html( $role_label )
				);
				?>
			</p>

			<div class="wbk-panel__grid">
				<a class="wbk-panel__card" href="<?php echo esc_url( admin_url( 'edit.php?post_type=wbk_order' ) ); ?>">
					<span class="wbk-panel__card-title"><?php esc_html_e( 'سفارش‌ها', 'webakery' ); ?></span>
					<span class="wbk-panel__card-count"><?php echo esc_html( (string) $orders_count ); ?></span>
					<span class="wbk-panel__card-desc"><?php esc_html_e( 'مشاهده و پیگیری سفارش‌های مشتریان', 'webakery' ); ?></span>
				</a>
				<a class="wbk-panel__card" href="<?php echo esc_url( admin_url( 'edit.php?post_type=wbk_invoice' ) ); ?>">
					<span class="wbk-panel__card-title"><?php esc_html_e( 'فاکتورها', 'webakery' ); ?></span>
					<span class="wbk-panel__card-count"><?php echo esc_html( (string) $invoices_count ); ?></span>
					<span class="wbk-panel__card-desc"><?php esc_html_e( 'صدور، چاپ و پیگیری وضعیت فاکتور', 'webakery' ); ?></span>
				</a>
				<?php if ( current_user_can( 'manage_options' ) || self::current_user_is_manager() ) : ?>
					<a class="wbk-panel__card" href="<?php echo esc_url( admin_url( 'edit.php?post_type=wbk_product' ) ); ?>">
						<span class="wbk-panel__card-title"><?php esc_html_e( 'محصولات', 'webakery' ); ?></span>
						<span class="wbk-panel__card-desc"><?php esc_html_e( 'مدیریت کاتالوگ و قیمت‌ها', 'webakery' ); ?></span>
					</a>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}
}
