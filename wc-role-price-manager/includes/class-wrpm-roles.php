<?php
defined( 'ABSPATH' ) || exit;

/**
 * مدیریت نقش‌های سفارشی
 */
class WRPM_Roles {

	const OPTION = 'wrpm_custom_roles';

	/** @var self|null */
	private static $instance = null;

	/**
	 * @return self
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'init', array( $this, 'register_custom_roles' ), 5 );
	}

	/**
	 * نقش‌های ذخیره‌شده در تنظیمات
	 *
	 * @return array<string,array{label:string,base:string,caps:array}>
	 */
	public static function get_custom_roles() {
		$roles = get_option( self::OPTION, array() );
		return is_array( $roles ) ? $roles : array();
	}

	/**
	 * ثبت نقش‌های سفارشی در وردپرس
	 */
	public function register_custom_roles() {
		foreach ( self::get_custom_roles() as $slug => $data ) {
			$slug = sanitize_key( $slug );
			if ( ! $slug || get_role( $slug ) ) {
				continue;
			}
			$base = sanitize_key( $data['base'] ?? 'customer' );
			$base_role = get_role( $base );
			$caps = $base_role ? $base_role->capabilities : array( 'read' => true );
			if ( ! empty( $data['caps'] ) && is_array( $data['caps'] ) ) {
				foreach ( $data['caps'] as $cap => $grant ) {
					$caps[ sanitize_key( $cap ) ] = (bool) $grant;
				}
			}
			add_role( $slug, sanitize_text_field( $data['label'] ?? $slug ), $caps );
		}
	}

	/**
	 * ذخیره نقش‌های سفارشی و همگام‌سازی با وردپرس
	 *
	 * @param array $roles
	 * @return array
	 */
	public static function save_custom_roles( array $roles ) {
		$clean    = array();
		$old      = self::get_custom_roles();
		$old_keys = array_keys( $old );

		foreach ( $roles as $slug => $data ) {
			$slug = sanitize_key( is_string( $slug ) ? $slug : ( $data['slug'] ?? '' ) );
			if ( ! $slug || 'administrator' === $slug ) {
				continue;
			}

			// فقط نقش‌های ساخته‌شده توسط این افزونه یا نقش کاملاً جدید
			$owned = isset( $old[ $slug ] );
			if ( get_role( $slug ) && ! $owned ) {
				continue;
			}

			$label = sanitize_text_field( $data['label'] ?? $slug );
			$base  = sanitize_key( $data['base'] ?? 'customer' );
			if ( ! get_role( $base ) ) {
				$base = 'customer';
			}

			$caps = array();
			if ( ! empty( $data['caps'] ) && is_array( $data['caps'] ) ) {
				foreach ( $data['caps'] as $cap => $grant ) {
					$caps[ sanitize_key( $cap ) ] = (bool) $grant;
				}
			}

			$clean[ $slug ] = array(
				'label' => $label,
				'base'  => $base,
				'caps'  => $caps,
			);

			$existing  = get_role( $slug );
			$base_role = get_role( $base );
			$full_caps = $base_role ? $base_role->capabilities : array( 'read' => true );
			$full_caps = array_merge( $full_caps, $caps );

			if ( $existing ) {
				foreach ( array_keys( $existing->capabilities ) as $cap ) {
					$existing->remove_cap( $cap );
				}
				foreach ( $full_caps as $cap => $grant ) {
					if ( $grant ) {
						$existing->add_cap( $cap );
					}
				}
				global $wp_roles;
				if ( isset( $wp_roles ) ) {
					$wp_roles->roles[ $slug ]['name'] = $label;
					$wp_roles->role_names[ $slug ]    = $label;
				}
			} else {
				add_role( $slug, $label, $full_caps );
			}
		}

		foreach ( $old_keys as $slug ) {
			if ( ! isset( $clean[ $slug ] ) ) {
				remove_role( $slug );
			}
		}

		update_option( self::OPTION, $clean, false );
		return $clean;
	}

	/**
	 * افزودن یک نقش
	 *
	 * @param string $slug
	 * @param string $label
	 * @param string $base
	 * @return true|WP_Error
	 */
	public static function add_role( $slug, $label, $base = 'customer' ) {
		$slug  = sanitize_key( $slug );
		$label = sanitize_text_field( $label );
		$base  = sanitize_key( $base );

		if ( ! $slug || ! $label ) {
			return new WP_Error( 'invalid', 'شناسه و عنوان نقش الزامی است.' );
		}
		if ( ! preg_match( '/^[a-z0-9_\-]+$/', $slug ) ) {
			return new WP_Error( 'invalid_slug', 'شناسه نقش فقط می‌تواند شامل حروف انگلیسی کوچک، عدد، خط تیره و زیرخط باشد.' );
		}
		if ( get_role( $slug ) ) {
			return new WP_Error( 'exists', 'این نقش از قبل وجود دارد.' );
		}
		if ( ! get_role( $base ) ) {
			$base = 'customer';
		}

		$roles = self::get_custom_roles();
		$roles[ $slug ] = array(
			'label' => $label,
			'base'  => $base,
			'caps'  => array(),
		);
		self::save_custom_roles( $roles );
		return true;
	}

	/**
	 * حذف نقش سفارشی
	 *
	 * @param string $slug
	 * @return bool
	 */
	public static function delete_role( $slug ) {
		$slug  = sanitize_key( $slug );
		$roles = self::get_custom_roles();
		if ( ! isset( $roles[ $slug ] ) ) {
			return false;
		}
		unset( $roles[ $slug ] );
		self::save_custom_roles( $roles );
		return true;
	}

	/**
	 * لیست نقش‌های قابل انتخاب برای قیمت‌گذاری (بدون administrator)
	 *
	 * @return array<string,string> slug => label
	 */
	public static function priceable_roles() {
		$out = array();
		foreach ( wp_roles()->roles as $slug => $role ) {
			if ( 'administrator' === $slug ) {
				continue;
			}
			$out[ $slug ] = translate_user_role( $role['name'] );
		}
		return $out;
	}
}
