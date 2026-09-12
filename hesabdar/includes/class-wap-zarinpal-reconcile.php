<?php
defined( 'ABSPATH' ) || exit;

/**
 * پایش تسویه‌های زرین‌پال (واریز شاپرک به حساب) و ارسال پیامک.
 *
 * زرین‌پال وب‌هوک لحظه‌ای برای تسویه ندارد؛ با GraphQL وضعیت Reconciliation
 * را چک می‌کنیم. وضعیت PAID = تسویه کامل / واریز شده.
 *
 * نیاز به Access Token (OAuth پنل زرین‌پال) و terminal_id (نه merchant UUID).
 */
class WAP_Zarinpal_Reconcile {

	const CRON_HOOK   = 'wap_poll_zarinpal_reconciles';
	const NOTIFIED_OPT = 'wap_zarinpal_reconcile_notified';
	const GRAPHQL_URL  = 'https://next.zarinpal.com/api/v4/graphql/';

	public static function init(): void {
		add_action( self::CRON_HOOK, array( __CLASS__, 'cron_poll' ) );
		add_action( 'admin_post_wap_poll_reconciles_now', array( __CLASS__, 'handle_manual_poll' ) );
		add_action( 'admin_post_wap_test_settle_sms', array( __CLASS__, 'handle_test_sms' ) );
		add_action( 'init', array( __CLASS__, 'maybe_schedule' ), 20 );
	}

	public static function maybe_schedule(): void {
		if ( ! class_exists( 'WAP_SMS' ) ) {
			return;
		}
		$enabled = (int) WAP_SMS::get( 'settle_enabled', 0 );
		$has     = wp_next_scheduled( self::CRON_HOOK );
		if ( $enabled && ! $has ) {
			wp_schedule_event( time() + 120, 'hourly', self::CRON_HOOK );
		} elseif ( ! $enabled && $has ) {
			wp_unschedule_event( $has, self::CRON_HOOK );
		}
	}

	public static function cron_poll(): void {
		self::poll_and_notify( false );
	}

	public static function handle_manual_poll(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized' );
		}
		check_admin_referer( 'wap_poll_reconciles_now' );
		$result = self::poll_and_notify( true );
		$redirect = add_query_arg(
			array(
				'page'            => 'wap-payment-sms',
				'wap_settle_msg'  => rawurlencode( is_wp_error( $result ) ? $result->get_error_message() : (string) $result ),
			),
			admin_url( 'admin.php' )
		);
		wp_safe_redirect( $redirect );
		exit;
	}

	/** ارسال پیامک تست با متن واریز شاپرک. */
	public static function handle_test_sms(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized' );
		}
		check_admin_referer( 'wap_test_settle_sms' );
		$phone = isset( $_POST['wap_test_phone'] ) ? WAP_SMS::normalize_phone( (string) wp_unslash( $_POST['wap_test_phone'] ) ) : '';
		if ( $phone === '' ) {
			$list = WAP_SMS::recipient_list();
			$phone = $list[0] ?? '';
		}
		$msg_key = 'no_phone';
		if ( $phone !== '' ) {
			$msg = WAP_SMS::render_settle_message(
				array(
					'amount'        => '1,250,000',
					'amount_rial'   => '12,500,000',
					'reference_id'  => 'TEST-REF',
					'reconcile_id'  => 'test',
					'status'        => 'PAID',
					'reconciled_at' => current_time( 'mysql' ),
					'payable_at'    => current_time( 'mysql' ),
				)
			);
			$r = WAP_SMS::send( $phone, $msg );
			$msg_key = is_wp_error( $r ) ? $r->get_error_message() : 'ok';
		}
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'        => 'wap-payment-sms',
					'wap_sms_msg' => rawurlencode( $msg_key ),
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * @return string|WP_Error خلاصهٔ فارسی یا خطا
	 */
	public static function poll_and_notify( bool $manual = false ) {
		if ( ! class_exists( 'WAP_SMS' ) ) {
			return new WP_Error( 'wap_sms', 'ماژول پیامک موجود نیست.' );
		}
		if ( ! (int) WAP_SMS::get( 'settle_enabled', 0 ) && ! $manual ) {
			return 'پایش تسویه غیرفعال است.';
		}

		$token      = trim( (string) WAP_SMS::get( 'zp_access_token', '' ) );
		$terminal_id = trim( (string) WAP_SMS::get( 'zp_terminal_id', '' ) );
		if ( $token === '' || $terminal_id === '' ) {
			return new WP_Error( 'wap_zp_cfg', 'Access Token و Terminal ID زرین‌پال را در تنظیمات وارد کنید.' );
		}

		$items = self::fetch_reconciles( $token, $terminal_id );
		if ( is_wp_error( $items ) ) {
			return $items;
		}

		$notified = get_option( self::NOTIFIED_OPT, array() );
		if ( ! is_array( $notified ) ) {
			$notified = array();
		}

		/*
		 * اولین پایش موفق: تسویه‌های قبلی را بدون پیامک ثبت کن
		 * تا سیل پیامک برای واریزهای تاریخی رخ ندهد.
		 */
		$is_seed    = empty( $notified );
		$sent_count = 0;
		$paid_count = 0;
		$recipients = WAP_SMS::recipient_list();

		foreach ( $items as $row ) {
			$id     = isset( $row['id'] ) ? (string) $row['id'] : '';
			$status = isset( $row['status'] ) ? strtoupper( (string) $row['status'] ) : '';
			if ( $id === '' ) {
				continue;
			}
			// فقط تسویهٔ کامل‌شده (واریز شده)
			if ( $status !== 'PAID' ) {
				continue;
			}
			$paid_count++;
			if ( isset( $notified[ $id ] ) ) {
				continue;
			}

			if ( $is_seed ) {
				$notified[ $id ] = current_time( 'mysql' );
				continue;
			}

			$amount = isset( $row['amount'] ) ? (float) $row['amount'] : 0;
			// مبلغ API معمولاً ریال است
			$toman = $amount >= 10 ? (int) round( $amount / 10 ) : (int) $amount;
			$vars  = array(
				'amount'        => number_format( $toman ),
				'amount_rial'   => number_format( (int) $amount ),
				'reference_id'  => (string) ( $row['reference_id'] ?? '' ),
				'reconcile_id'  => $id,
				'status'        => class_exists( 'WAP_Zarinpal_Report' ) ? WAP_Zarinpal_Report::status_label( $status ) : $status,
				'reconciled_at' => class_exists( 'WAP_Zarinpal_Report' )
					? WAP_Zarinpal_Report::format_iso_display( (string) ( $row['reconciled_at'] ?? '' ) )
					: (string) ( $row['reconciled_at'] ?? '' ),
				'payable_at'    => class_exists( 'WAP_Zarinpal_Report' )
					? WAP_Zarinpal_Report::format_iso_display( (string) ( $row['payable_at'] ?? '' ) )
					: (string) ( $row['payable_at'] ?? '' ),
			);
			$msg = WAP_SMS::render_settle_message( $vars );

			if ( empty( $recipients ) ) {
				return new WP_Error( 'wap_sms_recipients', 'شماره گیرنده پیامک تنظیم نشده است.' );
			}

			$ok = false;
			foreach ( $recipients as $phone ) {
				$r = WAP_SMS::send( $phone, $msg );
				if ( ! is_wp_error( $r ) ) {
					$ok = true;
				} else {
					error_log( 'Hesabdar settle SMS to ' . $phone . ': ' . $r->get_error_message() );
				}
			}
			if ( $ok ) {
				$notified[ $id ] = current_time( 'mysql' );
				$sent_count++;
			}
		}

		// فقط ۲۰۰ مورد آخر را نگه دار
		if ( count( $notified ) > 200 ) {
			$notified = array_slice( $notified, -200, null, true );
		}
		update_option( self::NOTIFIED_OPT, $notified, false );

		if ( $is_seed ) {
			return sprintf(
				'اولین بررسی: %d واریز قبلی ثبت شد (بدون پیامک). از این به بعد فقط واریزهای جدید پیامک می‌شوند.',
				$paid_count
			);
		}

		return sprintf( 'بررسی شد: %d تسویه PAID، %d پیامک جدید ارسال شد.', $paid_count, $sent_count );
	}

	/**
	 * واکشی تسویه‌ها از GraphQL زرین‌پال (همان داده‌ای که در پنل دیده می‌شود).
	 *
	 * @param array{filter?:string,created_from_date?:string,created_to_date?:string} $opts
	 * @return array<int,array<string,mixed>>|WP_Error
	 */
	public static function fetch_reconciles( string $token, string $terminal_id, array $opts = array() ) {
		$filter = strtoupper( (string) ( $opts['filter'] ?? 'PAID' ) );
		if ( ! in_array( $filter, array( 'ALL', 'PAID', 'IN_PROGRESS', 'REVERSED' ), true ) ) {
			$filter = 'PAID';
		}
		$from = isset( $opts['created_from_date'] ) ? trim( (string) $opts['created_from_date'] ) : '';
		$to   = isset( $opts['created_to_date'] ) ? trim( (string) $opts['created_to_date'] ) : '';

		$query = 'query getReconciles($terminal_id: ID, $filter: ReconciliationStatusEnum, $created_from_date: DateTime, $created_to_date: DateTime) { resource: Reconciliation(terminal_id: $terminal_id, filter: $filter, created_from_date: $created_from_date, created_to_date: $created_to_date) { id status amount payable_at reference_id reconciled_at } }';
		$vars  = array(
			'terminal_id' => $terminal_id,
			'filter'      => $filter,
		);
		if ( $from !== '' ) {
			$vars['created_from_date'] = $from;
		}
		if ( $to !== '' ) {
			$vars['created_to_date'] = $to;
		}

		$body = wp_json_encode( array(
			'query'     => $query,
			'variables' => $vars,
		) );

		$resp = wp_remote_post(
			self::GRAPHQL_URL,
			array(
				'timeout' => 40,
				'headers' => array(
					'Accept'        => 'application/json',
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $token,
				),
				'body'    => $body,
			)
		);

		if ( is_wp_error( $resp ) ) {
			return new WP_Error( 'wap_zp_http', 'خطا در ارتباط با زرین‌پال: ' . $resp->get_error_message() );
		}
		$code = (int) wp_remote_retrieve_response_code( $resp );
		$raw  = wp_remote_retrieve_body( $resp );
		$data = json_decode( $raw, true );
		if ( $code === 401 || $code === 403 ) {
			return new WP_Error( 'wap_zp_auth', 'توکن زرین‌پال نامعتبر یا منقضی است. Access Token را تمدید کنید.' );
		}
		if ( ! is_array( $data ) ) {
			return new WP_Error( 'wap_zp_bad', 'پاسخ نامعتبر از زرین‌پال.' );
		}
		if ( ! empty( $data['errors'] ) ) {
			// اگر فیلتر تاریخ پشتیبانی نشد، بدون تاریخ دوباره امتحان کن
			$msg = isset( $data['errors'][0]['message'] ) ? (string) $data['errors'][0]['message'] : 'خطای GraphQL';
			if ( ( $from !== '' || $to !== '' ) && stripos( $msg, 'created_' ) !== false ) {
				return self::fetch_reconciles( $token, $terminal_id, array( 'filter' => $filter ) );
			}
			return new WP_Error( 'wap_zp_gql', 'زرین‌پال: ' . $msg );
		}
		$resource = $data['data']['resource'] ?? array();
		if ( ! is_array( $resource ) ) {
			return array();
		}
		return array_values( $resource );
	}
}
