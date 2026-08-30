<?php
defined( 'ABSPATH' ) || exit;

/**
 * بازبینی پس‌زمینه صفحه‌های تخفیف (دسته‌ای، بدون قفل کردن پیشخوان).
 */
class WDP_Cron {

	const HOOK     = 'wdp_recalculate_all';
	const INTERVAL = 'wdp_every_fifteen_minutes';

	public static function register() {
		add_filter( 'cron_schedules', array( __CLASS__, 'schedules' ) );
		add_action( self::HOOK, array( __CLASS__, 'run' ) );
		add_action( 'init', array( __CLASS__, 'ensure_schedule' ) );
	}

	/**
	 * @param array $schedules
	 * @return array
	 */
	public static function schedules( $schedules ) {
		$schedules[ self::INTERVAL ] = array(
			'interval' => 15 * MINUTE_IN_SECONDS,
			'display'  => 'هر ۱۵ دقیقه (صفحه‌های تخفیف Webakery)',
		);
		return $schedules;
	}

	public static function ensure_schedule() {
		$next = wp_next_scheduled( self::HOOK );
		if ( ! $next ) {
			wp_schedule_event( time() + 120, self::INTERVAL, self::HOOK );
			return;
		}

		$crons = _get_cron_array();
		if ( ! is_array( $crons ) || empty( $crons[ $next ][ self::HOOK ] ) ) {
			return;
		}
		$event = reset( $crons[ $next ][ self::HOOK ] );
		if ( empty( $event['schedule'] ) || self::INTERVAL === $event['schedule'] ) {
			return;
		}
		wp_unschedule_event( $next, self::HOOK );
		wp_schedule_event( time() + 120, self::INTERVAL, self::HOOK );
	}

	public static function run() {
		if ( ! WDP_Plugin::woo_available() ) {
			return;
		}
		// فقط صف پس‌زمینه را پر می‌کند؛ پردازش در دسته‌های کوچک انجام می‌شود.
		WDP_Assigner::schedule_recalculate();
	}
}
