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

	/** @return string[] */
	public static function allowed_types() {
		return array( 'text', 'tel', 'email', 'number', 'textarea', 'select', 'radio', 'checkboxes', 'state', 'info', 'consent' );
	}

	/** @return string[] */
	public static function option_types() {
		return array( 'select', 'radio', 'checkboxes' );
	}

	/** فیلدهایی که فقط نمایش داده می‌شوند و ورودی کاربر ندارند */
	public static function display_only_types() {
		return array( 'info' );
	}

	/** فیلدهایی که متن اطلاع‌رسانی (content) دارند */
	public static function content_types() {
		return array( 'info', 'consent' );
	}

	/**
	 * @param string|array $options
	 * @return string[]
	 */
	public static function parse_options( $options ) {
		if ( is_array( $options ) ) {
			return array_values( array_filter( array_map( 'trim', array_map( 'strval', $options ) ) ) );
		}
		$raw = (string) $options;
		if ( '' === $raw ) {
			return array();
		}
		$parts = preg_split( '/[\r\n,]+/', $raw );
		return array_values( array_filter( array_map( 'trim', (array) $parts ) ) );
	}

	/** @return string */
	public static function normalize_options_string( $options ) {
		return implode( "\n", self::parse_options( $options ) );
	}

	/** @return string */
	/**
	 * رندر یک فیلد در فرم‌های مستقل (لینک پرداخت / shortcode).
	 *
	 * @param string $key
	 * @param array  $def
	 */
	public static function render_standalone_field( $key, $def ) {
		$type     = $def['type'] ?? 'text';
		$required = ! empty( $def['required'] );
		$req_attr = $required ? ' required' : '';
		$label    = $def['label'] ?? $key;

		echo '<div class="wccp-field wccp-field-' . esc_attr( sanitize_key( $type ) ) . '">';

		if ( 'info' === $type || 'consent' === $type ) {
			$content = (string) ( $def['content'] ?? '' );
			$agree   = self::parse_options( $def['options'] ?? '' );
			$agree   = $agree[0] ?? 'رضایت دارم';
			echo '<div class="wccp-info-box' . ( 'consent' === $type ? ' wccp-consent-box' : '' ) . '">';
			if ( '' !== trim( $label ) ) {
				echo '<strong class="wccp-info-title">' . esc_html( $label ) . '</strong>';
			}
			if ( '' !== trim( $content ) ) {
				echo '<div class="wccp-info-text">' . wp_kses_post( wpautop( $content ) ) . '</div>';
			}
			if ( 'consent' === $type ) {
				$id = $key . '_agree';
				echo '<label class="wccp-consent-check" for="' . esc_attr( $id ) . '">';
				echo '<input type="checkbox" name="' . esc_attr( $key ) . '" id="' . esc_attr( $id ) . '" value="' . esc_attr( $agree ) . '"' . $req_attr . ' /> ';
				echo '<span>' . esc_html( $agree ) . '</span>';
				echo '</label>';
			}
			echo '</div></div>';
			return;
		}

		echo '<span class="wccp-field-label">' . esc_html( $label );
		if ( $required ) {
			echo ' <abbr class="required" title="required">*</abbr>';
		}
		echo '</span>';

		if ( 'textarea' === $type ) {
			echo '<textarea name="' . esc_attr( $key ) . '"' . $req_attr . '></textarea>';
		} elseif ( 'select' === $type ) {
			$options = self::parse_options( $def['options'] ?? '' );
			echo '<select name="' . esc_attr( $key ) . '"' . $req_attr . '>';
			echo '<option value="">' . esc_html( 'انتخاب کنید' ) . '</option>';
			foreach ( $options as $opt ) {
				echo '<option value="' . esc_attr( $opt ) . '">' . esc_html( $opt ) . '</option>';
			}
			echo '</select>';
		} elseif ( 'radio' === $type ) {
			$options = self::parse_options( $def['options'] ?? '' );
			echo '<span class="wccp-choice-list wccp-radio-list">';
			foreach ( $options as $i => $opt ) {
				$id = $key . '_' . $i;
				echo '<label class="wccp-choice wccp-radio" for="' . esc_attr( $id ) . '">';
				echo '<input type="radio" name="' . esc_attr( $key ) . '" id="' . esc_attr( $id ) . '" value="' . esc_attr( $opt ) . '"' . $req_attr . ' /> ';
				echo esc_html( $opt );
				echo '</label>';
			}
			echo '</span>';
		} elseif ( 'checkboxes' === $type ) {
			$options = self::parse_options( $def['options'] ?? '' );
			echo '<span class="wccp-choice-list wccp-checkbox-list">';
			foreach ( $options as $i => $opt ) {
				$id = $key . '_' . $i;
				echo '<label class="wccp-choice wccp-checkbox" for="' . esc_attr( $id ) . '">';
				echo '<input type="checkbox" name="' . esc_attr( $key ) . '[]" id="' . esc_attr( $id ) . '" value="' . esc_attr( $opt ) . '" /> ';
				echo esc_html( $opt );
				echo '</label>';
			}
			echo '</span>';
		} else {
			$html_type = in_array( $type, array( 'email', 'tel', 'number' ), true ) ? $type : 'text';
			echo '<input type="' . esc_attr( $html_type ) . '" name="' . esc_attr( $key ) . '"' . $req_attr;
			if ( ! empty( $def['placeholder'] ) ) {
				echo ' placeholder="' . esc_attr( $def['placeholder'] ) . '"';
			}
			echo ' />';
		}

		echo '</div>';
	}

	public static function type_label( $type ) {
		$labels = array(
			'text'       => 'متنی',
			'textarea'   => 'چندخطی',
			'tel'        => 'تلفن',
			'email'      => 'ایمیل',
			'number'     => 'عدد',
			'select'     => 'انتخابی',
			'radio'      => 'رادیو',
			'checkboxes' => 'چندگزینه‌ای',
			'state'      => 'استان',
			'info'       => 'متن ساده',
			'consent'    => 'رضایت‌نامه',
		);
		return $labels[ $type ] ?? (string) $type;
	}

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
			$type = sanitize_key( $def['type'] ?? 'text' );
			if ( ! in_array( $type, self::allowed_types(), true ) ) {
				$type = 'text';
			}
			$clean_custom[ $key ] = array(
				'label'       => sanitize_text_field( $def['label'] ?? $key ),
				'type'        => $type,
				'required'    => ( 'info' === $type ) ? false : ! empty( $def['required'] ),
				'placeholder' => sanitize_text_field( $def['placeholder'] ?? '' ),
				'options'     => self::normalize_options_string( $def['options'] ?? '' ),
				'content'     => in_array( $type, self::content_types(), true ) ? sanitize_textarea_field( $def['content'] ?? '' ) : '',
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
