<?php
defined( 'ABSPATH' ) || exit;

class WBCN_Content {

	/**
	 * @return array<string,string>|null
	 */
	public static function item_from_post( $post ) {
		$post = get_post( $post );
		if ( ! $post ) {
			return null;
		}
		$excerpt = has_excerpt( $post ) ? $post->post_excerpt : wp_trim_words( wp_strip_all_tags( $post->post_content ), 36, '…' );
		$url     = get_permalink( $post );
		return array(
			'id'      => (string) $post->ID,
			'title'   => get_the_title( $post ),
			'excerpt' => $excerpt,
			'content' => $post->post_content,
			'url'     => $url ? $url : '',
			'type'    => $post->post_type,
			'site'    => wp_parse_url( home_url(), PHP_URL_HOST ),
			'image'   => get_the_post_thumbnail_url( $post, 'large' ) ?: '',
		);
	}

	/**
	 * @param string[] $post_types
	 * @return array<int,array<string,string>>
	 */
	public static function recent_items( $limit = 12, array $post_types = array() ) {
		$s     = WBCN_Settings::get();
		$types = $post_types ?: (array) $s['post_types'];
		$q     = get_posts(
			array(
				'post_type'      => $types,
				'post_status'    => 'publish',
				'posts_per_page' => max( 1, (int) $limit ),
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);
		$out = array();
		foreach ( $q as $p ) {
			$item = self::item_from_post( $p );
			if ( $item ) {
				$out[] = $item;
			}
		}
		return $out;
	}

	/**
	 * مطلبی که اخیراً به کانال ایده روزانه ارسال نشده.
	 *
	 * @return array<string,string>|null
	 */
	public static function pick_for_daily() {
		$used = array_map( 'intval', (array) get_option( 'wbcn_daily_used', array() ) );
		$items = self::recent_items( 30 );
		foreach ( $items as $item ) {
			$id = (int) $item['id'];
			if ( ! in_array( $id, $used, true ) ) {
				return $item;
			}
		}
		if ( $items ) {
			delete_option( 'wbcn_daily_used' );
			return $items[0];
		}
		return null;
	}

	public static function mark_daily_used( $post_id ) {
		$used   = array_map( 'intval', (array) get_option( 'wbcn_daily_used', array() ) );
		$used[] = (int) $post_id;
		$used   = array_values( array_unique( $used ) );
		if ( count( $used ) > 40 ) {
			$used = array_slice( $used, -40 );
		}
		update_option( 'wbcn_daily_used', $used, false );
	}

	public static function search_items( $q, $limit = 5 ) {
		$s = WBCN_Settings::get();
		$found = get_posts(
			array(
				's'              => $q,
				'post_type'      => (array) $s['post_types'],
				'post_status'    => 'publish',
				'posts_per_page' => max( 1, (int) $limit ),
			)
		);
		$out = array();
		foreach ( $found as $p ) {
			$item = self::item_from_post( $p );
			if ( $item ) {
				$out[] = $item;
			}
		}
		return $out;
	}
}
