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
 * Forces font-display: swap for common font providers and @font-face rules.
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
		'cdn.fontiran.com',
		'fontiran.com',
		'cdn.jsdelivr.net',
		'cdnjs.cloudflare.com',
	);

	/**
	 * Boot hooks.
	 */
	public static function boot() {
		add_filter( 'style_loader_tag', array( __CLASS__, 'provider_swap' ), 25, 4 );
		add_filter( 'style_loader_tag', array( __CLASS__, 'inline_font_faces_from_stylesheet' ), 26, 4 );
		add_action( 'template_redirect', array( __CLASS__, 'start_buffer' ), 0 );
		add_filter( 'webakery_speed_font_css_url', array( __CLASS__, 'ensure_swap_param' ) );
		add_filter( 'webakery_speed_preload_fonts', array( __CLASS__, 'preload_swap_urls' ) );
	}

	/**
	 * Buffer final HTML to patch inline CSS and link tags.
	 */
	public static function start_buffer() {
		if ( is_admin() || wp_doing_ajax() || wp_is_json_request() ) {
			return;
		}

		ob_start( array( __CLASS__, 'rewrite_html' ) );
	}

	/**
	 * Rewrite font-related markup in full HTML output.
	 *
	 * @param string $html Page HTML.
	 * @return string
	 */
	public static function rewrite_html( $html ) {
		if ( ! is_string( $html ) || '' === $html ) {
			return $html;
		}

		$html = preg_replace_callback(
			'/<style\b([^>]*)>(.*?)<\/style>/is',
			array( __CLASS__, 'rewrite_style_tag' ),
			$html
		);

		if ( ! is_string( $html ) ) {
			return '';
		}

		$html = preg_replace_callback(
			'/href=(["\'])(https?:\/\/[^"\']+)\1/i',
			array( __CLASS__, 'rewrite_font_href' ),
			$html
		);

		return is_string( $html ) ? $html : '';
	}

	/**
	 * Patch inline style blocks.
	 *
	 * @param array $matches Regex matches.
	 * @return string
	 */
	public static function rewrite_style_tag( $matches ) {
		$attrs = $matches[1];
		$css   = self::inject_font_display_in_css( $matches[2] );
		$css   = self::rewrite_font_imports_in_css( $css );

		return '<style' . $attrs . '>' . $css . '</style>';
	}

	/**
	 * Patch font stylesheet href attributes.
	 *
	 * @param array $matches Regex matches.
	 * @return string
	 */
	public static function rewrite_font_href( $matches ) {
		$quote = $matches[1];
		$url   = $matches[2];

		if ( ! self::is_font_css_url( $url ) ) {
			return $matches[0];
		}

		return 'href=' . $quote . esc_url( self::ensure_swap_param( $url ) ) . $quote;
	}

	/**
	 * Add or replace display=swap on font stylesheet links.
	 *
	 * @param string $html   Link tag HTML.
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
	 * Duplicate @font-face rules from linked CSS with font-display:swap.
	 *
	 * @param string $html   Link tag HTML.
	 * @param string $handle Handle.
	 * @param string $href   URL.
	 * @param string $media  Media.
	 * @return string
	 */
	public static function inline_font_faces_from_stylesheet( $html, $handle, $href, $media ) {
		unset( $media );

		if ( is_admin() || empty( $href ) || self::is_font_css_url( $href ) ) {
			return $html;
		}

		if ( ! self::looks_like_font_stylesheet( $href ) ) {
			return $html;
		}

		$css = self::get_stylesheet_css( $href );
		if ( '' === $css || false === stripos( $css, '@font-face' ) ) {
			return $html;
		}

		$faces = self::extract_font_face_blocks( $css );
		if ( '' === $faces ) {
			return $html;
		}

		$html .= '<style id="webakery-speed-font-display-' . esc_attr( $handle ) . '">' . $faces . '</style>';

		return $html;
	}

	/**
	 * Ensure preload font URLs also request display=swap.
	 *
	 * @param array $items Preload items.
	 * @return array
	 */
	public static function preload_swap_urls( $items ) {
		foreach ( $items as $index => $item ) {
			if ( empty( $item['url'] ) || ! self::is_font_css_url( $item['url'] ) ) {
				continue;
			}

			$items[ $index ]['url'] = self::ensure_swap_param( $item['url'] );
		}

		return $items;
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
		if ( empty( $parts['host'] ) ) {
			return $url;
		}

		if ( empty( $parts['query'] ) ) {
			return add_query_arg( 'display', 'swap', $url );
		}

		parse_str( $parts['query'], $query );
		$query['display'] = 'swap';

		$base = sprintf(
			'%s://%s%s',
			$parts['scheme'] ?? 'https',
			$parts['host'],
			$parts['path'] ?? ''
		);

		return $base . '?' . http_build_query( $query );
	}

	/**
	 * Inject or replace font-display:swap inside CSS text.
	 *
	 * @param string $css CSS source.
	 * @return string
	 */
	public static function inject_font_display_in_css( $css ) {
		if ( ! is_string( $css ) || '' === $css || false === stripos( $css, '@font-face' ) ) {
			return $css;
		}

		$updated = preg_replace_callback(
			'/@font-face\s*\{([^}]*)\}/i',
			array( __CLASS__, 'patch_font_face_block' ),
			$css
		);

		return is_string( $updated ) ? $updated : $css;
	}

	/**
	 * Patch one @font-face block.
	 *
	 * @param array $matches Regex matches.
	 * @return string
	 */
	private static function patch_font_face_block( $matches ) {
		$inner = $matches[1];

		if ( preg_match( '/font-display\s*:\s*swap\b/i', $inner ) ) {
			return $matches[0];
		}

		if ( preg_match( '/font-display\s*:\s*([a-z-]+)/i', $inner ) ) {
			$inner = preg_replace( '/font-display\s*:\s*[^;]+/i', 'font-display:swap', $inner );
			return '@font-face{' . $inner . '}';
		}

		$inner = rtrim( $inner );
		if ( '' !== $inner && ';' !== substr( $inner, -1 ) ) {
			$inner .= ';';
		}

		return '@font-face{' . $inner . 'font-display:swap;}';
	}

	/**
	 * Rewrite @import rules that load webfont CSS.
	 *
	 * @param string $css CSS source.
	 * @return string
	 */
	private static function rewrite_font_imports_in_css( $css ) {
		if ( false === stripos( $css, '@import' ) ) {
			return $css;
		}

		$updated = preg_replace_callback(
			'/@import\s+url\((["\']?)([^\'")]+)\1\)/i',
			function ( $matches ) {
				$url = trim( $matches[2], " \t\n\r\0\x0B'\"" );
				if ( ! self::is_font_css_url( $url ) ) {
					return $matches[0];
				}

				return '@import url("' . esc_url( self::ensure_swap_param( $url ) ) . '")';
			},
			$css
		);

		return is_string( $updated ) ? $updated : $css;
	}

	/**
	 * Extract patched @font-face blocks for inline fallback.
	 *
	 * @param string $css CSS source.
	 * @return string
	 */
	private static function extract_font_face_blocks( $css ) {
		if ( ! preg_match_all( '/@font-face\s*\{([^}]*)\}/i', $css, $matches, PREG_SET_ORDER ) ) {
			return '';
		}

		$blocks = array();
		foreach ( $matches as $match ) {
			if ( preg_match( '/font-display\s*:\s*swap\b/i', $match[1] ) ) {
				continue;
			}

			$blocks[] = self::patch_font_face_block( $match );
		}

		return implode( "\n", $blocks );
	}

	/**
	 * Fetch stylesheet content with transient cache.
	 *
	 * @param string $href Stylesheet URL.
	 * @return string
	 */
	private static function get_stylesheet_css( $href ) {
		$url = self::normalize_url( $href );
		if ( '' === $url || ! self::is_allowed_stylesheet_url( $url ) ) {
			return '';
		}

		$key    = 'wbs_font_css_' . md5( $url );
		$cached = get_transient( $key );
		if ( is_string( $cached ) ) {
			return $cached;
		}

		$response = wp_remote_get(
			$url,
			array(
				'timeout'     => 8,
				'redirection' => 2,
			)
		);

		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			set_transient( $key, '', HOUR_IN_SECONDS );
			return '';
		}

		$body = wp_remote_retrieve_body( $response );
		if ( strlen( $body ) > 150000 || false === stripos( $body, '@font-face' ) ) {
			set_transient( $key, '', HOUR_IN_SECONDS );
			return '';
		}

		set_transient( $key, $body, DAY_IN_SECONDS );

		return $body;
	}

	/**
	 * Only fetch same-site or wp-content stylesheets.
	 *
	 * @param string $url Stylesheet URL.
	 * @return bool
	 */
	private static function is_allowed_stylesheet_url( $url ) {
		$parts = wp_parse_url( $url );
		if ( empty( $parts['host'] ) ) {
			return true;
		}

		$site = wp_parse_url( home_url( '/' ) );
		if ( empty( $site['host'] ) ) {
			return false;
		}

		if ( strtolower( $parts['host'] ) !== strtolower( $site['host'] ) ) {
			return false;
		}

		$path = strtolower( $parts['path'] ?? '' );
		return false !== strpos( $path, '/wp-content/' ) || false !== strpos( $path, 'font' );
	}

	/**
	 * Heuristic for theme/plugin font stylesheets.
	 *
	 * @param string $href Stylesheet URL.
	 * @return bool
	 */
	private static function looks_like_font_stylesheet( $href ) {
		$href = strtolower( $href );
		$needles = array( 'font', 'icon', 'woff', 'typeface', 'iran', 'vazir', 'yekan', 'sahel' );

		foreach ( $needles as $needle ) {
			if ( false !== strpos( $href, $needle ) ) {
				return true;
			}
		}

		return false;
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

		return (bool) preg_match( '/fonts\.googleapis\.com|fonts\.bunny\.net/i', $url );
	}

	/**
	 * Normalize relative style URL.
	 *
	 * @param string $src Source URL.
	 * @return string
	 */
	private static function normalize_url( $src ) {
		if ( 0 === strpos( $src, '//' ) ) {
			return 'https:' . $src;
		}

		if ( 0 !== strpos( $src, 'http' ) ) {
			return site_url( $src );
		}

		return $src;
	}
}
