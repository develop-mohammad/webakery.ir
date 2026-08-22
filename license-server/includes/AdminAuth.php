<?php
/**
 * مدیریت یوزرنیم و رمز عبور مدیر پنل لایسنس.
 *
 * منبع اصلی: data/admin-auth.json (رمز با hash)
 * Fallback ورود: ADMIN_USER / ADMIN_PASS داخل config.php
 * هنگام تغییر رمز: هر دو منبع هم‌زمان به‌روز می‌شوند تا ورود قطع نشود.
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

	private static function config_path(): string {
		return dirname( __DIR__ ) . '/config.php';
	}

	public static function has_auth_file(): bool {
		$path = self::file_path();
		if ( ! is_readable( $path ) ) {
			return false;
		}
		$raw  = file_get_contents( $path );
		$data = json_decode( (string) $raw, true );
		return is_array( $data ) && ! empty( $data['username'] );
	}

	/**
	 * @return array{username:string,password:string}
	 */
	private static function config_credentials(): array {
		$user = defined( 'ADMIN_USER' ) ? (string) ADMIN_USER : 'admin';
		$pass = defined( 'ADMIN_PASS' ) ? (string) ADMIN_PASS : 'change-this-password';
		return array(
			'username' => $user,
			'password' => $pass,
		);
	}

	/**
	 * خواندن اعتبارنامهٔ فعلی (فایل جدا یا fallback از config.php).
	 *
	 * @return array{username:string,password_hash?:string,password_plain?:string,updated_at?:string}
	 */
	public static function get(): array {
		if ( self::has_auth_file() ) {
			$raw  = file_get_contents( self::file_path() );
			$data = json_decode( (string) $raw, true );
			if ( is_array( $data ) && ! empty( $data['username'] ) ) {
				return $data;
			}
		}

		$cfg = self::config_credentials();
		return array(
			'username'       => $cfg['username'],
			'password_plain' => $cfg['password'],
		);
	}

	public static function username(): string {
		$auth = self::get();
		return (string) ( $auth['username'] ?? 'admin' );
	}

	/**
	 * @param array{username?:string,password_hash?:string,password_plain?:string} $auth
	 */
	private static function matches( array $auth, string $username, string $password ): bool {
		$expected_user = (string) ( $auth['username'] ?? '' );
		if ( $username === '' || $expected_user === '' || ! hash_equals( $expected_user, $username ) ) {
			return false;
		}

		if ( ! empty( $auth['password_hash'] ) ) {
			return password_verify( $password, (string) $auth['password_hash'] );
		}

		$plain = (string) ( $auth['password_plain'] ?? '' );
		return $plain !== '' && hash_equals( $plain, $password );
	}

	/**
	 * بررسی ورود مدیر.
	 * اول admin-auth.json، اگر نشد config.php — تا بعد از آپدیت پنل ورود قطع نشود.
	 */
	public static function verify( string $username, string $password ): bool {
		if ( self::matches( self::get(), $username, $password ) ) {
			return true;
		}

		// اگر فایل auth وجود دارد و پسورد فایل جور نبود، هنوز config را هم قبول کن
		if ( self::has_auth_file() ) {
			$cfg = self::config_credentials();
			if ( self::matches(
				array(
					'username'       => $cfg['username'],
					'password_plain' => $cfg['password'],
				),
				$username,
				$password
			) ) {
				return true;
			}
		}

		// سازگاری با پیش‌فرض قدیمی change-password
		$cfg = self::config_credentials();
		$legacy_defaults = array( 'change-this-password', 'change-password' );
		if (
			hash_equals( $cfg['username'], $username )
			&& in_array( $cfg['password'], $legacy_defaults, true )
			&& in_array( $password, $legacy_defaults, true )
		) {
			return true;
		}

		return false;
	}

	/**
	 * تأیید رمز فعلی (برای فرم تغییر حساب).
	 * رمز فایل auth یا رمز config هر دو پذیرفته می‌شوند.
	 */
	public static function verify_current_password( string $password ): bool {
		if ( self::verify( self::username(), $password ) ) {
			return true;
		}
		$cfg = self::config_credentials();
		return self::matches(
			array(
				'username'       => $cfg['username'],
				'password_plain' => $cfg['password'],
			),
			$cfg['username'],
			$password
		);
	}

	/**
	 * نوشتن data/admin-auth.json
	 *
	 * @param array<string,mixed> $payload
	 * @return array{success:bool,message:string}
	 */
	private static function write_auth_file( array $payload ): array {
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
		return array( 'success' => true, 'message' => '' );
	}

	/**
	 * هم‌سان‌سازی ADMIN_USER / ADMIN_PASS داخل config.php (اگر قابل نوشتن باشد).
	 * برای سازگاری با نسخه‌های قدیمی پنل که فقط از config می‌خوانند.
	 */
	private static function sync_config_credentials( string $username, string $password ): string {
		$path = self::config_path();
		if ( ! is_readable( $path ) ) {
			return '';
		}
		if ( ! is_writable( $path ) ) {
			return ' (توجه: config.php قابل نوشتن نبود — فقط admin-auth.json به‌روز شد)';
		}

		$content = file_get_contents( $path );
		if ( $content === false || $content === '' ) {
			return ' (توجه: خواندن config.php ناموفق بود)';
		}

		$original = $content;
		$user_php = "'" . str_replace( array( '\\', "'" ), array( '\\\\', "\\'" ), $username ) . "'";
		$pass_php = "'" . str_replace( array( '\\', "'" ), array( '\\\\', "\\'" ), $password ) . "'";

		$content = preg_replace(
			"/define\(\s*'ADMIN_USER'\s*,\s*'[^']*'\s*\)/",
			"define( 'ADMIN_USER', {$user_php} )",
			$content,
			1
		);
		$content = preg_replace(
			"/define\(\s*'ADMIN_PASS'\s*,\s*'[^']*'\s*\)/",
			"define( 'ADMIN_PASS', {$pass_php} )",
			$content,
			1
		);

		if ( ! is_string( $content ) || $content === $original ) {
			// شاید با کوتیشن دبل تعریف شده
			$content = $original;
			$content = preg_replace(
				'/define\(\s*"ADMIN_USER"\s*,\s*"(?:[^"\\\\]|\\\\.)*"\s*\)/',
				'define( "ADMIN_USER", "' . addcslashes( $username, '"\\' ) . '" )',
				$content,
				1
			);
			$content = preg_replace(
				'/define\(\s*"ADMIN_PASS"\s*,\s*"(?:[^"\\\\]|\\\\.)*"\s*\)/',
				'define( "ADMIN_PASS", "' . addcslashes( $password, '"\\' ) . '" )',
				$content,
				1
			);
		}

		if ( ! is_string( $content ) || $content === $original ) {
			return ' (توجه: الگوی ADMIN_USER/ADMIN_PASS در config.php پیدا نشد)';
		}

		if ( file_put_contents( $path, $content, LOCK_EX ) === false ) {
			return ' (توجه: نوشتن config.php ناموفق بود — فقط admin-auth.json به‌روز شد)';
		}

		return ' — config.php هم هم‌سان شد';
	}

	/**
	 * تنظیم اجباری یوزر/رمز (برای ابزار ریست اضطراری).
	 *
	 * @return array{success:bool,message:string}
	 */
	public static function force_set( string $username, string $password ): array {
		$username = trim( $username );
		if ( $username === '' || ! preg_match( '/^[A-Za-z0-9._@-]{3,64}$/', $username ) ) {
			return array( 'success' => false, 'message' => 'نام کاربری معتبر نیست (۳ تا ۶۴ کاراکتر: حروف، عدد، . _ @ -).' );
		}
		if ( strlen( $password ) < 6 ) {
			return array( 'success' => false, 'message' => 'رمز عبور باید حداقل ۶ کاراکتر باشد.' );
		}

		$payload = array(
			'username'      => $username,
			'password_hash' => password_hash( $password, PASSWORD_DEFAULT ),
			'updated_at'    => gmdate( 'c' ),
		);

		$write = self::write_auth_file( $payload );
		if ( ! $write['success'] ) {
			return $write;
		}

		$sync_note = self::sync_config_credentials( $username, $password );

		return array(
			'success' => true,
			'message' => '✅ حساب مدیر ریست شد. با یوزرنیم/رمز جدید وارد پنل شوید.' . $sync_note,
		);
	}

	/**
	 * ذخیرهٔ یوزرنیم/رمز جدید — admin-auth.json + هم‌سان‌سازی config.php.
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

		$final_password = null;
		$password_hash  = $auth['password_hash'] ?? null;

		if ( $new_pass !== '' ) {
			if ( $new_pass !== $confirm_pass ) {
				return array( 'success' => false, 'message' => '❌ رمز عبور جدید و تکرار آن یکسان نیستند.' );
			}
			if ( strlen( $new_pass ) < 6 ) {
				return array( 'success' => false, 'message' => '❌ رمز عبور جدید باید حداقل ۶ کاراکتر باشد.' );
			}
			$password_hash  = password_hash( $new_pass, PASSWORD_DEFAULT );
			$final_password = $new_pass;
			$changed[]      = 'رمز عبور';
		} else {
			// فقط یوزرنیم عوض شده — برای sync کردن config به رمز فعلی نیاز داریم
			$final_password = $current_pass;
		}

		if ( empty( $changed ) ) {
			return array( 'success' => false, 'message' => '⚠️ چیزی تغییر نکرد (مقدار جدید با قبلی یکسان است).' );
		}

		$payload = array(
			'username'      => $username,
			'password_hash' => $password_hash ?: password_hash( (string) $final_password, PASSWORD_DEFAULT ),
			'updated_at'    => gmdate( 'c' ),
		);

		$write = self::write_auth_file( $payload );
		if ( ! $write['success'] ) {
			return $write;
		}

		$sync_note = self::sync_config_credentials( $username, (string) $final_password );

		return array(
			'success' => true,
			'message' => '✅ ' . implode( ' و ', $changed ) . ' با موفقیت تغییر یافت. از ورود بعدی از مقادیر جدید استفاده کنید.' . $sync_note,
		);
	}
}
