<?php
defined( 'ABSPATH' ) || exit;

class RZM_Ajax {

	public static function hooks() {
		$actions = array(
			'rzm_get_state',
			'rzm_save_day',
			'rzm_auto_plan',
			'rzm_save_routines',
			'rzm_save_prefs',
		);
		foreach ( $actions as $action ) {
			add_action( 'wp_ajax_' . $action, array( __CLASS__, str_replace( 'rzm_', 'handle_', $action ) ) );
			add_action( 'wp_ajax_nopriv_' . $action, array( __CLASS__, 'require_login' ) );
		}
	}

	public static function require_login() {
		wp_send_json_error( array( 'message' => 'برای ذخیره برنامه وارد حساب شوید.' ), 401 );
	}

	public static function handle_get_state() {
		self::guard();
		$user_id = get_current_user_id();
		$date    = RZM_Planner::sanitize_date( isset( $_POST['date'] ) ? wp_unslash( $_POST['date'] ) : '' );
		wp_send_json_success(
			array(
				'day'      => RZM_Planner::get_day( $user_id, $date ),
				'routines' => RZM_Planner::get_routines( $user_id ),
				'prefs'    => RZM_Settings::user_prefs( $user_id ),
				'guest'    => false,
			)
		);
	}

	public static function handle_save_day() {
		self::guard();
		$user_id = get_current_user_id();
		$date    = RZM_Planner::sanitize_date( isset( $_POST['date'] ) ? wp_unslash( $_POST['date'] ) : '' );
		$payload = self::json_field( 'day' );
		$result  = RZM_Planner::save_day( $user_id, $date, $payload );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
		}
		wp_send_json_success( array( 'day' => $result ) );
	}

	public static function handle_auto_plan() {
		self::guard();
		$user_id  = get_current_user_id();
		$date     = RZM_Planner::sanitize_date( isset( $_POST['date'] ) ? wp_unslash( $_POST['date'] ) : '' );
		$payload  = self::json_field( 'day' );
		$day      = RZM_Planner::normalize_public_day( $payload, $date );
		$routines = RZM_Planner::get_routines( $user_id );
		$prefs    = RZM_Settings::user_prefs( $user_id );
		$planned  = RZM_Planner::auto_plan( $day, $routines, $prefs );
		$saved    = RZM_Planner::save_day( $user_id, $date, $planned );
		if ( is_wp_error( $saved ) ) {
			wp_send_json_error( array( 'message' => $saved->get_error_message() ), 400 );
		}
		wp_send_json_success( array( 'day' => $saved ) );
	}

	public static function handle_save_routines() {
		self::guard();
		$user_id  = get_current_user_id();
		$routines = self::json_field( 'routines' );
		if ( ! isset( $routines[0] ) && ! empty( $routines ) ) {
			$routines = array_values( $routines );
		}
		$result = RZM_Planner::save_routines( $user_id, is_array( $routines ) ? $routines : array() );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
		}
		wp_send_json_success( array( 'routines' => $result ) );
	}

	public static function handle_save_prefs() {
		self::guard();
		$user_id = get_current_user_id();
		$prefs   = self::json_field( 'prefs' );
		$saved   = RZM_Settings::save_user_prefs( $user_id, is_array( $prefs ) ? $prefs : array() );
		wp_send_json_success( array( 'prefs' => $saved ) );
	}

	private static function guard() {
		if ( ! RZM_Plugin::is_usable() ) {
			wp_send_json_error( array( 'message' => 'لایسنس روزم فعال نیست.' ), 403 );
		}
		check_ajax_referer( 'rzm_nonce', 'nonce' );
		if ( ! is_user_logged_in() ) {
			self::require_login();
		}
	}

	private static function json_field( $key ) {
		$raw = isset( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : '';
		if ( is_array( $raw ) ) {
			return $raw;
		}
		$data = json_decode( (string) $raw, true );
		return is_array( $data ) ? $data : array();
	}
}
