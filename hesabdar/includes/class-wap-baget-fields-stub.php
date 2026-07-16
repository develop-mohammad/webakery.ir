<?php
defined( 'ABSPATH' ) || exit;

if ( class_exists( 'WAP_Baget_Fields' ) ) {
	return;
}

/**
 * جایگزین امن وقتی class-wap-baget-fields.php بارگذاری نشود.
 */
class WAP_Baget_Fields {

	public static function is_baget_active() {
		return false;
	}

	public static function get_export_columns() {
		return array();
	}

	public static function get_field_definitions() {
		return array();
	}

	public static function export_key_for( $meta_key ) {
		return (string) $meta_key;
	}

	public static function get_order_field_value( $order, $meta_key ) {
		return '';
	}

	public static function get_table_columns() {
		return array();
	}

	public static function table_column_count() {
		return 0;
	}

	public static function render_table_headers() {}

	public static function render_table_cells( $order ) {}

	public static function get_order_extra_values( $order ) {
		return array();
	}

	public static function merge_row_data( array $row_data, $order ) {
		return $row_data;
	}

	public static function get_merged_export_columns() {
		return array(
			'order_number'  => 'شماره سفارش',
			'first_name'    => 'نام',
			'last_name'     => 'نام خانوادگی',
			'email'         => 'ایمیل',
			'phone'         => 'شماره تماس',
			'city'          => 'شهر',
			'state'         => 'استان',
			'address'       => 'آدرس',
			'postcode'      => 'کد پستی',
			'payment'       => 'روش پرداخت',
			'status'        => 'وضعیت سفارش',
			'products'      => 'محصولات',
			'total'         => 'مبلغ کل',
			'date'          => 'تاریخ سفارش',
			'transaction'   => 'کد پیگیری',
			'customer_note' => 'یادداشت مشتری',
		);
	}

	public static function get_invoice_fields( $order ) {
		return array();
	}
}
