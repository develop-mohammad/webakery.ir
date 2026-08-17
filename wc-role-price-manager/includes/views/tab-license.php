<?php
defined( 'ABSPATH' ) || exit;

if ( class_exists( 'WB_License' ) && method_exists( 'WB_License', 'render_box' ) ) {
	echo '<div class="wrpm-card">';
	WB_License::render_box( WRPM_PRODUCT );
	echo '</div>';
} else {
	echo '<div class="wrpm-card"><p>ماژول لایسنس بارگذاری نشد.</p></div>';
}
