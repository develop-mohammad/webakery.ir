<?php
defined( 'ABSPATH' ) || exit;

/**
 * بازبینی خودکار صفحه‌های تخفیف؛ برای گرفتن شروع/پایان تخفیف
 * زمان‌بندی‌شده ووکامرس و همگام‌سازی در صورت تغییر قیمت خارج از مسیر ذخیره.
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

		// مهاجرت از زمان‌بندی ساعتی قدیمی به هر ۱۵ دقیقه.
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

		$count = WDP_Assigner::recalculate_all();

		$log = get_option( 'wdp_log', array() );
		$log = is_array( $log ) ? $log : array();
		array_unshift(
			$log,
			array(
				'time'  => time(),
				'count' => $count,
			)
		);
		update_option( 'wdp_log', array_slice( $log, 0, 20 ), false );
	}
}
