<?php
defined( 'ABSPATH' ) || exit;

/**
 * اسکن HTML صفحه برای اولویت‌های سرعت.
 */
class WBS_Scanner {

	/**
	 * @param string $url
	 * @return array|WP_Error
	 */
	public static function scan( $url = '' ) {
		$url = $url ? $url : home_url( '/' );
		$resp = wp_remote_get(
			add_query_arg( 'wbsb_scan', time(), $url ),
			array(
				'timeout' => 20,
				'headers' => array(
					'Accept'     => 'text/html',
					'User-Agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 16_0 like Mac OS X) WebakerySpeed/' . WBS_VERSION,
				),
			)
		);

		if ( is_wp_error( $resp ) ) {
			return $resp;
		}

		$code = (int) wp_remote_retrieve_response_code( $resp );
		$html = (string) wp_remote_retrieve_body( $resp );
		if ( $code >= 400 || strlen( $html ) < 200 ) {
			return new WP_Error( 'scan_failed', 'خواندن صفحه ناموفق بود (HTTP ' . $code . ').' );
		}

		$out = array(
			'scanned_at' => time(),
			'url'        => $url,
			'html_kb'    => round( strlen( $html ) / 1024, 1 ),
			'steps'      => array(),
		);

		$out['steps']['search_console'] = self::step_search_console();
		$out['steps']['render_blocking'] = self::step_render_blocking( $html );
		$out['steps']['image_dimensions'] = self::step_image_dimensions( $html );
		$out['steps']['image_format'] = self::step_image_format( $html );
		$out['steps']['image_weight'] = self::step_image_weight( $html );
		$out['steps']['fonts'] = self::step_fonts( $html );
		$out['steps']['lcp_images'] = self::step_lcp_images( $html );
		$out['steps']['forced_reflow'] = self::step_forced_reflow();
		$out['steps']['network_tree'] = self::step_network_tree( $html );

		return $out;
	}

	private static function step_search_console() {
		$data = get_option( 'wbsb_gsc', array() );
		$has  = ! empty( $data['gsc_url'] ) || ! empty( $data['lcp'] ) || ! empty( $data['inp'] ) || ! empty( $data['cls'] );

		$issues = array();
		if ( empty( $data['gsc_url'] ) ) {
			$issues[] = 'لینک سرچ‌کنسول / PageSpeed هنوز ثبت نشده.';
		}
		if ( ! empty( $data['lcp'] ) && (float) $data['lcp'] > 2.5 ) {
			$issues[] = 'LCP فیلد دیتا ضعیف است (> 2.5s).';
		}
		if ( ! empty( $data['inp'] ) && (float) $data['inp'] > 200 ) {
			$issues[] = 'INP فیلد دیتا ضعیف است (> 200ms).';
		}
		if ( ! empty( $data['cls'] ) && (float) $data['cls'] > 0.1 ) {
			$issues[] = 'CLS فیلد دیتا ضعیف است (> 0.1).';
		}

		$status = 'ok';
		if ( ! $has ) {
			$status = 'todo';
		} elseif ( ! empty( $issues ) ) {
			$status = 'warn';
		}

		return array(
			'id'       => 'search_console',
			'title'    => '۱) سرچ‌کنسول / Core Web Vitals',
			'status'   => $status,
			'summary'  => $has ? 'داده ثبت شده — از اینجا اولویت را شروع کن.' : 'اول گزارش سرچ‌کنسول یا PageSpeed را وارد کن.',
			'issues'   => $issues,
			'actions'  => array(
				'از دکمه «دریافت خودکار CWV از گوگل» یا منوی گوگل / CWV استفاده کن.',
				'یا لینک GSC/PSI و مقادیر LCP/INP/CLS را دستی ذخیره کن.',
				'بعد اسکن بزن و اصلاح خودکار را روشن کن.',
			),
			'metrics'  => $data,
		);
	}

	private static function step_render_blocking( $html ) {
		$blocking_css = array();
		$async_css    = 0;

		if ( preg_match_all( '#<link\b[^>]*>#i', $html, $links ) ) {
			foreach ( $links[0] as $tag ) {
				if ( ! preg_match( '#rel=[\'"]stylesheet[\'"]#i', $tag ) ) {
					continue;
				}
				$href = '';
				if ( preg_match( '#href=[\'"]([^\'"]+)#i', $tag, $m ) ) {
					$href = $m[1];
				}
				$media  = preg_match( '#media=[\'"]([^\'"]+)#i', $tag, $mm ) ? $mm[1] : 'all';
				$onload = false !== stripos( $tag, 'onload=' );
				$short  = self::short_url( $href );
				if ( $onload || 'print' === $media ) {
					$async_css++;
				} else {
					$blocking_css[] = $short;
				}
			}
		}

		$head = strstr( $html, '</head>' ) ? explode( '</head>', $html, 2 )[0] : substr( $html, 0, 80000 );
		$blocking_js = array();
		$delayed     = substr_count( strtolower( $html ), 'pmdelayedscript' );
		if ( preg_match_all( '#<script([^>]*)>#i', $head, $scripts ) ) {
			foreach ( $scripts[1] as $attrs ) {
				if ( ! preg_match( '#src=[\'"]([^\'"]+)#i', $attrs, $sm ) ) {
					continue;
				}
				$src = $sm[1];
				if ( preg_match( '#pmdelay|type=[\'"]pmdelayedscript#i', $attrs ) ) {
					continue;
				}
				if ( preg_match( '#\bdefer\b|\basync\b#i', $attrs ) ) {
					continue;
				}
				$blocking_js[] = self::short_url( $src );
			}
		}

		$issues = array();
		foreach ( array_slice( $blocking_css, 0, 8 ) as $f ) {
			$issues[] = 'CSS مسدودکننده: ' . $f;
		}
		foreach ( array_slice( $blocking_js, 0, 6 ) as $f ) {
			$issues[] = 'JS مسدودکننده در head: ' . $f;
		}

		$count = count( $blocking_css ) + count( $blocking_js );
		$status = ( 0 === $count ) ? 'ok' : ( $count <= 3 ? 'warn' : 'bad' );

		return array(
			'id'      => 'render_blocking',
			'title'   => '۲) Render-blocking',
			'status'  => $status,
			'summary' => sprintf( '%d CSS مسدود · %d CSS غیرمسدود · %d JS مسدود head · %d delayed', count( $blocking_css ), $async_css, count( $blocking_js ), $delayed ),
			'issues'  => $issues,
			'actions' => array(
				'Perfmatters → Remove Unused CSS = ON + Stylesheet Behavior = Async',
				'Elementor post-*.css را از Excluded Stylesheets خارج کن',
				'Delay All Scripts را روشن نگه دار (jQuery می‌تواند exclude بماند)',
				'Clear Used CSS + کش Rocket',
			),
			'files'   => array(
				'blocking_css' => $blocking_css,
				'blocking_js'  => $blocking_js,
			),
		);
	}

	private static function step_image_dimensions( $html ) {
		$imgs = array();
		if ( preg_match_all( '#<img\b[^>]*>#i', $html, $m ) ) {
			$imgs = $m[0];
		}
		$missing = 0;
		$samples = array();
		foreach ( $imgs as $tag ) {
			$has_w = preg_match( '#\bwidth=#i', $tag );
			$has_h = preg_match( '#\bheight=#i', $tag );
			if ( ! $has_w || ! $has_h ) {
				$missing++;
				if ( count( $samples ) < 6 && preg_match( '#(?:data-src|src)=[\'"]([^\'"]+)#i', $tag, $sm ) ) {
					$samples[] = self::short_url( $sm[1] );
				}
			}
		}

		$status = ( 0 === $missing ) ? 'ok' : ( $missing <= 5 ? 'warn' : 'bad' );
		$issues = array();
		foreach ( $samples as $s ) {
			$issues[] = 'بدون width/height: ' . $s;
		}

		return array(
			'id'      => 'image_dimensions',
			'title'   => '۳) ابعاد تصاویر (width/height)',
			'status'  => $status,
			'summary' => sprintf( '%d تصویر · %d بدون ابعاد', count( $imgs ), $missing ),
			'issues'  => $issues,
			'actions' => array(
				'Perfmatters → Lazy Loading → Image Dimensions = ON',
				'در Elementor برای تصویر هیرو عرض/ارتفاع مشخص بگذار',
				'از Image → Add media سایز مناسب انتخاب کن نه Full',
			),
		);
	}

	private static function step_image_format( $html ) {
		$urls = self::collect_image_urls( $html );
		$png = $jpg = $webp = $other = 0;
		foreach ( $urls as $u ) {
			if ( preg_match( '/\.webp($|\?)/i', $u ) ) {
				$webp++;
			} elseif ( preg_match( '/\.png($|\?)/i', $u ) ) {
				$png++;
			} elseif ( preg_match( '/\.(jpe?g)($|\?)/i', $u ) ) {
				$jpg++;
			} else {
				$other++;
			}
		}

		$issues = array();
		if ( $png + $jpg > 0 && 0 === $webp ) {
			$issues[] = 'در HTML هیچ .webp دیده نشد — WebP Express احتمالاً روی فرانت اعمال نشده.';
		}
		if ( $png >= 5 ) {
			$issues[] = sprintf( '%d فایل PNG در صفحه ارجاع داده شده.', $png );
		}

		$status = ( $webp > 0 && $png < 3 ) ? 'ok' : ( ( $png + $jpg ) > 10 ? 'bad' : 'warn' );

		return array(
			'id'      => 'image_format',
			'title'   => '۴) فرمت تصاویر (WebP/AVIF)',
			'status'  => $status,
			'summary' => sprintf( 'png=%d · jpg=%d · webp=%d', $png, $jpg, $webp ),
			'issues'  => $issues,
			'actions' => array(
				'WebP Express: Operation mode را درست کن (Varied responses / Destination)',
				'Bulk convert را دوباره برای uploads بزن',
				'در Network تب مرورگر Type باید webp باشد نه png',
				'کش Rocket را بعد از تبدیل پاک کن',
			),
		);
	}

	private static function step_image_weight( $html ) {
		$urls = array_slice( self::collect_image_urls( $html ), 0, 25 );
		$heavy = array();
		$total = 0;

		foreach ( $urls as $u ) {
			$bytes = self::remote_size( $u );
			if ( $bytes < 0 ) {
				continue;
			}
			$total += $bytes;
			if ( $bytes >= 200 * 1024 ) {
				$heavy[] = array(
					'url' => self::short_url( $u ),
					'kb'  => round( $bytes / 1024, 1 ),
				);
			}
		}

		usort(
			$heavy,
			function ( $a, $b ) {
				return $b['kb'] <=> $a['kb'];
			}
		);

		$issues = array();
		foreach ( array_slice( $heavy, 0, 8 ) as $h ) {
			$issues[] = $h['kb'] . ' KB → ' . $h['url'];
		}

		$mb = round( $total / 1024 / 1024, 2 );
		$status = ( $mb <= 1 && count( $heavy ) <= 1 ) ? 'ok' : ( $mb <= 3 ? 'warn' : 'bad' );

		return array(
			'id'      => 'image_weight',
			'title'   => '۵) حجم تصاویر',
			'status'  => $status,
			'summary' => sprintf( 'جمع تقریبی نمونه‌ها: %s MB · فایل سنگین: %d', $mb, count( $heavy ) ),
			'issues'  => $issues,
			'actions' => array(
				'هر تصویر بالای صفحه را زیر ۱۵۰–۲۰۰KB نگه دار',
				'بنر/هیرو را دستی WebP فشرده کن و در Elementor عوض کن',
				'srcset/sizes را فعال نگه دار (سایز مناسب نمایش)',
			),
			'heavy'   => $heavy,
		);
	}

	private static function step_fonts( $html ) {
		$preloads = array();
		if ( preg_match_all( '#<link\b[^>]*rel=[\'"]preload[\'"][^>]*>#i', $html, $m ) ) {
			foreach ( $m[0] as $tag ) {
				if ( ! preg_match( '#as=[\'"]font[\'"]|\.(?:woff2?|ttf|otf)#i', $tag ) ) {
					continue;
				}
				if ( preg_match( '#href=[\'"]([^\'"]+)#i', $tag, $hm ) ) {
					$preloads[] = $hm[1];
				}
			}
		}

		$bad = array();
		$woff2 = 0;
		foreach ( $preloads as $p ) {
			if ( preg_match( '#fonts\.gstatic\.com|fonts\.googleapis\.com|\.ttf($|\?)|\.woff($|\?)#i', $p ) && ! preg_match( '#\.woff2($|\?)#i', $p ) ) {
				$bad[] = self::short_url( $p );
			} elseif ( preg_match( '#\.ttf($|\?)#i', $p ) || preg_match( '#fonts\.gstatic#i', $p ) ) {
				$bad[] = self::short_url( $p );
			}
			if ( preg_match( '#\.woff2($|\?)#i', $p ) ) {
				$woff2++;
			}
		}

		$has_google = ( false !== stripos( $html, 'fonts.googleapis.com' ) || false !== stripos( $html, 'fonts.gstatic.com' ) );
		$has_swap   = ( false !== stripos( $html, 'font-display:swap' ) || false !== stripos( $html, 'font-display: swap' ) || false !== stripos( $html, 'display=swap' ) );

		$issues = $bad;
		if ( $has_google ) {
			$issues[] = 'Google Fonts هنوز در صفحه دیده می‌شود.';
		}
		if ( ! $has_swap ) {
			$issues[] = 'font-display:swap دیده نشد.';
		}

		$status = ( empty( $bad ) && ! $has_google && $has_swap ) ? 'ok' : ( count( $bad ) <= 3 ? 'warn' : 'bad' );

		return array(
			'id'      => 'fonts',
			'title'   => '۶) فونت · Swap · Preload',
			'status'  => $status,
			'summary' => sprintf( 'preload فونت=%d · woff2=%d · preload مضر=%d · google=%s · swap=%s', count( $preloads ), $woff2, count( $bad ), $has_google ? 'yes' : 'no', $has_swap ? 'yes' : 'no' ),
			'issues'  => array_slice( $issues, 0, 10 ),
			'actions' => array(
				'از منوی پنل سرعت → فونت سوییپ را باز کن و افزونه را روشن بگذار (حالت اجباری)',
				'اسکن دوباره بزن و کش WP Rocket را پاک کن',
				'در View Source باید WBS_FORCE_MODE=1 و wbfs-force-iransans دیده شود',
			),
		);
	}

	private static function step_lcp_images( $html ) {
		$candidates = array();
		if ( preg_match_all( '#<img\b[^>]*>#i', $html, $m ) ) {
			foreach ( array_slice( $m[0], 0, 8 ) as $i => $tag ) {
				$src = '';
				if ( preg_match( '#(?:data-src|src)=[\'"]([^\'"]+)#i', $tag, $sm ) ) {
					$src = $sm[1];
				}
				if ( ! $src || 0 === strpos( $src, 'data:' ) ) {
					continue;
				}
				$lazy = ( false !== stripos( $tag, 'loading=\'lazy\'' ) || false !== stripos( $tag, 'loading="lazy"' ) || false !== stripos( $tag, 'perfmatters-lazy' ) || false !== stripos( $tag, 'data-src=' ) );
				$prio = ( false !== stripos( $tag, 'fetchpriority' ) );
				$bytes = self::remote_size( $src );
				$candidates[] = array(
					'url'   => self::short_url( $src ),
					'lazy'  => $lazy,
					'prio'  => $prio,
					'kb'    => $bytes > 0 ? round( $bytes / 1024, 1 ) : null,
					'index' => $i + 1,
				);
			}
		}

		$issues = array();
		foreach ( array_slice( $candidates, 0, 3 ) as $c ) {
			if ( ! empty( $c['lazy'] ) && 1 === (int) $c['index'] ) {
				$issues[] = 'تصویر اول احتمالاً lazy است (برای LCP بد): ' . $c['url'];
			}
			if ( ! empty( $c['kb'] ) && $c['kb'] > 200 && (int) $c['index'] <= 2 ) {
				$issues[] = 'LCP سنگین ' . $c['kb'] . 'KB: ' . $c['url'];
			}
			if ( empty( $c['prio'] ) && (int) $c['index'] === 1 ) {
				$issues[] = 'fetchpriority=high روی تصویر اول دیده نشد: ' . $c['url'];
			}
		}

		$status = empty( $issues ) ? 'ok' : ( count( $issues ) <= 2 ? 'warn' : 'bad' );

		return array(
			'id'      => 'lcp_images',
			'title'   => '۷) LCP تصاویر',
			'status'  => $status,
			'summary' => sprintf( '%d کاندید اول صفحه بررسی شد', count( $candidates ) ),
			'issues'  => $issues,
			'actions' => array(
				'تصویر هیرو را از lazy خارج کن (Exclude Leading Images / fetchpriority)',
				'Perfmatters → Preload Critical Images = 1',
				'حجم هیرو را زیر ۲۰۰KB و WebP کن',
			),
			'candidates' => $candidates,
		);
	}

	private static function step_forced_reflow() {
		return array(
			'id'      => 'forced_reflow',
			'title'   => '۸) Forced reflow',
			'status'  => 'manual',
			'summary' => 'از HTML قابل اندازه‌گیری دقیق نیست — از Performance پنل کروم چک کن.',
			'issues'  => array(
				'معمولاً از خواندن layout بعد از تغییر DOM/فونت/اسلایدر می‌آید.',
			),
			'actions' => array(
				'Chrome DevTools → Performance → بگرد Forced reflow',
				'انیمیشن/اسلایدر بالای صفحه را سبک کن (Slick/WOW)',
				'فونت‌های دیررس را کم کن تا layout جابه‌جا نشود',
				'از تغییر ارتفاع بنر بعد از لود تصویر جلوگیری کن (ابعاد ثابت)',
			),
		);
	}

	private static function step_network_tree( $html ) {
		$hosts = array();
		if ( preg_match_all( '#https?://([^/"\'\s>]+)#i', $html, $m ) ) {
			foreach ( $m[1] as $h ) {
				$h = strtolower( $h );
				if ( false !== strpos( $h, 'gainstore.ir' ) || false !== strpos( home_url(), $h ) ) {
					continue;
				}
				$hosts[ $h ] = isset( $hosts[ $h ] ) ? $hosts[ $h ] + 1 : 1;
			}
		}
		arsort( $hosts );
		$third = array_slice( $hosts, 0, 12, true );

		$issues = array();
		foreach ( $third as $h => $n ) {
			$issues[] = $h . ' ×' . $n;
		}

		$count  = count( $third );
		$status = ( $count <= 4 ) ? 'ok' : ( $count <= 8 ? 'warn' : 'bad' );

		return array(
			'id'      => 'network_tree',
			'title'   => '۹) Network dependency tree',
			'status'  => $status,
			'summary' => sprintf( '%d دامنه شخص‌ثالث', $count ),
			'issues'  => $issues,
			'actions' => array(
				'اسکریپت‌های شخص‌ثالث را Delay کن (Zibal/Zarinpal/Chat/Analytics)',
				'Preconnect فقط برای ۲–۳ دامنه ضروری',
				'چت‌باکس و تراست‌کد را به تعامل کاربر موکول کن',
			),
		);
	}

	/** @return string[] */
	private static function collect_image_urls( $html ) {
		$urls = array();
		if ( preg_match_all( '#https?://[^"\'\s>]+\.(?:png|jpe?g|webp|gif)(?:\?[^"\'\s>]*)?#i', $html, $m ) ) {
			foreach ( $m[0] as $u ) {
				$urls[] = html_entity_decode( $u );
			}
		}
		return array_values( array_unique( $urls ) );
	}

	private static function remote_size( $url ) {
		$resp = wp_remote_head(
			$url,
			array(
				'timeout' => 8,
				'headers' => array(
					'Accept' => 'image/avif,image/webp,image/*,*/*;q=0.8',
				),
			)
		);
		if ( is_wp_error( $resp ) ) {
			return -1;
		}
		$len = wp_remote_retrieve_header( $resp, 'content-length' );
		return ( is_numeric( $len ) ) ? (int) $len : -1;
	}

	private static function short_url( $url ) {
		$home = untrailingslashit( home_url() );
		$url  = html_entity_decode( (string) $url );
		if ( 0 === strpos( $url, $home ) ) {
			return substr( $url, strlen( $home ) );
		}
		return $url;
	}
}
