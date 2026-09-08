<?php
/**
 * Smoke: خروجی تصویری گزارش‌ها (JPG/PNG wiring).
 */
$root = dirname( __DIR__ );
$portal = file_get_contents( $root . '/includes/class-wap-portal.php' );
$js     = file_get_contents( $root . '/assets/app.js' );
$css    = file_get_contents( $root . '/assets/style.css' );
$main   = file_get_contents( $root . '/hesabdar.php' );

$fail = function ( $msg ) {
	fwrite( STDERR, "FAIL: $msg\n" );
	exit( 1 );
};
$ok = function ( $msg ) {
	echo "OK: $msg\n";
};

if ( ! preg_match( "/define\\(\\s*'WAP_VERSION'\\s*,\\s*'1\\.11\\.1'\\s*\\)/", $main ) ) {
	$fail( 'WAP_VERSION must be 1.11.1' );
}
$ok( 'version 1.11.1' );

if ( strpos( $js, 'html2canvas' ) === false ) {
	$fail( 'app.js missing html2canvas loader' );
}
if ( strpos( $js, 'data-wap-export-image' ) === false ) {
	$fail( 'app.js missing export-image binding' );
}
if ( strpos( $js, 'data-wap-no-capture' ) === false ) {
	$fail( 'app.js missing no-capture hide logic' );
}
$ok( 'app.js image export' );

if ( strpos( $css, '.wap-btn-jpg' ) === false ) {
	$fail( 'style.css missing .wap-btn-jpg' );
}
$ok( 'css jpg button' );

$labels = array( 'sales', 'orders', 'products', 'shaparak', 'analytics' );
foreach ( $labels as $label ) {
	if ( strpos( $portal, "render_image_export_buttons( '$label' )" ) === false
		&& strpos( $portal, "render_export_bar( \$products_csv_url, 'products' )" ) === false
		&& $label === 'products' ) {
		// products via render_export_bar
	}
	if ( $label === 'products' ) {
		if ( substr_count( $portal, "render_export_bar( \$products_csv_url, 'products' )" ) < 1 ) {
			$fail( 'products missing image export bar' );
		}
		$ok( 'products export bar' );
		continue;
	}
	if ( strpos( $portal, "render_image_export_buttons( '$label' )" ) === false ) {
		$fail( "missing image export for $label" );
	}
	$ok( "image export: $label" );
}

$captures = substr_count( $portal, 'id="wap_capture"' );
if ( $captures < 5 ) {
	$fail( "expected >=5 #wap_capture wrappers, got $captures" );
}
$ok( "#wap_capture count=$captures" );

$closes = substr_count( $portal, '<!-- #wap_capture -->' );
if ( $closes !== $captures ) {
	$fail( "capture open/close mismatch: open=$captures close=$closes" );
}
$ok( 'capture wrappers balanced' );

echo "ALL SMOKE CHECKS PASSED\n";
exit( 0 );
