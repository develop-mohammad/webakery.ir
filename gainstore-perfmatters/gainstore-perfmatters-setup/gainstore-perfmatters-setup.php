<?php
/**
 * Plugin Name: تنظیمات Perfmatters | گین استور
 * Description: اعمال اجباری + دیباگ داخل view-source برای gainstore.ir (علت نبودن pmdelayedscript را نشان می‌دهد).
 * Version:     1.2.0
 * Plugin URI:  https://webakery.ir
 * Author:      webakery.ir
 * Author URI:  https://webakery.ir
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Text Domain: gainstore-perfmatters-setup
 * License:     GPLv2 or later
 */

defined( 'ABSPATH' ) || exit;

define( 'GSPS_VERSION', '1.2.0' );
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

	// Bust object cache / stubborn options.
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
	if ( function_exists( 'opcache_reset' ) ) {
		@opcache_reset();
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
		'message' => $ok
			? 'ذخیره شد: delay_js=ON, behavior=all, rucss=ON. حالا این لینک را در Incognito باز کن و view-source را بفرست: ' . home_url( '/?' . GSPS_DEBUG_KEY . '=1&nowprocket=1' )
			: 'ذخیره تأیید نشد. Perfmatters را فعال/آپدیت کن و دوباره Apply بزن.',
		'time'    => time(),
		'debug'   => array(
			'delay_js'          => $delay ? '1' : '0',
			'delay_js_behavior' => $beh,
			'remove_unused_css' => $rucss ? '1' : '0',
			'pm_version'        => defined( 'PERFMATTERS_VERSION' ) ? PERFMATTERS_VERSION : '',
		),
	);
	update_option( 'gsps_import_result', $result, false );
	return $result;
}

/**
 * Collect runtime diagnostics for view-source comment.
 */
function gsps_collect_debug() {
	$opts   = get_option( 'perfmatters_options', array() );
	$assets = ( isset( $opts['assets'] ) && is_array( $opts['assets'] ) ) ? $opts['assets'] : array();

	$post_id = get_queried_object_id();
	$meta_delay = $post_id ? get_post_meta( $post_id, 'perfmatters_exclude_delay_js', true ) : '';
	$meta_rucss = $post_id ? get_post_meta( $post_id, 'perfmatters_exclude_unused_css', true ) : '';

	$buffer_filters = 0;
	if ( function_exists( 'has_filter' ) ) {
		$buffer_filters = (int) has_filter( 'perfmatters_output_buffer_template_redirect' );
	}

	$allow_buffer = apply_filters( 'perfmatters_allow_buffer', true );
	$allow_delay  = apply_filters( 'perfmatters_delay_js', ! empty( $assets['delay_js'] ) );
	$allow_rucss  = apply_filters( 'perfmatters_remove_unused_css', ! empty( $assets['remove_unused_css'] ) );

	$dynamic = function_exists( 'perfmatters_is_dynamic_request' ) ? (bool) perfmatters_is_dynamic_request() : null;
	$builder = function_exists( 'perfmatters_is_page_builder' ) ? (bool) perfmatters_is_page_builder() : null;

	return array(
		'gsps_version'        => GSPS_VERSION,
		'pm_active'           => ( defined( 'PERFMATTERS_VERSION' ) || class_exists( '\\Perfmatters\\Config' ) ) ? 1 : 0,
		'pm_version'          => defined( 'PERFMATTERS_VERSION' ) ? PERFMATTERS_VERSION : '',
		'delay_js_db'         => ! empty( $assets['delay_js'] ) ? 1 : 0,
		'delay_behavior_db'   => isset( $assets['delay_js_behavior'] ) ? (string) $assets['delay_js_behavior'] : '',
		'rucss_db'            => ! empty( $assets['remove_unused_css'] ) ? 1 : 0,
		'rucss_method_db'     => isset( $assets['rucss_method'] ) ? (string) $assets['rucss_method'] : '',
		'logged_in'           => is_user_logged_in() ? 1 : 0,
		'is_admin'            => is_admin() ? 1 : 0,
		'post_id'             => (int) $post_id,
		'meta_exclude_delay'  => $meta_delay ? 1 : 0,
		'meta_exclude_rucss'  => $meta_rucss ? 1 : 0,
		'buffer_filter_count' => $buffer_filters,
		'allow_buffer'        => $allow_buffer ? 1 : 0,
		'allow_delay_filter'  => $allow_delay ? 1 : 0,
		'allow_rucss_filter'  => $allow_rucss ? 1 : 0,
		'dynamic_request'     => null === $dynamic ? 'n/a' : ( $dynamic ? 1 : 0 ),
		'page_builder'        => null === $builder ? 'n/a' : ( $builder ? 1 : 0 ),
		'has_get_perfmatters' => isset( $_GET['perfmatters'] ) ? 1 : 0,
		'wp_rocket_active'    => defined( 'WP_ROCKET_VERSION' ) ? WP_ROCKET_VERSION : 0,
		'litespeed_active'    => defined( 'LSCWP_V' ) ? LSCWP_V : 0,
	);
}

/**
 * Print debug into HTML so it appears in view-source.
 * Always prints a short comment; full dump with ?gspsdebug=1
 */
add_action( 'wp_head', function () {
	if ( is_admin() ) {
		return;
	}

	$d = gsps_collect_debug();
	$short = sprintf(
		'GSPS v%s | pm=%s(%s) | delay_db=%s behavior=%s rucss_db=%s | buffer_filters=%s allow_buffer=%s | logged_in=%s | rocket=%s',
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
 * After output, if delay should run but markers missing, leave a footer note.
 * (We inspect via a shutdown buffer only when debug query present.)
 */
add_action( 'template_redirect', function () {
	if ( is_admin() || ! isset( $_GET[ GSPS_DEBUG_KEY ] ) ) {
		return;
	}
	ob_start( function ( $html ) {
		$d = gsps_collect_debug();
		$has_delay = ( false !== strpos( $html, 'pmdelayedscript' ) );
		$has_rucss = ( false !== strpos( $html, 'perfmatters-used-css' ) );
		$note      = sprintf(
			'GSPS_RESULT delay_marker=%s rucss_marker=%s delay_db=%s behavior=%s buffer_filters=%s allow_buffer=%s',
			$has_delay ? 'YES' : 'NO',
			$has_rucss ? 'YES' : 'NO',
			$d['delay_js_db'],
			$d['delay_behavior_db'] !== '' ? $d['delay_behavior_db'] : 'empty',
			$d['buffer_filter_count'],
			$d['allow_buffer']
		);
		if ( false !== stripos( $html, '</body>' ) ) {
			$html = str_ireplace( '</body>', "<!-- {$note} -->\n</body>", $html );
		} else {
			$html .= "\n<!-- {$note} -->\n";
		}
		return $html;
	} );
}, -10000 );

add_action( 'admin_menu', function () {
	add_management_page(
		'گین استور Perfmatters',
		'گین استور Perfmatters',
		'manage_options',
		'gainstore-perfmatters-setup',
		'gsps_render_admin_page'
	);
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

add_action( 'admin_post_gsps_restore_backup', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Forbidden' );
	}
	check_admin_referer( 'gsps_restore_backup' );
	$backup_options = get_option( 'gsps_backup_options', null );
	$backup_tools   = get_option( 'gsps_backup_tools', null );
	if ( null !== $backup_options ) {
		delete_option( 'perfmatters_options' );
		add_option( 'perfmatters_options', $backup_options, '', false );
	}
	if ( null !== $backup_tools ) {
		delete_option( 'perfmatters_tools' );
		add_option( 'perfmatters_tools', $backup_tools, '', false );
	}
	update_option( 'gsps_import_result', array(
		'ok'      => true,
		'message' => 'بکاپ قبلی بازگردانی شد.',
		'time'    => time(),
	), false );
	wp_safe_redirect( admin_url( 'tools.php?page=gainstore-perfmatters-setup&restored=1' ) );
	exit;
} );

function gsps_render_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$opts   = get_option( 'perfmatters_options', array() );
	$result = get_option( 'gsps_import_result', array() );
	$assets = isset( $opts['assets'] ) && is_array( $opts['assets'] ) ? $opts['assets'] : array();
	$delay  = ! empty( $assets['delay_js'] ) ? 'ON' : 'OFF';
	$beh    = isset( $assets['delay_js_behavior'] ) ? (string) $assets['delay_js_behavior'] : '(empty)';
	$rucss  = ! empty( $assets['remove_unused_css'] ) ? 'ON' : 'OFF';

	$apply_url   = wp_nonce_url( admin_url( 'admin-post.php?action=gsps_force_apply' ), 'gsps_force_apply' );
	$restore_url = wp_nonce_url( admin_url( 'admin-post.php?action=gsps_restore_backup' ), 'gsps_restore_backup' );
	$debug_url   = home_url( '/?' . GSPS_DEBUG_KEY . '=1&nowprocket=1' );

	echo '<div class="wrap"><h1>گین استور Perfmatters v' . esc_html( GSPS_VERSION ) . '</h1>';
	if ( ! empty( $_GET['applied'] ) ) {
		echo '<div class="notice notice-success"><p>اعمال شد.</p></div>';
	}
	if ( ! empty( $result['message'] ) ) {
		echo '<div class="notice notice-info"><p>' . esc_html( $result['message'] ) . '</p></div>';
	}

	echo '<table class="widefat striped" style="max-width:820px"><tbody>';
	echo '<tr><td>Perfmatters</td><td>' . esc_html( defined( 'PERFMATTERS_VERSION' ) ? PERFMATTERS_VERSION : 'INACTIVE' ) . '</td></tr>';
	echo '<tr><td>Delay JS (DB)</td><td><strong>' . esc_html( $delay ) . '</strong></td></tr>';
	echo '<tr><td>Delay Behavior (DB)</td><td><strong>' . esc_html( $beh ) . '</strong> ← باید all باشد</td></tr>';
	echo '<tr><td>Remove Unused CSS (DB)</td><td><strong>' . esc_html( $rucss ) . '</strong></td></tr>';
	echo '</tbody></table>';

	echo '<p style="margin-top:18px"><a class="button button-primary button-hero" href="' . esc_url( $apply_url ) . '">۱) اعمال اجباری + پاک‌کردن کش</a> ';
	if ( get_option( 'gsps_backup_options', false ) ) {
		echo '<a class="button" href="' . esc_url( $restore_url ) . '">بازگردانی بکاپ</a>';
	}
	echo '</p>';

	echo '<h2>۲) دیباگ داخل view-source</h2>';
	echo '<p>این لینک را در <strong>Incognito</strong> باز کن، بعد View Source بگیر و خطی که با <code>GSPS</code> شروع می‌شود را کپی کن برام بفرست:</p>';
	echo '<p><a class="button button-secondary" href="' . esc_url( $debug_url ) . '" target="_blank" rel="noopener">' . esc_html( $debug_url ) . '</a></p>';
	echo '<p>در view-source جستجو کن: <code>GSPS</code> و <code>GSPS_RESULT</code> و <code>pmdelayedscript</code></p>';
	echo '<p><strong>مهم:</strong> اسکرین قبلی از kianstock بود. این کارها باید روی <code>gainstore.ir/wp-admin</code> باشد.</p>';
	echo '</div>';
}

add_action( 'admin_notices', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	echo '<div class="notice notice-warning"><p><strong>گین استور:</strong> Delay JS روی فرانت نیست. برو <a href="' . esc_url( admin_url( 'tools.php?page=gainstore-perfmatters-setup' ) ) . '">ابزارها → گین استور Perfmatters</a> → اعمال اجباری، بعد لینک دیباگ را در Incognito باز کن و متن <code>GSPS</code> داخل view-source را بفرست.</p></div>';
} );
