<?php
namespace WCCP;

defined( 'ABSPATH' ) || exit;

class Autoload {
	public static function register() {
		spl_autoload_register( array( __CLASS__, 'load' ) );
	}

	public static function load( $class ) {
		if ( 0 !== strpos( $class, __NAMESPACE__ . '\\' ) ) {
			return;
		}
		$rel  = str_replace( __NAMESPACE__ . '\\', '', $class );
		$rel  = str_replace( '\\', '/', $rel );
		$file = WCCP_PATH . 'includes/' . $rel . '.php';
		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
}
