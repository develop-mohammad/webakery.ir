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
		if ( ! class_exists( 'WooCommerce' ) && ! function_exists( 'WC' ) ) {
			return;
		}
		add_filter( 'woocommerce_checkout_fields', array( $this, 'filter_fields' ), 1000 );
		add_filter( 'woocommerce_form_field_wccp_radio', array( $this, 'render_choice_fields' ), 10, 4 );
		add_filter( 'woocommerce_form_field_wccp_checkboxes', array( $this, 'render_choice_fields' ), 10, 4 );
		add_filter( 'woocommerce_form_field_wccp_info', array( $this, 'render_info_field' ), 10, 4 );
		add_filter( 'woocommerce_form_field_wccp_consent', array( $this, 'render_consent_field' ), 10, 4 );
		// هر فیلد نوع «تلفن» = billing_phone برای درگاه/پیامک
		add_filter( 'woocommerce_checkout_fields', array( $this, 'ensure_hidden_billing_phone' ), 1001 );
		add_filter( 'woocommerce_checkout_posted_data', array( $this, 'sync_missing_core_fields' ), 1 );
		add_action( 'woocommerce_checkout_process', array( $this, 'force_sync_phone_early' ), 0 );
		add_action( 'woocommerce_checkout_process', array( $this, 'strip_false_mobile_notices' ), 999 );
		add_action( 'woocommerce_after_checkout_validation', array( $this, 'clear_orphan_phone_errors' ), 1, 2 );
		add_action( 'woocommerce_after_checkout_validation', array( $this, 'clear_orphan_phone_errors' ), 999, 2 );
		add_action( 'woocommerce_after_checkout_validation', array( $this, 'validate_choice_fields' ), 10, 2 );
		add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'save_order_meta' ) );
		add_action( 'woocommerce_admin_order_data_after_billing_address', array( $this, 'admin_display' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/** @return string[] */
	private function active_keys() {
		$active = Templates::fields_for( Templates::resolve_checkout_template() );
		if ( empty( $active ) ) {
			$active = Fields::get_active_keys();
		}
		return $active;
	}

	/** نرمال‌سازی شماره ایران → 09xxxxxxxxx یا خالی */
	public static function normalize_ir_mobile( $raw ) {
		$digits = preg_replace( '/\D+/', '', (string) $raw );
		if ( ! $digits ) {
			return '';
		}
		if ( 0 === strpos( $digits, '98' ) && strlen( $digits ) >= 12 ) {
			$digits = '0' . substr( $digits, 2 );
		}
		if ( preg_match( '/^9\d{9}$/', $digits ) ) {
			$digits = '0' . $digits;
		}
		return preg_match( '/^09\d{9}$/', $digits ) ? $digits : '';
	}

	/**
	 * هر فیلد نوع تلفن / شماره → مقدار billing_phone برای ووکامرس، پیامک و درگاه.
	 *
	 * @param array $data
	 * @return array
	 */
	public function sync_missing_core_fields( $data ) {
		if ( ! is_array( $data ) ) {
			return $data;
		}
		$active = $this->active_keys();
		$defs   = CustomFields::merged_with_defaults();

		$current = self::normalize_ir_mobile( $data['billing_phone'] ?? '' );
		$found   = $this->find_posted_mobile( $active, $defs );
		// اگر billing_phone خالی/نامعتبر است، از هر فیلد تلفن پر کن
		if ( ! $current && $found ) {
			$data['billing_phone']  = $found;
			$_POST['billing_phone'] = $found; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		} elseif ( $current ) {
			$data['billing_phone']  = $current;
			$_POST['billing_phone'] = $current; // phpcs:ignore
		} elseif ( $found ) {
			$data['billing_phone']  = $found;
			$_POST['billing_phone'] = $found; // phpcs:ignore
		}

		// اگر نام/نام‌خانوادگی استاندارد نیست ولی یک فیلد متنی پر شده، از آن پر کن
		$first = trim( (string) ( $data['billing_first_name'] ?? '' ) );
		if ( ( ! in_array( 'billing_first_name', $active, true ) || '' === $first ) ) {
			foreach ( $active as $key ) {
				if ( empty( $defs[ $key ] ) || in_array( ( $defs[ $key ]['type'] ?? '' ), array( 'tel', 'email', 'info', 'radio', 'checkboxes', 'select' ), true ) ) {
					continue;
				}
				$val = isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : ''; // phpcs:ignore
				if ( '' === $val ) {
					continue;
				}
				$parts = preg_split( '/\s+/u', $val, 2 );
				$data['billing_first_name']  = $parts[0] ?? $val;
				$data['billing_last_name']   = $parts[1] ?? ( $data['billing_last_name'] ?? '.' );
				$_POST['billing_first_name'] = $data['billing_first_name']; // phpcs:ignore
				$_POST['billing_last_name']  = $data['billing_last_name']; // phpcs:ignore
				break;
			}
		}

		return $data;
	}

	/** همگام‌سازی زودهنگام قبل از اعتبارسنجی افزونه‌های پیامک */
	public function force_sync_phone_early() {
		$active = $this->active_keys();
		$defs   = CustomFields::merged_with_defaults();
		$found  = $this->find_posted_mobile( $active, $defs );
		$cur    = self::normalize_ir_mobile( $_POST['billing_phone'] ?? '' ); // phpcs:ignore
		if ( ! $cur && $found ) {
			$_POST['billing_phone'] = $found; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		} elseif ( $cur ) {
			$_POST['billing_phone'] = $cur; // phpcs:ignore
		}
	}

	/**
	 * اولویت: billing_phone → type=tel → برچسب شماره → هر مقدار 09 در POST.
	 *
	 * @param string[]            $active
	 * @param array<string,array> $defs
	 * @return string
	 */
	private function find_posted_mobile( array $active, array $defs ) {
		$priority_keys = array();
		$tel_keys      = array();
		$label_keys    = array();

		foreach ( $active as $key ) {
			if ( empty( $defs[ $key ] ) ) {
				continue;
			}
			$type  = $defs[ $key ]['type'] ?? 'text';
			$label = (string) ( $defs[ $key ]['label'] ?? '' );
			if ( 'billing_phone' === $key ) {
				$priority_keys[] = $key;
			} elseif ( 'tel' === $type ) {
				$tel_keys[] = $key;
			} elseif (
				false !== stripos( $key, 'phone' )
				|| false !== stripos( $key, 'mobile' )
				|| false !== strpos( $label, 'شماره' )
				|| false !== strpos( $label, 'موبایل' )
				|| false !== strpos( $label, 'تلفن' )
			) {
				$label_keys[] = $key;
			}
		}

		foreach ( array_merge( $priority_keys, $tel_keys, $label_keys ) as $key ) {
			$raw = isset( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : ''; // phpcs:ignore
			if ( is_array( $raw ) ) {
				continue;
			}
			$mobile = self::normalize_ir_mobile( $raw );
			if ( $mobile ) {
				return $mobile;
			}
		}

		// آخرین راه: هر مقدار شبیه موبایل ایران در POST (کلیدهای wccp_ / billing_)
		foreach ( (array) wp_unslash( $_POST ) as $key => $raw ) { // phpcs:ignore
			$key = (string) $key;
			if ( is_array( $raw ) ) {
				continue;
			}
			if (
				0 !== strpos( $key, 'wccp' )
				&& 0 !== strpos( $key, 'billing_' )
				&& false === stripos( $key, 'phone' )
				&& false === stripos( $key, 'mobile' )
			) {
				continue;
			}
			$mobile = self::normalize_ir_mobile( $raw );
			if ( $mobile ) {
				return $mobile;
			}
		}
		return '';
	}

	/**
	 * اگر billing_phone در قالب نیست ولی فیلد تلفن داریم، فیلد مخفی billing_phone بساز.
	 *
	 * @param array $fields
	 * @return array
	 */
	public function ensure_hidden_billing_phone( $fields ) {
		if ( ! is_array( $fields ) ) {
			return $fields;
		}
		$active = $this->active_keys();
		$defs   = CustomFields::merged_with_defaults();
		$has_tel = false;
		foreach ( $active as $key ) {
			if ( 'billing_phone' === $key ) {
				return $fields;
			}
			if ( ! empty( $defs[ $key ]['type'] ) && 'tel' === $defs[ $key ]['type'] ) {
				$has_tel = true;
			}
		}
		if ( ! $has_tel ) {
			return $fields;
		}
		if ( ! isset( $fields['billing'] ) || ! is_array( $fields['billing'] ) ) {
			$fields['billing'] = array();
		}
		$fields['billing']['billing_phone'] = array(
			'type'              => 'hidden',
			'required'          => false,
			'label'             => '',
			'class'             => array( 'wccp-hidden-billing-phone', 'wccp-maps-billing-phone' ),
			'priority'          => 5,
			'custom_attributes' => array( 'data-wccp-synced' => '1', 'autocomplete' => 'tel' ),
		);
		return $fields;
	}

	/** پاک کردن noticeهای اشتباه افزونه پیامک وقتی شماره معتبر از فیلد تلفن داریم */
	public function strip_false_mobile_notices() {
		if ( ! function_exists( 'wc_get_notices' ) || ! function_exists( 'wc_clear_notices' ) ) {
			return;
		}
		$this->force_sync_phone_early();
		$phone = self::normalize_ir_mobile( $_POST['billing_phone'] ?? '' ); // phpcs:ignore
		if ( ! $phone ) {
			$phone = $this->find_posted_mobile( $this->active_keys(), CustomFields::merged_with_defaults() );
		}
		if ( ! $phone ) {
			return;
		}

		$all = wc_get_notices();
		if ( empty( $all['error'] ) || ! is_array( $all['error'] ) ) {
			return;
		}
		$kept = array();
		foreach ( $all['error'] as $item ) {
			$msg = is_array( $item ) ? (string) ( $item['notice'] ?? '' ) : (string) $item;
			if (
				false !== strpos( $msg, 'موبایل' )
				|| false !== strpos( $msg, 'شماره موبایل' )
				|| false !== stripos( $msg, 'mobile' )
			) {
				continue;
			}
			$kept[] = $item;
		}
		if ( count( $kept ) === count( $all['error'] ) ) {
			return;
		}
		wc_clear_notices();
		foreach ( $all as $type => $items ) {
			if ( 'error' === $type ) {
				foreach ( $kept as $item ) {
					$msg = is_array( $item ) ? (string) ( $item['notice'] ?? '' ) : (string) $item;
					if ( $msg !== '' ) {
						wc_add_notice( $msg, 'error' );
					}
				}
				continue;
			}
			if ( ! is_array( $items ) ) {
				continue;
			}
			foreach ( $items as $item ) {
				$msg = is_array( $item ) ? (string) ( $item['notice'] ?? '' ) : (string) $item;
				if ( $msg !== '' ) {
					wc_add_notice( $msg, $type );
				}
			}
		}
	}

	/**
	 * اگر حداقل یک فیلد تلفن معتبر داریم، خطای موبایل billing_phone را بردار.
	 *
	 * @param array     $data
	 * @param \WP_Error $errors
	 */
	public function clear_orphan_phone_errors( $data, $errors ) {
		if ( ! $errors instanceof \WP_Error ) {
			return;
		}
		$active = $this->active_keys();
		$defs   = CustomFields::merged_with_defaults();
		$phone  = self::normalize_ir_mobile( $data['billing_phone'] ?? '' );
		if ( ! $phone ) {
			$phone = self::normalize_ir_mobile( $_POST['billing_phone'] ?? '' ); // phpcs:ignore
		}
		if ( ! $phone ) {
			$phone = $this->find_posted_mobile( $active, $defs );
			if ( $phone ) {
				$_POST['billing_phone'] = $phone; // phpcs:ignore
			}
		}

		if ( ! $phone ) {
			return;
		}

		foreach ( $errors->get_error_codes() as $code ) {
			$msgs = $errors->get_error_messages( $code );
			foreach ( $msgs as $msg ) {
				if (
					false !== strpos( $msg, 'موبایل' )
					|| false !== strpos( $msg, 'شماره تماس' )
					|| false !== strpos( $msg, 'شماره موبایل' )
					|| false !== stripos( $msg, 'phone' )
					|| false !== stripos( $msg, 'mobile' )
					|| 'billing_phone' === $code
					|| false !== strpos( (string) $code, 'billing_phone' )
					|| false !== strpos( (string) $code, 'phone' )
				) {
					$errors->remove( $code );
					break;
				}
			}
		}
	}

	public function enqueue_assets() {
		$on_checkout = false;
		if ( class_exists( __NAMESPACE__ . '\\CheckoutPage' ) ) {
			$on_checkout = CheckoutPage::is_current_checkout();
		} elseif ( function_exists( 'is_checkout' ) && is_checkout() ) {
			$on_checkout = true;
		}
		if ( ! $on_checkout ) {
			return;
		}
		wp_enqueue_style( 'wccp-checkout', WCCP_URL . 'assets/checkout.css', array(), WCCP_VERSION );
		wp_enqueue_script( 'wccp-checkout', WCCP_URL . 'assets/checkout.js', array( 'jquery' ), WCCP_VERSION, true );
	}

	public function filter_fields( $fields ) {
		if ( ! is_array( $fields ) ) {
			return $fields;
		}

		$defs   = CustomFields::merged_with_defaults();
		$active = Templates::fields_for( Templates::resolve_checkout_template() );
		if ( empty( $active ) ) {
			$active = Fields::get_active_keys();
		}

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
			} elseif ( 'info' === $type ) {
				$wc_type = 'wccp_info';
			} elseif ( 'consent' === $type ) {
				$wc_type = 'wccp_consent';
			} elseif ( in_array( $type, array( 'text', 'tel', 'email', 'number', 'checkbox' ), true ) ) {
				$wc_type = $type;
			} else {
				$wc_type = 'text';
			}

			$field = array(
				'type'     => $wc_type,
				'label'    => $def['label'],
				'required' => ( 'info' === $raw_type ) ? false : ! empty( $def['required'] ),
				'class'    => array( 'form-row-wide', 'wccp-field', 'wccp-field-' . sanitize_key( $raw_type ) ),
				'priority' => $priority,
			);
			if ( ! empty( $def['placeholder'] ) ) {
				$field['placeholder'] = $def['placeholder'];
			}
			// فیلدهای تلفن → مثل billing_phone برای پیامک/درگاه همگام می‌شوند
			if ( 'tel' === $raw_type || 'billing_phone' === $key ) {
				$field['class'][]   = 'wccp-maps-billing-phone';
				$field['autocomplete'] = 'tel';
			}
			if ( 'info' === $raw_type || 'consent' === $raw_type ) {
				$field['wccp_content'] = (string) ( $def['content'] ?? '' );
			}
			if ( 'info' === $raw_type ) {
				$field['required'] = false;
			}
			if ( 'consent' === $raw_type ) {
				$agree = Fields::parse_options( $def['options'] ?? '' );
				$field['wccp_agree_label'] = $agree[0] ?? 'رضایت دارم';
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

	public function render_info_field( $field, $key, $args, $value ) {
		$classes = isset( $args['class'] ) ? (array) $args['class'] : array( 'form-row-wide', 'wccp-field', 'wccp-field-info' );
		$label   = isset( $args['label'] ) ? (string) $args['label'] : '';
		$content = isset( $args['wccp_content'] ) ? (string) $args['wccp_content'] : '';

		ob_start();
		echo '<div class="form-row ' . esc_attr( implode( ' ', $classes ) ) . '" id="' . esc_attr( $key ) . '_field" data-priority="' . esc_attr( (string) ( $args['priority'] ?? '' ) ) . '">';
		echo '<div class="wccp-info-box">';
		if ( '' !== trim( $label ) ) {
			echo '<strong class="wccp-info-title">' . esc_html( $label ) . '</strong>';
		}
		if ( '' !== trim( $content ) ) {
			echo '<div class="wccp-info-text">' . wp_kses_post( wpautop( $content ) ) . '</div>';
		}
		echo '</div></div>';
		return ob_get_clean();
	}

	public function render_consent_field( $field, $key, $args, $value ) {
		$classes = isset( $args['class'] ) ? (array) $args['class'] : array( 'form-row-wide', 'wccp-field', 'wccp-field-consent' );
		$label   = isset( $args['label'] ) ? (string) $args['label'] : '';
		$content = isset( $args['wccp_content'] ) ? (string) $args['wccp_content'] : '';
		$agree   = isset( $args['wccp_agree_label'] ) ? (string) $args['wccp_agree_label'] : 'رضایت دارم';
		$required = ! empty( $args['required'] );
		$checked  = (string) $value !== '' && (string) $value === (string) $agree;

		ob_start();
		echo '<div class="form-row ' . esc_attr( implode( ' ', $classes ) ) . '" id="' . esc_attr( $key ) . '_field" data-priority="' . esc_attr( (string) ( $args['priority'] ?? '' ) ) . '">';
		echo '<div class="wccp-info-box wccp-consent-box">';
		if ( '' !== trim( $label ) ) {
			echo '<strong class="wccp-info-title">' . esc_html( $label );
			if ( $required ) {
				echo ' <abbr class="required" title="required">*</abbr>';
			}
			echo '</strong>';
		}
		if ( '' !== trim( $content ) ) {
			echo '<div class="wccp-info-text">' . wp_kses_post( wpautop( $content ) ) . '</div>';
		}
		$id = $key . '_agree';
		echo '<label class="wccp-consent-check" for="' . esc_attr( $id ) . '">';
		echo '<input type="checkbox" class="input-checkbox" name="' . esc_attr( $key ) . '" id="' . esc_attr( $id ) . '" value="' . esc_attr( $agree ) . '" ' . checked( $checked, true, false ) . ( $required ? ' required' : '' ) . ' /> ';
		echo '<span>' . esc_html( $agree ) . '</span>';
		echo '</label>';
		echo '</div></div>';
		return ob_get_clean();
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
		$active = Templates::fields_for( Templates::resolve_checkout_template() );

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
			if ( 'consent' === $type ) {
				$val = isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : ''; // phpcs:ignore
				if ( '' === $val ) {
					$errors->add( 'wccp_' . $key, sprintf( 'برای ادامه، باید «%s» را تأیید کنید.', $defs[ $key ]['label'] ?: 'رضایت‌نامه' ) );
				}
			}
		}
	}

	public function save_order_meta( $order_id ) {
		$defs = CustomFields::merged_with_defaults();
		foreach ( Templates::fields_for( Templates::resolve_checkout_template() ) as $key ) {
			if ( empty( $defs[ $key ] ) ) {
				continue;
			}
			$type = $defs[ $key ]['type'] ?? 'text';
			if ( in_array( $type, Fields::display_only_types(), true ) ) {
				continue;
			}

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
