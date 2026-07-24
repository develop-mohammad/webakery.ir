<?php
defined( 'ABSPATH' ) || exit;

class WBCB_Conversations {

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'wbcb_conversations';
	}

	public static function now() {
		return current_time( 'mysql', true );
	}

	public static function generate_token() {
		return substr( hash( 'sha256', wp_generate_password( 24, true, true ) . microtime( true ) ), 0, 48 );
	}

	public static function get_by_token( $token ) {
		global $wpdb;
		$token = sanitize_text_field( (string) $token );
		if ( strlen( $token ) < 16 ) {
			return null;
		}
		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . self::table() . ' WHERE visitor_token = %s LIMIT 1',
				$token
			),
			ARRAY_A
		);
		return is_array( $row ) ? $row : null;
	}

	public static function get( $id ) {
		global $wpdb;
		$id = (int) $id;
		if ( $id <= 0 ) {
			return null;
		}
		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . self::table() . ' WHERE id = %d LIMIT 1',
				$id
			),
			ARRAY_A
		);
		return is_array( $row ) ? $row : null;
	}

	public static function get_or_create( $token, array $data = array() ) {
		$existing = self::get_by_token( $token );
		if ( $existing ) {
			return $existing;
		}
		global $wpdb;
		$now   = self::now();
		$token = $token ? sanitize_text_field( $token ) : self::generate_token();
		$wpdb->insert(
			self::table(),
			array(
				'visitor_token'   => $token,
				'visitor_name'    => sanitize_text_field( $data['visitor_name'] ?? '' ),
				'visitor_email'   => sanitize_email( $data['visitor_email'] ?? '' ),
				'page_url'        => esc_url_raw( $data['page_url'] ?? '' ),
				'page_title'      => sanitize_text_field( $data['page_title'] ?? '' ),
				'product_id'      => max( 0, (int) ( $data['product_id'] ?? 0 ) ),
				'product_name'    => sanitize_text_field( $data['product_name'] ?? '' ),
				'product_url'     => esc_url_raw( $data['product_url'] ?? '' ),
				'product_image'   => esc_url_raw( $data['product_image'] ?? '' ),
				'status'          => 'open',
				'unread_admin'    => 0,
				'last_message_at' => null,
				'created_at'      => $now,
				'updated_at'      => $now,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s' )
		);
		return self::get_by_token( $token );
	}

	public static function update_visitor( $id, array $data ) {
		global $wpdb;
		$id = (int) $id;
		if ( $id <= 0 ) {
			return false;
		}
		$fields = array(
			'updated_at' => self::now(),
		);
		$format = array( '%s' );
		if ( array_key_exists( 'visitor_name', $data ) ) {
			$fields['visitor_name'] = sanitize_text_field( (string) $data['visitor_name'] );
			$format[]               = '%s';
		}
		if ( array_key_exists( 'visitor_email', $data ) ) {
			$fields['visitor_email'] = sanitize_email( (string) $data['visitor_email'] );
			$format[]                = '%s';
		}
		if ( array_key_exists( 'page_url', $data ) ) {
			$fields['page_url'] = esc_url_raw( (string) $data['page_url'] );
			$format[]           = '%s';
		}
		if ( array_key_exists( 'page_title', $data ) ) {
			$fields['page_title'] = sanitize_text_field( (string) $data['page_title'] );
			$format[]             = '%s';
		}
		if ( array_key_exists( 'product_id', $data ) ) {
			$fields['product_id'] = max( 0, (int) $data['product_id'] );
			$format[]             = '%d';
		}
		if ( array_key_exists( 'product_name', $data ) ) {
			$fields['product_name'] = sanitize_text_field( (string) $data['product_name'] );
			$format[]               = '%s';
		}
		if ( array_key_exists( 'product_url', $data ) ) {
			$fields['product_url'] = esc_url_raw( (string) $data['product_url'] );
			$format[]              = '%s';
		}
		if ( array_key_exists( 'product_image', $data ) ) {
			$fields['product_image'] = esc_url_raw( (string) $data['product_image'] );
			$format[]                = '%s';
		}
		if ( array_key_exists( 'status', $data ) ) {
			$st = sanitize_key( (string) $data['status'] );
			$fields['status'] = in_array( $st, array( 'open', 'closed' ), true ) ? $st : 'open';
			$format[]         = '%s';
		}
		return false !== $wpdb->update( self::table(), $fields, array( 'id' => $id ), $format, array( '%d' ) );
	}

	/**
	 * به‌روزرسانی زمینه صفحه/محصول + پیام سیستمی برای ادمین.
	 *
	 * @return array گفتگوی به‌روز
	 */
	public static function sync_page_context( $conversation_id, array $ctx ) {
		$conv = self::get( (int) $conversation_id );
		if ( ! $conv ) {
			return null;
		}

		$page_url      = esc_url_raw( (string) ( $ctx['page_url'] ?? '' ) );
		$page_title    = sanitize_text_field( (string) ( $ctx['page_title'] ?? '' ) );
		$product_id    = max( 0, (int) ( $ctx['product_id'] ?? 0 ) );
		$product_name  = sanitize_text_field( (string) ( $ctx['product_name'] ?? '' ) );
		$product_url   = esc_url_raw( (string) ( $ctx['product_url'] ?? '' ) );
		$product_image = esc_url_raw( (string) ( $ctx['product_image'] ?? '' ) );

		if ( $product_id > 0 && function_exists( 'wc_get_product' ) ) {
			$p = wc_get_product( $product_id );
			if ( $p ) {
				if ( '' === $product_name ) {
					$product_name = $p->get_name();
				}
				if ( ! $product_url ) {
					$product_url = get_permalink( $product_id );
				}
				if ( ! $product_image ) {
					$img_id = (int) $p->get_image_id();
					if ( $img_id ) {
						$product_image = (string) wp_get_attachment_image_url( $img_id, 'medium' );
					}
					if ( ! $product_image ) {
						$product_image = (string) wp_get_attachment_image_url( $img_id, 'woocommerce_thumbnail' );
					}
					if ( ! $product_image && function_exists( 'wc_placeholder_img_src' ) ) {
						$product_image = (string) wc_placeholder_img_src( 'woocommerce_thumbnail' );
					}
				}
			}
		}
		if ( $product_id > 0 && ! $product_image ) {
			$thumb = get_the_post_thumbnail_url( $product_id, 'medium' );
			if ( $thumb ) {
				$product_image = $thumb;
			}
		}
		if ( $product_id > 0 && ! $product_url ) {
			$product_url = get_permalink( $product_id ) ?: $page_url;
		}
		if ( ! $page_url && $product_url ) {
			$page_url = $product_url;
		}
		if ( ! $page_title && $product_name ) {
			$page_title = $product_name;
		}

		$prev_pid = (int) ( $conv['product_id'] ?? 0 );
		$prev_url = (string) ( $conv['page_url'] ?? '' );

		self::update_visitor(
			(int) $conv['id'],
			array(
				'page_url'       => $page_url ?: $prev_url,
				'page_title'     => $page_title,
				'product_id'     => $product_id,
				'product_name'   => $product_name,
				'product_url'    => $product_url,
				'product_image'  => $product_image,
			)
		);

		$changed_product = $product_id > 0 && $product_id !== $prev_pid;
		$first_product   = $product_id > 0 && $prev_pid <= 0;
		$changed_page    = $page_url && $page_url !== $prev_url && ! $product_id;

		if ( $changed_product || $first_product ) {
			$body = "🛒 بازدید از محصول\n"
				. 'نام: ' . ( $product_name ?: ( 'محصول #' . $product_id ) ) . "\n"
				. 'لینک: ' . ( $product_url ?: $page_url );
			WBCB_Messages::add(
				(int) $conv['id'],
				'system',
				$body,
				array(
					'type'           => 'product_context',
					'product_id'     => $product_id,
					'product_name'   => $product_name,
					'product_url'    => $product_url,
					'product_image'  => $product_image,
				)
			);
		} elseif ( $changed_page && $page_title ) {
			$body = "📄 صفحه فعلی\n"
				. 'عنوان: ' . $page_title . "\n"
				. 'لینک: ' . $page_url;
			WBCB_Messages::add(
				(int) $conv['id'],
				'system',
				$body,
				array(
					'type'       => 'page_context',
					'page_title' => $page_title,
					'page_url'   => $page_url,
				)
			);
		}

		return self::get( (int) $conv['id'] );
	}

	public static function touch_message( $id, $unread_admin = null ) {
		global $wpdb;
		$id = (int) $id;
		if ( $id <= 0 ) {
			return;
		}
		$data = array(
			'last_message_at' => self::now(),
			'updated_at'      => self::now(),
		);
		$fmt  = array( '%s', '%s' );
		if ( null !== $unread_admin ) {
			$data['unread_admin'] = $unread_admin ? 1 : 0;
			$fmt[]                = '%d';
		}
		$wpdb->update( self::table(), $data, array( 'id' => $id ), $fmt, array( '%d' ) );
	}

	public static function mark_read( $id ) {
		global $wpdb;
		$id = (int) $id;
		if ( $id <= 0 ) {
			return;
		}
		$wpdb->update(
			self::table(),
			array(
				'unread_admin' => 0,
				'updated_at'   => self::now(),
			),
			array( 'id' => $id ),
			array( '%d', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * @return array{items:array,total:int}
	 */
	public static function list_admin( $args = array() ) {
		global $wpdb;
		$status = sanitize_key( $args['status'] ?? '' );
		$search = sanitize_text_field( $args['search'] ?? '' );
		$page   = max( 1, (int) ( $args['page'] ?? 1 ) );
		$per    = max( 5, min( 50, (int) ( $args['per_page'] ?? 20 ) ) );
		$offset = ( $page - 1 ) * $per;

		$where = array( '1=1' );
		$bind  = array();

		if ( $status && in_array( $status, array( 'open', 'closed' ), true ) ) {
			$where[] = 'status = %s';
			$bind[]  = $status;
		}
		if ( $search ) {
			$like    = '%' . $wpdb->esc_like( $search ) . '%';
			$where[] = '(visitor_name LIKE %s OR visitor_email LIKE %s OR visitor_token LIKE %s OR product_name LIKE %s OR page_title LIKE %s)';
			$bind[]  = $like;
			$bind[]  = $like;
			$bind[]  = $like;
			$bind[]  = $like;
			$bind[]  = $like;
		}

		$sql_count = 'SELECT COUNT(*) FROM ' . self::table() . ' WHERE ' . implode( ' AND ', $where );
		if ( $bind ) {
			$sql_count = $wpdb->prepare( $sql_count, $bind ); // phpcs:ignore
		}
		$total = (int) $wpdb->get_var( $sql_count ); // phpcs:ignore

		$sql = 'SELECT * FROM ' . self::table() . ' WHERE ' . implode( ' AND ', $where )
			. ' ORDER BY COALESCE(last_message_at, created_at) DESC LIMIT %d OFFSET %d';
		$bind[] = $per;
		$bind[] = $offset;
		$rows   = $wpdb->get_results( $wpdb->prepare( $sql, $bind ), ARRAY_A ); // phpcs:ignore

		return array(
			'items' => is_array( $rows ) ? $rows : array(),
			'total' => $total,
		);
	}

	public static function unread_count() {
		global $wpdb;
		return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . self::table() . ' WHERE unread_admin = 1 AND status = \'open\'' ); // phpcs:ignore
	}
}
