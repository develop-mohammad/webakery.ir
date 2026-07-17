<?php
defined( 'ABSPATH' ) || exit;

class NM_Installments {

	/** @var self|null */
	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {}

	public static function enabled() {
		return NM_Pro::is_active() && (int) NM_Settings::get( 'enable_installments', 0 );
	}

	public static function split_price( $total ) {
		$count = max( 2, (int) NM_Settings::get( 'installment_count', 2 ) );
		$total = (int) $total;
		$base  = (int) floor( $total / $count );
		$parts = array_fill( 0, $count, $base );
		$parts[0] += $total - array_sum( $parts );
		return $parts;
	}

	public static function first_payment( $total ) {
		$parts = self::split_price( $total );
		return $parts[0];
	}
}
