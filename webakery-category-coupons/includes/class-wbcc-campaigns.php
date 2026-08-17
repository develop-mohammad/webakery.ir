<?php
defined( 'ABSPATH' ) || exit;

/**
 * مدیریت «کمپین تخفیف» — هر کمپین یک قالب برای ساختن کد تخفیف است:
 * دسته‌بندی‌ها + بازه درصد + قوانین مصرف.
 */
class WBCC_Campaigns {

	const OPTION = 'wbcc_campaigns';

	/** @return array<int,array> */
	public static function all() {
		$rows = get_option( self::OPTION, array() );
		if ( ! is_array( $rows ) ) {
			return array();
		}
		$out = array();
		foreach ( $rows as $row ) {
			if ( ! empty( $row['id'] ) ) {
				$out[ (int) $row['id'] ] = wp_parse_args( $row, self::defaults() );
			}
		}
		ksort( $out );
		return $out;
	}

	/** @return array|null */
	public static function get( $id ) {
		$all = self::all();
		return isset( $all[ (int) $id ] ) ? $all[ (int) $id ] : null;
	}

	public static function defaults() {
		return array(
			'id'                   => 0,
			'name'                 => '',
			'enabled'              => 1,
			'categories'           => array(),
			'exclude_categories'   => array(),
			'include_children'     => 1,
			'type'                 => 'percent',
			'min'                  => 40,
			'max'                  => 50,
			'step'                 => 5,
			'prefix'               => '',
			'code_length'          => 6,
			'expires_days'         => 7,
			'usage_limit'          => 1,
			'usage_limit_per_user' => 1,
			'individual_use'       => 1,
			'free_shipping'        => 0,
			'exclude_sale_items'   => 0,
			'min_spend'            => '',
			'max_spend'            => '',
			'batch_count'          => 10,
			'auto_enabled'         => 0,
			'auto_interval'        => 'daily',
			'auto_count'           => 5,
			'last_run'             => 0,
			'public_enabled'       => 0,
			'public_cooldown'      => 24,
			'public_restrict_email' => 0,
			'created'              => 0,
		);
	}

	/**
	 * ذخیره کمپین (ساخت یا ویرایش). ورودی خام فرم را پاک‌سازی می‌کند.
	 *
	 * @return int شناسه کمپین
	 */
	public static function save( array $input ) {
		$all  = self::all();
		$id   = (int) ( $input['id'] ?? 0 );
		$prev = $id && isset( $all[ $id ] ) ? $all[ $id ] : self::defaults();
		$data = self::sanitize( $input, $prev );

		if ( ! $id ) {
			$id              = self::next_id( $all );
			$data['created'] = time();
		}
		$data['id']  = $id;
		$all[ $id ]  = $data;

		update_option( self::OPTION, array_values( $all ), false );
		return $id;
	}

	public static function delete( $id ) {
		$all = self::all();
		unset( $all[ (int) $id ] );
		update_option( self::OPTION, array_values( $all ), false );
	}

	public static function toggle( $id, $enabled ) {
		$all = self::all();
		$id  = (int) $id;
		if ( ! isset( $all[ $id ] ) ) {
			return;
		}
		$all[ $id ]['enabled'] = $enabled ? 1 : 0;
		update_option( self::OPTION, array_values( $all ), false );
	}

	public static function touch_run( $id, $time = null ) {
		$all = self::all();
		$id  = (int) $id;
		if ( ! isset( $all[ $id ] ) ) {
			return;
		}
		$all[ $id ]['last_run'] = $time ? (int) $time : time();
		update_option( self::OPTION, array_values( $all ), false );
	}

	protected static function next_id( array $all ) {
		$max = 0;
		foreach ( array_keys( $all ) as $key ) {
			$max = max( $max, (int) $key );
		}
		return $max + 1;
	}

	/**
	 * پاک‌سازی ورودی فرم. مقادیر نامعتبر به مقدار قبلی/پیش‌فرض برمی‌گردند.
	 */
	public static function sanitize( array $in, array $prev = array() ) {
		$prev = $prev ? wp_parse_args( $prev, self::defaults() ) : self::defaults();
		$d    = self::defaults();

		$types = array( 'percent', 'fixed_cart', 'fixed_product' );
		$type  = isset( $in['type'] ) && in_array( $in['type'], $types, true ) ? $in['type'] : $prev['type'];

		$min = isset( $in['min'] ) ? self::num( $in['min'] ) : (float) $prev['min'];
		$max = isset( $in['max'] ) ? self::num( $in['max'] ) : (float) $prev['max'];
		if ( $max < $min ) {
			$tmp = $min;
			$min = $max;
			$max = $tmp;
		}
		if ( 'percent' === $type ) {
			$min = (float) min( 100, max( 0, $min ) );
			$max = (float) min( 100, max( 0, $max ) );
		}
		$step = isset( $in['step'] ) ? max( 0, self::num( $in['step'] ) ) : (float) $prev['step'];

		$name = isset( $in['name'] ) ? sanitize_text_field( wp_unslash( $in['name'] ) ) : $prev['name'];
		if ( '' === trim( (string) $name ) ) {
			$name = 'کمپین تخفیف';
		}

		$prefix = isset( $in['prefix'] ) ? wp_unslash( $in['prefix'] ) : $prev['prefix'];
		$prefix = strtoupper( preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $prefix ) );
		$prefix = substr( $prefix, 0, 12 );

		$intervals = array( 'hourly', 'daily', 'weekly', 'monthly' );
		$interval  = isset( $in['auto_interval'] ) && in_array( $in['auto_interval'], $intervals, true )
			? $in['auto_interval'] : $prev['auto_interval'];

		return array(
			'id'                   => (int) ( $in['id'] ?? $prev['id'] ),
			'name'                 => $name,
			'enabled'              => self::bool( $in, 'enabled', $prev ),
			'categories'           => self::ids( $in['categories'] ?? $prev['categories'] ),
			'exclude_categories'   => self::ids( $in['exclude_categories'] ?? $prev['exclude_categories'] ),
			'include_children'     => self::bool( $in, 'include_children', $prev ),
			'type'                 => $type,
			'min'                  => $min,
			'max'                  => $max,
			'step'                 => $step,
			'prefix'               => $prefix,
			'code_length'          => self::clamp( $in['code_length'] ?? $prev['code_length'], 4, 20, $d['code_length'] ),
			'expires_days'         => self::clamp( $in['expires_days'] ?? $prev['expires_days'], 0, 3650, $d['expires_days'] ),
			'usage_limit'          => self::clamp( $in['usage_limit'] ?? $prev['usage_limit'], 0, 100000, $d['usage_limit'] ),
			'usage_limit_per_user' => self::clamp( $in['usage_limit_per_user'] ?? $prev['usage_limit_per_user'], 0, 100000, $d['usage_limit_per_user'] ),
			'individual_use'       => self::bool( $in, 'individual_use', $prev ),
			'free_shipping'        => self::bool( $in, 'free_shipping', $prev ),
			'exclude_sale_items'   => self::bool( $in, 'exclude_sale_items', $prev ),
			'min_spend'            => self::money( $in['min_spend'] ?? $prev['min_spend'] ),
			'max_spend'            => self::money( $in['max_spend'] ?? $prev['max_spend'] ),
			'batch_count'          => self::clamp( $in['batch_count'] ?? $prev['batch_count'], 1, 500, $d['batch_count'] ),
			'auto_enabled'         => self::bool( $in, 'auto_enabled', $prev ),
			'auto_interval'        => $interval,
			'auto_count'           => self::clamp( $in['auto_count'] ?? $prev['auto_count'], 1, 500, $d['auto_count'] ),
			'last_run'             => (int) $prev['last_run'],
			'public_enabled'       => self::bool( $in, 'public_enabled', $prev ),
			'public_cooldown'      => self::clamp( $in['public_cooldown'] ?? $prev['public_cooldown'], 0, 8760, $d['public_cooldown'] ),
			'public_restrict_email' => self::bool( $in, 'public_restrict_email', $prev ),
			'created'              => (int) $prev['created'],
		);
	}

	/** برچسب فارسی بازه تخفیف برای نمایش */
	public static function amount_label( array $campaign ) {
		$min = self::trim_zeros( $campaign['min'] );
		$max = self::trim_zeros( $campaign['max'] );
		$rng = ( $min === $max ) ? $min : $min . ' تا ' . $max;

		if ( 'percent' === $campaign['type'] ) {
			return $rng . ' درصد';
		}
		$unit = function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : '';
		$sub  = 'fixed_product' === $campaign['type'] ? ' (روی هر محصول)' : ' (روی کل سبد)';
		return $rng . ' ' . $unit . $sub;
	}

	/** نام دسته‌بندی‌های کمپین */
	public static function category_names( array $ids, $limit = 0 ) {
		$names = array();
		foreach ( $ids as $id ) {
			$term = get_term( (int) $id, 'product_cat' );
			if ( $term && ! is_wp_error( $term ) ) {
				$names[] = $term->name;
			}
		}
		if ( $limit > 0 && count( $names ) > $limit ) {
			$rest  = count( $names ) - $limit;
			$names = array_slice( $names, 0, $limit );
			$names[] = '+' . $rest . ' دسته دیگر';
		}
		return $names;
	}

	/**
	 * دسته‌بندی‌های نهایی کمپین؛ در صورت فعال بودن «زیردسته‌ها» فرزندان هم اضافه می‌شوند.
	 *
	 * @return int[]
	 */
	public static function resolved_categories( array $campaign ) {
		$ids = self::ids( $campaign['categories'] );
		if ( empty( $campaign['include_children'] ) || ! $ids ) {
			return $ids;
		}
		$out = $ids;
		foreach ( $ids as $id ) {
			$kids = get_term_children( $id, 'product_cat' );
			if ( is_array( $kids ) ) {
				$out = array_merge( $out, array_map( 'intval', $kids ) );
			}
		}
		return array_values( array_unique( $out ) );
	}

	/* ─── ابزارهای پاک‌سازی ───────────────────────────────────── */

	public static function ids( $value ) {
		$value = is_array( $value ) ? $value : ( '' === $value || null === $value ? array() : array( $value ) );
		$out   = array();
		foreach ( $value as $v ) {
			$v = (int) $v;
			if ( $v > 0 ) {
				$out[] = $v;
			}
		}
		return array_values( array_unique( $out ) );
	}

	protected static function bool( array $in, $key, array $prev ) {
		// فرم ادمین برای هر چک‌باکس یک فیلد مخفی «wbcc_has_<key>» می‌فرستد،
		// پس نبودِ کلید یعنی «تیک برداشته شد» فقط وقتی فرم آن را ارسال کرده باشد.
		if ( array_key_exists( $key, $in ) ) {
			return empty( $in[ $key ] ) ? 0 : 1;
		}
		if ( ! empty( $in['_fields'] ) && is_array( $in['_fields'] ) && in_array( $key, $in['_fields'], true ) ) {
			return 0;
		}
		return empty( $prev[ $key ] ) ? 0 : 1;
	}

	protected static function num( $value ) {
		$value = self::digits( (string) $value );
		return (float) preg_replace( '/[^0-9.\-]/', '', $value );
	}

	protected static function money( $value ) {
		$value = self::digits( (string) $value );
		$value = preg_replace( '/[^0-9.]/', '', $value );
		return '' === $value ? '' : (string) (float) $value;
	}

	protected static function clamp( $value, $min, $max, $fallback ) {
		$value = self::digits( (string) $value );
		if ( '' === trim( $value ) || ! is_numeric( trim( $value ) ) ) {
			return (int) $fallback;
		}
		return (int) min( $max, max( $min, (int) $value ) );
	}

	/** تبدیل ارقام فارسی/عربی به لاتین */
	public static function digits( $value ) {
		$fa = array( '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹' );
		$ar = array( '٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩' );
		$en = array( '0', '1', '2', '3', '4', '5', '6', '7', '8', '9' );
		return str_replace( array_merge( $fa, $ar ), array_merge( $en, $en ), (string) $value );
	}

	public static function trim_zeros( $number ) {
		$number = (float) $number;
		if ( $number === floor( $number ) ) {
			return (string) (int) $number;
		}
		return rtrim( rtrim( number_format( $number, 2, '.', '' ), '0' ), '.' );
	}
}
