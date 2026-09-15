<?php
defined( 'ABSPATH' ) || exit;
/** @var array $keywords */
/** @var array $domains */
/** @var array $matrix */
list( $own, $comps ) = DID_Admin::split_domains( $domains );
$cols = array();
if ( $own ) {
	$cols[] = $own;
}
foreach ( $comps as $c ) {
	$cols[] = $c;
}
?>
<?php if ( ! $keywords ) : ?>
	<p class="did-muted">کلیدواژه‌ای ثبت نشده. در تب پروژه حداقل یک کلمه بگذارید.</p>
<?php elseif ( ! $cols ) : ?>
	<p class="did-muted">دامنهٔ سایت مشخص نیست. پروژه را ذخیره کنید تا دامنه از آدرس وردپرس پر شود.</p>
<?php else : ?>
	<div class="did-table-wrap">
		<table class="widefat striped did-ranks">
			<thead>
				<tr>
					<th class="did-kw">کلمه کلیدی</th>
					<?php foreach ( $cols as $d ) : ?>
						<th colspan="2">
							<span dir="ltr"><?php echo esc_html( $d['host'] ); ?></span>
							<?php if ( 'own' === $d['kind'] ) : ?>
								<span class="did-tag">سایت</span>
							<?php endif; ?>
						</th>
					<?php endforeach; ?>
				</tr>
				<tr class="did-subhead">
					<th></th>
					<?php foreach ( $cols as $d ) : ?>
						<th>گوگل</th>
						<th>بینگ</th>
					<?php endforeach; ?>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $keywords as $kw ) : ?>
					<tr>
						<th class="did-kw"><?php echo esc_html( $kw['keyword'] ); ?></th>
						<?php foreach ( $cols as $d ) : ?>
							<td>
								<?php
								$g = isset( $matrix[ (int) $kw['id'] ][ (int) $d['id'] ]['google'] )
									? $matrix[ (int) $kw['id'] ][ (int) $d['id'] ]['google']
									: null;
								echo DID_Admin::position_html( $g ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								?>
							</td>
							<td>
								<?php
								$b = isset( $matrix[ (int) $kw['id'] ][ (int) $d['id'] ]['bing'] )
									? $matrix[ (int) $kw['id'] ][ (int) $d['id'] ]['bing']
									: null;
								echo DID_Admin::position_html( $b ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								?>
							</td>
						<?php endforeach; ?>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
<?php endif; ?>
