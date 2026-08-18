<?php
defined( 'ABSPATH' ) || exit;
/** @var array $diag */
?>
<div class="wdp-diagnose">

	<?php if ( empty( $diag['licensed'] ) ) : ?>
		<div class="notice notice-error inline" style="margin:10px 0">
			<p>
				⚠️ لایسنس/دوره آزمایشی این افزونه فعال نیست؛ به همین دلیل هیچ محصولی به‌طور خودکار به
				صفحه‌های تخفیف اضافه نمی‌شود. برای رفع این مشکل، به تب «لایسنس» بروید.
			</p>
		</div>
	<?php endif; ?>

	<?php if ( empty( $diag['woo'] ) ) : ?>
		<p class="wdp-admin-hint">ووکامرس فعال نیست.</p>
	<?php elseif ( empty( $diag['exists'] ) ) : ?>
		<p class="wdp-admin-hint">محصولی با این شناسه پیدا نشد.</p>
	<?php else : ?>

		<table class="widefat striped" style="margin-top:10px">
			<tbody>
				<tr>
					<th>محصول</th>
					<td>
						<?php echo esc_html( $diag['name'] ); ?>
						(#<?php echo (int) $diag['product_id']; ?>)
						<?php if ( ! empty( $diag['edit_link'] ) ) : ?>
							— <a href="<?php echo esc_url( $diag['edit_link'] ); ?>" target="_blank" rel="noopener">ویرایش محصول</a>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th>الان در حراج ووکامرس است؟</th>
					<td>
						<?php if ( $diag['is_on_sale'] ) : ?>
							<span class="wdp-pill wdp-pill-percent">بله</span>
						<?php else : ?>
							<span class="wdp-pill" style="background:#fee2e2;color:#b91c1c">خیر</span>
							<span class="wdp-hint">
								— یعنی «قیمت حراج» (Sale price) در ووکامرس برای این محصول خالی است یا تاریخ
								شروع/پایان تخفیف زمان‌بندی‌شده هنوز نرسیده/گذشته است. تا این مورد درست نشود،
								محصول در هیچ صفحه تخفیفی قرار نمی‌گیرد، حتی اگر بازه و دسته‌بندی درست باشد.
							</span>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th>قیمت اصلی / قیمت فروش</th>
					<td dir="ltr">
						<?php echo esc_html( WDP_Util::trim_zeros( $diag['regular'] ) ); ?>
						/
						<?php echo esc_html( WDP_Util::trim_zeros( $diag['sale'] ) ); ?>
					</td>
				</tr>
				<tr>
					<th>تخفیف محاسبه‌شده</th>
					<td>
						<?php if ( $diag['discount'] ) : ?>
							<?php echo esc_html( WDP_Util::fa_digits( WDP_Util::trim_zeros( $diag['discount']['percent'] ) ) ); ?>٪
							(<?php echo esc_html( WDP_Util::fa_digits( WDP_Util::trim_zeros( $diag['discount']['fixed'] ) ) ); ?> <?php echo esc_html( WDP_Taxonomy::currency() ); ?>)
						<?php else : ?>
							<span class="wdp-muted">— (تخفیف فعالی محاسبه نشد)</span>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th>دسته‌بندی‌های محصول</th>
					<td><?php echo $diag['category_names'] ? esc_html( implode( '، ', $diag['category_names'] ) ) : '<span class="wdp-muted">بدون دسته‌بندی</span>'; ?></td>
				</tr>
			</tbody>
		</table>

		<?php if ( ! empty( $diag['rule_checks'] ) ) : ?>
			<table class="widefat striped" style="margin-top:14px">
				<thead>
					<tr>
						<th>صفحه تخفیف</th>
						<th>بازه</th>
						<th>دسته‌بندی لازم</th>
						<th>دسته‌بندی محصول تطبیق دارد؟</th>
						<th>بازه تطبیق دارد؟</th>
						<th>نتیجه</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $diag['rule_checks'] as $row ) : ?>
						<tr<?php echo $row['match'] ? ' style="background:#f0fdf4"' : ''; ?>>
							<td><?php echo esc_html( $row['name'] ); ?></td>
							<td><?php echo esc_html( WDP_Util::range_label( $row['type'], $row['min'], $row['max'], WDP_Taxonomy::currency() ) ); ?></td>
							<td>
								<?php
								if ( empty( $row['categories'] ) ) {
									echo '<span class="wdp-muted">همه دسته‌بندی‌ها</span>';
								} else {
									$names = array();
									foreach ( $row['categories'] as $cid ) {
										$t = get_term( $cid, 'product_cat' );
										if ( $t && ! is_wp_error( $t ) ) {
											$names[] = $t->name;
										}
									}
									echo esc_html( implode( '، ', $names ) );
								}
								?>
							</td>
							<td><?php echo $row['cat_ok'] ? '✅' : '❌'; ?></td>
							<td>
								<?php echo $row['value_ok'] ? '✅' : '❌'; ?>
								<?php if ( null !== $row['value_now'] ) : ?>
									<span class="wdp-hint">(الان: <?php echo esc_html( WDP_Util::trim_zeros( $row['value_now'] ) ); ?>)</span>
								<?php endif; ?>
							</td>
							<td><?php echo $row['match'] ? '<strong>منطبق</strong>' : '—'; ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php else : ?>
			<p class="wdp-admin-hint" style="margin-top:14px">هنوز هیچ صفحه تخفیفی ساخته نشده است.</p>
		<?php endif; ?>

		<p class="wdp-hint-block" style="margin-top:14px">
			<?php if ( $diag['matched_term_id'] ) : ?>
				✅ نتیجه نهایی: این محصول باید در صفحه
				«<?php $t = get_term( $diag['matched_term_id'], WDP_Taxonomy::TAXONOMY ); echo esc_html( $t && ! is_wp_error( $t ) ? $t->name : '#' . $diag['matched_term_id'] ); ?>»
				قرار بگیرد.
			<?php else : ?>
				❌ نتیجه نهایی: این محصول با هیچ صفحه تخفیفی مطابقت ندارد.
			<?php endif; ?>

			<?php
			$current = $diag['current_terms'] ?? array();
			$target  = $diag['matched_term_id'] ? array( $diag['matched_term_id'] ) : array();
			sort( $current );
			sort( $target );
			if ( $current !== $target ) :
				?>
				<br>⚠️ اما وضعیت فعلی محصول با این نتیجه یکی نیست — روی دکمه «بازبینی همین حالا» در همین صفحه بزنید
				تا این محصول (و همه محصولات دیگر) دوباره بررسی و به‌روزرسانی شوند.
			<?php else : ?>
				<br>وضعیت فعلی محصول با این نتیجه یکسان است؛ چیزی برای اصلاح نیست.
			<?php endif; ?>
		</p>

	<?php endif; ?>
</div>
