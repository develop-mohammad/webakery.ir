<?php
/**
 * Plugin Name: تنظیمات Perfmatters | گین استور
 * Description: اعمال اجباری تنظیمات Perfmatters برای gainstore.ir + تشخیص اینکه Delay JS و Remove Unused CSS واقعاً در دیتابیس ذخیره شده‌اند یا نه.
 * Version:     1.1.0
 * Plugin URI:  https://webakery.ir
 * Author:      webakery.ir
 * Author URI:  https://webakery.ir
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Text Domain: gainstore-perfmatters-setup
 * License:     GPLv2 or later
 */

defined( 'ABSPATH' ) || exit;

define( 'GSPS_VERSION', '1.1.0' );
define( 'GSPS_FILE', __FILE__ );
define( 'GSPS_PATH', plugin_dir_path( __FILE__ ) );

register_activation_hook( __FILE__, 'gsps_activate' );

/**
 * Apply on activation.
 */
function gsps_activate() {
	gsps_apply_settings( true );
}

/**
 * Load bundled settings JSON.
 *
 * @return array|WP_Error
 */
function gsps_load_settings_file() {
	$file = GSPS_PATH . 'settings.json';
	if ( ! file_exists( $file ) ) {
		return new WP_Error( 'missing', 'فایل settings.json پیدا نشد.' );
	}
	$data = json_decode( file_get_contents( $file ), true );
	if ( ! is_array( $data ) || empty( $data['perfmatters_options'] ) ) {
		return new WP_Error( 'invalid', 'فایل تنظیمات نامعتبر است.' );
	}
	return $data;
}

/**
 * Force-write Perfmatters options and clear caches.
 *
 * @param bool $from_activation Whether called from activation hook.
 * @return array Result payload.
 */
function gsps_apply_settings( $from_activation = false ) {
	$data = gsps_load_settings_file();
	if ( is_wp_error( $data ) ) {
		$result = array(
			'ok'      => false,
			'message' => $data->get_error_message(),
			'time'    => time(),
		);
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

	// Hard replace options.
	update_option( 'perfmatters_options', $data['perfmatters_options'], false );
	if ( isset( $data['perfmatters_tools'] ) && is_array( $data['perfmatters_tools'] ) ) {
		update_option( 'perfmatters_tools', $data['perfmatters_tools'], false );
	}

	// Verify write.
	$saved   = get_option( 'perfmatters_options', array() );
	$delay   = ! empty( $saved['assets']['delay_js'] );
	$beh     = isset( $saved['assets']['delay_js_behavior'] ) ? (string) $saved['assets']['delay_js_behavior'] : '';
	$rucss   = ! empty( $saved['assets']['remove_unused_css'] );
	$verified = $delay && ( 'all' === $beh ) && $rucss;

	// Clear Perfmatters used CSS.
	if ( class_exists( '\\Perfmatters\\CSS' ) && method_exists( '\\Perfmatters\\CSS', 'clear_used_css' ) ) {
		\Perfmatters\CSS::clear_used_css();
	}

	// Clear WP Rocket if present.
	if ( function_exists( 'rocket_clean_domain' ) ) {
		rocket_clean_domain();
	}
	if ( function_exists( 'rocket_clean_minify' ) ) {
		rocket_clean_minify();
	}

	// LiteSpeed / common cache flush helpers.
	if ( class_exists( 'LiteSpeed\Purge' ) && method_exists( 'LiteSpeed\Purge', 'purge_all' ) ) {
		\LiteSpeed\Purge::purge_all();
	}
	if ( function_exists( 'wp_cache_flush' ) ) {
		wp_cache_flush();
	}

	$pm_active = defined( 'PERFMATTERS_VERSION' ) || class_exists( '\\Perfmatters\\Config' );
	$message   = $verified
		? 'تنظیمات در دیتابیس ذخیره شد: Delay JS=ON / Behavior=all / Remove Unused CSS=ON.'
		: 'ذخیره انجام شد ولی مقدارهای کلیدی تأیید نشد. Perfmatters را آپدیت/فعال کنید و دوباره اعمال کنید.';

	if ( ! $pm_active ) {
		$message .= ' Perfmatters فعال نیست.';
	}

	$message .= ' حالا کش را پاک کنید، با Incognito (خارج از لاگین) صفحه اصلی را باز کنید و در View Source دنبال pmdelayedscript بگردید.';

	$result = array(
		'ok'      => $verified,
		'message' => $message,
		'time'    => time(),
		'debug'   => array(
			'delay_js'            => $delay ? '1' : '0',
			'delay_js_behavior'   => $beh,
			'remove_unused_css'   => $rucss ? '1' : '0',
			'perfmatters_active'  => $pm_active ? '1' : '0',
			'perfmatters_version' => defined( 'PERFMATTERS_VERSION' ) ? PERFMATTERS_VERSION : '',
		),
	);

	update_option( 'gsps_import_result', $result, false );
	return $result;
}

/**
 * Admin menu.
 */
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
	gsps_apply_settings( false );
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
		update_option( 'perfmatters_options', $backup_options, false );
	}
	if ( null !== $backup_tools ) {
		update_option( 'perfmatters_tools', $backup_tools, false );
	}
	update_option( 'gsps_import_result', array(
		'ok'      => true,
		'message' => 'تنظیمات قبلی Perfmatters بازگردانی شد.',
		'time'    => time(),
	), false );

	wp_safe_redirect( admin_url( 'tools.php?page=gainstore-perfmatters-setup&restored=1' ) );
	exit;
} );

/**
 * Admin UI.
 */
function gsps_render_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$opts   = get_option( 'perfmatters_options', array() );
	$result = get_option( 'gsps_import_result', array() );
	$assets = isset( $opts['assets'] ) && is_array( $opts['assets'] ) ? $opts['assets'] : array();

	$delay = ! empty( $assets['delay_js'] ) ? 'ON' : 'OFF';
	$beh   = isset( $assets['delay_js_behavior'] ) ? (string) $assets['delay_js_behavior'] : '(empty=only specified)';
	$rucss = ! empty( $assets['remove_unused_css'] ) ? 'ON' : 'OFF';
	$method = isset( $assets['rucss_method'] ) ? (string) $assets['rucss_method'] : '(inline)';
	$sheet  = isset( $assets['rucss_stylesheet_behavior'] ) ? (string) $assets['rucss_stylesheet_behavior'] : '(delay)';

	$apply_url = wp_nonce_url( admin_url( 'admin-post.php?action=gsps_force_apply' ), 'gsps_force_apply' );
	$restore_url = wp_nonce_url( admin_url( 'admin-post.php?action=gsps_restore_backup' ), 'gsps_restore_backup' );
	$front = home_url( '/?nowprocket=1' );

	echo '<div class="wrap">';
	echo '<h1>گین استور → اعمال Perfmatters</h1>';

	if ( ! empty( $_GET['applied'] ) ) {
		echo '<div class="notice notice-success"><p>اعمال مجدد انجام شد.</p></div>';
	}
	if ( ! empty( $_GET['restored'] ) ) {
		echo '<div class="notice notice-success"><p>بکاپ قبلی بازگردانی شد.</p></div>';
	}

	if ( ! empty( $result['message'] ) ) {
		$class = ! empty( $result['ok'] ) ? 'notice-success' : 'notice-warning';
		echo '<div class="notice ' . esc_attr( $class ) . '"><p>' . esc_html( $result['message'] ) . '</p></div>';
	}

	echo '<h2>وضعیت فعلی در دیتابیس</h2>';
	echo '<table class="widefat striped" style="max-width:760px">';
	echo '<tbody>';
	echo '<tr><td>Perfmatters active</td><td>' . esc_html( defined( 'PERFMATTERS_VERSION' ) ? 'YES (' . PERFMATTERS_VERSION . ')' : 'NO' ) . '</td></tr>';
	echo '<tr><td>Delay JavaScript</td><td><strong>' . esc_html( $delay ) . '</strong></td></tr>';
	echo '<tr><td>Delay Behavior</td><td><strong>' . esc_html( $beh ) . '</strong> (باید <code>all</code> باشد)</td></tr>';
	echo '<tr><td>Remove Unused CSS</td><td><strong>' . esc_html( $rucss ) . '</strong></td></tr>';
	echo '<tr><td>Used CSS Method</td><td>' . esc_html( $method ) . '</td></tr>';
	echo '<tr><td>Stylesheet Behavior</td><td>' . esc_html( $sheet ) . '</td></tr>';
	echo '</tbody></table>';

	echo '<p style="margin-top:16px">';
	echo '<a class="button button-primary button-hero" href="' . esc_url( $apply_url ) . '">اعمال اجباری تنظیمات + پاک کردن کش</a> ';
	if ( get_option( 'gsps_backup_options', false ) ) {
		echo '<a class="button" href="' . esc_url( $restore_url ) . '">بازگردانی بکاپ قبلی</a>';
	}
	echo '</p>';

	echo '<h2>بعد از اعمال، این ۳ کار را بزن</h2>';
	echo '<ol>';
	echo '<li>در <strong>WP Rocket</strong> این‌ها را خاموش کن: Delay JS / Load JS deferred / Remove Unused CSS / Minify-Combine CSS-JS / LazyLoad</li>';
	echo '<li>با مرورگر ناشناس (Incognito) برو به: <a href="' . esc_url( $front ) . '" target="_blank" rel="noopener">' . esc_html( $front ) . '</a></li>';
	echo '<li>View Page Source بگیر و جستجو کن: <code>pmdelayedscript</code> و <code>perfmatters-used-css</code></li>';
	echo '</ol>';

	echo '<p><strong>مهم:</strong> Remove Unused CSS برای کاربر لاگین (ادمین) اعمال نمی‌شود. حتماً Incognito.</p>';
	echo '<p>اگر Delay Behavior مقدار <code>all</code> نبود، همین دکمه «اعمال اجباری» را بزن.</p>';
	echo '</div>';
}

add_action( 'admin_notices', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( $screen && 'plugins' !== $screen->id && 'tools_page_gainstore-perfmatters-setup' !== $screen->id ) {
		return;
	}
	echo '<div class="notice notice-info"><p><strong>گین استور Perfmatters:</strong> برای اعمال واقعی برو به <a href="' . esc_url( admin_url( 'tools.php?page=gainstore-perfmatters-setup' ) ) . '">ابزارها → گین استور Perfmatters</a> و دکمه «اعمال اجباری» را بزن.</p></div>';
} );
