<?php
defined( 'ABSPATH' ) || exit;

/**
 * فونت سوییپ — بهینه‌سازی فونت‌ها از یک پنل.
 */
class WBS_Fonts {

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
		$current = get_option( self::OPTION, false );
		if ( false === $current ) {
			add_option( self::OPTION, self::defaults(), '', false );
			return;
		}
		// ارتقا از 1.0: گزینه‌های جدید را پر کن.
		update_option( self::OPTION, wp_parse_args( (array) $current, self::defaults() ), false );
		delete_transient( self::TRANSIENT );
	}

	public static function defaults() {
		return array(
			'enabled'              => 1,
			// همه بهینه‌سازی‌ها اجباری‌اند؛ فقط کلید اصلی روشن/خاموش می‌شود.
			'font_display_swap'    => 1,
			'preload_woff2_only'   => 1,
			'max_preload'          => 3,
			'strip_bad_preloads'   => 1,
			'disable_google_fonts' => 1,
			'prefer_local_fonts'   => 1,
			'force_mode'           => 1,
		);
	}

	/**
	 * تنظیمات با اجبار همیشگی بهینه‌سازی‌ها (حتی اگر در DB خاموش ذخیره شده باشند).
	 */
	public static function settings() {
		$s = wp_parse_args( (array) get_option( self::OPTION, array() ), self::defaults() );
		// حالت اجباری: همه قابلیت‌ها ON.
		$s['force_mode']           = 1;
		$s['font_display_swap']    = 1;
		$s['preload_woff2_only']   = 1;
		$s['strip_bad_preloads']   = 1;
		$s['disable_google_fonts'] = 1;
		$s['prefer_local_fonts']   = 1;
		$max = isset( $s['max_preload'] ) ? (int) $s['max_preload'] : 3;
		$s['max_preload'] = ( $max < 1 ) ? 3 : min( 8, $max );
		return $s;
	}

	private function __construct() {
		add_action( 'admin_menu', array( $this, 'menu' ), 20 );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_post_wbfs_rescan', array( $this, 'handle_rescan' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_assets' ) );

		$s = self::settings();
		if ( empty( $s['enabled'] ) ) {
			return;
		}

		// === اعمال اجباری همه قابلیت‌ها ===
		add_action( 'wp_enqueue_scripts', array( $this, 'dequeue_google_fonts' ), 100 );
		add_filter( 'style_loader_src', array( $this, 'block_google_font_src' ), 100, 2 );
		add_filter( 'style_loader_src', array( $this, 'rewrite_local_font_css_src' ), 60, 2 );
		add_action( 'wp_head', array( $this, 'print_preload_and_swap' ), 1 );
		add_action( 'wp_head', array( $this, 'print_force_iransans_faces' ), 2 );
		add_action( 'template_redirect', array( $this, 'start_buffer' ), -9990 );
	}

	public function menu() {
		add_submenu_page(
			'webakery-speed',
			'فونت سوییپ',
			'فونت سوییپ',
			'manage_options',
			'webakery-speed-fonts',
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
		$input = is_array( $input ) ? $input : array();
		$max   = isset( $input['max_preload'] ) ? (int) $input['max_preload'] : 3;
		if ( $max < 1 ) {
			$max = 3;
		}
		if ( $max > 8 ) {
			$max = 8;
		}

		// فقط enabled و max_preload قابل تنظیم‌اند؛ بقیه همیشه اجباری ON ذخیره می‌شوند.
		$out = array(
			'enabled'              => ! empty( $input['enabled'] ) ? 1 : 0,
			'font_display_swap'    => 1,
			'preload_woff2_only'   => 1,
			'max_preload'          => $max,
			'strip_bad_preloads'   => 1,
			'disable_google_fonts' => 1,
			'prefer_local_fonts'   => 1,
			'force_mode'           => 1,
		);

		delete_transient( self::TRANSIENT );
		return $out;
	}

	public function admin_assets( $hook ) {
		if ( 'webakery-speed_page_webakery-speed-fonts' !== $hook ) {
			return;
		}
		wp_enqueue_style( 'wbs-fonts-admin', WBS_URL . 'assets/fonts-admin.css', array(), WBS_VERSION );
	}

	public function handle_rescan() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'دسترسی غیرمجاز' );
		}
		check_admin_referer( 'wbfs_rescan' );
		delete_transient( self::TRANSIENT );
		$this->detect_fonts( true );
		wp_safe_redirect( admin_url( 'admin.php?page=webakery-speed-fonts&scanned=1' ) );
		exit;
	}

	public function dequeue_google_fonts() {
		global $wp_styles;
		if ( ! ( $wp_styles instanceof WP_Styles ) ) {
			return;
		}
		foreach ( $wp_styles->registered as $handle => $obj ) {
			$src = isset( $obj->src ) ? (string) $obj->src : '';
			if ( false !== stripos( $src, 'fonts.googleapis.com' ) || false !== stripos( $src, 'fonts.gstatic.com' ) ) {
				wp_dequeue_style( $handle );
				wp_deregister_style( $handle );
			}
		}
	}

	public function block_google_font_src( $src, $handle ) {
		if ( ! is_string( $src ) ) {
			return $src;
		}
		if ( false !== stripos( $src, 'fonts.googleapis.com' ) || false !== stripos( $src, 'fonts.gstatic.com' ) ) {
			return false;
		}
		return $src;
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

	/**
	 * اگر استایل فونت محلی بود، نسخه‌ای با font-display:swap در uploads می‌سازد و URL را عوض می‌کند.
	 * این همان چیزی است که ابزارهایی مثل ShetabWP به‌عنوان Swap روی Link تشخیص می‌دهند.
	 */
	public function rewrite_local_font_css_src( $src, $handle ) {
		if ( ! is_string( $src ) || '' === $src ) {
			return $src;
		}
		if ( false !== stripos( $src, 'fonts.googleapis.com' ) || false !== stripos( $src, 'fonts.gstatic.com' ) ) {
			return $src;
		}
		// فقط CSSهای فونت‌محور.
		$looks_font = (bool) preg_match( '#iransans|iran\-sans|dana|anjoman|vazir|font|webfont#i', $src . ' ' . (string) $handle );
		if ( ! $looks_font ) {
			return $src;
		}

		$abs = $src;
		if ( 0 === strpos( $abs, '//' ) ) {
			$abs = ( is_ssl() ? 'https:' : 'http:' ) . $abs;
		} elseif ( 0 === strpos( $abs, '/' ) ) {
			$abs = home_url( $abs );
		}

		$optimized = $this->ensure_swap_css_file( $abs );
		return $optimized ? $optimized : $src;
	}

	/**
	 * @param string $css_url
	 * @return string|false URL فایل بهینه‌شده
	 */
	private function ensure_swap_css_file( $css_url ) {
		$body = $this->fetch_css( $css_url );
		if ( '' === $body || false === stripos( $body, '@font-face' ) ) {
			return false;
		}

		// اگر از قبل swap دارد و woff قبل از woff2 نیست، همان را نگه می‌داریم فقط اگر همه faceها swap دارند.
		$needs = ( false === stripos( $body, 'font-display' ) );

		// مرتب‌سازی src: woff2 اول.
		$optimized = preg_replace_callback(
			'/@font-face\s*\{(.*?)\}/is',
			function ( $m ) {
				$block = $m[1];
				if ( ! preg_match( '/font-display\s*:/i', $block ) ) {
					$block = rtrim( $block ) . "\n\tfont-display: swap;\n";
				} else {
					$block = preg_replace( '/font-display\s*:\s*[^;]+;/i', 'font-display: swap;', $block );
				}
				// اگر هم woff و هم woff2 هست، woff2 را جلو بیاور.
				if ( preg_match( '/src\s*:\s*([^;]+);/is', $block, $sm ) ) {
					$src = $sm[1];
					if ( preg_match_all( '/url\(\s*[\'"]?([^\'"\)]+)[\'"]?\s*\)\s*format\(\s*[\'"]?([^\'")]+)[\'"]?\s*\)/i', $src, $parts, PREG_SET_ORDER ) ) {
						$woff2 = array();
						$rest  = array();
						foreach ( $parts as $p ) {
							$item = 'url(\'' . $p[1] . '\') format(\'' . $p[2] . '\')';
							if ( false !== stripos( $p[2], 'woff2' ) || preg_match( '/\.woff2($|\?)/i', $p[1] ) ) {
								$woff2[] = $item;
							} else {
								$rest[] = $item;
							}
						}
						$new_src = implode( ",\n\t\t", array_merge( $woff2, $rest ) );
						if ( $new_src ) {
							$block = preg_replace( '/src\s*:\s*[^;]+;/is', 'src: ' . $new_src . ';', $block, 1 );
						}
					}
				}
				return '@font-face {' . $block . '}';
			},
			$body
		);

		if ( ! is_string( $optimized ) || '' === $optimized ) {
			return false;
		}

		$dir = WP_CONTENT_DIR . '/uploads/wbfs-font-swap';
		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}
		$hash = substr( md5( $css_url . '|' . $optimized ), 0, 16 );
		$file = $dir . '/font-' . $hash . '.css';
		if ( ! file_exists( $file ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			file_put_contents( $file, "/* WBFS optimized font-display:swap */\n" . $optimized );
		}

		return content_url( 'uploads/wbfs-font-swap/font-' . $hash . '.css' );
	}

	public function start_buffer() {
		if ( is_admin() || is_feed() || is_preview() ) {
			return;
		}
		ob_start( array( $this, 'filter_html' ) );
	}

	/**
	 * Force IRANSansX Regular/Bold @font-face into head so Swap cannot be skipped.
	 */
	public function print_force_iransans_faces() {
		if ( is_admin() ) {
			return;
		}

		$faces = $this->resolve_iransans_faces();
		if ( empty( $faces ) ) {
			return;
		}

		echo "<style id=\"wbfs-force-iransans\">\n";
		foreach ( $faces as $face ) {
			printf(
				"@font-face{font-family:%s;font-style:normal;font-weight:%s;font-display:swap;src:url(%s) format(\"woff2\")}\n",
				$this->css_quote( $face['family'] ),
				esc_attr( $face['weight'] ),
				$this->css_quote( $face['src'] )
			);
		}
		echo "</style>\n";
	}

	/**
	 * Resolve local IRANSansX woff2 URLs from common med-persian paths.
	 *
	 * @return array<int,array{family:string,weight:string,src:string}>
	 */
	private function resolve_iransans_faces() {
		$pairs = array(
			array(
				'family' => 'IRANSansX',
				'weight' => '400',
				'files'  => array(
					WP_PLUGIN_DIR . '/med-persian/assets/fonts/iransans/fonts/woff2/IRANSansX-Regular.woff2',
					WP_PLUGIN_DIR . '/med-persian/assets/fonts/IRANSansX-Regular.woff2',
					WP_PLUGIN_DIR . '/med-persian/assets/fonts/woff2/IRANSansX-Regular.woff2',
				),
			),
			array(
				'family' => 'IRANSansX',
				'weight' => '700',
				'files'  => array(
					WP_PLUGIN_DIR . '/med-persian/assets/fonts/iransans/fonts/woff2/IRANSansX-Bold.woff2',
					WP_PLUGIN_DIR . '/med-persian/assets/fonts/IRANSansX-Bold.woff2',
					WP_PLUGIN_DIR . '/med-persian/assets/fonts/woff2/IRANSansX-Bold.woff2',
				),
			),
		);

		$out = array();
		foreach ( $pairs as $pair ) {
			foreach ( $pair['files'] as $abs ) {
				if ( ! is_readable( $abs ) ) {
					continue;
				}
				$url = plugins_url( ltrim( str_replace( WP_PLUGIN_DIR, '', $abs ), '/' ) );
				$out[] = array(
					'family' => $pair['family'],
					'weight' => $pair['weight'],
					'src'    => $url,
				);
				break;
			}
		}
		return $out;
	}

	/**
	 * Strip harmful font preloads / Google font tags from final HTML.
	 * Also force-rewrite hardcoded local font stylesheet links to swapped CSS.
	 *
	 * @param string $html
	 * @return string
	 */
	public function filter_html( $html ) {
		if ( ! is_string( $html ) || strlen( $html ) < 50 ) {
			return $html;
		}

		// Force rewrite hardcoded local font stylesheet <link> tags.
		$html = preg_replace_callback(
			'#<link\b[^>]*rel=[\'"]stylesheet[\'"][^>]*>#i',
			function ( $m ) {
				$tag = $m[0];
				if ( ! preg_match( '#\bhref=[\'"]([^\'"]+)#i', $tag, $hm ) ) {
					return $tag;
				}
				$href = html_entity_decode( $hm[1], ENT_QUOTES );
				if ( false !== stripos( $href, 'fonts.googleapis.com' ) || false !== stripos( $href, 'fonts.gstatic.com' ) ) {
					return $tag;
				}
				if ( false !== stripos( $href, 'wbfs-font-swap' ) ) {
					return $tag;
				}
				if ( ! preg_match( '#(?:fonts?|font-awesome|fontawesome|iransans|iran\-sans|elementor/assets/lib/font|woocommerce.*font|webfont)#i', $href ) ) {
					return $tag;
				}
				$abs = $href;
				if ( 0 === strpos( $abs, '//' ) ) {
					$abs = ( is_ssl() ? 'https:' : 'http:' ) . $abs;
				} elseif ( 0 === strpos( $abs, '/' ) ) {
					$abs = home_url( $abs );
				}
				$swapped = $this->ensure_swap_css_file( $abs );
				if ( ! $swapped || $swapped === $abs ) {
					return $tag;
				}
				return str_replace( $hm[1], esc_url( $swapped ), $tag );
			},
			$html
		);

		$html = preg_replace_callback(
			'#<link\b[^>]*rel=[\'"]preload[\'"][^>]*>#i',
			function ( $m ) {
				$tag = $m[0];
				$as_font = (bool) preg_match( '#\bas=[\'"]font[\'"]#i', $tag );
				$href_font = (bool) preg_match( '#\.(?:woff2?|ttf|otf|eot)(?:\?|\'|"|\s|>)#i', $tag );
				if ( ! $as_font && ! $href_font ) {
					return $tag;
				}
				// Keep only local woff2.
				if ( preg_match( '#\bhref=[\'"]([^\'"]+)#i', $tag, $hm ) ) {
					$href = $hm[1];
					$is_woff2 = (bool) preg_match( '#\.woff2(?:\?|$)#i', $href );
					$is_remote = (bool) preg_match( '#fonts\.gstatic\.com|fonts\.googleapis\.com#i', $href );
					$is_ttf_woff = (bool) preg_match( '#\.(?:ttf|otf|eot|woff)(?:\?|$)#i', $href ) && ! $is_woff2;
					if ( $is_remote || $is_ttf_woff || ! $is_woff2 ) {
						return '';
					}
					return $tag;
				}
				return '';
			},
			$html
		);

		$html = preg_replace( '#<link\b[^>]+fonts\.googleapis\.com[^>]*>#i', '', $html );
		$html = preg_replace( '#<link\b[^>]+fonts\.gstatic\.com[^>]*>#i', '', $html );
		$html = preg_replace( '#<style[^>]*>[^<]*fonts\.googleapis\.com[^<]*</style>#is', '', $html );

		// Marker so View Source proves forced font mode is active.
		if ( false === stripos( $html, 'WBS_FORCE_MODE=1' ) ) {
			$html = preg_replace( '#</head>#i', "<!-- WBS_FORCE_MODE=1 -->\n</head>", $html, 1 );
		}

		return $html;
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

		// فونت‌های حیاتی شناخته‌شده (مثل IRANSansX در med-persian) حتی اگر اسکن صفحه کامل نباشد.
		$this->append_known_critical_fonts( $result );

		set_transient( self::TRANSIENT, $result, 12 * HOUR_IN_SECONDS );
		return $result;
	}

	/**
	 * افزودن مسیرهای قطعی IRANSansX و مشابه.
	 */
	private function append_known_critical_fonts( array &$result ) {
		foreach ( $this->resolve_iransans_faces() as $face ) {
			$result['families'][] = $face['family'];
			$result['files'][]    = $face['src'];
			$result['faces'][]    = array(
				'family' => $face['family'],
				'src'    => $face['src'],
				'weight' => $face['weight'],
				'style'  => 'normal',
			);
		}

		$result['files']    = array_values( array_unique( $result['files'] ) );
		$result['families'] = array_values( array_unique( array_filter( $result['families'] ) ) );
	}

	/** @return string[] */
	private function collect_css_urls() {
		$urls = array();

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
		}

		$urls[] = get_stylesheet_directory_uri() . '/style.css';
		if ( get_template_directory_uri() !== get_stylesheet_directory_uri() ) {
			$urls[] = get_template_directory_uri() . '/style.css';
		}
		$urls = array_merge( $urls, $this->scan_theme_css( get_stylesheet_directory(), 2 ) );

		if ( wp_get_custom_css() ) {
			$urls[] = 'custom-css://inline';
		}

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
			foreach ( $skip as $s ) {
				if ( false !== stripos( $rel, $s ) ) {
					continue 2;
				}
			}
			if ( is_dir( $path ) ) {
				$out = array_merge( $out, $this->scan_theme_css( $path, $depth - 1 ) );
			} elseif ( preg_match( '/\.css$/i', $item ) ) {
				$out[] = get_stylesheet_directory_uri() . $rel;
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
				$best = '';
				foreach ( $um[1] as $u ) {
					$abs = $this->absolutize_url( $u, $css_url );
					if ( ! $abs ) {
						continue;
					}
					$result['files'][] = $abs;
					// همیشه woff2 را ترجیح بده.
					if ( preg_match( '/\.woff2($|\?)/i', $abs ) ) {
						$best = $abs;
					} elseif ( ! $best ) {
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

	private function fetch_css( $url ) {
		if ( 'custom-css://inline' === $url ) {
			return (string) wp_get_custom_css();
		}
		$local = $this->url_to_path( $url );
		if ( $local && is_readable( $local ) ) {
			$raw = file_get_contents( $local ); // phpcs:ignore
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

	/**
	 * انتخاب فایل‌های preload: فقط woff2 محلی، حداکثر N، یکتا per family.
	 *
	 * @param array $fonts
	 * @return string[]
	 */
	private function select_preload_files( array $fonts ) {
		$s     = self::settings();
		$max   = isset( $s['max_preload'] ) ? (int) $s['max_preload'] : 3;
		$faces = (array) ( $fonts['faces'] ?? array() );
		$files = (array) ( $fonts['files'] ?? array() );

		$candidates = array();

		foreach ( $faces as $face ) {
			$src    = (string) ( $face['src'] ?? '' );
			$family = (string) ( $face['family'] ?? '' );
			if ( ! $src ) {
				continue;
			}
			$candidates[] = array(
				'src'    => $src,
				'family' => $family,
				'score'  => $this->score_font_file( $src, $family ),
			);
		}
		foreach ( $files as $src ) {
			$candidates[] = array(
				'src'    => (string) $src,
				'family' => '',
				'score'  => $this->score_font_file( (string) $src, '' ),
			);
		}

		usort(
			$candidates,
			function ( $a, $b ) {
				return $b['score'] <=> $a['score'];
			}
		);

		$picked   = array();
		$families = array();
		foreach ( $candidates as $c ) {
			if ( $c['score'] < 50 ) {
				continue; // رد TTF/Google/WOFF در حالت بهینه‌سازی.
			}
			if ( ! empty( $s['preload_woff2_only'] ) && ! preg_match( '/\.woff2($|\?)/i', $c['src'] ) ) {
				continue;
			}
			if ( ! empty( $s['prefer_local_fonts'] ) && preg_match( '#fonts\.gstatic\.com|fonts\.googleapis\.com#i', $c['src'] ) ) {
				continue;
			}
			$fam_key = strtolower( $c['family'] !== '' ? $c['family'] : $c['src'] );
			if ( isset( $families[ $fam_key ] ) ) {
				continue;
			}
			$families[ $fam_key ] = true;
			$picked[]               = $c['src'];
			if ( count( $picked ) >= $max ) {
				break;
			}
		}

		return array_values( array_unique( $picked ) );
	}

	private function score_font_file( $src, $family ) {
		$score = 0;
		if ( preg_match( '/\.woff2($|\?)/i', $src ) ) {
			$score += 100;
		} elseif ( preg_match( '/\.woff($|\?)/i', $src ) ) {
			$score += 40;
		} elseif ( preg_match( '/\.ttf($|\?)/i', $src ) ) {
			$score += 10;
		}
		if ( preg_match( '#fonts\.gstatic\.com|fonts\.googleapis\.com#i', $src ) ) {
			$score -= 80;
		}
		if ( preg_match( '#/uploads/|/themes/#i', $src ) ) {
			$score += 10;
		}
		// فونت متن فارسی = اولویت اول برای preload/swap.
		if ( preg_match( '#iransans|iran\-sans|dana|anjoman|vazir|yekan#i', $src . ' ' . $family ) ) {
			$score += 80;
		}
		if ( preg_match( '#IRANSansX-Regular#i', $src ) ) {
			$score += 40;
		}
		// Font Awesome / آیکون: preload نکن مگر جا خالی باشد.
		if ( preg_match( '#fontawesome|fa-solid|fa-brands|fa-regular|eicons|tinvwl-webfont|flaticon#i', $src . ' ' . $family ) ) {
			$score -= 120;
		}
		return $score;
	}

	public function print_preload_and_swap() {
		if ( is_admin() ) {
			return;
		}

		$s     = self::settings();
		$fonts = $this->detect_fonts( false );
		$files = $this->select_preload_files( $fonts );

		foreach ( $files as $file ) {
			printf(
				'<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n",
				esc_url( $file )
			);
		}

		if ( empty( $s['font_display_swap'] ) ) {
			return;
		}

		// فقط faceهای woff2 محلی برای swap.
		$faces_out = array();
		foreach ( (array) ( $fonts['faces'] ?? array() ) as $face ) {
			$src = (string) ( $face['src'] ?? '' );
			if ( ! preg_match( '/\.woff2($|\?)/i', $src ) ) {
				continue;
			}
			if ( preg_match( '#fonts\.gstatic\.com|fonts\.googleapis\.com#i', $src ) ) {
				continue;
			}
			$faces_out[] = $face;
			if ( count( $faces_out ) >= 16 ) {
				break;
			}
		}

		if ( empty( $faces_out ) ) {
			return;
		}

		echo "<style id=\"wbfs-font-swap\">\n";
		foreach ( $faces_out as $face ) {
			$family = trim( (string) ( $face['family'] ?? '' ) );
			$src    = trim( (string) ( $face['src'] ?? '' ) );
			if ( ! $family || ! $src ) {
				continue;
			}
			echo '@font-face{';
			echo 'font-family:' . $this->css_quote( $family ) . ';';
			echo 'src:url(' . $this->css_quote( $src ) . ') format("woff2");';
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

	private function css_quote( $value ) {
		$value = str_replace( array( '\\', '"' ), array( '\\\\', '\\"' ), (string) $value );
		return '"' . $value . '"';
	}

	private function render_toggle( $key, $label, $help, $checked ) {
		printf(
			'<label class="wbfs-toggle"><input type="checkbox" name="%1$s[%2$s]" value="1" %3$s /><span class="wbfs-toggle-ui" aria-hidden="true"></span><span class="wbfs-toggle-text"><strong>%4$s</strong><small>%5$s</small></span></label>',
			esc_attr( self::OPTION ),
			esc_attr( $key ),
			checked( $checked, true, false ),
			esc_html( $label ),
			wp_kses_post( $help )
		);
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$s     = self::settings();
		$fonts = $this->detect_fonts( false );
		$pre   = $this->select_preload_files( $fonts );
		$ok    = isset( $_GET['settings-updated'] ) || isset( $_GET['scanned'] ); // phpcs:ignore
		?>
		<div class="wrap wbfs-wrap">
			<div class="wbfs-hero">
				<div>
					<h1>فونت سوییپ</h1>
					<p>حالت اجباری: با روشن بودن افزونه، همه بهینه‌سازی‌های فونت بدون تیک جداگانه اعمال می‌شوند.</p>
				</div>
					<span class="wbfs-badge">اجباری · v<?php echo esc_html( WBS_VERSION ); ?> · IRANSansX</span>
			</div>
			<p style="margin:0 0 16px">
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=webakery-speed' ) ); ?>">اولویت‌ها</a>
				<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=webakery-speed-fonts' ) ); ?>">فونت سوییپ</a>
			</p>

			<?php if ( $ok ) : ?>
				<div class="wbfs-notice">ذخیره / اسکن انجام شد. کش سایت را پاک کنید و صفحه را در Incognito چک کنید.</div>
			<?php endif; ?>

			<form method="post" action="options.php" class="wbfs-card">
				<?php settings_fields( 'wbfs_settings_group' ); ?>

				<div class="wbfs-notice" style="margin-bottom:16px">
					<strong>حالت اجباری فعال است.</strong>
					وقتی افزونه روشن باشد این‌ها همیشه اعمال می‌شوند (تیک جداگانه ندارند):
					حذف Google Fonts، حذف preloadهای مضر، بازنویسی CSS فونت با <code>font-display:swap</code>،
					تزریق اجباری IRANSansX، و preload فقط woff2 محلی.
				</div>

				<?php
				$this->render_toggle( 'enabled', 'فعال‌سازی افزونه (کل کلید)', 'اگر خاموش باشد هیچ بهینه‌سازی فونتی اعمال نمی‌شود. اگر روشن باشد همه بهینه‌سازی‌ها اجباری‌اند.', ! empty( $s['enabled'] ) );
				?>

				<label style="display:block;margin:8px 0 16px">
					<strong>حداکثر تعداد Preload</strong>
					<input type="number" min="1" max="8" name="<?php echo esc_attr( self::OPTION ); ?>[max_preload]" value="<?php echo esc_attr( (string) (int) $s['max_preload'] ); ?>" style="width:80px;margin-right:8px" />
					<small class="wbfs-muted">پیشنهاد برای موبایل: ۲ یا ۳ — تنها مقدار قابل تنظیم علاوه بر کلید اصلی</small>
				</label>

				<?php submit_button( 'ذخیره تنظیمات', 'primary', 'submit', false ); ?>
			</form>

			<div class="wbfs-grid">
				<div class="wbfs-card">
					<div class="wbfs-card-head">
						<strong>خانواده‌های تشخیص‌داده‌شده</strong>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<?php wp_nonce_field( 'wbfs_rescan' ); ?>
							<input type="hidden" name="action" value="wbfs_rescan" />
							<button type="submit" class="button">اسکن دوباره</button>
						</form>
					</div>
					<?php if ( empty( $fonts['families'] ) ) : ?>
						<p class="wbfs-muted">فونتی پیدا نشد. اسکن دوباره را بزنید.</p>
					<?php else : ?>
						<ul class="wbfs-list">
							<?php foreach ( $fonts['families'] as $family ) : ?>
								<li><span class="wbfs-dot"></span><?php echo esc_html( $family ); ?></li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
					<?php if ( ! empty( $fonts['google'] ) ) : ?>
						<p class="wbfs-muted" style="margin-top:12px;color:#b45309">Google Fonts یافت شد: <?php echo esc_html( (string) count( $fonts['google'] ) ); ?> مورد — در حالت اجباری از HTML حذف می‌شوند.</p>
					<?php endif; ?>
				</div>

				<div class="wbfs-card">
					<div class="wbfs-card-head"><strong>Preload نهایی (woff2)</strong></div>
					<?php if ( empty( $pre ) ) : ?>
						<p class="wbfs-muted">هیچ woff2 محلی برای preload انتخاب نشد (این برای سرعت خوب است اگر فونت بحرانی بالای صفحه نداری).</p>
					<?php else : ?>
						<ul class="wbfs-files">
							<?php foreach ( $pre as $file ) : ?>
								<li dir="ltr"><?php echo esc_html( $file ); ?></li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
					<p class="wbfs-muted" style="margin-top:12px">
						<?php
						echo esc_html(
							sprintf(
								'%d خانواده · %d فایل خام · %d preload نهایی · اسکن: %s',
								count( $fonts['families'] ),
								count( $fonts['files'] ),
								count( $pre ),
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
