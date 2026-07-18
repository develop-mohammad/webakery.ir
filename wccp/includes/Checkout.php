<?php
namespace WCCP;

defined( 'ABSPATH' ) || exit;

class Checkout {

	/** @var self|null */
	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_filter( 'woocommerce_checkout_fields', array( $this, 'filter_fields' ), 1000 );
		add_filter( 'woocommerce_form_field', array( $this, 'render_choice_fields' ), 20, 4 );
		add_action( 'woocommerce_after_checkout_validation', array( $this, 'validate_choice_fields' ), 10, 2 );
		add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'save_order_meta' ) );
		add_action( 'woocommerce_admin_order_data_after_billing_address', array( $this, 'admin_display' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	public function enqueue_assets() {
		if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) {
			return;
		}
		wp_enqueue_style( 'wccp-checkout', WCCP_URL . 'assets/checkout.css', array(), WCCP_VERSION );
	}

	public function filter_fields( $fields ) {
		if ( ! is_array( $fields ) ) {
			return $fields;
		}

		$defs   = CustomFields::merged_with_defaults();
		$active = Fields::get_active_keys();

		if ( isset( $fields['billing'] ) && is_array( $fields['billing'] ) ) {
			foreach ( $fields['billing'] as $key => $cfg ) {
				if ( ! in_array( $key, $active, true ) ) {
					unset( $fields['billing'][ $key ] );
				}
			}
		}

		$priority = 10;
		foreach ( $active as $key ) {
			if ( empty( $defs[ $key ] ) ) {
				continue;
			}
			$def     = $defs[ $key ];
			$raw_type = $def['type'] ?? 'text';

			if ( 'order_comments' === $key ) {
				$fields['order']['order_comments']['label']    = $def['label'];
				$fields['order']['order_comments']['required'] = ! empty( $def['required'] );
				$fields['order']['order_comments']['priority'] = $priority;
				$priority += 10;
				continue;
			}

			$options = Fields::parse_options( $def['options'] ?? '' );
			$type    = $raw_type;

			if ( 'state' === $type ) {
				$wc_type = 'state';
			} elseif ( 'textarea' === $type ) {
				$wc_type = 'textarea';
			} elseif ( 'select' === $type ) {
				$wc_type = 'select';
			} elseif ( 'radio' === $type ) {
				$wc_type = 'wccp_radio';
			} elseif ( 'checkboxes' === $type ) {
				$wc_type = 'wccp_checkboxes';
			} elseif ( in_array( $type, array( 'text', 'tel', 'email', 'number', 'checkbox' ), true ) ) {
				$wc_type = $type;
			} else {
				$wc_type = 'text';
			}

			$field = array(
				'type'     => $wc_type,
				'label'    => $def['label'],
				'required' => ! empty( $def['required'] ),
				'class'    => array( 'form-row-wide', 'wccp-field-' . sanitize_key( $raw_type ) ),
				'priority' => $priority,
			);
			if ( ! empty( $def['placeholder'] ) ) {
				$field['placeholder'] = $def['placeholder'];
			}
			if ( in_array( $raw_type, Fields::option_types(), true ) ) {
				$field['wccp_options'] = $options;
			}
			if ( 'select' === $raw_type ) {
				$opts = array( '' => 'انتخاب کنید' );
				foreach ( $options as $opt ) {
					$opts[ $opt ] = $opt;
				}
				$field['options'] = $opts;
			}

			if ( 0 === strpos( $key, 'billing_' ) ) {
				$fields['billing'][ $key ] = isset( $fields['billing'][ $key ] )
					? array_merge( $fields['billing'][ $key ], $field )
					: $field;
			} else {
				$fields['billing'][ $key ] = $field;
			}
			$priority += 10;
		}

		return $fields;
	}

	public function render_choice_fields( $field, $key, $args, $value ) {
		$type = $args['type'] ?? '';
		if ( ! in_array( $type, array( 'wccp_radio', 'wccp_checkboxes' ), true ) ) {
			return $field;
		}

		$options = isset( $args['wccp_options'] ) && is_array( $args['wccp_options'] )
			? $args['wccp_options']
			: array();

		if ( empty( $options ) ) {
			return $field;
		}

		$required = ! empty( $args['required'] );
		$classes  = isset( $args['class'] ) ? (array) $args['class'] : array( 'form-row-wide' );
		$label    = isset( $args['label'] ) ? $args['label'] : '';

		ob_start();
		echo '<p class="form-row ' . esc_attr( implode( ' ', $classes ) ) . '" id="' . esc_attr( $key ) . '_field" data-priority="' . esc_attr( (string) ( $args['priority'] ?? '' ) ) . '">';
		echo '<label for="' . esc_attr( $key ) . '">' . esc_html( $label );
		if ( $required ) {
			echo '&nbsp;<abbr class="required" title="required">*</abbr>';
		}
		echo '</label>';

		if ( 'wccp_radio' === $type ) {
			echo '<span class="woocommerce-input-wrapper wccp-choice-list wccp-radio-list">';
			foreach ( $options as $i => $opt ) {
				$id = $key . '_' . $i;
				echo '<label class="wccp-choice wccp-radio" for="' . esc_attr( $id ) . '">';
				echo '<input type="radio" class="input-radio" name="' . esc_attr( $key ) . '" id="' . esc_attr( $id ) . '" value="' . esc_attr( $opt ) . '" ' . checked( (string) $value, (string) $opt, false ) . ( $required ? ' required' : '' ) . ' /> ';
				echo esc_html( $opt );
				echo '</label>';
			}
			echo '</span>';
		} else {
			$selected = array();
			if ( is_array( $value ) ) {
				$selected = $value;
			} elseif ( is_string( $value ) && '' !== $value ) {
				$selected = Fields::parse_options( $value );
			}
			echo '<span class="woocommerce-input-wrapper wccp-choice-list wccp-checkbox-list">';
			foreach ( $options as $i => $opt ) {
				$id = $key . '_' . $i;
				echo '<label class="wccp-choice wccp-checkbox" for="' . esc_attr( $id ) . '">';
				echo '<input type="checkbox" class="input-checkbox" name="' . esc_attr( $key ) . '[]" id="' . esc_attr( $id ) . '" value="' . esc_attr( $opt ) . '" ' . checked( in_array( $opt, $selected, true ), true, false ) . ' /> ';
				echo esc_html( $opt );
				echo '</label>';
			}
			echo '</span>';
		}

		echo '</p>';
		return ob_get_clean();
	}

	public function validate_choice_fields( $data, $errors ) {
		$defs   = CustomFields::merged_with_defaults();
		$active = Fields::get_active_keys();

		foreach ( $active as $key ) {
			if ( empty( $defs[ $key ] ) || empty( $defs[ $key ]['required'] ) ) {
				continue;
			}
			$type = $defs[ $key ]['type'] ?? 'text';
			if ( 'radio' === $type ) {
				$val = isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : ''; // phpcs:ignore
				if ( '' === $val ) {
					$errors->add( 'wccp_' . $key, sprintf( 'لطفاً «%s» را انتخاب کنید.', $defs[ $key ]['label'] ) );
				}
			}
			if ( 'checkboxes' === $type ) {
				$val = isset( $_POST[ $key ] ) ? (array) wp_unslash( $_POST[ $key ] ) : array(); // phpcs:ignore
				$val = array_filter( array_map( 'sanitize_text_field', $val ) );
				if ( empty( $val ) ) {
					$errors->add( 'wccp_' . $key, sprintf( 'لطفاً حداقل یک گزینه برای «%s» انتخاب کنید.', $defs[ $key ]['label'] ) );
				}
			}
		}
	}

	public function save_order_meta( $order_id ) {
		$defs = CustomFields::merged_with_defaults();
		foreach ( Fields::get_active_keys() as $key ) {
			if ( empty( $defs[ $key ] ) ) {
				continue;
			}
			$type = $defs[ $key ]['type'] ?? 'text';

			if ( 'checkboxes' === $type ) {
				if ( empty( $_POST[ $key ] ) || ! is_array( $_POST[ $key ] ) ) { // phpcs:ignore
					continue;
				}
				$vals = array_filter( array_map( 'sanitize_text_field', wp_unslash( (array) $_POST[ $key ] ) ) ); // phpcs:ignore
				$value = implode( '، ', $vals );
			} elseif ( ! isset( $_POST[ $key ] ) ) { // phpcs:ignore
				continue;
			} else {
				$value = sanitize_text_field( wp_unslash( $_POST[ $key ] ) ); // phpcs:ignore
			}

			if ( '' === $value ) {
				continue;
			}

			if ( ! empty( $defs[ $key ]['custom'] ) || ! empty( $defs[ $key ]['user_defined'] ) || 0 === strpos( $key, 'wccp_' ) ) {
				update_post_meta( $order_id, $key, $value );
				update_post_meta( $order_id, '_' . $key, $value );
			}
		}
	}

	public function admin_display( $order ) {
		$defs = CustomFields::merged_with_defaults();
		echo '<div class="wccp-admin-fields" style="margin-top:12px">';
		foreach ( Fields::get_active_keys() as $key ) {
			if ( empty( $defs[ $key ]['custom'] ) && empty( $defs[ $key ]['user_defined'] ) && 0 !== strpos( $key, 'wccp_' ) ) {
				continue;
			}
			$val = $order->get_meta( $key );
			if ( $val === '' ) {
				$val = $order->get_meta( '_' . $key );
			}
			if ( $val === '' ) {
				continue;
			}
			echo '<p><strong>' . esc_html( $defs[ $key ]['label'] ) . ':</strong> ' . esc_html( (string) $val ) . '</p>';
		}
		echo '</div>';
	}
}
