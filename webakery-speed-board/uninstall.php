<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}
delete_option( 'wbsb_gsc' );
delete_option( 'wbsb_last_scan' );
delete_option( 'wbsb_done_steps' );
