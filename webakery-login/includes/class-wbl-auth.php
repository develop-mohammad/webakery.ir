<?php
defined( 'ABSPATH' ) || exit;

/**
 * ورود / ثبت‌نام کاربر وردپرس.
 */
class WBL_Auth {

	const META_PHONE  = 'wbl_phone';
	const META_GOOGLE = 'wbl_google_id';

	/**
	 * ورود یا ثبت‌نام با شماره موبایل پس از تأیید OTP.
	 *
	 * @param string $phone
	 * @return array|WP_Error
	 */
	public static function login_by_phone( $phone ) {
		$phone = WBL_OTP::normalize_phone( $phone );
		if ( is_wp_error( $phone ) ) {
			return $phone;
		}

		$user = self::find_by_phone( $phone );
		if ( ! $user ) {
			if ( ! (int) WBL_Settings::get( 'auto_register', 1 ) ) {
				return new WP_Error( 'wbl_no_user', 'حسابی با این شماره یافت نشد. ابتدا ثبت‌نام کنید.' );
			}
			$user = self::create_from_phone( $phone );
			if ( is_wp_error( $user ) ) {
				return $user;
			}
		} else {
			// همگام‌سازی متای استاندارد.
			update_user_meta( $user->ID, self::META_PHONE, $phone );
			update_user_meta( $user->ID, 'billing_phone', $phone );
		}

		return self::sign_in( $user, 'phone' );
	}

	/**
	 * ورود یا ثبت‌نام با اطلاعات گوگل.
	 *
	 * @param array $info {email,name,id,picture}
	 * @return array|WP_Error
	 */
	public static function login_by_google( array $info ) {
		$email = strtolower( sanitize_email( $info['email'] ?? '' ) );
		if ( ! $email || ! is_email( $email ) ) {
			return new WP_Error( 'wbl_google_email', 'ایمیل گوگل معتبر نیست.' );
		}
		if ( empty( $info['verified'] ) ) {
			return new WP_Error( 'wbl_google_unverified', 'ایمیل گوگل تأیید نشده است.' );
		}

		$gid  = sanitize_text_field( $info['id'] ?? '' );
		$user = $gid ? self::find_by_google_id( $gid ) : null;
		if ( ! $user ) {
			$user = get_user_by( 'email', $email );
		}

		if ( ! $user ) {
			if ( ! (int) WBL_Settings::get( 'auto_register', 1 ) ) {
				return new WP_Error( 'wbl_no_user', 'حسابی با این ایمیل یافت نشد.' );
			}
			$user = self::create_from_google( $email, $info );
			if ( is_wp_error( $user ) ) {
				return $user;
			}
		} else {
			if ( $gid ) {
				update_user_meta( $user->ID, self::META_GOOGLE, $gid );
			}
			if ( ! empty( $info['name'] ) && ( ! $user->display_name || $user->display_name === $user->user_login ) ) {
				wp_update_user(
					array(
						'ID'           => $user->ID,
						'display_name' => sanitize_text_field( $info['name'] ),
					)
				);
			}
		}

		return self::sign_in( $user, 'google' );
	}

	/**
	 * @param WP_User $user
	 * @param string  $method
	 * @return array
	 */
	public static function sign_in( WP_User $user, $method = 'phone' ) {
		wp_set_current_user( $user->ID );
		wp_set_auth_cookie( $user->ID, true );
		do_action( 'wp_login', $user->user_login, $user );
		do_action( 'wbl_user_logged_in', $user, $method );

		return array(
			'ok'       => true,
			'user_id'  => $user->ID,
			'redirect' => self::redirect_url(),
			'message'  => 'ورود موفق بود.',
		);
	}

	public static function redirect_url( $override = '' ) {
		if ( $override ) {
			$u = esc_url_raw( $override );
			if ( $u && wp_validate_redirect( $u, false ) ) {
				return $u;
			}
		}
		$custom = trim( (string) WBL_Settings::get( 'redirect_after', '' ) );
		if ( $custom ) {
			return esc_url_raw( $custom );
		}
		if ( function_exists( 'wc_get_page_permalink' ) ) {
			$url = wc_get_page_permalink( 'myaccount' );
			if ( $url ) {
				return $url;
			}
		}
		return home_url( '/' );
	}

	/**
	 * جستجو با فرمت‌های رایج شماره.
	 *
	 * @param string $phone 09xxxxxxxxx
	 * @return WP_User|null
	 */
	public static function find_by_phone( $phone ) {
		$variants = array_unique(
			array(
				$phone,
				substr( $phone, 1 ),                 // 9xxxxxxxxx
				'+98' . substr( $phone, 1 ),
				'98' . substr( $phone, 1 ),
				'0098' . substr( $phone, 1 ),
			)
		);

		$meta_keys = array( self::META_PHONE, 'billing_phone', 'digits_phone', 'phone' );

		foreach ( $meta_keys as $meta_key ) {
			foreach ( $variants as $val ) {
				$users = get_users(
					array(
						'meta_key'    => $meta_key,
						'meta_value'  => $val,
						'number'      => 1,
						'count_total' => false,
					)
				);
				if ( ! empty( $users[0] ) ) {
					return $users[0];
				}
			}
		}

		return null;
	}

	public static function find_by_google_id( $gid ) {
		$users = get_users(
			array(
				'meta_key'    => self::META_GOOGLE,
				'meta_value'  => $gid,
				'number'      => 1,
				'count_total' => false,
			)
		);
		return ! empty( $users[0] ) ? $users[0] : null;
	}

	/**
	 * @param string $phone
	 * @return WP_User|WP_Error
	 */
	public static function create_from_phone( $phone ) {
		$role     = WBL_Settings::get( 'default_role', 'subscriber' );
		$login    = 'u' . $phone;
		$password = wp_generate_password( 24, true, true );
		$host     = wp_parse_url( home_url(), PHP_URL_HOST );
		$host     = $host ? preg_replace( '/^www\./', '', $host ) : 'example.com';
		$email    = 'sms.' . $phone . '@' . $host;

		if ( username_exists( $login ) ) {
			$login = 'u' . $phone . '_' . wp_rand( 100, 999 );
		}
		if ( email_exists( $email ) ) {
			$email = 'sms.' . $phone . '.' . wp_rand( 10, 99 ) . '@' . $host;
		}

		$uid = wp_insert_user(
			array(
				'user_login'   => $login,
				'user_pass'    => $password,
				'user_email'   => $email,
				'display_name' => $phone,
				'nickname'     => $phone,
				'role'         => $role,
			)
		);

		if ( is_wp_error( $uid ) ) {
			return $uid;
		}

		update_user_meta( $uid, self::META_PHONE, $phone );
		update_user_meta( $uid, 'billing_phone', $phone );
		do_action( 'wbl_user_registered', $uid, 'phone', $phone );

		return get_user_by( 'id', $uid );
	}

	/**
	 * @param string $email
	 * @param array  $info
	 * @return WP_User|WP_Error
	 */
	public static function create_from_google( $email, array $info ) {
		$role  = WBL_Settings::get( 'default_role', 'subscriber' );
		$base  = sanitize_user( current( explode( '@', $email ) ), true );
		$login = $base ?: 'google_user';
		if ( username_exists( $login ) ) {
			$login = $login . '_' . wp_rand( 1000, 9999 );
		}

		$name = sanitize_text_field( $info['name'] ?? $login );
		$uid  = wp_insert_user(
			array(
				'user_login'   => $login,
				'user_pass'    => wp_generate_password( 24, true, true ),
				'user_email'   => $email,
				'display_name' => $name,
				'nickname'     => $name,
				'first_name'   => sanitize_text_field( $info['given_name'] ?? '' ),
				'last_name'    => sanitize_text_field( $info['family_name'] ?? '' ),
				'role'         => $role,
			)
		);

		if ( is_wp_error( $uid ) ) {
			return $uid;
		}

		if ( ! empty( $info['id'] ) ) {
			update_user_meta( $uid, self::META_GOOGLE, sanitize_text_field( $info['id'] ) );
		}
		if ( ! empty( $info['picture'] ) ) {
			update_user_meta( $uid, 'wbl_google_picture', esc_url_raw( $info['picture'] ) );
		}
		do_action( 'wbl_user_registered', $uid, 'google', $email );

		return get_user_by( 'id', $uid );
	}
}
