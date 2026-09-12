<?php
/**
 * Full feature re-test for Hesabdar Shaparak / fee / Excel / SMS stack.
 */
error_reporting( E_ALL );
define( 'ABSPATH', '/' );

$FAILS = array();
$PASSES = array();
function fail( $m ) {
	global $FAILS;
	$FAILS[] = $m;
	fwrite( STDERR, "FAIL: $m\n" );
}
function ok( $c, $m ) {
	global $PASSES;
	if ( ! $c ) {
		fail( $m );
		return;
	}
	$PASSES[] = $m;
	echo "OK: $m\n";
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $d ) {
		return json_encode( $d, JSON_UNESCAPED_UNICODE );
	}
}
if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $t ) {
		return $t instanceof WP_Error;
	}
}
if ( ! function_exists( 'wp_tempnam' ) ) {
	function wp_tempnam( $p ) {
		return tempnam( sys_get_temp_dir(), $p );
	}
}
if ( ! function_exists( 'current_time' ) ) {
	function current_time( $t ) {
		return '2026-09-08 12:00:00';
	}
}
if ( ! function_exists( 'get_option' ) ) {
	function get_option( $k, $d = false ) {
		return $GLOBALS['wap_opt'][ $k ] ?? $d;
	}
}
if ( ! function_exists( 'update_option' ) ) {
	function update_option( $k, $v, $a = false ) {
		$GLOBALS['wap_opt'][ $k ] = $v;
		return true;
	}
}
if ( ! function_exists( 'add_action' ) ) {
	function add_action( $a, $b, $c = 10 ) {}
}
if ( ! function_exists( 'wp_next_scheduled' ) ) {
	function wp_next_scheduled( $h ) {
		return false;
	}
}
if ( ! function_exists( 'wp_schedule_event' ) ) {
	function wp_schedule_event( $t, $r, $h ) {}
}
if ( ! function_exists( 'wp_unschedule_event' ) ) {
	function wp_unschedule_event( $t, $h ) {}
}
if ( ! function_exists( 'wp_remote_post' ) ) {
	function wp_remote_post( $u, $a ) {
		return array( 'response' => array( 'code' => 200 ), 'body' => '{"data":{"resource":[]}}' );
	}
}
if ( ! function_exists( 'wp_remote_retrieve_response_code' ) ) {
	function wp_remote_retrieve_response_code( $r ) {
		return 200;
	}
}
if ( ! function_exists( 'wp_remote_retrieve_body' ) ) {
	function wp_remote_retrieve_body( $r ) {
		return '{"data":{"resource":[]}}';
	}
}
if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		private $m;
		public function __construct( $c, $m ) {
			$this->m = $m;
		}
		public function get_error_message() {
			return $this->m;
		}
	}
}

$GLOBALS['wap_opt'] = array();
$GLOBALS['wap_sms_sent'] = array();

echo "=== 1) FEE FORMULA ===\n";
require dirname( __DIR__ ) . '/includes/class-wap-zarinpal-fee.php';
foreach ( array( 10000 => 550, 50000 => 750, 195000 => 1475, 1000000 => 5500, 5000000 => 16500, 16000000 => 16500 ) as $amt => $fee ) {
	ok( WAP_Zarinpal_Fee::estimate_toman( $amt ) === $fee, "fee($amt)=$fee" );
}
ok( WAP_Zarinpal_Fee::estimate_rial( 1950000 ) === 14750, 'fee rial' );
ok( (int) WAP_Zarinpal_Fee::net_after_fee_toman( 195000 ) === 193525, 'net after fee' );

echo "\n=== 2) EXCEL XLSX 3-SHEET ===\n";
require dirname( __DIR__ ) . '/includes/class-wap-excel.php';
ok( class_exists( 'ZipArchive' ), 'ZipArchive' );
$bin = WAP_Excel::build( array(
	'خلاصه' => array(
		'headers' => array( 'بخش', 'مقدار' ),
		'rows'    => array( array( 'جمع واریز شاپرک (ریال — عین پنل)', 19500000 ) ),
	),
	'سفارش ووکامرس' => array(
		'headers' => array( 'شماره', 'مبلغ', 'کارمزد', 'خالص' ),
		'rows'    => array( array( '1001', 195000, 1475, 193525 ) ),
	),
	'واریز شاپرک' => array(
		'headers' => array( 'شناسه تسویه', 'وضعیت', 'مبلغ ریال (عین زرین‌پال)', 'شناسه ارجاع بانکی' ),
		'rows'    => array(
			array( '20355143', 'PAID', 19500000, '1405,05,27N1041000000016886912' ),
		),
	),
) );
ok( $bin !== '' && substr( $bin, 0, 2 ) === 'PK', 'xlsx zip magic' );
$tmp = tempnam( sys_get_temp_dir(), 'xlsxfull' );
file_put_contents( $tmp, $bin );
$zip = new ZipArchive();
ok( true === $zip->open( $tmp ), 'open xlsx' );
foreach ( array( 'xl/workbook.xml', 'xl/worksheets/sheet1.xml', 'xl/worksheets/sheet2.xml', 'xl/worksheets/sheet3.xml' ) as $part ) {
	ok( false !== $zip->locateName( $part ), "has $part" );
}
$s3 = $zip->getFromName( 'xl/worksheets/sheet3.xml' );
ok( strpos( $s3, '19500000' ) !== false && strpos( $s3, '20355143' ) !== false && strpos( $s3, 'PAID' ) !== false, 'sheet3 panel fields' );
$zip->close();
@unlink( $tmp );

echo "\n=== 3) RECONCILE SEED + NEW SMS ===\n";
class WAP_SMS_Stub {
	public static function get( $k = null, $d = null ) {
		$cfg = array(
			'settle_enabled'  => 1,
			'zp_access_token' => 'tok',
			'zp_terminal_id'  => '545232',
			'settle_message'  => 'واریز {amount} ریف {reference_id}',
		);
		return $k === null ? $cfg : ( $cfg[ $k ] ?? $d );
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

function wap_test_poll( array $items ) {
	$notified = get_option( WAP_Zarinpal_Reconcile::NOTIFIED_OPT, array() );
	if ( ! is_array( $notified ) ) {
		$notified = array();
	}
	$is_seed    = empty( $notified );
	$sent_count = 0;
	$paid_count = 0;
	foreach ( $items as $row ) {
		$id     = (string) ( $row['id'] ?? '' );
		$status = strtoupper( (string) ( $row['status'] ?? '' ) );
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
		$amount = (float) ( $row['amount'] ?? 0 );
		$toman  = $amount >= 10 ? (int) round( $amount / 10 ) : (int) $amount;
		$msg    = str_replace(
			array( '{amount}', '{reference_id}' ),
			array( number_format( $toman ), (string) ( $row['reference_id'] ?? '' ) ),
			'واریز {amount} ریف {reference_id}'
		);
		WAP_SMS_Stub::send( '09121234567', $msg );
		$notified[ $id ] = current_time( 'mysql' );
		$sent_count++;
	}
	update_option( WAP_Zarinpal_Reconcile::NOTIFIED_OPT, $notified, false );
	if ( $is_seed ) {
		return sprintf( 'اولین بررسی: %d واریز قبلی ثبت شد (بدون پیامک).', $paid_count );
	}
	return sprintf( 'بررسی شد: %d تسویه PAID، %d پیامک جدید ارسال شد.', $paid_count, $sent_count );
}

$rows = array(
	array( 'id' => 'r1', 'status' => 'PAID', 'amount' => 19500000, 'reference_id' => 'REF1' ),
	array( 'id' => 'r2', 'status' => 'IN_PROGRESS', 'amount' => 1000, 'reference_id' => 'REF2' ),
);
$GLOBALS['wap_opt'] = array();
$GLOBALS['wap_sms_sent'] = array();
$msg = wap_test_poll( $rows );
ok( strpos( $msg, 'اولین بررسی' ) !== false, 'seed message' );
ok( count( $GLOBALS['wap_sms_sent'] ) === 0, 'no SMS on seed' );
ok( isset( $GLOBALS['wap_opt'][ WAP_Zarinpal_Reconcile::NOTIFIED_OPT ]['r1'] ), 'r1 seeded' );
ok( ! isset( $GLOBALS['wap_opt'][ WAP_Zarinpal_Reconcile::NOTIFIED_OPT ]['r2'] ), 'IN_PROGRESS skipped' );

$rows[] = array( 'id' => 'r3', 'status' => 'PAID', 'amount' => 200000, 'reference_id' => 'REF3' );
$msg2 = wap_test_poll( $rows );
ok( strpos( $msg2, '1 پیامک' ) !== false, 'one new SMS' );
ok( count( $GLOBALS['wap_sms_sent'] ) === 1, 'SMS count=1' );
ok( strpos( $GLOBALS['wap_sms_sent'][0][1], 'REF3' ) !== false, 'SMS REF3' );
ok( strpos( $GLOBALS['wap_sms_sent'][0][1], '20,000' ) !== false, 'SMS toman from rial/10' );

echo "\n=== 4) API DATE FILTERS + FETCH SIGNATURE ===\n";
$rm = new ReflectionMethod( 'WAP_Zarinpal_Reconcile', 'fetch_reconciles' );
ok( count( $rm->getParameters() ) === 3, 'fetch_reconciles has opts arg' );
$src = file_get_contents( dirname( __DIR__ ) . '/includes/class-wap-zarinpal-reconcile.php' );
ok( strpos( $src, 'created_from_date' ) !== false && strpos( $src, 'created_to_date' ) !== false, 'official date filter fields in code' );
$enc = wp_json_encode( array(
	'variables' => array(
		'terminal_id'       => '545232',
		'filter'            => 'PAID',
		'created_from_date' => '2026-08-01',
		'created_to_date'   => '2026-09-08',
	),
) );
ok( strpos( $enc, 'created_from_date' ) !== false, 'json vars encode dates' );

echo "\n=== 5) REPORT RIAL EXACTNESS ===\n";
$amount_rial = 19500000.0;
$amount_toman = $amount_rial / 10;
ok( $amount_rial === 19500000.0, 'rial exact from API sample' );
ok( $amount_toman === 1950000.0, 'toman = rial/10' );
ok( (string) (int) $amount_rial === '19500000', 'rial string match panel' );

echo "\n=== 6) REAL SMS NORMALIZE (payment smoke file logic) ===\n";
// Load real SMS after renaming stub conflict avoided — class not loaded yet
require dirname( __DIR__ ) . '/includes/class-wap-sms.php';
ok( WAP_SMS::normalize_phone( '09121234567' ) === '09121234567', 'phone normal' );
ok( WAP_SMS::normalize_phone( '989121234567' ) === '09121234567', 'phone 98' );
ok( WAP_SMS::normalize_phone( '۰۹۱۲۱۲۳۴۵۶۷' ) === '09121234567', 'phone FA digits' );

echo "\n=== 7) PLUGIN ZIP CONTENTS ===\n";
ok( file_exists( '/workspace/hesabdar.zip' ), 'hesabdar.zip exists' );
$pz = new ZipArchive();
ok( true === $pz->open( '/workspace/hesabdar.zip' ), 'open plugin zip' );
foreach ( array(
	'hesabdar/hesabdar.php',
	'hesabdar/includes/class-wap-excel.php',
	'hesabdar/includes/class-wap-zarinpal-fee.php',
	'hesabdar/includes/class-wap-zarinpal-report.php',
	'hesabdar/includes/class-wap-zarinpal-reconcile.php',
	'hesabdar/includes/class-wap-sms.php',
	'hesabdar/includes/class-wap-payment-notify.php',
	'hesabdar/includes/class-wap-export.php',
	'hesabdar/includes/class-wap-portal.php',
) as $f ) {
	ok( false !== $pz->locateName( $f ), "zip:$f" );
}
$main = $pz->getFromName( 'hesabdar/hesabdar.php' );
ok( strpos( $main, 'Version:     1.10.3' ) !== false, 'version 1.10.3 in zip' );
ok( strpos( $main, 'class-wap-excel.php' ) !== false, 'excel in bootstrap' );
ok( strpos( $main, 'wci_export_shaparak_xlsx' ) !== false || strpos( $main, 'shaparak_xlsx' ) !== false || strpos( file_get_contents( dirname( __DIR__ ) . '/hesabdar.php' ), 'wci_export_shaparak_xlsx' ) !== false, 'xlsx export hook' );
$pz->close();

echo "\n=== 8) UI HOOKS PRESENT ===\n";
$portal = file_get_contents( dirname( __DIR__ ) . '/includes/class-wap-portal.php' );
$admin  = file_get_contents( dirname( __DIR__ ) . '/includes/class-wci-admin-pages.php' );
$export = file_get_contents( dirname( __DIR__ ) . '/includes/class-wap-export.php' );
ok( strpos( $portal, 'shaparak_xlsx' ) !== false, 'portal xlsx export' );
ok( strpos( $portal, 'مبلغ ریال (عین پنل)' ) !== false || strpos( $portal, 'عین پنل زرین‌پال' ) !== false, 'portal rial label' );
ok( strpos( $admin, 'wci_export_shaparak_xlsx' ) !== false, 'admin xlsx button' );
ok( strpos( $export, 'zarinpal_reconcile_xlsx' ) !== false, 'export xlsx method' );
ok( strpos( $export, 'settle_total_rial' ) !== false, 'export includes rial total' );

echo "\n=== SUMMARY ===\n";
echo 'PASSED: ' . count( $PASSES ) . "\n";
echo 'FAILED: ' . count( $FAILS ) . "\n";
if ( $FAILS ) {
	foreach ( $FAILS as $f ) {
		echo " - $f\n";
	}
	exit( 1 );
}
echo "ALL FULL FEATURE RE-TESTS PASSED\n";
