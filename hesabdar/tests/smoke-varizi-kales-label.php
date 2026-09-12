<?php
/** Smoke: برچسب بخش شاپرک → واریزی خالص */
$root = dirname( __DIR__ );
$portal = file_get_contents( $root . '/includes/class-wap-portal.php' );
$boot   = file_get_contents( $root . '/hesabdar.php' );
$admin  = file_get_contents( $root . '/includes/class-wap-admin.php' );
$wci    = file_get_contents( $root . '/includes/class-wci-admin-pages.php' );

assert( strpos( $portal, "'shaparak'  => 'واریزی خالص'" ) !== false || strpos( $portal, "shaparak'  => 'واریزی خالص'" ) !== false );
assert( strpos( $portal, 'شاپرک / کارمزد' ) === false );
assert( strpos( $boot, "'واریزی خالص'" ) !== false || strpos( $boot, 'واریزی خالص' ) !== false );
assert( strpos( $boot, 'شاپرک و کارمزد' ) === false );
assert( strpos( $admin, "'shaparak' => 'واریزی خالص'" ) !== false );
assert( strpos( $wci, '<h1>واریزی خالص</h1>' ) !== false );
assert( strpos( $boot, '1.23.0' ) !== false );
echo "ALL VARIZI-KHALES LABEL SMOKE PASSED\n";
