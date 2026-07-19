<?php
namespace WCCP;

defined( 'ABSPATH' ) || exit;

/**
 * قالب‌های صفحه پرداخت + فیلدهای اختصاصی هر قالب.
 *
 * دو قالب پیش‌فرض ثابت:
 * - digital  → محصولات دیجیتال
 * - physical → محصولات فیزیکی
 * قالب‌های سفارشی از صفحه «افزودن قالب» اضافه می‌شوند.
 */
class Templates {

	const OPTION         = 'wccp_pay_templates';
	const DEFAULT_OPTION = 'wccp_default_checkout_template';
	/** متا روی محصول ووکامرس (نه CPT لینک پرداخت) */
	const WC_PRODUCT_META = '_wccp_checkout_template';

	/** @return string[] */
	public static function default_fields() {
		return Fields::default_active();
	}

	/** فیلدهای پیشنهادی قالب دیجیتال */
	public static function digital_fields() {
		return array(
			'billing_first_name',
			'billing_last_name',
			'billing_email',
			'billing_phone',
		);
	}

	/** فیلدهای پیشنهادی قالب فیزیکی */
	public static function physical_fields() {
		return array(
			'billing_first_name',
			'billing_last_name',
			'billing_phone',
			'billing_email',
			'billing_state',
			'billing_city',
			'billing_address_1',
			'billing_postcode',
		);
	}

	/** مهاجرت کلیدهای قدیمی (مثل violet) به digital */
	private static function migrate_default_key() {
		$key = sanitize_key( (string) get_option( self::DEFAULT_OPTION, 'digital' ) );
		$all = self::all();
		if ( $key && isset( $all[ $key ] ) ) {
			return $key;
		}
		// کلید قدیمی دیگر وجود ندارد
		update_option( self::DEFAULT_OPTION, 'digital', false );
		return 'digital';
	}

	/** قالب پیش‌فرض صفحه پرداخت ووکامرس (وقتی محصول قالب اختصاصی ندارد) */
	public static function default_key() {
		return self::migrate_default_key();
	}

	/** @return true|\WP_Error */
	public static function set_default_key( $key ) {
		$key = sanitize_key( (string) $key );
		$all = self::all();
		if ( ! $key || ! isset( $all[ $key ] ) ) {
			return new \WP_Error( 'tpl', 'قالب نامعتبر است.' );
		}
		update_option( self::DEFAULT_OPTION, $key, false );
		$fields = self::fields_for( $key );
		update_option( Fields::ACTIVE_OPTION, $fields, false );
		return true;
	}

	/**
	 * ذخیره فیلدهای یک قالب (از صفحه drag&drop).
	 *
	 * @param string   $key
	 * @param string[] $fields
	 * @return string|\WP_Error
	 */
	public static function update_fields( $key, array $fields ) {
		$key = sanitize_key( (string) $key );
		$all = self::all();
		if ( ! $key || ! isset( $all[ $key ] ) ) {
			return new \WP_Error( 'tpl', 'قالب نامعتبر است.' );
		}
		$tpl   = $all[ $key ];
		$clean = self::sanitize_fields( $fields );
		if ( empty( $clean ) ) {
			return new \WP_Error( 'fields', 'حداقل یک فیلد فعال لازم است.' );
		}

		$payload = array(
			'label'       => $tpl['label'],
			'primary'     => $tpl['primary'],
			'background'  => $tpl['background'],
			'card'        => $tpl['card'],
			'text'        => $tpl['text'],
			'muted'       => $tpl['muted'],
			'button_text' => $tpl['button_text'],
			'radius'      => $tpl['radius'],
			'layout'      => $tpl['layout'],
			'fields'      => $clean,
		);
		$saved = self::save_custom( $payload, $key );
		if ( is_wp_error( $saved ) ) {
			return $saved;
		}
		if ( $key === self::default_key() ) {
			update_option( Fields::ACTIVE_OPTION, $clean, false );
		}
		return $saved;
	}

	/** افزودن یک فیلد به لیست فیلدهای قالب */
	public static function add_field_to_template( $template_key, $field_key ) {
		$fields   = self::fields_for( $template_key );
		$fields[] = sanitize_key( $field_key );
		return self::update_fields( $template_key, $fields );
	}

	/** @return array<string,array> */
	public static function builtins() {
		return array(
			'digital'  => array(
				'label'       => 'محصولات دیجیتال',
				'description' => 'برای فایل، دانلود و سرویس‌های دیجیتال',
				'primary'     => '#0ea5e9',
				'background'  => '#f0f9ff',
				'card'        => '#ffffff',
				'text'        => '#0c4a6e',
				'muted'       => '#64748b',
				'button_text' => '#ffffff',
				'radius'      => '16',
				'layout'      => 'card',
				'fields'      => self::digital_fields(),
				'builtin'     => true,
			),
			'physical' => array(
				'label'       => 'محصولات فیزیکی',
				'description' => 'برای کالاهای ارسالی با آدرس پستی',
				'primary'     => '#16a34a',
				'background'  => '#f0fdf4',
				'card'        => '#ffffff',
				'text'        => '#14532d',
				'muted'       => '#64748b',
				'button_text' => '#ffffff',
				'radius'      => '16',
				'layout'      => 'card',
				'fields'      => self::physical_fields(),
				'builtin'     => true,
			),
		);
	}

	/** @return array<string,array> */
	public static function custom() {
		$custom = get_option( self::OPTION, array() );
		return is_array( $custom ) ? $custom : array();
	}

	/** @return array<string,array> */
	public static function all() {
		$all = self::builtins();
		foreach ( self::custom() as $key => $tpl ) {
			if ( ! is_array( $tpl ) ) {
				continue;
			}
			$key = sanitize_key( (string) $key );
			if ( ! $key ) {
				continue;
			}
			// کلیدهای قدیمی پوسته‌ها (violet/ocean/…) اگر override داشته باشند به‌صورت سفارشی می‌مانند
			$base = $all[ $key ] ?? array(
				'label'       => $key,
				'primary'     => '#6d28d9',
				'background'  => '#f5f3ff',
				'card'        => '#ffffff',
				'text'        => '#0f172a',
				'muted'       => '#64748b',
				'button_text' => '#ffffff',
				'radius'      => '16',
				'layout'      => 'card',
				'fields'      => self::default_fields(),
				'builtin'     => false,
			);
			$merged            = array_merge( $base, $tpl );
			$merged['fields']  = self::sanitize_fields( $merged['fields'] ?? self::default_fields() );
			$merged['builtin'] = isset( self::builtins()[ $key ] );
			$all[ $key ]       = $merged;
		}
		return $all;
	}

	/** @return array */
	public static function get( $key ) {
		$all = self::all();
		if ( isset( $all[ $key ] ) && is_array( $all[ $key ] ) ) {
			return $all[ $key ];
		}
		return self::builtins()['digital'];
	}

	/** @return string[] */
	public static function fields_for( $key ) {
		$tpl    = self::get( $key );
		$fields = self::sanitize_fields( $tpl['fields'] ?? array() );
		return ! empty( $fields ) ? $fields : self::default_fields();
	}

	/**
	 * قالب لینک پرداخت (CPT wccp_product).
	 *
	 * @return string
	 */
	public static function product_template_key( $product_id ) {
		$key = sanitize_key( (string) get_post_meta( $product_id, '_wccp_template', true ) );
		$all = self::all();
		if ( $key && isset( $all[ $key ] ) ) {
			return $key;
		}
		return self::default_key();
	}

	/**
	 * قالب اختصاص‌داده‌شده به محصول ووکامرس.
	 *
	 * @return string
	 */
	public static function wc_product_template_key( $product_id ) {
		$key = sanitize_key( (string) get_post_meta( $product_id, self::WC_PRODUCT_META, true ) );
		$all = self::all();
		if ( $key && isset( $all[ $key ] ) ) {
			return $key;
		}
		return self::default_key();
	}

	public static function set_wc_product_template( $product_id, $template_key ) {
		$product_id   = (int) $product_id;
		$template_key = sanitize_key( (string) $template_key );
		$all          = self::all();
		if ( $product_id <= 0 || ! isset( $all[ $template_key ] ) ) {
			return false;
		}
		update_post_meta( $product_id, self::WC_PRODUCT_META, $template_key );
		return true;
	}

	/**
	 * تشخیص قالب checkout از سبد خرید ووکامرس.
	 * اولویت: اولین محصولی که قالب صریح دارد، وگرنه پیش‌فرض.
	 */
	public static function resolve_checkout_template() {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return self::default_key();
		}
		$all = self::all();
		foreach ( WC()->cart->get_cart() as $item ) {
			$pid = (int) ( $item['product_id'] ?? 0 );
			if ( $pid <= 0 ) {
				continue;
			}
			$explicit = sanitize_key( (string) get_post_meta( $pid, self::WC_PRODUCT_META, true ) );
			if ( $explicit && isset( $all[ $explicit ] ) ) {
				return $explicit;
			}
		}
		return self::default_key();
	}

	/**
	 * اعمال فیلدهای قالب روی محصول لینک پرداخت (CPT).
	 *
	 * @return string[]
	 */
	public static function apply_to_product( $product_id, $template_key ) {
		$fields = self::fields_for( $template_key );
		update_post_meta( $product_id, '_wccp_template', sanitize_key( $template_key ) );
		update_post_meta( $product_id, '_wccp_active_fields', $fields );
		return $fields;
	}

	/** @return string[] */
	public static function sanitize_fields( $fields ) {
		$defs = array_keys( CustomFields::merged_with_defaults( false ) );
		$out  = array();
		foreach ( (array) $fields as $key ) {
			$key = sanitize_key( (string) $key );
			if ( $key && in_array( $key, $defs, true ) ) {
				$out[] = $key;
			}
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * @param array  $data
	 * @param string $key
	 * @return string|\WP_Error
	 */
	public static function save_custom( array $data, $key = '' ) {
		$label = sanitize_text_field( $data['label'] ?? '' );
		if ( '' === $label ) {
			return new \WP_Error( 'label', 'نام قالب را وارد کنید.' );
		}

		$fields_raw = $data['fields'] ?? array();
		if ( is_string( $fields_raw ) ) {
			$decoded    = json_decode( $fields_raw, true );
			$fields_raw = is_array( $decoded ) ? $decoded : array_filter( array_map( 'trim', explode( ',', $fields_raw ) ) );
		}
		$fields = self::sanitize_fields( $fields_raw );
		if ( empty( $fields ) ) {
			return new \WP_Error( 'fields', 'حداقل یک فیلد برای قالب انتخاب کنید.' );
		}

		$clean = array(
			'label'       => $label,
			'primary'     => self::sanitize_color( $data['primary'] ?? '#6d28d9' ),
			'background'  => self::sanitize_color( $data['background'] ?? '#f5f3ff' ),
			'card'        => self::sanitize_color( $data['card'] ?? '#ffffff' ),
			'text'        => self::sanitize_color( $data['text'] ?? '#0f172a' ),
			'muted'       => self::sanitize_color( $data['muted'] ?? '#64748b' ),
			'button_text' => self::sanitize_color( $data['button_text'] ?? '#ffffff' ),
			'radius'      => (string) max( 0, min( 40, (int) ( $data['radius'] ?? 16 ) ) ),
			'layout'      => in_array( ( $data['layout'] ?? 'card' ), array( 'card', 'minimal', 'cover' ), true ) ? $data['layout'] : 'card',
			'fields'      => $fields,
			'builtin'     => false,
		);

		$custom = self::custom();
		$key    = sanitize_key( (string) $key );

		if ( ! $key ) {
			$key = 'custom_' . strtolower( wp_generate_password( 6, false, false ) );
		}

		if ( isset( self::builtins()[ $key ] ) ) {
			$clean['builtin'] = true;
		}

		$custom[ $key ] = $clean;
		update_option( self::OPTION, $custom, false );
		return $key;
	}

	/** @return true|\WP_Error */
	public static function delete_custom( $key ) {
		$key    = sanitize_key( (string) $key );
		$custom = self::custom();
		if ( ! isset( $custom[ $key ] ) ) {
			return new \WP_Error( 'missing', 'قالب سفارشی یافت نشد.' );
		}
		unset( $custom[ $key ] );
		update_option( self::OPTION, $custom, false );
		if ( self::default_key() === $key && ! isset( self::builtins()[ $key ] ) ) {
			update_option( self::DEFAULT_OPTION, 'digital', false );
		}
		return true;
	}

	/** @return string */
	public static function sanitize_color( $color ) {
		$color = trim( (string) $color );
		if ( preg_match( '/^#([A-Fa-f0-9]{3}|[A-Fa-f0-9]{6})$/', $color ) ) {
			return $color;
		}
		return '#6d28d9';
	}

	/** CSS برای صفحه پرداخت */
	public static function css_for( $key ) {
		$t      = self::get( $key );
		$prim   = $t['primary'];
		$bg     = $t['background'];
		$card   = $t['card'];
		$text   = $t['text'];
		$muted  = $t['muted'];
		$btn    = $t['button_text'];
		$radius = (int) $t['radius'];
		$layout = $t['layout'] ?? 'card';

		$extra = '';
		if ( 'minimal' === $layout ) {
			$extra = '.card{box-shadow:none;border:1px solid rgba(0,0,0,.08)}';
		} elseif ( 'cover' === $layout ) {
			$extra = 'body{display:flex;align-items:center;min-height:100vh}.card{margin:24px auto;width:100%}';
		}

		return "body{font-family:Vazirmatn,Tahoma,sans-serif;background:{$bg};margin:0;padding:24px;color:{$text}}"
			. ".card{max-width:520px;margin:40px auto;background:{$card};border-radius:{$radius}px;padding:28px;box-shadow:0 16px 40px rgba(15,23,42,.12)}"
			. "h1{color:{$text};margin:0 0 12px;font-size:24px}"
			. ".wccp-field{display:flex;flex-direction:column;gap:8px;margin:0 0 16px;padding:0 0 16px;border-bottom:1px dashed rgba(100,116,139,.35);font-size:13px;color:{$muted}}"
			. ".wccp-field:last-of-type{border-bottom:0;margin-bottom:8px;padding-bottom:4px}"
			. ".wccp-field-label{font-weight:700;color:{$text};font-size:14px}"
			. "input,textarea,select{border:1px solid rgba(100,116,139,.35);border-radius:12px;padding:12px;font-family:inherit;background:{$card};color:{$text}}"
			. ".wccp-choice-list{display:flex;flex-direction:column;gap:10px;margin-top:6px}"
			. ".wccp-choice{display:flex;align-items:center;gap:8px;padding:12px 14px;border:1px solid rgba(100,116,139,.3);border-radius:12px;background:transparent;cursor:pointer;font-weight:500;color:{$text}}"
			. ".wccp-choice input{margin:0}"
			. ".wccp-radio-list .wccp-choice:has(input:checked),.wccp-checkbox-list .wccp-choice:has(input:checked){border-color:{$prim};background:rgba(109,40,217,.08)}"
			. ".wccp-price{font-weight:700;color:{$prim};margin:12px 0;padding-top:8px;border-top:1px solid rgba(100,116,139,.2)}"
			. "button,.wccp-pay-btn{background:{$prim};color:{$btn};border:0;border-radius:14px;padding:12px 18px;font-weight:700;width:100%;cursor:pointer;font-family:inherit;margin-top:8px}"
			. $extra;
	}
}
