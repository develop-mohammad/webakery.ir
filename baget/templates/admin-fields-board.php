<?php
defined( 'ABSPATH' ) || exit;
/** @var array $fields */
/** @var array $active */
/** @var array $available */
?>
<div class="wccp-board">
	<section class="wccp-col">
		<header>
			<strong>فیلدهای موجود</strong>
			<button type="button" class="button" id="wccp-add-field">+ فیلد جدید</button>
		</header>
		<ul class="wccp-list" id="wccp-available" data-list="available">
			<?php foreach ( $available as $key ) :
				$f = $fields[ $key ] ?? null;
				if ( ! $f ) { continue; }
				include WCCP_PATH . 'templates/admin-field-row.php';
			endforeach; ?>
		</ul>
		<p class="wccp-muted" style="padding:8px 12px">روی + بزنید یا بکشید به ستون فعال.</p>
	</section>

	<div class="wccp-swap" aria-hidden="true">⇄</div>

	<section class="wccp-col">
		<header>
			<strong>فیلدهای فعال — برای مرتب‌سازی بکشید</strong>
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
