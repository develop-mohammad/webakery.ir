<?php
defined( 'ABSPATH' ) || exit;
$matrix   = $pid ? DID_Rank::matrix( $pid, $city, $device ) : array();
$crawl_job = $pid ? DID_Db::latest_job( $pid, 'crawl' ) : null;
$rank_job  = $pid ? DID_Db::latest_job( $pid, 'rank' ) : null;
$pages_n   = 0;
if ( $pid ) {
	foreach ( $domains as $d ) {
		$pages_n += (int) $d['page_count'];
	}
}
?>
<div class="did-grid">
	<div class="did-card">
		<div class="did-k">کلیدواژه</div>
		<div class="did-v"><?php echo (int) count( $keywords ); ?></div>
	</div>
	<div class="did-card">
		<div class="did-k">دامنهٔ رصد</div>
		<div class="did-v"><?php echo (int) count( $domains ); ?></div>
	</div>
	<div class="did-card">
		<div class="did-k">صفحات کرول‌شده</div>
		<div class="did-v"><?php echo (int) $pages_n; ?></div>
	</div>
	<div class="did-card">
		<div class="did-k">آخرین رتبه</div>
		<div class="did-v"><?php echo $rank_job && ! empty( $rank_job['finished_at'] ) ? esc_html( $rank_job['finished_at'] ) : '—'; ?></div>
	</div>
</div>

<?php if ( ! $project ) : ?>
	<div class="did-empty">
		<p>هنوز پروژه‌ای نساخته‌اید. از تب «پروژه» سایت خود، رقبا و کلیدواژه‌ها را وارد کنید.</p>
	</div>
<?php else : ?>
	<div class="did-actions">
		<button type="button" class="button button-primary" id="did-run-rank" <?php disabled( ! $usable || ! $pid ); ?>>بررسی رتبه الان</button>
		<button type="button" class="button" id="did-run-crawl" <?php disabled( ! $usable || ! $pid ); ?>>شروع کرول</button>
		<p class="did-job-msg" id="did-job-msg" hidden></p>
	</div>

	<p class="did-note">
		رتبهٔ فعلی برای <strong><?php echo esc_html( DID_Geo::device_label( $device ) ); ?></strong>
		در <strong><?php echo esc_html( DID_Geo::label( $city ) ); ?></strong>.
		رشد/افت و مقایسهٔ شهرها در تب «رتبه‌ها» است.
		رتبه از API است نه اسکرپ گوگل.
		<?php if ( $crawl_job ) : ?>
			<br />آخرین کرول: <?php echo esc_html( $crawl_job['status'] . ' — ' . $crawl_job['message'] ); ?>
		<?php endif; ?>
	</p>
	<?php DID_Admin::city_nav( 'dashboard', $pid, $cities, $city ); ?>

	<?php if ( $keywords && $domains ) : ?>
		<div class="did-table-wrap">
			<table class="widefat striped did-matrix">
				<thead>
					<tr>
						<th>کلیدواژه</th>
						<?php foreach ( $domains as $d ) : ?>
							<th>
								<?php echo esc_html( $d['host'] ); ?>
								<?php if ( 'own' === $d['kind'] ) : ?>
									<span class="did-pill did-pill-info">خودم</span>
								<?php endif; ?>
							</th>
						<?php endforeach; ?>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $keywords as $kw ) : ?>
						<tr>
							<th><?php echo esc_html( $kw['keyword'] ); ?></th>
							<?php foreach ( $domains as $d ) : ?>
								<td>
									<div class="did-cell">
										<span class="did-eng">گوگل</span>
										<?php
										$g = isset( $matrix[ (int) $kw['id'] ][ (int) $d['id'] ]['google'] )
											? $matrix[ (int) $kw['id'] ][ (int) $d['id'] ]['google']
											: null;
										echo DID_Admin::position_html( $g ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
										?>
									</div>
									<div class="did-cell">
										<span class="did-eng">بینگ</span>
										<?php
										$b = isset( $matrix[ (int) $kw['id'] ][ (int) $d['id'] ]['bing'] )
											? $matrix[ (int) $kw['id'] ][ (int) $d['id'] ]['bing']
											: null;
										echo DID_Admin::position_html( $b ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
										?>
									</div>
								</td>
							<?php endforeach; ?>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	<?php endif; ?>
<?php endif; ?>
