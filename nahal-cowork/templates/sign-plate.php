<?php
defined( 'ABSPATH' ) || exit;
$plate_src   = isset( $plate_src ) ? (string) $plate_src : '';
$plate_name  = isset( $plate_name ) ? (string) $plate_name : '';
$plate_role  = isset( $plate_role ) ? (string) $plate_role : 'امضا';
$plate_date  = isset( $plate_date ) ? (string) $plate_date : '';
$plate_extra = isset( $plate_extra ) ? (string) $plate_extra : '';
$plate_org   = ! empty( $plate_org );
?>
<div class="nck-sign-plate<?php echo $plate_org ? ' nck-sign-plate-org' : ''; ?>">
	<p class="nck-sign-plate-kicker"><?php echo esc_html( $plate_role ); ?></p>
	<div class="nck-sign-plate-stage">
		<?php if ( $plate_src !== '' ) : ?>
			<img class="nck-sign-plate-ink" src="<?php echo esc_attr( $plate_src ); ?>" alt="امضا" />
		<?php elseif ( $plate_org ) : ?>
			<p class="nck-org-sign"><?php echo esc_html( $plate_name ); ?></p>
		<?php endif; ?>
		<span class="nck-sign-plate-line" aria-hidden="true"></span>
	</div>
	<div class="nck-sign-plate-meta">
		<?php if ( ! $plate_org ) : ?>
			<strong><?php echo esc_html( $plate_name ); ?></strong>
		<?php else : ?>
			<span><?php echo esc_html( $plate_extra ); ?></span>
		<?php endif; ?>
		<?php if ( $plate_date !== '' ) : ?>
			<span><?php echo esc_html( $plate_date ); ?></span>
		<?php endif; ?>
	</div>
</div>
