<?php
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WCI_Invoice' ) ) :

/**
 * فاکتور اداری فشرده — مناسب چاپ یک‌صفحه‌ای PDF.
 */
class WCI_Invoice {

	private $order_id;

	public function __construct( $order_id ) {
		$this->order_id = absint( $order_id );
	}

	public static function admin_view_url( $order_id ): string {
		return add_query_arg(
			array(
				'page'        => 'wci-orders',
				'wci_invoice' => 1,
				'order_id'    => absint( $order_id ),
			),
			admin_url( 'admin.php' )
		);
	}

	public static function admin_download_url( $order_id ): string {
		return add_query_arg(
			array(
				'page'                 => 'wci-orders',
				'wci_invoice'          => 1,
				'wci_invoice_download' => 1,
				'order_id'             => absint( $order_id ),
			),
			admin_url( 'admin.php' )
		);
	}

	public static function portal_url( $order_id, $download = false ): string {
		$args = array(
			'action'   => 'wap_invoice',
			'order_id' => absint( $order_id ),
		);
		if ( $download ) {
			$args['download'] = 1;
		}
		return wp_nonce_url( add_query_arg( $args, admin_url( 'admin-post.php' ) ), 'wap_invoice_' . absint( $order_id ) );
	}

	private static function product_description( $product ): string {
		$empty_label = 'خالی است';
		if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
			return $empty_label;
		}

		$candidates = array(
			$product->get_short_description(),
			$product->get_description(),
		);
		if ( $product->is_type( 'variation' ) ) {
			$parent = wc_get_product( $product->get_parent_id() );
			if ( $parent ) {
				$candidates[] = $parent->get_short_description();
				$candidates[] = $parent->get_description();
			}
		}

		foreach ( $candidates as $candidate ) {
			$candidate = preg_replace( '/\s+/u', ' ', trim( wp_strip_all_tags( (string) $candidate ) ) );
			if ( $candidate !== '' ) {
				if ( function_exists( 'mb_strlen' ) && mb_strlen( $candidate ) > 180 ) {
					return mb_substr( $candidate, 0, 180 ) . '…';
				}
				return strlen( $candidate ) > 180 ? substr( $candidate, 0, 180 ) . '…' : $candidate;
			}
		}
		return $empty_label;
	}

	private static function is_hidden_item_meta( $meta_item ): bool {
		$key         = strtolower( (string) ( $meta_item->key ?? '' ) );
		$display_key = strtolower( wp_strip_all_tags( (string) ( $meta_item->display_key ?? '' ) ) );
		$hidden      = array( '_reduced_stock', 'reduced_stock', '_restock_refunded_items' );
		if ( in_array( $key, $hidden, true ) || in_array( $display_key, $hidden, true ) ) {
			return true;
		}
		return ( $key !== '' && $key[0] === '_' );
	}

	/**
	 * کیف پول باشگاه مشتریان / پرداخت جزئی از کیف پول.
	 *
	 * @return array{used:bool,amount:float,label:string}
	 */
	private static function wallet_info( WC_Order $order ): array {
		$method = (string) $order->get_payment_method();
		$title  = (string) $order->get_payment_method_title();
		if (
			false !== stripos( $method, 'wallet' )
			|| false !== stripos( $title, 'wallet' )
			|| false !== stripos( $title, 'کیف' )
			|| false !== stripos( $title, 'باشگاه' )
		) {
			return array(
				'used'   => true,
				'amount' => (float) $order->get_total(),
				'label'  => $title !== '' ? $title : 'کیف پول باشگاه مشتریان',
			);
		}

		$meta_keys = array(
			'_woo_wallet_partial_payment',
			'_wallet_partial_payment',
			'_aw_wallet_partial_payment',
			'_terra_wallet_partial_payment',
			'wallet_amount',
			'_wallet_amount',
			'partial_payment_amount',
			'_partial_payment_amount',
			'customer_wallet_amount',
			'_customer_wallet_amount',
			'club_wallet_amount',
			'_club_wallet_amount',
		);
		foreach ( $meta_keys as $key ) {
			$val = $order->get_meta( $key );
			if ( $val !== '' && $val !== null && is_numeric( $val ) && (float) $val > 0 ) {
				return array(
					'used'   => true,
					'amount' => (float) $val,
					'label'  => 'کیف پول باشگاه مشتریان',
				);
			}
		}

		foreach ( $order->get_fees() as $fee ) {
			$name  = (string) $fee->get_name();
			$total = (float) $fee->get_total();
			if ( $total < 0 && ( false !== stripos( $name, 'کیف' ) || false !== stripos( $name, 'wallet' ) || false !== stripos( $name, 'باشگاه' ) ) ) {
				return array(
					'used'   => true,
					'amount' => abs( $total ),
					'label'  => $name,
				);
			}
		}

		return array( 'used' => false, 'amount' => 0.0, 'label' => '' );
	}

	/**
	 * بارکد Code39 به‌صورت SVG (بدون وابستگی خارجی).
	 */
	private static function barcode_svg( string $text, int $height = 32, int $module = 1 ): string {
		$text = strtoupper( preg_replace( '/[^0-9A-Z\-\.\ \$\/\+%]/', '', $text ) );
		if ( $text === '' ) {
			$text = '0';
		}

		$codes = array(
			'0' => '000110100', '1' => '100100001', '2' => '001100001', '3' => '101100000',
			'4' => '000110001', '5' => '100110000', '6' => '001110000', '7' => '000100101',
			'8' => '100100100', '9' => '001100100', 'A' => '100001001', 'B' => '001001001',
			'C' => '101001000', 'D' => '000011001', 'E' => '100011000', 'F' => '001011000',
			'G' => '000001101', 'H' => '100001100', 'I' => '001001100', 'J' => '000011100',
			'K' => '100000011', 'L' => '001000011', 'M' => '101000010', 'N' => '000010011',
			'O' => '100010010', 'P' => '001010010', 'Q' => '000000111', 'R' => '100000110',
			'S' => '001000110', 'T' => '000010110', 'U' => '110000001', 'V' => '011000001',
			'W' => '111000000', 'X' => '010010001', 'Y' => '110010000', 'Z' => '011010000',
			'-' => '010000101', '.' => '110000100', ' ' => '011000100', '$' => '010101000',
			'/' => '010100010', '+' => '010001010', '%' => '000101010', '*' => '010010100',
		);

		$pattern = $codes['*'];
		$chars   = str_split( $text );
		foreach ( $chars as $ch ) {
			$pattern .= '0' . ( $codes[ $ch ] ?? $codes['0'] );
		}
		$pattern .= '0' . $codes['*'];

		$x     = 0;
		$rects = '';
		$len   = strlen( $pattern );
		for ( $i = 0; $i < $len; $i++ ) {
			$w = ( $pattern[ $i ] === '1' ) ? ( 3 * $module ) : ( 1 * $module );
			if ( $i % 2 === 0 ) {
				$rects .= '<rect x="' . $x . '" y="0" width="' . $w . '" height="' . $height . '" fill="#000"/>';
			}
			$x += $w;
		}
		$width = max( 1, $x );
		return '<svg xmlns="http://www.w3.org/2000/svg" width="' . $width . '" height="' . ( $height + 14 ) . '" viewBox="0 0 ' . $width . ' ' . ( $height + 14 ) . '" role="img" aria-label="barcode">'
			. $rects
			. '<text x="' . ( $width / 2 ) . '" y="' . ( $height + 11 ) . '" text-anchor="middle" font-size="9" font-family="monospace">' . esc_html( $text ) . '</text>'
			. '</svg>';
	}

	private static function product_image_url( $product ): string {
		if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
			return '';
		}
		$image_id = $product->get_image_id();
		if ( ! $image_id && $product->is_type( 'variation' ) ) {
			$parent = wc_get_product( $product->get_parent_id() );
			if ( $parent ) {
				$image_id = $parent->get_image_id();
			}
		}
		if ( ! $image_id ) {
			return '';
		}
		$url = wp_get_attachment_image_url( $image_id, 'thumbnail' );
		return $url ? $url : '';
	}

	public function download(): void {
		$this->render( true );
	}

	public function render( $as_download = false ): void {
		$order = wc_get_order( $this->order_id );
		if ( ! $order ) {
			wp_die( 'سفارش یافت نشد.' );
		}

		$s     = get_option( 'wci_invoice_settings', array() );
		$color = ! empty( $s['primary_color'] ) ? $s['primary_color'] : '#0f766e';
		$logo  = $s['logo_url'] ?? '';

		$state_code = $order->get_billing_state();
		$country    = $order->get_billing_country() ?: 'IR';
		$states     = WC()->countries->get_states( $country ) ?? array();
		$state_name = $states[ $state_code ] ?? $state_code;

		$company_name    = $s['company_name'] ?? get_bloginfo( 'name' );
		$company_address = $s['company_address'] ?? '';
		$company_phone   = $s['company_phone'] ?? '';

		$download_url = self::admin_download_url( $this->order_id );
		if ( isset( $_GET['action'] ) && 'wap_invoice' === sanitize_key( wp_unslash( $_GET['action'] ) ) ) {
			$download_url = self::portal_url( $this->order_id, true );
		}

		$wallet   = self::wallet_info( $order );
		$coupons  = $order->get_coupon_codes();
		$tracking = class_exists( 'WCI_Tracking' ) ? WCI_Tracking::get_for_order( $order ) : array( 'code' => '', 'provider_label' => '' );
		$ship_m   = $order->get_shipping_method();
		$pay_m    = function_exists( 'wci_payment_label' )
			? wci_payment_label( $order->get_payment_method() )
			: $order->get_payment_method_title();
		if ( $pay_m === '' ) {
			$pay_m = $order->get_payment_method_title();
		}

		$order_barcode_text = (string) $order->get_order_number();
		$invoice_no         = $order->get_order_number();

		if ( $as_download ) {
			$filename = 'hesabdar-invoice-' . $order_barcode_text . '.html';
			nocache_headers();
			header( 'Content-Type: text/html; charset=utf-8' );
			header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		}
		?>
<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
<meta charset="UTF-8">
<title>فاکتور #<?php echo esc_html( $invoice_no ); ?></title>
<style>
@page { size: A4; margin: 8mm; }
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:Tahoma,Arial,sans-serif;font-size:11px;color:#111;background:#fff;direction:rtl}
.sheet{max-width:190mm;margin:0 auto;padding:6px}
.no-print{text-align:center;margin:12px 0 8px}
.no-print button,.no-print a{margin:0 4px;padding:8px 16px;border:0;border-radius:4px;font:inherit;cursor:pointer;text-decoration:none;display:inline-block}
.btn-print{background:<?php echo esc_attr( $color ); ?>;color:#fff}
.btn-dl{background:#1d4ed8;color:#fff}
.btn-close{background:#64748b;color:#fff}

table.grid{width:100%;border-collapse:collapse;table-layout:fixed}
table.grid th,table.grid td{border:1px solid #222;padding:5px 6px;vertical-align:top}
table.grid th{background:#f3f4f6;font-size:10.5px}

.head{display:flex;justify-content:space-between;align-items:flex-start;gap:10px;margin-bottom:8px;border-bottom:2px solid #111;padding-bottom:6px}
.head-right{text-align:right}
.head-left{text-align:left}
.head h1{font-size:20px;letter-spacing:1px;margin-bottom:4px}
.logo img{max-height:48px;max-width:140px}
.logo-fallback{font-size:16px;font-weight:700;color:<?php echo esc_attr( $color ); ?>}
.meta-line{font-size:11px;margin:2px 0}
.bc{margin-top:4px;line-height:0}

.parties{margin:8px 0;display:table;width:100%;border-collapse:collapse;table-layout:fixed}
.party{display:table-cell;width:50%;border:1px solid #222;vertical-align:top;text-align:right;-webkit-print-color-adjust:exact;print-color-adjust:exact}
.party h3{border-bottom:1px solid #222;padding:5px 8px;font-size:11px;font-weight:700;color:#fff;-webkit-print-color-adjust:exact;print-color-adjust:exact}
.party .body{padding:7px 8px;line-height:1.55}
.party-sender{background:#e8f5f3}
.party-sender h3{background:<?php echo esc_attr( $color ); ?>}
.party-receiver{background:#eef2ff}
.party-receiver h3{background:#334155}

.items th{background:<?php echo esc_attr( $color ); ?>;color:#fff;border-color:#155e54}
.items td{font-size:10.5px}
.pimg{width:46px;height:46px;object-fit:cover;border:1px solid #ccc;display:block}
.pimg-empty{width:46px;height:46px;border:1px dashed #bbb;display:flex;align-items:center;justify-content:center;color:#999;font-size:9px}
.pname{font-weight:700;margin-bottom:2px}
.pdesc{color:#444;font-size:10px;margin-top:2px}
.pmeta{color:#666;font-size:9.5px}
.pbc{margin-top:3px;line-height:0;transform:scale(.85);transform-origin:right top}

.summary{width:55%;margin-right:auto;margin-top:8px;border-collapse:collapse}
.summary td{border:1px solid #222;padding:5px 8px}
.summary tr.total td{background:<?php echo esc_attr( $color ); ?>;color:#fff;font-weight:700;font-size:12px}
.footer{margin-top:8px;text-align:center;border-top:1px solid #999;padding-top:6px;font-size:10px;color:#444}

@media print{
	body{background:#fff}
	.no-print{display:none!important}
	.sheet{max-width:none;margin:0;padding:0}
	.party,.items,.summary,.head{break-inside:avoid}
	.party,.party h3,.items th,.summary tr.total td{-webkit-print-color-adjust:exact;print-color-adjust:exact}
}
</style>
</head>
<body>
<?php if ( ! $as_download ) : ?>
<div class="no-print">
	<button class="btn-print" type="button" onclick="window.print()">🖨 چاپ / PDF یک صفحه</button>
	<a class="btn-dl" href="<?php echo esc_url( $download_url ); ?>">⬇ دانلود</a>
	<button class="btn-close" type="button" onclick="window.close()">بستن</button>
</div>
<?php endif; ?>

<div class="sheet">
	<div class="head">
		<div class="head-right">
			<div class="logo">
				<?php if ( $logo ) : ?>
					<img src="<?php echo esc_url( $logo ); ?>" alt="logo">
				<?php else : ?>
					<div class="logo-fallback"><?php echo esc_html( $company_name ); ?></div>
				<?php endif; ?>
			</div>
		</div>
		<div class="head-left">
			<h1>فاکتور</h1>
			<div class="meta-line">شماره فاکتور: <strong><?php echo esc_html( $invoice_no ); ?></strong></div>
			<div class="bc"><?php echo self::barcode_svg( $order_barcode_text, 28, 1 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
			<div class="meta-line">تاریخ: <?php echo esc_html( wc_format_datetime( $order->get_date_created() ) ); ?></div>
			<div class="meta-line">وضعیت: <?php echo esc_html( wc_get_order_status_name( $order->get_status() ) ); ?></div>
		</div>
	</div>

	<div class="parties">
		<div class="party party-sender">
			<h3>فرستنده</h3>
			<div class="body">
				<div><strong><?php echo esc_html( $company_name ); ?></strong></div>
				<?php if ( $company_phone ) : ?><div>تلفن: <?php echo esc_html( $company_phone ); ?></div><?php endif; ?>
				<?php if ( $company_address ) : ?><div>آدرس: <?php echo nl2br( esc_html( $company_address ) ); ?></div><?php endif; ?>
			</div>
		</div>
		<div class="party party-receiver">
			<h3>گیرنده</h3>
			<div class="body">
				<div><strong><?php echo esc_html( $order->get_formatted_billing_full_name() ); ?></strong></div>
				<?php if ( $order->get_billing_phone() ) : ?><div>تلفن: <?php echo esc_html( $order->get_billing_phone() ); ?></div><?php endif; ?>
				<?php if ( $order->get_billing_email() ) : ?><div>ایمیل: <?php echo esc_html( $order->get_billing_email() ); ?></div><?php endif; ?>
				<div>آدرس:
					<?php
					echo esc_html(
						trim(
							$order->get_billing_address_1()
							. ( $order->get_billing_address_2() ? ' ' . $order->get_billing_address_2() : '' )
							. ( $order->get_billing_city() ? ' — ' . $order->get_billing_city() : '' )
							. ( $state_name ? '، ' . $state_name : '' )
							. ( $order->get_billing_postcode() ? '، کدپستی ' . $order->get_billing_postcode() : '' )
						)
					);
					?>
				</div>
				<?php if ( class_exists( 'WAP_Baget_Fields' ) ) : ?>
					<?php foreach ( WAP_Baget_Fields::get_invoice_fields( $order ) as $label => $value ) : ?>
						<div><?php echo esc_html( $label ); ?>: <?php echo esc_html( $value ); ?></div>
					<?php endforeach; ?>
				<?php endif; ?>
			</div>
		</div>
	</div>

	<table class="grid items">
		<thead>
			<tr>
				<th style="width:34px">ردیف</th>
				<th style="width:58px">کد کالا</th>
				<th style="width:54px">تصویر</th>
				<th>شرح کالا / توضیحات</th>
				<th style="width:70px">بارکد</th>
				<th style="width:44px">تعداد</th>
				<th style="width:78px">مبلغ واحد</th>
				<th style="width:78px">مبلغ کل</th>
			</tr>
		</thead>
		<tbody>
		<?php
		$i = 1;
		foreach ( $order->get_items() as $item ) :
			$product    = $item->get_product();
			$unit_price = $item->get_total() / max( 1, $item->get_quantity() );
			$desc       = self::product_description( $product );
			$sku        = ( $product && $product->get_sku() ) ? $product->get_sku() : (string) ( $product ? $product->get_id() : $item->get_product_id() );
			$img        = self::product_image_url( $product );
			$bc_text    = preg_replace( '/[^0-9A-Za-z\-]/', '', $sku );
			if ( $bc_text === '' ) {
				$bc_text = (string) $item->get_product_id();
			}
			?>
			<tr>
				<td style="text-align:center"><?php echo (int) $i++; ?></td>
				<td style="text-align:center;direction:ltr"><?php echo esc_html( $sku ); ?></td>
				<td style="text-align:center">
					<?php if ( $img ) : ?>
						<img class="pimg" src="<?php echo esc_url( $img ); ?>" alt="">
					<?php else : ?>
						<div class="pimg-empty">بدون تصویر</div>
					<?php endif; ?>
				</td>
				<td>
					<div class="pname"><?php echo esc_html( $item->get_name() ); ?></div>
					<?php
					$meta = $item->get_formatted_meta_data( '' );
					if ( ! empty( $meta ) ) {
						foreach ( $meta as $meta_item ) {
							if ( self::is_hidden_item_meta( $meta_item ) ) {
								continue;
							}
							echo '<div class="pmeta">' . esc_html( wp_strip_all_tags( (string) $meta_item->display_key ) ) . ': '
								. esc_html( wp_strip_all_tags( (string) $meta_item->display_value ) ) . '</div>';
						}
					}
					?>
					<div class="pdesc"><strong>توضیحات:</strong> <?php echo esc_html( $desc ); ?></div>
				</td>
				<td style="text-align:center">
					<div class="pbc"><?php echo self::barcode_svg( $bc_text, 22, 1 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
				</td>
				<td style="text-align:center"><?php echo esc_html( (string) $item->get_quantity() ); ?></td>
				<td style="text-align:center"><?php echo wp_kses_post( wc_price( $unit_price ) ); ?></td>
				<td style="text-align:center"><?php echo wp_kses_post( wc_price( $item->get_total() ) ); ?></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>

	<table class="summary">
		<tr>
			<td>مجموع کالاها</td>
			<td style="text-align:left"><?php echo wp_kses_post( wc_price( $order->get_subtotal() ) ); ?></td>
		</tr>
		<tr>
			<td>حمل و نقل<?php echo $ship_m ? ' — ' . esc_html( $ship_m ) : ''; ?></td>
			<td style="text-align:left"><?php echo wp_kses_post( wc_price( (float) $order->get_shipping_total() ) ); ?></td>
		</tr>
		<tr>
			<td>روش پرداخت</td>
			<td style="text-align:left"><?php echo esc_html( $pay_m !== '' ? $pay_m : '—' ); ?></td>
		</tr>
		<?php if ( $wallet['used'] ) : ?>
		<tr>
			<td><?php echo esc_html( $wallet['label'] ); ?></td>
			<td style="text-align:left"><?php echo wp_kses_post( wc_price( $wallet['amount'] ) ); ?> <span>(پرداخت شده)</span></td>
		</tr>
		<?php endif; ?>
		<?php if ( ! empty( $coupons ) ) : ?>
		<tr>
			<td>کد تخفیف</td>
			<td style="text-align:left;direction:ltr"><?php echo esc_html( implode( ', ', $coupons ) ); ?></td>
		</tr>
		<?php endif; ?>
		<?php if ( (float) $order->get_discount_total() > 0 ) : ?>
		<tr>
			<td>مبلغ تخفیف</td>
			<td style="text-align:left">-<?php echo wp_kses_post( wc_price( $order->get_discount_total() ) ); ?></td>
		</tr>
		<?php endif; ?>
		<?php if ( (float) $order->get_total_tax() > 0 ) : ?>
		<tr>
			<td>مالیات</td>
			<td style="text-align:left"><?php echo wp_kses_post( wc_price( $order->get_total_tax() ) ); ?></td>
		</tr>
		<?php endif; ?>
		<?php if ( ! empty( $tracking['code'] ) ) : ?>
		<tr>
			<td>کد رهگیری پستی</td>
			<td style="text-align:left;direction:ltr">
				<?php echo esc_html( $tracking['code'] ); ?>
				<?php if ( ! empty( $tracking['provider_label'] ) ) : ?>
					(<?php echo esc_html( $tracking['provider_label'] ); ?>)
				<?php endif; ?>
			</td>
		</tr>
		<?php endif; ?>
		<?php if ( $order->get_transaction_id() ) : ?>
		<tr>
			<td>کد پیگیری پرداخت</td>
			<td style="text-align:left;direction:ltr"><?php echo esc_html( $order->get_transaction_id() ); ?></td>
		</tr>
		<?php endif; ?>
		<tr class="total">
			<td>قیمت نهایی</td>
			<td style="text-align:left"><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></td>
		</tr>
	</table>

	<div class="footer">
		<?php echo nl2br( esc_html( $s['footer_text'] ?? 'با تشکر از خرید شما' ) ); ?>
	</div>
</div>
</body>
</html>
		<?php
		if ( $as_download ) {
			exit;
		}
	}
}

endif;
