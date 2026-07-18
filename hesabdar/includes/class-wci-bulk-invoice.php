<?php
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WCI_Bulk_Invoice' ) ) :

/**
 * چاپ دسته‌جمعی فاکتورها — بدون سقف تعداد.
 */
class WCI_Bulk_Invoice {

	const TRANSIENT_PREFIX = 'wci_bulk_inv_';
	const TRANSIENT_TTL    = 30 * MINUTE_IN_SECONDS;

	/** افزایش منابع برای چاپ تعداد زیاد */
	public static function boost_resources(): void {
		if ( function_exists( 'wp_raise_memory_limit' ) ) {
			wp_raise_memory_limit( 'admin' );
		}
		@ini_set( 'memory_limit', '1024M' ); // phpcs:ignore
		@set_time_limit( 0 ); // phpcs:ignore
		@ini_set( 'max_execution_time', '0' ); // phpcs:ignore
		ignore_user_abort( true );
		while ( ob_get_level() > 0 ) {
			ob_end_clean();
		}
	}

	/**
	 * خواندن شناسه سفارش‌ها از درخواست — پشتیبانی از JSON برای دور زدن max_input_vars.
	 *
	 * @param array<string,mixed>|null $src
	 * @return array<int>
	 */
	public static function parse_order_ids( $src = null ): array {
		$src = is_array( $src ) ? $src : wp_unslash( $_REQUEST );

		$ids = array();
		if ( ! empty( $src['order_ids_json'] ) ) {
			$raw = is_string( $src['order_ids_json'] ) ? $src['order_ids_json'] : '';
			$decoded = json_decode( $raw, true );
			if ( is_array( $decoded ) ) {
				$ids = $decoded;
			}
		} elseif ( ! empty( $src['order_ids'] ) && is_array( $src['order_ids'] ) ) {
			$ids = $src['order_ids'];
		} elseif ( ! empty( $src['order_ids'] ) && is_string( $src['order_ids'] ) ) {
			$ids = preg_split( '/[\s,]+/', $src['order_ids'] );
		}

		$ids = array_values( array_unique( array_filter( array_map( 'absint', (array) $ids ) ) ) );
		return $ids;
	}

	/**
	 * ذخیره لیست شناسه‌ها و برگرداندن کلید موقت.
	 *
	 * @param array<int> $order_ids
	 */
	public static function store_ids( array $order_ids ): string {
		$order_ids = array_values( array_unique( array_filter( array_map( 'absint', $order_ids ) ) ) );
		$key       = wp_generate_password( 20, false, false );
		set_transient( self::TRANSIENT_PREFIX . $key, $order_ids, self::TRANSIENT_TTL );
		return $key;
	}

	/**
	 * @return array<int>
	 */
	public static function load_ids( string $key ): array {
		$key  = preg_replace( '/[^a-zA-Z0-9]/', '', $key );
		$data = get_transient( self::TRANSIENT_PREFIX . $key );
		return is_array( $data ) ? array_values( array_filter( array_map( 'absint', $data ) ) ) : array();
	}

	public static function print_url( string $key, string $context = 'admin' ): string {
		$args = array(
			'action'   => 'wci_bulk_print',
			'bulk_key' => $key,
		);
		$url = wp_nonce_url( add_query_arg( $args, admin_url( 'admin-post.php' ) ), 'wci_bulk_print_' . $key );
		return $url;
	}

	/**
	 * شروع چاپ دسته‌جمعی: ذخیره + URL ریدایرکت.
	 *
	 * @param array<int> $order_ids
	 * @return array{ok:bool,message:string,redirect?:string,count?:int}
	 */
	public static function start( array $order_ids ): array {
		$order_ids = array_values( array_unique( array_filter( array_map( 'absint', $order_ids ) ) ) );
		if ( empty( $order_ids ) ) {
			return array( 'ok' => false, 'message' => 'هیچ سفارشی برای چاپ فاکتور انتخاب نشده است.' );
		}

		$key = self::store_ids( $order_ids );
		return array(
			'ok'       => true,
			'message'  => count( $order_ids ) . ' فاکتور آماده چاپ است…',
			'redirect' => self::print_url( $key ),
			'count'    => count( $order_ids ),
		);
	}

	/** هندل admin-post */
	public static function handle_admin_post(): void {
		if ( ! is_user_logged_in() ) {
			auth_redirect();
		}

		$key = isset( $_GET['bulk_key'] ) ? sanitize_text_field( wp_unslash( $_GET['bulk_key'] ) ) : '';
		check_admin_referer( 'wci_bulk_print_' . $key );

		$can = false;
		if ( class_exists( 'WAP_Order_Service' ) && WAP_Order_Service::can_change_status() ) {
			$can = true;
		} elseif ( current_user_can( 'manage_options' ) || current_user_can( 'manage_woocommerce' ) ) {
			$can = true;
		} elseif ( class_exists( 'WAP_Portal' ) && current_user_can( WAP_Portal::CAP ) ) {
			$can = true;
		}
		if ( ! $can ) {
			wp_die( 'دسترسی چاپ فاکتور ندارید.' );
		}

		$ids = self::load_ids( $key );
		if ( empty( $ids ) ) {
			wp_die( 'لیست فاکتورها منقضی شده یا خالی است. دوباره از لیست سفارش‌ها چاپ بگیرید.' );
		}

		self::render( $ids );
	}

	/**
	 * خروجی HTML همه فاکتورها برای چاپ — بدون سقف تعداد.
	 *
	 * @param array<int> $order_ids
	 */
	public static function render( array $order_ids ): void {
		self::boost_resources();

		$order_ids = array_values( array_unique( array_filter( array_map( 'absint', $order_ids ) ) ) );
		$s         = get_option( 'wci_invoice_settings', array() );
		$color     = $s['primary_color'] ?? '#2271b1';
		$total     = count( $order_ids );

		nocache_headers();
		header( 'Content-Type: text/html; charset=utf-8' );

		echo '<!DOCTYPE html><html dir="rtl" lang="fa"><head><meta charset="UTF-8">';
		echo '<title>چاپ دسته‌جمعی ' . esc_html( (string) $total ) . ' فاکتور</title>';
		echo '<style>';
		echo self::shared_css( $color );
		echo '
			.bulk-toolbar{position:sticky;top:0;z-index:50;background:#0f172a;color:#fff;padding:12px 18px;display:flex;gap:12px;flex-wrap:wrap;align-items:center;justify-content:space-between}
			.bulk-toolbar button,.bulk-toolbar a{padding:8px 16px;border:0;border-radius:8px;cursor:pointer;font-size:13px;text-decoration:none;color:#fff}
			.bulk-toolbar .btn-print{background:#16a34a}
			.bulk-toolbar .btn-close{background:#64748b}
			.bulk-count{font-weight:700}
			.invoice-page{page-break-after:always;break-after:page;padding:12px 0}
			.invoice-page:last-child{page-break-after:auto;break-after:auto}
			@media print{
				.bulk-toolbar,.no-print{display:none!important}
				body{background:#fff}
				.invoice-wrap{box-shadow:none;margin:0;max-width:100%}
				.invoice-page{padding:0}
			}
		';
		echo '</style></head><body>';

		echo '<div class="bulk-toolbar no-print">';
		echo '<div><span class="bulk-count">' . esc_html( number_format_i18n( $total ) ) . '</span> فاکتور آماده چاپ — بدون محدودیت تعداد</div>';
		echo '<div>';
		echo '<button type="button" class="btn-print" onclick="window.print()">🖨 چاپ همه</button> ';
		echo '<button type="button" class="btn-close" onclick="window.close()">بستن</button>';
		echo '</div></div>';

		$printed = 0;
		foreach ( $order_ids as $order_id ) {
			$order = wc_get_order( $order_id );
			if ( ! $order ) {
				continue;
			}
			echo '<div class="invoice-page">';
			self::echo_invoice_card( $order, $s, $color );
			echo '</div>';
			$printed++;
			if ( 0 === $printed % 10 ) {
				echo "\n";
				flush();
			}
		}

		if ( 0 === $printed ) {
			echo '<p style="text-align:center;padding:40px">هیچ فاکتور معتبری یافت نشد.</p>';
		} else {
			echo '<script>window.addEventListener("load",function(){setTimeout(function(){window.print();},400);});</script>';
		}

		echo '</body></html>';
		exit;
	}

	private static function shared_css( string $color ): string {
		$c = esc_attr( $color );
		return "
			*{box-sizing:border-box;margin:0;padding:0}
			body{font-family:Tahoma,Arial,sans-serif;font-size:13px;color:#333;direction:rtl;background:#f5f5f5}
			.invoice-wrap{max-width:820px;margin:20px auto;background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 2px 20px rgba(0,0,0,.12)}
			.inv-header{background:{$c};color:#fff;padding:28px 36px;display:flex;justify-content:space-between;align-items:center}
			.inv-header h1{font-size:26px;font-weight:bold}
			.inv-header .logo img{max-height:70px;max-width:180px}
			.inv-meta{padding:20px 36px;background:#f8f8f8;border-bottom:1px solid #e5e5e5;display:flex;gap:40px}
			.inv-meta dl{display:grid;grid-template-columns:auto 1fr;gap:4px 12px}
			.inv-meta dt{font-weight:bold;color:#555}
			.inv-body{padding:24px 36px}
			.inv-section-title{font-size:14px;font-weight:bold;color:{$c};border-bottom:2px solid {$c};padding-bottom:6px;margin:20px 0 12px}
			.customer-grid{display:grid;grid-template-columns:1fr 1fr;gap:8px 20px}
			.customer-grid .row{display:flex;gap:8px}
			.customer-grid .lbl{color:#777;min-width:110px}
			table.items{width:100%;border-collapse:collapse;margin-top:8px}
			table.items th{background:{$c};color:#fff;padding:9px 12px;text-align:right;font-size:12px}
			table.items td{padding:8px 12px;border-bottom:1px solid #eee}
			table.items tr:nth-child(even) td{background:#f9f9f9}
			.totals{display:flex;justify-content:flex-start;margin-top:16px}
			.totals table{border:1px solid #e5e5e5;border-radius:6px;overflow:hidden;min-width:260px}
			.totals td{padding:7px 14px}
			.totals tr:last-child td{font-weight:bold;background:{$c};color:#fff;font-size:14px}
			.inv-footer{background:#f8f8f8;border-top:1px solid #e5e5e5;padding:16px 36px;text-align:center;color:#888;font-size:12px}
			.signature-box{display:flex;justify-content:flex-end;margin-top:40px}
			.signature-inner{border:1px solid #ccc;border-radius:6px;padding:16px 24px;text-align:center;min-width:180px}
			.signature-inner p{color:#888;font-size:11px;margin-top:50px}
		";
	}

	/**
	 * @param WC_Order             $order
	 * @param array<string,mixed>  $s
	 */
	public static function echo_invoice_card( $order, array $s, string $color ): void {
		$logo       = $s['logo_url'] ?? '';
		$state_code = $order->get_billing_state();
		$country    = $order->get_billing_country() ?: 'IR';
		$states     = WC()->countries->get_states( $country ) ?? array();
		$state_name = $states[ $state_code ] ?? $state_code;
		?>
		<div class="invoice-wrap">
			<div class="inv-header">
				<div>
					<h1>فاکتور سفارش</h1>
					<div style="margin-top:6px;font-size:14px">#<?php echo esc_html( $order->get_order_number() ); ?></div>
				</div>
				<div class="logo">
					<?php if ( $logo ) : ?>
						<img src="<?php echo esc_url( $logo ); ?>" alt="لوگو">
					<?php else : ?>
						<div style="font-size:20px;font-weight:bold"><?php echo esc_html( $s['company_name'] ?? get_bloginfo( 'name' ) ); ?></div>
					<?php endif; ?>
				</div>
			</div>
			<div class="inv-meta">
				<dl>
					<dt>تاریخ:</dt>
					<dd><?php echo esc_html( wc_format_datetime( $order->get_date_created() ) ); ?></dd>
					<dt>وضعیت:</dt>
					<dd><?php echo esc_html( wc_get_order_status_name( $order->get_status() ) ); ?></dd>
					<dt>روش پرداخت:</dt>
					<dd><?php echo esc_html( function_exists( 'wci_payment_label' ) ? wci_payment_label( $order->get_payment_method() ) : $order->get_payment_method_title() ); ?></dd>
					<?php if ( $order->get_transaction_id() ) : ?>
					<dt>کد پیگیری:</dt>
					<dd><?php echo esc_html( $order->get_transaction_id() ); ?></dd>
					<?php endif; ?>
				</dl>
				<dl>
					<?php if ( ! empty( $s['company_address'] ) ) : ?>
					<dt>آدرس فروشگاه:</dt>
					<dd><?php echo nl2br( esc_html( $s['company_address'] ) ); ?></dd>
					<?php endif; ?>
					<?php if ( ! empty( $s['company_phone'] ) ) : ?>
					<dt>تلفن فروشگاه:</dt>
					<dd><?php echo esc_html( $s['company_phone'] ); ?></dd>
					<?php endif; ?>
				</dl>
			</div>
			<div class="inv-body">
				<div class="inv-section-title">اطلاعات خریدار</div>
				<div class="customer-grid">
					<div class="row"><span class="lbl">نام و نام خانوادگی:</span><span><?php echo esc_html( $order->get_formatted_billing_full_name() ); ?></span></div>
					<div class="row"><span class="lbl">شماره تماس:</span><span><?php echo esc_html( $order->get_billing_phone() ); ?></span></div>
					<div class="row"><span class="lbl">ایمیل:</span><span><?php echo esc_html( $order->get_billing_email() ); ?></span></div>
					<div class="row"><span class="lbl">شهر:</span><span><?php echo esc_html( $order->get_billing_city() ); ?></span></div>
					<div class="row"><span class="lbl">استان:</span><span><?php echo esc_html( $state_name ); ?></span></div>
					<div class="row"><span class="lbl">کد پستی:</span><span><?php echo esc_html( $order->get_billing_postcode() ); ?></span></div>
					<div class="row" style="grid-column:span 2"><span class="lbl">آدرس:</span><span><?php echo esc_html( $order->get_billing_address_1() . ( $order->get_billing_address_2() ? ' ' . $order->get_billing_address_2() : '' ) ); ?></span></div>
					<?php
					if ( class_exists( 'WAP_Baget_Fields' ) ) {
						foreach ( WAP_Baget_Fields::get_invoice_fields( $order ) as $label => $value ) {
							echo '<div class="row"><span class="lbl">' . esc_html( $label ) . ':</span><span>' . esc_html( $value ) . '</span></div>';
						}
					}
					?>
				</div>
				<div class="inv-section-title">اقلام سفارش</div>
				<table class="items">
					<thead><tr><th>ردیف</th><th>محصول</th><th>تعداد</th><th>قیمت واحد</th><th>جمع</th></tr></thead>
					<tbody>
					<?php
					$i = 1;
					foreach ( $order->get_items() as $item ) :
						$product    = $item->get_product();
						$unit_price = $item->get_total() / max( 1, $item->get_quantity() );
						?>
						<tr>
							<td><?php echo (int) $i++; ?></td>
							<td>
								<?php echo esc_html( $item->get_name() ); ?>
								<?php if ( $product && $product->get_sku() ) : ?>
									<br><small style="color:#999">SKU: <?php echo esc_html( $product->get_sku() ); ?></small>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( (string) $item->get_quantity() ); ?></td>
							<td><?php echo wp_kses_post( wc_price( $unit_price ) ); ?></td>
							<td><?php echo wp_kses_post( wc_price( $item->get_total() ) ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				<div class="totals">
					<table>
						<tr><td>جمع کل محصولات:</td><td><?php echo wp_kses_post( wc_price( $order->get_subtotal() ) ); ?></td></tr>
						<?php if ( $order->get_shipping_total() > 0 ) : ?>
						<tr><td>هزینه ارسال:</td><td><?php echo wp_kses_post( wc_price( $order->get_shipping_total() ) ); ?></td></tr>
						<?php endif; ?>
						<?php if ( $order->get_discount_total() > 0 ) : ?>
						<tr><td>تخفیف:</td><td>-<?php echo wp_kses_post( wc_price( $order->get_discount_total() ) ); ?></td></tr>
						<?php endif; ?>
						<?php if ( $order->get_total_tax() > 0 ) : ?>
						<tr><td>مالیات:</td><td><?php echo wp_kses_post( wc_price( $order->get_total_tax() ) ); ?></td></tr>
						<?php endif; ?>
						<tr><td>مبلغ نهایی:</td><td><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></td></tr>
					</table>
				</div>
				<?php if ( $order->get_customer_note() ) : ?>
				<div class="inv-section-title">یادداشت مشتری</div>
				<p><?php echo nl2br( esc_html( $order->get_customer_note() ) ); ?></p>
				<?php endif; ?>
				<?php if ( ! empty( $s['show_signature'] ) ) : ?>
				<div class="signature-box">
					<div class="signature-inner">
						<div style="font-weight:bold"><?php echo esc_html( $s['company_name'] ?? get_bloginfo( 'name' ) ); ?></div>
						<p>مهر و امضا</p>
					</div>
				</div>
				<?php endif; ?>
			</div>
			<div class="inv-footer"><?php echo nl2br( esc_html( $s['footer_text'] ?? 'با تشکر از خرید شما' ) ); ?></div>
		</div>
		<?php
	}
}

endif;
