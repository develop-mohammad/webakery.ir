<?php
namespace WCCP;

defined( 'ABSPATH' ) || exit;

class Ajax {

	/** @var self|null */
	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'wp_ajax_wccp_save_fields', array( $this, 'save_fields' ) );
		add_action( 'wp_ajax_wccp_create_field', array( $this, 'create_field' ) );
		add_action( 'wp_ajax_wccp_update_field', array( $this, 'update_field' ) );
		add_action( 'wp_ajax_wccp_delete_field', array( $this, 'delete_field' ) );
		add_action( 'wp_ajax_wccp_save_product_fields', array( $this, 'save_product_fields' ) );
	}

	private function guard() {
		if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'دسترسی غیرمجاز' ), 403 );
		}
		$nonce = isset( $_REQUEST['nonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'wccp_admin' ) ) {
			wp_send_json_error( array( 'message' => 'نشست منقضی شده — صفحه را رفرش کنید.' ), 403 );
		}
	}

	/** ذخیره ترتیب/فعال بودن فیلدهای سراسری */
	public function save_fields() {
		$this->guard();

		$raw = wp_unslash( $_POST );
		$active = array();
		if ( ! empty( $raw['active'] ) ) {
			if ( is_string( $raw['active'] ) ) {
				$decoded = json_decode( $raw['active'], true );
				$active  = is_array( $decoded ) ? $decoded : array_filter( array_map( 'trim', explode( ',', $raw['active'] ) ) );
			} elseif ( is_array( $raw['active'] ) ) {
				$active = $raw['active'];
			}
		}

		$custom = array();
		if ( ! empty( $raw['custom'] ) ) {
			$decoded = is_string( $raw['custom'] ) ? json_decode( $raw['custom'], true ) : $raw['custom'];
			$custom  = is_array( $decoded ) ? $decoded : array();
		} else {
			$custom = get_option( Fields::CUSTOM_OPTION, array() );
			if ( ! is_array( $custom ) ) {
				$custom = array();
			}
		}

		$overrides = array();
		if ( ! empty( $raw['overrides'] ) ) {
			$decoded   = is_string( $raw['overrides'] ) ? json_decode( $raw['overrides'], true ) : $raw['overrides'];
			$overrides = is_array( $decoded ) ? $decoded : array();
		} else {
			$overrides = get_option( Fields::OVERRIDE_OPTION, array() );
			if ( ! is_array( $overrides ) ) {
				$overrides = array();
			}
		}

		$saved = Fields::save_state( $active, $custom, $overrides );

		wp_send_json_success(
			array(
				'message'   => 'فیلدها ذخیره شد.',
				'active'    => $saved['active'],
				'available' => Fields::get_available_keys(),
				'fields'    => CustomFields::merged_with_defaults(),
			)
		);
	}

	public function create_field() {
		$this->guard();
		$label = sanitize_text_field( wp_unslash( $_POST['label'] ?? 'فیلد جدید' ) );
		$type  = sanitize_key( wp_unslash( $_POST['type'] ?? 'text' ) );
		if ( ! in_array( $type, Fields::allowed_types(), true ) ) {
			$type = 'text';
		}
		$options = Fields::normalize_options_string( wp_unslash( $_POST['options_text'] ?? '' ) );
		if ( in_array( $type, Fields::option_types(), true ) && '' === trim( $options ) ) {
			wp_send_json_error( array( 'message' => 'برای این نوع فیلد حداقل یک گزینه وارد کنید.' ) );
		}

		$key   = 'wccp_field_' . strtolower( wp_generate_password( 8, false, false ) );
		$custom = get_option( Fields::CUSTOM_OPTION, array() );
		if ( ! is_array( $custom ) ) {
			$custom = array();
		}
		$base = $key;
		$i    = 1;
		while ( isset( $custom[ $key ] ) || isset( Fields::defaults()[ $key ] ) ) {
			$key = $base . '_' . $i;
			$i++;
		}
		$custom[ $key ] = array(
			'label'        => $label,
			'type'         => $type,
			'required'     => ! empty( $_POST['required'] ),
			'options'      => $options,
			'custom'       => true,
			'user_defined' => true,
		);
		update_option( Fields::CUSTOM_OPTION, $custom, false );

		$active   = Fields::get_active_keys();
		$active[] = $key;
		update_option( Fields::ACTIVE_OPTION, array_values( array_unique( $active ) ), false );

		wp_send_json_success(
			array(
				'message' => 'فیلد سفارشی ساخته شد.',
				'key'     => $key,
				'field'   => $custom[ $key ],
				'active'  => Fields::get_active_keys(),
				'fields'  => CustomFields::merged_with_defaults(),
			)
		);
	}

	public function update_field() {
		$this->guard();
		$key = sanitize_key( wp_unslash( $_POST['key'] ?? '' ) );
		if ( ! $key ) {
			wp_send_json_error( array( 'message' => 'فیلد نامعتبر است.' ) );
		}
		$custom = get_option( Fields::CUSTOM_OPTION, array() );
		if ( ! is_array( $custom ) || ! isset( $custom[ $key ] ) ) {
			wp_send_json_error( array( 'message' => 'فقط فیلدهای سفارشی قابل ویرایش هستند.' ) );
		}

		$label = sanitize_text_field( wp_unslash( $_POST['label'] ?? $custom[ $key ]['label'] ?? '' ) );
		$type  = sanitize_key( wp_unslash( $_POST['type'] ?? $custom[ $key ]['type'] ?? 'text' ) );
		if ( ! in_array( $type, Fields::allowed_types(), true ) ) {
			$type = $custom[ $key ]['type'] ?? 'text';
		}
		$options = Fields::normalize_options_string( wp_unslash( $_POST['options_text'] ?? $custom[ $key ]['options'] ?? '' ) );
		if ( in_array( $type, Fields::option_types(), true ) && '' === trim( $options ) ) {
			wp_send_json_error( array( 'message' => 'برای این نوع فیلد حداقل یک گزینه وارد کنید.' ) );
		}

		$custom[ $key ] = array(
			'label'        => $label ?: $key,
			'type'         => $type,
			'required'     => ! empty( $_POST['required'] ),
			'options'      => $options,
			'custom'       => true,
			'user_defined' => true,
		);
		update_option( Fields::CUSTOM_OPTION, $custom, false );

		wp_send_json_success(
			array(
				'message' => 'فیلد به‌روز شد.',
				'key'     => $key,
				'field'   => $custom[ $key ],
				'fields'  => CustomFields::merged_with_defaults(),
			)
		);
	}

	public function delete_field() {
		$this->guard();
		$key = sanitize_key( wp_unslash( $_POST['key'] ?? '' ) );
		if ( ! $key || isset( Fields::defaults()[ $key ] ) ) {
			wp_send_json_error( array( 'message' => 'فقط فیلد سفارشی قابل حذف است.' ) );
		}
		$custom = get_option( Fields::CUSTOM_OPTION, array() );
		if ( is_array( $custom ) && isset( $custom[ $key ] ) ) {
			unset( $custom[ $key ] );
			update_option( Fields::CUSTOM_OPTION, $custom, false );
		}
		$active = array_values( array_diff( Fields::get_active_keys(), array( $key ) ) );
		update_option( Fields::ACTIVE_OPTION, $active, false );

		wp_send_json_success(
			array(
				'message' => 'فیلد حذف شد.',
				'active'  => $active,
				'fields'  => CustomFields::merged_with_defaults(),
			)
		);
	}

	/** ذخیره فیلدهای یک محصول آنلاین */
	public function save_product_fields() {
		$this->guard();
		$product_id = (int) ( $_POST['product_id'] ?? 0 );
		if ( ! $product_id || 'wccp_product' !== get_post_type( $product_id ) ) {
			wp_send_json_error( array( 'message' => 'محصول نامعتبر' ) );
		}

		$active = array();
		if ( ! empty( $_POST['active'] ) ) {
			$decoded = is_string( $_POST['active'] ) ? json_decode( wp_unslash( $_POST['active'] ), true ) : wp_unslash( $_POST['active'] );
			$active  = is_array( $decoded ) ? array_map( 'sanitize_key', $decoded ) : array();
		}
		update_post_meta( $product_id, '_wccp_active_fields', array_values( array_unique( $active ) ) );

		wp_send_json_success(
			array(
				'message' => 'فیلدهای محصول ذخیره شد.',
				'active'  => $active,
			)
		);
	}
}
