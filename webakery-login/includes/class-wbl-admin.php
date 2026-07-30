<?php
defined( 'ABSPATH' ) || exit;

class WBL_Admin {

	public static function hooks() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'handle_save' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( WBL_FILE ), array( __CLASS__, 'links' ) );
		add_action( 'show_user_profile', array( __CLASS__, 'user_phone_field' ) );
		add_action( 'edit_user_profile', array( __CLASS__, 'user_phone_field' ) );
		add_action( 'personal_options_update', array( __CLASS__, 'save_user_phone' ) );
		add_action( 'edit_user_profile_update', array( __CLASS__, 'save_user_phone' ) );
	}

	public static function menu() {
		add_menu_page(
			'ورود آسان',
			'ورود آسان',
			'manage_options',
			'webakery-login',
			array( __CLASS__, 'render' ),
			'dashicons-smartphone',
			58
		);
	}

	public static function assets( $hook ) {
		if ( false === strpos( (string) $hook, 'webakery-login' ) ) {
			return;
		}
		wp_enqueue_style( 'wbl-admin', WBL_URL . 'assets/css/admin.css', array(), WBL_VERSION );
		wp_enqueue_script( 'wbl-admin', WBL_URL . 'assets/js/admin.js', array(), WBL_VERSION, true );
		wp_localize_script(
			'wbl-admin',
			'WBLAdmin',
			array(
				'ajax'  => admin_url( 'admin-ajax.php' ),
				'nonce' => wp_create_nonce( 'wbl_admin' ),
			)
		);
	}

	public static function handle_save() {
		if ( empty( $_POST['wbl_save_settings'] ) ) { // phpcs:ignore
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		check_admin_referer( 'wbl_settings' );
		$input = isset( $_POST['settings'] ) && is_array( $_POST['settings'] ) ? wp_unslash( $_POST['settings'] ) : array(); // phpcs:ignore
		// چک‌باکس‌های خالی.
		foreach ( array( 'enable_phone', 'enable_google', 'auto_register', 'replace_wp_login' ) as $k ) {
			if ( ! isset( $input[ $k ] ) ) {
				$input[ $k ] = 0;
			}
		}
		WBL_Settings::save( $input );
		wp_safe_redirect( add_query_arg( array( 'page' => 'webakery-login', 'saved' => '1' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$s     = WBL_Settings::all();
		$saved = ! empty( $_GET['saved'] ); // phpcs:ignore
		include WBL_PATH . 'templates/admin-settings.php';
	}

	public static function links( $links ) {
		$url = admin_url( 'admin.php?page=webakery-login' );
		array_unshift( $links, '<a href="' . esc_url( $url ) . '">تنظیمات</a>' );
		return $links;
	}

	public static function user_phone_field( $user ) {
		$phone = get_user_meta( $user->ID, WBL_Auth::META_PHONE, true );
		?>
		<h2>ورود آسان — موبایل</h2>
		<table class="form-table">
			<tr>
				<th><label for="wbl_phone">شماره موبایل</label></th>
				<td>
					<input type="text" name="wbl_phone" id="wbl_phone" value="<?php echo esc_attr( $phone ); ?>" class="regular-text" dir="ltr" placeholder="09123456789" />
					<p class="description">برای ورود با OTP استفاده می‌شود.</p>
				</td>
			</tr>
		</table>
		<?php
	}

	public static function save_user_phone( $user_id ) {
		if ( ! current_user_can( 'edit_user', $user_id ) ) {
			return;
		}
		if ( ! isset( $_POST['wbl_phone'] ) ) { // phpcs:ignore
			return;
		}
		$raw = wp_unslash( $_POST['wbl_phone'] ); // phpcs:ignore
		if ( '' === trim( (string) $raw ) ) {
			delete_user_meta( $user_id, WBL_Auth::META_PHONE );
			return;
		}
		$norm = WBL_OTP::normalize_phone( $raw );
		if ( is_wp_error( $norm ) ) {
			return;
		}
		update_user_meta( $user_id, WBL_Auth::META_PHONE, $norm );
		update_user_meta( $user_id, 'billing_phone', $norm );
	}
}
