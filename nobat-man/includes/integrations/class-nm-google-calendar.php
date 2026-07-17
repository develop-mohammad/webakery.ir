<?php
defined( 'ABSPATH' ) || exit;

class NM_Google_Calendar {

	/** @var self|null */
	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_post_nm_google_oauth', array( __CLASS__, 'oauth_start' ) );
		add_action( 'admin_post_nm_google_callback', array( __CLASS__, 'oauth_callback' ) );
	}

	public static function sync_booking( $booking ) {
		if ( ! NM_Pro::is_active() ) {
			return;
		}
		$token = self::access_token();
		if ( ! $token ) {
			return;
		}

		$sp     = $booking->specialist_id ? NM_Specialist::get( $booking->specialist_id ) : null;
		$cal_id = ( $sp && $sp->google_calendar_id ) ? $sp->google_calendar_id : 'primary';

		$start = $booking->g_date . 'T' . substr( $booking->start_time, 0, 8 );
		$end   = $booking->g_date . 'T' . substr( $booking->end_time, 0, 8 );
		$tz    = wp_timezone_string();

			$body = array(
			'summary'     => 'نوبت مشاوره — ' . $booking->customer_name,
			'description' => "کد: {$booking->booking_code}\nتلفن: {$booking->customer_phone}\n{$booking->description}",
			'start'       => array(
				'dateTime' => $start,
				'timeZone' => $tz,
			),
			'end'         => array(
				'dateTime' => $end,
				'timeZone' => $tz,
			),
			'reminders'   => array(
				'useDefault' => false,
				'overrides'  => array(
					array(
						'method'  => 'email',
						'minutes' => 60,
					),
					array(
						'method'  => 'popup',
						'minutes' => 30,
					),
				),
			),
		);
		if ( $booking->customer_email ) {
			$body['attendees'] = array(
				array( 'email' => $booking->customer_email ),
			);
		}

		$resp = wp_remote_post(
			'https://www.googleapis.com/calendar/v3/calendars/' . rawurlencode( $cal_id ) . '/events',
			array(
				'timeout' => 15,
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $body ),
			)
		);
		if ( is_wp_error( $resp ) ) {
			return;
		}
		$data = json_decode( wp_remote_retrieve_body( $resp ), true );
		if ( ! empty( $data['id'] ) ) {
			global $wpdb;
			$wpdb->update(
				$wpdb->prefix . 'nm_bookings',
				array( 'google_event_id' => $data['id'] ),
				array( 'id' => $booking->id )
			);
		}
	}

	public static function access_token() {
		$refresh = NM_Settings::get( 'google_refresh_token' );
		$cid     = NM_Settings::get( 'google_client_id' );
		$secret  = NM_Settings::get( 'google_client_secret' );
		if ( ! $refresh || ! $cid || ! $secret ) {
			return '';
		}

		$cached = get_transient( 'nm_gcal_token' );
		if ( $cached ) {
			return $cached;
		}

		$resp = wp_remote_post(
			'https://oauth2.googleapis.com/token',
			array(
				'timeout' => 15,
				'body'    => array(
					'client_id'     => $cid,
					'client_secret' => $secret,
					'refresh_token' => $refresh,
					'grant_type'    => 'refresh_token',
				),
			)
		);
		if ( is_wp_error( $resp ) ) {
			return '';
		}
		$data = json_decode( wp_remote_retrieve_body( $resp ), true );
		if ( empty( $data['access_token'] ) ) {
			return '';
		}
		set_transient(
			'nm_gcal_token',
			$data['access_token'],
			max( 60, (int) ( $data['expires_in'] ?? 3500 ) - 60 )
		);
		return $data['access_token'];
	}

	public static function oauth_start() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Forbidden' );
		}
		$cid      = NM_Settings::get( 'google_client_id' );
		$redirect = admin_url( 'admin-post.php?action=nm_google_callback' );
		$url      = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query(
			array(
				'client_id'     => $cid,
				'redirect_uri'  => $redirect,
				'response_type' => 'code',
				'scope'         => 'https://www.googleapis.com/auth/calendar.events',
				'access_type'   => 'offline',
				'prompt'        => 'consent',
			)
		);
		wp_redirect( $url );
		exit;
	}

	public static function oauth_callback() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Forbidden' );
		}
		$code = sanitize_text_field( wp_unslash( $_GET['code'] ?? '' ) );
		if ( ! $code ) {
			wp_safe_redirect( admin_url( 'admin.php?page=nobat-man&tab=integrations&gcal=error' ) );
			exit;
		}
		$resp = wp_remote_post(
			'https://oauth2.googleapis.com/token',
			array(
				'body' => array(
					'code'          => $code,
					'client_id'     => NM_Settings::get( 'google_client_id' ),
					'client_secret' => NM_Settings::get( 'google_client_secret' ),
					'redirect_uri'  => admin_url( 'admin-post.php?action=nm_google_callback' ),
					'grant_type'    => 'authorization_code',
				),
			)
		);
		$data = json_decode( wp_remote_retrieve_body( $resp ), true );
		if ( ! empty( $data['refresh_token'] ) ) {
			NM_Settings::update( array( 'google_refresh_token' => $data['refresh_token'] ) );
		}
		wp_safe_redirect( admin_url( 'admin.php?page=nobat-man&tab=integrations&gcal=ok' ) );
		exit;
	}
}
