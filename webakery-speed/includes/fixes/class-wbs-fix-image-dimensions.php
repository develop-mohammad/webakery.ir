<?php
/**
 * Add width/height to unsized images.
 *
 * @package WebakerySpeed
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WBS_Fix_Image_Dimensions {

	public static function boot() {
		add_filter( 'the_content', array( __CLASS__, 'add_dimensions' ), 25 );
		add_filter( 'post_thumbnail_html', array( __CLASS__, 'add_dimensions' ), 25 );
	}

	public static function add_dimensions( $html ) {
		if ( is_admin() || empty( $html ) ) {
			return $html;
		}

		return preg_replace_callback(
			'/<img\b([^>]*?)>/i',
			function ( $matches ) {
				$tag  = $matches[0];
				$attr = $matches[1];

				if ( preg_match( '/\bwidth\s*=/i', $attr ) && preg_match( '/\bheight\s*=/i', $attr ) ) {
					return $tag;
				}

				if ( ! preg_match( '/\bsrc=["\']([^"\']+)["\']/i', $attr, $src_match ) ) {
					return $tag;
				}

				$size = self::size_from_src( $src_match[1] );
				if ( ! $size ) {
					return $tag;
				}

				$insert = '';
				if ( ! preg_match( '/\bwidth\s*=/i', $attr ) ) {
					$insert .= ' width="' . esc_attr( $size[0] ) . '"';
				}
				if ( ! preg_match( '/\bheight\s*=/i', $attr ) ) {
					$insert .= ' height="' . esc_attr( $size[1] ) . '"';
				}

				return str_replace( '<img', '<img' . $insert, $tag );
			},
			$html
		);
	}

	private static function size_from_src( $src ) {
		$attachment_id = attachment_url_to_postid( $src );
		if ( $attachment_id ) {
			$meta = wp_get_attachment_metadata( $attachment_id );
			if ( ! empty( $meta['width'] ) && ! empty( $meta['height'] ) ) {
				return array( (int) $meta['width'], (int) $meta['height'] );
			}
		}

		if ( function_exists( 'wp_getimagesize' ) ) {
			$size = wp_getimagesize( $src );
			if ( $size ) {
				return array( (int) $size[0], (int) $size[1] );
			}
		}

		return null;
	}
}
