<?php
defined( 'ABSPATH' ) || exit;

if ( class_exists( 'WB_License' ) && method_exists( 'WB_License', 'render_box' ) ) {
	echo '<div class="al-card">';
	WB_License::render_box( AL_PRODUCT );
	echo '</div>';
} else {
	echo '<div class="al-card"><p>ماژول لایسنس بارگذاری نشد.</p></div>';
}
