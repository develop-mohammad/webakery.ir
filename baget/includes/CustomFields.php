<?php
namespace WCCP;

defined( 'ABSPATH' ) || exit;

class CustomFields {

	/** @return array<string,array> */
	public static function overrides() {
		$ov = get_option( Fields::OVERRIDE_OPTION, array() );
		return is_array( $ov ) ? $ov : array();
	}

	/**
	 * ذخیره/به‌روزرسانی override یک فیلد پیش‌فرض.
	 *
	 * @param string               $key
	 * @param array<string,mixed>  $data
	 */
	public static function save_override( $key, array $data ) {
		$key = sanitize_key( (string) $key );
		if ( ! $key || ! isset( Fields::defaults()[ $key ] ) ) {
			return new \WP_Error( 'field', 'فقط فیلدهای پیش‌فرض override می‌شوند.' );
		}
		$all = self::overrides();
		$cur = isset( $all[ $key ] ) && is_array( $all[ $key ] ) ? $all[ $key ] : array();
		if ( array_key_exists( 'label', $data ) ) {
			$cur['label'] = sanitize_text_field( (string) $data['label'] );
		}
		if ( array_key_exists( 'required', $data ) ) {
			$cur['required'] = ! empty( $data['required'] ) ? 1 : 0;
		}
		if ( array_key_exists( 'enabled', $data ) ) {
			$cur['enabled'] = ! empty( $data['enabled'] ) ? 1 : 0;
		}
		$all[ $key ] = $cur;
		update_option( Fields::OVERRIDE_OPTION, $all, false );
		return $cur;
	}

	/** @return string[] کلیدهای پیش‌فرض مخفی‌شده */
	public static function hidden_default_keys() {
		$out = array();
		foreach ( self::overrides() as $key => $ov ) {
			if ( ! is_array( $ov ) || ! isset( Fields::defaults()[ $key ] ) ) {
				continue;
			}
			if ( array_key_exists( 'enabled', $ov ) && empty( $ov['enabled'] ) ) {
				$out[] = sanitize_key( (string) $key );
			}
		}
		return $out;
	}

	/**
	 * ادغام فیلدهای پیش‌فرض + سفارشی + override برچسب/اجباری.
	 *
	 * @param bool $include_hidden اگر true، فیلدهای پیش‌فرض حذف‌شده هم برمی‌گردند.
	 * @return array<string,array>
	 */
	public static function merged_with_defaults( $include_hidden = false ) {
		$fields = Fields::defaults();

		$custom = get_option( Fields::CUSTOM_OPTION, array() );
		if ( is_array( $custom ) ) {
			foreach ( $custom as $key => $def ) {
				if ( ! is_array( $def ) ) {
					continue;
				}
				$key  = sanitize_key( (string) $key );
				$type = (string) ( $def['type'] ?? 'text' );
				$fields[ $key ] = array(
					'label'        => (string) ( $def['label'] ?? $key ),
					'type'         => $type,
					'required'     => ( 'info' === $type ) ? false : ! empty( $def['required'] ),
					'placeholder'  => (string) ( $def['placeholder'] ?? '' ),
					'options'      => (string) ( $def['options'] ?? '' ),
					'content'      => (string) ( $def['content'] ?? '' ),
					'custom'       => true,
					'user_defined' => true,
					'icon'         => 'custom',
				);
			}
		}

		$overrides = self::overrides();
		foreach ( $overrides as $key => $ov ) {
			if ( ! isset( $fields[ $key ] ) || ! is_array( $ov ) ) {
				continue;
			}
			if ( isset( $ov['label'] ) && $ov['label'] !== '' ) {
				$fields[ $key ]['label'] = (string) $ov['label'];
			}
			if ( array_key_exists( 'required', $ov ) ) {
				$fields[ $key ]['required'] = ! empty( $ov['required'] );
			}
			if ( array_key_exists( 'enabled', $ov ) && empty( $ov['enabled'] ) ) {
				$fields[ $key ]['hidden'] = true;
				if ( ! $include_hidden && isset( Fields::defaults()[ $key ] ) && empty( $fields[ $key ]['custom'] ) ) {
					unset( $fields[ $key ] );
				}
			}
		}

		return apply_filters( 'wccp_merged_fields', $fields );
	}
}
