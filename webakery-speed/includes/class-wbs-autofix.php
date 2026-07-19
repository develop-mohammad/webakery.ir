<?php
defined( 'ABSPATH' ) || exit;

/**
 * اصلاح خودکار موارد امن سرعت روی HTML خروجی.
 * موارد پرریسک (مثل تأخیر کامل JS) عمداً اینجا نیست — Perfmatters/Rocket آن‌ها را دارند.
 */
class WBS_AutoFix {

	const OPTION = 'wbs_autofix';

	/** @var self|null */
	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public static function defaults() {
		return array(
			'enabled'              => 1,
			'image_dimensions'     => 1,
			'lazy_images'          => 1,
			'lcp_priority'         => 1,
			'async_icon_css'       => 1,
			'strip_google_fonts'   => 0, // فونت‌ماژول جداگانه این را اجباری دارد.
			'apply_from_scan'      => 1,
		);
	}

	public static function settings() {
		return wp_parse_args( (array) get_option( self::OPTION, array() ), self::defaults() );
	}

	public static function activate() {
		if ( false === get_option( self::OPTION, false ) ) {
			add_option( self::OPTION, self::defaults(), '', false );
		}
	}

	private function __construct() {
		add_action( 'admin_menu', array( $this, 'menu' ), 22 );
		add_action( 'admin_post_wbs_save_autofix', array( $this, 'save' ) );
		add_action( 'admin_post_wbs_autofix_from_scan', array( $this, 'enable_from_scan' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );

		$s = self::settings();
		if ( empty( $s['enabled'] ) ) {
			return;
		}
		add_action( 'template_redirect', array( $this, 'start_buffer' ), -9985 );
	}

	public function menu() {
		add_submenu_page(
			'webakery-speed',
			'اصلاح خودکار',
			'اصلاح خودکار',
			'manage_options',
			'webakery-speed-autofix',
			array( $this, 'render' )
		);
	}

	public function assets( $hook ) {
		if ( 'webakery-speed_page_webakery-speed-autofix' !== $hook ) {
			return;
		}
		wp_enqueue_style( 'wbs-board-admin', WBS_URL . 'assets/board-admin.css', array(), WBS_VERSION );
	}

	public function save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Forbidden' );
		}
		check_admin_referer( 'wbs_save_autofix' );
		$input = isset( $_POST['wbs_autofix'] ) && is_array( $_POST['wbs_autofix'] ) ? wp_unslash( $_POST['wbs_autofix'] ) : array(); // phpcs:ignore
		$out   = array(
			'enabled'            => ! empty( $input['enabled'] ) ? 1 : 0,
			'image_dimensions'   => ! empty( $input['image_dimensions'] ) ? 1 : 0,
			'lazy_images'        => ! empty( $input['lazy_images'] ) ? 1 : 0,
			'lcp_priority'       => ! empty( $input['lcp_priority'] ) ? 1 : 0,
			'async_icon_css'     => ! empty( $input['async_icon_css'] ) ? 1 : 0,
			'strip_google_fonts' => ! empty( $input['strip_google_fonts'] ) ? 1 : 0,
			'apply_from_scan'    => ! empty( $input['apply_from_scan'] ) ? 1 : 0,
		);
		update_option( self::OPTION, $out, false );
		wp_safe_redirect( admin_url( 'admin.php?page=webakery-speed-autofix&saved=1' ) );
		exit;
	}

	/**
	 * بر اساس آخرین اسکن، اصلاح‌های مرتبط را روشن کن.
	 */
	public function enable_from_scan() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Forbidden' );
		}
		check_admin_referer( 'wbs_autofix_from_scan' );
		$scan = (array) get_option( WBS_Board::OPTION_SCAN, array() );
		$s    = self::settings();
		$s['enabled'] = 1;

		$steps = isset( $scan['steps'] ) && is_array( $scan['steps'] ) ? $scan['steps'] : array();
		if ( ! empty( $steps['image_dimensions'] ) && in_array( $steps['image_dimensions']['status'], array( 'warn', 'bad' ), true ) ) {
			$s['image_dimensions'] = 1;
		}
		if ( ! empty( $steps['lcp_images'] ) && in_array( $steps['lcp_images']['status'], array( 'warn', 'bad', 'todo' ), true ) ) {
			$s['lcp_priority'] = 1;
			$s['lazy_images']  = 1;
		}
		if ( ! empty( $steps['image_format'] ) || ! empty( $steps['image_weight'] ) ) {
			$s['lazy_images'] = 1;
		}
		if ( ! empty( $steps['fonts'] ) && in_array( $steps['fonts']['status'], array( 'warn', 'bad' ), true ) ) {
			$s['async_icon_css'] = 1;
		}
		if ( ! empty( $steps['render_blocking'] ) && in_array( $steps['render_blocking']['status'], array( 'warn', 'bad' ), true ) ) {
			$s['async_icon_css'] = 1;
		}

		update_option( self::OPTION, $s, false );
		wp_safe_redirect( admin_url( 'admin.php?page=webakery-speed-autofix&applied=1' ) );
		exit;
	}

	public function start_buffer() {
		if ( is_admin() || is_feed() || is_preview() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}
		ob_start( array( $this, 'filter_html' ) );
	}

	/**
	 * @param string $html
	 * @return string
	 */
	public function filter_html( $html ) {
		if ( ! is_string( $html ) || strlen( $html ) < 50 || false === stripos( $html, '<html' ) ) {
			return $html;
		}
		$s = self::settings();
		if ( empty( $s['enabled'] ) ) {
			return $html;
		}

		$html = $this->fix_images( $html, $s );

		if ( ! empty( $s['async_icon_css'] ) ) {
			$html = $this->async_icon_css( $html );
		}

		if ( ! empty( $s['strip_google_fonts'] ) ) {
			$html = preg_replace( '#<link\b[^>]+fonts\.googleapis\.com[^>]*>#i', '', $html );
			$html = preg_replace( '#<link\b[^>]+fonts\.gstatic\.com[^>]*>#i', '', $html );
		}

		if ( false === stripos( $html, 'WBS_AUTOFIX=1' ) ) {
			$html = preg_replace( '#</head>#i', "<!-- WBS_AUTOFIX=1 -->\n</head>", $html, 1 );
		}

		return $html;
	}

	/**
	 * @param string $html
	 * @param array  $s
	 * @return string
	 */
	private function fix_images( $html, $s ) {
		$index = 0;
		return preg_replace_callback(
			'#<img\b([^>]*)>#i',
			function ( $m ) use ( &$index, $s ) {
				$attrs = $m[1];
				$index++;
				$is_first = ( 1 === $index );

				$src = '';
				if ( preg_match( '#\bsrc=[\'"]([^\'"]+)#i', $attrs, $sm ) ) {
					$src = $sm[1];
				} elseif ( preg_match( '#\bdata-src=[\'"]([^\'"]+)#i', $attrs, $sm ) ) {
					$src = $sm[1];
				}
				if ( ! $src || 0 === strpos( $src, 'data:' ) ) {
					return $m[0];
				}

				if ( ! empty( $s['image_dimensions'] ) && ! preg_match( '#\bwidth=#i', $attrs ) ) {
					$dims = $this->guess_dimensions( $src );
					if ( $dims ) {
						$attrs .= ' width="' . (int) $dims[0] . '" height="' . (int) $dims[1] . '"';
					}
				}

				if ( ! empty( $s['lcp_priority'] ) && $is_first ) {
					if ( ! preg_match( '#\bfetchpriority=#i', $attrs ) ) {
						$attrs .= ' fetchpriority="high"';
					}
					// LCP candidate should not be lazy.
					$attrs = preg_replace( '#\sloading=[\'"]lazy[\'"]#i', '', $attrs );
					if ( ! preg_match( '#\bloading=#i', $attrs ) ) {
						$attrs .= ' loading="eager"';
					}
					if ( ! preg_match( '#\bdecoding=#i', $attrs ) ) {
						$attrs .= ' decoding="async"';
					}
				} elseif ( ! empty( $s['lazy_images'] ) && ! $is_first ) {
					if ( ! preg_match( '#\bloading=#i', $attrs ) ) {
						$attrs .= ' loading="lazy"';
					}
					if ( ! preg_match( '#\bdecoding=#i', $attrs ) ) {
						$attrs .= ' decoding="async"';
					}
				}

				return '<img' . $attrs . '>';
			},
			$html
		);
	}

	/**
	 * @param string $src
	 * @return array{0:int,1:int}|null
	 */
	private function guess_dimensions( $src ) {
		$path = $this->url_to_path( $src );
		if ( ! $path || ! is_readable( $path ) ) {
			return null;
		}
		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$info = @getimagesize( $path );
		if ( ! is_array( $info ) || empty( $info[0] ) || empty( $info[1] ) ) {
			return null;
		}
		return array( (int) $info[0], (int) $info[1] );
	}

	private function url_to_path( $url ) {
		$url = strtok( $url, '?' );
		$content_url = content_url();
		if ( 0 === strpos( $url, $content_url ) ) {
			return wp_normalize_path( WP_CONTENT_DIR . substr( $url, strlen( $content_url ) ) );
		}
		$uploads = wp_upload_dir();
		if ( ! empty( $uploads['baseurl'] ) && 0 === strpos( $url, $uploads['baseurl'] ) ) {
			return wp_normalize_path( $uploads['basedir'] . substr( $url, strlen( $uploads['baseurl'] ) ) );
		}
		$home = untrailingslashit( home_url() );
		if ( 0 === strpos( $url, $home ) ) {
			return wp_normalize_path( ABSPATH . ltrim( substr( $url, strlen( $home ) ), '/' ) );
		}
		return '';
	}

	/**
	 * Async-load icon/font CSS that commonly blocks render.
	 *
	 * @param string $html
	 * @return string
	 */
	private function async_icon_css( $html ) {
		return preg_replace_callback(
			'#<link\b([^>]*rel=[\'"]stylesheet[\'"][^>]*)>#i',
			static function ( $m ) {
				$tag = $m[0];
				if ( ! preg_match( '#href=[\'"]([^\'"]+)#i', $tag, $hm ) ) {
					return $tag;
				}
				$href = $hm[1];
				if ( ! preg_match( '#fontawesome|fa-solid|fa-brands|dashicons|eicons|elementor-icons|tinvwl|flaticon#i', $href ) ) {
					return $tag;
				}
				if ( false !== stripos( $tag, 'onload=' ) || preg_match( '#media=[\'"]print[\'"]#i', $tag ) ) {
					return $tag;
				}
				// media=print + onload swap — الگوی رایج غیرمسدودکننده.
				$tag = preg_replace( '#\smedia=[\'"][^\'"]*[\'"]#i', '', $tag );
				$tag = str_replace( '<link', '<link media="print" onload="this.media=\'all\'"', $tag );
				return $tag;
			},
			$html
		);
	}

	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$s = self::settings();
		?>
		<div class="wrap wbsb-wrap">
			<div class="wbsb-hero">
				<div>
					<h1>اصلاح خودکار</h1>
					<p>پلاگین می‌تواند بخشی از مشکلات اولویت‌دار را روی HTML سایت خودش اصلاح کند. تبدیل کامل WebP یا بهینه‌سازی سنگین JS/CSS را به Perfmatters / WebP Express بسپار.</p>
				</div>
				<span class="wbsb-badge">AutoFix · v<?php echo esc_html( WBS_VERSION ); ?></span>
			</div>
			<p class="wbsb-navline">
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=webakery-speed' ) ); ?>">اولویت‌ها</a>
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=webakery-speed-cwv' ) ); ?>">گوگل / CWV</a>
				<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=webakery-speed-autofix' ) ); ?>">اصلاح خودکار</a>
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=webakery-speed-fonts' ) ); ?>">فونت سوییپ</a>
			</p>

			<?php if ( ! empty( $_GET['saved'] ) || ! empty( $_GET['applied'] ) ) : // phpcs:ignore ?>
				<div class="wbsb-flash">تنظیمات ذخیره شد. کش سایت را پاک کن و صفحه را تست کن. در View Source باید <code>WBS_AUTOFIX=1</code> دیده شود.</div>
			<?php endif; ?>

			<section class="wbsb-card accent">
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( 'wbs_autofix_from_scan' ); ?>
					<input type="hidden" name="action" value="wbs_autofix_from_scan" />
					<div class="wbsb-scanbar">
						<div>
							<strong>اعمال اصلاح بر اساس آخرین اسکن</strong>
							<small>موارد مرتبط با تصویر/فونت/آیکون‌CSS را از نتیجه اسکن اولویت‌ها روشن می‌کند.</small>
						</div>
						<button class="button button-hero button-primary">روشن کردن اصلاح‌های مرتبط</button>
					</div>
				</form>
			</section>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="wbsb-card">
				<?php wp_nonce_field( 'wbs_save_autofix' ); ?>
				<input type="hidden" name="action" value="wbs_save_autofix" />
				<h2>قابلیت‌های فعال</h2>
				<label class="wbsb-check"><input type="checkbox" name="wbs_autofix[enabled]" value="1" <?php checked( ! empty( $s['enabled'] ) ); ?> /> <strong>فعال‌سازی اصلاح خودکار</strong></label>
				<label class="wbsb-check"><input type="checkbox" name="wbs_autofix[image_dimensions]" value="1" <?php checked( ! empty( $s['image_dimensions'] ) ); ?> /> افزودن width/height به تصاویر بدون ابعاد (کمک به CLS)</label>
				<label class="wbsb-check"><input type="checkbox" name="wbs_autofix[lazy_images]" value="1" <?php checked( ! empty( $s['lazy_images'] ) ); ?> /> lazy-load برای تصاویر غیر اول</label>
				<label class="wbsb-check"><input type="checkbox" name="wbs_autofix[lcp_priority]" value="1" <?php checked( ! empty( $s['lcp_priority'] ) ); ?> /> fetchpriority=high برای اولین تصویر (کاندید LCP)</label>
				<label class="wbsb-check"><input type="checkbox" name="wbs_autofix[async_icon_css]" value="1" <?php checked( ! empty( $s['async_icon_css'] ) ); ?> /> غیرمسدود کردن CSS آیکون (Font Awesome و مشابه)</label>
				<label class="wbsb-check"><input type="checkbox" name="wbs_autofix[strip_google_fonts]" value="1" <?php checked( ! empty( $s['strip_google_fonts'] ) ); ?> /> حذف Google Fonts از HTML (اگر فونت سوییپ روشن است معمولاً لازم نیست)</label>
				<p class="wbsb-actions"><?php submit_button( 'ذخیره', 'primary', 'submit', false ); ?></p>
			</form>

			<section class="wbsb-card">
				<h2>چه چیزی را خودش اصلاح نمی‌کند؟</h2>
				<ul class="wbsb-todo">
					<li>تبدیل PNG/JPG به WebP/AVIF (نیاز به WebP Express یا مشابه)</li>
					<li>فشرده‌سازی فایل‌های چندمگابایتی روی دیسک</li>
					<li>تأخیر کامل جاوااسکریپت فروشگاهی (Perfmatters بهتر است)</li>
					<li>Forced reflow داخل تم/اسلایدر (نیاز به اصلاح کد تم)</li>
				</ul>
			</section>
		</div>
		<?php
	}
}
