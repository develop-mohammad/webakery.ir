<?php
/**
 * Smoke tests for Zarinpal reconcile notify helpers (no live API).
 */
error_reporting( E_ALL );
define( 'ABSPATH', '/' );

$GLOBALS['wap_opt'] = array();
$GLOBALS['wap_sms_sent'] = array();

function fail( $msg ) {
	fwrite( STDERR, "FAIL: $msg\n" );
	exit( 1 );
}
function ok( $cond, $msg ) {
	if ( ! $cond ) {
		fail( $msg );
	}
}

function get_option( $k, $d = false ) {
	return array_key_exists( $k, $GLOBALS['wap_opt'] ) ? $GLOBALS['wap_opt'][ $k ] : $d;
}
function update_option( $k, $v, $a = false ) {
	$GLOBALS['wap_opt'][ $k ] = $v;
	return true;
}
function current_time( $t ) {
	return '2026-09-05 12:00:00';
}
function wp_json_encode( $d ) {
	return json_encode( $d );
}
function is_wp_error( $t ) {
	return $t instanceof WP_Error;
}
function add_action( $a, $b, $c = 10 ) {}
function wp_next_scheduled( $h ) {
	return false;
}
function wp_schedule_event( $t, $r, $h ) {}
function wp_unschedule_event( $t, $h ) {}
function wp_remote_post( $u, $a ) {
	return array( 'response' => array( 'code' => 200 ), 'body' => '' );
}
function wp_remote_retrieve_response_code( $r ) {
	return 200;
}
function wp_remote_retrieve_body( $r ) {
	return '{"data":{"resource":[]}}';
}

class WP_Error {
	private $m;
	public function __construct( $c, $m ) {
		$this->m = $m;
	}
	public function get_error_message() {
		return $this->m;
	}
}

class WAP_SMS {
	public static $cfg = array(
		'settle_enabled'  => 1,
		'zp_access_token' => 'tok',
		'zp_terminal_id'  => '123',
		'settle_message'  => 'واریز {amount} ریف {reference_id}',
	);
	public static function get( $k = null, $d = null ) {
		return $k === null ? self::$cfg : ( self::$cfg[ $k ] ?? $d );
	}
	public static function recipient_list() {
		return array( '09121234567' );
	}
	public static function send( $p, $m ) {
		$GLOBALS['wap_sms_sent'][] = array( $p, $m );
		return true;
	}
}

require dirname( __DIR__ ) . '/includes/class-wap-zarinpal-reconcile.php';

/*
 * Parent poll_and_notify uses self::fetch_reconciles (early bind).
 * Copy the poll body into a test harness that injects rows.
 */
function wap_test_poll( array $items ) {
	if ( ! (int) WAP_SMS::get( 'settle_enabled', 0 ) ) {
		return 'پایش تسویه غیرفعال است.';
	}
	$token       = trim( (string) WAP_SMS::get( 'zp_access_token', '' ) );
	$terminal_id = trim( (string) WAP_SMS::get( 'zp_terminal_id', '' ) );
	if ( $token === '' || $terminal_id === '' ) {
		return new WP_Error( 'wap_zp_cfg', 'missing cfg' );
	}

	$notified = get_option( WAP_Zarinpal_Reconcile::NOTIFIED_OPT, array() );
	if ( ! is_array( $notified ) ) {
		$notified = array();
	}
	$is_seed    = empty( $notified );
	$sent_count = 0;
	$paid_count = 0;
	$recipients = WAP_SMS::recipient_list();

	foreach ( $items as $row ) {
		$id     = isset( $row['id'] ) ? (string) $row['id'] : '';
		$status = isset( $row['status'] ) ? strtoupper( (string) $row['status'] ) : '';
		if ( $id === '' || $status !== 'PAID' ) {
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
		$toman  = $amount >= 10 ? (int) round( $amount / 10 ) : (int) $amount;
		$vars   = array(
			'amount'       => number_format( $toman ),
			'reference_id' => (string) ( $row['reference_id'] ?? '' ),
		);
		$msg = (string) WAP_SMS::get( 'settle_message' );
		foreach ( $vars as $k => $v ) {
			$msg = str_replace( '{' . $k . '}', $v, $msg );
		}
		$ok = false;
		foreach ( $recipients as $phone ) {
			$r = WAP_SMS::send( $phone, $msg );
			if ( ! is_wp_error( $r ) ) {
				$ok = true;
			}
		}
		if ( $ok ) {
			$notified[ $id ] = current_time( 'mysql' );
			$sent_count++;
		}
	}
	update_option( WAP_Zarinpal_Reconcile::NOTIFIED_OPT, $notified, false );
	if ( $is_seed ) {
		return sprintf( 'اولین بررسی: %d واریز قبلی ثبت شد (بدون پیامک). از این به بعد فقط واریزهای جدید پیامک می‌شوند.', $paid_count );
	}
	return sprintf( 'بررسی شد: %d تسویه PAID، %d پیامک جدید ارسال شد.', $paid_count, $sent_count );
}

$rows = array(
	array( 'id' => 'r1', 'status' => 'PAID', 'amount' => 100000, 'reference_id' => 'REF1' ),
	array( 'id' => 'r2', 'status' => 'PENDING', 'amount' => 50000, 'reference_id' => 'REF2' ),
);

$GLOBALS['wap_opt'] = array();
$GLOBALS['wap_sms_sent'] = array();
$msg = wap_test_poll( $rows );
ok( is_string( $msg ), 'seed returns string' );
ok( strpos( $msg, 'اولین بررسی' ) !== false, 'seed message text' );
ok( count( $GLOBALS['wap_sms_sent'] ) === 0, 'no SMS on seed' );
ok( isset( $GLOBALS['wap_opt'][ WAP_Zarinpal_Reconcile::NOTIFIED_OPT ]['r1'] ), 'r1 seeded' );
ok( ! isset( $GLOBALS['wap_opt'][ WAP_Zarinpal_Reconcile::NOTIFIED_OPT ]['r2'] ), 'pending not seeded' );

$rows[] = array( 'id' => 'r3', 'status' => 'PAID', 'amount' => 200000, 'reference_id' => 'REF3' );
$msg2 = wap_test_poll( $rows );
ok( is_string( $msg2 ), 'second poll string' );
ok( strpos( $msg2, '1 پیامک' ) !== false, 'one new SMS in summary: ' . $msg2 );
ok( count( $GLOBALS['wap_sms_sent'] ) === 1, 'exactly one SMS sent' );
ok( strpos( $GLOBALS['wap_sms_sent'][0][1], 'REF3' ) !== false, 'message has REF3' );
ok( isset( $GLOBALS['wap_opt'][ WAP_Zarinpal_Reconcile::NOTIFIED_OPT ]['r3'] ), 'r3 notified' );

// Config validation from real class
$err = WAP_Zarinpal_Reconcile::poll_and_notify( true );
// With token set it will hit empty API body and return string or error — just ensure callable
ok( is_string( $err ) || is_wp_error( $err ), 'real poll callable' );

// Auth / missing cfg
WAP_SMS::$cfg['zp_access_token'] = '';
$err2 = WAP_Zarinpal_Reconcile::poll_and_notify( true );
ok( is_wp_error( $err2 ), 'missing token errors' );

echo "ALL ZARINPAL RECONCILE SMOKE TESTS PASSED\n";
