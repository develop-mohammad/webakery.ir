<?php
/**
 * Order invoices — view and download.
 *
 * @package Webakery
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds printable invoices for bakery orders.
 */
class Webakery_Invoice {

	/**
	 * Hook registrations.
	 */
	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'maybe_handle_request' ) );
	}

	/**
	 * Capability check for invoice access.
	 *
	 * @param int $order_id Order post ID.
	 * @return bool
	 */
	public static function user_can_access( $order_id ) {
		return current_user_can( 'edit_post', $order_id );
	}

	/**
	 * Build invoice URL.
	 *
	 * @param int    $order_id Order ID.
	 * @param string $action   view|download.
	 * @return string
	 */
	public static function get_url( $order_id, $action = 'view' ) {
		$order_id = absint( $order_id );
		$action   = ( 'download' === $action ) ? 'download' : 'view';

		return wp_nonce_url(
			admin_url(
				'edit.php?post_type=wbk_order&webakery_invoice=' . $order_id . '&webakery_invoice_action=' . $action
			),
			'webakery_invoice_' . $order_id
		);
	}

	/**
	 * Handle view/download requests.
	 */
	public static function maybe_handle_request() {
		if ( empty( $_GET['webakery_invoice'] ) ) {
			return;
		}

		$order_id = absint( $_GET['webakery_invoice'] );
		$action   = isset( $_GET['webakery_invoice_action'] ) ? sanitize_key( wp_unslash( $_GET['webakery_invoice_action'] ) ) : 'view';

		if ( $order_id <= 0 ) {
			wp_die( esc_html__( 'سفارش نامعتبر است.', 'webakery' ) );
		}

		if ( ! self::user_can_access( $order_id ) ) {
			wp_die( esc_html__( 'اجازه دسترسی ندارید.', 'webakery' ) );
		}

		check_admin_referer( 'webakery_invoice_' . $order_id );

		$order = get_post( $order_id );
		if ( ! $order || 'wbk_order' !== $order->post_type ) {
			wp_die( esc_html__( 'سفارش یافت نشد.', 'webakery' ) );
		}

		$data = self::get_invoice_data( $order_id );
		$html = self::render_html( $data, 'download' === $action );

		if ( 'download' === $action ) {
			$filename = 'webakery-invoice-' . $order_id . '.html';
			nocache_headers();
			header( 'Content-Type: text/html; charset=utf-8' );
			header( 'Content-Disposition: attachment; filename=' . $filename );
			echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- assembled with escapes below.
			exit;
		}

		nocache_headers();
		header( 'Content-Type: text/html; charset=utf-8' );
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	/**
	 * Look up current catalog price by product title.
	 *
	 * @param string $product_name Product title.
	 * @return int
	 */
	public static function lookup_product_price( $product_name ) {
		$product_name = trim( (string) $product_name );
		if ( '' === $product_name ) {
			return 0;
		}

		global $wpdb;
		$product_id = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_status IN ('publish','private') AND post_title = %s ORDER BY ID DESC LIMIT 1",
				'wbk_product',
				$product_name
			)
		);

		if ( $product_id <= 0 ) {
			return 0;
		}

		return absint( get_post_meta( $product_id, '_wbk_price', true ) );
	}

	/**
	 * Resolve unit price for an order (stored meta, else product match).
	 *
	 * @param int    $order_id     Order ID.
	 * @param string $product_name Product title.
	 * @return int
	 */
	public static function resolve_unit_price( $order_id, $product_name = '' ) {
		$stored = get_post_meta( $order_id, '_wbk_unit_price', true );
		if ( '' !== $stored && null !== $stored ) {
			return absint( $stored );
		}

		if ( '' === $product_name ) {
			$product_name = (string) get_post_meta( $order_id, '_wbk_product', true );
		}

		return self::lookup_product_price( $product_name );
	}

	/**
	 * Collect invoice fields for an order.
	 *
	 * @param int $order_id Order ID.
	 * @return array
	 */
	public static function get_invoice_data( $order_id ) {
		$settings = Webakery_Settings::get();
		$qty      = max( 1, absint( get_post_meta( $order_id, '_wbk_qty', true ) ) );
		$product  = (string) get_post_meta( $order_id, '_wbk_product', true );
		$unit     = absint( self::resolve_unit_price( $order_id, $product ) );
		$total    = $unit * $qty;

		$invoice_prefix = ! empty( $settings['invoice_prefix'] ) ? $settings['invoice_prefix'] : 'WBK';
		$invoice_number = $invoice_prefix . '-' . str_pad( (string) $order_id, 5, '0', STR_PAD_LEFT );

		return array(
			'order_id'         => $order_id,
			'invoice_number'   => $invoice_number,
			'date'             => get_the_date( 'Y/m/d H:i', $order_id ),
			'store_name'       => $settings['store_name'],
			'store_phone'      => $settings['phone'],
			'store_address'    => $settings['address'],
			'currency'         => $settings['currency'],
			'invoice_note'     => isset( $settings['invoice_note'] ) ? $settings['invoice_note'] : '',
			'customer_name'    => (string) get_post_meta( $order_id, '_wbk_customer_name', true ),
			'customer_phone'   => (string) get_post_meta( $order_id, '_wbk_customer_phone', true ),
			'product'          => $product,
			'qty'              => $qty,
			'unit_price'       => $unit,
			'total'            => $total,
			'message'          => (string) get_post_meta( $order_id, '_wbk_message', true ),
			'download_url'     => self::get_url( $order_id, 'download' ),
			'view_url'         => self::get_url( $order_id, 'view' ),
		);
	}

	/**
	 * Format amount with currency.
	 *
	 * @param int    $amount   Amount.
	 * @param string $currency Currency label.
	 * @return string
	 */
	public static function format_money( $amount, $currency ) {
		$amount = absint( $amount );
		if ( $amount <= 0 ) {
			return '—';
		}

		return number_format_i18n( $amount ) . ' ' . $currency;
	}

	/**
	 * Render full HTML invoice document.
	 *
	 * @param array $data            Invoice data.
	 * @param bool  $for_download    Hide on-screen toolbar when true.
	 * @return string
	 */
	public static function render_html( $data, $for_download = false ) {
		$store_name     = esc_html( $data['store_name'] );
		$invoice_number = esc_html( $data['invoice_number'] );
		$date           = esc_html( $data['date'] );
		$customer_name  = esc_html( $data['customer_name'] ? $data['customer_name'] : '—' );
		$customer_phone = esc_html( $data['customer_phone'] ? $data['customer_phone'] : '—' );
		$product        = esc_html( $data['product'] ? $data['product'] : '—' );
		$qty            = esc_html( (string) $data['qty'] );
		$unit_price     = esc_html( self::format_money( $data['unit_price'], $data['currency'] ) );
		$total          = esc_html( self::format_money( $data['total'], $data['currency'] ) );
		$message        = esc_html( $data['message'] ? $data['message'] : '—' );
		$store_phone    = esc_html( $data['store_phone'] );
		$store_address  = esc_html( $data['store_address'] );
		$note           = esc_html( $data['invoice_note'] );
		$download_url   = esc_url( $data['download_url'] );
		$title          = esc_html(
			sprintf(
				/* translators: %s: invoice number */
				__( 'فاکتور %s', 'webakery' ),
				$data['invoice_number']
			)
		);

		$toolbar = '';
		if ( ! $for_download ) {
			$toolbar = '<div class="wbk-invoice-toolbar no-print">'
				. '<button type="button" onclick="window.print()">' . esc_html__( 'چاپ / ذخیره PDF', 'webakery' ) . '</button>'
				. '<a class="wbk-invoice-download" href="' . $download_url . '">' . esc_html__( 'دانلود فاکتور', 'webakery' ) . '</a>'
				. '<button type="button" class="wbk-invoice-close" onclick="window.close()">' . esc_html__( 'بستن', 'webakery' ) . '</button>'
				. '</div>';
		}

		$css = self::get_styles();

		ob_start();
		?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
	<meta charset="utf-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<title><?php echo $title; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></title>
	<style><?php echo $css; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></style>
</head>
<body>
	<?php echo $toolbar; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	<main class="wbk-invoice">
		<header class="wbk-invoice__header">
			<div>
				<p class="wbk-invoice__brand"><?php echo $store_name; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
				<p class="wbk-invoice__label"><?php echo esc_html__( 'فاکتور فروش', 'webakery' ); ?></p>
			</div>
			<div class="wbk-invoice__meta">
				<p><strong><?php echo esc_html__( 'شماره فاکتور:', 'webakery' ); ?></strong> <?php echo $invoice_number; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
				<p><strong><?php echo esc_html__( 'تاریخ:', 'webakery' ); ?></strong> <?php echo $date; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
				<p><strong><?php echo esc_html__( 'شماره سفارش:', 'webakery' ); ?></strong> #<?php echo esc_html( (string) $data['order_id'] ); ?></p>
			</div>
		</header>

		<section class="wbk-invoice__parties">
			<div>
				<h2><?php echo esc_html__( 'خریدار', 'webakery' ); ?></h2>
				<p><?php echo $customer_name; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
				<p dir="ltr"><?php echo $customer_phone; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
			</div>
			<div>
				<h2><?php echo esc_html__( 'فروشنده', 'webakery' ); ?></h2>
				<p><?php echo $store_name; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
				<?php if ( $store_phone ) : ?>
					<p dir="ltr"><?php echo $store_phone; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
				<?php endif; ?>
				<?php if ( $store_address ) : ?>
					<p><?php echo $store_address; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
				<?php endif; ?>
			</div>
		</section>

		<table class="wbk-invoice__table">
			<thead>
				<tr>
					<th><?php echo esc_html__( 'ردیف', 'webakery' ); ?></th>
					<th><?php echo esc_html__( 'محصول', 'webakery' ); ?></th>
					<th><?php echo esc_html__( 'تعداد', 'webakery' ); ?></th>
					<th><?php echo esc_html__( 'قیمت واحد', 'webakery' ); ?></th>
					<th><?php echo esc_html__( 'مبلغ', 'webakery' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<tr>
					<td>1</td>
					<td><?php echo $product; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
					<td><?php echo $qty; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
					<td><?php echo $unit_price; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
					<td><?php echo $total; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
				</tr>
			</tbody>
			<tfoot>
				<tr>
					<td colspan="4"><?php echo esc_html__( 'جمع کل', 'webakery' ); ?></td>
					<td><?php echo $total; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
				</tr>
			</tfoot>
		</table>

		<section class="wbk-invoice__note">
			<p><strong><?php echo esc_html__( 'یادداشت سفارش:', 'webakery' ); ?></strong> <?php echo $message; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
			<?php if ( $note ) : ?>
				<p class="wbk-invoice__footer-note"><?php echo $note; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
			<?php endif; ?>
		</section>
	</main>
</body>
</html>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Invoice page CSS.
	 *
	 * @return string
	 */
	private static function get_styles() {
		return '
			*{box-sizing:border-box}
			body{margin:0;background:#f3efe8;color:#2b2118;font-family:Tahoma,"Segoe UI",sans-serif;line-height:1.7}
			.wbk-invoice-toolbar{display:flex;gap:10px;justify-content:center;flex-wrap:wrap;padding:14px;background:#2b2118;position:sticky;top:0;z-index:5}
			.wbk-invoice-toolbar button,.wbk-invoice-toolbar a{appearance:none;border:0;border-radius:8px;padding:10px 16px;font:inherit;cursor:pointer;text-decoration:none;color:#2b2118;background:#f4c76a}
			.wbk-invoice-toolbar .wbk-invoice-download{background:#fff}
			.wbk-invoice-toolbar .wbk-invoice-close{background:#d9cfc3}
			.wbk-invoice{max-width:820px;margin:24px auto;background:#fff;border:1px solid #e2d6c8;border-radius:16px;padding:28px;box-shadow:0 10px 30px rgba(43,33,24,.06)}
			.wbk-invoice__header{display:flex;justify-content:space-between;gap:20px;border-bottom:2px solid #2b2118;padding-bottom:16px;margin-bottom:20px}
			.wbk-invoice__brand{margin:0;font-size:28px;font-weight:700;color:#8b4513}
			.wbk-invoice__label{margin:4px 0 0;color:#6b5a4a}
			.wbk-invoice__meta{text-align:left;direction:rtl}
			.wbk-invoice__meta p{margin:0 0 6px}
			.wbk-invoice__parties{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:22px}
			.wbk-invoice__parties h2{margin:0 0 8px;font-size:15px;color:#8b4513}
			.wbk-invoice__parties p{margin:0 0 4px}
			.wbk-invoice__table{width:100%;border-collapse:collapse;margin-bottom:18px}
			.wbk-invoice__table th,.wbk-invoice__table td{border:1px solid #e2d6c8;padding:10px 12px;text-align:center}
			.wbk-invoice__table th{background:#fff8ef}
			.wbk-invoice__table tfoot td{font-weight:700;background:#f7f1e8}
			.wbk-invoice__note p{margin:0 0 8px}
			.wbk-invoice__footer-note{color:#6b5a4a;border-top:1px dashed #e2d6c8;padding-top:10px;margin-top:12px}
			@media print{
				body{background:#fff}
				.no-print{display:none!important}
				.wbk-invoice{margin:0;border:0;box-shadow:none;border-radius:0;max-width:none}
			}
			@media (max-width:640px){
				.wbk-invoice{margin:12px;padding:18px}
				.wbk-invoice__header,.wbk-invoice__parties{grid-template-columns:1fr;display:grid}
				.wbk-invoice__meta{text-align:right}
			}
		';
	}
}
