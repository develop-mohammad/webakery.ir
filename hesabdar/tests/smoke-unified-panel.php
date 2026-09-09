<?php
/**
 * Smoke: پنل یکپارچه — بدون پنل مدیر / فرم افزودن / دسترسی نقش در ادمین
 */
$root = dirname( __DIR__ );
$fail = 0;

function assert_true( $cond, $msg ) {
	global $fail;
	if ( $cond ) {
		echo "OK  $msg\n";
	} else {
		echo "FAIL $msg\n";
		$fail++;
	}
}

$admin = file_get_contents( $root . '/includes/class-wap-admin.php' );
$portal = file_get_contents( $root . '/includes/class-wap-portal.php' );
$boot  = file_get_contents( $root . '/hesabdar.php' );

assert_true( strpos( $admin, '<h2>افزودن حسابدار</h2>' ) === false, 'admin: no add-accountant form' );
assert_true( strpos( $admin, 'ساخت کاربر جدید' ) === false, 'admin: no create-user fields' );
assert_true( strpos( $admin, 'wap_save_accountant' ) === false && strpos( $admin, 'name="wap_mode"' ) === false, 'admin: no add-accountant POST handlers' );
assert_true( strpos( $admin, 'دسترسی بر اساس نقش' ) === false, 'admin: no role-access UI' );
assert_true( strpos( $admin, 'دو آدرس مستقل' ) === false, 'admin: no dual URL table' );
assert_true( strpos( $admin, 'پنل مدیر' ) === false, 'admin: no manager panel label' );
assert_true( strpos( $admin, 'آدرس یکپارچه گزارش فروش' ) !== false, 'admin: unified URL copy' );
assert_true( strpos( $admin, 'لیست حسابداران' ) !== false, 'admin: accountants list kept' );
assert_true( strpos( $admin, 'خروجی Google Sheets' ) !== false, 'admin: Google Sheets section kept' );

assert_true( strpos( $portal, 'PANEL_MANAGER' ) === false, 'portal: no PANEL_MANAGER' );
assert_true( preg_match( "/const PANEL_ACCOUNTANT = 'accountant'/", $portal ) === 1, 'portal: PANEL_ACCOUNTANT only' );
assert_true( strpos( $portal, "home_url( '/accountant-panel/' )" ) !== false, 'portal: single accountant URL' );

assert_true( strpos( $boot, "manager-panel/?\$'" ) !== false || strpos( $boot, "^manager-panel" ) !== false, 'boot: legacy manager rewrite redirect' );
assert_true( strpos( $boot, "wap_panel=accountant" ) !== false, 'boot: manager rewrite → accountant' );
assert_true( defined( 'WAP_VERSION' ) || preg_match( "/define\(\s*'WAP_VERSION',\s*'1\.19\.1'\s*\)/", $boot ), 'version 1.19.1' );

exit( $fail > 0 ? 1 : 0 );
