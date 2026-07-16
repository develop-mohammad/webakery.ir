<?php
/**
 * Preload critical font files and Google Fonts CSS.
 *
 * @package WebakerySpeed
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Outputs font preload link tags in head.
 */
class WBS_Fix_Preload_Fonts {

	/**
	 * Boot hooks.
	 */
	public static function boot() {
		add_action( 'wp_head', array( __CLASS__, 'output' ), 2 );
	}

	/**
	 * Print preload tags.
	 */
	public static function output() {
		if ( is_admin() ) {
			return;
		}

		foreach ( self::get_items() as $item ) {
			self::print_preload( $item );
		}
	}

	/**
	 * Collect preload candidates.
	 *
	 * @return array
	 */
	private static function get_items() {
		$items = array();

		global $wp_styles;
		if ( $wp_styles instanceof WP_Styles ) {
			foreach ( (array) $wp_styles->queue as $handle ) {
				if ( empty( $wp_styles->registered[ $handle ]->src ) ) {
					continue;
				}

				$src = self::normalize_url( $wp_styles->registered[ $handle ]->src );
				if ( false !== strpos( $src, 'fonts.googleapis.com' ) ) {
				$items[] = array(
					'url'  => self::apply_font_display_swap( $src ),
					'as'   => 'style',
					'type' => '',
				);
				}
			}
		}

		$manual = WBS_Settings::get_one( 'preload_font_urls', '' );
		$lines  = preg_split( '/\r\n|\r|\n/', (string) $manual );

		foreach ( $lines as $line ) {
			$line = trim( $line );
			if ( '' === $line ) {
				continue;
			}

			$url = esc_url_raw( $line );
			if ( empty( $url ) ) {
				continue;
			}

			$path = wp_parse_url( $url, PHP_URL_PATH );
			$ext  = strtolower( pathinfo( (string) $path, PATHINFO_EXTENSION ) );

			if ( in_array( $ext, array( 'woff2', 'woff', 'ttf', 'otf' ), true ) ) {
				$mime = 'font/woff2';
				if ( 'woff' === $ext ) {
					$mime = 'font/woff';
				} elseif ( 'ttf' === $ext ) {
					$mime = 'font/ttf';
				} elseif ( 'otf' === $ext ) {
					$mime = 'font/otf';
				}

				$items[] = array(
					'url'         => $url,
					'as'          => 'font',
					'type'        => $mime,
					'crossorigin' => true,
				);
				continue;
			}

			if ( false !== strpos( $url, 'fonts.googleapis.com' ) ) {
				$items[] = array(
					'url'  => self::apply_font_display_swap( $url ),
					'as'   => 'style',
					'type' => '',
				);
			}
		}

		/**
		 * Filter preload font items.
		 *
		 * @param array $items Each item: url, as, type, crossorigin?
		 */
		$items = apply_filters( 'webakery_speed_preload_fonts', $items );

		$seen = array();
		$out  = array();
		foreach ( $items as $item ) {
			if ( empty( $item['url'] ) || isset( $seen[ $item['url'] ] ) ) {
				continue;
			}
			$seen[ $item['url'] ] = true;
			$out[]                = $item;
		}

		return $out;
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

	/**
	 * Apply display=swap when font-display fix is active.
	 *
	 * @param string $url Font CSS URL.
	 * @return string
	 */
	private static function apply_font_display_swap( $url ) {
		if ( ! WBS_Settings::is_fix_enabled( 'font_display' ) ) {
			return $url;
		}

		$url = apply_filters( 'webakery_speed_font_css_url', $url );

		return is_string( $url ) ? $url : '';
	}

	/**
	 * Echo one preload tag.
	 *
	 * @param array $item Preload item.
	 */
	private static function print_preload( $item ) {
		$attrs = sprintf(
			'rel="preload" href="%s" as="%s"',
			esc_url( $item['url'] ),
			esc_attr( $item['as'] )
		);

		if ( ! empty( $item['type'] ) ) {
			$attrs .= ' type="' . esc_attr( $item['type'] ) . '"';
		}

		if ( ! empty( $item['crossorigin'] ) ) {
			$attrs .= ' crossorigin';
		}

		echo '<link ' . $attrs . " />\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
