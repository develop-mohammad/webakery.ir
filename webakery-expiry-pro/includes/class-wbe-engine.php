<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * منطق بچ‌ها — بدون وابستگی به وردپرس/ووکامرس.
 *
 * ترتیب فعال: نزدیک‌ترین انقضای معتبر؛ اگر برابر بود، ترتیب ورود ادمین.
 * نامعتبر = موجودی صفر یا تاریخ گذشته.
 */
class WBE_Engine {

	/**
	 * @param array  $batches
	 * @param string $today Y-m-d
	 * @return int|null
	 */
	public static function active_index( array $batches, $today ) {
		$best_i = null;
		$best_e = null;
		foreach ( $batches as $i => $batch ) {
			if ( ! self::is_valid( $batch, $today ) ) {
				continue;
			}
			$exp = (string) $batch['expiry'];
			if ( null === $best_i || $exp < $best_e ) {
				$best_i = (int) $i;
				$best_e = $exp;
			}
		}
		return $best_i;
	}

	/**
	 * @param array  $batch
	 * @param string $today Y-m-d
	 * @return bool
	 */
	public static function is_valid( $batch, $today ) {
		if ( ! is_array( $batch ) ) {
			return false;
		}
		$stock = isset( $batch['stock'] ) ? (int) $batch['stock'] : 0;
		$exp   = isset( $batch['expiry'] ) ? (string) $batch['expiry'] : '';
		if ( $stock <= 0 || $exp === '' ) {
			return false;
		}
		return $exp >= (string) $today;
	}

	/**
	 * کاهش موجودی بچ فعال. اگر صفر شد، حلقه در خواندن بعدی سراغ رزرو می‌رود.
	 *
	 * @param array  $batches
	 * @param int    $qty
	 * @param string $today
	 * @return array{batches:array,batch_id:string,consumed:int}
	 */
	public static function consume( array $batches, $qty, $today ) {
		$qty      = max( 0, (int) $qty );
		$consumed = 0;
		$batch_id = '';
		$idx      = self::active_index( $batches, $today );
		if ( null === $idx || $qty <= 0 ) {
			return array(
				'batches'  => $batches,
				'batch_id' => $batch_id,
				'consumed' => 0,
			);
		}
		$have = (int) $batches[ $idx ]['stock'];
		$take = min( $qty, $have );
		$batches[ $idx ]['stock'] = $have - $take;
		$consumed = $take;
		$batch_id = isset( $batches[ $idx ]['id'] ) ? (string) $batches[ $idx ]['id'] : '';
		return array(
			'batches'  => $batches,
			'batch_id' => $batch_id,
			'consumed' => $consumed,
		);
	}

	/**
	 * @param array  $batches
	 * @param int    $qty
	 * @param string $batch_id
	 * @return array
	 */
	public static function restore( array $batches, $qty, $batch_id ) {
		$qty = max( 0, (int) $qty );
		if ( $qty <= 0 ) {
			return $batches;
		}
		foreach ( $batches as $i => $batch ) {
			if ( isset( $batch['id'] ) && (string) $batch['id'] === (string) $batch_id ) {
				$batches[ $i ]['stock'] = (int) $batch['stock'] + $qty;
				return $batches;
			}
		}
		return $batches;
	}

	/**
	 * @param mixed  $rows
	 * @param string $calendar jalali|gregorian
	 * @return array
	 */
	public static function sanitize_batches( $rows, $calendar = 'gregorian' ) {
		$out = array();
		if ( ! is_array( $rows ) ) {
			return $out;
		}
		$n = 0;
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$price  = class_exists( 'WBE_Jalali' ) ? WBE_Jalali::number( isset( $row['price'] ) ? $row['price'] : 0 ) : (float) $row['price'];
			$stock  = (int) ( class_exists( 'WBE_Jalali' ) ? WBE_Jalali::number( isset( $row['stock'] ) ? $row['stock'] : 0 ) : $row['stock'] );
			$expiry = '';
			if ( class_exists( 'WBE_Jalali' ) ) {
				$expiry = WBE_Jalali::parse_to_ymd( isset( $row['expiry'] ) ? $row['expiry'] : '', $calendar );
			} else {
				$expiry = preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $row['expiry'] ) ? (string) $row['expiry'] : '';
			}
			if ( $expiry === '' && $price <= 0 && $stock <= 0 ) {
				continue;
			}
			if ( $expiry === '' ) {
				continue;
			}
			$discount = isset( $row['discount'] ) ? (string) $row['discount'] : '0';
			if ( class_exists( 'WBE_Jalali' ) ) {
				$discount = WBE_Jalali::fa_to_en( $discount );
			}
			$discount = str_replace( array( '%', '٪', ',', '٬', '،', ' ' ), '', $discount );
			$discount = is_numeric( $discount ) ? (float) $discount : 0;
			$discount = (int) round( max( 0, min( 100, $discount ) ) );
			$id       = isset( $row['id'] ) ? preg_replace( '/[^a-zA-Z0-9_\-]/', '', (string) $row['id'] ) : '';
			if ( $id === '' ) {
				$id = 'b' . sprintf( '%04d', $n ) . substr( md5( $expiry . '|' . $price . '|' . $n ), 0, 8 );
			}
			$out[] = array(
				'id'       => $id,
				'price'    => (string) $price,
				'discount' => $discount,
				'stock'    => max( 0, $stock ),
				'expiry'   => $expiry,
			);
			$n++;
		}
		return $out;
	}

	/**
	 * محصول «تنظیم‌شده» است اگر حداقل یک بچ ثبت شده باشد.
	 *
	 * @param array $batches
	 * @return bool
	 */
	public static function is_configured( array $batches ) {
		return ! empty( $batches );
	}

	/**
	 * تایمر کمپین: تنظیمات سراسری روشن باشد و روی محصول مخفی نشده باشد.
	 *
	 * @param mixed $global_on
	 * @param mixed $product_hidden
	 * @return bool
	 */
	public static function countdown_allowed( $global_on, $product_hidden ) {
		if ( empty( $global_on ) || '0' === (string) $global_on ) {
			return false;
		}
		if ( ! empty( $product_hidden ) && '0' !== (string) $product_hidden ) {
			return false;
		}
		return true;
	}

	/**
	 * درصد تخفیف بچ (۰ تا ۱۰۰).
	 *
	 * @param array $batch
	 * @return int
	 */
	public static function discount_of( $batch ) {
		if ( ! is_array( $batch ) ) {
			return 0;
		}
		return max( 0, min( 100, (int) round( isset( $batch['discount'] ) ? (float) $batch['discount'] : 0 ) ) );
	}

	/**
	 * قیمت پس از تخفیف. بدون تخفیف همان قیمت اصلی است.
	 *
	 * @param float|string $price
	 * @param float|int    $discount
	 * @return float
	 */
	public static function sale_price( $price, $discount ) {
		$price    = (float) $price;
		$discount = max( 0, min( 100, (float) $discount ) );
		if ( $discount <= 0 ) {
			return $price;
		}
		return (float) round( $price * ( 100 - $discount ) / 100 );
	}

	/**
	 * آیا امروز داخل بازه فروش فوق‌العاده است؟ تاریخ خالی = بدون محدودیت.
	 *
	 * @param string $from_ymd Y-m-d یا خالی
	 * @param string $to_ymd   Y-m-d یا خالی
	 * @param string $today    Y-m-d
	 * @return bool
	 */
	public static function sale_window_live( $from_ymd, $to_ymd, $today ) {
		$today = (string) $today;
		$from  = (string) $from_ymd;
		$to    = (string) $to_ymd;
		if ( $from !== '' && $from > $today ) {
			return false;
		}
		if ( $to !== '' && $to < $today ) {
			return false;
		}
		return true;
	}

	/**
	 * متن بازه فروش فوق‌العاده برای مشتری.
	 *
	 * @param string $from_ymd
	 * @param string $to_ymd
	 * @param string $calendar jalali|gregorian
	 * @param bool   $fa_digits
	 * @return string
	 */
	public static function sale_dates_text( $from_ymd, $to_ymd, $calendar = 'jalali', $fa_digits = true ) {
		$from_f = ( $from_ymd !== '' && class_exists( 'WBE_Jalali' ) ) ? WBE_Jalali::format_ymd( $from_ymd, $calendar, $fa_digits ) : '';
		$to_f   = ( $to_ymd !== '' && class_exists( 'WBE_Jalali' ) ) ? WBE_Jalali::format_ymd( $to_ymd, $calendar, $fa_digits ) : '';
		if ( $from_f && $to_f ) {
			return 'از ' . $from_f . ' تا ' . $to_f;
		}
		if ( $to_f ) {
			return 'تا ' . $to_f;
		}
		if ( $from_f ) {
			return 'از ' . $from_f;
		}
		return '';
	}

	/**
	 * پایان روز Y-m-d به timestamp یونیکس (منطقه زمانی سایت).
	 *
	 * @param string $ymd
	 * @return int
	 */
	public static function ymd_end_ts( $ymd ) {
		$ymd = (string) $ymd;
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $ymd ) ) {
			return 0;
		}
		if ( function_exists( 'wp_timezone' ) ) {
			$dt = date_create( $ymd . ' 23:59:59', wp_timezone() );
			return $dt ? $dt->getTimestamp() : 0;
		}
		$ts = strtotime( $ymd . ' 23:59:59' );
		return $ts ? (int) $ts : 0;
	}

	/**
	 * قطعات تایمر معکوس.
	 *
	 * @param int $end_ts
	 * @param int $now_ts
	 * @return array{remaining:int,days:int,hours:int,minutes:int,seconds:int}
	 */
	public static function countdown_parts( $end_ts, $now_ts ) {
		$left = max( 0, (int) $end_ts - (int) $now_ts );
		return array(
			'remaining' => $left,
			'days'      => (int) floor( $left / 86400 ),
			'hours'     => (int) floor( ( $left % 86400 ) / 3600 ),
			'minutes'   => (int) floor( ( $left % 3600 ) / 60 ),
			'seconds'   => (int) ( $left % 60 ),
		);
	}

	/**
	 * درصد تخفیف از قیمت اصلی و قیمت فروش ووکامرس.
	 *
	 * @param float|string     $regular
	 * @param float|string|null $sale
	 * @return int
	 */
	public static function discount_from_prices( $regular, $sale ) {
		$regular = (float) $regular;
		if ( $sale === null || $sale === '' || ! is_numeric( $sale ) ) {
			return 0;
		}
		$sale = (float) $sale;
		if ( $regular <= 0 || $sale < 0 || $sale >= $regular ) {
			return 0;
		}
		return max( 0, min( 100, (int) round( 100 - ( ( $sale / $regular ) * 100 ) ) ) );
	}

	/**
	 * قیمت ووکامرس را روی بچ فعال می‌نویسد (تغییر گروهی / محصول تازه).
	 * اگر بچ فعال نباشد، اولین ردیف به‌روز می‌شود.
	 *
	 * @param array            $batches
	 * @param float|string     $regular
	 * @param float|string|null $sale
	 * @param string           $today
	 * @param bool             $update_discount اگر false باشد درصد تخفیف قبلی می‌ماند
	 * @return array
	 */
	public static function apply_wc_price_to_active( array $batches, $regular, $sale, $today, $update_discount = true ) {
		if ( empty( $batches ) ) {
			return $batches;
		}
		$regular = class_exists( 'WBE_Jalali' ) ? WBE_Jalali::number( $regular ) : (float) $regular;
		if ( $regular <= 0 ) {
			return $batches;
		}
		$idx = self::active_index( $batches, $today );
		if ( null === $idx ) {
			$keys = array_keys( $batches );
			$idx  = (int) $keys[0];
		}
		if ( ! isset( $batches[ $idx ] ) || ! is_array( $batches[ $idx ] ) ) {
			return $batches;
		}
		$same_price = (string) (float) $batches[ $idx ]['price'] === (string) (float) $regular;
		if ( $update_discount ) {
			$discount = self::discount_from_prices( $regular, $sale );
			if ( $same_price && (int) self::discount_of( $batches[ $idx ] ) === $discount ) {
				return $batches;
			}
			$batches[ $idx ]['discount'] = $discount;
		} elseif ( $same_price ) {
			return $batches;
		}
		$batches[ $idx ]['price'] = (string) $regular;
		return $batches;
	}

	/**
	 * بند JOIN/ORDER BY برای سورت تاریخ انقضای فعال.
	 * محصولات بدون تاریخ انتهای لیست می‌مانند.
	 *
	 * @param string $join
	 * @param string $orderby
	 * @param string $posts_table
	 * @param string $postmeta_table
	 * @param string $order ASC|DESC
	 * @return array{0:string,1:string}
	 */
	public static function expiry_order_clauses( $join, $orderby, $posts_table, $postmeta_table, $order = 'ASC' ) {
		$join    = (string) $join;
		$orderby = (string) $orderby;
		if ( false !== strpos( $join, 'wbe_exp' ) ) {
			return array( $join, $orderby );
		}
		$order = ( 'DESC' === strtoupper( (string) $order ) ) ? 'DESC' : 'ASC';
		$join .= " LEFT JOIN {$postmeta_table} AS wbe_exp ON (wbe_exp.post_id = {$posts_table}.ID AND wbe_exp.meta_key = '_wbe_active_expiry') ";
		$sql   = " CASE WHEN wbe_exp.meta_value IS NULL OR wbe_exp.meta_value = '' THEN 1 ELSE 0 END ASC, wbe_exp.meta_value {$order}, {$posts_table}.ID ASC ";
		return array( $join, $sql );
	}

	/**
	 * سطح هشدار بر اساس روز مانده تا انقضا.
	 * soon ≈ یک هفته، month ≈ یک ماه، two_months ≈ دو ماه.
	 *
	 * @param int|null $days
	 * @return string soon|month|two_months|expired|''
	 */
	public static function urgency( $days, $soon = 7, $month = 30, $two = 60 ) {
		if ( null === $days || $days === '' ) {
			return '';
		}
		$days  = (int) $days;
		$soon  = max( 0, (int) $soon );
		$month = max( $soon, (int) $month );
		$two   = max( $month, (int) $two );
		if ( $days < 0 ) {
			return 'expired';
		}
		if ( $days <= $soon ) {
			return 'soon';
		}
		if ( $days <= $month ) {
			return 'month';
		}
		if ( $days <= $two ) {
			return 'two_months';
		}
		return '';
	}

	public static function match_point( $days, array $points ) {
		if ( null === $days || $days === '' ) {
			return null;
		}
		$days = (int) $days;
		if ( $days < 0 ) {
			return 'expired';
		}
		$points = self::clean_points( $points );
		foreach ( $points as $point ) {
			if ( $days <= $point ) {
				return (int) $point;
			}
		}
		return null;
	}

	public static function clean_points( $points ) {
		$out = array();
		if ( ! is_array( $points ) ) {
			$points = array( $points );
		}
		foreach ( $points as $p ) {
			if ( class_exists( 'WBE_Jalali' ) ) {
				$raw = trim( WBE_Jalali::fa_to_en( $p ) );
			} else {
				$raw = trim( (string) $p );
			}
			$raw = str_replace( array( ',', '٬', '،', ' ' ), '', $raw );
			if ( $raw === '' || ! is_numeric( $raw ) ) {
				continue;
			}
			$n = (int) $raw;
			if ( $n >= 0 && $n <= 3650 ) {
				$out[] = $n;
			}
		}
		$out = array_values( array_unique( $out ) );
		sort( $out, SORT_NUMERIC );
		return $out;
	}

	public static function normalize_phone( $raw ) {
		$digits = class_exists( 'WBE_Jalali' ) ? WBE_Jalali::fa_to_en( $raw ) : (string) $raw;
		$digits = preg_replace( '/\D+/', '', $digits );
		if ( strlen( $digits ) === 12 && 0 === strpos( $digits, '98' ) ) {
			$digits = '0' . substr( $digits, 2 );
		}
		if ( strlen( $digits ) === 10 && 0 === strpos( $digits, '9' ) ) {
			$digits = '0' . $digits;
		}
		return preg_match( '/^09\d{9}$/', $digits ) ? $digits : '';
	}

	/**
	 * مبلغ آزاد (ارقام فارسی، جداکننده هزارگان) → float یا null اگر خالی/نامعتبر.
	 * صفر معتبر است و با خالی فرق دارد.
	 *
	 * @param mixed $raw
	 * @return float|null
	 */
	public static function parse_amount( $raw ) {
		if ( null === $raw ) {
			return null;
		}
		$raw = class_exists( 'WBE_Jalali' ) ? WBE_Jalali::fa_to_en( $raw ) : (string) $raw;
		$raw = trim( str_replace( array( ',', '٬', '،', ' ' ), '', $raw ) );
		if ( '' === $raw || ! is_numeric( $raw ) ) {
			return null;
		}
		return (float) $raw;
	}

	/**
	 * تغییر مبلغ. حالت‌ها: set, inc, dec, inc_pct, dec_pct.
	 *
	 * @param float|string $current مبلغ فعلی.
	 * @param string       $mode    حالت تغییر.
	 * @param mixed        $value   مقدار جدید یا اختلاف.
	 * @return float
	 */
	public static function change_amount( $current, $mode, $value ) {
		$current = (float) $current;
		$value   = (float) $value;
		switch ( $mode ) {
			case 'set':
				$out = $value;
				break;
			case 'inc':
				$out = $current + $value;
				break;
			case 'dec':
				$out = $current - $value;
				break;
			case 'inc_pct':
				$out = $current * ( 1 + ( $value / 100 ) );
				break;
			case 'dec_pct':
				$out = $current * ( 1 - ( $value / 100 ) );
				break;
			default:
				return max( 0, $current );
		}
		if ( $out < 0 ) {
			$out = 0;
		}
		return round( $out, 2 );
	}

	/**
	 * آیا $ops تغییری روی قیمت/تخفیف دارد؟
	 *
	 * @param array $ops
	 * @return bool
	 */
	public static function has_price_ops( array $ops ) {
		if ( ! empty( $ops['clear_sale'] ) ) {
			return true;
		}
		if ( ! empty( $ops['regular_mode'] ) && 'none' !== $ops['regular_mode'] && array_key_exists( 'regular_value', $ops ) && null !== $ops['regular_value'] && '' !== $ops['regular_value'] ) {
			return true;
		}
		if ( ! empty( $ops['sale_mode'] ) && 'none' !== $ops['sale_mode'] && array_key_exists( 'sale_value', $ops ) && null !== $ops['sale_value'] && '' !== $ops['sale_value'] ) {
			return true;
		}
		if ( array_key_exists( 'discount', $ops ) && null !== $ops['discount'] && '' !== $ops['discount'] ) {
			return true;
		}
		return false;
	}

	/**
	 * اعمال عملیات گروهی فقط روی بچ فعال. رزرو دست نمی‌خورد.
	 *
	 * کلیدهای $ops:
	 *   regular_mode / regular_value
	 *   sale_mode / sale_value   (مبلغ بعد از تخفیف / جشنواره)
	 *   discount                 (درصد؛ اگر sale_mode خالی باشد)
	 *   clear_sale               (پاک کردن تخفیف)
	 * اگر هم مبلغ جشنواره و هم درصد بیاید، مبلغ جشنواره اولویت دارد.
	 *
	 * @param array  $batches بچ‌ها.
	 * @param array  $ops     عملیات.
	 * @param string $today   Y-m-d.
	 * @return array
	 */
	public static function apply_bulk_to_active( array $batches, array $ops, $today ) {
		if ( empty( $batches ) || ! self::has_price_ops( $ops ) ) {
			return $batches;
		}
		$i = self::active_index( $batches, $today );
		if ( null === $i ) {
			return $batches;
		}

		$b       = $batches[ $i ];
		$regular = isset( $b['price'] ) ? (float) $b['price'] : 0;

		if ( ! empty( $ops['regular_mode'] ) && 'none' !== $ops['regular_mode'] && array_key_exists( 'regular_value', $ops ) && null !== $ops['regular_value'] && '' !== $ops['regular_value'] ) {
			$regular    = self::change_amount( $regular, $ops['regular_mode'], $ops['regular_value'] );
			$b['price'] = (string) $regular;
		}

		if ( ! empty( $ops['clear_sale'] ) ) {
			$b['discount'] = 0;
		} elseif ( ! empty( $ops['sale_mode'] ) && 'none' !== $ops['sale_mode'] && array_key_exists( 'sale_value', $ops ) && null !== $ops['sale_value'] && '' !== $ops['sale_value'] ) {
			$current_sale = self::sale_price( $regular, isset( $b['discount'] ) ? $b['discount'] : 0 );
			$new_sale     = self::change_amount( $current_sale, $ops['sale_mode'], $ops['sale_value'] );
			if ( $new_sale >= $regular ) {
				$b['discount'] = 0;
			} else {
				$b['discount'] = self::discount_from_prices( $regular, $new_sale );
			}
		} elseif ( array_key_exists( 'discount', $ops ) && null !== $ops['discount'] && '' !== $ops['discount'] ) {
			$b['discount'] = max( 0, min( 100, (int) round( (float) $ops['discount'] ) ) );
		}

		$batches[ $i ] = $b;
		return $batches;
	}
}
