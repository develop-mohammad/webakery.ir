<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}
delete_option( 'wbfs_settings' );
delete_transient( 'wbfs_detected_fonts' );
