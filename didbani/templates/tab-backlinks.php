<?php
defined( 'ABSPATH' ) || exit;
$map      = $pid ? DID_Backlinks::map( $pid ) : array();
$job      = $pid ? DID_Db::latest_job( $pid, 'backlinks' ) : null;
$has_dfs  = DID_Settings::has_dataforseo();
?>
<div class="did-actions">
	<button type="button" class="button button-primary" id="did-run-backlinks" <?php disabled( ! $usable || ! $pid || ! $has_dfs ); ?>>بررسی بک‌لینک</button>
	<p class="did-job-msg" id="did-job-msg" <?php echo $job ? '' : 'hidden'; ?>>
		<?php
		if ( $job ) {
			echo esc_html( $job['status'] . ' — ' . $job['message'] );
		}
		?>
	</p>
</div>

<?php if ( ! $has_dfs ) : ?>
	<p class="did-muted">برای تعداد بک‌لینک و انکر تکست، ورود DataForSEO را در تب تنظیمات وارد کنید.</p>
<?php endif; ?>

<?php if ( ! $domains ) : ?>
	<p class="did-muted">ابتدا پروژه را با سایت خود (و در صورت تمایل رقبا) ذخیره کنید.</p>
<?php else : ?>
	<div class="did-table-wrap">
		<table class="widefat striped did-backlinks">
			<thead>
				<tr>
					<th>دامنه</th>
					<th>نقش</th>
					<th>بک‌لینک</th>
					<th>دامنهٔ ارجاع</th>
					<th>انکر تکست</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $domains as $d ) : ?>
					<?php $row = isset( $map[ (int) $d['id'] ] ) ? $map[ (int) $d['id'] ] : null; ?>
					<tr>
						<td dir="ltr"><?php echo esc_html( $d['host'] ); ?></td>
						<td><?php echo 'own' === $d['kind'] ? 'سایت' : 'رقیب'; ?></td>
						<td>
							<?php
							if ( $row && '' === (string) $row['error'] ) {
								echo esc_html( DID_Admin::format_int( $row['backlinks'] ) );
							} elseif ( $row && $row['error'] ) {
								echo '<span class="did-muted">' . esc_html( $row['error'] ) . '</span>';
							} else {
								echo '<span class="did-muted">—</span>';
							}
							?>
						</td>
						<td>
							<?php
							echo $row && '' === (string) $row['error']
								? esc_html( DID_Admin::format_int( $row['referring_domains'] ) )
								: '<span class="did-muted">—</span>';
							?>
						</td>
						<td>
							<?php
							if ( $row && '' === (string) $row['error'] ) {
								echo DID_Admin::anchors_html( $row['anchors'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							} else {
								echo '<span class="did-muted">—</span>';
							}
							?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
<?php endif; ?>
