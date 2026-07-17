<?php
defined( 'ABSPATH' ) || exit;

class NM_Booking {

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'nm_bookings';
	}

	public static function generate_code() {
		return strtoupper( 'NM' . wp_generate_password( 8, false, false ) );
	}

	public static function create( array $data ) {
		global $wpdb;

		$jalali = NM_Jalali::parse( $data['jalali_date'] ?? '' );
		if ( ! $jalali ) {
			return new WP_Error( 'bad_date', 'تاریخ شمسی نامعتبر است.' );
		}

		$specialist_id = (int) ( $data['specialist_id'] ?? 0 );
		$start         = sanitize_text_field( $data['start_time'] ?? '' );
		$duration      = max(
			(int) NM_Settings::get( 'min_duration', 5 ),
			min( (int) NM_Settings::get( 'max_duration', 300 ), (int) ( $data['duration'] ?? NM_Availability::duration_for( $specialist_id ) ) )
		);

		$slots = NM_Availability::slots_for_date( $data['jalali_date'], $specialist_id );
		$match = null;
		foreach ( $slots as $s ) {
			if ( $s['start'] === substr( $start, 0, 5 ) || $s['start'] . ':00' === $start ) {
				$match = $s;
				break;
			}
		}
		if ( ! $match ) {
			return new WP_Error( 'slot_taken', 'این نوبت دیگر در دسترس نیست.' );
		}

		$attachments = self::handle_uploads( $data );
		if ( is_wp_error( $attachments ) ) {
			return $attachments;
		}

		$now  = current_time( 'mysql' );
		$code = self::generate_code();
		$row  = array(
			'booking_code'     => $code,
			'specialist_id'    => $specialist_id,
			'business_id'      => (int) ( $data['business_id'] ?? 0 ) ?: null,
			'customer_name'    => sanitize_text_field( $data['customer_name'] ?? '' ),
			'customer_email'   => sanitize_email( $data['customer_email'] ?? '' ),
			'customer_phone'   => sanitize_text_field( $data['customer_phone'] ?? '' ),
			'customer_city'    => sanitize_text_field( $data['customer_city'] ?? '' ),
			'customer_gender'  => sanitize_text_field( $data['customer_gender'] ?? '' ),
			'jalali_date'      => NM_Jalali::format( $jalali['y'], $jalali['m'], $jalali['d'] ),
			'g_date'           => NM_Jalali::to_g_date( $jalali['y'], $jalali['m'], $jalali['d'] ),
			'start_time'       => $match['start'] . ':00',
			'end_time'         => $match['end'] . ':00',
			'duration'         => (int) $match['duration'],
			'price'            => (int) $match['price'],
			'currency'         => 'IRT',
			'status'           => 'pending',
			'payment_status'   => 'unpaid',
			'problem_category' => sanitize_text_field( $data['problem_category'] ?? '' ),
			'description'      => sanitize_textarea_field( $data['description'] ?? '' ),
			'answers'          => wp_json_encode( $data['answers'] ?? array() ),
			'attachments'      => wp_json_encode( $attachments ),
			'meta'             => wp_json_encode( array( 'ip' => self::client_ip() ) ),
			'created_at'       => $now,
			'updated_at'       => $now,
		);

		if ( empty( $row['customer_name'] ) || empty( $row['customer_phone'] ) ) {
			return new WP_Error( 'required', 'نام و شماره تماس الزامی است.' );
		}

		$ok = $wpdb->insert( self::table(), $row );
		if ( ! $ok ) {
			return new WP_Error( 'db', 'خطا در ثبت نوبت.' );
		}

		$id = (int) $wpdb->insert_id;
		$booking = self::get( $id );

		do_action( 'nm_booking_created', $booking );

		return $booking;
	}

	public static function get( $id ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id = %d', $id ) );
		return $row ?: null;
	}

	public static function get_by_code( $code ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE booking_code = %s', $code ) );
	}

	public static function update_status( $id, $status, $payment_status = null ) {
		global $wpdb;
		$data = array(
			'status'     => sanitize_key( $status ),
			'updated_at' => current_time( 'mysql' ),
		);
		if ( null !== $payment_status ) {
			$data['payment_status'] = sanitize_key( $payment_status );
		}
		$wpdb->update( self::table(), $data, array( 'id' => (int) $id ) );
		$booking = self::get( $id );
		do_action( 'nm_booking_status_changed', $booking, $status );
		return $booking;
	}

	public static function mark_paid( $id, $order_id = 0 ) {
		global $wpdb;
		$invoice = 'NM-' . date( 'Ymd' ) . '-' . $id;
		$wpdb->update(
			self::table(),
			array(
				'status'         => 'confirmed',
				'payment_status' => 'paid',
				'order_id'       => (int) $order_id,
				'invoice_no'     => $invoice,
				'updated_at'     => current_time( 'mysql' ),
			),
			array( 'id' => (int) $id )
		);
		$booking = self::get( $id );
		do_action( 'nm_booking_paid', $booking );
		return $booking;
	}

	public static function query( array $args = array() ) {
		global $wpdb;
		$table = self::table();
		$where = array( '1=1' );
		$params = array();

		if ( ! empty( $args['status'] ) ) {
			$where[] = 'status = %s';
			$params[] = $args['status'];
		}
		if ( ! empty( $args['specialist_id'] ) ) {
			$where[] = 'specialist_id = %d';
			$params[] = (int) $args['specialist_id'];
		}
		if ( ! empty( $args['search'] ) ) {
			$where[] = '(customer_name LIKE %s OR customer_phone LIKE %s OR booking_code LIKE %s)';
			$like = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$params[] = $like; $params[] = $like; $params[] = $like;
		}

		$limit  = max( 1, min( 200, (int) ( $args['limit'] ?? 50 ) ) );
		$offset = max( 0, (int) ( $args['offset'] ?? 0 ) );
		$sql = "SELECT * FROM {$table} WHERE " . implode( ' AND ', $where ) . " ORDER BY g_date DESC, start_time DESC LIMIT {$limit} OFFSET {$offset}";
		if ( $params ) {
			$sql = $wpdb->prepare( $sql, $params );
		}
		return $wpdb->get_results( $sql );
	}

	public static function thank_you_html( $booking ) {
		$tpl = (string) NM_Settings::get( 'thank_you_text', '' );
		$map = array(
			'{booking_code}' => $booking->booking_code,
			'{jalali_date}'  => $booking->jalali_date,
			'{start_time}'   => substr( $booking->start_time, 0, 5 ),
			'{end_time}'     => substr( $booking->end_time, 0, 5 ),
			'{customer_name}'=> $booking->customer_name,
			'{price}'        => NM_Settings::format_price( $booking->price ),
			'{site_url}'     => home_url( '/' ),
			'{invoice_no}'   => $booking->invoice_no ?: '',
		);
		$html = strtr( $tpl, $map );
		return wp_kses_post( wpautop( $html ) );
	}

	private static function handle_uploads( array $data ) {
		$out = array();
		$allow_photo = (int) NM_Settings::get( 'enable_photo', 1 );
		$allow_voice = (int) NM_Settings::get( 'enable_voice', 1 );
		$max_mb = max( 1, (int) NM_Settings::get( 'max_upload_mb', 8 ) );

		if ( empty( $_FILES ) ) {
			return $out;
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$fields = array();
		if ( $allow_photo ) $fields[] = 'photo';
		if ( $allow_voice ) $fields[] = 'voice';

		foreach ( $fields as $field ) {
			if ( empty( $_FILES[ $field ] ) || empty( $_FILES[ $field ]['tmp_name'] ) ) {
				continue;
			}
			if ( (int) $_FILES[ $field ]['size'] > $max_mb * MB_IN_BYTES ) {
				return new WP_Error( 'file_size', 'حجم فایل بیش از حد مجاز است.' );
			}
			$type = (string) ( $_FILES[ $field ]['type'] ?? '' );
			if ( 'photo' === $field && 0 !== strpos( $type, 'image/' ) ) {
				return new WP_Error( 'file_type', 'فقط تصویر مجاز است.' );
			}
			if ( 'voice' === $field && ! preg_match( '#^(audio/|video/webm)#', $type ) ) {
				return new WP_Error( 'file_type', 'فرمت ویس نامعتبر است.' );
			}
			$attach_id = media_handle_upload( $field, 0 );
			if ( is_wp_error( $attach_id ) ) {
				return $attach_id;
			}
			$out[ $field ] = (int) $attach_id;
		}
		return $out;
	}

	private static function client_ip() {
		return isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	}
}
