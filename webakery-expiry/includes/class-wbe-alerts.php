<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * هشدار انقضای نزدیک: آلارم پیشخوان، ویجت، نوار بالا، ایمیل و پیامک.
 */
class WBE_Alerts {

	const TRANSIENT = 'wbe_alerts_cache';

	public static function register() {
		add_action( 'admin_notices', array( __CLASS__, 'admin_notice' ), 20 );
		add_action( 'wp_dashboard_setup', array( __CLASS__, 'widget' ) );
		add_action( 'admin_bar_menu', array( __CLASS__, 'admin_bar' ), 80 );
		add_action( 'admin_init', array( __CLASS__, 'maybe_dismiss' ) );
		add_action( 'admin_post_wbe_test_sms', array( __CLASS__, 'handle_test_sms' ) );
	}

	public static function thresholds() {
		$s = WBE_Settings::get();
		return array(
			'soon'  => max( 0, (int) $s['alert_soon_days'] ),
			'month' => max( 1, (int) $s['alert_month_days'] ),
			'two'   => max( 1, (int) $s['alert_two_month_days'] ),
		);
	}

	public static function flush() {
		delete_transient( self::TRANSIENT );
	}

	/**
	 * @return array{soon:array,month:array,two_months:array,expired:array,count:int}
	 */
	public static function groups() {
		$cached = get_transient( self::TRANSIENT );
		if ( is_array( $cached ) && isset( $cached['count'] ) ) {
			return $cached;
		}
		$t = self::thresholds();
		$groups = array(
			'soon'       => array(),
			'month'      => array(),
			'two_months' => array(),
			'expired'    => array(),
			'count'      => 0,
		);
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
					'urgency'   => 'expired',
				);
				continue;
			}
			$days = (int) floor( ( strtotime( $active['expiry'] . ' UTC' ) - strtotime( $today . ' UTC' ) ) / DAY_IN_SECONDS );
			$u    = WBE_Engine::urgency( $days, $t['soon'], $t['month'], $t['two'] );
			if ( '' === $u ) {
				continue;
			}
			$item = array(
				'id'        => $id,
				'name'      => $product->get_name(),
				'days'      => $days,
				'expiry_fa' => WBE_Jalali::format_ymd( $active['expiry'], $cal, true ),
				'stock'     => (int) $active['stock'],
				'urgency'   => $u,
			);
			$groups[ $u ][] = $item;
		}
		foreach ( array( 'soon', 'month', 'two_months', 'expired' ) as $k ) {
			usort(
				$groups[ $k ],
				function ( $a, $b ) {
					return (int) $a['days'] - (int) $b['days'];
				}
			);
		}
		$groups['count'] = count( $groups['soon'] ) + count( $groups['month'] ) + count( $groups['two_months'] ) + count( $groups['expired'] );
		set_transient( self::TRANSIENT, $groups, 10 * MINUTE_IN_SECONDS );
		return $groups;
	}

	public static function count() {
		$g = self::groups();
		return (int) $g['count'];
	}

	public static function labels() {
		return array(
			'soon'       => 'تا ۷ روز / فوری',
			'month'      => 'تا یک ماه',
			'two_months' => 'تا دو ماه',
			'expired'    => 'بدون بچ فعال',
		);
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
		$soon_n = count( $g['soon'] );
		$m_n    = count( $g['month'] );
		$t_n    = count( $g['two_months'] );
		$e_n    = count( $g['expired'] );
		$bits   = array();
		if ( $soon_n ) {
			$bits[] = $soon_n . ' فوری';
		}
		if ( $m_n ) {
			$bits[] = $m_n . ' تا یک ماه';
		}
		if ( $t_n ) {
			$bits[] = $t_n . ' تا دو ماه';
		}
		if ( $e_n ) {
			$bits[] = $e_n . ' بدون بچ فعال';
		}
		$preview = array_slice( array_merge( $g['soon'], $g['month'], $g['two_months'] ), 0, 5 );
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
		$g     = self::groups();
		$labels = self::labels();
		$t      = self::thresholds();
		$labels['soon']       = 'تا ' . (int) $t['soon'] . ' روز';
		$labels['month']      = 'تا یک ماه (' . (int) $t['month'] . ' روز)';
		$labels['two_months'] = 'تا دو ماه (' . (int) $t['two'] . ' روز)';
		if ( $g['count'] <= 0 ) {
			echo '<p>محصول نزدیک به انقضایی نیست.</p>';
			return;
		}
		echo '<div class="wbe-widget" dir="rtl">';
		foreach ( array( 'soon', 'month', 'two_months', 'expired' ) as $k ) {
			if ( empty( $g[ $k ] ) ) {
				continue;
			}
			echo '<p class="wbe-widget-h wbe-widget-h--' . esc_attr( $k ) . '">' . esc_html( $labels[ $k ] . ' — ' . count( $g[ $k ] ) . ' مورد' ) . '</p>';
			echo '<ul>';
			foreach ( array_slice( $g[ $k ], 0, 8 ) as $item ) {
				echo '<li><a href="' . esc_url( get_edit_post_link( $item['id'] ) ) . '">' . esc_html( $item['name'] ) . '</a>';
				if ( 'expired' !== $k ) {
					echo ' <span class="wbe-muted">' . esc_html( $item['expiry_fa'] . ' · ' . $item['days'] . ' روز · موجودی ' . $item['stock'] ) . '</span>';
				}
				echo '</li>';
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

	public static function digest_text() {
		$g = self::groups();
		if ( $g['count'] <= 0 ) {
			return '';
		}
		$lines = array( 'هشدار انقضای کالا (' . $g['count'] . ' مورد):' );
		$map   = array(
			'soon'       => 'فوری',
			'month'      => 'تا یک ماه',
			'two_months' => 'تا دو ماه',
			'expired'    => 'بدون بچ فعال',
		);
		foreach ( $map as $k => $label ) {
			if ( empty( $g[ $k ] ) ) {
				continue;
			}
			$names = array();
			foreach ( array_slice( $g[ $k ], 0, 5 ) as $item ) {
				$names[] = $item['name'] . ( isset( $item['days'] ) && $item['days'] >= 0 ? ' (' . $item['days'] . 'روز)' : '' );
			}
			$more   = count( $g[ $k ] ) > 5 ? ' و ' . ( count( $g[ $k ] ) - 5 ) . ' مورد دیگر' : '';
			$lines[] = $label . ': ' . implode( '، ', $names ) . $more;
		}
		return implode( "\n", $lines );
	}

	public static function notify_daily() {
		if ( ! WBE_Plugin::licensed() ) {
			return;
		}
		$today = WBE_Jalali::today_ymd();
		if ( get_option( 'wbe_last_notify_date' ) === $today ) {
			return;
		}
		$g = self::groups();
		if ( $g['count'] <= 0 ) {
			update_option( 'wbe_last_notify_date', $today, false );
			return;
		}
		$s    = WBE_Settings::get();
		$text = self::digest_text();
		if ( ! empty( $s['email_alert'] ) && $text ) {
			$to = trim( (string) $s['email_to'] );
			if ( $to === '' || ! is_email( $to ) ) {
				$to = get_option( 'admin_email' );
			}
			wp_mail( $to, 'هشدار انقضای کالا — ' . $g['count'] . ' مورد', $text );
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
