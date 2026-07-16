<?php
/**
 * Uninstall cleanup.
 *
 * @package WebakerySpeed
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'webakery_speed_settings' );
delete_option( 'webakery_speed_last_scan' );
delete_option( 'webakery_speed_fix_status' );
