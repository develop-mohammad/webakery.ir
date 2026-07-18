<?php
namespace WCCP;

defined( 'ABSPATH' ) || exit;

/**
 * قالب‌های صفحه پرداخت + فیلدهای اختصاصی هر قالب.
 */
class Templates {

	const OPTION         = 'wccp_pay_templates';
	const DEFAULT_OPTION = 'wccp_default_checkout_template';

	/** @return string[] */
	public static function default_fields() {
		return Fields::default_active();
	}

	/** قالب پیش‌فرض صفحه پرداخت ووکامرس */
	public static function default_key() {
		$key = sanitize_key( (string) get_option( self::DEFAULT_OPTION, 'violet' ) );
		$all = self::all();
		return ( $key && isset( $all[ $key ] ) ) ? $key : 'violet';
	}

	/** @return true|\WP_Error */
	public static function set_default_key( $key ) {
		$key = sanitize_key( (string) $key );
		$all = self::all();
		if ( ! $key || ! isset( $all[ $key ] ) ) {
			return new \WP_Error( 'tpl', 'قالب نامعتبر است.' );
		}
		update_option( self::DEFAULT_OPTION, $key, false );
		// فیلدهای فعال checkout را با قالب پیش‌فرض همگام کن
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
		$tpl    = $all[ $key ];
		$clean  = self::sanitize_fields( $fields );
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
		$base_fields = self::default_fields();
		$skins       = array(
			'violet'  => array( 'بنفش کلاسیک', '#6d28d9', '#f5f3ff', '#ffffff', '#0f172a', '#64748b', '#ffffff', '20', 'card' ),
			'ocean'   => array( 'آبی اقیانوس', '#0284c7', '#e0f2fe', '#ffffff', '#0c4a6e', '#64748b', '#ffffff', '16', 'card' ),
			'emerald' => array( 'سبز زمردی', '#059669', '#ecfdf5', '#ffffff', '#064e3b', '#64748b', '#ffffff', '18', 'card' ),
			'dark'    => array( 'تیره حرفه‌ای', '#a78bfa', '#0f172a', '#1e293b', '#f8fafc', '#94a3b8', '#0f172a', '16', 'card' ),
			'minimal' => array( 'مینیمال روشن', '#111827', '#f9fafb', '#ffffff', '#111827', '#6b7280', '#ffffff', '12', 'minimal' ),
			'rose'    => array( 'صورتی مدرن', '#e11d48', '#fff1f2', '#ffffff', '#881337', '#9f1239', '#ffffff', '22', 'card' ),
		);
		$out = array();
		foreach ( $skins as $key => $s ) {
			$out[ $key ] = array(
				'label'       => $s[0],
				'primary'     => $s[1],
				'background'  => $s[2],
				'card'        => $s[3],
				'text'        => $s[4],
				'muted'       => $s[5],
				'button_text' => $s[6],
				'radius'      => $s[7],
				'layout'      => $s[8],
				'fields'      => $base_fields,
				'builtin'     => true,
			);
		}
		return $out;
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
			$base           = $all[ $key ] ?? array(
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
			$merged         = array_merge( $base, $tpl );
			$merged['fields'] = self::sanitize_fields( $merged['fields'] ?? self::default_fields() );
			$merged['builtin'] = isset( self::builtins()[ $key ] );
			$all[ $key ]    = $merged;
		}
		return $all;
	}

	/** @return array */
	public static function get( $key ) {
		$all = self::all();
		if ( isset( $all[ $key ] ) && is_array( $all[ $key ] ) ) {
			return $all[ $key ];
		}
		return self::builtins()['violet'];
	}

	/** @return string[] */
	public static function fields_for( $key ) {
		$tpl = self::get( $key );
		$fields = self::sanitize_fields( $tpl['fields'] ?? array() );
		return ! empty( $fields ) ? $fields : self::default_fields();
	}

	/** @return string */
	public static function product_template_key( $product_id ) {
		$key = sanitize_key( (string) get_post_meta( $product_id, '_wccp_template', true ) );
		$all = self::all();
		if ( $key && isset( $all[ $key ] ) ) {
			return $key;
		}
		return 'violet';
	}

	/**
	 * اعمال فیلدهای قالب روی محصول.
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
		$defs = array_keys( CustomFields::merged_with_defaults() );
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

		// کلید جدید
		if ( ! $key ) {
			$key = 'custom_' . strtolower( wp_generate_password( 6, false, false ) );
		}

		// اگر روی پیش‌فرض ذخیره می‌شود، به‌صورت override نگه دار
		if ( isset( self::builtins()[ $key ] ) ) {
			$clean['builtin'] = true;
		}

		$custom[ $key ] = $clean;
		update_option( self::OPTION, $custom, false );
		return $key;
	}

	/** @return true|\WP_Error */
	public static function delete_custom( $key ) {
		$key = sanitize_key( (string) $key );
		$custom = self::custom();
		if ( ! isset( $custom[ $key ] ) ) {
			return new \WP_Error( 'missing', 'قالب سفارشی یافت نشد.' );
		}
		// برای پیش‌فرض فقط override پاک می‌شود (برمی‌گردد به حالت اولیه)
		unset( $custom[ $key ] );
		update_option( self::OPTION, $custom, false );
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
