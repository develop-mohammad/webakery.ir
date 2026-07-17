<?php
defined( 'ABSPATH' ) || exit;

class NM_Subscriptions {

	/** @var self|null */
	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {}

	public static function plans() {
		$plans = array(
			'monthly_4'  => array( 'label' => 'اشتراک ماهانه ۴ جلسه', 'credits' => 4, 'price' => 1800000, 'days' => 30 ),
			'monthly_8'  => array( 'label' => 'اشتراک ماهانه ۸ جلسه', 'credits' => 8, 'price' => 3200000, 'days' => 30 ),
		);
		return apply_filters( 'nm_subscription_plans', $plans );
	}

	public static function create( $email, $phone, $plan_key, $order_id = 0 ) {
		if ( ! NM_Pro::is_active() ) return NM_Pro::require_pro();
		$plans = self::plans();
		if ( empty( $plans[ $plan_key ] ) ) return new WP_Error( 'plan', 'پلن نامعتبر' );
		$p = $plans[ $plan_key ];
		global $wpdb;
		$now = current_time( 'mysql' );
		$exp = gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) + ( (int) $p['days'] * DAY_IN_SECONDS ) );
		$wpdb->insert( $wpdb->prefix . 'nm_subscriptions', array(
			'customer_email' => sanitize_email( $email ),
			'customer_phone' => sanitize_text_field( $phone ),
			'plan_key'       => $plan_key,
			'credits'        => (int) $p['credits'],
			'starts_at'      => $now,
			'expires_at'     => $exp,
			'status'         => 'active',
			'order_id'       => (int) $order_id,
		) );
		return (int) $wpdb->insert_id;
	}

	public static function consume_credit( $email ) {
		global $wpdb;
		$table = $wpdb->prefix . 'nm_subscriptions';
		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$table} WHERE customer_email = %s AND status = 'active' AND credits > 0 AND expires_at >= %s ORDER BY id ASC LIMIT 1",
			$email, current_time( 'mysql' )
		) );
		if ( ! $row ) return false;
		$wpdb->update( $table, array( 'credits' => max( 0, (int) $row->credits - 1 ) ), array( 'id' => $row->id ) );
		return true;
	}
}
