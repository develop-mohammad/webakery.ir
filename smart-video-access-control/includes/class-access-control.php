<?php
defined( 'ABSPATH' ) || exit;

final class SVAC_Access_Control {
	public static function init(): void {
		add_filter( 'the_content', array( __CLASS__, 'replace_video_shortcodes' ), 8 );
	}

	public static function can_access( int $video_id, ?int $user_id = null ): array {
		$video = get_post( $video_id );
		if ( ! $video || 'svac_video' !== $video->post_type || 'publish' !== $video->post_status ) {
			return array(
				'allowed' => false,
				'reason'  => 'not_found',
			);
		}
		$user_id = null === $user_id ? get_current_user_id() : $user_id;
		if ( $user_id && user_can( $user_id, 'manage_options' ) ) {
			return array(
				'allowed' => true,
				'reason'  => 'administrator',
			);
		}
		$meta = SVAC_Video_Post_Type::get_meta( $video_id );
		$now  = new DateTimeImmutable( 'now', wp_timezone() );
		if ( $meta['start_at'] && $now < new DateTimeImmutable( $meta['start_at'], wp_timezone() ) ) {
			return array(
				'allowed' => false,
				'reason'  => 'not_started',
			);
		}
		if ( $meta['end_at'] && $now > new DateTimeImmutable( $meta['end_at'], wp_timezone() ) ) {
			return array(
				'allowed' => false,
				'reason'  => 'expired',
			);
		}
		if ( in_array( $user_id, $meta['blocked_users'], true ) ) {
			return array(
				'allowed' => false,
				'reason'  => 'blocked',
			);
		}
		if ( in_array( $user_id, $meta['allowed_users'], true ) ) {
			return array(
				'allowed' => true,
				'reason'  => 'allowed_user',
			);
		}
		$roles = self::get_effective_roles( $video_id, $meta['allowed_roles'] );
		if ( empty( $roles ) ) {
			return array(
				'allowed' => true,
				'reason'  => 'public',
			);
		}
		$user = $user_id ? get_userdata( $user_id ) : false;
		if ( $user && array_intersect( $roles, (array) $user->roles ) ) {
			return array(
				'allowed' => true,
				'reason'  => 'allowed_role',
			);
		}
		return array(
			'allowed' => false,
			'reason'  => $user_id ? 'role_required' : 'login_required',
		);
	}

	public static function message( string $reason ): string {
		$settings = get_option( 'svac_settings', array() );
		$messages = array(
			'not_started'    => $settings['message_not_started'] ?? __( 'این ویدیو هنوز در دسترس نیست.', 'smart-video-access-control' ),
			'expired'        => $settings['message_expired'] ?? __( 'دوره دسترسی شما به پایان رسیده است.', 'smart-video-access-control' ),
			'login_required' => $settings['message_login'] ?? __( 'برای مشاهده این ویدیو وارد حساب کاربری خود شوید.', 'smart-video-access-control' ),
			'blocked'        => $settings['message_denied'] ?? __( 'شما اجازه مشاهده این ویدیو را ندارید.', 'smart-video-access-control' ),
			'role_required'  => $settings['message_denied'] ?? __( 'شما اجازه مشاهده این ویدیو را ندارید.', 'smart-video-access-control' ),
			'not_found'      => __( 'ویدیوی درخواستی یافت نشد.', 'smart-video-access-control' ),
		);
		return wp_kses_post( $messages[ $reason ] ?? $messages['role_required'] );
	}

	public static function replace_video_shortcodes( string $content ): string {
		return $content;
	}

	private static function get_effective_roles( int $video_id, array $video_roles ): array {
		if ( ! empty( $video_roles ) ) {
			return $video_roles;
		}
		$roles = array();
		$terms = get_the_terms( $video_id, 'svac_video_category' );
		if ( is_array( $terms ) ) {
			foreach ( $terms as $term ) {
				$term_roles = get_term_meta( $term->term_id, '_svac_allowed_roles', true );
				if ( is_array( $term_roles ) ) {
					$roles = array_merge( $roles, $term_roles );
				}
			}
		}
		return array_values( array_unique( array_filter( array_map( 'sanitize_key', $roles ) ) ) );
	}
}
