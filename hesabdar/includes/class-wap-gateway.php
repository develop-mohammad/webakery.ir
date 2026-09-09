<?php
defined( 'ABSPATH' ) || exit;

/**
 * رجیستری درگاه‌های پرداخت ایرانی + تخمین کارمزد بر اساس تعرفهٔ مستند.
 *
 * زرین‌پال: ۰٫۵٪ تا سقف ۱۶٬۰۰۰ + ۵۰۰ تومان (pricing.zarinpal.com)
 * زیبال: ۱٪ با کف ۲٬۰۰۰ و سقف ۲۰٬۰۰۰ تومان (از خرداد ۱۴۰۵ — zibal.ir/blog)
 * آیدی‌پی: ۱٪ تا سقف ۵٬۰۰۰ تومان (تعرفه رایج پرداخت‌یار)
 * ترب‌پی: ۶٫۶٪ کارمزد فروشنده (مستندات ترب‌پی)
 * دیجی‌پی: تخمین ۵٪ (بازهٔ رایج ۴–۸٪؛ قابل تنظیم)
 * اسنپ‌پی: پیش‌فرض ۰٪ برای پذیرنده (توافقی؛ قابل تنظیم)
 */
class WAP_Gateway {

	const FAMILY_ZARINPAL = 'zarinpal';
	const FAMILY_ZIBAL    = 'zibal';
	const FAMILY_IDPAY    = 'idpay';
	const FAMILY_TOROB    = 'torobpay';
	const FAMILY_DIGIPAY  = 'digipay';
	const FAMILY_SNAPP    = 'snapppay';
	const FAMILY_OTHER    = 'other';

	/** @return array<string,array{label:string,patterns:array<int,string>,fee:array}> */
	public static function families(): array {
		return array(
			self::FAMILY_ZARINPAL => array(
				'label'    => 'زرین‌پال',
				'patterns' => array( 'zarin', 'zpal', 'زرین' ),
				'fee'      => array( 'type' => 'zarinpal' ),
			),
			self::FAMILY_ZIBAL => array(
				'label'    => 'زیبال',
				'patterns' => array( 'zibal', 'زیبال' ),
				'fee'      => array( 'type' => 'pct_minmax', 'pct' => 0.01, 'min' => 2000, 'max' => 20000 ),
			),
			self::FAMILY_IDPAY => array(
				'label'    => 'آیدی‌پی',
				'patterns' => array( 'idpay', 'id_pay', 'آیدی' ),
				'fee'      => array( 'type' => 'pct_cap', 'pct' => 0.01, 'cap' => 5000 ),
			),
			self::FAMILY_TOROB => array(
				'label'    => 'ترب‌پی',
				'patterns' => array( 'torob', 'ترب' ),
				'fee'      => array( 'type' => 'pct', 'pct' => 0.066 ),
			),
			self::FAMILY_DIGIPAY => array(
				'label'    => 'دیجی‌پی',
				'patterns' => array( 'digipay', 'digi_pay', 'دیجی' ),
				'fee'      => array( 'type' => 'pct', 'pct' => 0.05 ),
			),
			self::FAMILY_SNAPP => array(
				'label'    => 'اسنپ‌پی',
				'patterns' => array( 'snapp', 'اسنپ' ),
				'fee'      => array( 'type' => 'pct', 'pct' => 0.0 ),
			),
		);
	}

	public static function family( string $method ): string {
		$raw = trim( $method );
		$m   = strtolower( $raw );
		if ( $raw === '' ) {
			return self::FAMILY_OTHER;
		}
		foreach ( self::families() as $id => $meta ) {
			foreach ( $meta['patterns'] as $p ) {
				if ( $p === '' ) {
					continue;
				}
				$hay = preg_match( '/[^\x00-\x7F]/', $p ) ? $raw : $m;
				$needle = preg_match( '/[^\x00-\x7F]/', $p ) ? $p : strtolower( $p );
				if ( function_exists( 'mb_stripos' ) ) {
					if ( mb_stripos( $hay, $needle ) !== false ) {
						return $id;
					}
				} elseif ( stripos( $hay, $needle ) !== false ) {
					return $id;
				}
			}
		}
		return self::FAMILY_OTHER;
	}

	public static function label( string $method ): string {
		$id = self::family( $method );
		if ( $id !== self::FAMILY_OTHER ) {
			return self::families()[ $id ]['label'];
		}
		// سازگاری با برچسب‌های قبلی
		if ( class_exists( 'WAP_Data' ) ) {
			$legacy = WAP_Data::payment_label( $method );
			if ( $legacy && $legacy !== $method ) {
				return $legacy;
			}
		}
		return $method !== '' ? $method : '—';
	}

	public static function is_family( string $method, string $family ): bool {
		return self::family( $method ) === $family;
	}

	/** کارمزد تخمینی به تومان */
	public static function estimate_fee_toman( string $method, float $amount_toman ): int {
		if ( $amount_toman <= 0 ) {
			return 0;
		}
		$id   = self::family( $method );
		$meta = self::families()[ $id ]['fee'] ?? array( 'type' => 'pct', 'pct' => 0 );
		$type = $meta['type'] ?? 'pct';

		if ( $type === 'zarinpal' && class_exists( 'WAP_Zarinpal_Fee' ) ) {
			return WAP_Zarinpal_Fee::estimate_toman( $amount_toman );
		}
		$pct = (float) ( $meta['pct'] ?? 0 );
		$fee = $amount_toman * $pct;
		if ( $type === 'pct_cap' ) {
			$fee = min( $fee, (float) ( $meta['cap'] ?? $fee ) );
		} elseif ( $type === 'pct_minmax' ) {
			$fee = max( (float) ( $meta['min'] ?? 0 ), min( $fee, (float) ( $meta['max'] ?? $fee ) ) );
		}
		return (int) round( $fee );
	}

	public static function fee_note( string $family ): string {
		$map = array(
			self::FAMILY_ZARINPAL => '۰٫۵٪ تا سقف ۱۶٬۰۰۰ + ۵۰۰ تومان',
			self::FAMILY_ZIBAL    => '۱٪ (کف ۲٬۰۰۰ / سقف ۲۰٬۰۰۰ تومان)',
			self::FAMILY_IDPAY    => '۱٪ تا سقف ۵٬۰۰۰ تومان',
			self::FAMILY_TOROB    => '۶٫۶٪ کارمزد فروشنده',
			self::FAMILY_DIGIPAY  => 'تخمین ۵٪ (قابل تنظیم در توافق فروشنده)',
			self::FAMILY_SNAPP    => 'پیش‌فرض ۰٪ (توافق‌نامه اسنپ‌پی)',
		);
		return $map[ $family ] ?? '—';
	}
}
