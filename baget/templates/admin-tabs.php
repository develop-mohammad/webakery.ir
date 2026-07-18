<?php
defined( 'ABSPATH' ) || exit;
/** @var string $tab */
$tabs = array(
	'fields'   => array(
		'label' => 'فیلدها',
		'url'   => admin_url( 'admin.php?page=wccp' ),
	),
	'products' => array(
		'label' => 'محصولات آنلاین',
		'url'   => admin_url( 'edit.php?post_type=wccp_product' ),
	),
	'license'  => array(
		'label' => 'لایسنس',
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
