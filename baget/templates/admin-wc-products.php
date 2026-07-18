<?php
defined( 'ABSPATH' ) || exit;
/** @var string $tab */

$templates   = \WCCP\Templates::all();
$default_tpl = \WCCP\Templates::default_key();
$paged       = max( 1, (int) ( $_GET['paged'] ?? 1 ) ); // phpcs:ignore
$search      = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : ''; // phpcs:ignore
$per_page    = 20;

$wc_ok = class_exists( 'WooCommerce' ) || post_type_exists( 'product' );

$query = null;
$total = 0;
if ( $wc_ok ) {
	$args = array(
		'post_type'      => 'product',
		'post_status'    => array( 'publish', 'private', 'draft' ),
		'posts_per_page' => $per_page,
		'paged'          => $paged,
		'orderby'        => 'title',
		'order'          => 'ASC',
	);
	if ( $search !== '' ) {
		$args['s'] = $search;
	}
	$query = new \WP_Query( $args );
	$total = (int) $query->found_posts;
}
$max_pages = $query ? (int) $query->max_num_pages : 0;
?>
<div class="wrap wccp-wrap">
	<div class="wccp-topbar">
		<div>
			<h1>Baget — محصولات فروشگاه</h1>
			<p class="wccp-muted">همه محصولات ووکامرس را ببینید و برای هر کدام قالب صفحه پرداخت سفارشی انتخاب کنید.</p>
		</div>
		<div class="wccp-topbar-actions">
			<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=wccp&tab=templates' ) ); ?>">+ افزودن قالب</a>
			<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=wccp' ) ); ?>">افزودن فیلدها</a>
		</div>
	</div>

	<?php include WCCP_PATH . 'templates/admin-tabs.php'; ?>

	<?php
	$errors = get_transient( 'settings_errors' );
	if ( is_array( $errors ) ) {
		global $wp_settings_errors;
		$wp_settings_errors = $errors;
		delete_transient( 'settings_errors' );
	}
	settings_errors( 'wccp_wc_products' );
	?>

	<div class="wccp-howto">
		<div class="wccp-howto-step"><span>۱</span><div><strong>قالب بسازید</strong><p>دیجیتال / فیزیکی یا سفارشی</p></div></div>
		<div class="wccp-howto-step"><span>۲</span><div><strong>فیلدها را تنظیم کنید</strong><p>در تب افزودن فیلدها</p></div></div>
		<div class="wccp-howto-step"><span>۳</span><div><strong>محصول را انتخاب کنید</strong><p>از لیست زیر</p></div></div>
		<div class="wccp-howto-step"><span>۴</span><div><strong>قالب پرداخت را بزنید</strong><p>ذخیره → اعمال در checkout</p></div></div>
	</div>

	<?php if ( ! $wc_ok ) : ?>
		<div class="notice notice-warning"><p>ووکامرس فعال نیست. برای لیست محصولات فروشگاه، افزونه WooCommerce را نصب و فعال کنید.</p></div>
	<?php else : ?>

		<form method="get" class="wccp-wc-search" style="margin:0 0 14px;display:flex;gap:8px;flex-wrap:wrap;align-items:center">
			<input type="hidden" name="page" value="wccp" />
			<input type="hidden" name="tab" value="wc-products" />
			<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="جستجوی محصول…" class="regular-text" />
			<button type="submit" class="button">جستجو</button>
			<?php if ( $search !== '' ) : ?>
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=wccp&tab=wc-products' ) ); ?>">پاک کردن</a>
			<?php endif; ?>
			<span class="wccp-muted"><?php echo esc_html( (string) $total ); ?> محصول</span>
		</form>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'wccp_save_wc_templates' ); ?>
			<input type="hidden" name="action" value="wccp_save_wc_templates" />
			<input type="hidden" name="paged" value="<?php echo esc_attr( (string) $paged ); ?>" />
			<input type="hidden" name="s" value="<?php echo esc_attr( $search ); ?>" />

			<table class="widefat striped wccp-wc-table">
				<thead>
					<tr>
						<th style="width:70px">شناسه</th>
						<th>محصول</th>
						<th style="width:140px">وضعیت</th>
						<th style="width:280px">قالب صفحه پرداخت</th>
					</tr>
				</thead>
				<tbody>
					<?php if ( ! $query || ! $query->have_posts() ) : ?>
						<tr><td colspan="4">محصولی یافت نشد.</td></tr>
					<?php else : ?>
						<?php
						while ( $query->have_posts() ) :
							$query->the_post();
							$pid      = get_the_ID();
							$selected = sanitize_key( (string) get_post_meta( $pid, \WCCP\Templates::WC_PRODUCT_META, true ) );
							$has_explicit = ( $selected !== '' && isset( $templates[ $selected ] ) );
							if ( ! $has_explicit ) {
								$selected = '';
							}
							$status = get_post_status( $pid );
							?>
							<tr>
								<td><code dir="ltr"><?php echo esc_html( (string) $pid ); ?></code></td>
								<td>
									<strong>
										<a href="<?php echo esc_url( get_edit_post_link( $pid ) ); ?>">
											<?php echo esc_html( get_the_title() ?: '(بدون عنوان)' ); ?>
										</a>
									</strong>
								</td>
								<td><?php echo esc_html( $status ); ?></td>
								<td>
									<select name="wccp_tpl[<?php echo esc_attr( (string) $pid ); ?>]" class="widefat">
										<option value="">پیش‌فرض فروشگاه (<?php echo esc_html( $templates[ $default_tpl ]['label'] ?? $default_tpl ); ?>)</option>
										<?php foreach ( $templates as $key => $tpl ) : ?>
											<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $selected, $key ); ?>>
												<?php echo esc_html( $tpl['label'] ); ?>
												<?php echo ! empty( $tpl['builtin'] ) ? ' ★' : ''; ?>
											</option>
										<?php endforeach; ?>
									</select>
								</td>
							</tr>
						<?php endwhile; ?>
						<?php wp_reset_postdata(); ?>
					<?php endif; ?>
				</tbody>
			</table>

			<p style="margin-top:14px">
				<button type="submit" class="button button-primary button-large">ذخیره قالب محصولات</button>
			</p>
		</form>

		<?php if ( $max_pages > 1 ) : ?>
			<div class="tablenav" style="margin-top:12px">
				<div class="tablenav-pages">
					<?php
					echo wp_kses_post(
						paginate_links(
							array(
								'base'      => add_query_arg( array( 'paged' => '%#%', 'tab' => 'wc-products', 'page' => 'wccp', 's' => $search ) ),
								'format'    => '',
								'current'   => $paged,
								'total'     => $max_pages,
								'prev_text' => '«',
								'next_text' => '»',
							)
						)
					);
					?>
				</div>
			</div>
		<?php endif; ?>

	<?php endif; ?>
</div>
