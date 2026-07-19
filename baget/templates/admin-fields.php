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
			<h1>Baget — قالب‌های صفحه پرداخت</h1>
			<p class="wccp-muted">ابتدا قالب را از لیست انتخاب کنید، با ★ مشخص کنید کدام قالب پیش‌فرض checkout است، سپس برای همان قالب فیلد/سوال سفارشی اضافه کنید.</p>
		</div>
		<div class="wccp-topbar-actions">
			<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=wccp&tab=templates' ) ); ?>">+ افزودن قالب</a>
			<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=wccp&tab=wc-products' ) ); ?>">محصولات فروشگاه</a>
			<button type="button" class="button button-secondary" id="wccp-add-info">+ متن ساده</button>
			<button type="button" class="button button-secondary" id="wccp-add-consent">+ رضایت‌نامه</button>
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
				<span class="wccp-muted">روی نام قالب کلیک کنید تا انتخاب شود · روی ★ بزنید تا پیش‌فرض صفحه پرداخت شود</span>
			</div>
			<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=wccp&tab=templates' ) ); ?>">+ افزودن قالب جدید</a>
		</header>

		<div class="wccp-tpl-table" role="list">
			<div class="wccp-tpl-table-head" aria-hidden="true">
				<span>پیش‌فرض</span>
				<span>نام قالب</span>
				<span>شناسه</span>
				<span>تعداد فیلد</span>
				<span>وضعیت</span>
			</div>
			<?php foreach ( $templates as $key => $tpl ) :
				$is_default  = ( $key === $default_tpl );
				$is_current  = ( $key === $current_tpl );
				$field_count = count( \WCCP\Templates::sanitize_fields( $tpl['fields'] ?? array() ) );
				$url         = admin_url( 'admin.php?page=wccp&tpl=' . rawurlencode( $key ) );
				?>
				<div class="wccp-tpl-row <?php echo $is_current ? 'is-current' : ''; ?> <?php echo $is_default ? 'is-default' : ''; ?>" data-tpl="<?php echo esc_attr( $key ); ?>" role="listitem">
					<button
						type="button"
						class="wccp-tpl-star"
						title="<?php echo $is_default ? 'این قالب پیش‌فرض صفحه پرداخت است' : 'کلیک کنید تا پیش‌فرض صفحه پرداخت شود'; ?>"
						data-tpl="<?php echo esc_attr( $key ); ?>"
						aria-label="<?php echo $is_default ? 'پیش‌فرض' : 'انتخاب به‌عنوان پیش‌فرض'; ?>"
						aria-pressed="<?php echo $is_default ? 'true' : 'false'; ?>"
					>
						<?php echo $is_default ? '★' : '☆'; ?>
					</button>

					<a class="wccp-tpl-row-main" href="<?php echo esc_url( $url ); ?>">
						<span class="wccp-tpl-pick-swatch" style="background:linear-gradient(90deg,<?php echo esc_attr( $tpl['primary'] ); ?>,<?php echo esc_attr( $tpl['background'] ); ?>)"></span>
						<span class="wccp-tpl-pick-name"><?php echo esc_html( $tpl['label'] ); ?></span>
					</a>

					<code class="wccp-tpl-row-key" dir="ltr"><?php echo esc_html( $key ); ?></code>
					<span class="wccp-tpl-pick-count"><?php echo esc_html( (string) $field_count ); ?> فیلد</span>

					<span class="wccp-tpl-row-status">
						<?php if ( $is_default ) : ?>
							<span class="wccp-tag required">★ پیش‌فرض checkout</span>
						<?php endif; ?>
						<?php if ( $is_current ) : ?>
							<span class="wccp-tag custom">در حال ویرایش فیلدها</span>
						<?php else : ?>
							<a class="button button-small" href="<?php echo esc_url( $url ); ?>">انتخاب</a>
						<?php endif; ?>
					</span>
				</div>
			<?php endforeach; ?>
		</div>
	</section>

	<section class="wccp-fields-for-tpl" id="wccp-fields-for-tpl">
		<div class="wccp-current-tpl-bar">
			<div class="wccp-current-tpl-bar-text">
				<strong>۲) فیلدهای سفارشی قالب انتخاب‌شده</strong>
				<span class="wccp-current-tpl-name"><?php echo esc_html( $tpl_label ); ?></span>
				<code dir="ltr"><?php echo esc_html( $current_tpl ); ?></code>
				<?php if ( $current_tpl === $default_tpl ) : ?>
					<span class="wccp-tag required">★ همین قالب روی صفحه پرداخت فروشگاه اعمال می‌شود</span>
				<?php else : ?>
					<button type="button" class="button button-small wccp-tpl-star-inline" data-tpl="<?php echo esc_attr( $current_tpl ); ?>">☆ انتخاب به‌عنوان پیش‌فرض checkout</button>
				<?php endif; ?>
			</div>
			<div class="wccp-current-tpl-bar-actions">
				<button type="button" class="button button-primary" id="wccp-add-field-top">+ فیلد / سوال جدید برای این قالب</button>
			</div>
		</div>

		<div class="wccp-app" data-mode="template" data-template="<?php echo esc_attr( $current_tpl ); ?>" id="wccp-app">
			<?php include WCCP_PATH . 'templates/admin-fields-board.php'; ?>
		</div>
	</section>

	<div id="wccp-toast" class="wccp-toast" hidden></div>
	<?php include WCCP_PATH . 'templates/admin-field-modal.php'; ?>
</div>
