<?php
namespace WCCP;

defined( 'ABSPATH' ) || exit;

class CustomFields {

	/**
	 * ادغام فیلدهای پیش‌فرض + سفارشی + override برچسب/اجباری.
	 *
	 * @return array<string,array>
	 */
	public static function merged_with_defaults() {
		$fields = Fields::defaults();

		$custom = get_option( Fields::CUSTOM_OPTION, array() );
		if ( is_array( $custom ) ) {
			foreach ( $custom as $key => $def ) {
				if ( ! is_array( $def ) ) {
					continue;
				}
				$key = sanitize_key( (string) $key );
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

		$overrides = get_option( Fields::OVERRIDE_OPTION, array() );
		if ( is_array( $overrides ) ) {
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
			}
		}

		return apply_filters( 'wccp_merged_fields', $fields );
	}
}
