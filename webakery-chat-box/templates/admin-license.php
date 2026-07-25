<?php
defined( 'ABSPATH' ) || exit;

if ( class_exists( 'WBCB_Plugin' ) ) {
	try {
		WBCB_Plugin::instance()->boot_license();
	} catch ( Throwable $e ) { // phpcs:ignore
	}
}

$domain = preg_replace( '/^www\./i', '', (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
$return = admin_url( 'admin.php?page=webakery-chat-box-license' );
$base   = array(
	'plugin' => WBCB_PRODUCT,
	'domain' => $domain,
	'return' => $return,
);
$pay_1m   = 'https://webakery.ir/license-server/pay/?' . http_build_query( array_merge( $base, array( 'plan' => '1m' ) ) );
$pay_3m   = 'https://webakery.ir/license-server/pay/?' . http_build_query( array_merge( $base, array( 'plan' => '3m' ) ) );
$pay_life = 'https://webakery.ir/license-server/pay/?' . http_build_query( array_merge( $base, array( 'plan' => 'life' ) ) );
?>
<div class="wrap wbcb-wrap" dir="rtl">
	<div class="wbcb-top">
		<div>
			<h1>چت باکس — خرید و فعال‌سازی لایسنس</h1>
			<p class="description">ماهانه، ۳ ماهه یا دائمی — پرداخت آنلاین، فعال‌سازی خودکار، دوره آزمایشی ۳ روزه و به‌روزرسانی.</p>
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
				<p>برای استفاده کامل و دریافت آپدیت، یکی از پلن‌های زیر را انتخاب کنید.</p>
				<p style="display:flex;gap:10px;flex-wrap:wrap">
					<a class="button button-secondary button-hero" href="<?php echo esc_url( $pay_1m ); ?>" target="_blank" rel="noopener">
						ماهانه — ۱۵۰,۰۰۰ تومان
					</a>
					<a class="button button-primary button-hero" href="<?php echo esc_url( $pay_3m ); ?>" target="_blank" rel="noopener">
						۳ ماهه — ۳۵۰,۰۰۰ تومان (پیشنهادی)
					</a>
					<a class="button button-secondary button-hero" href="<?php echo esc_url( $pay_life ); ?>" target="_blank" rel="noopener">
						دائمی — ۷۹۹,۰۰۰ تومان
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
