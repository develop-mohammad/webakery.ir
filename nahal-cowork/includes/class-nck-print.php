<?php
defined( 'ABSPATH' ) || exit;

class NCK_Print {

	public static function hooks() {
		add_action( 'template_redirect', array( __CLASS__, 'maybe_print' ) );
	}

	public static function maybe_print() {
		if ( empty( $_GET['nck_print'] ) ) { // phpcs:ignore
			return;
		}
		$token    = sanitize_text_field( wp_unslash( $_GET['nck_print'] ) ); // phpcs:ignore
		$contract = NCK_Contracts::by_token( $token );
		if ( ! $contract || 'signed' !== $contract['status'] ) {
			wp_die( 'قرارداد پیدا نشد.', 'نهال', array( 'response' => 404 ) );
		}
		if ( 'hall' === NCK_Contracts::kind_of( $contract ) ) {
			self::render_hall( $contract );
		} else {
			self::render( $contract );
		}
		exit;
	}

	public static function render( array $contract ) {
		$s    = NCK_Settings::all();
		$vars = NCK_Settings::contract_vars(
			array(
				'name'  => $contract['full_name'],
				'phone' => $contract['phone'],
				'title' => $contract['honorific'],
				'plan'  => $contract['plan'],
			)
		);
		if ( ! empty( $contract['signed_at'] ) ) {
			$g = substr( (string) $contract['signed_at'], 0, 10 );
			$dt = NCK_Jalali::from_g_date( $g );
			if ( $dt ) {
				$vars['date'] = NCK_Jalali::format_long( $dt['y'], $dt['m'], $dt['d'] );
			}
		}
		$intro    = NCK_Contract::fill_template( $s['contract_intro'], $vars );
		$preamble = NCK_Contract::fill_template( $s['contract_preamble'], $vars );
		$sections = array();
		foreach ( NCK_Settings::parse_sections() as $sec ) {
			$sections[] = array(
				'title' => NCK_Contract::fill_template( $sec['title'], $vars ),
				'body'  => NCK_Contract::fill_template( $sec['body'], $vars ),
			);
		}
		include NCK_PATH . 'templates/print-contract.php';
	}

	public static function render_hall( array $contract ) {
		$s       = NCK_Settings::all();
		$payload = NCK_Contracts::payload( $contract );
		$vars    = NCK_Hall::fill_vars( $payload, $s );
		$clauses = NCK_Hall::filled_clauses( $vars );
		$note    = NCK_Hall::projector_note( (int) $s['projector_price'], (int) $s['projector_minutes'] );
		include NCK_PATH . 'templates/print-hall.php';
	}
}
