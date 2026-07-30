<?php
defined( 'ABSPATH' ) || exit;

class WBL_Ajax {

	public static function hooks() {
		add_action( 'wp_ajax_nopriv_wbl_send_otp', array( __CLASS__, 'send_otp' ) );
		add_action( 'wp_ajax_wbl_send_otp', array( __CLASS__, 'send_otp' ) );
		add_action( 'wp_ajax_nopriv_wbl_verify_otp', array( __CLASS__, 'verify_otp' ) );
		add_action( 'wp_ajax_wbl_verify_otp', array( __CLASS__, 'verify_otp' ) );
		add_action( 'wp_ajax_wbl_logout', array( __CLASS__, 'logout' ) );
		add_action( 'wp_ajax_nopriv_wbl_test_sms', array( __CLASS__, 'forbid' ) );
		add_action( 'wp_ajax_wbl_test_sms', array( __CLASS__, 'test_sms' ) );
	}

	public static function forbid() {
		wp_send_json_error( array( 'message' => 'دسترسی ندارید.' ), 403 );
	}

	private static function check_nonce() {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : ''; // phpcs:ignore
		if ( ! wp_verify_nonce( $nonce, 'wbl_front' ) ) {
			wp_send_json_error( array( 'message' => 'نشست منقضی شده. صفحه را رفرش کنید.' ), 403 );
		}
	}

	public static function send_otp() {
		self::check_nonce();

		if ( ! WBL_Plugin::is_usable() ) {
			wp_send_json_error( array( 'message' => 'لایسنس افزونه فعال نیست.' ) );
		}
		if ( ! (int) WBL_Settings::get( 'enable_phone', 1 ) ) {
			wp_send_json_error( array( 'message' => 'ورود با موبایل غیرفعال است.' ) );
		}
		if ( is_user_logged_in() ) {
			wp_send_json_success(
				array(
					'message'  => 'قبلاً وارد شده‌اید.',
					'redirect' => WBL_Auth::redirect_url(),
				)
			);
		}

		$phone  = isset( $_POST['phone'] ) ? wp_unslash( $_POST['phone'] ) : ''; // phpcs:ignore
		$result = WBL_OTP::send( $phone );
		if ( is_wp_error( $result ) ) {
			$data = array( 'message' => $result->get_error_message() );
			$extra = $result->get_error_data();
			if ( is_array( $extra ) ) {
				$data = array_merge( $data, $extra );
			}
			wp_send_json_error( $data );
		}

		wp_send_json_success( $result );
	}

	public static function verify_otp() {
		self::check_nonce();

		if ( ! WBL_Plugin::is_usable() ) {
			wp_send_json_error( array( 'message' => 'لایسنس افزونه فعال نیست.' ) );
		}

		$phone = isset( $_POST['phone'] ) ? wp_unslash( $_POST['phone'] ) : ''; // phpcs:ignore
		$code  = isset( $_POST['code'] ) ? wp_unslash( $_POST['code'] ) : ''; // phpcs:ignore

		$check = WBL_OTP::verify( $phone, $code );
		if ( is_wp_error( $check ) ) {
			wp_send_json_error( array( 'message' => $check->get_error_message() ) );
		}

		$result = WBL_Auth::login_by_phone( $phone );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( $result );
	}

	public static function logout() {
		check_ajax_referer( 'wbl_front', 'nonce' );
		wp_logout();
		wp_send_json_success( array( 'redirect' => home_url( '/' ) ) );
	}

	public static function test_sms() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'دسترسی ندارید.' ), 403 );
		}
		check_ajax_referer( 'wbl_admin', 'nonce' );

		$phone = isset( $_POST['phone'] ) ? wp_unslash( $_POST['phone'] ) : ''; // phpcs:ignore
		$norm  = WBL_OTP::normalize_phone( $phone );
		if ( is_wp_error( $norm ) ) {
			wp_send_json_error( array( 'message' => $norm->get_error_message() ) );
		}

		$code   = WBL_OTP::generate_code();
		$result = WBL_SMS::send_otp( $norm, $code );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'message' => 'پیامک تست با کد ' . $code . ' ارسال شد.' ) );
	}
}
