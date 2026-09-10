<?php
defined( 'ABSPATH' ) || exit;
/** @var int $pid */
/** @var array $domains */
/** @var array $keywords */
/** @var bool $usable */
$matrix   = $pid ? DID_Rank::matrix( $pid ) : array();
$engines  = DID_Rank::enabled_engines();
$rank_job = $pid ? DID_Db::latest_job( $pid, 'rank' ) : null;
?>
<div class="did-actions">
	<button type="button" class="button button-primary" id="did-run-rank" <?php disabled( ! $usable || ! $pid ); ?>>بررسی رتبه</button>
	<p class="did-job-msg" id="did-job-msg" <?php echo $rank_job ? '' : 'hidden'; ?>>
		<?php
		if ( $rank_job ) {
			echo esc_html( $rank_job['status'] . ' — ' . $rank_job['message'] );
		}
		?>
	</p>
</div>

<?php if ( ! $engines ) : ?>
	<div class="notice notice-info inline"><p>برای رتبه، در تب تنظیمات کلید Azure Bing و/یا SerpAPI (یا DataForSEO) را وارد کنید.</p></div>
<?php endif; ?>

<?php include DID_PATH . 'templates/partial-rank-table.php'; ?>
