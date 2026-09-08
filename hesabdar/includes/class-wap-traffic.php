<?php
defined( 'ABSPATH' ) || exit;

/**
 * ردیابی منبع ورود مشتری (ارگانیک گوگل / سوشال / مستقیم / سایر).
 * کوکی کوتاه‌عمر + ذخیره روی سفارش.
 */
class WAP_Traffic {

	const COOKIE = 'wap_traffic';
	const META_SOURCE = '_wap_traffic_source';
	const META_DETAIL = '_wap_traffic_detail';

	public static function init(): void {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'woocommerce_checkout_create_order', array( __CLASS__, 'attach_to_order' ), 20, 2 );
		add_action( 'woocommerce_store_api_checkout_update_order_meta', array( __CLASS__, 'attach_to_order_api' ), 20, 1 );
	}

	public static function enqueue(): void {
		if ( is_admin() ) {
			return;
		}
		wp_register_script( 'wap-traffic', false, array(), WAP_VERSION, true );
		wp_enqueue_script( 'wap-traffic' );
		$js = <<<'JS'
(function(){
  try {
    var params = new URLSearchParams(window.location.search || '');
    var utm = (params.get('utm_source') || '') + '|' + (params.get('utm_medium') || '') + '|' + (params.get('utm_campaign') || '');
    var ref = document.referrer || '';
    var src = 'direct';
    var detail = '';
    var u = utm.toLowerCase();
    var r = ref.toLowerCase();
    if (u.indexOf('google') >= 0 || r.indexOf('google.') >= 0 || r.indexOf('googleusercontent') >= 0) {
      src = (u.indexOf('cpc') >= 0 || u.indexOf('paid') >= 0 || params.get('gclid')) ? 'google_ads' : 'google_organic';
      detail = ref || utm;
    } else if (/instagram|facebook|t\.me|telegram|twitter|x\.com|linkedin|pinterest|tiktok|aparat|youtube|threads/.test(u+r) || /social|ig|fb|story/.test(u)) {
      src = 'social';
      detail = ref || utm;
    } else if (ref && ref.indexOf(location.hostname) === -1) {
      src = 'referral';
      detail = ref;
    } else if (utm.replace(/\|/g,'') !== '') {
      src = 'campaign';
      detail = utm;
    }
    var payload = encodeURIComponent(JSON.stringify({s:src,d:detail,t:Date.now()}));
    document.cookie = 'wap_traffic=' + payload + ';path=/;max-age=' + (60*60*24*30) + ';SameSite=Lax';
  } catch (e) {}
})();
JS;
		wp_add_inline_script( 'wap-traffic', $js );
	}

	public static function read_cookie(): array {
		$raw = isset( $_COOKIE[ self::COOKIE ] ) ? wp_unslash( $_COOKIE[ self::COOKIE ] ) : '';
		if ( $raw === '' ) {
			return array( 'source' => 'direct', 'detail' => '' );
		}
		$json = urldecode( $raw );
		$data = json_decode( $json, true );
		if ( ! is_array( $data ) ) {
			return array( 'source' => 'direct', 'detail' => '' );
		}
		$src = sanitize_key( (string) ( $data['s'] ?? 'direct' ) );
		$allowed = array( 'google_organic', 'google_ads', 'social', 'referral', 'campaign', 'direct' );
		if ( ! in_array( $src, $allowed, true ) ) {
			$src = 'direct';
		}
		return array(
			'source' => $src,
			'detail' => sanitize_text_field( (string) ( $data['d'] ?? '' ) ),
		);
	}

	/** @param WC_Order $order */
	public static function attach_to_order( $order, $data = null ): void {
		if ( ! $order || ! is_object( $order ) ) {
			return;
		}
		if ( $order->get_meta( self::META_SOURCE ) ) {
			return;
		}
		$info = self::read_cookie();
		$order->update_meta_data( self::META_SOURCE, $info['source'] );
		$order->update_meta_data( self::META_DETAIL, $info['detail'] );
	}

	public static function attach_to_order_api( $order ): void {
		self::attach_to_order( $order );
	}

	public static function source_label( string $src ): string {
		$map = array(
			'google_organic' => 'گوگل ارگانیک',
			'google_ads'     => 'گوگل تبلیغاتی',
			'social'         => 'سوشال‌مدیا',
			'referral'       => 'ارجاعی',
			'campaign'       => 'کمپین',
			'direct'         => 'ورود مستقیم',
		);
		return $map[ $src ] ?? $src;
	}

	/** @param WC_Order $order */
	public static function order_source( $order ): string {
		$s = (string) $order->get_meta( self::META_SOURCE );
		return $s !== '' ? $s : 'direct';
	}
}
