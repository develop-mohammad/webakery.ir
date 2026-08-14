<?php
defined( 'ABSPATH' ) || exit;

/**
 * فونت سوییپ — تشخیص سبک، Preload و font-display:swap با یک تیک.
 */
class WBFS_Plugin {

	const OPTION    = 'wbfs_settings';
	const TRANSIENT = 'wbfs_detected_fonts';

	/** @var self|null */
	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public static function activate() {
		if ( false === get_option( self::OPTION, false ) ) {
			add_option( self::OPTION, self::defaults(), '', false );
		}
	}

	public static function defaults() {
		return array(
			'enabled' => 1,
		);
	}

	public static function settings() {
		return wp_parse_args( (array) get_option( self::OPTION, array() ), self::defaults() );
	}

	private function __construct() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_post_wbfs_rescan', array( $this, 'handle_rescan' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_assets' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( WBFS_FILE ), array( $this, 'action_links' ) );

		if ( ! empty( self::settings()['enabled'] ) ) {
			add_action( 'wp_head', array( $this, 'print_preload_and_swap' ), 1 );
			add_filter( 'style_loader_src', array( $this, 'force_google_display_swap' ), 20, 2 );
		}
	}

	public function menu() {
		add_options_page(
			'فونت سوییپ',
			'فونت سوییپ',
			'manage_options',
			'webakery-font-swap',
			array( $this, 'render_page' )
		);
	}

	public function register_settings() {
		register_setting(
			'wbfs_settings_group',
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => self::defaults(),
			)
		);
	}

	public function sanitize( $input ) {
		return array(
			'enabled' => ! empty( $input['enabled'] ) ? 1 : 0,
		);
	}

	public function admin_assets( $hook ) {
		if ( 'settings_page_webakery-font-swap' !== $hook ) {
			return;
		}
		wp_enqueue_style( 'wbfs-admin', WBFS_URL . 'assets/admin.css', array(), WBFS_VERSION );
	}

	public function action_links( $links ) {
		array_unshift(
			$links,
			'<a href="' . esc_url( admin_url( 'options-general.php?page=webakery-font-swap' ) ) . '"><strong>تنظیمات</strong></a>'
		);
		return $links;
	}

	public function handle_rescan() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'دسترسی غیرمجاز' );
		}
		check_admin_referer( 'wbfs_rescan' );
		delete_transient( self::TRANSIENT );
		$this->detect_fonts( true );
		wp_safe_redirect( admin_url( 'options-general.php?page=webakery-font-swap&scanned=1' ) );
		exit;
	}

	/**
	 * @param bool $force
	 * @return array
	 */
	public function detect_fonts( $force = false ) {
		if ( ! $force ) {
			$cached = get_transient( self::TRANSIENT );
			if ( is_array( $cached ) && isset( $cached['files'] ) ) {
				return $cached;
			}
		}

		$result = array(
			'files'    => array(),
			'families' => array(),
			'faces'    => array(),
			'google'   => array(),
			'sources'  => array(),
			'scanned'  => time(),
		);

		foreach ( $this->collect_css_urls() as $css_url ) {
			$body = $this->fetch_css( $css_url );
			if ( '' === $body ) {
				continue;
			}
			$result['sources'][] = $css_url;
			$this->parse_css_fonts( $body, $css_url, $result );
		}

		$result['files']    = array_values( array_unique( $result['files'] ) );
		$result['families'] = array_values( array_unique( array_filter( $result['families'] ) ) );
		$result['google']   = array_values( array_unique( $result['google'] ) );
		$result['sources']  = array_values( array_unique( $result['sources'] ) );

		// یکتا کردن faces بر اساس family+src
		$uniq  = array();
		$faces = array();
		foreach ( $result['faces'] as $face ) {
			$key = md5( ( $face['family'] ?? '' ) . '|' . ( $face['src'] ?? '' ) . '|' . ( $face['weight'] ?? '' ) );
			if ( isset( $uniq[ $key ] ) ) {
				continue;
			}
			$uniq[ $key ] = true;
			$faces[]      = $face;
		}
		$result['faces'] = $faces;

		set_transient( self::TRANSIENT, $result, 12 * HOUR_IN_SECONDS );
		return $result;
	}

	/** @return string[] */
	private function collect_css_urls() {
		$urls = array();

		// ۱) لینک‌های CSS صفحه اصلی
		$home = wp_remote_get(
			home_url( '/' ),
			array(
				'timeout' => 10,
				'headers' => array( 'Accept' => 'text/html' ),
			)
		);
		if ( ! is_wp_error( $home ) && 200 === (int) wp_remote_retrieve_response_code( $home ) ) {
			$html = (string) wp_remote_retrieve_body( $home );
			if ( preg_match_all( '/<link[^>]+href=[\'"]([^\'"]+)[\'"][^>]*>/i', $html, $m ) ) {
				foreach ( $m[1] as $href ) {
					if ( false !== stripos( $href, 'fonts.googleapis.com' ) || preg_match( '/\.css(\?|$)/i', $href ) ) {
						$urls[] = $this->absolutize_url( $href, home_url( '/' ) );
					}
				}
			}
			if ( preg_match_all( '/@import\s+(?:url\()?[\'"]?([^\'"\);]+)/i', $html, $im ) ) {
				foreach ( $im[1] as $href ) {
					$urls[] = $this->absolutize_url( $href, home_url( '/' ) );
				}
			}
		}

		// ۲) استایل تم
		$urls[] = get_stylesheet_directory_uri() . '/style.css';
		if ( get_template_directory_uri() !== get_stylesheet_directory_uri() ) {
			$urls[] = get_template_directory_uri() . '/style.css';
		}

		// ۳) چند فایل CSS تم (سبک، عمق کم)
		$theme_dir = get_stylesheet_directory();
		$urls      = array_merge( $urls, $this->scan_theme_css( $theme_dir, 2 ) );

		// ۴) CSS سفارشی‌ساز
		if ( wp_get_custom_css() ) {
			$urls[] = 'custom-css://inline';
		}

		// ۵) استایل‌های ثبت‌شده وردپرس (اگر در ادمین هم باشند)
		global $wp_styles;
		if ( $wp_styles instanceof WP_Styles ) {
			foreach ( $wp_styles->registered as $obj ) {
				if ( empty( $obj->src ) ) {
					continue;
				}
				$src = (string) $obj->src;
				if ( 0 === strpos( $src, '//' ) ) {
					$src = ( is_ssl() ? 'https:' : 'http:' ) . $src;
				} elseif ( 0 === strpos( $src, '/' ) ) {
					$src = home_url( $src );
				}
				if ( preg_match( '/\.css(\?|$)/i', $src ) || false !== stripos( $src, 'fonts.googleapis.com' ) ) {
					$urls[] = $src;
				}
			}
		}

		return array_values( array_unique( array_filter( $urls ) ) );
	}

	/**
	 * @param string $dir
	 * @param int    $depth
	 * @return string[]
	 */
	private function scan_theme_css( $dir, $depth = 2 ) {
		$out = array();
		if ( $depth < 0 || ! is_dir( $dir ) ) {
			return $out;
		}
		$skip = array( 'node_modules', 'vendor', '.git', 'assets/js' );
		foreach ( (array) @scandir( $dir ) as $item ) { // phpcs:ignore
			if ( ! $item || '.' === $item[0] ) {
				continue;
			}
			$path = $dir . '/' . $item;
			$rel  = str_replace( '\\', '/', substr( $path, strlen( get_stylesheet_directory() ) ) );
			$bad  = false;
			foreach ( $skip as $s ) {
				if ( false !== stripos( $rel, $s ) ) {
					$bad = true;
					break;
				}
			}
			if ( $bad ) {
				continue;
			}
			if ( is_dir( $path ) ) {
				$out = array_merge( $out, $this->scan_theme_css( $path, $depth - 1 ) );
			} elseif ( preg_match( '/\.css$/i', $item ) ) {
				$url = get_stylesheet_directory_uri() . $rel;
				$out[] = $url;
			}
		}
		return $out;
	}

	private function parse_css_fonts( $body, $css_url, array &$result ) {
		if ( false !== stripos( $css_url, 'fonts.googleapis.com' ) ) {
			$result['google'][] = $css_url;
			if ( preg_match_all( '/family=([^&"\']+)/i', $css_url, $m ) ) {
				foreach ( $m[1] as $fam_raw ) {
					foreach ( explode( '|', rawurldecode( $fam_raw ) ) as $part ) {
						$family = trim( str_replace( '+', ' ', explode( ':', $part )[0] ) );
						if ( $family ) {
							$result['families'][] = $family;
						}
					}
				}
			}
		}

		if ( ! preg_match_all( '/@font-face\s*\{(.*?)\}/is', $body, $faces ) ) {
			return;
		}

		foreach ( $faces[1] as $block ) {
			$family = '';
			$weight = '';
			$style  = '';
			$src    = '';

			if ( preg_match( '/font-family\s*:\s*([^;]+)/i', $block, $fm ) ) {
				$family = trim( $fm[1], " \t\n\r\0\x0B'\"" );
			}
			if ( preg_match( '/font-weight\s*:\s*([^;]+)/i', $block, $wm ) ) {
				$weight = trim( $wm[1] );
			}
			if ( preg_match( '/font-style\s*:\s*([^;]+)/i', $block, $sm ) ) {
				$style = trim( $sm[1] );
			}
			if ( preg_match_all( '/url\(\s*[\'"]?([^\'"\)]+\.(?:woff2|woff|ttf|otf))[\'"]?\s*\)/i', $block, $um ) ) {
				// ترجیح woff2
				$best = '';
				foreach ( $um[1] as $u ) {
					$abs = $this->absolutize_url( $u, $css_url );
					if ( ! $abs ) {
						continue;
					}
					$result['files'][] = $abs;
					if ( ! $best || preg_match( '/\.woff2($|\?)/i', $abs ) ) {
						$best = $abs;
					}
				}
				$src = $best;
			}

			if ( $family ) {
				$result['families'][] = $family;
			}
			if ( $family && $src ) {
				$result['faces'][] = array(
					'family' => $family,
					'src'    => $src,
					'weight' => $weight,
					'style'  => $style,
				);
			}
		}
	}

	/** @return string */
	private function fetch_css( $url ) {
		if ( 'custom-css://inline' === $url ) {
			return (string) wp_get_custom_css();
		}

		$local = $this->url_to_path( $url );
		if ( $local && is_readable( $local ) ) {
			$raw = file_get_contents( $local ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			return is_string( $raw ) ? $raw : '';
		}

		$resp = wp_remote_get(
			$url,
			array(
				'timeout' => 8,
				'headers' => array( 'Accept' => 'text/css,*/*' ),
			)
		);
		if ( is_wp_error( $resp ) || 200 !== (int) wp_remote_retrieve_response_code( $resp ) ) {
			return '';
		}
		return (string) wp_remote_retrieve_body( $resp );
	}

	/** @return string */
	private function url_to_path( $url ) {
		$url = strtok( $url, '?' );
		$content_url = content_url();
		if ( 0 === strpos( $url, $content_url ) ) {
			return wp_normalize_path( WP_CONTENT_DIR . substr( $url, strlen( $content_url ) ) );
		}
		$home = untrailingslashit( home_url() );
		if ( 0 === strpos( $url, $home ) ) {
			return wp_normalize_path( ABSPATH . ltrim( substr( $url, strlen( $home ) ), '/' ) );
		}
		return '';
	}

	/** @return string */
	private function absolutize_url( $font_url, $css_url ) {
		$font_url = trim( html_entity_decode( (string) $font_url ) );
		if ( '' === $font_url || 0 === strpos( $font_url, 'data:' ) ) {
			return '';
		}
		if ( 0 === strpos( $font_url, '//' ) ) {
			return ( is_ssl() ? 'https:' : 'http:' ) . $font_url;
		}
		if ( preg_match( '#^https?://#i', $font_url ) ) {
			return $font_url;
		}
		if ( 'custom-css://inline' === $css_url ) {
			return ( 0 === strpos( $font_url, '/' ) ) ? home_url( $font_url ) : home_url( '/' . ltrim( $font_url, './' ) );
		}
		if ( 0 === strpos( $font_url, '/' ) ) {
			$parts = wp_parse_url( $css_url );
			if ( ! empty( $parts['scheme'] ) && ! empty( $parts['host'] ) ) {
				return $parts['scheme'] . '://' . $parts['host'] . $font_url;
			}
			return home_url( $font_url );
		}
		$base = preg_replace( '#/[^/]*(\?.*)?$#', '/', (string) $css_url );
		return $base . $font_url;
	}

	public function force_google_display_swap( $src, $handle ) {
		if ( ! is_string( $src ) || false === stripos( $src, 'fonts.googleapis.com' ) ) {
			return $src;
		}
		if ( false !== stripos( $src, 'display=' ) ) {
			return preg_replace( '/display=[^&]*/i', 'display=swap', $src );
		}
		return add_query_arg( 'display', 'swap', $src );
	}

	public function print_preload_and_swap() {
		if ( is_admin() ) {
			return;
		}

		$fonts = $this->detect_fonts( false );
		$files = array_slice( (array) ( $fonts['files'] ?? array() ), 0, 12 );

		foreach ( $files as $file ) {
			$type = 'font/woff2';
			if ( preg_match( '/\.woff($|\?)/i', $file ) && ! preg_match( '/\.woff2($|\?)/i', $file ) ) {
				$type = 'font/woff';
			} elseif ( preg_match( '/\.ttf($|\?)/i', $file ) ) {
				$type = 'font/ttf';
			} elseif ( preg_match( '/\.otf($|\?)/i', $file ) ) {
				$type = 'font/otf';
			}
			printf(
				'<link rel="preload" href="%s" as="font" type="%s" crossorigin>' . "\n",
				esc_url( $file ),
				esc_attr( $type )
			);
		}

		$faces = array_slice( (array) ( $fonts['faces'] ?? array() ), 0, 24 );
		if ( empty( $faces ) ) {
			return;
		}

		echo "<style id=\"wbfs-font-swap\">\n";
		foreach ( $faces as $face ) {
			$family = trim( (string) ( $face['family'] ?? '' ) );
			$src    = trim( (string) ( $face['src'] ?? '' ) );
			if ( ! $family || ! $src ) {
				continue;
			}
			$format = 'woff2';
			if ( preg_match( '/\.woff($|\?)/i', $src ) && ! preg_match( '/\.woff2($|\?)/i', $src ) ) {
				$format = 'woff';
			} elseif ( preg_match( '/\.ttf($|\?)/i', $src ) ) {
				$format = 'truetype';
			} elseif ( preg_match( '/\.otf($|\?)/i', $src ) ) {
				$format = 'opentype';
			}

			echo '@font-face{';
			echo 'font-family:' . $this->css_quote( $family ) . ';';
			echo 'src:url(' . $this->css_quote( $src ) . ') format(' . $this->css_quote( $format ) . ');';
			echo 'font-display:swap;';
			if ( ! empty( $face['weight'] ) ) {
				echo 'font-weight:' . esc_attr( $face['weight'] ) . ';';
			}
			if ( ! empty( $face['style'] ) ) {
				echo 'font-style:' . esc_attr( $face['style'] ) . ';';
			}
			echo "}\n";
		}
		echo "</style>\n";
	}

	/** @return string */
	private function css_quote( $value ) {
		$value = str_replace( array( '\\', '"' ), array( '\\\\', '\\"' ), (string) $value );
		return '"' . $value . '"';
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$s     = self::settings();
		$fonts = $this->detect_fonts( false );
		$ok    = isset( $_GET['settings-updated'] ) || isset( $_GET['scanned'] ); // phpcs:ignore
		?>
		<div class="wrap wbfs-wrap">
			<div class="wbfs-hero">
				<div>
					<h1>فونت سوییپ</h1>
					<p>فونت‌های سایت را پیدا می‌کند، Preload می‌کند و با یک تیک Swap را روشن می‌کند.</p>
				</div>
				<span class="wbfs-badge">سبک · v<?php echo esc_html( WBFS_VERSION ); ?></span>
			</div>

			<?php if ( $ok ) : ?>
				<div class="wbfs-notice">ذخیره / اسکن انجام شد.</div>
			<?php endif; ?>

			<form method="post" action="options.php" class="wbfs-card">
				<?php settings_fields( 'wbfs_settings_group' ); ?>
				<label class="wbfs-toggle">
					<input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[enabled]" value="1" <?php checked( ! empty( $s['enabled'] ) ); ?> />
					<span class="wbfs-toggle-ui" aria-hidden="true"></span>
					<span class="wbfs-toggle-text">
						<strong>فعال‌سازی Preload + Swap</strong>
						<small>فونت‌ها زودتر لود می‌شوند و با <code>font-display:swap</code> جابه‌جا می‌شوند — متن زودتر دیده می‌شود.</small>
					</span>
				</label>
				<?php submit_button( 'ذخیره', 'primary', 'submit', false ); ?>
			</form>

			<div class="wbfs-grid">
				<div class="wbfs-card">
					<div class="wbfs-card-head">
						<strong>فونت‌های تشخیص‌داده‌شده</strong>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<?php wp_nonce_field( 'wbfs_rescan' ); ?>
							<input type="hidden" name="action" value="wbfs_rescan" />
							<button type="submit" class="button">اسکن دوباره</button>
						</form>
					</div>
					<?php if ( empty( $fonts['families'] ) ) : ?>
						<p class="wbfs-muted">هنوز فونتی پیدا نشد. «اسکن دوباره» را بزنید (صفحه اصلی سایت خوانده می‌شود).</p>
					<?php else : ?>
						<ul class="wbfs-list">
							<?php foreach ( $fonts['families'] as $family ) : ?>
								<li><span class="wbfs-dot"></span><?php echo esc_html( $family ); ?></li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</div>

				<div class="wbfs-card">
					<div class="wbfs-card-head"><strong>فایل‌های Preload</strong></div>
					<?php if ( empty( $fonts['files'] ) ) : ?>
						<p class="wbfs-muted">فایل woff2 مستقیمی پیدا نشد. اگر Google Fonts دارید، با روشن بودن تیک، <code>display=swap</code> اضافه می‌شود.</p>
					<?php else : ?>
						<ul class="wbfs-files">
							<?php foreach ( array_slice( $fonts['files'], 0, 12 ) as $file ) : ?>
								<li dir="ltr"><?php echo esc_html( $file ); ?></li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
					<p class="wbfs-muted" style="margin-top:12px">
						<?php
						echo esc_html(
							sprintf(
								'%d خانواده · %d فایل · اسکن: %s',
								count( $fonts['families'] ),
								count( $fonts['files'] ),
								! empty( $fonts['scanned'] ) ? wp_date( 'Y/m/d H:i', (int) $fonts['scanned'] ) : '—'
							)
						);
						?>
					</p>
				</div>
			</div>
		</div>
		<?php
	}
}
