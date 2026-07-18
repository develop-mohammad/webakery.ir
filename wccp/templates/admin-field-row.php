<?php
defined( 'ABSPATH' ) || exit;
/** @var string $key */
/** @var array $f */
$is_custom = ! empty( $f['custom'] ) || ! empty( $f['user_defined'] );
$required  = ! empty( $f['required'] );
?>
<li class="wccp-item"
	draggable="true"
	data-key="<?php echo esc_attr( $key ); ?>"
	data-custom="<?php echo $is_custom ? '1' : '0'; ?>">
	<div class="wccp-item-actions">
		<button type="button" class="wccp-icon-btn wccp-move-btn" title="افزودن/حذف" aria-label="جابه‌جا">+</button>
		<?php if ( $is_custom ) : ?>
			<button type="button" class="wccp-icon-btn wccp-del-btn" title="حذف فیلد سفارشی">×</button>
		<?php endif; ?>
	</div>
	<div class="wccp-item-meta">
		<?php if ( $is_custom ) : ?>
			<span class="wccp-tag custom">سفارشی</span>
		<?php else : ?>
			<span class="wccp-tag default">پیش‌فرض</span>
		<?php endif; ?>
		<?php if ( $required ) : ?>
			<span class="wccp-tag required">اجباری</span>
		<?php endif; ?>
		<span class="wccp-item-label"><?php echo esc_html( $f['label'] ?? $key ); ?></span>
		<code class="wccp-item-key" dir="ltr"><?php echo esc_html( $key ); ?></code>
	</div>
	<span class="wccp-drag-handle" title="بکشید">⋮⋮</span>
</li>
