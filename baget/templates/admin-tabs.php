<?php
defined( 'ABSPATH' ) || exit;
/** @var string $tab */
$tabs = array(
	'fields'      => array(
		'label' => 'قالب‌ها و فیلدها',
		'url'   => admin_url( 'admin.php?page=wccp' ),
	),
	'templates'   => array(
		'label' => 'ساخت قالب',
		'url'   => admin_url( 'admin.php?page=wccp&tab=templates' ),
	),
	'wc-products' => array(
		'label' => 'محصولات فروشگاه',
		'url'   => admin_url( 'admin.php?page=wccp&tab=wc-products' ),
	),
	'products'    => array(
		'label' => 'لینک پرداخت',
		'url'   => admin_url( 'edit.php?post_type=wccp_product' ),
	),
	'payments'    => array(
		'label' => 'پرداخت',
		'url'   => admin_url( 'admin.php?page=wccp&tab=payments' ),
	),
	'license'     => array(
		'label' => 'خرید و لایسنس',
		'url'   => admin_url( 'admin.php?page=wccp&tab=license' ),
	),
);
?>
<nav class="wccp-tabs">
	<?php foreach ( $tabs as $key => $item ) : ?>
		<a class="<?php echo $tab === $key ? 'is-active' : ''; ?>" href="<?php echo esc_url( $item['url'] ); ?>">
			<?php echo esc_html( $item['label'] ); ?>
		</a>
	<?php endforeach; ?>
</nav>
