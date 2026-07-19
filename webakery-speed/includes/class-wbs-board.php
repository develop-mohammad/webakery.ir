<?php
defined( 'ABSPATH' ) || exit;

class WBS_Board {

	const OPTION_GSC  = 'wbsb_gsc';
	const OPTION_SCAN = 'wbsb_last_scan';
	const OPTION_DONE = 'wbsb_done_steps';

	/** @var self|null */
	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public static function activate() {
		if ( false === get_option( self::OPTION_GSC, false ) ) {
			add_option(
				self::OPTION_GSC,
				array(
					'gsc_url' => '',
					'psi_url' => '',
					'lcp'     => '',
					'inp'     => '',
					'cls'     => '',
					'note'    => '',
				),
				'',
				false
			);
		}
		if ( false === get_option( self::OPTION_DONE, false ) ) {
			add_option( self::OPTION_DONE, array(), '', false );
		}
	}

	private function __construct() {
		add_action( 'admin_menu', array( $this, 'menu' ), 9 );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'admin_post_wbsb_save_gsc', array( $this, 'save_gsc' ) );
		add_action( 'admin_post_wbsb_run_scan', array( $this, 'run_scan' ) );
		add_action( 'admin_post_wbsb_toggle_done', array( $this, 'toggle_done' ) );
	}

	public function menu() {
		add_menu_page(
			'پنل سرعت',
			'پنل سرعت',
			'manage_options',
			'webakery-speed',
			array( $this, 'render' ),
			'dashicons-performance',
			58
		);
		add_submenu_page(
			'webakery-speed',
			'اولویت‌ها',
			'اولویت‌ها',
			'manage_options',
			'webakery-speed',
			array( $this, 'render' )
		);
	}

	public function assets( $hook ) {
		if ( 'toplevel_page_webakery-speed' !== $hook ) {
			return;
		}
		wp_enqueue_style( 'wbs-board-admin', WBS_URL . 'assets/board-admin.css', array(), WBS_VERSION );
	}

	public function save_gsc() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Forbidden' );
		}
		check_admin_referer( 'wbsb_save_gsc' );
		update_option(
			self::OPTION_GSC,
			array(
				'gsc_url' => esc_url_raw( wp_unslash( $_POST['gsc_url'] ?? '' ) ),
				'psi_url' => esc_url_raw( wp_unslash( $_POST['psi_url'] ?? '' ) ),
				'lcp'     => sanitize_text_field( wp_unslash( $_POST['lcp'] ?? '' ) ),
				'inp'     => sanitize_text_field( wp_unslash( $_POST['inp'] ?? '' ) ),
				'cls'     => sanitize_text_field( wp_unslash( $_POST['cls'] ?? '' ) ),
				'note'    => sanitize_textarea_field( wp_unslash( $_POST['note'] ?? '' ) ),
			),
			false
		);
		wp_safe_redirect( admin_url( 'admin.php?page=webakery-speed&saved=1' ) );
		exit;
	}

	public function run_scan() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Forbidden' );
		}
		check_admin_referer( 'wbsb_run_scan' );
		$result = WBS_Scanner::scan( home_url( '/' ) );
		if ( is_wp_error( $result ) ) {
			update_option( self::OPTION_SCAN, array( 'error' => $result->get_error_message(), 'scanned_at' => time() ), false );
		} else {
			update_option( self::OPTION_SCAN, $result, false );
		}
		wp_safe_redirect( admin_url( 'admin.php?page=webakery-speed&scanned=1' ) );
		exit;
	}

	public function toggle_done() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Forbidden' );
		}
		check_admin_referer( 'wbsb_toggle_done' );
		$id   = sanitize_key( wp_unslash( $_POST['step_id'] ?? '' ) );
		$done = (array) get_option( self::OPTION_DONE, array() );
		if ( $id ) {
			if ( ! empty( $done[ $id ] ) ) {
				unset( $done[ $id ] );
			} else {
				$done[ $id ] = time();
			}
			update_option( self::OPTION_DONE, $done, false );
		}
		wp_safe_redirect( admin_url( 'admin.php?page=webakery-speed' ) );
		exit;
	}

	private function status_label( $status ) {
		$map = array(
			'ok'     => array( 'خوب', 'ok' ),
			'warn'   => array( 'نیاز به کار', 'warn' ),
			'bad'    => array( 'اولویت بالا', 'bad' ),
			'todo'   => array( 'شروع کن', 'todo' ),
			'manual' => array( 'دستی', 'manual' ),
		);
		return $map[ $status ] ?? array( $status, 'todo' );
	}

	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$gsc  = wp_parse_args(
			(array) get_option( self::OPTION_GSC, array() ),
			array(
				'gsc_url' => '',
				'psi_url' => '',
				'lcp'     => '',
				'inp'     => '',
				'cls'     => '',
				'note'    => '',
			)
		);
		$scan = (array) get_option( self::OPTION_SCAN, array() );
		$done = (array) get_option( self::OPTION_DONE, array() );
		$order = array( 'search_console', 'render_blocking', 'image_dimensions', 'image_format', 'image_weight', 'fonts', 'lcp_images', 'forced_reflow', 'network_tree' );

		// اگر اسکن نداریم، حداقل استپ سرچ‌کنسول را بساز.
		if ( empty( $scan['steps'] ) ) {
			$scan['steps'] = array(
				'search_console' => array(
					'id'      => 'search_console',
					'title'   => '۱) سرچ‌کنسول / Core Web Vitals',
					'status'  => 'todo',
					'summary' => 'اول داده سرچ‌کنسول را وارد کن، بعد اسکن صفحه را بزن.',
					'issues'  => array(),
					'actions' => array( 'فرم بالا را پر کن', 'دکمه اسکن صفحه را بزن' ),
				),
			);
		} else {
			$scan['steps']['search_console'] = $this->fresh_gsc_step( $gsc );
		}

		$next = null;
		foreach ( $order as $id ) {
			if ( empty( $scan['steps'][ $id ] ) ) {
				continue;
			}
			$st = $scan['steps'][ $id ]['status'];
			if ( ! empty( $done[ $id ] ) ) {
				continue;
			}
			if ( in_array( $st, array( 'todo', 'warn', 'bad', 'manual' ), true ) ) {
				$next = $id;
				break;
			}
		}
		?>
		<div class="wrap wbsb-wrap">
			<div class="wbsb-hero">
				<div>
					<h1>پنل سرعت</h1>
					<p>CWV را از گوگل بگیر، اسکن کن، بعد اصلاح خودکار را برای موارد امن روشن کن.</p>
				</div>
				<span class="wbsb-badge">v<?php echo esc_html( WBS_VERSION ); ?> · AutoFix · CWV</span>
			</div>
			<p class="wbsb-navline">
				<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=webakery-speed' ) ); ?>">اولویت‌ها</a>
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=webakery-speed-cwv' ) ); ?>">گوگل / CWV</a>
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=webakery-speed-autofix' ) ); ?>">اصلاح خودکار</a>
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=webakery-speed-fonts' ) ); ?>">فونت سوییپ</a>
			</p>

			<?php if ( ! empty( $_GET['saved'] ) || ! empty( $_GET['scanned'] ) ) : // phpcs:ignore ?>
				<div class="wbsb-flash">ذخیره / اسکن انجام شد.</div>
			<?php endif; ?>

			<?php if ( ! empty( $scan['error'] ) ) : ?>
				<div class="wbsb-flash bad"><?php echo esc_html( $scan['error'] ); ?></div>
			<?php endif; ?>

			<section class="wbsb-card">
				<h2>شروع با سرچ‌کنسول / PageSpeed</h2>
				<p class="wbsb-hint" style="margin:0 0 12px">می‌توانی دستی وارد کنی، یا از منوی <a href="<?php echo esc_url( admin_url( 'admin.php?page=webakery-speed-cwv' ) ); ?>">گوگل / CWV</a> به‌صورت خودکار بگیری.</p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-bottom:14px">
					<?php wp_nonce_field( 'wbs_fetch_cwv' ); ?>
					<input type="hidden" name="action" value="wbs_fetch_cwv" />
					<button class="button button-primary">دریافت خودکار CWV از گوگل</button>
					<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=webakery-speed-autofix' ) ); ?>">رفتن به اصلاح خودکار</a>
				</form>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="wbsb-form">
					<?php wp_nonce_field( 'wbsb_save_gsc' ); ?>
					<input type="hidden" name="action" value="wbsb_save_gsc" />
					<div class="wbsb-grid-2">
						<label>لینک Google Search Console
							<input type="url" name="gsc_url" value="<?php echo esc_attr( $gsc['gsc_url'] ); ?>" placeholder="https://search.google.com/search-console/..." />
						</label>
						<label>لینک PageSpeed Insights
							<input type="url" name="psi_url" value="<?php echo esc_attr( $gsc['psi_url'] ); ?>" placeholder="https://pagespeed.web.dev/analysis/..." />
						</label>
						<label>LCP فیلد دیتا (ثانیه)
							<input type="text" name="lcp" value="<?php echo esc_attr( $gsc['lcp'] ); ?>" placeholder="مثلا 6.2" />
						</label>
						<label>INP فیلد دیتا (ms)
							<input type="text" name="inp" value="<?php echo esc_attr( $gsc['inp'] ); ?>" placeholder="مثلا 196" />
						</label>
						<label>CLS فیلد دیتا
							<input type="text" name="cls" value="<?php echo esc_attr( $gsc['cls'] ); ?>" placeholder="مثلا 0.08" />
						</label>
						<label class="wbsb-span-2">یادداشت
							<textarea name="note" rows="2" placeholder="موبایل / دسکتاپ / URL تست / نکات"><?php echo esc_textarea( $gsc['note'] ); ?></textarea>
						</label>
					</div>
					<p class="wbsb-actions">
						<button class="button button-primary">ذخیره گزارش پایه</button>
						<?php if ( $gsc['gsc_url'] ) : ?>
							<a class="button" target="_blank" rel="noopener" href="<?php echo esc_url( $gsc['gsc_url'] ); ?>">باز کردن GSC</a>
						<?php endif; ?>
						<?php if ( $gsc['psi_url'] ) : ?>
							<a class="button" target="_blank" rel="noopener" href="<?php echo esc_url( $gsc['psi_url'] ); ?>">باز کردن PSI</a>
						<?php endif; ?>
					</p>
				</form>
			</section>

			<section class="wbsb-card accent">
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( 'wbsb_run_scan' ); ?>
					<input type="hidden" name="action" value="wbsb_run_scan" />
					<div class="wbsb-scanbar">
						<div>
							<strong>اسکن صفحه اصلی</strong>
							<small>Render-blocking، تصاویر، فونت، LCP و دامنه‌های شخص‌ثالث را از HTML زنده می‌خواند.</small>
							<?php if ( ! empty( $scan['scanned_at'] ) ) : ?>
								<small>آخرین اسکن: <?php echo esc_html( wp_date( 'Y/m/d H:i', (int) $scan['scanned_at'] ) ); ?> · HTML <?php echo esc_html( (string) ( $scan['html_kb'] ?? '?' ) ); ?>KB</small>
							<?php endif; ?>
						</div>
						<button class="button button-hero button-primary">اجرای اسکن</button>
					</div>
				</form>
				<?php if ( $next && ! empty( $scan['steps'][ $next ] ) ) : ?>
					<div class="wbsb-next">
						<span>قدم بعدی:</span>
						<strong><?php echo esc_html( $scan['steps'][ $next ]['title'] ); ?></strong>
					</div>
				<?php endif; ?>
			</section>

			<div class="wbsb-steps">
				<?php
				foreach ( $order as $id ) :
					if ( empty( $scan['steps'][ $id ] ) ) {
						continue;
					}
					$step = $scan['steps'][ $id ];
					list( $label, $cls ) = $this->status_label( $step['status'] );
					$is_done = ! empty( $done[ $id ] );
					$is_next = ( $next === $id );
					?>
					<article class="wbsb-step <?php echo esc_attr( $cls . ( $is_done ? ' done' : '' ) . ( $is_next ? ' next' : '' ) ); ?>">
						<header>
							<div>
								<h3><?php echo esc_html( $step['title'] ); ?></h3>
								<p><?php echo esc_html( $step['summary'] ?? '' ); ?></p>
							</div>
							<div class="wbsb-pill"><?php echo esc_html( $is_done ? 'انجام شد' : $label ); ?></div>
						</header>

						<?php if ( ! empty( $step['issues'] ) ) : ?>
							<ul class="wbsb-issues">
								<?php foreach ( array_slice( (array) $step['issues'], 0, 8 ) as $issue ) : ?>
									<li><?php echo esc_html( $issue ); ?></li>
								<?php endforeach; ?>
							</ul>
						<?php endif; ?>

						<?php if ( ! empty( $step['actions'] ) ) : ?>
							<ol class="wbsb-todo">
								<?php foreach ( (array) $step['actions'] as $action ) : ?>
									<li><?php echo esc_html( $action ); ?></li>
								<?php endforeach; ?>
							</ol>
						<?php endif; ?>

						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="wbsb-step-actions">
							<?php wp_nonce_field( 'wbsb_toggle_done' ); ?>
							<input type="hidden" name="action" value="wbsb_toggle_done" />
							<input type="hidden" name="step_id" value="<?php echo esc_attr( $id ); ?>" />
							<button class="button"><?php echo $is_done ? 'برگرداندن به انجام‌نشده' : 'این مورد انجام شد ✓'; ?></button>
						</form>
					</article>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}

	private function fresh_gsc_step( $gsc ) {
		$issues = array();
		$has = ! empty( $gsc['gsc_url'] ) || ! empty( $gsc['psi_url'] ) || ! empty( $gsc['lcp'] );
		if ( ! $has ) {
			$issues[] = 'لینک سرچ‌کنسول / PageSpeed هنوز ثبت نشده.';
		}
		if ( ! empty( $gsc['lcp'] ) && (float) $gsc['lcp'] > 2.5 ) {
			$issues[] = 'LCP فیلد دیتا ضعیف است (> 2.5s).';
		}
		if ( ! empty( $gsc['inp'] ) && (float) $gsc['inp'] > 200 ) {
			$issues[] = 'INP فیلد دیتا ضعیف است (> 200ms).';
		}
		if ( ! empty( $gsc['cls'] ) && (float) $gsc['cls'] > 0.1 ) {
			$issues[] = 'CLS فیلد دیتا ضعیف است (> 0.1).';
		}
		$status = ! $has ? 'todo' : ( empty( $issues ) ? 'ok' : 'warn' );
		return array(
			'id'      => 'search_console',
			'title'   => '۱) سرچ‌کنسول / Core Web Vitals',
			'status'  => $status,
			'summary' => $has ? 'داده ثبت شده — اولویت را از اینجا شروع کن.' : 'اول گزارش را وارد کن.',
			'issues'  => $issues,
			'actions' => array(
				'مقادیر فیلد دیتا را ذخیره کن',
				'اسکن صفحه را اجرا کن',
				'بعد برو سراغ Render-blocking',
			),
		);
	}
}
