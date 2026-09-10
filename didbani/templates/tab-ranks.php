<?php
defined( 'ABSPATH' ) || exit;
/** @var int $pid */
/** @var array $domains */
/** @var array $keywords */
/** @var bool $usable */
/** @var string $device */
/** @var array $cities */
/** @var string $city */
$matrix   = $pid ? DID_Rank::matrix( $pid, $city, $device ) : array();
$engines  = DID_Rank::enabled_engines();
$rank_job = $pid ? DID_Db::latest_job( $pid, 'rank' ) : null;
$own_id   = 0;
foreach ( $domains as $d ) {
	if ( 'own' === $d['kind'] ) {
		$own_id = (int) $d['id'];
		break;
	}
}
$city_mx = ( $pid && $own_id ) ? DID_Rank::city_matrix( $pid, $own_id, $device ) : array();
$movers  = $pid ? DID_Rank::movers( $pid, $city, $device ) : array();
$gains   = array();
$drops   = array();
foreach ( $movers as $m ) {
	if ( 'up' === $m['change']['kind'] || 'enter' === $m['change']['kind'] ) {
		$gains[] = $m;
	} elseif ( 'down' === $m['change']['kind'] || 'exit' === $m['change']['kind'] ) {
		$drops[] = $m;
	}
}
usort(
	$gains,
	function ( $a, $b ) {
		return (int) $b['change']['steps'] - (int) $a['change']['steps'];
	}
);
usort(
	$drops,
	function ( $a, $b ) {
		return (int) $b['change']['steps'] - (int) $a['change']['steps'];
	}
);
$kw_map = array();
foreach ( $keywords as $kw ) {
	$kw_map[ (int) $kw['id'] ] = $kw['keyword'];
}
$dom_map = array();
foreach ( $domains as $d ) {
	$dom_map[ (int) $d['id'] ] = $d['host'];
}
?>
<div class="did-actions">
	<button type="button" class="button button-primary" id="did-run-rank" <?php disabled( ! $usable || ! $pid ); ?>>بررسی رتبه موبایل الان</button>
	<p class="did-job-msg" id="did-job-msg" <?php echo $rank_job ? '' : 'hidden'; ?>>
		<?php
		if ( $rank_job ) {
			echo esc_html( $rank_job['status'] . ' — ' . $rank_job['message'] );
		}
		?>
	</p>
</div>

<p class="did-note">
	دیوایس: <strong><?php echo esc_html( DID_Geo::device_label( $device ) ); ?></strong>
	· شهر این جدول: <strong><?php echo esc_html( DID_Geo::label( $city ) ); ?></strong>
	· رشد یعنی عدد رتبه کمتر شده؛ افت یعنی بیشتر شده.
	<?php if ( 'cse' === DID_Settings::get( 'google_serp_provider' ) ) : ?>
		<br />Google CSE شهر/موبایل را دقیق نمی‌دهد؛ برای رتبهٔ واقعی SerpAPI یا DataForSEO بگذارید.
	<?php endif; ?>
</p>

<?php DID_Admin::city_nav( 'ranks', $pid, $cities, $city ); ?>

<?php if ( ! $engines ) : ?>
	<div class="notice notice-info inline"><p>برای رتبه، در تب تنظیمات کلید Azure Bing و/یا SerpAPI (یا DataForSEO) را وارد کنید. اسکرپ HTML گوگل پشتیبانی نمی‌شود.</p></div>
<?php endif; ?>

<?php if ( $own_id && $keywords && $cities ) : ?>
	<h2 class="did-h2">سایت خودم در شهرهای ایران (<?php echo esc_html( DID_Geo::device_label( $device ) ); ?>)</h2>
	<div class="did-table-wrap">
		<table class="widefat striped did-matrix">
			<thead>
				<tr>
					<th>کلیدواژه</th>
					<?php foreach ( $cities as $slug ) : ?>
						<th><?php echo esc_html( DID_Geo::label( $slug ) ); ?></th>
					<?php endforeach; ?>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $keywords as $kw ) : ?>
					<tr>
						<th><?php echo esc_html( $kw['keyword'] ); ?></th>
						<?php foreach ( $cities as $slug ) : ?>
							<td>
								<div class="did-cell">
									<span class="did-eng">گوگل</span>
									<?php
									$g = isset( $city_mx[ (int) $kw['id'] ][ $slug ]['google'] )
										? $city_mx[ (int) $kw['id'] ][ $slug ]['google']
										: null;
									echo DID_Admin::position_html( $g ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
									?>
								</div>
								<div class="did-cell">
									<span class="did-eng">بینگ</span>
									<?php
									$b = isset( $city_mx[ (int) $kw['id'] ][ $slug ]['bing'] )
										? $city_mx[ (int) $kw['id'] ][ $slug ]['bing']
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

<div class="did-split">
	<div>
		<h2 class="did-h2">بیشترین رشد — <?php echo esc_html( DID_Geo::label( $city ) ); ?></h2>
		<?php if ( ! $gains ) : ?>
			<p class="did-muted">هنوز مقایسه‌ای نیست. یک بار «بررسی رتبه» را اجرا کنید و بعد از اجرای دوم، رشد مشخص می‌شود.</p>
		<?php else : ?>
			<ul class="did-movers">
				<?php foreach ( array_slice( $gains, 0, 8 ) as $m ) : ?>
					<li>
						<span class="did-delta did-delta-<?php echo esc_attr( $m['change']['kind'] ); ?>"><?php echo esc_html( $m['change']['label'] ); ?></span>
						<?php echo esc_html( isset( $kw_map[ (int) $m['keyword_id'] ] ) ? $kw_map[ (int) $m['keyword_id'] ] : '' ); ?>
						· <?php echo esc_html( isset( $dom_map[ (int) $m['domain_id'] ] ) ? $dom_map[ (int) $m['domain_id'] ] : '' ); ?>
						· <?php echo esc_html( DID_Admin::engine_label( $m['engine'] ) ); ?>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	</div>
	<div>
		<h2 class="did-h2">بیشترین افت — <?php echo esc_html( DID_Geo::label( $city ) ); ?></h2>
		<?php if ( ! $drops ) : ?>
			<p class="did-muted">افتی ثبت نشده.</p>
		<?php else : ?>
			<ul class="did-movers">
				<?php foreach ( array_slice( $drops, 0, 8 ) as $m ) : ?>
					<li>
						<span class="did-delta did-delta-<?php echo esc_attr( $m['change']['kind'] ); ?>"><?php echo esc_html( $m['change']['label'] ); ?></span>
						<?php echo esc_html( isset( $kw_map[ (int) $m['keyword_id'] ] ) ? $kw_map[ (int) $m['keyword_id'] ] : '' ); ?>
						· <?php echo esc_html( isset( $dom_map[ (int) $m['domain_id'] ] ) ? $dom_map[ (int) $m['domain_id'] ] : '' ); ?>
						· <?php echo esc_html( DID_Admin::engine_label( $m['engine'] ) ); ?>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	</div>
</div>

<h2 class="did-h2">رقبا در <?php echo esc_html( DID_Geo::label( $city ) ); ?></h2>
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
<?php else : ?>
	<p class="did-muted">ابتدا در تب پروژه، دامنه و کلیدواژه وارد کنید.</p>
<?php endif; ?>
