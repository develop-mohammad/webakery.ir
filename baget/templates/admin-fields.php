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
			<h1>Baget — تنظیمات فیلدها و سوالات</h1>
			<p class="wccp-muted">اینجا فیلدهای checkout و سوالات چندگزینه‌ای را می‌سازید، مرتب می‌کنید و ذخیره می‌کنید.</p>
		</div>
		<div class="wccp-topbar-actions">
			<button type="button" class="button button-secondary" id="wccp-add-radio">+ سوال رادیو</button>
			<button type="button" class="button button-secondary" id="wccp-add-checkboxes">+ سوال چندگزینه‌ای</button>
			<button type="button" class="wccp-btn-save" id="wccp-save-btn">
				<span class="dashicons dashicons-yes"></span> ذخیره تنظیمات
			</button>
		</div>
	</div>

	<?php include WCCP_PATH . 'templates/admin-tabs.php'; ?>

	<div class="wccp-howto">
		<div class="wccp-howto-step"><span>۱</span><div><strong>سوال بسازید</strong><p>دکمه «+ سوال چندگزینه‌ای» یا «+ فیلد جدید»</p></div></div>
		<div class="wccp-howto-step"><span>۲</span><div><strong>گزینه‌ها را اضافه کنید</strong><p>هر گزینه در یک ردیف — مثل فرم‌ساز وردپرس</p></div></div>
		<div class="wccp-howto-step"><span>۳</span><div><strong>به ستون فعال بکشید</strong><p>ترتیب را با drag تنظیم کنید</p></div></div>
		<div class="wccp-howto-step"><span>۴</span><div><strong>ذخیره تنظیمات</strong><p>حتماً دکمه بنفش ذخیره را بزنید</p></div></div>
	</div>

	<div class="wccp-app" data-mode="global" id="wccp-app">
		<?php include WCCP_PATH . 'templates/admin-fields-board.php'; ?>
	</div>

	<div id="wccp-toast" class="wccp-toast" hidden></div>
	<?php include WCCP_PATH . 'templates/admin-field-modal.php'; ?>
</div>
