<?php
defined( 'ABSPATH' ) || exit;

final class SVAC_REST_API {
	public static function init(): void {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	public static function register_routes(): void {
		register_rest_route(
			'svac/v1',
			'/videos/(?P<id>\d+)/access',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_access' ),
				'permission_callback' => array( __CLASS__, 'authenticated_user' ),
				'args'                => array( 'id' => array( 'validate_callback' => array( __CLASS__, 'validate_video_id' ) ) ),
			)
		);
		register_rest_route(
			'svac/v1',
			'/videos/(?P<id>\d+)/check',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'check_access' ),
				'permission_callback' => array( __CLASS__, 'authenticated_user' ),
				'args'                => array( 'id' => array( 'validate_callback' => array( __CLASS__, 'validate_video_id' ) ) ),
			)
		);
	}

	public static function authenticated_user(): bool {
		return is_user_logged_in() && current_user_can( 'read' );
	}

	/**
	 * REST validation callbacks receive ( $value, $request, $param ), so a bare is_numeric()
	 * would blow up with an ArgumentCountError.
	 */
	public static function validate_video_id( $value ): bool {
		return is_numeric( $value ) && absint( $value ) > 0;
	}

	public static function get_access( WP_REST_Request $request ): WP_REST_Response {
		$result = SVAC_Access_Control::can_access( absint( $request['id'] ) );
		return new WP_REST_Response(
			array(
				'video_id' => absint( $request['id'] ),
				'allowed'  => $result['allowed'],
				'reason'   => $result['reason'],
				'message'  => SVAC_Access_Control::message( $result['reason'] ),
			)
		);
	}

	public static function check_access( WP_REST_Request $request ): WP_REST_Response {
		$video_id = absint( $request['id'] );
		$result   = SVAC_Access_Control::can_access( $video_id );
		if ( 'not_found' !== $result['reason'] ) {
			SVAC_Access_Logs::log( $video_id, $result['allowed'] );
		}
		return new WP_REST_Response(
			array(
				'video_id' => $video_id,
				'allowed'  => $result['allowed'],
				'reason'   => $result['reason'],
				'message'  => SVAC_Access_Control::message( $result['reason'] ),
			)
		);
	}
}
