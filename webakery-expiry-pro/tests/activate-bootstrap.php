<?php
/**
 * شبیه‌سازی فعال‌سازی وردپرس: include فایل اصلی + هوک activate، بدون plugins_loaded.
 */
error_reporting( E_ALL );
define( 'ABSPATH', sys_get_temp_dir() . '/' );

function plugin_dir_path( $file ) {
	return dirname( $file ) . '/';
}
function plugin_dir_url( $file ) {
	return 'http://example.test/wp-content/plugins/webakery-expiry/';
}
function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
	return true;
}
function register_activation_hook( $file, $callback ) {
	$GLOBALS['wbe_activate_cb'] = $callback;
}
function register_deactivation_hook( $file, $callback ) {
	return true;
}
function get_option( $key, $default = false ) {
	return $default;
}
function add_option( $key, $value, $deprecated = '', $autoload = 'yes' ) {
	$GLOBALS['wbe_opts'][ $key ] = $value;
	return true;
}
function wp_next_scheduled( $hook = '' ) {
	return false;
}
function wp_schedule_event( $timestamp = 0, $recurrence = '', $hook = '' ) {
	$GLOBALS['wbe_cron'] = $hook;
	return true;
}

$GLOBALS['wbe_opts'] = array();
require dirname( __DIR__ ) . '/webakery-expiry.php';

if ( empty( $GLOBALS['wbe_activate_cb'] ) ) {
	fwrite( STDERR, "FAIL: activation hook not registered\n" );
	exit( 1 );
}
call_user_func( $GLOBALS['wbe_activate_cb'] );
if ( empty( $GLOBALS['wbe_opts']['wbe_settings'] ) ) {
	fwrite( STDERR, "FAIL: settings not saved\n" );
	exit( 1 );
}
if ( empty( $GLOBALS['wbe_cron'] ) ) {
	fwrite( STDERR, "FAIL: cron not scheduled\n" );
	exit( 1 );
}
echo "OK — bootstrap activate without plugins_loaded\n";
exit( 0 );
