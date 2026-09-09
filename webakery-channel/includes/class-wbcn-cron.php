<?php
defined( 'ABSPATH' ) || exit;

class WBCN_Cron {

	const DAILY = 'wbcn_daily_idea';
	const POLL  = 'wbcn_poll_updates';

	public static function register() {
		add_action( self::DAILY, array( __CLASS__, 'run_daily' ) );
		add_action( self::POLL, array( __CLASS__, 'run_poll' ) );
		add_filter( 'cron_schedules', array( __CLASS__, 'schedules' ) );
	}

	public static function schedules( $schedules ) {
		if ( ! isset( $schedules['wbcn_five_min'] ) ) {
			$schedules['wbcn_five_min'] = array(
				'interval' => 300,
				'display'  => 'هر ۵ دقیقه (کانال‌یار)',
			);
		}
		return $schedules;
	}

	public static function sync() {
		$s = WBCN_Settings::get();
		self::unschedule( self::DAILY );
		self::unschedule( self::POLL );

		if ( ! empty( $s['daily_ideas'] ) && WBCN_Plugin::licensed() ) {
			$hour = (int) $s['daily_hour'];
			$ts   = self::next_hour_ts( $hour );
			wp_schedule_event( $ts, 'daily', self::DAILY );
		}
		if ( ! empty( $s['polling'] ) && WBCN_Plugin::licensed() ) {
			wp_schedule_event( time() + 60, 'wbcn_five_min', self::POLL );
		}
	}

	public static function unschedule( $hook ) {
		$ts = wp_next_scheduled( $hook );
		while ( $ts ) {
			wp_unschedule_event( $ts, $hook );
			$ts = wp_next_scheduled( $hook );
		}
	}

	public static function next_hour_ts( $hour ) {
		$tz = function_exists( 'wp_timezone' ) ? wp_timezone() : new DateTimeZone( 'Asia/Tehran' );
		$now = new DateTime( 'now', $tz );
		$run = clone $now;
		$run->setTime( (int) $hour, 5, 0 );
		if ( $run <= $now ) {
			$run->modify( '+1 day' );
		}
		return $run->getTimestamp();
	}

	public static function run_daily() {
		if ( ! WBCN_Plugin::licensed() ) {
			return;
		}
		$s = WBCN_Settings::get();
		if ( empty( $s['daily_ideas'] ) ) {
			return;
		}
		$item = WBCN_Content::pick_for_daily();
		if ( ! $item ) {
			return;
		}
		$weekday = (int) wp_date( 'w' ); // 0 Sunday
		$map     = array(
			6 => 'tip',
			0 => 'question',
			1 => 'checklist',
			2 => 'mistake',
			3 => 'summary',
			4 => 'quote',
			5 => 'cta',
		);
		$format = $map[ $weekday ] ?? 'tip';
		$chan   = ! empty( $s['signature'] ) ? WBCN_Settings::channel_username( $s ) : '';
		$built  = WBCN_Ideas::build( $item, $format, $chan );
		$res    = WBCN_Telegram::send_parts( $built['parts'], '', $s );
		if ( ! is_wp_error( $res ) ) {
			WBCN_Content::mark_daily_used( (int) $item['id'] );
			self::log( 'ایده روزانه ارسال شد: ' . $item['title'] );
		} else {
			self::log( 'خطای ایده روزانه: ' . $res->get_error_message() );
		}
	}

	public static function run_poll() {
		if ( ! WBCN_Plugin::licensed() ) {
			return;
		}
		$s = WBCN_Settings::get();
		if ( empty( $s['polling'] ) ) {
			return;
		}
		$offset = (int) get_option( 'wbcn_update_offset', 0 );
		$ups    = WBCN_Telegram::get_updates( $offset, $s );
		if ( is_wp_error( $ups ) ) {
			self::log( 'خطای polling: ' . $ups->get_error_message() );
			return;
		}
		$max = $offset;
		foreach ( $ups as $u ) {
			$id = (int) ( $u['update_id'] ?? 0 );
			if ( $id >= $max ) {
				$max = $id + 1;
			}
			WBCN_Webhook::handle_update( $u, $s );
		}
		if ( $max > $offset ) {
			update_option( 'wbcn_update_offset', $max, false );
		}
	}

	public static function log( $msg ) {
		$log   = (array) get_option( 'wbcn_log', array() );
		array_unshift(
			$log,
			array(
				't' => time(),
				'm' => (string) $msg,
			)
		);
		$log = array_slice( $log, 0, 40 );
		update_option( 'wbcn_log', $log, false );
	}
}
