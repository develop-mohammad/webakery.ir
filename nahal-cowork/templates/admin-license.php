<?php
defined( 'ABSPATH' ) || exit;
$domain = preg_replace( '/^www\./i', '', (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
$return = admin_url( 'admin.php?page=' . NCK_MENU . '&tab=license' );
$pay    = 'https://webakery.ir/license-server/pay/?' . http_build_query(
	array(
		'plugin' => NCK_PRODUCT,
		'domain' => $domain,
		'return' => $return,
	)
);
?>
<div class="nck-license">
	<p>دوره آزمایشی ۷ روزه است. پس از خرید، کلید لایسنس را وارد کنید تا به‌روزرسانی خودکار فعال شود.</p>
	<p><a class="button button-primary" href="<?php echo esc_url( $pay ); ?>" target="_blank" rel="noopener">خرید لایسنس — ۳۹۹,۰۰۰ تومان</a></p>
	<?php
	if ( class_exists( 'WB_License', false ) && method_exists( 'WB_License', 'render_box' ) ) {
		echo WB_License::render_box( NCK_PRODUCT ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
	?>
</div>
