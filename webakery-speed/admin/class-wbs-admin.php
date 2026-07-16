<?php
/**
 * Admin dashboard.
 *
 * @package WebakerySpeed
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings and scan UI.
 */
class WBS_Admin {

	/**
	 * Init hooks.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'handle_actions' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'wp_ajax_wbs_import_report', array( __CLASS__, 'ajax_import_report' ) );
		add_action( 'wp_ajax_wbs_scan', array( __CLASS__, 'ajax_scan' ) );
		add_action( 'wp_ajax_wbs_apply_safe', array( __CLASS__, 'ajax_apply_safe' ) );
		add_action( 'wp_ajax_wbs_disable_all', array( __CLASS__, 'ajax_disable_all' ) );
	}

	/**
	 * Register menu.
	 */
	public static function menu() {
		add_menu_page(
			__( 'Webakery Speed', 'webakery-speed' ),
			__( 'PageSpeed', 'webakery-speed' ),
			'manage_options',
			'webakery-speed',
			array( __CLASS__, 'render_page' ),
			'dashicons-performance',
			58
		);
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Hook suffix.
	 */
	public static function assets( $hook ) {
		if ( 'toplevel_page_webakery-speed' !== $hook ) {
			return;
		}

		wp_enqueue_style( 'wbs-admin', WBS_URL . 'admin/css/admin.css', array(), WBS_VERSION );
		wp_enqueue_script( 'wbs-admin', WBS_URL . 'admin/js/admin.js', array(), WBS_VERSION, true );
		wp_localize_script(
			'wbs-admin',
			'wbsAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'wbs_admin' ),
				'i18n'    => array(
					'importing' => __( 'در حال دریافت گزارش PageSpeed…', 'webakery-speed' ),
					'scanning'  => __( 'در حال اسکن PageSpeed…', 'webakery-speed' ),
					'applying'  => __( 'در حال اعمال اصلاحات امن…', 'webakery-speed' ),
					'disabling' => __( 'در حال خاموش کردن همه اصلاحات…', 'webakery-speed' ),
					'done'      => __( 'انجام شد.', 'webakery-speed' ),
					'error'     => __( 'خطا رخ داد.', 'webakery-speed' ),
				),
			)
		);
	}

	/**
	 * Handle form posts.
	 */
	public static function handle_actions() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( isset( $_POST['wbs_save_settings'] ) && check_admin_referer( 'wbs_save_settings' ) ) {
			WBS_Settings::update( wp_unslash( $_POST['wbs_settings'] ?? array() ) );
			add_settings_error( 'wbs', 'saved', __( 'تنظیمات ذخیره شد.', 'webakery-speed' ), 'updated' );
		}

		if ( isset( $_POST['wbs_import_json'] ) && check_admin_referer( 'wbs_import_json' ) ) {
			$json = wp_unslash( $_POST['wbs_json_report'] ?? '' );
			$res  = WBS_Scanner::import_json( $json );
			if ( is_wp_error( $res ) ) {
				add_settings_error( 'wbs', 'import_error', $res->get_error_message(), 'error' );
			} else {
				add_settings_error( 'wbs', 'import_ok', __( 'گزارش PageSpeed وارد شد.', 'webakery-speed' ), 'updated' );
			}
		}

		if ( ( isset( $_POST['wbs_import_report_url'] ) || isset( $_POST['wbs_import_and_fix'] ) ) && check_admin_referer( 'wbs_import_report_url' ) ) {
			$report_url = esc_url_raw( wp_unslash( $_POST['wbs_report_url'] ?? '' ) );
			$auto_apply = isset( $_POST['wbs_import_and_fix'] );
			$res        = WBS_Scanner::import_report_url( $report_url, $auto_apply );

			if ( is_wp_error( $res ) ) {
				add_settings_error( 'wbs', 'report_error', $res->get_error_message(), 'error' );
			} else {
				$message = sprintf(
					/* translators: 1: site URL, 2: performance score */
					__( 'گزارش %1$s دریافت شد. امتیاز Performance: %2$s', 'webakery-speed' ),
					$res['url'],
					null !== $res['performance'] ? (string) $res['performance'] : '—'
				);
				if ( ! empty( $res['fetch_method'] ) && 'embedded' === $res['fetch_method'] ) {
					$message .= ' — ' . __( 'از pagespeed.web.dev (بدون API)', 'webakery-speed' );
				}
				if ( $auto_apply && ! empty( $res['applied_fixes'] ) ) {
					$message .= ' — ' . sprintf(
						/* translators: %d: number of fixes */
						__( '%d اصلاح امن فعال شد.', 'webakery-speed' ),
						count( $res['applied_fixes'] )
					);
				}
				add_settings_error( 'wbs', 'report_ok', $message, 'updated' );
			}
		}

		if ( isset( $_POST['wbs_emergency_off'] ) && check_admin_referer( 'wbs_emergency_off' ) ) {
			WBS_Fix_Manager::disable_all_fixes();
			add_settings_error( 'wbs', 'off', __( 'همه اصلاحات خاموش شد. سایت به حالت قبل برگشت.', 'webakery-speed' ), 'updated' );
		}
	}

	/**
	 * AJAX import pagespeed.web.dev report URL.
	 */
	public static function ajax_import_report() {
		check_ajax_referer( 'wbs_admin', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'دسترسی ندارید.', 'webakery-speed' ) ), 403 );
		}

		$report_url = esc_url_raw( wp_unslash( $_POST['report_url'] ?? '' ) );
		$auto_apply = ! empty( $_POST['auto_apply'] );

		$result = WBS_Scanner::import_report_url( $report_url, $auto_apply );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success(
			array(
				'scan'    => $result,
				'message' => __( 'گزارش PageSpeed دریافت شد.', 'webakery-speed' ),
			)
		);
	}

	/**
	 * AJAX scan.
	 */
	public static function ajax_scan() {
		check_ajax_referer( 'wbs_admin', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'دسترسی ندارید.', 'webakery-speed' ) ), 403 );
		}

		$result = WBS_Scanner::run();
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'scan' => $result ) );
	}

	/**
	 * AJAX apply safe fixes.
	 */
	public static function ajax_apply_safe() {
		check_ajax_referer( 'wbs_admin', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'دسترسی ندارید.', 'webakery-speed' ) ), 403 );
		}

		$applied = WBS_Scanner::apply_suggested( true );
		wp_send_json_success(
			array(
				'applied' => $applied,
				'message' => sprintf(
					/* translators: %d: number of fixes */
					__( '%d اصلاح امن فعال شد.', 'webakery-speed' ),
					count( $applied )
				),
			)
		);
	}

	/**
	 * AJAX disable all.
	 */
	public static function ajax_disable_all() {
		check_ajax_referer( 'wbs_admin', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'دسترسی ندارید.', 'webakery-speed' ) ), 403 );
		}

		WBS_Fix_Manager::disable_all_fixes();
		wp_send_json_success( array( 'message' => __( 'همه اصلاحات خاموش شد.', 'webakery-speed' ) ) );
	}

	/**
	 * Render admin page.
	 */
	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings = WBS_Settings::get();
		$scan     = WBS_Settings::get_last_scan();
		$fixes    = WBS_Fix_Registry::all();
		$enabled  = isset( $settings['enabled_fixes'] ) ? $settings['enabled_fixes'] : array();
		settings_errors( 'wbs' );
		?>
		<div class="wrap wbs-admin" dir="rtl">
			<h1><?php esc_html_e( 'Webakery Speed — رفع خطاهای PageSpeed', 'webakery-speed' ); ?></h1>
			<p class="wbs-lead">
				<?php esc_html_e( 'گزارش Google PageSpeed را می‌گیریم، خطاها را نشان می‌دهیم و فقط اصلاحات امن را — با قابلیت خاموش‌کردن فوری — اعمال می‌کنیم.', 'webakery-speed' ); ?>
			</p>

			<div class="wbs-grid">
				<section class="wbs-card wbs-card--highlight">
					<h2><?php esc_html_e( '۱. دریافت گزارش از PageSpeed.web.dev', 'webakery-speed' ); ?></h2>
					<p class="description">
						<?php esc_html_e( 'لینک گزارش pagespeed.web.dev را paste کنید. پلاگین همان گزارش Lighthouse را از صفحه می‌خواند (بدون نیاز به API)، خطاها را نشان می‌دهد و با یک کلیک اصلاحات امن را فعال می‌کند.', 'webakery-speed' ); ?>
					</p>
					<form method="post" class="wbs-form">
						<?php wp_nonce_field( 'wbs_import_report_url' ); ?>
						<p>
							<input
								type="url"
								class="large-text code"
								name="wbs_report_url"
								id="wbs_report_url"
								dir="ltr"
								placeholder="https://pagespeed.web.dev/analysis/https-kianstock-ir/1575e77yov?form_factor=mobile"
								value="<?php echo esc_attr( is_array( $scan ) ? ( $scan['report_url'] ?? '' ) : '' ); ?>"
							/>
						</p>
						<p class="submit">
							<button type="submit" name="wbs_import_report_url" class="button button-secondary"><?php esc_html_e( 'دریافت گزارش', 'webakery-speed' ); ?></button>
							<button type="submit" name="wbs_import_and_fix" class="button button-primary"><?php esc_html_e( 'دریافت گزارش + اعمال اصلاحات امن', 'webakery-speed' ); ?></button>
							<button type="button" class="button button-secondary" id="wbs-import-report-fix"><?php esc_html_e( 'دریافت و اصلاح (Ajax)', 'webakery-speed' ); ?></button>
						</p>
					</form>
				</section>

				<section class="wbs-card">
					<h2><?php esc_html_e( '۲. اسکن دستی سایت', 'webakery-speed' ); ?></h2>
					<form method="post" class="wbs-form">
						<?php wp_nonce_field( 'wbs_save_settings' ); ?>
						<table class="form-table" role="presentation">
							<tr>
								<th><label for="wbs_scan_url"><?php esc_html_e( 'آدرس سایت', 'webakery-speed' ); ?></label></th>
								<td><input type="url" class="regular-text" id="wbs_scan_url" name="wbs_settings[scan_url]" value="<?php echo esc_attr( $settings['scan_url'] ); ?>" dir="ltr" /></td>
							</tr>
							<tr>
								<th><label for="wbs_api_key"><?php esc_html_e( 'کلید API PageSpeed', 'webakery-speed' ); ?></label></th>
								<td>
									<input type="text" class="large-text" id="wbs_api_key" name="wbs_settings[psi_api_key]" value="<?php echo esc_attr( $settings['psi_api_key'] ); ?>" dir="ltr" placeholder="AIza..." />
									<p class="description">
										<?php
										printf(
											/* translators: %s: Google Cloud link */
											esc_html__( 'از %s یک API Key برای PageSpeed Insights بگیرید.', 'webakery-speed' ),
											'<a href="https://console.cloud.google.com/apis/library/pagespeedonline.googleapis.com" target="_blank" rel="noopener">Google Cloud</a>'
										);
										?>
									</p>
								</td>
							</tr>
							<tr>
								<th><?php esc_html_e( 'نوع اسکن', 'webakery-speed' ); ?></th>
								<td>
									<label><input type="radio" name="wbs_settings[strategy]" value="mobile" <?php checked( $settings['strategy'], 'mobile' ); ?> /> <?php esc_html_e( 'موبایل', 'webakery-speed' ); ?></label>
									&nbsp;
									<label><input type="radio" name="wbs_settings[strategy]" value="desktop" <?php checked( $settings['strategy'], 'desktop' ); ?> /> <?php esc_html_e( 'دسکتاپ', 'webakery-speed' ); ?></label>
								</td>
							</tr>
							<tr>
								<th><?php esc_html_e( 'حالت امن', 'webakery-speed' ); ?></th>
								<td>
									<label>
										<input type="checkbox" name="wbs_settings[safe_mode]" value="1" <?php checked( $settings['safe_mode'] ); ?> />
										<?php esc_html_e( 'فقط اصلاحات کم‌ریسک به‌صورت خودکار پیشنهاد شود', 'webakery-speed' ); ?>
									</label>
								</td>
							</tr>
							<tr>
								<th><?php esc_html_e( 'فعال بودن پلاگین', 'webakery-speed' ); ?></th>
								<td>
									<label>
										<input type="checkbox" name="wbs_settings[enabled]" value="1" <?php checked( $settings['enabled'] ); ?> />
										<?php esc_html_e( 'اصلاحات فعال باشند', 'webakery-speed' ); ?>
									</label>
								</td>
							</tr>
						</table>
						<p class="submit">
							<button type="submit" name="wbs_save_settings" class="button button-primary"><?php esc_html_e( 'ذخیره تنظیمات', 'webakery-speed' ); ?></button>
							<button type="button" class="button button-secondary" id="wbs-run-scan"><?php esc_html_e( 'اسکن PageSpeed', 'webakery-speed' ); ?></button>
							<button type="button" class="button button-secondary" id="wbs-apply-safe"><?php esc_html_e( 'اعمال اصلاحات امن پیشنهادی', 'webakery-speed' ); ?></button>
						</p>
					</form>

					<form method="post" class="wbs-form wbs-import">
						<?php wp_nonce_field( 'wbs_import_json' ); ?>
						<h3><?php esc_html_e( 'یا گزارش JSON را وارد کنید', 'webakery-speed' ); ?></h3>
						<p class="description"><?php esc_html_e( 'خروجی افزونه کروم Webakery Speed (دکمه کپی JSON) یا گزارش Lighthouse را اینجا paste کنید.', 'webakery-speed' ); ?></p>
						<textarea name="wbs_json_report" rows="4" class="large-text code" dir="ltr" placeholder='{"lighthouseResult":{...}}'></textarea>
						<p><button type="submit" name="wbs_import_json" class="button"><?php esc_html_e( 'وارد کردن گزارش', 'webakery-speed' ); ?></button></p>
					</form>
				</section>

				<section class="wbs-card wbs-card--danger">
					<h2><?php esc_html_e( 'خاموش کردن فوری (بدون خراب کردن سایت)', 'webakery-speed' ); ?></h2>
					<p><?php esc_html_e( 'اگر بعد از بهینه‌سازی مشکلی دیدید، یک کلیک همه اصلاحات را خاموش می‌کند.', 'webakery-speed' ); ?></p>
					<form method="post" onsubmit="return confirm('<?php echo esc_js( __( 'همه اصلاحات خاموش شود؟', 'webakery-speed' ) ); ?>');">
						<?php wp_nonce_field( 'wbs_emergency_off' ); ?>
						<button type="submit" name="wbs_emergency_off" class="button button-link-delete" id="wbs-disable-all"><?php esc_html_e( 'خاموش کردن همه اصلاحات', 'webakery-speed' ); ?></button>
					</form>
				</section>
			</div>

			<?php if ( $scan ) : ?>
				<section class="wbs-card">
					<h2><?php esc_html_e( '۳. نتیجه آخرین اسکن', 'webakery-speed' ); ?></h2>
					<div class="wbs-scan-summary">
						<div><strong><?php esc_html_e( 'آدرس:', 'webakery-speed' ); ?></strong> <?php echo esc_html( $scan['url'] ); ?></div>
						<?php if ( ! empty( $scan['report_url'] ) ) : ?>
							<div><strong><?php esc_html_e( 'لینک گزارش:', 'webakery-speed' ); ?></strong> <a href="<?php echo esc_url( $scan['report_url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $scan['report_url'] ); ?></a></div>
						<?php endif; ?>
						<div><strong><?php esc_html_e( 'استراتژی:', 'webakery-speed' ); ?></strong> <?php echo esc_html( $scan['strategy'] ?? 'mobile' ); ?></div>
						<div><strong><?php esc_html_e( 'امتیاز Performance:', 'webakery-speed' ); ?></strong>
							<span class="wbs-score wbs-score--<?php echo esc_attr( self::score_class( $scan['performance'] ) ); ?>">
								<?php echo null !== $scan['performance'] ? esc_html( $scan['performance'] ) : '—'; ?>
							</span>
						</div>
						<div><strong><?php esc_html_e( 'تاریخ:', 'webakery-speed' ); ?></strong> <?php echo esc_html( $scan['scanned_at'] ); ?></div>
						<?php if ( ! empty( $scan['fetch_method'] ) ) : ?>
							<div><strong><?php esc_html_e( 'روش دریافت:', 'webakery-speed' ); ?></strong>
								<?php
								echo 'embedded' === $scan['fetch_method']
									? esc_html__( 'مستقیم از pagespeed.web.dev', 'webakery-speed' )
									: esc_html__( 'PageSpeed API', 'webakery-speed' );
								?>
							</div>
						<?php endif; ?>
					</div>

					<?php if ( ! empty( $scan['issues'] ) ) : ?>
						<table class="widefat striped wbs-issues">
							<thead>
								<tr>
									<th><?php esc_html_e( 'خطا', 'webakery-speed' ); ?></th>
									<th><?php esc_html_e( 'امتیاز', 'webakery-speed' ); ?></th>
									<th><?php esc_html_e( 'اصلاح پیشنهادی', 'webakery-speed' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $scan['issues'] as $issue ) : ?>
									<tr>
										<td>
											<strong><?php echo esc_html( $issue['title'] ); ?></strong>
											<?php if ( ! empty( $issue['display'] ) ) : ?>
												<br /><small><?php echo esc_html( $issue['display'] ); ?></small>
											<?php endif; ?>
										</td>
										<td><?php echo esc_html( round( $issue['score'] * 100 ) ); ?></td>
										<td>
											<?php
											if ( empty( $issue['suggested'] ) ) {
												esc_html_e( '—', 'webakery-speed' );
											} else {
												$labels = array();
												foreach ( $issue['suggested'] as $slug ) {
													if ( isset( $fixes[ $slug ] ) ) {
														$labels[] = $fixes[ $slug ]['title'];
													}
												}
												echo esc_html( implode( '، ', $labels ) );
											}
											?>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					<?php else : ?>
						<p><?php esc_html_e( 'خطای مهمی یافت نشد یا هنوز اسکن انجام نشده.', 'webakery-speed' ); ?></p>
					<?php endif; ?>
				</section>
			<?php endif; ?>

			<section class="wbs-card">
				<h2><?php esc_html_e( '۴. اصلاحات قابل اعمال (دستی)', 'webakery-speed' ); ?></h2>
				<form method="post">
					<?php wp_nonce_field( 'wbs_save_settings' ); ?>
					<input type="hidden" name="wbs_settings[_update_fixes]" value="1" />
					<input type="hidden" name="wbs_settings[scan_url]" value="<?php echo esc_attr( $settings['scan_url'] ); ?>" />
					<input type="hidden" name="wbs_settings[psi_api_key]" value="<?php echo esc_attr( $settings['psi_api_key'] ); ?>" />
					<input type="hidden" name="wbs_settings[strategy]" value="<?php echo esc_attr( $settings['strategy'] ); ?>" />
					<input type="hidden" name="wbs_settings[enabled]" value="<?php echo $settings['enabled'] ? '1' : ''; ?>" />
					<input type="hidden" name="wbs_settings[safe_mode]" value="<?php echo $settings['safe_mode'] ? '1' : ''; ?>" />
					<input type="hidden" name="wbs_settings[exclude_scripts]" value="<?php echo esc_attr( $settings['exclude_scripts'] ); ?>" />
					<input type="hidden" name="wbs_settings[exclude_styles]" value="<?php echo esc_attr( $settings['exclude_styles'] ); ?>" />
					<input type="hidden" name="wbs_settings[preload_font_urls]" value="<?php echo esc_attr( $settings['preload_font_urls'] ?? '' ); ?>" />

					<div class="wbs-fix-list">
						<?php foreach ( $fixes as $slug => $fix ) : ?>
							<label class="wbs-fix-item wbs-fix-item--<?php echo esc_attr( $fix['risk'] ); ?>">
								<input type="checkbox" name="wbs_settings[enabled_fixes][]" value="<?php echo esc_attr( $slug ); ?>" <?php checked( in_array( $slug, $enabled, true ) ); ?> />
								<span>
									<strong><?php echo esc_html( $fix['title'] ); ?></strong>
									<em>(<?php echo esc_html( self::risk_label( $fix['risk'] ) ); ?>)</em>
									<br /><small><?php echo esc_html( $fix['description'] ); ?></small>
								</span>
							</label>
						<?php endforeach; ?>
					</div>

					<table class="form-table" role="presentation">
						<tr>
							<th><label for="wbs_exclude_scripts"><?php esc_html_e( 'استثنا اسکریپت', 'webakery-speed' ); ?></label></th>
							<td><textarea id="wbs_exclude_scripts" name="wbs_settings[exclude_scripts]" rows="2" class="large-text" dir="ltr"><?php echo esc_textarea( $settings['exclude_scripts'] ); ?></textarea></td>
						</tr>
						<tr>
							<th><label for="wbs_exclude_styles"><?php esc_html_e( 'استثنا CSS', 'webakery-speed' ); ?></label></th>
							<td><textarea id="wbs_exclude_styles" name="wbs_settings[exclude_styles]" rows="2" class="large-text" dir="ltr"><?php echo esc_textarea( $settings['exclude_styles'] ); ?></textarea></td>
						</tr>
						<tr>
							<th><label for="wbs_preload_font_urls"><?php esc_html_e( 'آدرس فونت برای Preload', 'webakery-speed' ); ?></label></th>
							<td>
								<textarea id="wbs_preload_font_urls" name="wbs_settings[preload_font_urls]" rows="4" class="large-text code" dir="ltr" placeholder="https://example.com/fonts/main.woff2&#10;https://fonts.googleapis.com/css2?family=Vazirmatn"><?php echo esc_textarea( $settings['preload_font_urls'] ?? '' ); ?></textarea>
								<p class="description"><?php esc_html_e( 'هر خط یک URL فونت (woff2/woff) یا CSS فونت Google. فونت‌های Google در صف هم به‌صورت خودکار preload می‌شوند.', 'webakery-speed' ); ?></p>
							</td>
						</tr>
					</table>

					<p class="submit">
						<button type="submit" name="wbs_save_settings" class="button button-primary"><?php esc_html_e( 'ذخیره اصلاحات انتخاب‌شده', 'webakery-speed' ); ?></button>
					</p>
				</form>
			</section>

			<p class="wbs-feedback" id="wbs-feedback" hidden></p>
		</div>
		<?php
	}

	/**
	 * Score CSS class.
	 *
	 * @param int|null $score Score.
	 * @return string
	 */
	private static function score_class( $score ) {
		if ( null === $score ) {
			return 'na';
		}
		if ( $score >= 90 ) {
			return 'good';
		}
		if ( $score >= 50 ) {
			return 'avg';
		}
		return 'bad';
	}

	/**
	 * Risk label.
	 *
	 * @param string $risk Risk key.
	 * @return string
	 */
	private static function risk_label( $risk ) {
		$labels = array(
			'low'    => __( 'ریسک کم', 'webakery-speed' ),
			'medium' => __( 'ریسک متوسط', 'webakery-speed' ),
			'high'   => __( 'ریسک بالا', 'webakery-speed' ),
		);
		return $labels[ $risk ] ?? $risk;
	}
}
