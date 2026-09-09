<?php
defined( 'ABSPATH' ) || exit;

$s      = WBCN_Settings::get();
$chan   = ! empty( $s['signature'] ) ? WBCN_Settings::channel_username( $s ) : '';
$items  = WBCN_Content::recent_items( 40 );
$post_id = isset( $_GET['post_id'] ) ? (int) $_GET['post_id'] : 0; // phpcs:ignore
if ( ! $post_id && $items ) {
	$post_id = (int) $items[0]['id'];
}
$item   = $post_id ? WBCN_Content::item_from_post( $post_id ) : WBCN_Ideas::sample_item();
$format = isset( $_GET['format'] ) ? sanitize_key( wp_unslash( $_GET['format'] ) ) : 'tip'; // phpcs:ignore
$formats = WBCN_Ideas::formats();
if ( ! isset( $formats[ $format ] ) ) {
	$format = 'tip';
}
$built = $item ? WBCN_Ideas::build( $item, $format, $chan ) : null;
?>

<div class="wbcn-grid">
	<div>
		<div class="wbcn-card">
			<h2>مطلب منبع</h2>
			<p class="wbcn-hint">یک نوشته یا محصول را انتخاب کنید؛ افزونه از خود متن، پست کانال می‌سازد — کپی‌پیست RSS نیست.</p>
			<?php if ( $items ) : ?>
				<label class="wbcn-label" for="wbcn-post">انتخاب مطلب</label>
				<select id="wbcn-post" class="wbcn-select">
					<?php foreach ( $items as $row ) : ?>
						<option value="<?php echo (int) $row['id']; ?>" <?php selected( $post_id, (int) $row['id'] ); ?>>
							<?php echo esc_html( $row['title'] ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			<?php else : ?>
				<p>هنوز مطلب منتشرشده‌ای نیست. نمونهٔ وب‌آکری نمایش داده می‌شود.</p>
			<?php endif; ?>

			<div class="wbcn-formats" id="wbcn-formats">
				<?php foreach ( $formats as $key => $meta ) : ?>
					<button type="button" class="wbcn-chip<?php echo $key === $format ? ' is-on' : ''; ?>" data-format="<?php echo esc_attr( $key ); ?>">
						<?php echo esc_html( $meta['label'] ); ?>
					</button>
				<?php endforeach; ?>
			</div>
			<p class="wbcn-hint" id="wbcn-format-hint"><?php echo esc_html( $formats[ $format ]['hint'] . ' — ' . $formats[ $format ]['growth'] ); ?></p>
		</div>
	</div>

	<div>
		<div class="wbcn-card wbcn-preview-card">
			<h2>پیش‌نمایش پست کانال</h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'wbcn_send_idea' ); ?>
				<input type="hidden" name="action" value="wbcn_send_idea">
				<input type="hidden" name="post_id" id="wbcn-post-id" value="<?php echo (int) $post_id; ?>">
				<input type="hidden" name="format" id="wbcn-format" value="<?php echo esc_attr( $format ); ?>">
				<textarea name="text" id="wbcn-text" class="wbcn-text" rows="16" dir="rtl"><?php echo esc_textarea( $built ? $built['text'] : '' ); ?></textarea>
				<p class="wbcn-hint">HTML تلگرام: <code>&lt;b&gt;</code> <code>&lt;i&gt;</code> <code>&lt;a href&gt;</code> — قبل از ارسال ویرایش کنید.</p>
				<p>
					<button type="submit" class="button button-primary" <?php disabled( ! WBCN_Plugin::licensed() ); ?>>ارسال به کانال</button>
					<button type="button" class="button" id="wbcn-copy">کپی متن</button>
				</p>
			</form>
		</div>
	</div>
</div>
