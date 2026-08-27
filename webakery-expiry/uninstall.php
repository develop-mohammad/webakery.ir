<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}
delete_option( 'wbe_settings' );
delete_transient( 'wbe_sales_map' );
wp_clear_scheduled_hook( 'wbe_daily_sync' );
