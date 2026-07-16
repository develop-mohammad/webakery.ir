<?php
/**
 * Lazy load images and iframes.
 *
 * @package WebakerySpeed
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WBS_Fix_Lazyload {

	public static function boot() {
		add_filter( 'wp_get_attachment_image_attributes', array( __CLASS__, 'image_attrs' ), 20, 3 );
		add_filter( 'the_content', array( __CLASS__, 'content_lazy' ), 20 );
		add_filter( 'post_thumbnail_html', array( __CLASS__, 'content_lazy' ), 20 );
	}

	public static function image_attrs( $attr ) {
		if ( is_admin() ) {
			return $attr;
		}
		if ( empty( $attr['loading'] ) ) {
			$attr['loading'] = 'lazy';
		}
		if ( empty( $attr['decoding'] ) ) {
			$attr['decoding'] = 'async';
		}
		return $attr;
	}

	public static function content_lazy( $html ) {
		if ( is_admin() || empty( $html ) ) {
			return $html;
		}

		$html = preg_replace_callback(
			'/<img\b([^>]*?)>/i',
			function ( $matches ) {
				$tag = $matches[0];
				if ( false !== stripos( $tag, 'loading=' ) ) {
					return $tag;
				}
				return str_replace( '<img', '<img loading="lazy" decoding="async"', $tag );
			},
			$html
		);

		$html = preg_replace_callback(
			'/<iframe\b([^>]*?)>/i',
			function ( $matches ) {
				$tag = $matches[0];
				if ( false !== stripos( $tag, 'loading=' ) ) {
					return $tag;
				}
				return str_replace( '<iframe', '<iframe loading="lazy"', $tag );
			},
			$html
		);

		return $html;
	}
}
