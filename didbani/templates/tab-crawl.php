<?php
defined( 'ABSPATH' ) || exit;
$pages = $pid ? DID_Db::pages( $pid, 0, 250 ) : array();
$kw_map = array();
foreach ( $keywords as $kw ) {
	$kw_map[ (string) $kw['id'] ] = $kw['keyword'];
}
$domain_map = array();
foreach ( $domains as $d ) {
	$domain_map[ (int) $d['id'] ] = $d;
}
$crawl_job = $pid ? DID_Db::latest_job( $pid, 'crawl' ) : null;
?>
<div class="did-actions">
	<button type="button" class="button button-primary" id="did-run-crawl" <?php disabled( ! $usable || ! $pid ); ?>>شروع کرول</button>
	<p class="did-job-msg" id="did-job-msg" <?php echo $crawl_job ? '' : 'hidden'; ?>>
		<?php
		if ( $crawl_job ) {
			echo esc_html( $crawl_job['status'] . ' — ' . $crawl_job['message'] );
		}
		?>
	</p>
</div>

<div class="did-note">
	<ul>
		<li>فقط GET صفحات عمومی؛ لاگین و دیوار پرداخت دور زده نمی‌شود.</li>
		<li>robots.txt رعایت می‌شود. سقف صفحه در تنظیمات است (پیش‌فرض ۵۰ برای هر دامنه).</li>
		<li>کرول از سرور همین وردپرس اجرا می‌شود؛ اگر رقیب IP دیتاسنتر را بسته باشد شکست می‌خورد.</li>
		<li>سایت‌های تک‌صفحه‌ای جاوااسکریپتی ممکن است محتوای کمی بدهند.</li>
	</ul>
</div>

<?php if ( $domains ) : ?>
	<table class="widefat striped">
		<thead>
			<tr>
				<th>دامنه</th>
				<th>نقش</th>
				<th>وضعیت</th>
				<th>صفحات</th>
				<th>آخرین کرول</th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $domains as $d ) : ?>
				<tr>
					<td dir="ltr"><?php echo esc_html( $d['host'] ); ?></td>
					<td><?php echo 'own' === $d['kind'] ? 'سایت خودم' : 'رقیب'; ?></td>
					<td><?php echo esc_html( $d['crawl_status'] ? $d['crawl_status'] : '—' ); ?>
						<?php if ( ! empty( $d['crawl_error'] ) ) : ?>
							<div class="did-muted"><?php echo esc_html( $d['crawl_error'] ); ?></div>
						<?php endif; ?>
					</td>
					<td><?php echo (int) $d['page_count']; ?></td>
					<td><?php echo $d['last_crawled_at'] ? esc_html( $d['last_crawled_at'] ) : '—'; ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
<?php endif; ?>

<h2 class="did-h2">صفحات و حضور کلیدواژه</h2>
<?php if ( ! $pages ) : ?>
	<p class="did-muted">هنوز صفحه‌ای کرول نشده.</p>
<?php else : ?>
	<div class="did-table-wrap">
		<table class="widefat striped did-pages">
			<thead>
				<tr>
					<th>دامنه</th>
					<th>عنوان</th>
					<th>H1</th>
					<th>کلمه</th>
					<th>کلیدواژه در صفحه</th>
					<th>آدرس</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $pages as $page ) : ?>
					<?php
					$hits = json_decode( (string) $page['keyword_hits'], true );
					$badges = array();
					if ( is_array( $hits ) ) {
						foreach ( $hits as $kid => $h ) {
							if ( empty( $h['count'] ) ) {
								continue;
							}
							$label = isset( $kw_map[ (string) $kid ] ) ? $kw_map[ (string) $kid ] : $kid;
							$where = array();
							if ( ! empty( $h['in_title'] ) ) {
								$where[] = 'عنوان';
							}
							if ( ! empty( $h['in_h1'] ) ) {
								$where[] = 'H1';
							}
							if ( ! empty( $h['in_body'] ) ) {
								$where[] = 'متن';
							}
							$badges[] = $label . ( $where ? ' (' . implode('، ', $where) . ')' : '' );
						}
					}
					$host = isset( $domain_map[ (int) $page['domain_id'] ] ) ? $domain_map[ (int) $page['domain_id'] ]['host'] : '';
					?>
					<tr>
						<td dir="ltr"><?php echo esc_html( $host ); ?></td>
						<td><?php echo esc_html( $page['title'] ); ?></td>
						<td><?php echo esc_html( $page['h1'] ); ?></td>
						<td><?php echo (int) $page['word_count']; ?></td>
						<td><?php echo $badges ? esc_html( implode( ' · ', $badges ) ) : '—'; ?></td>
						<td><a href="<?php echo esc_url( $page['url'] ); ?>" target="_blank" rel="noopener" dir="ltr"><?php echo esc_html( $page['url'] ); ?></a></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
<?php endif; ?>
