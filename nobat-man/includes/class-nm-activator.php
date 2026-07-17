<?php
defined( 'ABSPATH' ) || exit;

class NM_Activator {

	public static function activate() {
		NM_Install::create_tables();
		NM_Install::seed_defaults();

		if ( ! get_option( 'wbl_' . NM_PRODUCT . '_install_time' ) ) {
			add_option( 'wbl_' . NM_PRODUCT . '_install_time', time() );
		}

		update_option( 'nm_db_version', NM_DB_VERSION );
		flush_rewrite_rules();
	}

	public static function deactivate() {
		wp_clear_scheduled_hook( 'nm_daily_maintenance' );
		flush_rewrite_rules();
	}
}
