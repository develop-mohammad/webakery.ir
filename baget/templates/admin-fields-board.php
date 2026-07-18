<?php
defined( 'ABSPATH' ) || exit;
/** @var array $fields */
/** @var array $active */
/** @var array $available */
?>
<div class="wccp-board">
	<section class="wccp-col">
		<header>
			<div>
				<strong>فیلدهای موجود</strong>
				<small class="wccp-muted">هنوز در این قالب فعال نیستند</small>
			</div>
			<button type="button" class="button button-primary" id="wccp-add-field">+ فیلد / سوال جدید</button>
		</header>
		<ul class="wccp-list" id="wccp-available" data-list="available">
			<?php foreach ( $available as $key ) :
				$f = $fields[ $key ] ?? null;
				if ( ! $f ) { continue; }
				include WCCP_PATH . 'templates/admin-field-row.php';
			endforeach; ?>
		</ul>
		<p class="wccp-muted" style="padding:8px 12px">برای فعال‌سازی: دکمه + یا کشیدن به ستون راست.</p>
	</section>

	<div class="wccp-swap" aria-hidden="true">⇄</div>

	<section class="wccp-col wccp-col-active">
		<header>
			<div>
				<strong>فیلدهای فعال این قالب</strong>
				<small class="wccp-muted">برای تغییر ترتیب بکشید — روی checkout فقط قالب ★ پیش‌فرض می‌آید</small>
			</div>
		</header>
		<ul class="wccp-list" id="wccp-active" data-list="active">
			<?php foreach ( $active as $key ) :
				$f = $fields[ $key ] ?? null;
				if ( ! $f ) { continue; }
				include WCCP_PATH . 'templates/admin-field-row.php';
			endforeach; ?>
		</ul>
		<input type="hidden" name="wccp_active_fields" id="wccp-active-input" value="<?php echo esc_attr( wp_json_encode( array_values( $active ) ) ); ?>" />
	</section>
</div>
