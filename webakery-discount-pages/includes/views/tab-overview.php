<?php
defined( 'ABSPATH' ) || exit;

$terms    = get_terms(
	array(
		'taxonomy'   => WDP_Taxonomy::TAXONOMY,
		'hide_empty' => false,
	)
);
$terms    = is_wp_error( $terms ) ? array() : $terms;
$add_url  = admin_url( 'edit-tags.php?taxonomy=' . WDP_Taxonomy::TAXONOMY . '&post_type=product' );
$log      = get_option( 'wdp_log', array() );
$next_run = wp_next_scheduled( WDP_Cron::HOOK );
?>

<div class="wdp-bar">
	<a href="<?php echo esc_url( $add_url ); ?>" class="button button-primary">+ افزودن صفحه تخفیف جدید</a>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline">
		<?php wp_nonce_field( 'wdp_recalculate' ); ?>
		<input type="hidden" name="action" value="wdp_recalculate">
		<button type="submit" class="button">بازبینی همین حالا</button>
	</form>
	<span class="wdp-bar-hint">
		اجرای خودکار بعدی:
		<?php echo $next_run ? esc_html( date_i18n( 'Y-m-d H:i', $next_run ) ) : 'زمان‌بندی نشده'; ?>
	</span>
</div>

<?php if ( ! $terms ) : ?>
	<div class="wdp-empty">
		<div class="wdp-empty-ic">🏷️</div>
		<h2>هنوز صفحه تخفیفی نساخته‌اید</h2>
		<p>
			یک صفحه تخفیف مثل «۲۰ تا ۳۰ درصد تخفیف» بسازید؛ هر محصولی که الان همین‌قدر تخفیف داشته باشد،
			خودکار در این صفحه نشان داده می‌شود — با یک URL اختصاصی که می‌توانید در منو یا تبلیغات استفاده کنید.
			اگر بعداً تخفیف محصول عوض شود، خودکار به صفحه درست منتقل می‌شود.
		</p>
		<a href="<?php echo esc_url( $add_url ); ?>" class="button button-primary button-hero">افزودن صفحه تخفیف جدید</a>
	</div>
<?php else : ?>
	<table class="widefat striped wdp-table">
		<thead>
			<tr>
				<th>نام صفحه</th>
				<th>نوع</th>
				<th>بازه تخفیف</th>
				<th>دسته‌بندی محصول</th>
				<th>تعداد محصول</th>
				<th>لینک صفحه</th>
				<th>ویرایش</th>
			</tr>
		</thead>
		<tbody>
			<?php
			foreach ( $terms as $term ) :
				$link      = get_term_link( $term );
				$edit      = get_edit_term_link( $term->term_id, WDP_Taxonomy::TAXONOMY, 'product' );
				$type      = WDP_Taxonomy::type( $term->term_id );
				$cat_names = WDP_Taxonomy::category_names( $term->term_id );
				?>
				<tr>
					<td><strong><?php echo esc_html( $term->name ); ?></strong></td>
					<td>
						<span class="wdp-pill <?php echo 'fixed' === $type ? 'wdp-pill-fixed' : 'wdp-pill-percent'; ?>">
							<?php echo 'fixed' === $type ? 'مبلغ ثابت' : 'درصدی'; ?>
						</span>
					</td>
					<td><?php echo esc_html( WDP_Taxonomy::range_label( $term->term_id ) ); ?></td>
					<td><?php echo $cat_names ? esc_html( implode( '، ', $cat_names ) ) : '<span class="wdp-muted">همه دسته‌بندی‌ها</span>'; ?></td>
					<td><?php echo (int) $term->count; ?></td>
					<td>
						<?php if ( ! is_wp_error( $link ) ) : ?>
							<input type="text" class="wdp-link-copy" readonly dir="ltr" value="<?php echo esc_attr( $link ); ?>" onclick="this.select()">
							<a href="<?php echo esc_url( $link ); ?>" target="_blank" rel="noopener" class="button button-small">مشاهده</a>
						<?php endif; ?>
					</td>
					<td><a href="<?php echo esc_url( $edit ); ?>" class="button button-small">ویرایش</a></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
<?php endif; ?>

<div class="wdp-grid" style="margin-top:20px">
	<div class="wdp-card-box">
		<h3>راهنمای سریع</h3>
		<ol class="wdp-guide">
			<li>روی «افزودن صفحه تخفیف جدید» بزنید.</li>
			<li>نوع تخفیف را انتخاب کنید: <strong>درصدی</strong> یا <strong>مبلغ ثابت</strong>.</li>
			<li>بازه را بگذارید؛ مثلاً از <strong>۲۰</strong> تا <strong>۳۰</strong> برای «۲۰ تا ۳۰ درصد تخفیف».</li>
			<li>یک نامک (اسلاگ) دلخواه برای URL انتخاب کنید و ذخیره کنید.</li>
			<li>
				اختیاری: اگر می‌خواهید این صفحه فقط مخصوص یک یا چند دسته‌بندی محصول باشد
				(مثلاً «۲۰ تا ۳۰٪ لوازم خانگی» جدا از «۲۰ تا ۳۰٪ پوشاک»)، از بخش «محدود به دسته‌بندی محصول»
				همان دسته‌ها را تیک بزنید؛ در غیر این صورت صفحه برای همه دسته‌بندی‌ها باز است.
			</li>
			<li>
				تمام — محصولاتی که الان همان‌قدر تخفیف داشته باشند (و در صورت محدودیت، در همان دسته‌بندی باشند)
				خودکار در صفحه نشان داده می‌شوند؛ اگر بعداً تخفیف محصول عوض شود (مثلاً از ۲۰٪ به ۵۰٪) یا
				دسته‌بندی محصول عوض شود، خودکار از این صفحه خارج و به صفحه درست منتقل می‌شود.
			</li>
		</ol>
		<p class="wdp-hint-block">
			شورت‌کد فهرست صفحه‌های تخفیف: <code dir="ltr">[webakery_discount_pages]</code>
			— یا ویجت المنتور «صفحه‌های تخفیف» را در صفحه بگذارید.
		</p>
	</div>

	<div class="wdp-card-box" style="grid-column:1/-1">
		<h3>🛒 همه محصولاتی که الان در حراج ووکامرس هستند</h3>
		<?php $on_sale = WDP_Assigner::list_on_sale_overview(); ?>
		<?php if ( ! $on_sale ) : ?>
			<p class="wdp-muted">هیچ محصولی الان در حراج ووکامرس نیست (فیلد «Sale price» هیچ محصولی پر نشده یا خالی شده است).</p>
		<?php else : ?>
			<table class="widefat striped wdp-table">
				<thead>
					<tr>
						<th>محصول</th>
						<th>دسته‌بندی محصول</th>
						<th>تخفیف محاسبه‌شده</th>
						<th>باید در کدام صفحه باشد</th>
						<th>وضعیت</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $on_sale as $row ) : ?>
						<tr>
							<td>
								#<?php echo (int) $row['product_id']; ?> —
								<?php if ( $row['edit_link'] ) : ?>
									<a href="<?php echo esc_url( $row['edit_link'] ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $row['name'] ); ?></a>
								<?php else : ?>
									<?php echo esc_html( $row['name'] ); ?>
								<?php endif; ?>
							</td>
							<td><?php echo $row['category_names'] ? esc_html( implode( '، ', $row['category_names'] ) ) : '<span class="wdp-muted">بدون دسته‌بندی</span>'; ?></td>
							<td>
								<?php if ( $row['discount'] ) : ?>
									<?php echo esc_html( WDP_Util::fa_digits( WDP_Util::trim_zeros( $row['discount']['percent'] ) ) ); ?>٪
									(<?php echo esc_html( WDP_Util::fa_digits( WDP_Util::trim_zeros( $row['discount']['fixed'] ) ) ); ?> <?php echo esc_html( WDP_Taxonomy::currency() ); ?>)
								<?php else : ?>
									<span class="wdp-muted">—</span>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( $row['matched_name'] ); ?></td>
							<td><?php echo $row['in_sync'] ? '✅ هماهنگ' : '⚠️ نیاز به بازبینی'; ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<p class="wdp-hint" style="margin-top:8px">
				اگر ستون «باید در کدام صفحه باشد» برای محصولی «—» است، یعنی هیچ صفحه تخفیفی با دسته‌بندی و
				درصد تخفیف همان محصول تطبیق ندارد؛ به دسته‌بندی دقیق آن محصول در این جدول و دسته‌بندی
				تیک‌خورده روی صفحه تخفیف نگاه کنید — احتمالاً یکی نیستند (مثلاً دو دسته با نام مشابه).
			</p>
		<?php endif; ?>
	</div>

	<div class="wdp-card-box">
		<h3>🔍 چرا یک محصول در صفحه‌ای قرار نمی‌گیرد؟</h3>
		<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
			<input type="hidden" name="page" value="<?php echo esc_attr( WDP_MENU ); ?>">
			<input type="hidden" name="tab" value="overview">
			<p>
				<label class="wdp-label">شناسه محصول (Product ID)</label>
				<input type="number" name="wdp_check_product" min="1" style="max-width:160px"
					value="<?php echo isset( $_GET['wdp_check_product'] ) ? (int) $_GET['wdp_check_product'] : ''; ?>">
				<button type="submit" class="button">بررسی</button>
			</p>
		</form>
		<p class="wdp-hint">
			شناسه محصول را از صفحه ویرایش محصول (در آدرس مرورگر، بعد از <code dir="ltr">post=</code>)
			یا از ستون «شناسه» در فهرست محصولات وردپرس پیدا کنید.
		</p>
		<?php
		if ( ! empty( $_GET['wdp_check_product'] ) ) {
			$diag = WDP_Assigner::diagnose( (int) $_GET['wdp_check_product'] );
			include WDP_PATH . 'includes/views/partial-diagnose.php';
		}
		?>
	</div>

	<div class="wdp-card-box">
		<h3>آخرین اجراهای خودکار</h3>
		<?php if ( ! is_array( $log ) || ! $log ) : ?>
			<p class="wdp-muted">هنوز اجرای خودکاری ثبت نشده است.</p>
		<?php else : ?>
			<table class="widefat striped">
				<thead><tr><th>زمان</th><th>تعداد محصول بررسی‌شده</th></tr></thead>
				<tbody>
				<?php foreach ( array_slice( $log, 0, 10 ) as $row ) : ?>
					<tr>
						<td><?php echo esc_html( date_i18n( 'Y-m-d H:i', $row['time'] ) ); ?></td>
						<td><?php echo (int) $row['count']; ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>
</div>
