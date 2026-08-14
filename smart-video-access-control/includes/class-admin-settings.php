<?php
defined( 'ABSPATH' ) || exit;

final class SVAC_Admin_Settings {
	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_post_svac_export_logs', array( __CLASS__, 'export_logs' ) );
		add_action( 'admin_post_svac_save_category_rules', array( __CLASS__, 'save_category_rules' ) );
	}

	public static function menu(): void {
		add_menu_page( __( 'Smart Video Control', 'smart-video-access-control' ), __( 'کنترل ویدیو', 'smart-video-access-control' ), 'manage_options', 'svac-settings', array( __CLASS__, 'render' ), 'dashicons-video-alt3', 58 );
		add_submenu_page( 'svac-settings', __( 'تنظیمات', 'smart-video-access-control' ), __( 'تنظیمات', 'smart-video-access-control' ), 'manage_options', 'svac-settings', array( __CLASS__, 'render' ) );
	}

	public static function register_settings(): void {
		register_setting( 'svac_settings_group', 'svac_settings', array( 'sanitize_callback' => array( __CLASS__, 'sanitize_settings' ) ) );
	}

	public static function sanitize_settings( $settings ): array {
		$settings = is_array( $settings ) ? $settings : array();
		return array(
			'message_denied'      => isset( $settings['message_denied'] ) ? sanitize_textarea_field( $settings['message_denied'] ) : '',
			'message_login'       => isset( $settings['message_login'] ) ? sanitize_textarea_field( $settings['message_login'] ) : '',
			'message_expired'     => isset( $settings['message_expired'] ) ? sanitize_textarea_field( $settings['message_expired'] ) : '',
			'message_not_started' => isset( $settings['message_not_started'] ) ? sanitize_textarea_field( $settings['message_not_started'] ) : '',
		);
	}

	public static function assets( string $hook ): void {
		if ( false === strpos( $hook, 'svac' ) ) { return; }
		wp_enqueue_style( 'svac-admin', SVAC_URL . 'admin/css/admin.css', array(), SVAC_VERSION );
		wp_enqueue_script( 'svac-admin', SVAC_URL . 'admin/js/admin.js', array(), SVAC_VERSION, true );
	}

	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) { return; }
		$settings = get_option( 'svac_settings', array() );
		$roles    = wp_roles()->roles;
		$terms    = get_terms( array( 'taxonomy' => 'svac_video_category', 'hide_empty' => false ) );
		?>
		<div class="wrap svac-admin" dir="rtl">
			<h1><?php esc_html_e( 'Smart Video Control', 'smart-video-access-control' ); ?></h1>
			<nav class="nav-tab-wrapper"><a class="nav-tab nav-tab-active" href="#svac-general"><?php esc_html_e( 'تنظیمات عمومی و پیام‌ها', 'smart-video-access-control' ); ?></a><a class="nav-tab" href="#svac-rules"><?php esc_html_e( 'قوانین دسته‌بندی', 'smart-video-access-control' ); ?></a><a class="nav-tab" href="#svac-logs"><?php esc_html_e( 'گزارش دسترسی', 'smart-video-access-control' ); ?></a></nav>
			<section id="svac-general"><form method="post" action="options.php"><?php settings_fields( 'svac_settings_group' ); ?>
				<h2><?php esc_html_e( 'پیام‌ها', 'smart-video-access-control' ); ?></h2>
				<p><label><?php esc_html_e( 'عدم دسترسی', 'smart-video-access-control' ); ?><br><textarea name="svac_settings[message_denied]" rows="2" class="large-text"><?php echo esc_textarea( $settings['message_denied'] ?? '' ); ?></textarea></label></p>
				<p><label><?php esc_html_e( 'نیاز به ورود', 'smart-video-access-control' ); ?><br><textarea name="svac_settings[message_login]" rows="2" class="large-text"><?php echo esc_textarea( $settings['message_login'] ?? '' ); ?></textarea></label></p>
				<p><label><?php esc_html_e( 'انقضای دسترسی', 'smart-video-access-control' ); ?><br><textarea name="svac_settings[message_expired]" rows="2" class="large-text"><?php echo esc_textarea( $settings['message_expired'] ?? '' ); ?></textarea></label></p>
				<p><label><?php esc_html_e( 'شروع‌نشدن دسترسی', 'smart-video-access-control' ); ?><br><textarea name="svac_settings[message_not_started]" rows="2" class="large-text"><?php echo esc_textarea( $settings['message_not_started'] ?? '' ); ?></textarea></label></p>
				<?php submit_button(); ?></form></section>
			<section id="svac-rules" hidden><h2><?php esc_html_e( 'قوانین نقش برای دسته‌ها', 'smart-video-access-control' ); ?></h2><p><?php esc_html_e( 'وقتی برای خود ویدیو نقشی انتخاب نشده باشد، نقش‌های اینجا اعمال می‌شوند.', 'smart-video-access-control' ); ?></p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="svac_save_category_rules"><?php wp_nonce_field( 'svac_save_category_rules' ); ?>
				<table class="widefat striped"><thead><tr><th><?php esc_html_e( 'دسته', 'smart-video-access-control' ); ?></th><th><?php esc_html_e( 'نقش‌های مجاز', 'smart-video-access-control' ); ?></th></tr></thead><tbody><?php foreach ( $terms as $term ) : $selected = (array) get_term_meta( $term->term_id, '_svac_allowed_roles', true ); ?><tr><td><?php echo esc_html( $term->name ); ?></td><td><select name="roles[<?php echo esc_attr( (string) $term->term_id ); ?>][]" multiple size="4"><?php foreach ( $roles as $key => $role ) : ?><option value="<?php echo esc_attr( $key ); ?>" <?php selected( in_array( $key, $selected, true ) ); ?>><?php echo esc_html( translate_user_role( $role['name'] ) ); ?></option><?php endforeach; ?></select></td></tr><?php endforeach; ?></tbody></table><?php submit_button( __( 'ذخیره قوانین دسته‌ها', 'smart-video-access-control' ) ); ?></form></section>
			<section id="svac-logs" hidden><h2><?php esc_html_e( 'گزارش دسترسی اخیر', 'smart-video-access-control' ); ?></h2><p><a class="button button-secondary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=svac_export_logs' ), 'svac_export_logs' ) ); ?>"><?php esc_html_e( 'دریافت CSV', 'smart-video-access-control' ); ?></a></p><table class="widefat striped"><thead><tr><th>ID</th><th><?php esc_html_e( 'کاربر', 'smart-video-access-control' ); ?></th><th><?php esc_html_e( 'ویدیو', 'smart-video-access-control' ); ?></th><th><?php esc_html_e( 'زمان', 'smart-video-access-control' ); ?></th><th>IP</th><th><?php esc_html_e( 'وضعیت', 'smart-video-access-control' ); ?></th></tr></thead><tbody><?php foreach ( SVAC_Access_Logs::get_logs() as $log ) : ?><tr><td><?php echo esc_html( $log['id'] ); ?></td><td><?php echo esc_html( $log['user_id'] ? ( get_userdata( (int) $log['user_id'] )->display_name ?? $log['user_id'] ) : __( 'مهمان', 'smart-video-access-control' ) ); ?></td><td><?php echo esc_html( get_the_title( (int) $log['video_id'] ) ?: $log['video_id'] ); ?></td><td><?php echo esc_html( get_date_from_gmt( $log['access_time'], get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) ); ?></td><td><?php echo esc_html( $log['ip_address'] ); ?></td><td><?php echo esc_html( $log['status'] ); ?></td></tr><?php endforeach; ?></tbody></table></section>
		</div>
		<?php
	}

	public static function save_category_rules(): void {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'اجازه کافی ندارید.', 'smart-video-access-control' ) ); }
		check_admin_referer( 'svac_save_category_rules' );
		$roles = isset( $_POST['roles'] ) ? (array) wp_unslash( $_POST['roles'] ) : array();
		$valid = array_keys( wp_roles()->roles );
		foreach ( get_terms( array( 'taxonomy' => 'svac_video_category', 'hide_empty' => false ) ) as $term ) {
			$values = isset( $roles[ $term->term_id ] ) ? (array) $roles[ $term->term_id ] : array();
			update_term_meta( $term->term_id, '_svac_allowed_roles', array_values( array_intersect( array_map( 'sanitize_key', $values ), $valid ) ) );
		}
		wp_safe_redirect( admin_url( 'admin.php?page=svac-settings#svac-rules' ) );
		exit;
	}

	public static function export_logs(): void {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'اجازه کافی ندارید.', 'smart-video-access-control' ) ); }
		check_admin_referer( 'svac_export_logs' );
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=video-access-logs-' . gmdate( 'Y-m-d' ) . '.csv' );
		$output = fopen( 'php://output', 'w' );
		fputcsv( $output, array( 'id', 'user_id', 'video_id', 'access_time', 'ip_address', 'status' ) );
		foreach ( SVAC_Access_Logs::get_logs( 500 ) as $log ) { fputcsv( $output, $log ); }
		fclose( $output );
		exit;
	}
}
