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
				$cat = sanitize_text_field( $_REQUEST['category'] ?? '' );
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
				$answers[ (int) $qid ] = sanitize_textarea_field( wp_unslash( $ans ) );
			}
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
			'problem_category' => sanitize_text_field( wp_unslash( $_POST['problem_category'] ?? '' ) ),
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
				if ( isset( $raw['closed_weekdays'] ) && is_array( $raw['closed_weekdays'] ) ) {
					$clean['closed_weekdays'] = array_map( 'intval', $raw['closed_weekdays'] );
				}
				NM_Settings::update( $clean );
				wp_send_json_success( array( 'message' => 'ذخیره شد.' ) );

			case 'update_booking_status':
				$id = (int) ( $_POST['id'] ?? 0 );
				$status = sanitize_key( $_POST['status'] ?? '' );
				NM_Booking::update_status( $id, $status );
				wp_send_json_success( array( 'message' => 'وضعیت به‌روز شد.' ) );

			default:
				wp_send_json_error( array( 'message' => 'اکشن نامعتبر' ), 400 );
		}
	}

	private static function resolve_pay_url( $booking ) {
		$gw = NM_Settings::get( 'payment_gateway', 'auto' );
		$has_zibal = NM_Zibal::enabled();
		$has_wc    = class_exists( 'WooCommerce' );

		// انتخاب صریح
		if ( 'zibal' === $gw ) {
			return $has_zibal ? NM_Zibal::pay_url_for_booking( $booking ) : ( $has_wc ? NM_WooCommerce::create_checkout_for_booking( $booking ) : '' );
		}
		if ( 'woocommerce' === $gw ) {
			return $has_wc ? NM_WooCommerce::create_checkout_for_booking( $booking ) : ( $has_zibal ? NM_Zibal::pay_url_for_booking( $booking ) : '' );
		}

		// auto: اول مرچنت زیبال، اگر نبود ووکامرس
		if ( $has_zibal ) {
			return NM_Zibal::pay_url_for_booking( $booking );
		}
		if ( $has_wc ) {
			return NM_WooCommerce::create_checkout_for_booking( $booking );
		}
		return '';
	}
}
