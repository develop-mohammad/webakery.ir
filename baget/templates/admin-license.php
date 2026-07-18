<?php
defined( 'ABSPATH' ) || exit;
/** @var string $tab */
$tab = 'license';

// اطمینان از ثبت محصول لایسنس قبل از رندر
if ( class_exists( '\\WCCP\\Plugin' ) ) {
	try {
		\WCCP\Plugin::instance()->boot_license();
	} catch ( Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
	}
}

$pay_url = 'https://webakery.ir/license-server/pay/?' . http_build_query(
	array(
		'plugin' => 'wccp',
		'domain' => preg_replace( '/^www\./i', '', (string) wp_parse_url( home_url(), PHP_URL_HOST ) ),
		'return' => admin_url( 'admin.php?page=wccp&tab=license' ),
	)
);
?>
<div class="wrap wccp-wrap">
	<div class="wccp-topbar">
		<div>
			<h1>Baget — خرید و فعال‌سازی لایسنس</h1>
			<p class="wccp-muted">پرداخت آنلاین، فعال‌سازی با کلید لایسنس، دوره آزمایشی و به‌روزرسانی خودکار.</p>
		</div>
	</div>

	<?php include WCCP_PATH . 'templates/admin-tabs.php'; ?>

	<div class="wccp-license-wrap">
		<?php
		$box = '';
		if ( class_exists( '\\WB_License', false ) && method_exists( '\\WB_License', 'render_box' ) ) {
			$box = (string) \WB_License::render_box( WCCP_PRODUCT );
		}
		if ( $box ) {
			echo $box; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		} else {
			?>
			<div class="wccp-license-fallback">
				<div class="wccp-license-fallback-card">
					<h2>🔑 فعال‌سازی Baget</h2>
					<p>برای استفاده کامل و دریافت آپدیت، لایسنس را فعال کنید.</p>
					<p><strong>قیمت:</strong> ۱۹۹,۰۰۰ تومان — پرداخت یکباره</p>
					<p>
						<a class="button button-primary button-hero" href="<?php echo esc_url( $pay_url ); ?>" target="_blank" rel="noopener">
							💳 پرداخت و فعال‌سازی آنی
						</a>
					</p>
					<hr>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="max-width:480px">
						<?php wp_nonce_field( 'wb_license_save' ); ?>
						<input type="hidden" name="action" value="wb_license_save" />
						<input type="hidden" name="product" value="<?php echo esc_attr( WCCP_PRODUCT ); ?>" />
						<p>
							<label><strong>کلید لایسنس دارید؟</strong></label><br>
							<input type="text" class="regular-text" name="license_key" placeholder="XXXXXX-XXXX-XXXX-XXXX-XXXX" dir="ltr" required />
						</p>
						<p><button type="submit" class="button button-secondary">فعال‌سازی با کلید</button></p>
					</form>
					<p class="wccp-muted">اگر باکس لایسنس خالی است، یک‌بار افزونه را غیرفعال/فعال کنید یا ZIP کامل v1.4.2 را نصب کنید.</p>
				</div>
			</div>
			<?php
		}
		?>
	</div>
</div>
