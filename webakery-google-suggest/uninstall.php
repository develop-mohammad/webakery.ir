<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}
delete_option( 'wbgs_settings' );
delete_option( 'wbgs_reports' );
