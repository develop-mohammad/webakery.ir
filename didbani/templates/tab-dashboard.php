<?php
defined( 'ABSPATH' ) || exit;
$matrix   = $pid ? DID_Rank::matrix( $pid ) : array();
$rank_job = $pid ? DID_Db::latest_job( $pid, 'rank' ) : null;
$engines  = DID_Rank::enabled_engines();
?>
<?php if ( ! $project ) : ?>
	<div class="did-empty">
		<p>پروژه‌ای نیست. در تب «پروژه» کلیدواژه‌ها را وارد کنید. رقبا اختیاری‌اند.</p>
	</div>
<?php else : ?>
	<div class="did-actions">
		<button type="button" class="button button-primary" id="did-run-rank" <?php disabled( ! $usable || ! $pid ); ?>>بررسی رتبه</button>
		<p class="did-job-msg" id="did-job-msg" hidden></p>
	</div>
	<?php if ( ! $engines ) : ?>
		<p class="did-muted">در تنظیمات کلید بینگ یا گوگل را وارد کنید. اسکرپ HTML گوگل پشتیبانی نمی‌شود.</p>
	<?php endif; ?>
	<?php
	include DID_PATH . 'templates/partial-rank-table.php';
	?>
<?php endif; ?>
