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
			$id = isset( $row['id'] ) ? preg_replace( '/[^a-zA-Z0-9_\-]/', '', (string) $row['id'] ) : '';
			if ( $id === '' ) {
				$id = 'b' . sprintf( '%04d', $n ) . substr( md5( $expiry . '|' . $price . '|' . $n ), 0, 8 );
			}
			$out[] = array(
				'id'     => $id,
				'price'  => (string) $price,
				'stock'  => max( 0, $stock ),
				'expiry' => $expiry,
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
}
