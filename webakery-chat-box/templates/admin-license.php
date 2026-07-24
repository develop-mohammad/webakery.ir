<?php
defined( 'ABSPATH' ) || exit;

if ( class_exists( 'WBCB_Plugin' ) ) {
	try {
		WBCB_Plugin::instance()->boot_license();
	} catch ( Throwable $e ) { // phpcs:ignore
	}
}

$pay_url = 'https://webakery.ir/license-server/pay/?' . http_build_query(
	array(
		'plugin' => WBCB_PRODUCT,
		'domain' => preg_replace( '/^www\./i', '', (string) wp_parse_url( home_url(), PHP_URL_HOST ) ),
		'return' => admin_url( 'admin.php?page=webakery-chat-box-license' ),
	)
);
?>
<div class="wrap wbcb-wrap" dir="rtl">
	<div class="wbcb-top">
		<div>
			<h1>چت باکس — خرید و فعال‌سازی لایسنس</h1>
			<p class="description">پرداخت آنلاین، فعال‌سازی با کلید، دوره آزمایشی ۳ روزه و به‌روزرسانی خودکار.</p>
		</div>
		<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=webakery-chat-box' ) ); ?>">صندوق پیام</a>
	</div>

	<div class="wbcb-license-wrap">
		<?php
		$box = '';
		if ( class_exists( 'WB_License', false ) && method_exists( 'WB_License', 'render_box' ) ) {
			$box = (string) WB_License::render_box( WBCB_PRODUCT );
		}
		if ( $box ) {
			echo $box; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		} else {
			?>
			<div class="wbcb-help-box" style="max-width:640px">
				<h2>🔑 فعال‌سازی چت باکس</h2>
				<p>برای استفاده کامل و دریافت آپدیت، لایسنس را فعال کنید.</p>
				<p><strong>قیمت:</strong> ۵۰۵,۰۰۰ تومان — پرداخت یکباره</p>
				<p>
					<a class="button button-primary button-hero" href="<?php echo esc_url( $pay_url ); ?>" target="_blank" rel="noopener">
						💳 پرداخت و فعال‌سازی آنی
					</a>
				</p>
				<hr>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="max-width:480px">
					<?php wp_nonce_field( 'wb_license_save' ); ?>
					<input type="hidden" name="action" value="wb_license_save" />
					<input type="hidden" name="product" value="<?php echo esc_attr( WBCB_PRODUCT ); ?>" />
					<p>
						<label><strong>کلید لایسنس دارید؟</strong></label><br>
						<input type="text" class="regular-text" name="license_key" placeholder="XXXXXX-XXXX-XXXX-XXXX-XXXX" dir="ltr" required />
					</p>
					<p><button type="submit" class="button button-secondary">فعال‌سازی با کلید</button></p>
				</form>
			</div>
			<?php
		}
		?>
	</div>
</div>
