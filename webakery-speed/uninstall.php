<?php
/**
 * Uninstall cleanup for WebAkery Speed.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'wbfs_settings' );
delete_option( 'wbsb_gsc' );
delete_option( 'wbsb_last_scan' );
delete_option( 'wbsb_done_steps' );
delete_option( 'wbs_cwv_settings' );
delete_option( 'wbs_cwv_last' );
delete_option( 'wbs_autofix' );
delete_transient( 'wbfs_detected_fonts' );
