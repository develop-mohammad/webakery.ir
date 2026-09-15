<?php
defined( 'ABSPATH' ) || exit;
$nck_sign_title = isset( $nck_sign_title ) ? $nck_sign_title : 'امضا';
$nck_sign_party = isset( $nck_sign_party ) ? $nck_sign_party : 'طرف قرارداد';
?>
<div class="nck-sign-wrap" data-nck-sign-wrap>
	<div class="nck-sign-head">
		<span><?php echo esc_html( $nck_sign_title ); ?></span>
		<button type="button" class="nck-link" data-nck-clear>پاک کردن</button>
	</div>
	<div class="nck-sign-modes" role="tablist">
		<button type="button" class="nck-sign-mode is-on" data-nck-sign-mode="upload" role="tab" aria-selected="true">آپلود عکس امضا</button>
		<button type="button" class="nck-sign-mode" data-nck-sign-mode="draw" role="tab" aria-selected="false">کشیدن با دست</button>
	</div>
	<div data-nck-sign-panel="upload">
		<label class="nck-sign-drop">
			<input type="file" accept="image/png,image/jpeg,image/webp,image/gif" data-nck-sign-file hidden />
			<strong>عکس امضا را انتخاب کنید</strong>
			<span>PNG یا JPG — پس‌زمینه روشن حذف می‌شود و امضا روی خط قرارداد می‌نشیند.</span>
		</label>
	</div>
	<div data-nck-sign-panel="draw" hidden>
		<canvas class="nck-sign" width="720" height="180" data-nck-pad></canvas>
		<p class="nck-form-help">با ماوس یا انگشت داخل کادر امضا کنید.</p>
	</div>
	<div class="nck-sign-plate" data-nck-sign-plate>
		<p class="nck-sign-plate-kicker">پیش‌نمایش روی قرارداد</p>
		<div class="nck-sign-plate-stage">
			<img class="nck-sign-plate-ink" data-nck-sign-ink alt="" hidden />
			<span class="nck-sign-plate-empty" data-nck-sign-empty>امضا اینجا، روی خط قرارداد قرار می‌گیرد.</span>
			<span class="nck-sign-plate-line" aria-hidden="true"></span>
		</div>
		<div class="nck-sign-plate-meta">
			<span><?php echo esc_html( $nck_sign_party ); ?></span>
			<strong data-nck-live="name">…</strong>
		</div>
	</div>
</div>
