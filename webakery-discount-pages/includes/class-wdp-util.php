<?php
defined( 'ABSPATH' ) || exit;

/**
 * توابع محاسباتی خالص (بدون وابستگی به ووکامرس/وردپرس)؛ به‌همین‌دلیل
 * به‌سادگی و بدون نیاز به نصب وردپرس قابل تست است (نگاه کنید به tests/test-util.php).
 */
class WDP_Util {

	/** تبدیل ارقام فارسی/عربی به لاتین */
	public static function digits( $value ) {
		$fa = array( '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹' );
		$ar = array( '٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩' );
		$en = array( '0', '1', '2', '3', '4', '5', '6', '7', '8', '9' );
		return str_replace( array_merge( $fa, $ar ), array_merge( $en, $en ), (string) $value );
	}

	/** تبدیل ارقام لاتین به فارسی (برای نمایش) */
	public static function fa_digits( $value ) {
		$en = array( '0', '1', '2', '3', '4', '5', '6', '7', '8', '9' );
		$fa = array( '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹' );
		return str_replace( $en, $fa, (string) $value );
	}

	/** ورودی فرم (احتمالاً با ارقام فارسی) را به عدد اعشاری تبدیل می‌کند */
	public static function to_number( $value ) {
		$value = self::digits( (string) $value );
		$value = preg_replace( '/[^0-9.\-]/', '', $value );
		return '' === $value || '-' === $value || '.' === $value ? 0.0 : (float) $value;
	}

	/** حذف صفرهای اضافه اعشار برای نمایش تمیزتر (مثلاً 20.00 → 20) */
	public static function trim_zeros( $number ) {
		$number = (float) $number;
		if ( $number === floor( $number ) ) {
			return (string) (int) $number;
		}
		return rtrim( rtrim( number_format( $number, 2, '.', '' ), '0' ), '.' );
	}

	/**
	 * محاسبه درصد و مبلغ تخفیف از روی قیمت اصلی و قیمت فروش.
	 *
	 * @return array{percent:float,fixed:float}|null null یعنی تخفیف معتبری وجود ندارد
	 */
	public static function compute_discount( $regular, $sale ) {
		$regular = (float) $regular;
		$sale    = (float) $sale;
		if ( $regular <= 0 || $sale <= 0 || $sale >= $regular ) {
			return null;
		}
		return array(
			'percent' => round( ( $regular - $sale ) / $regular * 100, 2 ),
			'fixed'   => round( $regular - $sale, 2 ),
		);
	}

	/**
	 * از میان چند «قانون صفحه تخفیف»، بهترین منطبق با تخفیف فعلی محصول را برمی‌گرداند.
	 * اولویت با: عدد priority بیشتر ← باریک‌ترین بازه ← کوچک‌ترین شناسه ترم.
	 *
	 * @param array $rules    هر عضو: array('term_id'=>int,'type'=>'percent'|'fixed','min'=>float,'max'=>float,'priority'=>int)
	 * @param array $discount خروجی compute_discount(): array('percent'=>float,'fixed'=>float)
	 *
	 * @return int|null شناسه ترم منتخب یا null
	 */
	public static function find_best_match( array $rules, array $discount ) {
		$matches = array();

		foreach ( $rules as $rule ) {
			$type = ( isset( $rule['type'] ) && 'fixed' === $rule['type'] ) ? 'fixed' : 'percent';
			if ( ! isset( $discount[ $type ] ) ) {
				continue;
			}
			$value = (float) $discount[ $type ];
			$min   = (float) ( $rule['min'] ?? 0 );
			$max   = (float) ( $rule['max'] ?? 0 );
			if ( $max < $min ) {
				list( $min, $max ) = array( $max, $min );
			}
			if ( $value >= $min - 0.001 && $value <= $max + 0.001 ) {
				$matches[] = array(
					'term_id'  => (int) $rule['term_id'],
					'width'    => $max - $min,
					'priority' => (int) ( $rule['priority'] ?? 10 ),
				);
			}
		}

		if ( ! $matches ) {
			return null;
		}

		usort(
			$matches,
			function ( $a, $b ) {
				if ( $a['priority'] !== $b['priority'] ) {
					return $b['priority'] <=> $a['priority'];
				}
				if ( $a['width'] !== $b['width'] ) {
					return $a['width'] <=> $b['width'];
				}
				return $a['term_id'] <=> $b['term_id'];
			}
		);

		return $matches[0]['term_id'];
	}

	/** برچسب فارسی بازه تخفیف برای نمایش، مثلاً «۲۰ تا ۳۰ درصد» یا «۵۰,۰۰۰ تا ۱۰۰,۰۰۰ تومان» */
	public static function range_label( $type, $min, $max, $currency = 'تومان' ) {
		$min_l = self::trim_zeros( $min );
		$max_l = self::trim_zeros( $max );
		$rng   = ( $min_l === $max_l ) ? $min_l : ( $min_l . ' تا ' . $max_l );
		return 'percent' === $type ? ( $rng . ' درصد' ) : ( $rng . ' ' . $currency );
	}
}
