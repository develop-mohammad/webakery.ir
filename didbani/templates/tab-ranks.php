<?php
defined( 'ABSPATH' ) || exit;
$matrix  = $pid ? DID_Rank::matrix( $pid ) : array();
$engines = DID_Rank::enabled_engines();
$rank_job = $pid ? DID_Db::latest_job( $pid, 'rank' ) : null;
?>
<div class="did-actions">
	<button type="button" class="button button-primary" id="did-run-rank" <?php disabled( ! $usable || ! $pid ); ?>>بررسی رتبه الان</button>
	<p class="did-job-msg" id="did-job-msg" <?php echo $rank_job ? '' : 'hidden'; ?>>
		<?php
		if ( $rank_job ) {
			echo esc_html( $rank_job['status'] . ' — ' . $rank_job['message'] );
		}
		?>
	</p>
</div>

<?php if ( ! $engines ) : ?>
	<div class="notice notice-info inline"><p>برای رتبه، در تب تنظیمات کلید Azure Bing و/یا SerpAPI (یا DataForSEO) را وارد کنید. اسکرپ HTML گوگل پشتیبانی نمی‌شود.</p></div>
<?php else : ?>
	<p class="did-note">موتورهای فعال:
		<?php
		$labs = array();
		foreach ( $engines as $e ) {
			$labs[] = DID_Admin::engine_label( $e );
		}
		echo esc_html( implode( '، ', $labs ) );
		?>
	</p>
<?php endif; ?>

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
