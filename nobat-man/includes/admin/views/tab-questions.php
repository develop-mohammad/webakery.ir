<?php
defined( 'ABSPATH' ) || exit;

$categories = NM_Questions::all_categories();
$current    = sanitize_text_field( wp_unslash( $_GET['nm_cat'] ?? '' ) );
if ( ! $current && ! empty( $categories ) ) {
	$current = (string) $categories[0];
}
if ( ! $current ) {
	$current = 'عمومی';
}
$board = NM_Questions::board_for_category( $current );
?>
<div class="nm-qboard-wrap">
	<div class="nm-qboard-topbar">
		<div>
			<h2 class="nm-qboard-title">سوالات رزرو</h2>
			<p class="nm-qboard-muted">فیلدها را بکشید، جابه‌جا کنید، بعد ذخیره کنید. قبل و بعد از ذخیره هم جابه‌جایی کار می‌کند.</p>
		</div>
		<button type="button" class="nm-qboard-btn-save" id="nm-q-save-btn">
			<span class="dashicons dashicons-yes"></span> ذخیره
		</button>
	</div>

	<div class="nm-qboard-shell">
		<div class="nm-qboard-main">
			<nav class="nm-qboard-tabs">
				<span class="is-active">فیلدها</span>
				<span class="nm-qboard-muted">دسته: <?php echo esc_html( $current ); ?></span>
			</nav>

			<div class="nm-qboard-app" id="nm-q-app"
				data-category="<?php echo esc_attr( $current ); ?>">
				<?php include NM_PATH . 'includes/admin/views/questions-board.php'; ?>
			</div>
		</div>

		<aside class="nm-qboard-sidebar">
			<strong>دسته‌بندی‌ها</strong>
			<ul class="nm-qboard-cats" id="nm-q-cats">
				<?php foreach ( $categories as $cat ) : ?>
					<li>
						<a class="nm-qboard-cat<?php echo $current === $cat ? ' is-active' : ''; ?>"
							href="<?php echo esc_url( admin_url( 'admin.php?page=nobat-man&tab=questions&nm_cat=' . rawurlencode( $cat ) ) ); ?>">
							<?php echo esc_html( $cat ); ?>
						</a>
					</li>
				<?php endforeach; ?>
				<?php if ( empty( $categories ) ) : ?>
					<li><span class="nm-qboard-muted">هنوز دسته‌ای نیست</span></li>
				<?php endif; ?>
			</ul>
			<button type="button" class="button" id="nm-q-add-cat">+ دسته جدید</button>
		</aside>
	</div>

	<div id="nm-q-toast" class="nm-qboard-toast" hidden></div>
</div>

<div id="nm-q-modal" class="nm-qboard-modal" hidden>
	<div class="nm-qboard-modal-card">
		<h3 id="nm-q-modal-title">سوال جدید</h3>
		<form id="nm-q-modal-form">
			<input type="hidden" name="id" id="nm-q-id" value="0" />
			<label>سوال<input type="text" id="nm-q-question" class="widefat" required /></label>
			<label>نوع
				<select id="nm-q-type" class="widefat">
					<option value="text">متنی</option>
					<option value="textarea">چندخطی</option>
					<option value="select">انتخابی</option>
				</select>
			</label>
			<label>گزینه‌ها (هر خط یک گزینه)<textarea id="nm-q-options" class="widefat" rows="4"></textarea></label>
			<label><input type="checkbox" id="nm-q-required" value="1" checked /> اجباری</label>
			<div class="nm-qboard-modal-actions">
				<button type="button" class="button" id="nm-q-modal-cancel">انصراف</button>
				<button type="submit" class="button button-primary">ذخیره سوال</button>
			</div>
		</form>
	</div>
</div>
