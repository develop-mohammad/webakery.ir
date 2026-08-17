<?php
defined( 'ABSPATH' ) || exit;

if ( class_exists( 'WB_License' ) ) {
	echo WB_License::render_box( WDP_PRODUCT ); // phpcs:ignore WordPress.Security.EscapeOutput
} else {
	echo '<p>کلاینت لایسنس در دسترس نیست.</p>';
}
