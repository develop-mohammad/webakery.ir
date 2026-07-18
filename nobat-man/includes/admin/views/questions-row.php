<?php
defined( 'ABSPATH' ) || exit;
/** @var string $key */
/** @var array $f */
$type_labels = array(
	'text'     => 'متنی',
	'textarea' => 'چندخطی',
	'select'   => 'انتخابی',
);
$type_label = $type_labels[ $f['type'] ?? 'text' ] ?? ( $f['type'] ?? 'text' );
?>
<li class="nm-qboard-item"
	draggable="true"
	data-key="<?php echo esc_attr( $key ); ?>">
	<div class="nm-qboard-item-actions">
		<button type="button" class="nm-qboard-icon-btn nm-q-move-btn" title="افزودن/حذف" aria-label="جابه‌جا">+</button>
		<button type="button" class="nm-qboard-icon-btn nm-q-edit-btn" title="ویرایش">✎</button>
		<button type="button" class="nm-qboard-icon-btn nm-q-del-btn" title="حذف">×</button>
	</div>
	<div class="nm-qboard-item-meta">
		<span class="nm-qboard-tag custom">سفارشی</span>
		<?php if ( ! empty( $f['required'] ) ) : ?>
			<span class="nm-qboard-tag required">اجباری</span>
		<?php endif; ?>
		<span class="nm-qboard-tag default"><?php echo esc_html( $type_label ); ?></span>
		<span class="nm-qboard-item-label"><?php echo esc_html( $f['label'] ?? $key ); ?></span>
		<code class="nm-qboard-item-key" dir="ltr">#<?php echo esc_html( $key ); ?></code>
	</div>
	<span class="nm-qboard-drag-handle" title="بکشید">⋮⋮</span>
</li>
