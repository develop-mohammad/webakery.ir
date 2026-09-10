<?php
defined( 'ABSPATH' ) || exit;

class NCK_Ajax {

	public static function hooks() {
		$front = array( 'nck_sign', 'nck_sign_hall', 'nck_lookup', 'nck_checkin' );
		foreach ( $front as $action ) {
			add_action( 'wp_ajax_' . $action, array( __CLASS__, $action ) );
			add_action( 'wp_ajax_nopriv_' . $action, array( __CLASS__, $action ) );
		}
		add_action( 'wp_ajax_nck_admin_checkin', array( __CLASS__, 'admin_checkin' ) );
		add_action( 'wp_ajax_nck_admin_status', array( __CLASS__, 'admin_status' ) );
	}

	private static function nonce_front() {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : ''; // phpcs:ignore
		if ( ! wp_verify_nonce( $nonce, 'nck_front' ) ) {
			wp_send_json_error( array( 'message' => 'نشست منقضی شده. صفحه را تازه‌سازی کنید.' ), 403 );
		}
	}

	private static function nonce_admin() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => 'دسترسی ندارید.' ), 403 );
		}
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : ''; // phpcs:ignore
		if ( ! wp_verify_nonce( $nonce, 'nck_admin' ) ) {
			wp_send_json_error( array( 'message' => 'نشست منقضی شده. صفحه را تازه‌سازی کنید.' ), 403 );
		}
	}

	private static function rate( $key, $max = 20 ) {
		$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : '0';
		$id  = 'nck_rl_' . md5( $key . '|' . $ip . '|' . gmdate( 'Y-m-d-H' ) );
		$n   = (int) get_transient( $id );
		if ( $n >= $max ) {
			wp_send_json_error( array( 'message' => 'تعداد تلاش بیش از حد است. کمی بعد دوباره امتحان کنید.' ), 429 );
		}
		set_transient( $id, $n + 1, HOUR_IN_SECONDS );
	}

	private static function licensed() {
		if ( ! NCK_Plugin::is_usable() ) {
			wp_send_json_error( array( 'message' => 'لایسنس افزونه فعال نیست.' ) );
		}
	}

	public static function nck_sign() {
		self::nonce_front();
		self::licensed();
		self::rate( 'sign', 12 );

		$name       = isset( $_POST['name'] ) ? wp_unslash( $_POST['name'] ) : ''; // phpcs:ignore
		$honorific  = isset( $_POST['honorific'] ) ? sanitize_key( wp_unslash( $_POST['honorific'] ) ) : 'mr'; // phpcs:ignore
		$phone      = isset( $_POST['phone'] ) ? wp_unslash( $_POST['phone'] ) : ''; // phpcs:ignore
		$plan       = isset( $_POST['plan'] ) ? sanitize_key( wp_unslash( $_POST['plan'] ) ) : ''; // phpcs:ignore
		$signature  = isset( $_POST['signature'] ) ? wp_unslash( $_POST['signature'] ) : ''; // phpcs:ignore
		$agree      = ! empty( $_POST['agree'] ); // phpcs:ignore
		$ip         = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

		if ( ! $agree ) {
			wp_send_json_error( array( 'message' => 'برای ثبت قرارداد باید مفاد آن را بپذیرید.' ) );
		}

		$result = NCK_Contracts::sign_flow( $name, $honorific, $phone, $plan, $signature, $ip );
		if ( empty( $result['ok'] ) ) {
			wp_send_json_error( array( 'message' => $result['message'] ) );
		}

		wp_send_json_success(
			array(
				'message' => $result['message'],
				'print'   => $result['print'],
				'name'    => $result['member']['full_name'],
			)
		);
	}

	public static function nck_sign_hall() {
		self::nonce_front();
		self::licensed();
		self::rate( 'sign_hall', 12 );

		$agree = ! empty( $_POST['agree'] ); // phpcs:ignore
		if ( ! $agree ) {
			wp_send_json_error( array( 'message' => 'برای ثبت قرارداد باید مفاد آن را بپذیرید.' ) );
		}

		$input = array(
			'name'        => isset( $_POST['name'] ) ? wp_unslash( $_POST['name'] ) : '', // phpcs:ignore
			'honorific'   => isset( $_POST['honorific'] ) ? sanitize_key( wp_unslash( $_POST['honorific'] ) ) : 'mr', // phpcs:ignore
			'national_id' => isset( $_POST['national_id'] ) ? wp_unslash( $_POST['national_id'] ) : '', // phpcs:ignore
			'phone'       => isset( $_POST['phone'] ) ? wp_unslash( $_POST['phone'] ) : '', // phpcs:ignore
			'hall_name'   => isset( $_POST['hall_name'] ) ? wp_unslash( $_POST['hall_name'] ) : '', // phpcs:ignore
			'amount'      => isset( $_POST['amount'] ) ? wp_unslash( $_POST['amount'] ) : '', // phpcs:ignore
			'event_date'  => isset( $_POST['event_date'] ) ? wp_unslash( $_POST['event_date'] ) : '', // phpcs:ignore
			'start_hour'  => isset( $_POST['start_hour'] ) ? wp_unslash( $_POST['start_hour'] ) : '', // phpcs:ignore
			'end_hour'    => isset( $_POST['end_hour'] ) ? wp_unslash( $_POST['end_hour'] ) : '', // phpcs:ignore
			'chairs'      => isset( $_POST['chairs'] ) ? wp_unslash( $_POST['chairs'] ) : '', // phpcs:ignore
			'projector'   => ! empty( $_POST['projector'] ), // phpcs:ignore
		);
		$signature = isset( $_POST['signature'] ) ? wp_unslash( $_POST['signature'] ) : ''; // phpcs:ignore
		$ip        = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

		$result = NCK_Contracts::sign_hall_flow( $input, $signature, $ip );
		if ( empty( $result['ok'] ) ) {
			wp_send_json_error( array( 'message' => $result['message'] ) );
		}

		wp_send_json_success(
			array(
				'message' => $result['message'],
				'print'   => $result['print'],
				'name'    => $result['member']['full_name'],
			)
		);
	}

	public static function nck_lookup() {
		self::nonce_front();
		self::licensed();
		self::rate( 'lookup', 30 );

		$phone  = isset( $_POST['phone'] ) ? wp_unslash( $_POST['phone'] ) : ''; // phpcs:ignore
		$member = NCK_Members::by_phone( $phone );
		if ( ! $member ) {
			wp_send_json_error( array( 'message' => 'با این شماره قراردادی پیدا نشد.' ) );
		}

		wp_send_json_success( self::member_payload( $member ) );
	}

	public static function nck_checkin() {
		self::nonce_front();
		self::licensed();
		self::rate( 'checkin', 30 );

		$phone  = isset( $_POST['phone'] ) ? wp_unslash( $_POST['phone'] ) : ''; // phpcs:ignore
		$shift  = isset( $_POST['shift'] ) ? sanitize_key( wp_unslash( $_POST['shift'] ) ) : ''; // phpcs:ignore
		$member = NCK_Members::by_phone( $phone );
		if ( ! $member ) {
			wp_send_json_error( array( 'message' => 'با این شماره عضوی پیدا نشد.' ) );
		}

		$now  = NCK_Jalali::now();
		$slot = NCK_Shifts::current_slot( $now, NCK_Settings::hours() );
		if ( $shift === '' ) {
			$shift = $slot;
		}
		if ( $shift && $slot && $shift !== $slot ) {
			wp_send_json_error( array( 'message' => 'الان خارج از این شیفت هستید. شیفت جاری را انتخاب کنید.' ) );
		}
		if ( ! $shift ) {
			wp_send_json_error( array( 'message' => 'الان خارج از ساعات کاری مجموعه است.' ) );
		}

		$g_date = $now->format( 'Y-m-d' );
		$res    = NCK_Attendance::check_in( (int) $member['id'], $shift, $g_date, 'front' );
		if ( empty( $res['ok'] ) ) {
			wp_send_json_error( array( 'message' => $res['message'] ) );
		}

		$payload            = self::member_payload( $member );
		$payload['message'] = $res['message'];
		wp_send_json_success( $payload );
	}

	public static function admin_checkin() {
		self::nonce_admin();
		$member_id = isset( $_POST['member_id'] ) ? (int) $_POST['member_id'] : 0; // phpcs:ignore
		$shift     = isset( $_POST['shift'] ) ? sanitize_key( wp_unslash( $_POST['shift'] ) ) : ''; // phpcs:ignore
		$date      = isset( $_POST['date'] ) ? sanitize_text_field( wp_unslash( $_POST['date'] ) ) : ''; // phpcs:ignore

		$j = NCK_Jalali::parse( $date );
		if ( ! $j ) {
			wp_send_json_error( array( 'message' => 'تاریخ شمسی را به صورت ۱۴۰۴/۰۶/۲۰ وارد کنید.' ) );
		}
		$g   = NCK_Jalali::to_g_date( $j['y'], $j['m'], $j['d'] );
		$res = NCK_Attendance::check_in( $member_id, $shift, $g, 'admin' );
		if ( empty( $res['ok'] ) ) {
			wp_send_json_error( array( 'message' => $res['message'] ) );
		}
		wp_send_json_success( $res );
	}

	public static function admin_status() {
		self::nonce_admin();
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'دسترسی ندارید.' ), 403 );
		}
		$id     = isset( $_POST['member_id'] ) ? (int) $_POST['member_id'] : 0; // phpcs:ignore
		$status = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : ''; // phpcs:ignore
		NCK_Members::set_status( $id, $status );
		wp_send_json_success( array( 'message' => 'وضعیت عضو به‌روز شد.' ) );
	}

	private static function member_payload( array $member ) {
		$now   = NCK_Jalali::now();
		$today = NCK_Jalali::today();
		$g     = $now->format( 'Y-m-d' );
		$slot  = NCK_Shifts::current_slot( $now, NCK_Settings::hours() );
		$snap  = NCK_Subscriptions::snapshot( (int) $member['id'], $today['y'], $today['m'] );
		$last  = NCK_Contracts::latest_for_member( (int) $member['id'], 'cowork' );
		$ctx   = NCK_Settings::holiday_context( $today['y'], $today['m'], $today['d'] );

		$shifts = array();
		foreach ( $snap as $type => $row ) {
			$day = NCK_Shifts::day_status( $type, $today['y'], $today['m'], $today['d'], $ctx );
			$already = (bool) NCK_Attendance::exists( (int) $member['id'], $g, $type );
			$shifts[ $type ] = array_merge(
				$row,
				array(
					'open'    => ! empty( $day['ok'] ),
					'reason'  => empty( $day['ok'] ) ? $day['title'] : '',
					'already' => $already,
					'current' => ( $slot === $type ),
				)
			);
		}

		return array(
			'name'     => $member['full_name'],
			'phone'    => $member['phone'],
			'status'   => $member['status'],
			'month'    => NCK_Jalali::format_long( $today['y'], $today['m'], 1 ),
			'slot'     => $slot,
			'shifts'   => $shifts,
			'print'    => $last ? NCK_Contracts::print_url( $last['print_token'] ) : '',
			'notice'   => (string) NCK_Settings::get( 'event_notice', '' ),
		);
	}
}
