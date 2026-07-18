<?php
defined( 'ABSPATH' ) || exit;

class AL_Access {

	const OPTION = 'al_role_rules';

	public static function register() {
		add_action( 'admin_menu', array( __CLASS__, 'enforce_menus' ), 9999 );
		add_action( 'admin_init', array( __CLASS__, 'block_direct_url' ), 1 );
	}

	public static function rules() {
		$rules = get_option( self::OPTION, array() );
		return is_array( $rules ) ? $rules : array();
	}

	public static function save_rules( array $rules ) {
		$clean = array();
		foreach ( $rules as $role => $denied ) {
			$role = sanitize_key( $role );
			if ( ! $role || ! is_array( $denied ) ) {
				continue;
			}
			$clean[ $role ] = array_values( array_unique( array_map( 'sanitize_key', $denied ) ) );
		}
		update_option( self::OPTION, $clean, false );
		return $clean;
	}

	public static function denied_for_user( $user = null ) {
		if ( ! $user ) {
			$user = wp_get_current_user();
		}
		if ( ! $user || ! $user->exists() ) {
			return array();
		}
		if ( user_can( $user, 'manage_options' ) ) {
			return array();
		}
		$rules  = self::rules();
		$denied = array();
		foreach ( (array) $user->roles as $role ) {
			if ( ! empty( $rules[ $role ] ) ) {
				$denied = array_merge( $denied, $rules[ $role ] );
			}
		}
		return array_values( array_unique( $denied ) );
	}

	public static function menu_catalog() {
		global $menu, $submenu;
		$catalog = array();

		if ( is_array( $menu ) ) {
			foreach ( $menu as $item ) {
				if ( empty( $item[2] ) ) {
					continue;
				}
				$slug = (string) $item[2];
				$catalog[ $slug ] = array(
					'slug'  => $slug,
					'title' => wp_strip_all_tags( (string) ( $item[0] ?? $slug ) ),
					'type'  => 'menu',
				);
			}
		}

		if ( is_array( $submenu ) ) {
			foreach ( $submenu as $parent => $items ) {
				foreach ( (array) $items as $sub ) {
					if ( empty( $sub[2] ) ) {
						continue;
					}
					$slug = (string) $sub[2];
					$key  = $parent . '::' . $slug;
					$catalog[ $key ] = array(
						'slug'   => $slug,
						'parent' => (string) $parent,
						'title'  => wp_strip_all_tags( (string) ( $sub[0] ?? $slug ) ),
						'type'   => 'submenu',
					);
				}
			}
		}

		uasort(
			$catalog,
			static function ( $a, $b ) {
				return strcmp( $a['title'], $b['title'] );
			}
		);
		return $catalog;
	}

	public static function enforce_menus() {
		if ( ! AL_Plugin::licensed() ) {
			return;
		}
		$denied = self::denied_for_user();
		if ( empty( $denied ) ) {
			return;
		}

		global $menu, $submenu;
		if ( is_array( $menu ) ) {
			foreach ( $menu as $i => $item ) {
				$slug = (string) ( $item[2] ?? '' );
				if ( $slug && in_array( $slug, $denied, true ) ) {
					unset( $menu[ $i ] );
				}
			}
		}
		if ( is_array( $submenu ) ) {
			foreach ( $submenu as $parent => $items ) {
				if ( in_array( (string) $parent, $denied, true ) ) {
					unset( $submenu[ $parent ] );
					continue;
				}
				foreach ( (array) $items as $j => $sub ) {
					$slug = (string) ( $sub[2] ?? '' );
					$key  = $parent . '::' . $slug;
					if ( in_array( $slug, $denied, true ) || in_array( $key, $denied, true ) ) {
						unset( $submenu[ $parent ][ $j ] );
					}
				}
			}
		}
	}

	public static function block_direct_url() {
		if ( ! AL_Plugin::licensed() ) {
			return;
		}
		if ( wp_doing_ajax() || wp_doing_cron() ) {
			return;
		}
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore
		if ( ! $page ) {
			return;
		}
		$denied = self::denied_for_user();
		if ( in_array( $page, $denied, true ) ) {
			wp_die( esc_html__( 'شما به این بخش دسترسی ندارید.', 'access-levels' ), 403 );
		}
	}
}
