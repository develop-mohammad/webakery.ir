<?php
defined( 'ABSPATH' ) || exit;

class NM_Admin_Export {
	public static function csv() {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Forbidden' );
		if ( ! NM_Pro::is_active() ) wp_die( 'نسخه پرو لازم است' );
		$rows = NM_Booking::query( array( 'limit' => 5000 ) );
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=nobat-man-bookings.csv' );
		echo "\xEF\xBB\xBF";
		$out = fopen( 'php://output', 'w' );
		fputcsv( $out, array( 'code', 'name', 'phone', 'email', 'city', 'gender', 'jalali_date', 'start', 'end', 'price', 'status', 'payment', 'order_id', 'invoice' ) );
		foreach ( $rows as $r ) {
			fputcsv( $out, array( $r->booking_code, $r->customer_name, $r->customer_phone, $r->customer_email, $r->customer_city, $r->customer_gender, $r->jalali_date, $r->start_time, $r->end_time, $r->price, $r->status, $r->payment_status, $r->order_id, $r->invoice_no ) );
		}
		fclose( $out );
		exit;
	}
}
add_action( 'admin_post_nm_export_csv', array( 'NM_Admin_Export', 'csv' ) );
