<?php
defined( 'ABSPATH' ) || exit;

/**
 * ساخت کد تخفیف ووکامرس بر اساس تنظیمات کمپین.
 */
class WBCC_Generator {

	const META_CAMPAIGN = '_wbcc_campaign';
	const META_SOURCE   = '_wbcc_source';
	const CHARSET       = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

	/**
	 * ساخت چند کد تخفیف برای یک کمپین.
	 *
	 * @param array  $campaign کمپین
	 * @param int    $count    تعداد کد
	 * @param string $source   منبع ساخت: manual | auto | public
	 * @param array  $args     اختیاری: email (محدودکردن کد به ایمیل مشتری)
	 *
	 * @return array{ok:bool,message:string,coupons:array<int,array>}
	 */
	public static function generate( array $campaign, $count = 1, $source = 'manual', array $args = array() ) {
		if ( ! WBCC_Plugin::woo_available() ) {
			return self::fail( 'ووکامرس فعال نیست.' );
		}
		if ( ! WBCC_Plugin::licensed() ) {
			return self::fail( 'دوره آزمایشی به پایان رسیده است؛ برای ساخت کد تخفیف، لایسنس را فعال کنید.' );
		}
		$cats = WBCC_Campaigns::resolved_categories( $campaign );
		if ( ! $cats ) {
			return self::fail( 'برای این کمپین هیچ دسته‌بندی محصولی انتخاب نشده است.' );
		}

		$count   = max( 1, min( 500, (int) $count ) );
		$coupons = array();

		for ( $i = 0; $i < $count; $i++ ) {
			$amount = self::pick_amount( $campaign['min'], $campaign['max'], $campaign['step'] );
			$code   = self::unique_code( $campaign );
			if ( ! $code ) {
				break;
			}
			$id = self::create_coupon( $campaign, $code, $amount, $cats, $source, $args );
			if ( $id ) {
				$coupons[] = array(
					'id'     => $id,
					'code'   => self::display_code( $code ),
					'amount' => $amount,
				);
			}
		}

		if ( ! $coupons ) {
			return self::fail( 'ساخت کد تخفیف ناموفق بود.' );
		}

		/**
		 * پس از ساخت کدهای تخفیف یک کمپین.
		 *
		 * @param array $coupons لیست کدهای ساخته‌شده
		 * @param array $campaign
		 * @param string $source
		 */
		do_action( 'wbcc_coupons_generated', $coupons, $campaign, $source );

		return array(
			'ok'      => true,
			'message' => count( $coupons ) . ' کد تخفیف ساخته شد.',
			'coupons' => $coupons,
		);
	}

	/**
	 * انتخاب مقدار تخفیف از بازه کمپین.
	 * با «پله» مقادیر مرتب (۴۰، ۴۵، ۵۰) و بدون پله عدد تصادفی با دو رقم اعشار.
	 *
	 * @return float
	 */
	public static function pick_amount( $min, $max, $step = 0 ) {
		$min  = (float) $min;
		$max  = (float) $max;
		$step = (float) $step;

		if ( $max < $min ) {
			$tmp = $min;
			$min = $max;
			$max = $tmp;
		}
		if ( $max - $min < 0.00001 ) {
			return round( $min, 2 );
		}

		if ( $step > 0 ) {
			$values = array();
			for ( $v = $min; $v <= $max + 0.00001; $v += $step ) {
				$values[] = round( $v, 2 );
			}
			if ( ! $values ) {
				$values[] = round( $min, 2 );
			}
			if ( end( $values ) < $max - 0.00001 ) {
				$values[] = round( $max, 2 );
			}
			return $values[ wp_rand( 0, count( $values ) - 1 ) ];
		}

		return round( wp_rand( (int) round( $min * 100 ), (int) round( $max * 100 ) ) / 100, 2 );
	}

	/** یک کد یکتا برای کمپین می‌سازد (حداکثر ۲۵ تلاش) */
	public static function unique_code( array $campaign ) {
		for ( $try = 0; $try < 25; $try++ ) {
			$code = self::build_code( $campaign );
			if ( ! self::code_exists( $code ) ) {
				return $code;
			}
		}
		return '';
	}

	public static function build_code( array $campaign ) {
		$prefix = strtoupper( (string) ( $campaign['prefix'] ?? '' ) );
		if ( '' === $prefix ) {
			$prefix = self::default_prefix();
		}
		$len   = max( 4, (int) ( $campaign['code_length'] ?? 6 ) );
		$chars = self::CHARSET;
		$rand  = '';
		$size  = strlen( $chars );
		for ( $i = 0; $i < $len; $i++ ) {
			$rand .= $chars[ wp_rand( 0, $size - 1 ) ];
		}
		return $prefix ? $prefix . '-' . $rand : $rand;
	}

	public static function default_prefix() {
		$settings = get_option( 'wbcc_settings', array() );
		$prefix   = isset( $settings['default_prefix'] ) ? (string) $settings['default_prefix'] : 'OFF';
		$prefix   = strtoupper( preg_replace( '/[^A-Za-z0-9_-]/', '', $prefix ) );
		return substr( $prefix, 0, 12 );
	}

	/**
	 * ووکامرس کد تخفیف را با حروف کوچک ذخیره می‌کند؛ برای نمایش بزرگ نشان می‌دهیم.
	 * اعمال کد در ووکامرس به بزرگی/کوچکی حروف حساس نیست.
	 */
	public static function display_code( $code ) {
		return strtoupper( (string) $code );
	}

	public static function code_exists( $code ) {
		if ( function_exists( 'wc_get_coupon_id_by_code' ) ) {
			return (bool) wc_get_coupon_id_by_code( $code );
		}
		global $wpdb;
		return (bool) $wpdb->get_var( $wpdb->prepare(
			"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'shop_coupon' AND post_title = %s LIMIT 1",
			$code
		) );
	}

	/**
	 * ساخت پست کد تخفیف ووکامرس.
	 *
	 * @return int شناسه کد تخفیف یا 0
	 */
	protected static function create_coupon( array $campaign, $code, $amount, array $cats, $source, array $args = array() ) {
		$coupon = new WC_Coupon();
		$coupon->set_code( $code );
		$coupon->set_discount_type( $campaign['type'] );
		$coupon->set_amount( $amount );
		$coupon->set_product_categories( $cats );
		$coupon->set_excluded_product_categories( WBCC_Campaigns::ids( $campaign['exclude_categories'] ) );
		$coupon->set_individual_use( ! empty( $campaign['individual_use'] ) );
		$coupon->set_free_shipping( ! empty( $campaign['free_shipping'] ) );
		$coupon->set_exclude_sale_items( ! empty( $campaign['exclude_sale_items'] ) );
		$coupon->set_usage_limit( (int) $campaign['usage_limit'] );
		$coupon->set_usage_limit_per_user( (int) $campaign['usage_limit_per_user'] );

		if ( '' !== $campaign['min_spend'] ) {
			$coupon->set_minimum_amount( $campaign['min_spend'] );
		}
		if ( '' !== $campaign['max_spend'] ) {
			$coupon->set_maximum_amount( $campaign['max_spend'] );
		}
		if ( (int) $campaign['expires_days'] > 0 ) {
			$coupon->set_date_expires( time() + (int) $campaign['expires_days'] * DAY_IN_SECONDS );
		}
		if ( ! empty( $args['email'] ) && is_email( $args['email'] ) ) {
			$coupon->set_email_restrictions( array( sanitize_email( $args['email'] ) ) );
		}

		$names = WBCC_Campaigns::category_names( $cats, 4 );
		$coupon->set_description( sprintf(
			'ساخته‌شده با افزونه کد تخفیف دسته‌بندی (webakery.ir) — کمپین «%s» — %s — دسته‌ها: %s',
			$campaign['name'],
			WBCC_Campaigns::amount_label( $campaign ),
			$names ? implode( '، ', $names ) : '—'
		) );

		$id = $coupon->save();
		if ( ! $id ) {
			return 0;
		}
		update_post_meta( $id, self::META_CAMPAIGN, (int) $campaign['id'] );
		update_post_meta( $id, self::META_SOURCE, sanitize_key( $source ) );
		return (int) $id;
	}

	/**
	 * کدهای ساخته‌شده توسط افزونه.
	 *
	 * @param array $args campaign (int), limit, paged, search
	 * @return array{items:array<int,array>,total:int}
	 */
	public static function list_coupons( array $args = array() ) {
		$args = wp_parse_args( $args, array(
			'campaign' => 0,
			'limit'    => 30,
			'paged'    => 1,
			'search'   => '',
		) );

		$meta = array(
			array(
				'key'     => self::META_CAMPAIGN,
				'compare' => 'EXISTS',
			),
		);
		if ( $args['campaign'] ) {
			$meta = array(
				array(
					'key'   => self::META_CAMPAIGN,
					'value' => (int) $args['campaign'],
				),
			);
		}

		$query = new WP_Query( array(
			'post_type'      => 'shop_coupon',
			'post_status'    => array( 'publish', 'draft', 'pending' ),
			'posts_per_page' => (int) $args['limit'],
			'paged'          => max( 1, (int) $args['paged'] ),
			'orderby'        => 'date',
			'order'          => 'DESC',
			's'              => $args['search'],
			'meta_query'     => $meta, // phpcs:ignore WordPress.DB.SlowDBQuery
			'no_found_rows'  => false,
		) );

		$items = array();
		foreach ( $query->posts as $post ) {
			$coupon  = new WC_Coupon( $post->ID );
			$expires = $coupon->get_date_expires();
			$items[] = array(
				'id'         => $post->ID,
				'code'       => self::display_code( $coupon->get_code() ),
				'type'       => $coupon->get_discount_type(),
				'amount'     => $coupon->get_amount(),
				'usage'      => (int) $coupon->get_usage_count(),
				'limit'      => (int) $coupon->get_usage_limit(),
				'expires'    => $expires ? $expires->getTimestamp() : 0,
				'campaign'   => (int) get_post_meta( $post->ID, self::META_CAMPAIGN, true ),
				'source'     => (string) get_post_meta( $post->ID, self::META_SOURCE, true ),
				'categories' => $coupon->get_product_categories(),
				'created'    => get_post_time( 'U', true, $post ),
			);
		}

		return array(
			'items' => $items,
			'total' => (int) $query->found_posts,
		);
	}

	public static function delete_coupon( $id ) {
		$id = (int) $id;
		if ( ! $id || 'shop_coupon' !== get_post_type( $id ) ) {
			return false;
		}
		if ( ! get_post_meta( $id, self::META_CAMPAIGN, true ) ) {
			return false; // فقط کدهای ساخته‌شده توسط افزونه
		}
		return (bool) wp_delete_post( $id, true );
	}

	/** حذف کدهای منقضی‌شده افزونه که مدت مشخصی از انقضایشان گذشته */
	public static function cleanup_expired( $grace_days = 0 ) {
		$deleted = 0;
		$cut     = time() - max( 0, (int) $grace_days ) * DAY_IN_SECONDS;

		$posts = get_posts( array(
			'post_type'      => 'shop_coupon',
			'post_status'    => 'any',
			'posts_per_page' => 200,
			'fields'         => 'ids',
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery
				array(
					'key'     => self::META_CAMPAIGN,
					'compare' => 'EXISTS',
				),
			),
		) );

		foreach ( $posts as $id ) {
			$coupon  = new WC_Coupon( $id );
			$expires = $coupon->get_date_expires();
			if ( ! $expires ) {
				continue;
			}
			if ( $expires->getTimestamp() < $cut ) {
				wp_delete_post( $id, true );
				$deleted++;
			}
		}
		return $deleted;
	}

	protected static function fail( $message ) {
		return array(
			'ok'      => false,
			'message' => $message,
			'coupons' => array(),
		);
	}
}
