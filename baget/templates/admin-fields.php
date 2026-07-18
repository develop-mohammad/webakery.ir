<?php
defined( 'ABSPATH' ) || exit;
/** @var array $fields */
/** @var array $active */
/** @var array $available */
/** @var string $tab */
/** @var string $current_tpl */
/** @var string $default_tpl */
/** @var array $templates */

$templates    = \WCCP\Templates::all();
$default_tpl  = \WCCP\Templates::default_key();
$current_tpl  = isset( $_GET['tpl'] ) ? sanitize_key( wp_unslash( $_GET['tpl'] ) ) : $default_tpl; // phpcs:ignore
if ( ! isset( $templates[ $current_tpl ] ) ) {
	$current_tpl = $default_tpl;
}
$active    = \WCCP\Templates::fields_for( $current_tpl );
$fields    = \WCCP\CustomFields::merged_with_defaults();
$available = array_values( array_diff( array_keys( $fields ), $active ) );
$tpl_label = $templates[ $current_tpl ]['label'] ?? $current_tpl;
?>
<div class="wrap wccp-wrap">
	<div class="wccp-topbar">
		<div>
			<h1>Baget — قالب‌ها و فیلدهای سفارشی</h1>
			<p class="wccp-muted">اول قالب را از لیست انتخاب کنید، با ★ پیش‌فرض checkout را مشخص کنید، بعد برای همان قالب فیلد/سوال سفارشی اضافه کنید.</p>
		</div>
		<div class="wccp-topbar-actions">
			<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=wccp&tab=templates' ) ); ?>">+ افزودن قالب</a>
			<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=wccp&tab=wc-products' ) ); ?>">محصولات فروشگاه</a>
			<button type="button" class="button button-secondary" id="wccp-add-radio">+ سوال رادیو</button>
			<button type="button" class="button button-secondary" id="wccp-add-checkboxes">+ سوال چندگزینه‌ای</button>
			<button type="button" class="wccp-btn-save" id="wccp-save-btn">
				<span class="dashicons dashicons-yes"></span> ذخیره فیلدهای این قالب
			</button>
		</div>
	</div>

	<?php include WCCP_PATH . 'templates/admin-tabs.php'; ?>

	<section class="wccp-tpl-picker" id="wccp-tpl-picker">
		<header class="wccp-tpl-picker-head">
			<div>
				<strong>۱) لیست قالب‌ها</strong>
				<span class="wccp-muted">☆ / ★ = پیش‌فرض صفحه پرداخت · کلیک روی کارت = انتخاب برای افزودن فیلد</span>
			</div>
			<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=wccp&tab=templates' ) ); ?>">+ افزودن قالب جدید</a>
		</header>
		<div class="wccp-tpl-picker-list">
			<?php foreach ( $templates as $key => $tpl ) :
				$is_default  = ( $key === $default_tpl );
				$is_current  = ( $key === $current_tpl );
				$field_count = count( \WCCP\Templates::sanitize_fields( $tpl['fields'] ?? array() ) );
				$url         = admin_url( 'admin.php?page=wccp&tpl=' . rawurlencode( $key ) );
				?>
				<div class="wccp-tpl-pick <?php echo $is_current ? 'is-current' : ''; ?> <?php echo $is_default ? 'is-default' : ''; ?>" data-tpl="<?php echo esc_attr( $key ); ?>">
					<button type="button" class="wccp-tpl-star" title="<?php echo $is_default ? 'این قالب پیش‌فرض checkout است' : 'کلیک کنید تا پیش‌فرض checkout شود'; ?>" data-tpl="<?php echo esc_attr( $key ); ?>" aria-label="پیش‌فرض">
						<?php echo $is_default ? '★' : '☆'; ?>
					</button>
					<a class="wccp-tpl-pick-main" href="<?php echo esc_url( $url ); ?>">
						<span class="wccp-tpl-pick-swatch" style="background:linear-gradient(135deg,<?php echo esc_attr( $tpl['primary'] ); ?>,<?php echo esc_attr( $tpl['background'] ); ?>)"></span>
						<span class="wccp-tpl-pick-name"><?php echo esc_html( $tpl['label'] ); ?></span>
						<span class="wccp-tpl-pick-count"><?php echo esc_html( (string) $field_count ); ?> فیلد</span>
						<?php if ( $is_default ) : ?>
							<span class="wccp-tag required">★ پیش‌فرض checkout</span>
						<?php endif; ?>
						<?php if ( $is_current ) : ?>
							<span class="wccp-tag custom">در حال ویرایش</span>
						<?php endif; ?>
					</a>
				</div>
			<?php endforeach; ?>
		</div>
	</section>

	<div class="wccp-current-tpl-bar">
		<strong>۲) افزودن فیلد سفارشی برای قالب:</strong>
		<span class="wccp-current-tpl-name"><?php echo esc_html( $tpl_label ); ?></span>
		<code dir="ltr"><?php echo esc_html( $current_tpl ); ?></code>
		<?php if ( $current_tpl === $default_tpl ) : ?>
			<span class="wccp-tag required">★ روی صفحه پرداخت فروشگاه اعمال می‌شود</span>
		<?php else : ?>
			<button type="button" class="button button-small wccp-tpl-star-inline" data-tpl="<?php echo esc_attr( $current_tpl ); ?>">☆ انتخاب به‌عنوان پیش‌فرض</button>
		<?php endif; ?>
	</div>

	<div class="wccp-app" data-mode="template" data-template="<?php echo esc_attr( $current_tpl ); ?>" id="wccp-app">
		<?php include WCCP_PATH . 'templates/admin-fields-board.php'; ?>
	</div>

	<div id="wccp-toast" class="wccp-toast" hidden></div>
	<?php include WCCP_PATH . 'templates/admin-field-modal.php'; ?>
</div>
