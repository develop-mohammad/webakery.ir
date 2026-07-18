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
		add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'save_order_meta' ) );
		add_action( 'woocommerce_admin_order_data_after_billing_address', array( $this, 'admin_display' ) );
	}

	public function filter_fields( $fields ) {
		if ( ! is_array( $fields ) ) {
			return $fields;
		}

		$defs   = CustomFields::merged_with_defaults();
		$active = Fields::get_active_keys();

		// مخفی‌سازی فیلدهای billing غیر فعال
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
			$def = $defs[ $key ];

			if ( 'order_comments' === $key ) {
				$fields['order']['order_comments']['label']    = $def['label'];
				$fields['order']['order_comments']['required'] = ! empty( $def['required'] );
				$fields['order']['order_comments']['priority'] = $priority;
				$priority += 10;
				continue;
			}

			$type = $def['type'] ?? 'text';
			if ( 'state' === $type ) {
				$type = 'state';
			} elseif ( 'textarea' === $type ) {
				$type = 'textarea';
			} elseif ( ! in_array( $type, array( 'text', 'tel', 'email', 'number', 'select', 'checkbox' ), true ) ) {
				$type = 'text';
			}

			$field = array(
				'type'     => $type,
				'label'    => $def['label'],
				'required' => ! empty( $def['required'] ),
				'class'    => array( 'form-row-wide' ),
				'priority' => $priority,
			);
			if ( ! empty( $def['placeholder'] ) ) {
				$field['placeholder'] = $def['placeholder'];
			}
			if ( 'select' === $type && ! empty( $def['options'] ) ) {
				$opts = array( '' => 'انتخاب کنید' );
				foreach ( array_map( 'trim', explode( ',', $def['options'] ) ) as $opt ) {
					if ( $opt !== '' ) {
						$opts[ $opt ] = $opt;
					}
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

	public function save_order_meta( $order_id ) {
		$defs = CustomFields::merged_with_defaults();
		foreach ( Fields::get_active_keys() as $key ) {
			if ( empty( $defs[ $key ] ) || empty( $defs[ $key ]['custom'] ) && isset( Fields::defaults()[ $key ] ) && 0 === strpos( $key, 'billing_' ) ) {
				// فیلدهای native ووکامرس خودش ذخیره می‌شود؛ سفارشی‌ها را نگه دار
			}
			if ( empty( $_POST[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
				continue;
			}
			$value = sanitize_text_field( wp_unslash( $_POST[ $key ] ) ); // phpcs:ignore
			update_post_meta( $order_id, $key, $value );
			update_post_meta( $order_id, '_' . $key, $value );
		}
	}

	public function admin_display( $order ) {
		$defs = CustomFields::merged_with_defaults();
		echo '<div class="wccp-admin-fields" style="margin-top:12px">';
		foreach ( Fields::get_active_keys() as $key ) {
			if ( empty( $defs[ $key ]['custom'] ) && empty( $defs[ $key ]['user_defined'] ) ) {
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
