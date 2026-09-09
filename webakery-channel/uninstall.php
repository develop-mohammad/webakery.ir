<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}
delete_option( 'wbcn_settings' );
delete_option( 'wbcn_log' );
delete_option( 'wbcn_daily_used' );
delete_option( 'wbcn_update_offset' );
