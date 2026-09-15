<?php
/**
 * Smoke: موجودی محصولات فقط از ووکامرس (ساده + متغیر)
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

$stock  = file_get_contents( $root . '/includes/class-wap-stock.php' );
$data   = file_get_contents( $root . '/includes/class-wap-data.php' );
$order  = file_get_contents( $root . '/includes/class-wap-order-service.php' );
$portal = file_get_contents( $root . '/includes/class-wap-portal.php' );
$admin  = file_get_contents( $root . '/includes/class-wci-admin-pages.php' );
$export = file_get_contents( $root . '/includes/class-wap-export.php' );
$js     = file_get_contents( $root . '/assets/wci-order-edit.js' );
$boot   = file_get_contents( $root . '/hesabdar.php' );

assert_true( strpos( $boot, 'class-wap-stock.php' ) !== false, 'boot loads WAP_Stock' );
assert_true( strpos( $boot, "1.24.1" ) !== false, 'version 1.24.1' );

assert_true( strpos( $stock, 'id_from_order_item' ) !== false, 'maps order item to variation/simple id' );
assert_true( strpos( $stock, 'is_self_managing' ) !== false || strpos( $stock, "get_manage_stock" ) !== false, 'avoids parent-managed variation double-count' );
assert_true( strpos( $data, 'WAP_Stock::id_from_order_item' ) !== false, 'sales keyed by WC stockable id' );
assert_true( strpos( $order, "'variation'" ) !== false, 'product search includes variations' );


assert_true( strpos( $stock, 'class WAP_Stock' ) !== false, 'WAP_Stock class exists' );
assert_true( strpos( $stock, 'get_stock_quantity' ) !== false, 'uses WC get_stock_quantity' );
assert_true( strpos( $stock, "is_type( 'variable' )" ) !== false, 'handles variable products' );
assert_true( strpos( $stock, 'get_children' ) !== false, 'sums variation children when parent unmanaged' );
assert_true( strpos( $stock, 'managing_stock' ) !== false, 'respects managing_stock' );

assert_true( strpos( $data, 'WAP_Stock::snapshot_for_id' ) !== false, 'get_product_sales enriches WC stock' );
assert_true( strpos( $order, "'stock'" ) !== false && strpos( $order, 'WAP_Stock::get_quantity' ) !== false, 'product_payload includes WC stock qty' );
assert_true( strpos( $portal, '>موجودی<' ) !== false && strpos( $portal, 'stock_display' ) !== false, 'portal products table shows موجودی' );
assert_true( strpos( $admin, '>موجودی<' ) !== false && strpos( $admin, 'WAP_Stock::snapshot_for_id' ) !== false, 'admin products table shows موجودی' );
assert_true( strpos( $export, 'موجودی' ) !== false, 'CSV export includes موجودی' );
assert_true( strpos( $js, 'موجودی:' ) !== false && strpos( $js, 'stock_display' ) !== false, 'order search shows stock_display' );

// Unit-ish: mock-free logic via reflection of source patterns — no custom stock meta
assert_true( strpos( $stock, 'update_post_meta' ) === false, 'no custom stock write' );
assert_true( strpos( $stock, "get_post_meta" ) === false && strpos( $stock, "'_stock'" ) === false, 'no direct _stock meta reads' );

exit( $fail > 0 ? 1 : 0 );
