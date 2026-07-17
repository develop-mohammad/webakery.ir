<?php
defined( 'ABSPATH' ) || exit;

class NM_Invoice {

	/** @var self|null */
	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_post_nm_print_invoice', array( __CLASS__, 'print_invoice' ) );
	}

	public static function print_invoice() {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Forbidden' );
		$id = (int) ( $_GET['id'] ?? 0 );
		$booking = NM_Booking::get( $id );
		if ( ! $booking ) wp_die( 'یافت نشد' );

		$biz = NM_Settings::get( 'business_name' );
		nocache_headers();
		header( 'Content-Type: text/html; charset=utf-8' );
		?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8" />
<title>فاکتور <?php echo esc_html( $booking->invoice_no ?: $booking->booking_code ); ?></title>
<link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet" />
<style>
body{font-family:Vazirmatn,Tahoma,sans-serif;background:#f6f7fb;color:#111;margin:0;padding:24px}
.card{max-width:720px;margin:0 auto;background:#fff;border-radius:18px;padding:28px;box-shadow:0 10px 40px rgba(15,23,42,.08)}
h1{margin:0 0 8px;font-size:22px}.muted{color:#64748b}.row{display:flex;justify-content:space-between;gap:16px;margin:18px 0}
table{width:100%;border-collapse:collapse;margin-top:18px}th,td{border-bottom:1px solid #e2e8f0;padding:10px;text-align:right}
.total{font-size:20px;font-weight:700;color:#6d28d9}@media print{body{background:#fff}.card{box-shadow:none}}
</style>
</head>
<body>
<div class="card">
	<div class="row">
		<div>
			<h1>فاکتور رزرو نوبت</h1>
			<div class="muted"><?php echo esc_html( $biz ); ?> — سایت مشاوره آنلاین</div>
		</div>
		<div style="text-align:left">
			<div><strong><?php echo esc_html( $booking->invoice_no ?: $booking->booking_code ); ?></strong></div>
			<div class="muted"><?php echo esc_html( $booking->jalali_date ); ?></div>
		</div>
	</div>
	<table>
		<tr><th>مشتری</th><td><?php echo esc_html( $booking->customer_name ); ?></td></tr>
		<tr><th>تلفن</th><td><?php echo esc_html( $booking->customer_phone ); ?></td></tr>
		<tr><th>ایمیل</th><td><?php echo esc_html( $booking->customer_email ); ?></td></tr>
		<tr><th>شهر / جنسیت</th><td><?php echo esc_html( $booking->customer_city . ' / ' . $booking->customer_gender ); ?></td></tr>
		<tr><th>زمان جلسه</th><td><?php echo esc_html( $booking->jalali_date . ' — ' . substr( $booking->start_time, 0, 5 ) . ' تا ' . substr( $booking->end_time, 0, 5 ) ); ?></td></tr>
		<tr><th>مدت</th><td><?php echo (int) $booking->duration; ?> دقیقه</td></tr>
		<tr><th>مبلغ</th><td class="total"><?php echo esc_html( NM_Settings::format_price( $booking->price ) ); ?></td></tr>
	</table>
	<p class="muted" style="margin-top:24px">این فاکتور توسط افزونه نوبت من | webakery.ir صادر شده است.</p>
	<script>window.print()</script>
</div>
</body>
</html>
		<?php
		exit;
	}
}
