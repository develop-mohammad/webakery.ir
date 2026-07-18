<?php
namespace WCCP;

defined( 'ABSPATH' ) || exit;

/**
 * فیلدهای پیش‌فرض ووکامرس + داخلی Baget.
 */
class Fields {

	const ACTIVE_OPTION   = 'wccp_active_fields';
	const OVERRIDE_OPTION = 'wccp_field_overrides';
	const CUSTOM_OPTION   = 'wccp_custom_fields';

	/** @return array<string,array> */
	public static function defaults() {
		return array(
			'billing_first_name'  => array( 'label' => 'نام', 'type' => 'text', 'required' => true, 'icon' => 'user' ),
			'billing_last_name'   => array( 'label' => 'نام خانوادگی', 'type' => 'text', 'required' => true, 'icon' => 'user' ),
			'billing_phone'       => array( 'label' => 'شماره تماس', 'type' => 'tel', 'required' => true, 'icon' => 'phone' ),
			'billing_email'       => array( 'label' => 'ایمیل', 'type' => 'email', 'required' => false, 'icon' => 'mail' ),
			'billing_state'       => array( 'label' => 'استان', 'type' => 'state', 'required' => false, 'icon' => 'map' ),
			'billing_city'        => array( 'label' => 'شهر', 'type' => 'text', 'required' => false, 'icon' => 'map' ),
			'billing_address_1'   => array( 'label' => 'آدرس', 'type' => 'textarea', 'required' => false, 'icon' => 'home' ),
			'billing_postcode'    => array( 'label' => 'کد پستی', 'type' => 'text', 'required' => false, 'icon' => 'pin' ),
			'billing_company'     => array( 'label' => 'شرکت', 'type' => 'text', 'required' => false, 'icon' => 'building' ),
			'billing_national_id' => array( 'label' => 'کد ملی', 'type' => 'text', 'required' => false, 'icon' => 'id' ),
			'billing_birth_date'  => array( 'label' => 'تاریخ تولد', 'type' => 'text', 'required' => false, 'icon' => 'calendar' ),
			'billing_father_name' => array( 'label' => 'نام پدر', 'type' => 'text', 'required' => false, 'icon' => 'user' ),
			'billing_mother_name' => array( 'label' => 'نام مادر', 'type' => 'text', 'required' => false, 'icon' => 'user' ),
			'order_comments'      => array( 'label' => 'یادداشت سفارش', 'type' => 'textarea', 'required' => false, 'icon' => 'note' ),
		);
	}

	/** ترتیب پیش‌فرض فیلدهای فعال */
	public static function default_active() {
		return array(
			'billing_first_name',
			'billing_last_name',
			'billing_phone',
			'billing_state',
			'billing_city',
		);
	}

	/** @return array<string,array> */
	public static function all_definitions() {
		return CustomFields::merged_with_defaults();
	}

	/** @return string[] */
	public static function get_active_keys() {
		$active = get_option( self::ACTIVE_OPTION, null );
		if ( ! is_array( $active ) ) {
			$active = self::default_active();
			update_option( self::ACTIVE_OPTION, $active, false );
		}
		$active = array_values( array_unique( array_map( 'strval', $active ) ) );
		$defs   = self::all_definitions();
		$out    = array();
		foreach ( $active as $key ) {
			if ( isset( $defs[ $key ] ) ) {
				$out[] = $key;
			}
		}
		return $out;
	}

	/** @return string[] */
	public static function get_available_keys() {
		$active = self::get_active_keys();
		$out    = array();
		foreach ( array_keys( self::all_definitions() ) as $key ) {
			if ( ! in_array( $key, $active, true ) ) {
				$out[] = $key;
			}
		}
		return $out;
	}

	/**
	 * ذخیره کامل وضعیت فیلدها.
	 *
	 * @param string[]             $active
	 * @param array<string,array>  $custom
	 * @param array<string,array>  $overrides
	 */
	public static function save_state( array $active, array $custom = array(), array $overrides = array() ) {
		$defs_default = self::defaults();
		$clean_custom = array();
		foreach ( $custom as $key => $def ) {
			$key = sanitize_key( (string) $key );
			if ( ! $key || isset( $defs_default[ $key ] ) || ! is_array( $def ) ) {
				continue;
			}
			if ( 0 !== strpos( $key, 'billing_' ) && 0 !== strpos( $key, 'wccp_' ) ) {
				$key = 'wccp_' . $key;
			}
			$clean_custom[ $key ] = array(
				'label'       => sanitize_text_field( $def['label'] ?? $key ),
				'type'        => sanitize_key( $def['type'] ?? 'text' ),
				'required'    => ! empty( $def['required'] ),
				'placeholder' => sanitize_text_field( $def['placeholder'] ?? '' ),
				'options'     => sanitize_text_field( $def['options'] ?? '' ),
				'custom'      => true,
				'user_defined'=> true,
			);
		}
		update_option( self::CUSTOM_OPTION, $clean_custom, false );

		$clean_ov = array();
		foreach ( $overrides as $key => $ov ) {
			$key = sanitize_key( (string) $key );
			if ( ! $key || ! is_array( $ov ) ) {
				continue;
			}
			$clean_ov[ $key ] = array(
				'label'    => sanitize_text_field( $ov['label'] ?? '' ),
				'required' => ! empty( $ov['required'] ),
				'enabled'  => isset( $ov['enabled'] ) ? (int) ! empty( $ov['enabled'] ) : 1,
			);
		}
		update_option( self::OVERRIDE_OPTION, $clean_ov, false );

		$all_keys = array_merge( array_keys( self::defaults() ), array_keys( $clean_custom ) );
		$clean_active = array();
		foreach ( $active as $key ) {
			$key = sanitize_key( (string) $key );
			if ( $key && in_array( $key, $all_keys, true ) ) {
				$clean_active[] = $key;
			}
		}
		$clean_active = array_values( array_unique( $clean_active ) );
		update_option( self::ACTIVE_OPTION, $clean_active, false );

		return array(
			'active'    => $clean_active,
			'custom'    => $clean_custom,
			'overrides' => $clean_ov,
		);
	}
}
