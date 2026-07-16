<?php
/**
 * Font display swap.
 *
 * @package WebakerySpeed
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Forces font-display: swap for common font providers.
 */
class WBS_Fix_Font_Display {

	/**
	 * Font CSS host patterns.
	 *
	 * @var string[]
	 */
	private static $font_css_hosts = array(
		'fonts.googleapis.com',
		'fonts.bunny.net',
		'use.typekit.net',
		'cloud.typography.com',
	);

	/**
	 * Boot hooks.
	 */
	public static function boot() {
		add_filter( 'style_loader_tag', array( __CLASS__, 'provider_swap' ), 25, 4 );
		add_action( 'wp_head', array( __CLASS__, 'inline_swap' ), 1 );
		add_filter( 'webakery_speed_font_css_url', array( __CLASS__, 'ensure_swap_param' ) );
	}

	/**
	 * Add or replace display=swap on font stylesheet links.
	 *
	 * @param string $html  Link tag HTML.
	 * @param string $handle Handle.
	 * @param string $href   URL.
	 * @param string $media  Media.
	 * @return string
	 */
	public static function provider_swap( $html, $handle, $href, $media ) {
		unset( $handle, $media );

		if ( is_admin() || empty( $href ) || ! self::is_font_css_url( $href ) ) {
			return $html;
		}

		$href = self::ensure_swap_param( $href );
		$html = preg_replace( '/href=(["\']).*?\1/', 'href="' . esc_url( $href ) . '"', $html, 1 );

		return $html;
	}

	/**
	 * Global swap fallback for @font-face rules.
	 */
	public static function inline_swap() {
		if ( is_admin() ) {
			return;
		}
		echo "<style id=\"webakery-speed-font-display\">@font-face{font-display:swap !important;}</style>\n";
	}

	/**
	 * Ensure Google/Bunny style URLs use display=swap.
	 *
	 * @param string $url Font CSS URL.
	 * @return string
	 */
	public static function ensure_swap_param( $url ) {
		if ( empty( $url ) || ! self::is_font_css_url( $url ) ) {
			return $url;
		}

		$parts = wp_parse_url( $url );
		if ( empty( $parts['query'] ) ) {
			return add_query_arg( 'display', 'swap', $url );
		}

		parse_str( $parts['query'], $query );
		$query['display'] = 'swap';

		$base = sprintf(
			'%s://%s%s',
			$parts['scheme'] ?? 'https',
			$parts['host'] ?? '',
			$parts['path'] ?? ''
		);

		return $base . '?' . http_build_query( $query );
	}

	/**
	 * Is known webfont CSS URL.
	 *
	 * @param string $url URL.
	 * @return bool
	 */
	private static function is_font_css_url( $url ) {
		foreach ( self::$font_css_hosts as $host ) {
			if ( false !== strpos( $url, $host ) ) {
				return true;
			}
		}
		return false;
	}
}
