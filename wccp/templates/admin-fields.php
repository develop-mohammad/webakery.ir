<?php
defined( 'ABSPATH' ) || exit;
/** @var array $fields */
/** @var array $active */
/** @var array $available */
/** @var string $tab */
?>
<div class="wrap wccp-wrap">
	<div class="wccp-topbar">
		<div>
			<h1>Baget — مدیریت فیلدها</h1>
			<p class="wccp-muted">فیلدها را بکشید، جابه‌جا کنید، بعد ذخیره کنید. قبل و بعد از ذخیره هم جابه‌جایی کار می‌کند.</p>
		</div>
		<button type="button" class="wccp-btn-save" id="wccp-save-btn">
			<span class="dashicons dashicons-yes"></span> ذخیره
		</button>
	</div>

	<?php include WCCP_PATH . 'templates/admin-tabs.php'; ?>

	<div class="wccp-app" data-mode="global" id="wccp-app">
		<?php include WCCP_PATH . 'templates/admin-fields-board.php'; ?>
	</div>

	<div id="wccp-toast" class="wccp-toast" hidden></div>
	<?php include WCCP_PATH . 'templates/admin-field-modal.php'; ?>
</div>
