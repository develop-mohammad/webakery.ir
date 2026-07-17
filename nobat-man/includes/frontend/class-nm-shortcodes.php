<?php
defined( 'ABSPATH' ) || exit;

class NM_Shortcodes {

	public static function register() {
		add_shortcode( 'nobat_man', array( __CLASS__, 'render' ) );
		add_shortcode( 'nobat_man_thanks', array( __CLASS__, 'thanks' ) );
	}

	public static function render( $atts ) {
		$atts = shortcode_atts( array(
			'specialist_id' => 0,
			'business_id'   => 0,
		), $atts, 'nobat_man' );

		NM_Assets::enqueue_front();

		ob_start();
		include NM_PATH . 'includes/frontend/views/booking-form.php';
		return ob_get_clean();
	}

	public static function thanks( $atts ) {
		$code = isset( $_GET['nm_code'] ) ? sanitize_text_field( wp_unslash( $_GET['nm_code'] ) ) : '';
		if ( ! $code ) {
			return '<div class="nm-thanks">کد رزرو یافت نشد.</div>';
		}
		$booking = NM_Booking::get_by_code( $code );
		if ( ! $booking ) {
			return '<div class="nm-thanks">رزرو یافت نشد.</div>';
		}
		return '<div class="nm-thanks nm-card">' . NM_Booking::thank_you_html( $booking ) . '</div>';
	}
}
