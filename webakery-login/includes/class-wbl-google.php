<?php
defined( 'ABSPATH' ) || exit;

/**
 * ورود با Google / Gmail (OAuth 2.0).
 */
class WBL_Google {

	const STATE_TRANSIENT = 'wbl_gstate_';

	public static function enabled() {
		return (int) WBL_Settings::get( 'enable_google', 1 )
			&& WBL_Settings::get( 'google_client_id' )
			&& WBL_Settings::get( 'google_client_secret' );
	}

	public static function redirect_uri() {
		return add_query_arg( 'wbl_google_callback', '1', home_url( '/' ) );
	}

	public static function auth_url() {
		if ( ! self::enabled() ) {
			return '';
		}
		$state = wp_generate_password( 32, false, false );
		set_transient( self::STATE_TRANSIENT . $state, 1, 10 * MINUTE_IN_SECONDS );

		$params = array(
			'client_id'     => WBL_Settings::get( 'google_client_id' ),
			'redirect_uri'  => self::redirect_uri(),
			'response_type' => 'code',
			'scope'         => 'openid email profile',
			'state'         => $state,
			'access_type'   => 'online',
			'prompt'        => 'select_account',
		);

		return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query( $params, '', '&', PHP_QUERY_RFC3986 );
	}

	public static function hooks() {
		add_action( 'init', array( __CLASS__, 'maybe_handle_callback' ), 5 );
	}

	public static function maybe_handle_callback() {
		if ( empty( $_GET['wbl_google_callback'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		$error_redirect = self::login_page_url();

		if ( ! empty( $_GET['error'] ) ) { // phpcs:ignore
			wp_safe_redirect( add_query_arg( 'wbl_error', rawurlencode( 'ورود با گوگل لغو شد.' ), $error_redirect ) );
			exit;
		}

		$state = isset( $_GET['state'] ) ? sanitize_text_field( wp_unslash( $_GET['state'] ) ) : ''; // phpcs:ignore
		if ( ! $state || ! get_transient( self::STATE_TRANSIENT . $state ) ) {
			wp_safe_redirect( add_query_arg( 'wbl_error', rawurlencode( 'خطای امنیتی. دوباره تلاش کنید.' ), $error_redirect ) );
			exit;
		}
		delete_transient( self::STATE_TRANSIENT . $state );

		$code = isset( $_GET['code'] ) ? sanitize_text_field( wp_unslash( $_GET['code'] ) ) : ''; // phpcs:ignore
		if ( ! $code ) {
			wp_safe_redirect( add_query_arg( 'wbl_error', rawurlencode( 'کد تأیید گوگل دریافت نشد.' ), $error_redirect ) );
			exit;
		}

		$token = self::exchange_code( $code );
		if ( is_wp_error( $token ) ) {
			wp_safe_redirect( add_query_arg( 'wbl_error', rawurlencode( $token->get_error_message() ), $error_redirect ) );
			exit;
		}

		$info = self::fetch_userinfo( $token );
		if ( is_wp_error( $info ) ) {
			wp_safe_redirect( add_query_arg( 'wbl_error', rawurlencode( $info->get_error_message() ), $error_redirect ) );
			exit;
		}

		$result = WBL_Auth::login_by_google( $info );
		if ( is_wp_error( $result ) ) {
			wp_safe_redirect( add_query_arg( 'wbl_error', rawurlencode( $result->get_error_message() ), $error_redirect ) );
			exit;
		}

		wp_safe_redirect( $result['redirect'] );
		exit;
	}

	/**
	 * @param string $code
	 * @return string|WP_Error access_token
	 */
	private static function exchange_code( $code ) {
		$resp = wp_remote_post(
			'https://oauth2.googleapis.com/token',
			array(
				'timeout' => 15,
				'body'    => array(
					'code'          => $code,
					'client_id'     => WBL_Settings::get( 'google_client_id' ),
					'client_secret' => WBL_Settings::get( 'google_client_secret' ),
					'redirect_uri'  => self::redirect_uri(),
					'grant_type'    => 'authorization_code',
				),
			)
		);

		if ( is_wp_error( $resp ) ) {
			return new WP_Error( 'wbl_google_token', 'ارتباط با گوگل برقرار نشد.' );
		}

		$data  = json_decode( wp_remote_retrieve_body( $resp ), true );
		$token = is_array( $data ) ? ( $data['access_token'] ?? '' ) : '';
		if ( ! $token ) {
			return new WP_Error( 'wbl_google_token', 'توکن دسترسی از گوگل دریافت نشد.' );
		}
		return $token;
	}

	/**
	 * @param string $token
	 * @return array|WP_Error
	 */
	private static function fetch_userinfo( $token ) {
		$resp = wp_remote_get(
			'https://www.googleapis.com/oauth2/v2/userinfo',
			array(
				'timeout' => 15,
				'headers' => array( 'Authorization' => 'Bearer ' . $token ),
			)
		);
		if ( is_wp_error( $resp ) ) {
			return new WP_Error( 'wbl_google_info', 'دریافت اطلاعات کاربر از گوگل ناموفق بود.' );
		}
		$data = json_decode( wp_remote_retrieve_body( $resp ), true );
		if ( empty( $data['email'] ) ) {
			return new WP_Error( 'wbl_google_info', 'ایمیل از گوگل دریافت نشد.' );
		}
		return array(
			'id'          => $data['id'] ?? '',
			'email'       => $data['email'],
			'verified'    => ! empty( $data['verified_email'] ),
			'name'        => $data['name'] ?? '',
			'given_name'  => $data['given_name'] ?? '',
			'family_name' => $data['family_name'] ?? '',
			'picture'     => $data['picture'] ?? '',
		);
	}

	public static function login_page_url() {
		$page_id = (int) WBL_Settings::get( 'login_page_id', 0 );
		if ( $page_id ) {
			$url = get_permalink( $page_id );
			if ( $url ) {
				return $url;
			}
		}
		return wp_login_url();
	}
}
