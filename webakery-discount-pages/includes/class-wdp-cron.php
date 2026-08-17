<?php
defined( 'ABSPATH' ) || exit;

/**
 * بازبینی خودکار ساعتی صفحه‌های تخفیف؛ برای گرفتن شروع/پایان تخفیف
 * زمان‌بندی‌شده ووکامرس، حتی وقتی کسی محصول را دستی باز و ذخیره نمی‌کند.
 */
class WDP_Cron {

	const HOOK = 'wdp_recalculate_all';

	public static function register() {
		add_action( self::HOOK, array( __CLASS__, 'run' ) );
		add_action( 'init', array( __CLASS__, 'ensure_schedule' ) );
	}

	public static function ensure_schedule() {
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time() + 300, 'hourly', self::HOOK );
		}
	}

	public static function run() {
		if ( ! WDP_Plugin::woo_available() || ! WDP_Plugin::licensed() ) {
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
