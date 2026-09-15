<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * هشدار انقضای نزدیک: آلارم پیشخوان، ویجت، نوار بالا، ایمیل و پیامک.
 */
class WBE_Alerts {

	const TRANSIENT = 'wbe_alerts_cache';
	const STATE     = 'wbe_notify_state';

	public static function register() {
		add_action( 'admin_notices', array( __CLASS__, 'admin_notice' ), 20 );
		add_action( 'wp_dashboard_setup', array( __CLASS__, 'widget' ) );
		add_action( 'admin_bar_menu', array( __CLASS__, 'admin_bar' ), 80 );
		add_action( 'admin_init', array( __CLASS__, 'maybe_dismiss' ) );
		add_action( 'admin_post_wbe_test_sms', array( __CLASS__, 'handle_test_sms' ) );
	}

	public static function thresholds() {
		$points = WBE_Settings::alert_points();
		$last   = $points ? (int) $points[ count( $points ) - 1 ] : 60;
		return array(
			'points' => $points,
			'soon'   => isset( $points[0] ) ? (int) $points[0] : 7,
			'month'  => isset( $points[1] ) ? (int) $points[1] : $last,
			'two'    => $last,
		);
	}

	public static function flush() {
		delete_transient( self::TRANSIENT );
	}

	/**
	 * @return array{points:array<int,array>,expired:array,count:int,order:int[]}
	 */
	public static function groups() {
		$cached = get_transient( self::TRANSIENT );
		if ( is_array( $cached ) && isset( $cached['count'], $cached['points'] ) ) {
			return $cached;
		}
		$points = WBE_Settings::alert_points();
		$groups = array(
			'points'  => array(),
			'expired' => array(),
			'count'   => 0,
			'order'   => $points,
		);
		foreach ( $points as $p ) {
			$groups['points'][ $p ] = array();
		}
		if ( ! function_exists( 'wc_get_product' ) ) {
			return $groups;
		}
		$today = WBE_Jalali::today_ymd();
		foreach ( WBE_Product::configured_ids() as $id ) {
			$product = wc_get_product( $id );
			if ( ! $product ) {
				continue;
			}
			$active = WBE_Product::active( $id );
			$cal    = WBE_Product::calendar( $id );
			if ( ! $active ) {
				$groups['expired'][] = array(
					'id'        => $id,
					'name'      => $product->get_name(),
					'days'      => -1,
					'expiry_fa' => '—',
					'stock'     => 0,
					'point'     => 'expired',
				);
				continue;
			}
			$days  = (int) floor( ( strtotime( $active['expiry'] . ' UTC' ) - strtotime( $today . ' UTC' ) ) / DAY_IN_SECONDS );
			$point = WBE_Engine::match_point( $days, $points );
			if ( null === $point ) {
				continue;
			}
			$item = array(
				'id'        => $id,
				'name'      => $product->get_name(),
				'days'      => $days,
				'expiry_fa' => WBE_Jalali::format_ymd( $active['expiry'], $cal, true ),
				'stock'     => (int) $active['stock'],
				'point'     => $point,
			);
			if ( 'expired' === $point ) {
				$groups['expired'][] = $item;
			} else {
				$groups['points'][ (int) $point ][] = $item;
			}
		}
		foreach ( $points as $p ) {
			usort(
				$groups['points'][ $p ],
				function ( $a, $b ) {
					return (int) $a['days'] - (int) $b['days'];
				}
			);
		}
		$n = count( $groups['expired'] );
		foreach ( $groups['points'] as $list ) {
			$n += count( $list );
		}
		$groups['count'] = $n;
		set_transient( self::TRANSIENT, $groups, 10 * MINUTE_IN_SECONDS );
		return $groups;
	}

	public static function count() {
		$g = self::groups();
		return (int) $g['count'];
	}

	public static function flat_items() {
		$g    = self::groups();
		$flat = array();
		foreach ( $g['order'] as $p ) {
			if ( ! empty( $g['points'][ $p ] ) ) {
				$flat = array_merge( $flat, $g['points'][ $p ] );
			}
		}
		return array_merge( $flat, $g['expired'] );
	}

	public static function admin_notice() {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! WBE_Plugin::licensed() ) {
			return;
		}
		$s = WBE_Settings::get();
		if ( empty( $s['dash_alarm'] ) ) {
			return;
		}
		if ( self::dismissed_today() ) {
			return;
		}
		$g = self::groups();
		if ( $g['count'] <= 0 ) {
			return;
		}
		$report = admin_url( 'admin.php?page=webakery-expiry&wbe_near=1' );
		$hide   = wp_nonce_url( add_query_arg( 'wbe_dismiss_alert', '1' ), 'wbe_dismiss_alert' );
		$bits   = array();
		foreach ( $g['order'] as $p ) {
			$n = isset( $g['points'][ $p ] ) ? count( $g['points'][ $p ] ) : 0;
			if ( $n ) {
				$bits[] = $n . ' مورد تا ' . $p . ' روز';
			}
		}
		if ( $g['expired'] ) {
			$bits[] = count( $g['expired'] ) . ' بدون بچ فعال';
		}
		$preview = array_slice( self::flat_items(), 0, 5 );
		echo '<div class="notice notice-warning wbe-alarm" dir="rtl"><p><strong>هشدار انقضای کالا:</strong> ';
		echo esc_html( implode( '، ', $bits ) );
		echo ' — <a href="' . esc_url( $report ) . '">گزارش</a>';
		echo ' · <a href="' . esc_url( $hide ) . '">امروز دیگر نشان نده</a></p>';
		if ( $preview ) {
			echo '<ul class="wbe-alarm-list">';
			foreach ( $preview as $item ) {
				$edit = get_edit_post_link( $item['id'] );
				echo '<li><a href="' . esc_url( $edit ) . '">' . esc_html( $item['name'] ) . '</a>';
				echo ' — ' . esc_html( $item['expiry_fa'] );
				echo ' (' . esc_html( (string) $item['days'] ) . ' روز)</li>';
			}
			echo '</ul>';
		}
		echo '</div>';
	}

	public static function widget() {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! WBE_Plugin::licensed() ) {
			return;
		}
		if ( empty( WBE_Settings::get()['dash_widget'] ) ) {
			return;
		}
		wp_add_dashboard_widget( 'wbe_expiry_widget', 'هشدار انقضای کالا', array( __CLASS__, 'render_widget' ) );
	}

	public static function render_widget() {
		$g = self::groups();
		if ( $g['count'] <= 0 ) {
			echo '<p>محصولی داخل بازه‌های هشدار شما نیست. آستانه‌ها را از تنظیمات انقضای کالا تغییر دهید.</p>';
			return;
		}
		echo '<div class="wbe-widget" dir="rtl">';
		foreach ( $g['order'] as $p ) {
			if ( empty( $g['points'][ $p ] ) ) {
				continue;
			}
			echo '<p class="wbe-widget-h">تا ' . esc_html( (string) $p ) . ' روز — ' . esc_html( (string) count( $g['points'][ $p ] ) ) . ' مورد</p>';
			echo '<ul>';
			foreach ( array_slice( $g['points'][ $p ], 0, 8 ) as $item ) {
				echo '<li><a href="' . esc_url( get_edit_post_link( $item['id'] ) ) . '">' . esc_html( $item['name'] ) . '</a>';
				echo ' <span class="wbe-muted">' . esc_html( $item['expiry_fa'] . ' · ' . $item['days'] . ' روز · موجودی ' . $item['stock'] ) . '</span></li>';
			}
			echo '</ul>';
		}
		if ( $g['expired'] ) {
			echo '<p class="wbe-widget-h wbe-widget-h--expired">بدون بچ فعال — ' . esc_html( (string) count( $g['expired'] ) ) . ' مورد</p>';
			echo '<ul>';
			foreach ( array_slice( $g['expired'], 0, 8 ) as $item ) {
				echo '<li><a href="' . esc_url( get_edit_post_link( $item['id'] ) ) . '">' . esc_html( $item['name'] ) . '</a></li>';
			}
			echo '</ul>';
		}
		echo '<p><a class="button" href="' . esc_url( admin_url( 'admin.php?page=webakery-expiry&wbe_near=1' ) ) . '">گزارش کامل</a></p>';
		echo '</div>';
	}

	public static function admin_bar( $bar ) {
		if ( ! is_admin_bar_showing() || ! current_user_can( 'manage_woocommerce' ) || ! WBE_Plugin::licensed() ) {
			return;
		}
		$n = self::count();
		if ( $n <= 0 ) {
			return;
		}
		$bar->add_node(
			array(
				'id'    => 'wbe-alerts',
				'title' => 'انقضا: ' . $n . ' هشدار',
				'href'  => admin_url( 'admin.php?page=webakery-expiry&wbe_near=1' ),
				'meta'  => array( 'class' => 'wbe-ab-alert' ),
			)
		);
	}

	public static function maybe_dismiss() {
		if ( empty( $_GET['wbe_dismiss_alert'] ) || ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		check_admin_referer( 'wbe_dismiss_alert' );
		update_user_meta( get_current_user_id(), 'wbe_alert_dismissed', WBE_Jalali::today_ymd() );
		wp_safe_redirect( remove_query_arg( array( 'wbe_dismiss_alert', '_wpnonce' ) ) );
		exit;
	}

	private static function dismissed_today() {
		$d = (string) get_user_meta( get_current_user_id(), 'wbe_alert_dismissed', true );
		return $d === WBE_Jalali::today_ymd();
	}

	public static function digest_text( $items = null ) {
		if ( null === $items ) {
			$items = self::flat_items();
		}
		if ( empty( $items ) ) {
			return '';
		}
		$lines = array( 'هشدار انقضای کالا (' . count( $items ) . ' مورد):' );
		$by    = array();
		foreach ( $items as $item ) {
			$key = isset( $item['point'] ) ? $item['point'] : 'other';
			if ( ! isset( $by[ $key ] ) ) {
				$by[ $key ] = array();
			}
			$by[ $key ][] = $item;
		}
		foreach ( $by as $key => $list ) {
			$label = ( 'expired' === $key ) ? 'بدون بچ فعال' : ( 'تا ' . $key . ' روز' );
			$names = array();
			foreach ( array_slice( $list, 0, 5 ) as $item ) {
				$names[] = $item['name'] . ( isset( $item['days'] ) && (int) $item['days'] >= 0 ? ' (' . $item['days'] . 'روز)' : '' );
			}
			$more    = count( $list ) > 5 ? ' و ' . ( count( $list ) - 5 ) . ' مورد دیگر' : '';
			$lines[] = $label . ': ' . implode( '، ', $names ) . $more;
		}
		return implode( "\n", $lines );
	}

	/**
	 * در حالت on_point فقط محصولاتی که تازه وارد یک آستانه شده‌اند.
	 *
	 * @return array
	 */
	public static function due_for_send() {
		$g     = self::groups();
		$s     = WBE_Settings::get();
		$items = self::flat_items();
		if ( 'daily' === $s['notify_mode'] ) {
			return $items;
		}
		$state = get_option( self::STATE, array() );
		if ( ! is_array( $state ) ) {
			$state = array();
		}
		$due     = array();
		$current = array();
		foreach ( $items as $item ) {
			$id    = (int) $item['id'];
			$point = (string) $item['point'];
			$current[ $id ] = $point;
			$prev = isset( $state[ $id ] ) ? (string) $state[ $id ] : '';
			if ( $prev !== $point ) {
				$due[] = $item;
			}
		}
		update_option( self::STATE, $current, false );
		return $due;
	}

	public static function notify_daily() {
		if ( ! WBE_Plugin::licensed() ) {
			return;
		}
		$today = WBE_Jalali::today_ymd();
		if ( get_option( 'wbe_last_notify_date' ) === $today ) {
			return;
		}
		$items = self::due_for_send();
		if ( empty( $items ) ) {
			update_option( 'wbe_last_notify_date', $today, false );
			return;
		}
		$s    = WBE_Settings::get();
		$text = self::digest_text( $items );
		if ( ! empty( $s['email_alert'] ) && $text ) {
			$to = trim( (string) $s['email_to'] );
			if ( $to === '' || ! is_email( $to ) ) {
				$to = get_option( 'admin_email' );
			}
			wp_mail( $to, 'هشدار انقضای کالا — ' . count( $items ) . ' مورد', $text );
		}
		if ( ! empty( $s['sms_alert'] ) && $text ) {
			WBE_SMS::send( $s['sms_phone'], $text );
		}
		update_option( 'wbe_last_notify_date', $today, false );
	}

	public static function handle_test_sms() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( 'دسترسی غیرمجاز' );
		}
		check_admin_referer( 'wbe_test_sms' );
		$s   = WBE_Settings::get();
		$res = WBE_SMS::send( $s['sms_phone'], 'تست هشدار انقضای کالا از webakery.ir' );
		$ok  = ! is_wp_error( $res );
		$msg = $ok ? 'sms_ok' : 'sms_err';
		$url = admin_url( 'admin.php?page=webakery-expiry-settings&wbe_sms=' . $msg );
		if ( ! $ok ) {
			$url .= '&wbe_sms_err=' . rawurlencode( $res->get_error_message() );
		}
		wp_safe_redirect( $url );
		exit;
	}
}
