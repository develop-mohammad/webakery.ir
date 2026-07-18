<?php
defined( 'ABSPATH' ) || exit;

/**
 * AJAX سبک — یک اکشن عمومی + اکشن ادمین، بدون heartbeat اضافه.
 */
class NM_Ajax {

	public static function register() {
		add_action( 'wp_ajax_nm_api', array( __CLASS__, 'handle' ) );
		add_action( 'wp_ajax_nopriv_nm_api', array( __CLASS__, 'handle' ) );
		add_action( 'wp_ajax_nm_admin', array( __CLASS__, 'handle_admin' ) );
	}

	public static function handle() {
		check_ajax_referer( 'nm_public', 'nonce' );
		$action = sanitize_key( $_REQUEST['nm_action'] ?? '' );

		try {
			switch ( $action ) {
				case 'month':
					$jy = (int) ( $_REQUEST['y'] ?? 0 );
					$jm = (int) ( $_REQUEST['m'] ?? 0 );
					$sp = (int) ( $_REQUEST['specialist_id'] ?? 0 );
					if ( ! $jy || ! $jm ) {
						$t = NM_Jalali::today();
						$jy = $t['y']; $jm = $t['m'];
					}
					wp_send_json_success( NM_Availability::month_status( $jy, $jm, $sp ) );

				case 'slots':
					$date = sanitize_text_field( $_REQUEST['date'] ?? '' );
					$sp   = (int) ( $_REQUEST['specialist_id'] ?? 0 );
					wp_send_json_success( array(
						'date'  => $date,
						'slots' => NM_Availability::slots_for_date( $date, $sp ),
					) );

				case 'questions':
					$cat = sanitize_text_field( wp_unslash( $_REQUEST['category'] ?? '' ) );
					wp_send_json_success( array(
						'categories' => NM_Questions::categories(),
						'questions'  => NM_Questions::by_category( $cat ),
					) );

				case 'specialists':
					$list = array_map( function ( $s ) {
						return array(
							'id'       => (int) $s->id,
							'name'     => $s->name,
							'skills'   => $s->skills,
							'price'    => (int) $s->price,
							'price_fa' => NM_Settings::format_price( $s->price ),
							'duration' => (int) $s->duration,
							'bio'      => wp_strip_all_tags( $s->bio ),
							'avatar'   => $s->avatar_id ? wp_get_attachment_image_url( $s->avatar_id, 'thumbnail' ) : '',
						);
					}, NM_Specialist::all_active() );
					wp_send_json_success( $list );

				case 'book':
					self::book();

				default:
					wp_send_json_error( array( 'message' => 'اکشن نامعتبر' ), 400 );
			}
		} catch ( Throwable $e ) {
			error_log( 'Nobat Man AJAX error (' . $action . '): ' . $e->getMessage() );
			wp_send_json_error( array(
				'message' => defined( 'WP_DEBUG' ) && WP_DEBUG
					? $e->getMessage()
					: 'خطای سرور. لطفاً دوباره تلاش کنید.',
			), 500 );
		}
	}

	private static function book() {
		// Rate limit ساده با transient
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? md5( $_SERVER['REMOTE_ADDR'] ) : 'x';
		$key = 'nm_rl_' . $ip;
		$hits = (int) get_transient( $key );
		if ( $hits > 20 ) {
			wp_send_json_error( array( 'message' => 'تعداد درخواست‌ها زیاد است. کمی بعد تلاش کنید.' ), 429 );
		}
		set_transient( $key, $hits + 1, MINUTE_IN_SECONDS );

		$answers = array();
		if ( ! empty( $_POST['answers'] ) && is_array( $_POST['answers'] ) ) {
			foreach ( $_POST['answers'] as $qid => $ans ) {
				if ( is_array( $ans ) ) {
					$ans = implode( ', ', array_map( 'strval', $ans ) );
				}
				$answers[ (int) $qid ] = sanitize_textarea_field( wp_unslash( (string) $ans ) );
			}
		}

		$problem_category = sanitize_text_field( wp_unslash( $_POST['problem_category'] ?? '' ) );
		$answer_check     = NM_Questions::validate_answers( $problem_category, $answers );
		if ( is_wp_error( $answer_check ) ) {
			wp_send_json_error( array( 'message' => $answer_check->get_error_message() ) );
		}

		$data = array(
			'specialist_id'    => (int) ( $_POST['specialist_id'] ?? 0 ),
			'jalali_date'      => sanitize_text_field( wp_unslash( $_POST['jalali_date'] ?? '' ) ),
			'start_time'       => sanitize_text_field( wp_unslash( $_POST['start_time'] ?? '' ) ),
			'duration'         => (int) ( $_POST['duration'] ?? 0 ),
			'customer_name'    => sanitize_text_field( wp_unslash( $_POST['customer_name'] ?? '' ) ),
			'customer_email'   => sanitize_email( wp_unslash( $_POST['customer_email'] ?? '' ) ),
			'customer_phone'   => sanitize_text_field( wp_unslash( $_POST['customer_phone'] ?? '' ) ),
			'customer_city'    => sanitize_text_field( wp_unslash( $_POST['customer_city'] ?? '' ) ),
			'customer_gender'  => sanitize_text_field( wp_unslash( $_POST['customer_gender'] ?? '' ) ),
			'problem_category' => $problem_category,
			'description'      => sanitize_textarea_field( wp_unslash( $_POST['description'] ?? '' ) ),
			'answers'          => $answers,
		);

		$booking = NM_Booking::create( $data );
		if ( is_wp_error( $booking ) ) {
			wp_send_json_error( array( 'message' => $booking->get_error_message() ) );
		}

		$pay_url = self::resolve_pay_url( $booking );

		wp_send_json_success( array(
			'booking_id'   => (int) $booking->id,
			'booking_code' => $booking->booking_code,
			'price'        => (int) $booking->price,
			'price_fa'     => NM_Settings::format_price( $booking->price ),
			'pay_url'      => $pay_url,
			'thank_you'    => NM_Booking::thank_you_html( $booking ),
		) );
	}

	public static function handle_admin() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'دسترسی غیرمجاز' ), 403 );
		}
		check_ajax_referer( 'nm_admin', 'nonce' );
		$action = sanitize_key( $_REQUEST['nm_action'] ?? '' );

		switch ( $action ) {
			case 'save_settings':
				$raw = isset( $_POST['settings'] ) ? (array) wp_unslash( $_POST['settings'] ) : array();
				$clean = array();
				foreach ( $raw as $k => $v ) {
					$key = sanitize_key( $k );
					if ( is_array( $v ) ) {
						$clean[ $key ] = array_map( 'sanitize_text_field', $v );
					} else {
						$clean[ $key ] = wp_kses_post( $v );
					}
				}
				// عددی‌ها
				foreach ( array( 'default_price', 'default_duration', 'min_duration', 'max_duration', 'buffer_minutes', 'slot_step', 'max_upload_mb', 'pending_ttl_hours', 'wc_product_id', 'installment_count' ) as $num ) {
					if ( isset( $clean[ $num ] ) ) {
						$clean[ $num ] = (int) $clean[ $num ];
					}
				}
				if ( isset( $raw['working_weekdays'] ) && is_array( $raw['working_weekdays'] ) ) {
					$working = array_map( 'intval', $raw['working_weekdays'] );
					$clean['closed_weekdays'] = array_values( array_diff( range( 0, 6 ), $working ) );
				} elseif ( isset( $raw['closed_weekdays'] ) && is_array( $raw['closed_weekdays'] ) ) {
					$clean['closed_weekdays'] = NM_Settings::normalize_closed_weekdays( $raw['closed_weekdays'] );
				}
				NM_Settings::update( $clean );
				wp_send_json_success( array( 'message' => 'ذخیره شد.' ) );

			case 'update_booking_status':
				$id = (int) ( $_POST['id'] ?? 0 );
				$status = sanitize_key( $_POST['status'] ?? '' );
				NM_Booking::update_status( $id, $status );
				wp_send_json_success( array( 'message' => 'وضعیت به‌روز شد.' ) );

			case 'save_questions_board':
				$category = sanitize_text_field( wp_unslash( $_POST['category'] ?? '' ) );
				$active   = array();
				if ( ! empty( $_POST['active'] ) ) {
					$decoded = is_string( $_POST['active'] ) ? json_decode( wp_unslash( $_POST['active'] ), true ) : wp_unslash( $_POST['active'] );
					$active  = is_array( $decoded ) ? array_map( 'intval', $decoded ) : array();
				}
				$res = NM_Questions::save_board( $category, $active );
				if ( is_wp_error( $res ) ) {
					wp_send_json_error( array( 'message' => $res->get_error_message() ) );
				}
				$board = NM_Questions::board_for_category( $category );
				wp_send_json_success( array(
					'message'   => 'سوالات ذخیره شد.',
					'category'  => $category,
					'active'    => $board['active'],
					'available' => $board['available'],
					'fields'    => $board['fields'],
				) );

			case 'create_question':
				$category = sanitize_text_field( wp_unslash( $_POST['category'] ?? '' ) );
				$question = sanitize_text_field( wp_unslash( $_POST['question'] ?? '' ) );
				if ( '' === $category ) {
					wp_send_json_error( array( 'message' => 'دسته‌بندی را انتخاب کنید.' ) );
				}
				if ( '' === $question ) {
					wp_send_json_error( array( 'message' => 'متن سوال الزامی است.' ) );
				}
				$opts = array();
				if ( ! empty( $_POST['options_text'] ) ) {
					$opts = array_filter( array_map( 'trim', explode( "\n", (string) wp_unslash( $_POST['options_text'] ) ) ) );
				}
				$id = NM_Questions::save( array(
					'category'    => $category,
					'question'    => $question,
					'type'        => sanitize_key( $_POST['type'] ?? 'text' ),
					'options'     => $opts,
					'is_required' => ! empty( $_POST['is_required'] ),
					'sort_order'  => (int) ( $_POST['sort_order'] ?? 0 ),
					'is_active'   => empty( $_POST['inactive'] ) ? 1 : 0,
				) );
				if ( is_wp_error( $id ) ) {
					wp_send_json_error( array( 'message' => $id->get_error_message() ) );
				}
				$board = NM_Questions::board_for_category( $category );
				wp_send_json_success( array(
					'message'   => 'سوال ساخته شد.',
					'id'        => (int) $id,
					'category'  => $category,
					'active'    => $board['active'],
					'available' => $board['available'],
					'fields'    => $board['fields'],
				) );

			case 'update_question':
				$id = (int) ( $_POST['id'] ?? 0 );
				$q  = NM_Questions::get( $id );
				if ( ! $q ) {
					wp_send_json_error( array( 'message' => 'سوال یافت نشد.' ) );
				}
				$opts = array();
				if ( isset( $_POST['options_text'] ) ) {
					$opts = array_filter( array_map( 'trim', explode( "\n", (string) wp_unslash( $_POST['options_text'] ) ) ) );
				}
				$res = NM_Questions::save( array(
					'category'    => sanitize_text_field( wp_unslash( $_POST['category'] ?? $q->category ) ),
					'question'    => sanitize_text_field( wp_unslash( $_POST['question'] ?? $q->question ) ),
					'type'        => sanitize_key( $_POST['type'] ?? $q->type ),
					'options'     => $opts ?: ( $q->options ?? '' ),
					'is_required' => isset( $_POST['is_required'] ) ? ! empty( $_POST['is_required'] ) : (bool) $q->is_required,
					'sort_order'  => (int) ( $_POST['sort_order'] ?? $q->sort_order ),
					'is_active'   => (int) $q->is_active,
				), $id );
				if ( is_wp_error( $res ) ) {
					wp_send_json_error( array( 'message' => $res->get_error_message() ) );
				}
				$category = sanitize_text_field( wp_unslash( $_POST['category'] ?? $q->category ) );
				$board    = NM_Questions::board_for_category( $category );
				wp_send_json_success( array(
					'message'   => 'سوال به‌روز شد.',
					'category'  => $category,
					'active'    => $board['active'],
					'available' => $board['available'],
					'fields'    => $board['fields'],
				) );

			case 'delete_question':
				$id = (int) ( $_POST['id'] ?? 0 );
				$q  = NM_Questions::get( $id );
				if ( ! $q ) {
					wp_send_json_error( array( 'message' => 'سوال یافت نشد.' ) );
				}
				$category = (string) $q->category;
				NM_Questions::delete( $id );
				$board = NM_Questions::board_for_category( $category );
				wp_send_json_success( array(
					'message'   => 'سوال حذف شد.',
					'category'  => $category,
					'active'    => $board['active'],
					'available' => $board['available'],
					'fields'    => $board['fields'],
				) );

			default:
				wp_send_json_error( array( 'message' => 'اکشن نامعتبر' ), 400 );
		}
	}

	private static function resolve_pay_url( $booking ) {
		return NM_Payments::pay_url_for_booking( $booking );
	}
}
