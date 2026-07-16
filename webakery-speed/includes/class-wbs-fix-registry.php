<?php
/**
 * Available fixes registry.
 *
 * @package WebakerySpeed
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Maps PageSpeed audits to safe fixes.
 */
class WBS_Fix_Registry {

	/**
	 * All fixes metadata.
	 *
	 * @return array
	 */
	public static function all() {
		return array(
			'defer_js' => array(
				'title'       => __( 'Defer جاوااسکریپت غیرضروری', 'webakery-speed' ),
				'description' => __( 'اسکریپت‌های غیر بحرانی را defer می‌کند تا render-blocking کم شود.', 'webakery-speed' ),
				'risk'        => 'low',
				'audits'      => array( 'render-blocking-resources', 'unused-javascript', 'bootup-time', 'mainthread-work-breakdown' ),
				'file'        => 'class-wbs-fix-defer-js.php',
				'class'       => 'WBS_Fix_Defer_JS',
			),
			'async_css' => array(
				'title'       => __( 'CSS غیر بحرانی به‌صورت async', 'webakery-speed' ),
				'description' => __( 'فایل‌های CSS غیرضروری را non-blocking بارگذاری می‌کند.', 'webakery-speed' ),
				'risk'        => 'medium',
				'audits'      => array( 'render-blocking-resources' ),
				'file'        => 'class-wbs-fix-async-css.php',
				'class'       => 'WBS_Fix_Async_CSS',
			),
			'lazyload' => array(
				'title'       => __( 'Lazy load تصاویر و iframe', 'webakery-speed' ),
				'description' => __( 'تصاویر خارج از viewport و iframeها را lazy load می‌کند.', 'webakery-speed' ),
				'risk'        => 'low',
				'audits'      => array( 'offscreen-images', 'uses-optimized-images' ),
				'file'        => 'class-wbs-fix-lazyload.php',
				'class'       => 'WBS_Fix_Lazyload',
			),
			'image_dimensions' => array(
				'title'       => __( 'ابعاد تصاویر (جلوگیری از CLS)', 'webakery-speed' ),
				'description' => __( 'width و height به تصاویر بدون ابعاد اضافه می‌کند.', 'webakery-speed' ),
				'risk'        => 'low',
				'audits'      => array( 'unsized-images', 'layout-shift-elements', 'cumulative-layout-shift' ),
				'file'        => 'class-wbs-fix-image-dimensions.php',
				'class'       => 'WBS_Fix_Image_Dimensions',
			),
			'font_display' => array(
				'title'       => __( 'font-display: swap', 'webakery-speed' ),
				'description' => __( 'برای فونت‌های Google و CSS فونت، swap اعمال می‌شود.', 'webakery-speed' ),
				'risk'        => 'low',
				'audits'      => array( 'font-display' ),
				'file'        => 'class-wbs-fix-font-display.php',
				'class'       => 'WBS_Fix_Font_Display',
			),
			'preload_fonts' => array(
				'title'       => __( 'Preload فونت‌های مهم', 'webakery-speed' ),
				'description' => __( 'فایل‌های woff2/woff و CSS فونت Google را preload می‌کند تا فونت زودتر لود شود.', 'webakery-speed' ),
				'risk'        => 'low',
				'audits'      => array( 'preload-fonts', 'font-display', 'network-requests' ),
				'file'        => 'class-wbs-fix-preload-fonts.php',
				'class'       => 'WBS_Fix_Preload_Fonts',
			),
			'preconnect' => array(
				'title'       => __( 'Preconnect به دامنه‌های مهم', 'webakery-speed' ),
				'description' => __( 'preconnect/dns-prefetch برای Google Fonts و CDNهای رایج.', 'webakery-speed' ),
				'risk'        => 'low',
				'audits'      => array( 'uses-rel-preconnect', 'network-requests' ),
				'file'        => 'class-wbs-fix-preconnect.php',
				'class'       => 'WBS_Fix_Preconnect',
			),
			'disable_emojis' => array(
				'title'       => __( 'غیرفعال‌سازی Emoji وردپرس', 'webakery-speed' ),
				'description' => __( 'اسکریپت و استایل emoji پیش‌فرض وردپرس را حذف می‌کند.', 'webakery-speed' ),
				'risk'        => 'low',
				'audits'      => array( 'unused-javascript', 'bootup-time' ),
				'file'        => 'class-wbs-fix-disable-emojis.php',
				'class'       => 'WBS_Fix_Disable_Emojis',
			),
			'cache_headers' => array(
				'title'       => __( 'هدر Cache-Control برای فایل‌های استاتیک', 'webakery-speed' ),
				'description' => __( 'برای assetهای استاتیک هدر کش مرورگر اضافه می‌کند.', 'webakery-speed' ),
				'risk'        => 'low',
				'audits'      => array( 'uses-long-cache-ttl', 'efficient-cache-policy' ),
				'file'        => 'class-wbs-fix-cache-headers.php',
				'class'       => 'WBS_Fix_Cache_Headers',
			),
			'preload_lcp' => array(
				'title'       => __( 'Preload تصویر LCP', 'webakery-speed' ),
				'description' => __( 'تصویر بزرگ صفحه (LCP) را preload می‌کند.', 'webakery-speed' ),
				'risk'        => 'medium',
				'audits'      => array( 'largest-contentful-paint-element', 'lcp-lazy-loaded' ),
				'file'        => 'class-wbs-fix-preload-lcp.php',
				'class'       => 'WBS_Fix_Preload_LCP',
			),
		);
	}

	/**
	 * Get one fix.
	 *
	 * @param string $slug Slug.
	 * @return array|null
	 */
	public static function get( $slug ) {
		$all = self::all();
		return isset( $all[ $slug ] ) ? $all[ $slug ] : null;
	}

	/**
	 * Safe fixes only.
	 *
	 * @return array
	 */
	public static function safe_slugs() {
		$safe = array();
		foreach ( self::all() as $slug => $fix ) {
			if ( 'low' === $fix['risk'] ) {
				$safe[] = $slug;
			}
		}
		return $safe;
	}

	/**
	 * Map audit id to fix slugs.
	 *
	 * @param string $audit_id Audit ID.
	 * @return array
	 */
	public static function fixes_for_audit( $audit_id ) {
		$matches = array();
		foreach ( self::all() as $slug => $fix ) {
			if ( in_array( $audit_id, $fix['audits'], true ) ) {
				$matches[] = $slug;
			}
		}
		return $matches;
	}
}
