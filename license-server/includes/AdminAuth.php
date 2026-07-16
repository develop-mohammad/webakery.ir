<?php
/**
 * مدیریت یوزرنیم و رمز عبور مدیر پنل لایسنس.
 * اعتبارنامه‌ها در data/admin-auth.json ذخیره می‌شوند تا config.php دست نخورده بماند.
 */

class AdminAuth {

	/** @var string */
	private static $file;

	private static function file_path(): string {
		if ( ! self::$file ) {
			self::$file = dirname( __DIR__ ) . '/data/admin-auth.json';
		}
		return self::$file;
	}

	/**
	 * خواندن اعتبارنامهٔ فعلی (فایل جدا یا fallback از config.php).
	 *
	 * @return array{username:string,password_hash?:string,password_plain?:string,updated_at?:string}
	 */
	public static function get(): array {
		$path = self::file_path();
		if ( is_readable( $path ) ) {
			$raw = file_get_contents( $path );
			$data = json_decode( (string) $raw, true );
			if ( is_array( $data ) && ! empty( $data['username'] ) ) {
				return $data;
			}
		}

		$user = defined( 'ADMIN_USER' ) ? (string) ADMIN_USER : 'admin';
		$pass = defined( 'ADMIN_PASS' ) ? (string) ADMIN_PASS : 'change-this-password';

		return array(
			'username'       => $user,
			'password_plain' => $pass,
		);
	}

	public static function username(): string {
		$auth = self::get();
		return (string) ( $auth['username'] ?? 'admin' );
	}

	/**
	 * بررسی ورود مدیر.
	 */
	public static function verify( string $username, string $password ): bool {
		$auth = self::get();
		$expected_user = (string) ( $auth['username'] ?? '' );
		if ( $username === '' || ! hash_equals( $expected_user, $username ) ) {
			return false;
		}

		if ( ! empty( $auth['password_hash'] ) ) {
			return password_verify( $password, (string) $auth['password_hash'] );
		}

		$plain = (string) ( $auth['password_plain'] ?? '' );
		return $plain !== '' && hash_equals( $plain, $password );
	}

	/**
	 * تأیید رمز فعلی (برای فرم تغییر حساب).
	 */
	public static function verify_current_password( string $password ): bool {
		return self::verify( self::username(), $password );
	}

	/**
	 * ذخیرهٔ یوزرنیم/رمز جدید بدون تغییر config.php.
	 *
	 * @return array{success:bool,message:string}
	 */
	public static function update( string $current_pass, string $new_user, string $new_pass, string $confirm_pass ): array {
		if ( ! self::verify_current_password( $current_pass ) ) {
			return array( 'success' => false, 'message' => '❌ رمز عبور فعلی اشتباه است.' );
		}

		$new_user = trim( $new_user );
		$auth     = self::get();
		$username = (string) $auth['username'];
		$changed  = array();

		if ( $new_user === '' && $new_pass === '' ) {
			return array( 'success' => false, 'message' => '⚠️ چیزی برای تغییر وارد نکردید.' );
		}

		if ( $new_user !== '' ) {
			if ( ! preg_match( '/^[A-Za-z0-9._@-]{3,64}$/', $new_user ) ) {
				return array(
					'success' => false,
					'message' => '❌ نام کاربری باید ۳ تا ۶۴ کاراکتر و فقط شامل حروف، عدد و . _ @ - باشد.',
				);
			}
			if ( $new_user !== $username ) {
				$username  = $new_user;
				$changed[] = 'نام کاربری';
			}
		}

		$password_hash = $auth['password_hash'] ?? null;
		$password_plain = $auth['password_plain'] ?? null;

		if ( $new_pass !== '' ) {
			if ( $new_pass !== $confirm_pass ) {
				return array( 'success' => false, 'message' => '❌ رمز عبور جدید و تکرار آن یکسان نیستند.' );
			}
			if ( strlen( $new_pass ) < 6 ) {
				return array( 'success' => false, 'message' => '❌ رمز عبور جدید باید حداقل ۶ کاراکتر باشد.' );
			}
			$password_hash  = password_hash( $new_pass, PASSWORD_DEFAULT );
			$password_plain = null;
			$changed[]      = 'رمز عبور';
		}

		if ( empty( $changed ) ) {
			return array( 'success' => false, 'message' => '⚠️ چیزی تغییر نکرد (مقدار جدید با قبلی یکسان است).' );
		}

		// اگر هنوز فقط plain از config داریم و فقط یوزرنیم عوض شده، همان plain را نگه می‌داریم
		if ( empty( $password_hash ) && $password_plain !== null && $password_plain !== '' ) {
			$payload = array(
				'username'       => $username,
				'password_plain' => $password_plain,
				'updated_at'     => gmdate( 'c' ),
			);
		} else {
			$payload = array(
				'username'      => $username,
				'password_hash' => $password_hash,
				'updated_at'    => gmdate( 'c' ),
			);
		}

		$dir = dirname( self::file_path() );
		if ( ! is_dir( $dir ) && ! mkdir( $dir, 0755, true ) && ! is_dir( $dir ) ) {
			return array( 'success' => false, 'message' => '❌ پوشه data قابل ایجاد نیست.' );
		}

		$json = json_encode( $payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );
		if ( $json === false ) {
			return array( 'success' => false, 'message' => '❌ ساخت فایل اعتبارنامه ناموفق بود.' );
		}

		$tmp = self::file_path() . '.tmp';
		if ( file_put_contents( $tmp, $json . "\n", LOCK_EX ) === false ) {
			return array( 'success' => false, 'message' => '❌ نوشتن فایل موقت ناموفق بود.' );
		}
		if ( ! rename( $tmp, self::file_path() ) ) {
			@unlink( $tmp );
			return array( 'success' => false, 'message' => '❌ ذخیره فایل admin-auth.json ناموفق بود — مجوز پوشه data را بررسی کنید.' );
		}

		@chmod( self::file_path(), 0640 );

		return array(
			'success' => true,
			'message' => '✅ ' . implode( ' و ', $changed ) . ' با موفقیت تغییر یافت. از ورود بعدی از مقادیر جدید استفاده کنید. (تنظیمات config.php دست نخورده ماند)',
		);
	}
}
