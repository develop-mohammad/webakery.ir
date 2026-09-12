<?php
defined( 'ABSPATH' ) || exit;
$nck_logo_url = class_exists( 'NCK_Settings' ) ? NCK_Settings::logo_url( 'medium' ) : '';
$nck_logo_alt = class_exists( 'NCK_Settings' ) ? (string) NCK_Settings::get( 'org_name', 'نهال' ) : 'نهال';
?>
<?php if ( $nck_logo_url ) : ?>
	<div class="nck-mark has-logo">
		<img src="<?php echo esc_url( $nck_logo_url ); ?>" alt="<?php echo esc_attr( $nck_logo_alt ); ?>" />
	</div>
<?php else : ?>
	<div class="nck-mark" aria-hidden="true">
		<svg viewBox="0 0 48 48" width="42" height="42" focusable="false">
			<path fill="currentColor" d="M24 4c1.2 6 3 10 8 14-6 1-10 4-12 10-2-6-6-9-12-10 5-4 6.8-8 8-14 2 5 4 8 8 10z"/>
			<path fill="currentColor" opacity=".55" d="M24 28c2 6 5 10 12 14-8-1-14 1-16 8-2-7-8-9-16-8 7-4 10-8 12-14 2 4 4 6 8 8z"/>
		</svg>
	</div>
<?php endif; ?>
