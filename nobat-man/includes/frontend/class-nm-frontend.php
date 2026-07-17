<?php
defined( 'ABSPATH' ) || exit;

class NM_Frontend {

	/** @var self|null */
	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_filter( 'body_class', function ( $classes ) {
			$classes[] = 'nm-rtl';
			return $classes;
		} );
	}
}
