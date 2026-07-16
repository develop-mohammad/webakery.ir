<?php
/**
 * Parse pagespeed.web.dev report URLs.
 *
 * @package WebakerySpeed
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Extracts target URL and strategy from PageSpeed report links.
 */
class WBS_Report_URL {

	/**
	 * Parse a PageSpeed report URL or plain site URL.
	 *
	 * @param string $input Report URL or site URL.
	 * @return array|WP_Error
	 */
	public static function parse( $input ) {
		$input = trim( (string) $input );
		if ( '' === $input ) {
			return new WP_Error( 'wbs_empty_report_url', __( 'لینک گزارش خالی است.', 'webakery-speed' ) );
		}

		if ( ! preg_match( '#^https?://#i', $input ) ) {
			$input = 'https://' . ltrim( $input, '/' );
		}

		$parts = wp_parse_url( $input );
		if ( empty( $parts['host'] ) ) {
			return new WP_Error( 'wbs_invalid_report_url', __( 'لینک گزارش معتبر نیست.', 'webakery-speed' ) );
		}

		$strategy = self::parse_strategy( $parts );
		$host     = strtolower( $parts['host'] );

		if ( false === strpos( $host, 'pagespeed.web.dev' ) ) {
			return array(
				'url'        => esc_url_raw( $input ),
				'strategy'   => $strategy,
				'report_id'  => '',
				'report_url' => '',
				'source'     => 'direct',
			);
		}

		if ( ! empty( $parts['query'] ) ) {
			parse_str( $parts['query'], $query );
			if ( ! empty( $query['url'] ) ) {
				return array(
					'url'        => esc_url_raw( $query['url'] ),
					'strategy'   => self::parse_strategy( $parts, $query ),
					'report_id'  => '',
					'report_url' => esc_url_raw( $input ),
					'source'     => 'pagespeed-web',
				);
			}
		}

		$path = trim( $parts['path'] ?? '', '/' );
		if ( preg_match( '#analysis/([^/]+)(?:/([^/]+))?#', $path, $matches ) ) {
			return array(
				'url'        => self::decode_slug( $matches[1] ),
				'strategy'   => $strategy,
				'report_id'  => sanitize_text_field( $matches[2] ?? '' ),
				'report_url' => esc_url_raw( $input ),
				'source'     => 'pagespeed-web',
			);
		}

		return new WP_Error(
			'wbs_unparsed_report_url',
			__( 'ساختار لینک pagespeed.web.dev شناخته نشد.', 'webakery-speed' )
		);
	}

	/**
	 * Decode path slug like https-kianstock-ir to URL.
	 *
	 * @param string $slug Encoded slug.
	 * @return string
	 */
	public static function decode_slug( $slug ) {
		$slug = sanitize_text_field( rawurldecode( $slug ) );

		if ( preg_match( '#^https?://#i', $slug ) ) {
			return esc_url_raw( $slug );
		}

		if ( preg_match( '/^(https?)-(.+)$/i', $slug, $matches ) ) {
			$protocol = strtolower( $matches[1] );
			$host     = str_replace( '-', '.', $matches[2] );
			return esc_url_raw( $protocol . '://' . $host );
		}

		return esc_url_raw( 'https://' . str_replace( '-', '.', $slug ) );
	}

	/**
	 * Parse strategy from URL parts.
	 *
	 * @param array      $parts URL parts.
	 * @param array|null $query Parsed query.
	 * @return string
	 */
	private static function parse_strategy( $parts, $query = null ) {
		if ( null === $query && ! empty( $parts['query'] ) ) {
			parse_str( $parts['query'], $query );
		}

		$form_factor = is_array( $query ) ? ( $query['form_factor'] ?? '' ) : '';
		return in_array( $form_factor, array( 'mobile', 'desktop' ), true ) ? $form_factor : 'mobile';
	}
}
