<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ویرایش گروهی قیمت تخفیف و جشنواره — صفحه اختصاصی + فیلدهای ووکامرس.
 */
class WBE_Admin_Bulk {

	/** @var self|null */
	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_post_wbe_bulk_apply', array( $this, 'handle_apply' ) );
		add_action( 'woocommerce_product_bulk_edit_end', array( $this, 'wc_bulk_fields' ) );
		add_action( 'woocommerce_product_bulk_edit_save', array( $this, 'wc_bulk_save' ), 25 );
	}

	/**
	 * برچسب حالت‌های تغییر مبلغ.
	 *
	 * @return array<string,string>
	 */
	public static function mode_labels() {
		return array(
			'none'    => 'بدون تغییر',
			'set'     => 'تنظیم روی',
			'inc'     => 'افزایش مبلغ',
			'dec'     => 'کاهش مبلغ',
			'inc_pct' => 'افزایش درصدی',
			'dec_pct' => 'کاهش درصدی',
		);
	}

	/**
	 * خواندن عملیات از درخواست فرم گروهی.
	 *
	 * @param array  $src      معمولاً $_POST یا $_REQUEST.
	 * @param string $calendar jalali|gregorian.
	 * @return array
	 */
	public static function ops_from_request( $src, $calendar = 'jalali' ) {
		$src = is_array( $src ) ? $src : array();
		$ops = array();
		$ok  = array_keys( self::mode_labels() );

		$rm = isset( $src['wbe_regular_mode'] ) ? sanitize_key( wp_unslash( $src['wbe_regular_mode'] ) ) : 'none';
		$rv = array_key_exists( 'wbe_regular_value', $src ) ? WBE_Engine::parse_amount( wp_unslash( $src['wbe_regular_value'] ) ) : null;
		if ( in_array( $rm, $ok, true ) && 'none' !== $rm && null !== $rv ) {
			$ops['regular_mode']  = $rm;
			$ops['regular_value'] = $rv;
		}

		$sm = isset( $src['wbe_sale_mode'] ) ? sanitize_key( wp_unslash( $src['wbe_sale_mode'] ) ) : 'none';
		$sv = array_key_exists( 'wbe_sale_value', $src ) ? WBE_Engine::parse_amount( wp_unslash( $src['wbe_sale_value'] ) ) : null;
		if ( in_array( $sm, $ok, true ) && 'none' !== $sm && null !== $sv ) {
			$ops['sale_mode']  = $sm;
			$ops['sale_value'] = $sv;
		}

		if ( array_key_exists( 'wbe_discount', $src ) ) {
			$disc = WBE_Engine::parse_amount( wp_unslash( $src['wbe_discount'] ) );
			if ( null !== $disc ) {
				$ops['discount'] = max( 0, min( 100, $disc ) );
			}
		}

		if ( ! empty( $src['wbe_clear_sale'] ) ) {
			$ops['clear_sale'] = true;
		}

		$ops = array_merge( $ops, self::dates_from_src( $src, $calendar, 'wbe_sale_from', 'wbe_sale_to' ) );
		return $ops;
	}

	/**
	 * عملیات ردیف تکی: فیلد پرشده یعنی «تنظیم روی».
	 *
	 * @param array  $row
	 * @param string $calendar
	 * @return array
	 */
	public static function ops_from_row( $row, $calendar = 'jalali' ) {
		$row = is_array( $row ) ? $row : array();
		$ops = array();

		$regular = array_key_exists( 'regular', $row ) ? WBE_Engine::parse_amount( $row['regular'] ) : null;
		if ( null !== $regular ) {
			$ops['regular_mode']  = 'set';
			$ops['regular_value'] = $regular;
		}

		$sale = array_key_exists( 'sale', $row ) ? WBE_Engine::parse_amount( $row['sale'] ) : null;
		if ( null !== $sale ) {
			$ops['sale_mode']  = 'set';
			$ops['sale_value'] = $sale;
		}

		$disc = array_key_exists( 'discount', $row ) ? WBE_Engine::parse_amount( $row['discount'] ) : null;
		if ( null !== $disc ) {
			$ops['discount'] = max( 0, min( 100, $disc ) );
		}

		if ( ! empty( $row['clear'] ) ) {
			$ops['clear_sale'] = true;
		}

		$ops = array_merge( $ops, self::dates_from_src( $row, $calendar, 'from', 'to' ) );
		return $ops;
	}

	/**
	 * آیا عملیات چیزی برای اعمال دارد؟
	 *
	 * @param array $ops
	 * @return bool
	 */
	public static function ops_meaningful( array $ops ) {
		if ( WBE_Engine::has_price_ops( $ops ) ) {
			return true;
		}
		return ( isset( $ops['sale_from'] ) && '' !== $ops['sale_from'] )
			|| ( isset( $ops['sale_to'] ) && '' !== $ops['sale_to'] );
	}

	/**
	 * @param array  $src
	 * @param string $calendar
	 * @param string $from_key
	 * @param string $to_key
	 * @return array
	 */
	private static function dates_from_src( $src, $calendar, $from_key, $to_key ) {
		$out = array();
		if ( isset( $src[ $from_key ] ) && '' !== trim( (string) wp_unslash( $src[ $from_key ] ) ) ) {
			$from = WBE_Jalali::parse_to_ymd( wp_unslash( $src[ $from_key ] ), $calendar );
			if ( '' !== $from ) {
				$out['sale_from'] = $from;
			}
		}
		if ( isset( $src[ $to_key ] ) && '' !== trim( (string) wp_unslash( $src[ $to_key ] ) ) ) {
			$to = WBE_Jalali::parse_to_ymd( wp_unslash( $src[ $to_key ] ), $calendar );
			if ( '' !== $to ) {
				$out['sale_to'] = $to;
			}
		}
		return $out;
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( 'دسترسی غیرمجاز' );
		}
		if ( ! WBE_Plugin::licensed() ) {
			echo '<div class="wrap"><div class="notice notice-error"><p>برای ویرایش گروهی، لایسنس را فعال کنید.</p></div></div>';
			return;
		}
		$filters = array(
			'q'        => isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification
			'category' => isset( $_GET['wbe_cat'] ) ? (int) $_GET['wbe_cat'] : 0, // phpcs:ignore WordPress.Security.NonceVerification
		);
		$rows     = $this->collect_rows( $filters );
		$calendar = WBE_Settings::calendar();
		$updated  = isset( $_GET['updated'] ) ? (int) $_GET['updated'] : 0; // phpcs:ignore WordPress.Security.NonceVerification
		$skipped  = isset( $_GET['skipped'] ) ? (int) $_GET['skipped'] : 0; // phpcs:ignore WordPress.Security.NonceVerification
		$empty    = isset( $_GET['wbe_empty'] ) ? 1 : 0; // phpcs:ignore WordPress.Security.NonceVerification
		include WBE_PATH . 'includes/views/bulk-prices.php';
	}

	/**
	 * محصولات تنظیم‌شده برای جدول گروهی.
	 *
	 * @param array $filters
	 * @return array<int,array>
	 */
	public function collect_rows( array $filters ) {
		$q   = isset( $filters['q'] ) ? trim( (string) $filters['q'] ) : '';
		$cat = isset( $filters['category'] ) ? (int) $filters['category'] : 0;
		$out = array();
		foreach ( WBE_Product::configured_ids() as $id ) {
			$post = get_post( $id );
			if ( ! $post ) {
				continue;
			}
			$sku = (string) get_post_meta( $id, '_sku', true );
			if ( $q ) {
				$hay = $post->post_title . ' ' . $sku;
				if ( function_exists( 'mb_stripos' ) ) {
					if ( false === mb_stripos( $hay, $q ) ) {
						continue;
					}
				} elseif ( false === stripos( $hay, $q ) ) {
					continue;
				}
			}
			if ( $cat && function_exists( 'has_term' ) && ! has_term( $cat, 'product_cat', $id ) ) {
				continue;
			}
			$active   = WBE_Product::active( $id );
			$cal      = WBE_Product::calendar( $id );
			$regular  = $active ? (string) $active['price'] : '';
			$discount = $active ? WBE_Engine::discount_of( $active ) : 0;
			$sale     = ( '' !== $regular ) ? (string) WBE_Engine::sale_price( $regular, $discount ) : '';
			$pair     = WBE_Product::sale_ymd_pair( $id );
			$out[]    = array(
				'id'       => $id,
				'name'     => $post->post_title,
				'sku'      => $sku,
				'regular'  => $regular,
				'discount' => $discount,
				'sale'     => $sale,
				'from'     => $pair[0],
				'to'       => $pair[1],
				'from_fa'  => $pair[0] ? WBE_Jalali::format_ymd( $pair[0], $cal, false ) : '',
				'to_fa'    => $pair[1] ? WBE_Jalali::format_ymd( $pair[1], $cal, false ) : '',
				'expiry'   => $active && ! empty( $active['expiry'] ) ? WBE_Jalali::format_ymd( $active['expiry'], $cal, false ) : '',
				'has_active' => (bool) $active,
			);
		}
		return $out;
	}

	public function handle_apply() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( 'دسترسی غیرمجاز' );
		}
		check_admin_referer( 'wbe_bulk' );
		if ( ! WBE_Plugin::licensed() ) {
			wp_die( 'لایسنس نامعتبر است.' );
		}

		$calendar = WBE_Settings::calendar();
		$mode     = isset( $_POST['wbe_bulk_mode'] ) ? sanitize_key( wp_unslash( $_POST['wbe_bulk_mode'] ) ) : 'selected';
		$updated  = 0;
		$skipped  = 0;
		$empty    = 0;

		if ( 'rows' === $mode ) {
			$rows = isset( $_POST['wbe_row'] ) && is_array( $_POST['wbe_row'] ) ? wp_unslash( $_POST['wbe_row'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			foreach ( $rows as $id => $row ) {
				$ops = self::ops_from_row( $row, $calendar );
				if ( ! self::ops_meaningful( $ops ) ) {
					continue;
				}
				if ( WBE_Product::apply_bulk( (int) $id, $ops ) ) {
					$updated++;
				} else {
					$skipped++;
				}
			}
		} else {
			$ops = self::ops_from_request( $_POST, $calendar ); // phpcs:ignore WordPress.Security.NonceVerification
			$ids = isset( $_POST['ids'] ) ? array_map( 'intval', (array) wp_unslash( $_POST['ids'] ) ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			if ( ! self::ops_meaningful( $ops ) ) {
				$empty = 1;
			} elseif ( empty( $ids ) ) {
				$empty = 1;
			} else {
				foreach ( $ids as $id ) {
					if ( $id <= 0 ) {
						continue;
					}
					if ( WBE_Product::apply_bulk( $id, $ops ) ) {
						$updated++;
					} else {
						$skipped++;
					}
				}
			}
		}

		$args = array(
			'page'    => 'webakery-expiry-bulk',
			'updated' => $updated,
			'skipped' => $skipped,
		);
		if ( $empty ) {
			$args['wbe_empty'] = 1;
		}
		if ( isset( $_POST['s'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$args['s'] = sanitize_text_field( wp_unslash( $_POST['s'] ) );
		}
		if ( isset( $_POST['wbe_cat'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$args['wbe_cat'] = (int) $_POST['wbe_cat'];
		}
		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}

	public function wc_bulk_fields() {
		if ( ! current_user_can( 'edit_products' ) || ! WBE_Plugin::licensed() ) {
			return;
		}
		$modes     = self::mode_labels();
		$calendar  = WBE_Settings::calendar();
		$ph_date   = ( 'jalali' === $calendar ) ? '۱۴۰۵/۰۶/۰۵' : '2026/08/27';
		?>
		<div class="inline-edit-group wbe-wc-bulk">
			<label class="alignleft">
				<span class="title">تخفیف ٪</span>
				<span class="input-text-wrap">
					<input type="text" name="wbe_discount" class="text" placeholder="بدون تغییر" dir="ltr" />
				</span>
			</label>
			<label class="alignleft">
				<span class="title">قیمت جشنواره</span>
				<span class="input-text-wrap">
					<select name="wbe_sale_mode" class="wbe-bulk-mode">
						<?php foreach ( $modes as $key => $label ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
					<input type="text" name="wbe_sale_value" class="text wbe-bulk-value" placeholder="مبلغ" dir="ltr" />
				</span>
			</label>
			<br class="clear" />
			<label class="alignleft">
				<span class="title">جشنواره از</span>
				<span class="input-text-wrap">
					<input type="text" name="wbe_sale_from" class="text" placeholder="<?php echo esc_attr( $ph_date ); ?>" dir="ltr" />
				</span>
			</label>
			<label class="alignleft">
				<span class="title">جشنواره تا</span>
				<span class="input-text-wrap">
					<input type="text" name="wbe_sale_to" class="text" placeholder="<?php echo esc_attr( $ph_date ); ?>" dir="ltr" />
				</span>
			</label>
			<label class="alignleft">
				<span class="title">حذف تخفیف</span>
				<span class="input-text-wrap">
					<input type="checkbox" name="wbe_clear_sale" value="1" />
				</span>
			</label>
			<br class="clear" />
		</div>
		<?php
	}

	/**
	 * بعد از مچ قیمت ووکامرس (اولویت ۲۰)، فیلدهای اختصاصی جشنواره/تخفیف را اعمال کن.
	 *
	 * @param WC_Product $product
	 */
	public function wc_bulk_save( $product ) {
		if ( ! WBE_Plugin::licensed() ) {
			return;
		}
		if ( ! is_object( $product ) || ! method_exists( $product, 'get_id' ) ) {
			return;
		}
		$ops = self::ops_from_request( $_REQUEST, WBE_Settings::calendar() ); // phpcs:ignore WordPress.Security.NonceVerification
		if ( ! self::ops_meaningful( $ops ) ) {
			return;
		}
		WBE_Product::apply_bulk( (int) $product->get_id(), $ops );
	}
}
