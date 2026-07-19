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
		add_action( 'wp_ajax_wccp_restore_field', array( $this, 'restore_field' ) );
		add_action( 'wp_ajax_wccp_save_product_fields', array( $this, 'save_product_fields' ) );
		add_action( 'wp_ajax_wccp_set_default_template', array( $this, 'set_default_template' ) );
	}

	/** حذف کلید از فیلدهای همه قالب‌ها + active سراسری */
	private function purge_key_from_templates( $key, $template_key = '' ) {
		$key = sanitize_key( (string) $key );
		foreach ( array_keys( Templates::all() ) as $tk ) {
			$current = Templates::fields_for( $tk );
			if ( $tk !== $template_key && ! in_array( $key, $current, true ) ) {
				continue;
			}
			$tf = array_values( array_diff( $current, array( $key ) ) );
			if ( empty( $tf ) ) {
				$tf = array_values( array_diff( Templates::default_fields(), array( $key ) ) );
			}
			if ( empty( $tf ) ) {
				$tf = ( 'billing_first_name' === $key ) ? array( 'billing_phone' ) : array( 'billing_first_name' );
			}
			Templates::update_fields( $tk, $tf );
		}
		$active = array_values( array_diff( Fields::get_active_keys(), array( $key ) ) );
		update_option( Fields::ACTIVE_OPTION, $active, false );
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

	/** @return string[] */
	private function parse_active_from_post() {
		$raw    = wp_unslash( $_POST );
		$active = array();
		if ( ! empty( $raw['active'] ) ) {
			if ( is_string( $raw['active'] ) ) {
				$decoded = json_decode( $raw['active'], true );
				$active  = is_array( $decoded ) ? $decoded : array_filter( array_map( 'trim', explode( ',', $raw['active'] ) ) );
			} elseif ( is_array( $raw['active'] ) ) {
				$active = $raw['active'];
			}
		}
		return array_values( array_unique( array_map( 'sanitize_key', $active ) ) );
	}

	/** ذخیره فیلدهای یک قالب (یا سراسری) */
	public function save_fields() {
		$this->guard();
		$active       = $this->parse_active_from_post();
		$template_key = sanitize_key( wp_unslash( $_POST['template_key'] ?? '' ) );

		if ( $template_key ) {
			$res = Templates::update_fields( $template_key, $active );
			if ( is_wp_error( $res ) ) {
				wp_send_json_error( array( 'message' => $res->get_error_message() ) );
			}
			wp_send_json_success(
				array(
					'message'       => 'فیلدهای قالب ذخیره شد.',
					'active'        => Templates::fields_for( $template_key ),
					'template_key'  => $template_key,
					'default_tpl'   => Templates::default_key(),
					'fields'        => CustomFields::merged_with_defaults(),
					'templates'     => Templates::all(),
				)
			);
		}

		$custom = get_option( Fields::CUSTOM_OPTION, array() );
		if ( ! is_array( $custom ) ) {
			$custom = array();
		}
		$overrides = get_option( Fields::OVERRIDE_OPTION, array() );
		if ( ! is_array( $overrides ) ) {
			$overrides = array();
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

	public function set_default_template() {
		$this->guard();
		$key = sanitize_key( wp_unslash( $_POST['template_key'] ?? '' ) );
		$res = Templates::set_default_key( $key );
		if ( is_wp_error( $res ) ) {
			wp_send_json_error( array( 'message' => $res->get_error_message() ) );
		}
		wp_send_json_success(
			array(
				'message'     => 'قالب پیش‌فرض صفحه پرداخت تنظیم شد.',
				'default_tpl' => Templates::default_key(),
				'active'      => Templates::fields_for( Templates::default_key() ),
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
		$content = sanitize_textarea_field( wp_unslash( $_POST['content'] ?? '' ) );
		if ( in_array( $type, Fields::option_types(), true ) && '' === trim( $options ) ) {
			wp_send_json_error( array( 'message' => 'برای این نوع فیلد حداقل یک گزینه وارد کنید.' ) );
		}
		if ( 'info' === $type && '' === trim( $content ) && '' === trim( $label ) ) {
			wp_send_json_error( array( 'message' => 'برای متن ساده، عنوان یا متن اطلاع‌رسانی را وارد کنید.' ) );
		}

		$key    = 'wccp_field_' . strtolower( wp_generate_password( 8, false, false ) );
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
			'required'     => ( 'info' === $type ) ? false : ! empty( $_POST['required'] ),
			'options'      => $options,
			'content'      => ( 'info' === $type ) ? $content : '',
			'custom'       => true,
			'user_defined' => true,
		);
		update_option( Fields::CUSTOM_OPTION, $custom, false );

		$template_key = sanitize_key( wp_unslash( $_POST['template_key'] ?? '' ) );
		if ( $template_key && isset( Templates::all()[ $template_key ] ) ) {
			Templates::add_field_to_template( $template_key, $key );
			$active = Templates::fields_for( $template_key );
		} else {
			$active   = Fields::get_active_keys();
			$active[] = $key;
			update_option( Fields::ACTIVE_OPTION, array_values( array_unique( $active ) ), false );
			$active = Fields::get_active_keys();
		}

		wp_send_json_success(
			array(
				'message'      => 'فیلد سفارشی ساخته شد و به قالب اضافه شد.',
				'key'          => $key,
				'field'        => $custom[ $key ],
				'active'       => $active,
				'fields'       => CustomFields::merged_with_defaults(),
				'template_key' => $template_key,
			)
		);
	}

	public function update_field() {
		$this->guard();
		$key = sanitize_key( wp_unslash( $_POST['key'] ?? '' ) );
		if ( ! $key ) {
			wp_send_json_error( array( 'message' => 'فیلد نامعتبر است.' ) );
		}

		$is_default = isset( Fields::defaults()[ $key ] );
		$custom     = get_option( Fields::CUSTOM_OPTION, array() );
		if ( ! is_array( $custom ) ) {
			$custom = array();
		}

		// فیلد پیش‌فرض: فقط برچسب + اجباری/اختیاری
		if ( $is_default && ! isset( $custom[ $key ] ) ) {
			$label = sanitize_text_field( wp_unslash( $_POST['label'] ?? Fields::defaults()[ $key ]['label'] ) );
			$res   = CustomFields::save_override(
				$key,
				array(
					'label'    => $label ?: Fields::defaults()[ $key ]['label'],
					'required' => ! empty( $_POST['required'] ) ? 1 : 0,
					'enabled'  => 1,
				)
			);
			if ( is_wp_error( $res ) ) {
				wp_send_json_error( array( 'message' => $res->get_error_message() ) );
			}
			$fields = CustomFields::merged_with_defaults();
			wp_send_json_success(
				array(
					'message' => 'فیلد پیش‌فرض به‌روز شد (اجباری/اختیاری و عنوان).',
					'key'     => $key,
					'field'   => $fields[ $key ] ?? array(),
					'fields'  => $fields,
					'hidden'  => CustomFields::hidden_default_keys(),
				)
			);
		}

		if ( ! isset( $custom[ $key ] ) ) {
			wp_send_json_error( array( 'message' => 'فیلد قابل ویرایش نیست.' ) );
		}

		$label = sanitize_text_field( wp_unslash( $_POST['label'] ?? $custom[ $key ]['label'] ?? '' ) );
		$type  = sanitize_key( wp_unslash( $_POST['type'] ?? $custom[ $key ]['type'] ?? 'text' ) );
		if ( ! in_array( $type, Fields::allowed_types(), true ) ) {
			$type = $custom[ $key ]['type'] ?? 'text';
		}
		$options = Fields::normalize_options_string( wp_unslash( $_POST['options_text'] ?? $custom[ $key ]['options'] ?? '' ) );
		$content = sanitize_textarea_field( wp_unslash( $_POST['content'] ?? $custom[ $key ]['content'] ?? '' ) );
		if ( in_array( $type, Fields::option_types(), true ) && '' === trim( $options ) ) {
			wp_send_json_error( array( 'message' => 'برای این نوع فیلد حداقل یک گزینه وارد کنید.' ) );
		}
		if ( 'info' === $type && '' === trim( $content ) && '' === trim( $label ) ) {
			wp_send_json_error( array( 'message' => 'برای متن ساده، عنوان یا متن اطلاع‌رسانی را وارد کنید.' ) );
		}

		$custom[ $key ] = array(
			'label'        => $label ?: $key,
			'type'         => $type,
			'required'     => ( 'info' === $type ) ? false : ! empty( $_POST['required'] ),
			'options'      => $options,
			'content'      => ( 'info' === $type ) ? $content : '',
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
				'hidden'  => CustomFields::hidden_default_keys(),
			)
		);
	}

	public function delete_field() {
		$this->guard();
		$key = sanitize_key( wp_unslash( $_POST['key'] ?? '' ) );
		if ( ! $key ) {
			wp_send_json_error( array( 'message' => 'فیلد نامعتبر است.' ) );
		}

		$template_key = sanitize_key( wp_unslash( $_POST['template_key'] ?? '' ) );
		$is_default   = isset( Fields::defaults()[ $key ] );

		if ( $is_default ) {
			// مخفی‌سازی قابل بازیابی — نه حذف فیزیکی تعریف پیش‌فرض
			CustomFields::save_override(
				$key,
				array(
					'enabled'  => 0,
					'required' => ! empty( Fields::defaults()[ $key ]['required'] ) ? 1 : 0,
					'label'    => Fields::defaults()[ $key ]['label'],
				)
			);
			$this->purge_key_from_templates( $key, $template_key );
			$active = $template_key ? Templates::fields_for( $template_key ) : Fields::get_active_keys();
			wp_send_json_success(
				array(
					'message' => 'فیلد پیش‌فرض حذف شد (قابل بازیابی است).',
					'active'  => $active,
					'fields'  => CustomFields::merged_with_defaults(),
					'hidden'  => CustomFields::hidden_default_keys(),
				)
			);
		}

		$custom = get_option( Fields::CUSTOM_OPTION, array() );
		if ( is_array( $custom ) && isset( $custom[ $key ] ) ) {
			unset( $custom[ $key ] );
			update_option( Fields::CUSTOM_OPTION, $custom, false );
		} else {
			wp_send_json_error( array( 'message' => 'فیلد یافت نشد.' ) );
		}

		$this->purge_key_from_templates( $key, $template_key );
		$active = $template_key ? Templates::fields_for( $template_key ) : Fields::get_active_keys();

		wp_send_json_success(
			array(
				'message' => 'فیلد سفارشی حذف شد.',
				'active'  => $active,
				'fields'  => CustomFields::merged_with_defaults(),
				'hidden'  => CustomFields::hidden_default_keys(),
			)
		);
	}

	public function restore_field() {
		$this->guard();
		$key = sanitize_key( wp_unslash( $_POST['key'] ?? '' ) );
		if ( ! $key || ! isset( Fields::defaults()[ $key ] ) ) {
			wp_send_json_error( array( 'message' => 'فقط فیلد پیش‌فرض مخفی قابل بازیابی است.' ) );
		}
		$ov = CustomFields::overrides();
		$cur = isset( $ov[ $key ] ) && is_array( $ov[ $key ] ) ? $ov[ $key ] : array();
		CustomFields::save_override(
			$key,
			array(
				'enabled'  => 1,
				'required' => array_key_exists( 'required', $cur ) ? (int) ! empty( $cur['required'] ) : ( ! empty( Fields::defaults()[ $key ]['required'] ) ? 1 : 0 ),
				'label'    => (string) ( $cur['label'] ?? Fields::defaults()[ $key ]['label'] ),
			)
		);

		$template_key = sanitize_key( wp_unslash( $_POST['template_key'] ?? '' ) );
		$active       = $template_key ? Templates::fields_for( $template_key ) : Fields::get_active_keys();

		wp_send_json_success(
			array(
				'message' => 'فیلد پیش‌فرض بازیابی شد — می‌توانید به ستون فعال اضافه کنید.',
				'active'  => $active,
				'fields'  => CustomFields::merged_with_defaults(),
				'hidden'  => CustomFields::hidden_default_keys(),
			)
		);
	}

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
