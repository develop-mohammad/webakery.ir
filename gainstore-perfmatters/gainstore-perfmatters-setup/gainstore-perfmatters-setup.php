<?php
/**
 * Plugin Name: تنظیمات Perfmatters | گین استور
 * Description: اعمال تنظیمات + دیباگ + Fallback Delay/CSS وقتی WP Rocket بافر Perfmatters را خنثی می‌کند.
 * Version:     1.3.0
 * Plugin URI:  https://webakery.ir
 * Author:      webakery.ir
 * Author URI:  https://webakery.ir
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Text Domain: gainstore-perfmatters-setup
 * License:     GPLv2 or later
 */

defined( 'ABSPATH' ) || exit;

define( 'GSPS_VERSION', '1.3.0' );
define( 'GSPS_FILE', __FILE__ );
define( 'GSPS_PATH', plugin_dir_path( __FILE__ ) );
define( 'GSPS_DEBUG_KEY', 'gspsdebug' );

register_activation_hook( __FILE__, 'gsps_activate' );

function gsps_activate() {
	gsps_apply_settings();
}

function gsps_load_settings_file() {
	$file = GSPS_PATH . 'settings.json';
	if ( ! file_exists( $file ) ) {
		return new WP_Error( 'missing', 'settings.json پیدا نشد.' );
	}
	$data = json_decode( file_get_contents( $file ), true );
	if ( ! is_array( $data ) || empty( $data['perfmatters_options'] ) ) {
		return new WP_Error( 'invalid', 'settings.json نامعتبر است.' );
	}
	return $data;
}

function gsps_apply_settings() {
	$data = gsps_load_settings_file();
	if ( is_wp_error( $data ) ) {
		$result = array( 'ok' => false, 'message' => $data->get_error_message(), 'time' => time() );
		update_option( 'gsps_import_result', $result, false );
		return $result;
	}

	$current_options = get_option( 'perfmatters_options', null );
	$current_tools   = get_option( 'perfmatters_tools', null );
	if ( false === get_option( 'gsps_backup_options', false ) && null !== $current_options ) {
		update_option( 'gsps_backup_options', $current_options, false );
	}
	if ( false === get_option( 'gsps_backup_tools', false ) && null !== $current_tools ) {
		update_option( 'gsps_backup_tools', $current_tools, false );
	}

	delete_option( 'perfmatters_options' );
	add_option( 'perfmatters_options', $data['perfmatters_options'], '', false );
	if ( isset( $data['perfmatters_tools'] ) && is_array( $data['perfmatters_tools'] ) ) {
		delete_option( 'perfmatters_tools' );
		add_option( 'perfmatters_tools', $data['perfmatters_tools'], '', false );
	}

	if ( function_exists( 'wp_cache_delete' ) ) {
		wp_cache_delete( 'perfmatters_options', 'options' );
		wp_cache_delete( 'alloptions', 'options' );
	}

	$saved = get_option( 'perfmatters_options', array() );
	$delay = ! empty( $saved['assets']['delay_js'] );
	$beh   = isset( $saved['assets']['delay_js_behavior'] ) ? (string) $saved['assets']['delay_js_behavior'] : '';
	$rucss = ! empty( $saved['assets']['remove_unused_css'] );
	$ok    = $delay && ( 'all' === $beh ) && $rucss;

	if ( class_exists( '\\Perfmatters\\CSS' ) && method_exists( '\\Perfmatters\\CSS', 'clear_used_css' ) ) {
		\Perfmatters\CSS::clear_used_css();
	}
	if ( function_exists( 'rocket_clean_domain' ) ) {
		rocket_clean_domain();
	}
	if ( function_exists( 'rocket_clean_minify' ) ) {
		rocket_clean_minify();
	}
	if ( class_exists( 'LiteSpeed\Purge' ) && method_exists( 'LiteSpeed\Purge', 'purge_all' ) ) {
		\LiteSpeed\Purge::purge_all();
	}
	if ( function_exists( 'wp_cache_flush' ) ) {
		wp_cache_flush();
	}

	$result = array(
		'ok'      => $ok,
		'message' => 'v1.3 اعمال شد. کش Rocket را پاک کن، Incognito بزن، view-source را برای pmdelayedscript چک کن. اگر Perfmatters نزند، Fallback خود افزونه Delay را اعمال می‌کند.',
		'time'    => time(),
	);
	update_option( 'gsps_import_result', $result, false );
	return $result;
}

function gsps_collect_debug() {
	$opts   = get_option( 'perfmatters_options', array() );
	$assets = ( isset( $opts['assets'] ) && is_array( $opts['assets'] ) ) ? $opts['assets'] : array();
	$buffer_filters = function_exists( 'has_filter' ) ? (int) has_filter( 'perfmatters_output_buffer_template_redirect' ) : 0;

	return array(
		'gsps_version'        => GSPS_VERSION,
		'pm_active'           => ( defined( 'PERFMATTERS_VERSION' ) || class_exists( '\\Perfmatters\\Config' ) ) ? 1 : 0,
		'pm_version'          => defined( 'PERFMATTERS_VERSION' ) ? PERFMATTERS_VERSION : '',
		'delay_js_db'         => ! empty( $assets['delay_js'] ) ? 1 : 0,
		'delay_behavior_db'   => isset( $assets['delay_js_behavior'] ) ? (string) $assets['delay_js_behavior'] : '',
		'rucss_db'            => ! empty( $assets['remove_unused_css'] ) ? 1 : 0,
		'logged_in'           => is_user_logged_in() ? 1 : 0,
		'buffer_filter_count' => $buffer_filters,
		'allow_buffer'        => apply_filters( 'perfmatters_allow_buffer', true ) ? 1 : 0,
		'wp_rocket_active'    => defined( 'WP_ROCKET_VERSION' ) ? WP_ROCKET_VERSION : 0,
		'fallback'            => 1,
	);
}

add_action( 'wp_head', function () {
	if ( is_admin() ) {
		return;
	}
	$d = gsps_collect_debug();
	$short = sprintf(
		'GSPS v%s | pm=%s(%s) | delay_db=%s behavior=%s rucss_db=%s | buffer_filters=%s allow_buffer=%s | logged_in=%s | rocket=%s | fallback=ON',
		$d['gsps_version'],
		$d['pm_active'],
		$d['pm_version'],
		$d['delay_js_db'],
		$d['delay_behavior_db'] !== '' ? $d['delay_behavior_db'] : 'empty',
		$d['rucss_db'],
		$d['buffer_filter_count'],
		$d['allow_buffer'],
		$d['logged_in'],
		$d['wp_rocket_active']
	);
	echo "\n<!-- " . esc_html( $short ) . " -->\n";
	if ( isset( $_GET[ GSPS_DEBUG_KEY ] ) ) {
		echo '<!-- GSPS_DEBUG_JSON ' . esc_html( wp_json_encode( $d ) ) . " -->\n";
	}
}, 0 );

/**
 * Fallback output buffer: if Perfmatters options say Delay All but markers missing, apply delay ourselves.
 * Also async non-critical stylesheets and strip Google Fonts CSS links when configured.
 */
add_action( 'template_redirect', 'gsps_start_fallback_buffer', -9998 );

function gsps_start_fallback_buffer() {
	if ( is_admin() || is_feed() || is_preview() || ( function_exists( 'is_customize_preview' ) && is_customize_preview() ) ) {
		return;
	}
	if ( is_user_logged_in() ) {
		return; // match Perfmatters RUCSS behavior; keep admin views clean
	}

	ob_start( 'gsps_fallback_buffer_callback' );
}

function gsps_fallback_buffer_callback( $html ) {
	if ( ! is_string( $html ) || strlen( $html ) < 100 ) {
		return $html;
	}

	$opts   = get_option( 'perfmatters_options', array() );
	$assets = ( isset( $opts['assets'] ) && is_array( $opts['assets'] ) ) ? $opts['assets'] : array();
	$fonts  = ( isset( $opts['fonts'] ) && is_array( $opts['fonts'] ) ) ? $opts['fonts'] : array();

	$want_delay = ! empty( $assets['delay_js'] ) && ( ( $assets['delay_js_behavior'] ?? '' ) === 'all' );
	$want_rucss = ! empty( $assets['remove_unused_css'] );
	$disable_gf = ! empty( $fonts['disable_google_fonts'] );

	$did_delay = false;
	$did_css   = false;
	$did_gf    = false;

	// --- Google Fonts strip ---
	if ( $disable_gf && ( false !== strpos( $html, 'fonts.googleapis.com' ) || false !== strpos( $html, 'fonts.gstatic.com' ) ) ) {
		$html = preg_replace( '#<link[^>]+fonts\.googleapis\.com[^>]*>#i', '', $html );
		$html = preg_replace( '#<link[^>]+fonts\.gstatic\.com[^>]*>#i', '', $html );
		$html = preg_replace( '#<style[^>]*id=[\'"]wbfs-font-swap[\'"][^>]*>.*?</style>#is', '', $html );
		$did_gf = true;
	}

	// --- Delay JS fallback ---
	if ( $want_delay && false === strpos( $html, 'pmdelayedscript' ) ) {
		$exclusions = array(
			'lazyload.min.js',
			'lazyLoadOptions',
			'lazyLoadInstance',
			'perfmatters-lazy-load',
			'jquery.min.js',
			'jquery-core',
			'gsps-',
		);
		if ( ! empty( $assets['delay_js_exclusions'] ) && is_array( $assets['delay_js_exclusions'] ) ) {
			$exclusions = array_merge( $exclusions, $assets['delay_js_exclusions'] );
		}

		$html = preg_replace_callback(
			'#<script(\b[^>]*)>(.*?)</script>#is',
			function ( $m ) use ( $exclusions ) {
				$attrs = $m[1];
				$body  = $m[2];
				$full  = $m[0];

				// Skip already delayed / JSON / templates.
				if ( preg_match( '#type\s*=\s*[\'"](application/ld\+json|application/json|text/template|text/x-|pmdelayedscript|speculationrules)[\'"]#i', $attrs ) ) {
					return $full;
				}
				if ( preg_match( '#type\s*=\s*[\'"]module[\'"]#i', $attrs ) ) {
					return $full;
				}

				$hay = $attrs . ' ' . $body;
				foreach ( $exclusions as $ex ) {
					$ex = trim( (string) $ex );
					if ( $ex !== '' && stripos( $hay, $ex ) !== false ) {
						return $full;
					}
				}

				// Keep original type in data-type.
				$type = 'text/javascript';
				if ( preg_match( '#type\s*=\s*[\'"]([^\'"]+)[\'"]#i', $attrs, $tm ) ) {
					$type  = $tm[1];
					$attrs = preg_replace( '#\s*type\s*=\s*[\'"][^\'"]*[\'"]#i', '', $attrs );
				}
				$attrs = trim( $attrs );
				return '<script type="pmdelayedscript" data-type="' . esc_attr( $type ) . '" ' . $attrs . '>' . $body . '</script>';
			},
			$html
		);

		if ( false !== strpos( $html, 'pmdelayedscript' ) && false === strpos( $html, 'id="perfmatters-delayed-scripts-js"' ) && false === strpos( $html, 'id="gsps-delayed-scripts-js"' ) ) {
			$loader = '<script type="text/javascript" id="gsps-delayed-scripts-js">!function(){var t=setTimeout(n,1e4),e=["keydown","mousedown","mousemove","wheel","touchstart","touchmove","touchend"];function n(){clearTimeout(t),e.forEach(function(t){window.removeEventListener(t,n,{passive:!0})}),document.querySelectorAll("script[type=pmdelayedscript]").forEach(function(t){var e=document.createElement("script");[...t.attributes].forEach(function(t){var n=t.nodeName;"type"!==n&&("data-type"===n?e.type=t.nodeValue:e.setAttribute(n,t.nodeValue))}),t.text&&(e.text=t.text),t.parentNode.replaceChild(e,t)})}e.forEach(function(t){window.addEventListener(t,n,{passive:!0})})}();</script>';
			if ( false !== stripos( $html, '</body>' ) ) {
				$html = str_ireplace( '</body>', $loader . "\n</body>", $html );
			} else {
				$html .= $loader;
			}
			$did_delay = true;
		} elseif ( false !== strpos( $html, 'pmdelayedscript' ) ) {
			$did_delay = true;
		}
	}

	// --- CSS async fallback when Used CSS missing ---
	if ( $want_rucss && false === strpos( $html, 'perfmatters-used-css' ) ) {
		$html = preg_replace_callback(
			'#<link\b([^>]*rel=[\'"]stylesheet[\'"][^>]*)>#i',
			function ( $m ) {
				$tag = $m[0];
				$attrs = $m[1];
				if ( stripos( $attrs, 'fonts.googleapis.com' ) !== false ) {
					return ''; // drop if still present
				}
				// skip if already async/print trick
				if ( stripos( $attrs, 'onload=' ) !== false ) {
					return $tag;
				}
				// Keep tiny critical? Async all stylesheets for PSI render-blocking win.
				$new = $tag;
				$new = preg_replace( '#\smedia=[\'"][^\'"]*[\'"]#i', '', $new );
				$new = rtrim( substr( $new, 0, -1 ) ) . ' media="print" onload="this.media=\'all\'">';
				return $new . '<noscript>' . $tag . '</noscript>';
			},
			$html
		);
		$did_css = true;
	}

	$flag = sprintf(
		'GSPS_FALLBACK delay=%s css_async=%s strip_gfonts=%s has_pmdelay=%s has_usedcss=%s',
		$did_delay ? 'YES' : 'NO',
		$did_css ? 'YES' : 'NO',
		$did_gf ? 'YES' : 'NO',
		( false !== strpos( $html, 'pmdelayedscript' ) ) ? 'YES' : 'NO',
		( false !== strpos( $html, 'perfmatters-used-css' ) ) ? 'YES' : 'NO'
	);
	if ( false !== stripos( $html, '</body>' ) ) {
		$html = str_ireplace( '</body>', '<!-- ' . $flag . " -->\n</body>", $html );
	} else {
		$html .= "\n<!-- {$flag} -->\n";
	}

	return $html;
}

add_action( 'admin_menu', function () {
	add_management_page( 'گین استور Perfmatters', 'گین استور Perfmatters', 'manage_options', 'gainstore-perfmatters-setup', 'gsps_render_admin_page' );
} );

add_action( 'admin_post_gsps_force_apply', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Forbidden' );
	}
	check_admin_referer( 'gsps_force_apply' );
	gsps_apply_settings();
	wp_safe_redirect( admin_url( 'tools.php?page=gainstore-perfmatters-setup&applied=1' ) );
	exit;
} );

function gsps_render_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$opts   = get_option( 'perfmatters_options', array() );
	$assets = isset( $opts['assets'] ) && is_array( $opts['assets'] ) ? $opts['assets'] : array();
	$apply  = wp_nonce_url( admin_url( 'admin-post.php?action=gsps_force_apply' ), 'gsps_force_apply' );
	$debug  = home_url( '/?' . GSPS_DEBUG_KEY . '=1&nowprocket=1' );

	echo '<div class="wrap"><h1>گین استور Perfmatters v' . esc_html( GSPS_VERSION ) . '</h1>';
	echo '<div class="notice notice-success"><p><strong>تشخیص از GSPS:</strong> تنظیمات DB درست است (delay=ON / all / rucss=ON) ولی WP Rocket 3.16 بافر Perfmatters را خنثی می‌کند. نسخه ۱.۳ Fallback Delay/CSS دارد.</p></div>';
	echo '<p>Delay DB: <strong>' . ( ! empty( $assets['delay_js'] ) ? 'ON' : 'OFF' ) . '</strong> | Behavior: <strong>' . esc_html( $assets['delay_js_behavior'] ?? '' ) . '</strong> | RUCSS: <strong>' . ( ! empty( $assets['remove_unused_css'] ) ? 'ON' : 'OFF' ) . '</strong></p>';
	echo '<p><a class="button button-primary button-hero" href="' . esc_url( $apply ) . '">اعمال اجباری + پاک‌کردن کش</a></p>';
	echo '<h2>الان این کارها را بزن</h2><ol>';
	echo '<li>در WP Rocket این‌ها را <strong>خاموش</strong> کن: Delay JS، Load JS deferred، Remove Unused CSS، Minify/Combine CSS/JS، LazyLoad</li>';
	echo '<li>Clear cache کامل Rocket</li>';
	echo '<li>این افزونه را به ۱.۳ آپدیت و فعال کن، بعد Apply</li>';
	echo '<li>Incognito: <a href="' . esc_url( $debug ) . '" target="_blank">' . esc_html( $debug ) . '</a></li>';
	echo '<li>View Source → باید <code>pmdelayedscript</code> و <code>GSPS_FALLBACK delay=YES</code> را ببینی</li>';
	echo '</ol></div>';
}

add_action( 'admin_notices', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	echo '<div class="notice notice-error"><p><strong>گین استور:</strong> از دیباگ معلوم شد WP Rocket جلوی Delay پرفمترز را گرفته. ZIP نسخه <strong>1.3</strong> را نصب کن (Fallback). همچنین بهینه‌سازی فایل Rocket را خاموش کن. <a href="' . esc_url( admin_url( 'tools.php?page=gainstore-perfmatters-setup' ) ) . '">صفحه ابزار</a></p></div>';
} );
