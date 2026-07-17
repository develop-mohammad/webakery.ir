<?php
defined( 'ABSPATH' ) || exit;
?>
<div class="nm-panel-card">
	<?php
	if ( class_exists( 'WB_License' ) ) {
		echo WB_License::render_box( NM_PRODUCT );
	} else {
		echo '<p>کلاینت لایسنس یافت نشد.</p>';
	}
	?>
</div>
