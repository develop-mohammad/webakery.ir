<?php
defined( 'ABSPATH' ) || exit;

class RZM_Settings {

	const OPTION = 'rzm_settings';

	public static function defaults() {
		return array(
			'wake_time'     => '07:00',
			'sleep_time'    => '23:00',
			'break_minutes' => 10,
			'page_title'    => 'روزم',
		);
	}

	public static function get() {
		$saved = get_option( self::OPTION, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}
		return array_merge( self::defaults(), $saved );
	}

	public static function update( array $data ) {
		$current = self::get();
		$next    = array_merge( $current, $data );

		$next['wake_time']     = self::sanitize_time( $next['wake_time'], '07:00' );
		$next['sleep_time']    = self::sanitize_time( $next['sleep_time'], '23:00' );
		$next['break_minutes'] = max( 0, min( 60, absint( $next['break_minutes'] ) ) );
		$next['page_title']    = sanitize_text_field( $next['page_title'] );

		update_option( self::OPTION, $next, false );
		return $next;
	}

	public static function sanitize_time( $value, $fallback ) {
		$value = is_string( $value ) ? trim( $value ) : '';
		if ( ! preg_match( '/^\d{2}:\d{2}$/', $value ) ) {
			return $fallback;
		}
		list( $h, $m ) = array_map( 'intval', explode( ':', $value ) );
		if ( $h < 0 || $h > 23 || $m < 0 || $m > 59 ) {
			return $fallback;
		}
		return sprintf( '%02d:%02d', $h, $m );
	}

	public static function user_prefs( $user_id = 0 ) {
		$user_id = $user_id ? absint( $user_id ) : get_current_user_id();
		$site    = self::get();
		if ( ! $user_id ) {
			return array(
				'wake_time'     => $site['wake_time'],
				'sleep_time'    => $site['sleep_time'],
				'break_minutes' => (int) $site['break_minutes'],
			);
		}
		$prefs = get_user_meta( $user_id, 'rzm_prefs', true );
		if ( ! is_array( $prefs ) ) {
			$prefs = array();
		}
		return array(
			'wake_time'     => self::sanitize_time( isset( $prefs['wake_time'] ) ? $prefs['wake_time'] : $site['wake_time'], $site['wake_time'] ),
			'sleep_time'    => self::sanitize_time( isset( $prefs['sleep_time'] ) ? $prefs['sleep_time'] : $site['sleep_time'], $site['sleep_time'] ),
			'break_minutes' => isset( $prefs['break_minutes'] )
				? max( 0, min( 60, absint( $prefs['break_minutes'] ) ) )
				: (int) $site['break_minutes'],
		);
	}

	public static function save_user_prefs( $user_id, array $prefs ) {
		$user_id = absint( $user_id );
		if ( ! $user_id ) {
			return false;
		}
		$clean = array(
			'wake_time'     => self::sanitize_time( isset( $prefs['wake_time'] ) ? $prefs['wake_time'] : '07:00', '07:00' ),
			'sleep_time'    => self::sanitize_time( isset( $prefs['sleep_time'] ) ? $prefs['sleep_time'] : '23:00', '23:00' ),
			'break_minutes' => max( 0, min( 60, absint( isset( $prefs['break_minutes'] ) ? $prefs['break_minutes'] : 10 ) ) ),
		);
		update_user_meta( $user_id, 'rzm_prefs', $clean );
		return $clean;
	}
}
