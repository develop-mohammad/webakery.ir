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
			<h1>Baget — فیلدها بر اساس قالب</h1>
			<p class="wccp-muted">اول قالب را انتخاب کنید (ستاره = پیش‌فرض checkout)، بعد فیلدهای همان قالب را بسازید و مرتب کنید.</p>
		</div>
		<div class="wccp-topbar-actions">
			<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=wccp&tab=templates' ) ); ?>">+ مدیریت ظاهر قالب‌ها</a>
			<button type="button" class="button button-secondary" id="wccp-add-radio">+ سوال رادیو</button>
			<button type="button" class="button button-secondary" id="wccp-add-checkboxes">+ سوال چندگزینه‌ای</button>
			<button type="button" class="wccp-btn-save" id="wccp-save-btn">
				<span class="dashicons dashicons-yes"></span> ذخیره فیلدهای این قالب
			</button>
		</div>
	</div>

	<?php include WCCP_PATH . 'templates/admin-tabs.php'; ?>

	<section class="wccp-tpl-picker" id="wccp-tpl-picker">
		<header>
			<strong>قالب‌ها</strong>
			<span class="wccp-muted">روی ★ بزنید تا پیش‌فرض صفحه پرداخت شود · روی کارت بزنید تا فیلدهایش را ویرایش کنید</span>
		</header>
		<div class="wccp-tpl-picker-list">
			<?php foreach ( $templates as $key => $tpl ) :
				$is_default  = ( $key === $default_tpl );
				$is_current  = ( $key === $current_tpl );
				$field_count = count( \WCCP\Templates::sanitize_fields( $tpl['fields'] ?? array() ) );
				$url         = admin_url( 'admin.php?page=wccp&tpl=' . rawurlencode( $key ) );
				?>
				<div class="wccp-tpl-pick <?php echo $is_current ? 'is-current' : ''; ?> <?php echo $is_default ? 'is-default' : ''; ?>" data-tpl="<?php echo esc_attr( $key ); ?>">
					<button type="button" class="wccp-tpl-star" title="<?php echo $is_default ? 'قالب پیش‌فرض checkout' : 'تبدیل به پیش‌فرض checkout'; ?>" data-tpl="<?php echo esc_attr( $key ); ?>" aria-label="پیش‌فرض">
						<?php echo $is_default ? '★' : '☆'; ?>
					</button>
					<a class="wccp-tpl-pick-main" href="<?php echo esc_url( $url ); ?>">
						<span class="wccp-tpl-pick-swatch" style="background:linear-gradient(135deg,<?php echo esc_attr( $tpl['primary'] ); ?>,<?php echo esc_attr( $tpl['background'] ); ?>)"></span>
						<span class="wccp-tpl-pick-name"><?php echo esc_html( $tpl['label'] ); ?></span>
						<span class="wccp-tpl-pick-count"><?php echo esc_html( (string) $field_count ); ?> فیلد</span>
						<?php if ( $is_default ) : ?>
							<span class="wccp-tag required">پیش‌فرض checkout</span>
						<?php endif; ?>
					</a>
				</div>
			<?php endforeach; ?>
		</div>
	</section>

	<div class="wccp-current-tpl-bar">
		<strong>در حال ویرایش فیلدهای قالب:</strong>
		<span class="wccp-current-tpl-name"><?php echo esc_html( $tpl_label ); ?></span>
		<code dir="ltr"><?php echo esc_html( $current_tpl ); ?></code>
		<?php if ( $current_tpl === $default_tpl ) : ?>
			<span class="wccp-tag required">★ این قالب روی صفحه پرداخت فروشگاه اعمال می‌شود</span>
		<?php endif; ?>
	</div>

	<div class="wccp-howto">
		<div class="wccp-howto-step"><span>۱</span><div><strong>قالب را انتخاب کنید</strong><p>از لیست بالا</p></div></div>
		<div class="wccp-howto-step"><span>۲</span><div><strong>ستاره پیش‌فرض</strong><p>کدام قالب checkout باشد</p></div></div>
		<div class="wccp-howto-step"><span>۳</span><div><strong>فیلد/سوال اضافه کنید</strong><p>مخصوص همین قالب</p></div></div>
		<div class="wccp-howto-step"><span>۴</span><div><strong>ذخیره فیلدهای این قالب</strong><p>دکمه بنفش</p></div></div>
	</div>

	<div class="wccp-app" data-mode="template" data-template="<?php echo esc_attr( $current_tpl ); ?>" id="wccp-app">
		<?php include WCCP_PATH . 'templates/admin-fields-board.php'; ?>
	</div>

	<div id="wccp-toast" class="wccp-toast" hidden></div>
	<?php include WCCP_PATH . 'templates/admin-field-modal.php'; ?>
</div>
