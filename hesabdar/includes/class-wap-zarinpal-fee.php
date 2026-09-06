<?php
defined( 'ABSPATH' ) || exit;

/**
 * محاسبه کارمزد زرین‌پال بر اساس تعرفه رسمی و متد feeCalculation.
 *
 * تعرفه استاندارد درگاه (zarinpal.com/pricing):
 * ۰٫۵٪ تا سقف ۱۶٬۰۰۰ تومان + ۵۰۰ تومان ثابت به ازای هر تراکنش.
 * fee_type معمولاً Merchant است (کسر از پذیرنده).
 *
 * @link https://www.zarinpal.com/docs/paymentGateway/otherMethods/feeCalculation
 * @link https://www.zarinpal.com/pricing
 */
class WAP_Zarinpal_Fee {

	const FEE_PCT          = 0.005;   // ۰٫۵٪
	const FEE_CAP_TOMAN    = 16000;   // سقف درصدی
	const FEE_FIXED_TOMAN  = 500;     // ثابت هر تراکنش
	const FEE_API_URL      = 'https://payment.zarinpal.com/pg/v4/payment/feeCalculation.json';

	/**
	 * کارمزد تخمینی به تومان طبق تعرفه رسمی.
	 */
	public static function estimate_toman( float $amount_toman ): int {
		if ( $amount_toman <= 0 ) {
			return 0;
		}
		$pct = min( $amount_toman * self::FEE_PCT, self::FEE_CAP_TOMAN );
		return (int) round( $pct + self::FEE_FIXED_TOMAN );
	}

	/**
	 * کارمزد تخمینی به ریال (برای مقایسه با API که ریال برمی‌گرداند).
	 */
	public static function estimate_rial( int $amount_rial ): int {
		return self::estimate_toman( $amount_rial / 10 ) * 10;
	}

	/**
	 * مبلغ خالص قابل‌انتظار پس از کسر کارمزد (وقتی fee_type = Merchant).
	 */
	public static function net_after_fee_toman( float $gross_toman, ?int $fee_toman = null ): float {
		$fee = $fee_toman !== null ? $fee_toman : self::estimate_toman( $gross_toman );
		return max( 0, $gross_toman - $fee );
	}

	/**
	 * فراخوانی feeCalculation زرین‌پال.
	 *
	 * @return array{amount:int,fee:int,fee_type:string,suggested_amount:int}|WP_Error
	 */
	public static function calculate_via_api( int $amount_rial, string $merchant_id = '' ) {
		if ( $merchant_id === '' && class_exists( 'WAP_SMS' ) ) {
			$merchant_id = trim( (string) WAP_SMS::get( 'zp_merchant_id', '' ) );
		}
		if ( $merchant_id === '' ) {
			return new WP_Error( 'wap_zp_merchant', 'مرچنت‌کد زرین‌پال برای محاسبه کارمزد تنظیم نشده است.' );
		}
		if ( $amount_rial < 1000 ) {
			return new WP_Error( 'wap_zp_amount', 'مبلغ باید حداقل ۱۰۰۰ ریال باشد.' );
		}

		$resp = wp_remote_post(
			self::FEE_API_URL,
			array(
				'timeout' => 15,
				'headers' => array(
					'Accept'       => 'application/json',
					'Content-Type' => 'application/json',
				),
				'body'    => wp_json_encode( array(
					'merchant_id' => $merchant_id,
					'amount'      => $amount_rial,
					'currency'    => 'IRR',
				) ),
			)
		);
		if ( is_wp_error( $resp ) ) {
			return $resp;
		}
		$data = json_decode( (string) wp_remote_retrieve_body( $resp ), true );
		$row  = is_array( $data ) ? ( $data['data'] ?? null ) : null;
		if ( ! is_array( $row ) || (int) ( $row['code'] ?? 0 ) !== 100 ) {
			$msg = is_array( $row ) ? (string) ( $row['message'] ?? 'خطا' ) : 'پاسخ نامعتبر feeCalculation';
			return new WP_Error( 'wap_zp_fee', $msg );
		}
		return array(
			'amount'           => (int) ( $row['amount'] ?? $amount_rial ),
			'fee'              => (int) ( $row['fee'] ?? 0 ),
			'fee_type'         => (string) ( $row['fee_type'] ?? 'Merchant' ),
			'suggested_amount' => (int) ( $row['suggested_amount'] ?? 0 ),
		);
	}

	/**
	 * کارمزد به تومان: اول فرمول رسمی؛ در صورت وجود مرچنت و $prefer_api=true از API.
	 *
	 * @return array{fee_toman:int,fee_rial:int,fee_type:string,source:string}
	 */
	public static function resolve_fee( float $amount_toman, bool $prefer_api = false ): array {
		$est = self::estimate_toman( $amount_toman );
		$out = array(
			'fee_toman' => $est,
			'fee_rial'  => $est * 10,
			'fee_type'  => 'Merchant',
			'source'    => 'formula',
		);
		if ( ! $prefer_api ) {
			return $out;
		}
		$merchant = class_exists( 'WAP_SMS' ) ? trim( (string) WAP_SMS::get( 'zp_merchant_id', '' ) ) : '';
		if ( $merchant === '' ) {
			return $out;
		}
		$api = self::calculate_via_api( (int) round( $amount_toman * 10 ), $merchant );
		if ( is_wp_error( $api ) ) {
			return $out;
		}
		$fee_rial = (int) $api['fee'];
		return array(
			'fee_toman' => (int) round( $fee_rial / 10 ),
			'fee_rial'  => $fee_rial,
			'fee_type'  => (string) $api['fee_type'],
			'source'    => 'api',
		);
	}

	/** توضیح کوتاه تعرفه برای UI */
	public static function tariff_note(): string {
		return 'کارمزد استاندارد زرین‌پال: ۰٫۵٪ تا سقف ۱۶٬۰۰۰ تومان + ۵۰۰ تومان ثابت (معمولاً کسر از پذیرنده).';
	}
}
