<?php
defined( 'ABSPATH' ) || exit;

final class SVAC_Video_Post_Type {
	public static function init(): void {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_boxes' ) );
		add_action( 'save_post_svac_video', array( __CLASS__, 'save_meta' ) );
	}

	public static function register(): void {
		register_post_type(
			'svac_video',
			array(
				'labels'       => array(
					'name'          => __( 'ویدیوهای محافظت‌شده', 'smart-video-access-control' ),
					'singular_name' => __( 'ویدیوی محافظت‌شده', 'smart-video-access-control' ),
					'add_new_item'  => __( 'افزودن ویدیوی جدید', 'smart-video-access-control' ),
					'edit_item'     => __( 'ویرایش ویدیو', 'smart-video-access-control' ),
				),
				'public'       => false,
				'show_ui'      => true,
				'show_in_menu' => 'svac-settings',
				'supports'     => array( 'title' ),
				'show_in_rest' => true,
				'menu_icon'    => 'dashicons-video-alt3',
			)
		);
		register_taxonomy(
			'svac_video_category',
			'svac_video',
			array(
				'labels'            => array(
					'name'          => __( 'دسته‌های ویدیو', 'smart-video-access-control' ),
					'singular_name' => __( 'دسته ویدیو', 'smart-video-access-control' ),
				),
				'public'            => false,
				'show_ui'           => true,
				'show_admin_column' => true,
				'show_in_rest'      => true,
				'hierarchical'      => true,
			)
		);
	}

	public static function add_meta_boxes(): void {
		add_meta_box( 'svac_video_rules', __( 'آدرس و قوانین دسترسی', 'smart-video-access-control' ), array( __CLASS__, 'render_meta_box' ), 'svac_video', 'normal', 'high' );
	}

	public static function render_meta_box( WP_Post $post ): void {
		wp_nonce_field( 'svac_save_video', 'svac_video_nonce' );
		$meta  = self::get_meta( $post->ID );
		$users = get_users( array( 'fields' => array( 'ID', 'display_name', 'user_login' ) ) );
		$roles = wp_roles()->roles;
		?>
		<p><label for="svac_video_url"><strong><?php esc_html_e( 'آدرس ویدیو', 'smart-video-access-control' ); ?></strong></label><br><input class="widefat" type="url" id="svac_video_url" name="svac_video_url" value="<?php echo esc_attr( $meta['url'] ); ?>" placeholder="https://youtu.be/... یا https://example.com/video.mp4"></p>
		<p class="description"><?php esc_html_e( 'YouTube، Vimeo، MP4 و نشانی‌های HTTPS خارجی پشتیبانی می‌شوند.', 'smart-video-access-control' ); ?></p>
		<table class="form-table" role="presentation"><tbody>
		<tr><th><label for="svac_allowed_roles"><?php esc_html_e( 'نقش‌های مجاز', 'smart-video-access-control' ); ?></label></th><td><select id="svac_allowed_roles" name="svac_allowed_roles[]" multiple size="5">
		<?php
		foreach ( $roles as $key => $role ) :
			?>
			<option value="<?php echo esc_attr( $key ); ?>" <?php selected( in_array( $key, $meta['allowed_roles'], true ) ); ?>><?php echo esc_html( translate_user_role( $role['name'] ) ); ?></option><?php endforeach; ?></select><p class="description"><?php esc_html_e( 'خالی یعنی نقش محدودیتی ندارد.', 'smart-video-access-control' ); ?></p></td></tr>
		<tr><th><label for="svac_allowed_users"><?php esc_html_e( 'کاربران مجاز', 'smart-video-access-control' ); ?></label></th><td><select id="svac_allowed_users" name="svac_allowed_users[]" multiple size="5">
		<?php
		foreach ( $users as $user ) :
			?>
			<option value="<?php echo esc_attr( $user->ID ); ?>" <?php selected( in_array( (int) $user->ID, $meta['allowed_users'], true ) ); ?>><?php echo esc_html( $user->display_name . ' (' . $user->user_login . ')' ); ?></option><?php endforeach; ?></select><p class="description"><?php esc_html_e( 'کاربران مجاز بر محدودیت نقش غلبه می‌کنند.', 'smart-video-access-control' ); ?></p></td></tr>
		<tr><th><label for="svac_blocked_users"><?php esc_html_e( 'کاربران مسدود', 'smart-video-access-control' ); ?></label></th><td><select id="svac_blocked_users" name="svac_blocked_users[]" multiple size="5">
		<?php
		foreach ( $users as $user ) :
			?>
			<option value="<?php echo esc_attr( $user->ID ); ?>" <?php selected( in_array( (int) $user->ID, $meta['blocked_users'], true ) ); ?>><?php echo esc_html( $user->display_name . ' (' . $user->user_login . ')' ); ?></option><?php endforeach; ?></select></td></tr>
		<tr><th><?php esc_html_e( 'بازه دسترسی', 'smart-video-access-control' ); ?></th><td><label><?php esc_html_e( 'شروع', 'smart-video-access-control' ); ?> <input type="datetime-local" name="svac_start_at" value="<?php echo esc_attr( $meta['start_at'] ); ?>"></label>&nbsp; <label><?php esc_html_e( 'پایان', 'smart-video-access-control' ); ?> <input type="datetime-local" name="svac_end_at" value="<?php echo esc_attr( $meta['end_at'] ); ?>"></label><p class="description"><?php esc_html_e( 'زمان‌ها با منطقه زمانی وردپرس ارزیابی می‌شوند.', 'smart-video-access-control' ); ?></p></td></tr>
		</tbody></table>
		<?php
	}

	public static function save_meta( int $post_id ): void {
		if ( ! isset( $_POST['svac_video_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['svac_video_nonce'] ) ), 'svac_save_video' ) || ! current_user_can( 'edit_post', $post_id ) || wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}
		$url = isset( $_POST['svac_video_url'] ) ? esc_url_raw( wp_unslash( $_POST['svac_video_url'] ) ) : '';
		if ( $url && ! wp_http_validate_url( $url ) ) {
			$url = '';
		}
		update_post_meta( $post_id, '_svac_video_url', $url );
		foreach ( array( 'allowed_users', 'blocked_users' ) as $field ) {
			$values = isset( $_POST[ 'svac_' . $field ] ) ? (array) map_deep( wp_unslash( $_POST[ 'svac_' . $field ] ), 'sanitize_text_field' ) : array();
			update_post_meta( $post_id, '_svac_' . $field, array_values( array_unique( array_filter( array_map( 'absint', $values ) ) ) ) );
		}
		$roles       = isset( $_POST['svac_allowed_roles'] ) ? (array) map_deep( wp_unslash( $_POST['svac_allowed_roles'] ), 'sanitize_key' ) : array();
		$valid_roles = array_keys( wp_roles()->roles );
		update_post_meta( $post_id, '_svac_allowed_roles', array_values( array_intersect( array_map( 'sanitize_key', $roles ), $valid_roles ) ) );
		foreach ( array( 'start_at', 'end_at' ) as $field ) {
			$value = isset( $_POST[ 'svac_' . $field ] ) ? sanitize_text_field( wp_unslash( $_POST[ 'svac_' . $field ] ) ) : '';
			update_post_meta( $post_id, '_svac_' . $field, self::validate_datetime( $value ) );
		}
	}

	public static function get_meta( int $video_id ): array {
		return array(
			'url'           => (string) get_post_meta( $video_id, '_svac_video_url', true ),
			'allowed_users' => self::meta_list( $video_id, '_svac_allowed_users', 'absint' ),
			'blocked_users' => self::meta_list( $video_id, '_svac_blocked_users', 'absint' ),
			'allowed_roles' => self::meta_list( $video_id, '_svac_allowed_roles', 'sanitize_key' ),
			'start_at'      => (string) get_post_meta( $video_id, '_svac_start_at', true ),
			'end_at'        => (string) get_post_meta( $video_id, '_svac_end_at', true ),
		);
	}

	/**
	 * A missing meta value reads back as an empty string, which must not become array( '' ):
	 * that would make an unset "blocked users" list match guests (user ID 0) and an unset
	 * "allowed roles" list look like a rule that nobody can satisfy.
	 */
	public static function meta_list( int $video_id, string $key, string $sanitizer ): array {
		$value = get_post_meta( $video_id, $key, true );
		if ( ! is_array( $value ) ) {
			return array();
		}
		return array_values( array_filter( array_map( $sanitizer, $value ) ) );
	}

	private static function validate_datetime( string $value ): string {
		if ( '' === $value ) {
			return '';
		}
		$date = DateTimeImmutable::createFromFormat( 'Y-m-d\TH:i', $value, wp_timezone() );
		return $date && $date->format( 'Y-m-d\TH:i' ) === $value ? $value : '';
	}
}
