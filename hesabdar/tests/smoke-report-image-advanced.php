<?php
/**
 * Smoke: خروجی تصویری پیشرفته 1.12.0
 */
$root = dirname( __DIR__ );
$checks = array(
	$root . '/hesabdar.php' => array( "1.12.0", "class-wap-report-image.php", "WAP_Report_Image::init" ),
	$root . '/includes/class-wap-report-image.php' => array( 'ajax_archive', 'run_daily_digest', 'send_telegram_photo', 'date_lock' ),
	$root . '/includes/class-wap-portal.php' => array( 'render_image_export_tools', 'current_view_public', 'data-wap-img-scope', 'آرشیو رسانه' ),
	$root . '/includes/class-wap-admin.php' => array( 'wap-report-image', 'report_image_page' ),
	$root . '/assets/app.js' => array( 'wapCopyCanvas', 'wapArchiveCanvas', 'wapSliceCanvases', 'wapEnsureWatermark', 'clipboard' ),
	$root . '/assets/style.css' => array( 'wap-image-export-tools', 'wap-capture-watermark' ),
);

foreach ( $checks as $file => $needles ) {
	if ( ! is_readable( $file ) ) {
		fwrite( STDERR, "FAIL missing $file\n" );
		exit( 1 );
	}
	$c = file_get_contents( $file );
	foreach ( $needles as $n ) {
		if ( strpos( $c, $n ) === false ) {
			fwrite( STDERR, "FAIL $file missing: $n\n" );
			exit( 1 );
		}
	}
	echo "OK " . basename( $file ) . "\n";
}

foreach ( array(
	$root . '/includes/class-wap-report-image.php',
	$root . '/includes/class-wap-portal.php',
	$root . '/includes/class-wap-admin.php',
	$root . '/hesabdar.php',
) as $php ) {
	exec( 'php -l ' . escapeshellarg( $php ), $out, $code );
	if ( $code !== 0 ) {
		fwrite( STDERR, "FAIL php -l $php\n" );
		exit( 1 );
	}
}
echo "ALL ADVANCED IMAGE EXPORT CHECKS PASSED\n";
