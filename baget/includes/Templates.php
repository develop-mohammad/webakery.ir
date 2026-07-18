<?php
namespace WCCP;

defined( 'ABSPATH' ) || exit;

/**
 * قالب‌های صفحه پرداخت محصولات آنلاین.
 */
class Templates {

	const OPTION = 'wccp_pay_templates';

	/** @return array<string,array> */
	public static function builtins() {
		return array(
			'violet'  => array(
				'label'       => 'بنفش کلاسیک',
				'primary'     => '#6d28d9',
				'background'  => '#f5f3ff',
				'card'        => '#ffffff',
				'text'        => '#0f172a',
				'muted'       => '#64748b',
				'button_text' => '#ffffff',
				'radius'      => '20',
				'layout'      => 'card',
				'builtin'     => true,
			),
			'ocean'   => array(
				'label'       => 'آبی اقیانوس',
				'primary'     => '#0284c7',
				'background'  => '#e0f2fe',
				'card'        => '#ffffff',
				'text'        => '#0c4a6e',
				'muted'       => '#64748b',
				'button_text' => '#ffffff',
				'radius'      => '16',
				'layout'      => 'card',
				'builtin'     => true,
			),
			'emerald' => array(
				'label'       => 'سبز زمردی',
				'primary'     => '#059669',
				'background'  => '#ecfdf5',
				'card'        => '#ffffff',
				'text'        => '#064e3b',
				'muted'       => '#64748b',
				'button_text' => '#ffffff',
				'radius'      => '18',
				'layout'      => 'card',
				'builtin'     => true,
			),
			'dark'    => array(
				'label'       => 'تیره حرفه‌ای',
				'primary'     => '#a78bfa',
				'background'  => '#0f172a',
				'card'        => '#1e293b',
				'text'        => '#f8fafc',
				'muted'       => '#94a3b8',
				'button_text' => '#0f172a',
				'radius'      => '16',
				'layout'      => 'card',
				'builtin'     => true,
			),
			'minimal' => array(
				'label'       => 'مینیمال روشن',
				'primary'     => '#111827',
				'background'  => '#f9fafb',
				'card'        => '#ffffff',
				'text'        => '#111827',
				'muted'       => '#6b7280',
				'button_text' => '#ffffff',
				'radius'      => '12',
				'layout'      => 'minimal',
				'builtin'     => true,
			),
			'rose'    => array(
				'label'       => 'صورتی مدرن',
				'primary'     => '#e11d48',
				'background'  => '#fff1f2',
				'card'        => '#ffffff',
				'text'        => '#881337',
				'muted'       => '#9f1239',
				'button_text' => '#ffffff',
				'radius'      => '22',
				'layout'      => 'card',
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
		return array_merge( self::builtins(), self::custom() );
	}

	/** @return array */
	public static function get( $key ) {
		$all = self::all();
		if ( isset( $all[ $key ] ) && is_array( $all[ $key ] ) ) {
			return $all[ $key ];
		}
		return self::builtins()['violet'];
	}

	/** @return string */
	public static function product_template_key( $product_id ) {
		$key = get_post_meta( $product_id, '_wccp_template', true );
		$key = sanitize_key( (string) $key );
		$all = self::all();
		if ( $key && isset( $all[ $key ] ) ) {
			return $key;
		}
		return 'violet';
	}

	/**
	 * @param array $data
	 * @return string|WP_Error key
	 */
	public static function save_custom( array $data, $key = '' ) {
		$label = sanitize_text_field( $data['label'] ?? '' );
		if ( '' === $label ) {
			return new \WP_Error( 'label', 'نام قالب را وارد کنید.' );
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
			'builtin'     => false,
		);

		$custom = self::custom();
		$key    = sanitize_key( (string) $key );
		if ( ! $key || isset( self::builtins()[ $key ] ) ) {
			$key = 'custom_' . strtolower( wp_generate_password( 6, false, false ) );
		}
		$custom[ $key ] = $clean;
		update_option( self::OPTION, $custom, false );
		return $key;
	}

	/** @return true|\WP_Error */
	public static function delete_custom( $key ) {
		$key = sanitize_key( (string) $key );
		if ( ! $key || isset( self::builtins()[ $key ] ) ) {
			return new \WP_Error( 'builtin', 'قالب پیش‌فرض قابل حذف نیست.' );
		}
		$custom = self::custom();
		if ( ! isset( $custom[ $key ] ) ) {
			return new \WP_Error( 'missing', 'قالب یافت نشد.' );
		}
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
			. ".wccp-choice + .wccp-choice{margin-top:0}"
			. ".wccp-choice input{margin:0}"
			. ".wccp-radio-list .wccp-choice:has(input:checked),.wccp-checkbox-list .wccp-choice:has(input:checked){border-color:{$prim};background:rgba(109,40,217,.08)}"
			. ".wccp-price{font-weight:700;color:{$prim};margin:12px 0;padding-top:8px;border-top:1px solid rgba(100,116,139,.2)}"
			. "button,.wccp-pay-btn{background:{$prim};color:{$btn};border:0;border-radius:14px;padding:12px 18px;font-weight:700;width:100%;cursor:pointer;font-family:inherit;margin-top:8px}"
			. $extra;
	}
}
