<?php
defined( 'ABSPATH' ) || exit;
/** @var string $tab */
?>
<div class="wrap wccp-wrap">
	<div class="wccp-topbar">
		<div>
			<h1>Baget — لایسنس</h1>
			<p class="wccp-muted">فعال‌سازی لایسنس، دوره آزمایشی و به‌روزرسانی خودکار افزونه.</p>
		</div>
	</div>

	<?php include WCCP_PATH . 'templates/admin-tabs.php'; ?>

	<div class="wccp-license-wrap">
		<?php
		if ( class_exists( 'WB_License' ) && method_exists( 'WB_License', 'render_box' ) ) {
			echo WB_License::render_box( WCCP_PRODUCT ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		} else {
			echo '<p>سیستم لایسنس در دسترس نیست.</p>';
		}
		?>
	</div>
</div>
