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
		add_action( 'admin_post_wbe_bulk_csv', array( $this, 'handle_csv' ) );
		add_action( 'wp_ajax_wbe_bulk_save', array( $this, 'ajax_save' ) );
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
	 * وضعیت‌های قابل ویرایش محصول.
	 *
	 * @return array<string,string>
	 */
	public static function status_labels() {
		return array(
			'publish' => 'منتشرشده',
			'draft'   => 'پیش‌نویس',
			'private' => 'خصوصی',
			'pending' => 'در انتظار',
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

		$smode = isset( $src['wbe_stock_mode'] ) ? sanitize_key( wp_unslash( $src['wbe_stock_mode'] ) ) : 'none';
		$sval  = array_key_exists( 'wbe_stock_value', $src ) ? WBE_Engine::parse_amount( wp_unslash( $src['wbe_stock_value'] ) ) : null;
		if ( in_array( $smode, $ok, true ) && 'none' !== $smode && null !== $sval ) {
			$ops['stock_mode'] = $smode;
			$ops['stock']      = $sval;
		}

		if ( isset( $src['wbe_expiry'] ) && '' !== trim( (string) wp_unslash( $src['wbe_expiry'] ) ) ) {
			$exp = WBE_Jalali::parse_to_ymd( wp_unslash( $src['wbe_expiry'] ), $calendar );
			if ( '' !== $exp ) {
				$ops['expiry'] = $exp;
			}
		}

		if ( isset( $src['wbe_round'] ) ) {
			$round = sanitize_key( wp_unslash( $src['wbe_round'] ) );
			if ( in_array( $round, array( 'round', 'ceil', 'floor' ), true ) ) {
				$ops['round'] = $round;
			}
		}

		if ( isset( $src['wbe_set_status'] ) ) {
			$status = sanitize_key( wp_unslash( $src['wbe_set_status'] ) );
			if ( in_array( $status, array( 'publish', 'draft', 'private', 'pending' ), true ) ) {
				$ops['status'] = $status;
			}
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

		$stock = array_key_exists( 'stock', $row ) ? WBE_Engine::parse_amount( $row['stock'] ) : null;
		if ( null !== $stock ) {
			$ops['stock_mode'] = 'set';
			$ops['stock']      = $stock;
		}

		if ( isset( $row['expiry'] ) && '' !== trim( (string) $row['expiry'] ) ) {
			$exp = WBE_Jalali::parse_to_ymd( $row['expiry'], $calendar );
			if ( '' !== $exp ) {
				$ops['expiry'] = $exp;
			}
		}

		if ( isset( $row['name'] ) && '' !== trim( (string) $row['name'] ) ) {
			$ops['name'] = function_exists( 'sanitize_text_field' ) ? sanitize_text_field( $row['name'] ) : trim( (string) $row['name'] );
		}
		if ( array_key_exists( 'sku', $row ) ) {
			$ops['sku'] = function_exists( 'sanitize_text_field' ) ? sanitize_text_field( $row['sku'] ) : (string) $row['sku'];
		}
		if ( isset( $row['status'] ) ) {
			$status = sanitize_key( (string) $row['status'] );
			if ( in_array( $status, array( 'publish', 'draft', 'private', 'pending' ), true ) ) {
				$ops['status'] = $status;
			}
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
		if ( WBE_Engine::has_batch_ops( $ops ) ) {
			return true;
		}
		if ( ! empty( $ops['name'] ) || array_key_exists( 'sku', $ops ) || ! empty( $ops['status'] ) ) {
			return true;
		}
		return ( isset( $ops['sale_from'] ) && '' !== $ops['sale_from'] )
			|| ( isset( $ops['sale_to'] ) && '' !== $ops['sale_to'] );
	}

	/**
	 * تعداد محصول در هر تکهٔ AJAX.
	 *
	 * @return int
	 */
	public static function chunk_size() {
		return 40;
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
			'scope'    => isset( $_GET['wbe_scope'] ) ? sanitize_key( wp_unslash( $_GET['wbe_scope'] ) ) : 'all', // phpcs:ignore WordPress.Security.NonceVerification
			'status'   => isset( $_GET['wbe_status'] ) ? sanitize_key( wp_unslash( $_GET['wbe_status'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification
		);
		if ( ! in_array( $filters['scope'], array( 'all', 'batches', 'plain' ), true ) ) {
			$filters['scope'] = 'all';
		}
		$rows     = $this->collect_rows( $filters );
		$calendar = WBE_Settings::calendar();
		$updated  = isset( $_GET['updated'] ) ? (int) $_GET['updated'] : 0; // phpcs:ignore WordPress.Security.NonceVerification
		$skipped  = isset( $_GET['skipped'] ) ? (int) $_GET['skipped'] : 0; // phpcs:ignore WordPress.Security.NonceVerification
		$empty    = isset( $_GET['wbe_empty'] ) ? 1 : 0; // phpcs:ignore WordPress.Security.NonceVerification
		include WBE_PATH . 'includes/views/bulk-prices.php';
	}

	/**
		 * محصولات ووکامرس برای جدول گروهی (با یا بدون بچ).
	 *
	 * @param array $filters
	 * @return array<int,array>
	 */
	public function collect_rows( array $filters ) {
		global $wpdb;
		if ( ! isset( $wpdb ) || ! is_object( $wpdb ) ) {
			return array();
		}

		$q           = isset( $filters['q'] ) ? trim( (string) $filters['q'] ) : '';
		$cat         = isset( $filters['category'] ) ? (int) $filters['category'] : 0;
		$scope       = isset( $filters['scope'] ) ? (string) $filters['scope'] : 'all';
		$status_f    = isset( $filters['status'] ) ? (string) $filters['status'] : '';
		$today       = WBE_Jalali::today_ymd();
		$default_cal = WBE_Settings::calendar();

		$sql = "SELECT p.ID, p.post_title, p.post_status,
				sku.meta_value AS sku,
				batches.meta_value AS batches_raw,
				cal.meta_value AS calendar,
				sf.meta_value AS sale_from,
				st.meta_value AS sale_to,
				reg.meta_value AS wc_regular,
				sale.meta_value AS wc_sale,
				stock.meta_value AS wc_stock
			FROM {$wpdb->posts} p
			LEFT JOIN {$wpdb->postmeta} batches ON batches.post_id = p.ID AND batches.meta_key = '_wbe_batches'
			LEFT JOIN {$wpdb->postmeta} sku ON sku.post_id = p.ID AND sku.meta_key = '_sku'
			LEFT JOIN {$wpdb->postmeta} cal ON cal.post_id = p.ID AND cal.meta_key = '_wbe_calendar'
			LEFT JOIN {$wpdb->postmeta} sf ON sf.post_id = p.ID AND sf.meta_key = '_sale_price_dates_from'
			LEFT JOIN {$wpdb->postmeta} st ON st.post_id = p.ID AND st.meta_key = '_sale_price_dates_to'
			LEFT JOIN {$wpdb->postmeta} reg ON reg.post_id = p.ID AND reg.meta_key = '_regular_price'
			LEFT JOIN {$wpdb->postmeta} sale ON sale.post_id = p.ID AND sale.meta_key = '_sale_price'
			LEFT JOIN {$wpdb->postmeta} stock ON stock.post_id = p.ID AND stock.meta_key = '_stock'
			WHERE p.post_type = 'product'
			AND p.post_status IN ('publish','private','draft','pending')";

		$args = array();
		if ( $status_f && in_array( $status_f, array( 'publish', 'draft', 'private', 'pending' ), true ) ) {
			$sql   .= ' AND p.post_status = %s';
			$args[] = $status_f;
		}
		if ( $cat && function_exists( 'get_term_children' ) ) {
			$term_ids = array( $cat );
			$kids     = get_term_children( $cat, 'product_cat' );
			if ( ! is_wp_error( $kids ) && $kids ) {
				$term_ids = array_merge( $term_ids, array_map( 'intval', $kids ) );
			}
			$term_ids = array_filter( array_map( 'intval', $term_ids ) );
			if ( $term_ids ) {
				$in   = implode( ',', $term_ids );
				$sql .= " AND p.ID IN (
					SELECT tr.object_id FROM {$wpdb->term_relationships} tr
					INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
					WHERE tt.taxonomy = 'product_cat' AND tt.term_id IN ({$in})
				)";
			}
		}
		if ( $q ) {
			$like   = '%' . $wpdb->esc_like( $q ) . '%';
			$sql   .= ' AND (p.post_title LIKE %s OR sku.meta_value LIKE %s)';
			$args[] = $like;
			$args[] = $like;
		}
		$sql .= ' ORDER BY p.post_title ASC LIMIT 2500';
		if ( $args ) {
			$sql = $wpdb->prepare( $sql, $args ); // phpcs:ignore WordPress.DB.PreparedSQL
		}

		$records = $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL
		if ( ! $records ) {
			return array();
		}

		$out = array();
		foreach ( $records as $rec ) {
			$batches = maybe_unserialize( $rec->batches_raw );
			if ( ! is_array( $batches ) ) {
				$batches = array();
			}
			$has_batches = ! empty( $batches );
			if ( 'batches' === $scope && ! $has_batches ) {
				continue;
			}
			if ( 'plain' === $scope && $has_batches ) {
				continue;
			}
			$cal   = ( 'jalali' === $rec->calendar || 'gregorian' === $rec->calendar ) ? $rec->calendar : $default_cal;
			$out[] = WBE_Engine::bulk_row_from_record(
				$rec->ID,
				$rec->post_title,
				isset( $rec->sku ) ? $rec->sku : '',
				$batches,
				$cal,
				$rec->sale_from,
				$rec->sale_to,
				$today,
				array(
					'regular' => isset( $rec->wc_regular ) ? $rec->wc_regular : '',
					'sale'    => isset( $rec->wc_sale ) ? $rec->wc_sale : '',
					'stock'   => isset( $rec->wc_stock ) ? $rec->wc_stock : '',
					'status'  => isset( $rec->post_status ) ? $rec->post_status : 'publish',
				)
			);
		}
		return $out;
	}

	/**
	 * ذخیرهٔ تکه‌ای با AJAX تا تایم‌اوت نشود.
	 */
	public function ajax_save() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => 'دسترسی غیرمجاز' ), 403 );
		}
		check_ajax_referer( 'wbe_bulk', 'nonce' );
		if ( ! WBE_Plugin::licensed() ) {
			wp_send_json_error( array( 'message' => 'لایسنس نامعتبر است.' ), 403 );
		}

		$calendar = WBE_Settings::calendar();
		$limit    = self::chunk_size();
		$mode     = isset( $_POST['wbe_bulk_mode'] ) ? sanitize_key( wp_unslash( $_POST['wbe_bulk_mode'] ) ) : 'rows';
		$updated  = 0;
		$skipped  = 0;

		if ( 'selected' === $mode ) {
			$ops = self::ops_from_request( $_POST, $calendar ); // phpcs:ignore WordPress.Security.NonceVerification
			$ids = isset( $_POST['ids'] ) ? array_map( 'intval', (array) wp_unslash( $_POST['ids'] ) ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			$ids = array_slice( array_values( array_filter( $ids ) ), 0, $limit );
			if ( ! self::ops_meaningful( $ops ) || ! $ids ) {
				wp_send_json_success(
					array(
						'updated' => 0,
						'skipped' => 0,
						'empty'   => 1,
					)
				);
			}
			foreach ( $ids as $id ) {
				if ( WBE_Product::apply_bulk( $id, $ops, false ) ) {
					$updated++;
				} else {
					$skipped++;
				}
			}
		} else {
			$rows = isset( $_POST['wbe_row'] ) && is_array( $_POST['wbe_row'] ) ? wp_unslash( $_POST['wbe_row'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			$n    = 0;
			foreach ( $rows as $id => $row ) {
				if ( $n >= $limit ) {
					break;
				}
				$ops = self::ops_from_row( $row, $calendar );
				if ( ! self::ops_meaningful( $ops ) ) {
					continue;
				}
				$n++;
				if ( WBE_Product::apply_bulk( (int) $id, $ops, false ) ) {
					$updated++;
				} else {
					$skipped++;
				}
			}
		}

		if ( class_exists( 'WBE_Alerts' ) ) {
			WBE_Alerts::flush();
		}

		wp_send_json_success(
			array(
				'updated' => $updated,
				'skipped' => $skipped,
				'chunk'   => $limit,
			)
		);
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
				if ( WBE_Product::apply_bulk( (int) $id, $ops, false ) ) {
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
					if ( WBE_Product::apply_bulk( $id, $ops, false ) ) {
						$updated++;
					} else {
						$skipped++;
					}
				}
			}
		}
		if ( class_exists( 'WBE_Alerts' ) ) {
			WBE_Alerts::flush();
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
		if ( isset( $_POST['wbe_scope'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$args['wbe_scope'] = sanitize_key( wp_unslash( $_POST['wbe_scope'] ) );
		}
		if ( isset( $_POST['wbe_status'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$args['wbe_status'] = sanitize_key( wp_unslash( $_POST['wbe_status'] ) );
		}
		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}

	public function handle_csv() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( 'دسترسی غیرمجاز' );
		}
		check_admin_referer( 'wbe_bulk_csv' );
		if ( ! WBE_Plugin::licensed() ) {
			wp_die( 'لایسنس نامعتبر است.' );
		}
		$filters = array(
			'q'        => isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '',
			'category' => isset( $_GET['wbe_cat'] ) ? (int) $_GET['wbe_cat'] : 0,
			'scope'    => isset( $_GET['wbe_scope'] ) ? sanitize_key( wp_unslash( $_GET['wbe_scope'] ) ) : 'all',
			'status'   => isset( $_GET['wbe_status'] ) ? sanitize_key( wp_unslash( $_GET['wbe_status'] ) ) : '',
		);
		$rows = $this->collect_rows( $filters );
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=wbe-products.csv' );
		$out = fopen( 'php://output', 'w' );
		fprintf( $out, chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ) );
		fputcsv(
			$out,
			array( 'ID', 'نام', 'SKU', 'وضعیت', 'قیمت اصلی', 'تخفیف', 'جشنواره', 'از', 'تا', 'موجودی', 'انقضا' )
		);
		foreach ( $rows as $r ) {
			fputcsv(
				$out,
				array(
					$r['id'],
					$r['name'],
					$r['sku'],
					$r['status'],
					$r['regular'],
					$r['discount'],
					$r['sale'],
					$r['from_fa'],
					$r['to_fa'],
					$r['stock'],
					$r['expiry_fa'],
				)
			);
		}
		fclose( $out );
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
