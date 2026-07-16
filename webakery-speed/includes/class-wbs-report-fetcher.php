<?php
/**
 * Fetch Lighthouse JSON embedded in pagespeed.web.dev report pages.
 *
 * @package WebakerySpeed
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Downloads report HTML and extracts Lighthouse payloads.
 */
class WBS_Report_Fetcher {

	/**
	 * Fetch Lighthouse result from a pagespeed.web.dev report URL.
	 *
	 * @param string $report_url Full report URL.
	 * @param string $strategy   mobile|desktop.
	 * @return array|WP_Error Normalized scan array.
	 */
	public static function fetch( $report_url, $strategy = 'mobile' ) {
		$response = wp_remote_get(
			esc_url_raw( $report_url ),
			array(
				'timeout'     => 60,
				'redirection' => 3,
				'headers'     => array(
					'Accept'          => 'text/html,application/xhtml+xml',
					'Accept-Language' => 'en-US,en;q=0.9,fa;q=0.8',
				),
				'user-agent'  => 'Mozilla/5.0 (compatible; WebakerySpeed/' . WBS_VERSION . '; +https://webakery.ir)',
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );

		if ( 200 !== (int) $code || '' === $body ) {
			return new WP_Error(
				'wbs_report_fetch_failed',
				__( 'دریافت صفحه گزارش pagespeed.web.dev ناموفق بود.', 'webakery-speed' ),
				array( 'status' => $code )
			);
		}

		$lighthouse = self::extract_lighthouse( $body, $strategy );
		if ( is_wp_error( $lighthouse ) ) {
			return $lighthouse;
		}

		$parsed = WBS_Report_URL::parse( $report_url );
		$url    = is_array( $parsed ) ? ( $parsed['url'] ?? '' ) : '';

		$wrapped = array(
			'lighthouseResult' => $lighthouse,
		);

		$result = WBS_PageSpeed_API::normalize_scan(
			$wrapped,
			$url ? $url : ( $lighthouse['finalUrl'] ?? $lighthouse['requestedUrl'] ?? '' ),
			$strategy
		);

		$result['source']      = 'pagespeed-web';
		$result['report_url']  = esc_url_raw( $report_url );
		$result['report_id']   = is_array( $parsed ) ? ( $parsed['report_id'] ?? '' ) : '';
		$result['fetch_method'] = 'embedded';

		return $result;
	}

	/**
	 * Extract Lighthouse JSON for the requested form factor.
	 *
	 * @param string $html     Report page HTML.
	 * @param string $strategy mobile|desktop.
	 * @return array|WP_Error
	 */
	public static function extract_lighthouse( $html, $strategy = 'mobile' ) {
		$strategy = in_array( $strategy, array( 'mobile', 'desktop' ), true ) ? $strategy : 'mobile';
		$matches  = array();

		if ( ! preg_match_all(
			'/,\"([a-z0-9]{10})\",\"(\{\\\\n  \\\\\"fetchTime\\\\\")/s',
			$html,
			$all,
			PREG_OFFSET_CAPTURE
		) ) {
			return new WP_Error(
				'wbs_no_lighthouse',
				__( 'گزارش Lighthouse در صفحه pagespeed.web.dev یافت نشد.', 'webakery-speed' )
			);
		}

		$count = count( $all[0] );
		for ( $i = 0; $i < $count; $i++ ) {
			$start = (int) $all[2][ $i ][1] - 1;
			$raw   = self::read_escaped_json_string( $html, $start );
			if ( is_wp_error( $raw ) ) {
				continue;
			}

			$decoded = self::decode_embedded_json( $raw );
			if ( is_wp_error( $decoded ) ) {
				continue;
			}

			$form_factor = $decoded['configSettings']['formFactor'] ?? '';
			$matches[]   = array(
				'data'        => $decoded,
				'form_factor' => $form_factor,
			);
		}

		if ( empty( $matches ) ) {
			return new WP_Error(
				'wbs_no_lighthouse',
				__( 'گزارش Lighthouse در صفحه pagespeed.web.dev یافت نشد.', 'webakery-speed' )
			);
		}

		foreach ( $matches as $item ) {
			if ( $strategy === $item['form_factor'] ) {
				return $item['data'];
			}
		}

		// Fallback to first payload if form factor metadata is missing.
		return $matches[0]['data'];
	}

	/**
	 * Read a JSON-escaped string starting at the opening quote.
	 *
	 * @param string $html  Source HTML.
	 * @param int    $start Index of opening quote.
	 * @return string|WP_Error
	 */
	private static function read_escaped_json_string( $html, $start ) {
		$length = strlen( $html );
		if ( $start < 0 || $start >= $length || '"' !== $html[ $start ] ) {
			return new WP_Error( 'wbs_bad_string', __( 'ساختار گزارش نامعتبر است.', 'webakery-speed' ) );
		}

		$out = '';
		$i   = $start + 1;

		while ( $i < $length ) {
			$char = $html[ $i ];

			if ( '\\' === $char ) {
				if ( $i + 1 >= $length ) {
					break;
				}
				$out .= $html[ $i ] . $html[ $i + 1 ];
				$i   += 2;
				continue;
			}

			if ( '"' === $char ) {
				return $out;
			}

			$out .= $char;
			$i++;
		}

		return new WP_Error( 'wbs_unterminated_string', __( 'گزارش Lighthouse ناقص است.', 'webakery-speed' ) );
	}

	/**
	 * Decode doubly-encoded Lighthouse JSON from the report page.
	 *
	 * @param string $raw Escaped JSON string body (without surrounding quotes).
	 * @return array|WP_Error
	 */
	private static function decode_embedded_json( $raw ) {
		$json_string = json_decode( '"' . $raw . '"', true );
		if ( ! is_string( $json_string ) ) {
			return new WP_Error( 'wbs_decode_failed', __( 'رمزگشایی گزارش Lighthouse ناموفق بود.', 'webakery-speed' ) );
		}

		$data = json_decode( $json_string, true );
		if ( ! is_array( $data ) || empty( $data['audits'] ) ) {
			return new WP_Error( 'wbs_decode_failed', __( 'ساختار گزارش Lighthouse نامعتبر است.', 'webakery-speed' ) );
		}

		return $data;
	}
}
