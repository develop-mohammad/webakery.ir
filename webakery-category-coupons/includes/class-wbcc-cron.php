<?php
defined( 'ABSPATH' ) || exit;

/**
 * ساخت خودکار زمان‌بندی‌شده کدهای تخفیف + پاک‌سازی کدهای منقضی.
 * یک رویداد ساعتی همه کمپین‌ها را بررسی می‌کند و هر کمپین بر اساس
 * بازه زمانی خودش (ساعتی/روزانه/هفتگی/ماهانه) اجرا می‌شود.
 */
class WBCC_Cron {

	const HOOK = 'wbcc_auto_run';

	public static function register() {
		add_action( self::HOOK, array( __CLASS__, 'run' ) );
		add_action( 'init', array( __CLASS__, 'ensure_schedule' ) );
	}

	public static function ensure_schedule() {
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time() + 300, 'hourly', self::HOOK );
		}
	}

	public static function intervals() {
		return array(
			'hourly'  => array( 'label' => 'هر ساعت', 'seconds' => HOUR_IN_SECONDS ),
			'daily'   => array( 'label' => 'روزانه', 'seconds' => DAY_IN_SECONDS ),
			'weekly'  => array( 'label' => 'هفتگی', 'seconds' => 7 * DAY_IN_SECONDS ),
			'monthly' => array( 'label' => 'ماهانه', 'seconds' => 30 * DAY_IN_SECONDS ),
		);
	}

	public static function interval_seconds( $key ) {
		$all = self::intervals();
		return isset( $all[ $key ] ) ? $all[ $key ]['seconds'] : DAY_IN_SECONDS;
	}

	public static function interval_label( $key ) {
		$all = self::intervals();
		return isset( $all[ $key ] ) ? $all[ $key ]['label'] : $key;
	}

	/** زمان اجرای بعدی خودکار یک کمپین */
	public static function next_run( array $campaign ) {
		if ( empty( $campaign['auto_enabled'] ) ) {
			return 0;
		}
		$last = (int) $campaign['last_run'];
		if ( ! $last ) {
			return time();
		}
		return $last + self::interval_seconds( $campaign['auto_interval'] );
	}

	/** اجرای دوره‌ای: کمپین‌های سررسیده را می‌سازد */
	public static function run() {
		if ( ! WBCC_Plugin::woo_available() || ! WBCC_Plugin::licensed() ) {
			return;
		}

		$log = array();
		foreach ( WBCC_Campaigns::all() as $campaign ) {
			if ( empty( $campaign['enabled'] ) || empty( $campaign['auto_enabled'] ) ) {
				continue;
			}
			$next = self::next_run( $campaign );
			if ( $next > time() ) {
				continue;
			}
			$res = WBCC_Generator::generate( $campaign, (int) $campaign['auto_count'], 'auto' );
			WBCC_Campaigns::touch_run( $campaign['id'] );
			$log[] = array(
				'time'     => time(),
				'campaign' => $campaign['name'],
				'count'    => count( $res['coupons'] ),
				'message'  => $res['message'],
			);
		}

		$settings = get_option( 'wbcc_settings', array() );
		if ( ! empty( $settings['cleanup_expired'] ) ) {
			$deleted = WBCC_Generator::cleanup_expired( (int) ( $settings['cleanup_days'] ?? 7 ) );
			if ( $deleted ) {
				$log[] = array(
					'time'     => time(),
					'campaign' => '—',
					'count'    => $deleted,
					'message'  => $deleted . ' کد منقضی‌شده پاک شد.',
				);
			}
		}

		if ( $log ) {
			$prev = get_option( 'wbcc_log', array() );
			$prev = is_array( $prev ) ? $prev : array();
			$all  = array_slice( array_merge( $log, $prev ), 0, 30 );
			update_option( 'wbcc_log', $all, false );
		}
	}
}
