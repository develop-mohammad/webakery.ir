<?php
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WAP_Baget_Fields' ) ) :

/**
 * یکپارچه‌سازی فیلدهای checkout افزونه Baget (WCCP) با خروجی Hesabdar.
 */
class WAP_Baget_Fields {

	const CUSTOM_OPTION   = 'wccp_custom_fields';
	const OVERRIDE_OPTION = 'wccp_field_overrides';

	/** فیلدهای پیش‌فرض Baget که در خروجی استاندارد Hesabdar نیستند. */
	private static $builtin_extra = array(
		'billing_national_id' => 'کد ملی',
		'billing_birth_date'  => 'تاریخ تولد',
		'billing_father_name' => 'نام پدر',
		'billing_mother_name' => 'نام مادر',
	);

	/** فیلدهایی که Hesabdar/WCI از قبل در خروجی پایه دارند. */
	private static $base_export_keys = array(
		'order_number', 'first_name', 'last_name', 'email', 'phone',
		'city', 'state', 'address', 'postcode', 'payment', 'status',
		'products', 'total', 'date', 'transaction', 'customer_note',
	);

	private static $wc_native = array(
		'billing_first_name', 'billing_last_name', 'billing_company',
		'billing_country', 'billing_address_1', 'billing_address_2',
		'billing_city', 'billing_state', 'billing_postcode',
		'billing_phone', 'billing_email',
	);

	public static function is_baget_active(): bool {
		return class_exists( '\WCCP\CustomFields' );
	}

	/**
	 * @return array<string,string> meta_key => label
	 */
	public static function get_export_columns(): array {
		$cols = array();
		foreach ( self::get_field_definitions() as $key => $def ) {
			if ( in_array( $key, self::$wc_native, true ) || $key === 'order_notes' ) {
				continue;
			}
			$export_key = self::export_key_for( $key );
			if ( in_array( $export_key, self::$base_export_keys, true ) ) {
				continue;
			}
			$label = $def['label'] ?? $key;
			if ( $label !== '' ) {
				$cols[ $export_key ] = $label;
			}
		}
		return apply_filters( 'hesabdar_baget_export_columns', $cols );
	}

	/**
	 * @return array<string,array{label:string,type?:string,custom?:bool}>
	 */
	public static function get_field_definitions(): array {
		try {
			if ( self::is_baget_active() && class_exists( '\WCCP\Fields' ) ) {
				$fields = \WCCP\CustomFields::merged_with_defaults();
			} else {
				$fields = self::get_fallback_definitions();
			}
		} catch ( Throwable $e ) {
			$fields = self::get_fallback_definitions();
		}

		$out = array();
		foreach ( $fields as $key => $def ) {
			if ( ! is_array( $def ) ) {
				continue;
			}
			$out[ $key ] = array(
				'label'  => (string) ( $def['label'] ?? $key ),
				'type'   => (string) ( $def['type'] ?? 'text' ),
				'custom' => ! empty( $def['custom'] ) || ! empty( $def['user_defined'] ),
			);
		}

		return apply_filters( 'hesabdar_baget_field_definitions', $out );
	}

	private static function get_fallback_definitions(): array {
		$fields = self::$builtin_extra;
		$normalized = array();
		foreach ( $fields as $key => $label ) {
			$normalized[ $key ] = array(
				'label'  => $label,
				'type'   => 'text',
				'custom' => false,
			);
		}

		$custom = get_option( self::CUSTOM_OPTION, array() );
		if ( is_array( $custom ) ) {
			foreach ( $custom as $key => $def ) {
				if ( ! is_array( $def ) ) {
					continue;
				}
				$normalized[ $key ] = array(
					'label'  => (string) ( $def['label'] ?? $key ),
					'type'   => (string) ( $def['type'] ?? 'text' ),
					'custom' => true,
				);
			}
		}

		$overrides = get_option( self::OVERRIDE_OPTION, array() );
		if ( is_array( $overrides ) ) {
			foreach ( $overrides as $key => $ov ) {
				if ( ! isset( $normalized[ $key ] ) || ! is_array( $ov ) ) {
					continue;
				}
				if ( isset( $ov['label'] ) && $ov['label'] !== '' ) {
					$normalized[ $key ]['label'] = (string) $ov['label'];
				}
			}
		}

		return $normalized;
	}

	public static function export_key_for( string $meta_key ): string {
		return $meta_key;
	}

	public static function get_order_field_value( $order, string $meta_key ): string {
		if ( ! $order || ! is_object( $order ) ) {
			return '';
		}

		$candidates = array( '_' . $meta_key, $meta_key );

		if ( method_exists( $order, 'get_meta' ) ) {
			foreach ( $candidates as $key ) {
				$value = $order->get_meta( $key, true );
				if ( $value !== '' && $value !== null ) {
					return apply_filters( 'hesabdar_baget_order_field_value', (string) $value, $order, $meta_key );
				}
			}
		}

		if ( method_exists( $order, 'get_id' ) && $order->get_id() ) {
			$oid = $order->get_id();
			foreach ( $candidates as $key ) {
				$value = get_post_meta( $oid, $key, true );
				if ( $value !== '' && $value !== null ) {
					return apply_filters( 'hesabdar_baget_order_field_value', (string) $value, $order, $meta_key );
				}
			}
		}

		if ( method_exists( $order, 'get_meta_data' ) ) {
			foreach ( $order->get_meta_data() as $meta ) {
				$key = $meta->key;
				if ( $key === $meta_key || $key === '_' . $meta_key || ltrim( $key, '_' ) === $meta_key ) {
					$value = $meta->value;
					if ( $value !== '' && $value !== null ) {
						return apply_filters( 'hesabdar_baget_order_field_value', (string) $value, $order, $meta_key );
					}
				}
			}
		}

		return apply_filters( 'hesabdar_baget_order_field_value', '', $order, $meta_key );
	}

	/** @return array<string,string> meta_key => label */
	public static function get_table_columns(): array {
		return array();
	}

	public static function table_column_count(): int {
		return count( self::get_table_columns() );
	}

	public static function render_table_headers(): void {
		foreach ( self::get_table_columns() as $label ) {
			echo '<th>' . esc_html( $label ) . '</th>';
		}
	}

	public static function render_table_cells( $order ): void {
		$defs = self::get_field_definitions();
		foreach ( array_keys( self::get_table_columns() ) as $key ) {
			$value = self::get_order_field_value( $order, $key );
			$type  = $defs[ $key ]['type'] ?? 'text';
			$attr  = in_array( $type, array( 'tel', 'number' ), true ) ? ' style="direction:ltr;text-align:right"' : '';
			echo '<td' . $attr . '>' . esc_html( $value !== '' ? $value : '—' ) . '</td>';
		}
	}

	/**
	 * @return array<string,string>
	 */
	public static function get_order_extra_values( $order ): array {
		$values = array();
		foreach ( self::get_export_columns() as $export_key => $label ) {
			$values[ $export_key ] = self::get_order_field_value( $order, $export_key );
		}
		return $values;
	}

	/**
	 * @param array<string,mixed> $row_data
	 * @return array<string,mixed>
	 */
	public static function merge_row_data( array $row_data, $order ): array {
		return array_merge( $row_data, self::get_order_extra_values( $order ) );
	}

	/**
	 * ستون‌های پایه WCI + فیلدهای Baget.
	 *
	 * @return array<string,string>
	 */
	public static function get_merged_export_columns(): array {
		$base = array(
			'order_number'  => 'شماره سفارش',
			'first_name'    => 'نام',
			'last_name'     => 'نام خانوادگی',
			'email'         => 'ایمیل',
			'phone'         => 'شماره تماس',
			'city'          => 'شهر',
			'state'         => 'استان',
			'address'       => 'آدرس',
			'postcode'      => 'کد پستی',
			'payment'       => 'روش پرداخت',
			'status'        => 'وضعیت سفارش',
			'products'      => 'محصولات',
			'total'         => 'مبلغ کل',
			'date'          => 'تاریخ سفارش',
			'transaction'   => 'کد پیگیری',
			'customer_note' => 'یادداشت مشتری',
		);

		return array_merge( $base, self::get_export_columns() );
	}

	/**
	 * فیلدهای پر شده سفارش برای نمایش در فاکتور.
	 *
	 * @return array<string,string> label => value
	 */
	public static function get_invoice_fields( $order ): array {
		$fields = array();
		foreach ( self::get_field_definitions() as $key => $def ) {
			if ( in_array( $key, self::$wc_native, true ) || $key === 'order_notes' ) {
				continue;
			}
			$value = self::get_order_field_value( $order, $key );
			if ( $value === '' ) {
				continue;
			}
			$fields[ $def['label'] ] = $value;
		}
		return $fields;
	}
}

endif;
