<?php
defined( 'ABSPATH' ) || exit;

/**
 * خروجی Google Sheets — ساده:
 * با جیمیل کاربر (Google Sign-In) شیت در Drive خودش ساخته می‌شود و داده نوشته می‌شود.
 */
class WAP_Google_Sheets {

	const OPTION_CLIENT_ID = 'wap_google_client_id';
	const CSV_TTL          = 3600;

	public static function client_id(): string {
		$id = trim( (string) get_option( self::OPTION_CLIENT_ID, '' ) );
		/**
		 * می‌توانید یک Client ID سراسری برگردانید تا مشتری تنظیم نکند.
		 * دامنه سایت باید در Google Cloud → Authorized JavaScript origins باشد.
		 */
		return (string) apply_filters( 'hesabdar_google_client_id', $id );
	}

	public static function is_configured(): bool {
		return self::client_id() !== '';
	}

	public static function site_origin(): string {
		$home = home_url( '/' );
		$parts = wp_parse_url( $home );
		if ( empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
			return $home;
		}
		$origin = $parts['scheme'] . '://' . $parts['host'];
		if ( ! empty( $parts['port'] ) ) {
			$origin .= ':' . $parts['port'];
		}
		return $origin;
	}

	/**
	 * @return array{title:string,rows:array<int,array<int,string>>}
	 */
	public static function build_matrix_from_request(): array {
		$view = sanitize_text_field( $_REQUEST['wap_view'] ?? 'sales' );
		if ( ! in_array( $view, array( 'sales', 'orders', 'products' ), true ) ) {
			$view = 'sales';
		}

		if ( $view === 'orders' ) {
			$f = WAP_Data::get_order_list_filters();
			list( , , $orders ) = WAP_Data::get_filtered_order_list( $f );
			$extra   = WAP_Baget_Fields::get_export_columns();
			$headers = array_merge(
				array( 'شماره سفارش', 'نام', 'نام خانوادگی', 'تلفن', 'شهر', 'محصول', 'روش پرداخت', 'وضعیت', 'مبلغ کل', 'تاریخ' ),
				array_values( $extra )
			);
			$rows = array( $headers );
			foreach ( $orders as $order ) {
				$line = array(
					(string) $order->get_order_number(),
					(string) $order->get_billing_first_name(),
					(string) $order->get_billing_last_name(),
					(string) $order->get_billing_phone(),
					(string) $order->get_billing_city(),
					WAP_Data::order_products_summary( $order ),
					WAP_Data::payment_label( $order->get_payment_method() ),
					wc_get_order_status_name( $order->get_status() ),
					(string) $order->get_total(),
					wc_format_datetime( $order->get_date_created() ),
				);
				foreach ( array_keys( $extra ) as $meta_key ) {
					$line[] = WAP_Baget_Fields::get_order_field_value( $order, $meta_key );
				}
				$rows[] = self::stringify_row( $line );
			}
			return array( 'title' => 'Hesabdar — سفارش‌ها — ' . date_i18n( 'Y-m-d H:i' ), 'rows' => $rows );
		}

		if ( $view === 'products' ) {
			$f          = WAP_Data::get_filters();
			$orders     = WAP_Data::get_orders( $f );
			$product_id = ! empty( $_REQUEST['product_id'] ) ? (int) $_REQUEST['product_id'] : 0;

			if ( $product_id ) {
				$product = wc_get_product( $product_id );
				$name    = $product ? $product->get_name() : ( '#' . $product_id );
				$extra   = WAP_Baget_Fields::get_export_columns();
				$headers = array_merge(
					array( 'شماره سفارش', 'نام خریدار', 'تلفن', 'ایمیل', 'تاریخ', 'تعداد', 'مبلغ', 'وضعیت' ),
					array_values( $extra )
				);
				$rows = array( $headers );
				foreach ( WAP_Data::get_product_drilldown( $orders, $product_id ) as $row ) {
					$order = $row['order'];
					$buyer = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
					$line  = array(
						(string) $order->get_order_number(),
						$buyer ?: (string) $order->get_billing_email(),
						(string) $order->get_billing_phone(),
						(string) $order->get_billing_email(),
						wc_format_datetime( $order->get_date_created() ),
						(string) $row['qty'],
						(string) $row['revenue'],
						wc_get_order_status_name( $order->get_status() ),
					);
					foreach ( array_keys( $extra ) as $meta_key ) {
						$line[] = WAP_Baget_Fields::get_order_field_value( $order, $meta_key );
					}
					$rows[] = self::stringify_row( $line );
				}
				return array( 'title' => 'Hesabdar — ' . $name . ' — ' . date_i18n( 'Y-m-d H:i' ), 'rows' => $rows );
			}

			$rows = array( array( 'نام محصول', 'SKU', 'تعداد فروخته‌شده', 'تعداد سفارشات', 'درآمد کل' ) );
			foreach ( WAP_Data::get_product_sales( $orders ) as $p ) {
				$rows[] = self::stringify_row( array( $p['name'], $p['sku'], $p['qty'], $p['orders'], $p['revenue'] ) );
			}
			return array( 'title' => 'Hesabdar — فروش محصولات — ' . date_i18n( 'Y-m-d H:i' ), 'rows' => $rows );
		}

		$f      = WAP_Data::get_filters();
		$orders = WAP_Data::get_orders( $f );
		$groups = WAP_Data::apply_filter( WAP_Data::build_rows( $orders, $f['period'] ), $f );
		$rows   = array( array( 'دوره', 'تعداد فروش', 'مبلغ فروش', 'میانگین هر سفارش' ) );
		foreach ( $groups as $g ) {
			$avg    = $g['count'] > 0 ? $g['total'] / $g['count'] : 0;
			$rows[] = self::stringify_row( array( $g['label'], $g['count'], $g['total'], round( $avg, 2 ) ) );
		}
		return array( 'title' => 'Hesabdar — گزارش مالی — ' . date_i18n( 'Y-m-d H:i' ), 'rows' => $rows );
	}

	/**
	 * @param array<int,mixed> $row
	 * @return array<int,string>
	 */
	private static function stringify_row( array $row ): array {
		$out = array();
		foreach ( $row as $cell ) {
			if ( is_bool( $cell ) ) {
				$out[] = $cell ? '1' : '0';
			} elseif ( is_scalar( $cell ) || $cell === null ) {
				$out[] = (string) $cell;
			} else {
				$out[] = wp_json_encode( $cell, JSON_UNESCAPED_UNICODE );
			}
		}
		return $out;
	}

	public static function store_temp_csv( array $rows ): string {
		$token = wp_generate_password( 32, false, false );
		set_transient( 'wap_gsheets_csv_' . $token, array( 'rows' => $rows ), self::CSV_TTL );
		return add_query_arg(
			array(
				'action'    => 'wap_sheets_csv',
				'wap_token' => $token,
			),
			admin_url( 'admin-post.php' )
		);
	}

	public static function handle_temp_csv_download() {
		$token = sanitize_text_field( $_GET['wap_token'] ?? '' );
		$pack  = $token ? get_transient( 'wap_gsheets_csv_' . $token ) : false;
		if ( ! is_array( $pack ) || empty( $pack['rows'] ) ) {
			wp_die( 'لینک منقضی شده است.', 'Hesabdar', array( 'response' => 410 ) );
		}
		while ( ob_get_level() ) {
			ob_end_clean();
		}
		header( 'Content-Type: text/csv; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="hesabdar-' . date( 'Y-m-d-His' ) . '.csv"' );
		$fp = fopen( 'php://output', 'w' );
		fputs( $fp, "\xEF\xBB\xBF" );
		foreach ( $pack['rows'] as $row ) {
			fputcsv( $fp, $row );
		}
		fclose( $fp );
		exit;
	}

	public static function handle_ajax_export() {
		if ( ! is_user_logged_in() || ! WAP_Portal::current_user_allowed() ) {
			wp_send_json_error( array( 'message' => 'دسترسی غیرمجاز.' ), 403 );
		}
		check_ajax_referer( 'wap_sheets_export', 'nonce' );

		$matrix = self::build_matrix_from_request();
		$rows   = $matrix['rows'];
		if ( count( $rows ) <= 1 ) {
			wp_send_json_error( array( 'message' => 'داده‌ای برای خروجی نیست.' ) );
		}

		$max = (int) apply_filters( 'hesabdar_sheets_max_rows', 5000 );
		if ( count( $rows ) > $max ) {
			$rows = array_slice( $rows, 0, $max );
		}

		wp_send_json_success(
			array(
				'mode'     => 'google_oauth',
				'title'    => $matrix['title'],
				'rows'     => $rows,
				'csv_url'  => self::store_temp_csv( $rows ),
				'clientId' => self::client_id(),
				'origin'   => self::site_origin(),
			)
		);
	}

	public static function handle_ajax_save_client_id() {
		if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'فقط مدیر سایت.' ), 403 );
		}
		check_ajax_referer( 'wap_sheets_export', 'nonce' );
		$id = preg_replace( '/\s+/', '', (string) wp_unslash( $_POST['client_id'] ?? '' ) );
		if ( $id === '' ) {
			delete_option( self::OPTION_CLIENT_ID );
			wp_send_json_success( array( 'clientId' => '', 'message' => 'پاک شد.' ) );
		}
		if ( ! preg_match( '/\.apps\.googleusercontent\.com$/', $id ) ) {
			wp_send_json_error( array( 'message' => 'Client ID نامعتبر است.' ) );
		}
		update_option( self::OPTION_CLIENT_ID, $id, false );
		wp_send_json_success( array( 'clientId' => $id, 'message' => 'ذخیره شد.' ) );
	}
}
