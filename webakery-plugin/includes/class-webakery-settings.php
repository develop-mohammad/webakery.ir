<?php
/**
 * Plugin settings.
 *
 * @package Webakery
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings API wrapper.
 */
class Webakery_Settings {

	const OPTION_KEY = 'webakery_settings';

	/**
	 * Hook registrations.
	 */
	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
	}

	/**
	 * Register settings.
	 */
	public static function register() {
		register_setting(
			'webakery_settings_group',
			self::OPTION_KEY,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => array(),
			)
		);
	}

	/**
	 * Get all settings with defaults.
	 *
	 * @return array
	 */
	public static function get() {
		$defaults = array(
			'store_name'    => 'Webakery',
			'phone'         => '',
			'whatsapp'      => '',
			'address'       => '',
			'currency'      => 'تومان',
			'hours_weekday' => '۸ صبح تا ۹ شب',
			'hours_friday'  => '۹ صبح تا ۲ بعدازظهر',
			'order_email'   => get_option( 'admin_email' ),
			'intro'         => __( 'نان تازه، شیرینی خانگی و سفارش روز.', 'webakery' ),
		);

		$saved = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}

		return wp_parse_args( $saved, $defaults );
	}

	/**
	 * Get one setting.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Fallback.
	 * @return mixed
	 */
	public static function get_one( $key, $default = '' ) {
		$settings = self::get();
		return isset( $settings[ $key ] ) ? $settings[ $key ] : $default;
	}

	/**
	 * Sanitize settings array.
	 *
	 * @param array $input Raw input.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$input = is_array( $input ) ? $input : array();

		return array(
			'store_name'    => sanitize_text_field( $input['store_name'] ?? '' ),
			'phone'         => sanitize_text_field( $input['phone'] ?? '' ),
			'whatsapp'      => preg_replace( '/[^0-9+]/', '', $input['whatsapp'] ?? '' ),
			'address'       => sanitize_textarea_field( $input['address'] ?? '' ),
			'currency'      => sanitize_text_field( $input['currency'] ?? 'تومان' ),
			'hours_weekday' => sanitize_text_field( $input['hours_weekday'] ?? '' ),
			'hours_friday'  => sanitize_text_field( $input['hours_friday'] ?? '' ),
			'order_email'   => sanitize_email( $input['order_email'] ?? '' ),
			'intro'         => sanitize_textarea_field( $input['intro'] ?? '' ),
		);
	}
}
