<?php
defined( 'ABSPATH' ) || exit;

/**
 * اتصال به داده Core Web Vitals از طریق PageSpeed Insights API
 * (منبع فیلد دیتا همان Chrome UX Report است که Search Console نشان می‌دهد).
 */
class WBS_CWV {

	const OPTION = 'wbs_cwv_settings';
	const LAST   = 'wbs_cwv_last';

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
			add_option(
				self::OPTION,
				array(
					'api_key'   => '',
					'strategy'  => 'mobile',
					'auto_sync' => 0,
				),
				'',
				false
			);
		}
	}

	public static function settings() {
		return wp_parse_args(
			(array) get_option( self::OPTION, array() ),
			array(
				'api_key'   => '',
				'strategy'  => 'mobile',
				'auto_sync' => 0,
			)
		);
	}

	private function __construct() {
		add_action( 'admin_menu', array( $this, 'menu' ), 21 );
		add_action( 'admin_post_wbs_save_cwv', array( $this, 'save_settings' ) );
		add_action( 'admin_post_wbs_fetch_cwv', array( $this, 'fetch_action' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
	}

	public function menu() {
		add_submenu_page(
			'webakery-speed',
			'Core Web Vitals',
			'گوگل / CWV',
			'manage_options',
			'webakery-speed-cwv',
			array( $this, 'render' )
		);
	}

	public function assets( $hook ) {
		if ( 'webakery-speed_page_webakery-speed-cwv' !== $hook && 'toplevel_page_webakery-speed' !== $hook ) {
			return;
		}
		wp_enqueue_style( 'wbs-board-admin', WBS_URL . 'assets/board-admin.css', array(), WBS_VERSION );
	}

	public function save_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Forbidden' );
		}
		check_admin_referer( 'wbs_save_cwv' );
		$strategy = sanitize_key( wp_unslash( $_POST['strategy'] ?? 'mobile' ) );
		if ( ! in_array( $strategy, array( 'mobile', 'desktop' ), true ) ) {
			$strategy = 'mobile';
		}
		update_option(
			self::OPTION,
			array(
				'api_key'   => sanitize_text_field( wp_unslash( $_POST['api_key'] ?? '' ) ),
				'strategy'  => $strategy,
				'auto_sync' => ! empty( $_POST['auto_sync'] ) ? 1 : 0,
			),
			false
		);
		wp_safe_redirect( admin_url( 'admin.php?page=webakery-speed-cwv&saved=1' ) );
		exit;
	}

	public function fetch_action() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Forbidden' );
		}
		check_admin_referer( 'wbs_fetch_cwv' );
		$result = self::fetch( home_url( '/' ) );
		if ( is_wp_error( $result ) ) {
			update_option( self::LAST, array( 'error' => $result->get_error_message(), 'fetched_at' => time() ), false );
			wp_safe_redirect( admin_url( 'admin.php?page=webakery-speed-cwv&err=1' ) );
			exit;
		}
		update_option( self::LAST, $result, false );
		self::sync_to_board( $result );
		wp_safe_redirect( admin_url( 'admin.php?page=webakery-speed-cwv&fetched=1' ) );
		exit;
	}

	/**
	 * Copy field metrics into the priorities board form.
	 *
	 * @param array $result
	 */
	public static function sync_to_board( array $result ) {
		$gsc = wp_parse_args(
			(array) get_option( WBS_Board::OPTION_GSC, array() ),
			array(
				'gsc_url' => '',
				'psi_url' => '',
				'lcp'     => '',
				'inp'     => '',
				'cls'     => '',
				'note'    => '',
			)
		);
		if ( isset( $result['field']['lcp_s'] ) && '' !== $result['field']['lcp_s'] ) {
			$gsc['lcp'] = (string) $result['field']['lcp_s'];
		}
		if ( isset( $result['field']['inp_ms'] ) && '' !== $result['field']['inp_ms'] ) {
			$gsc['inp'] = (string) $result['field']['inp_ms'];
		}
		if ( isset( $result['field']['cls'] ) && '' !== $result['field']['cls'] ) {
			$gsc['cls'] = (string) $result['field']['cls'];
		}
		if ( ! empty( $result['psi_url'] ) ) {
			$gsc['psi_url'] = $result['psi_url'];
		}
		$note_bits = array();
		if ( ! empty( $result['strategy'] ) ) {
			$note_bits[] = 'strategy=' . $result['strategy'];
		}
		if ( ! empty( $result['field']['overall'] ) ) {
			$note_bits[] = 'field=' . $result['field']['overall'];
		}
		if ( isset( $result['lab']['score'] ) ) {
			$note_bits[] = 'lab_score=' . $result['lab']['score'];
		}
		$gsc['note'] = trim( ( $gsc['note'] ? $gsc['note'] . "\n" : '' ) . 'CWV auto: ' . implode( ' · ', $note_bits ) );
		update_option( WBS_Board::OPTION_GSC, $gsc, false );
	}

	/**
	 * @param string $url
	 * @return array|WP_Error
	 */
	public static function fetch( $url = '' ) {
		$url      = $url ? $url : home_url( '/' );
		$settings = self::settings();
		$strategy = $settings['strategy'] ? $settings['strategy'] : 'mobile';

		$endpoint = add_query_arg(
			array(
				'url'      => $url,
				'strategy' => $strategy,
				'category' => 'performance',
			),
			'https://www.googleapis.com/pagespeedonline/v5/runPagespeed'
		);
		if ( ! empty( $settings['api_key'] ) ) {
			$endpoint = add_query_arg( 'key', $settings['api_key'], $endpoint );
		}

		$resp = wp_remote_get(
			$endpoint,
			array(
				'timeout' => 60,
				'headers' => array( 'Accept' => 'application/json' ),
			)
		);
		if ( is_wp_error( $resp ) ) {
			return $resp;
		}
		$code = (int) wp_remote_retrieve_response_code( $resp );
		$body = (string) wp_remote_retrieve_body( $resp );
		$data = json_decode( $body, true );
		if ( $code >= 400 || ! is_array( $data ) ) {
			$msg = is_array( $data ) && ! empty( $data['error']['message'] ) ? $data['error']['message'] : 'PSI API error HTTP ' . $code;
			return new WP_Error( 'psi_failed', $msg );
		}

		$field_src = array();
		if ( ! empty( $data['loadingExperience']['metrics'] ) ) {
			$field_src = $data['loadingExperience'];
		} elseif ( ! empty( $data['originLoadingExperience']['metrics'] ) ) {
			$field_src = $data['originLoadingExperience'];
		}

		$field = self::parse_field( $field_src );
		$lab   = self::parse_lab( isset( $data['lighthouseResult'] ) ? $data['lighthouseResult'] : array() );

		return array(
			'fetched_at' => time(),
			'url'        => $url,
			'strategy'   => $strategy,
			'psi_url'    => 'https://pagespeed.web.dev/analysis?url=' . rawurlencode( $url ),
			'field'      => $field,
			'lab'        => $lab,
			'opportunities' => self::parse_opportunities( isset( $data['lighthouseResult'] ) ? $data['lighthouseResult'] : array() ),
		);
	}

	/**
	 * @param array $exp
	 * @return array
	 */
	private static function parse_field( $exp ) {
		$m = isset( $exp['metrics'] ) && is_array( $exp['metrics'] ) ? $exp['metrics'] : array();
		$lcp_ms = isset( $m['LARGEST_CONTENTFUL_PAINT_MS']['percentile'] ) ? (float) $m['LARGEST_CONTENTFUL_PAINT_MS']['percentile'] : null;
		$inp_ms = null;
		if ( isset( $m['INTERACTION_TO_NEXT_PAINT']['percentile'] ) ) {
			$inp_ms = (float) $m['INTERACTION_TO_NEXT_PAINT']['percentile'];
		} elseif ( isset( $m['FIRST_INPUT_DELAY_MS']['percentile'] ) ) {
			$inp_ms = (float) $m['FIRST_INPUT_DELAY_MS']['percentile'];
		}
		// PSI CrUX CLS percentile is typically ×100 (e.g. 8 → 0.08).
		$cls = null;
		if ( isset( $m['CUMULATIVE_LAYOUT_SHIFT_SCORE']['percentile'] ) ) {
			$raw = (float) $m['CUMULATIVE_LAYOUT_SHIFT_SCORE']['percentile'];
			$cls = ( $raw > 1 ) ? round( $raw / 100, 3 ) : round( $raw, 3 );
		}

		return array(
			'overall'   => isset( $exp['overall_category'] ) ? (string) $exp['overall_category'] : '',
			'lcp_s'     => null !== $lcp_ms ? (string) round( $lcp_ms / 1000, 2 ) : '',
			'inp_ms'    => null !== $inp_ms ? (string) round( $inp_ms ) : '',
			'cls'       => null !== $cls ? (string) $cls : '',
			'available' => ! empty( $m ),
		);
	}

	/**
	 * @param array $lhr
	 * @return array
	 */
	private static function parse_lab( $lhr ) {
		$audits = isset( $lhr['audits'] ) && is_array( $lhr['audits'] ) ? $lhr['audits'] : array();
		$cats   = isset( $lhr['categories']['performance']['score'] ) ? $lhr['categories']['performance']['score'] : null;
		$get    = static function ( $id ) use ( $audits ) {
			return isset( $audits[ $id ]['numericValue'] ) ? (float) $audits[ $id ]['numericValue'] : null;
		};
		$lcp = $get( 'largest-contentful-paint' );
		$cls = $get( 'cumulative-layout-shift' );
		$tbt = $get( 'total-blocking-time' );
		return array(
			'score'  => null !== $cats ? (int) round( $cats * 100 ) : null,
			'lcp_s'  => null !== $lcp ? round( $lcp / 1000, 2 ) : null,
			'cls'    => null !== $cls ? round( $cls, 3 ) : null,
			'tbt_ms' => null !== $tbt ? (int) round( $tbt ) : null,
		);
	}

	/**
	 * @param array $lhr
	 * @return array
	 */
	private static function parse_opportunities( $lhr ) {
		$audits = isset( $lhr['audits'] ) && is_array( $lhr['audits'] ) ? $lhr['audits'] : array();
		$ids    = array(
			'render-blocking-resources',
			'unused-css-rules',
			'unused-javascript',
			'uses-optimized-images',
			'uses-responsive-images',
			'offscreen-images',
			'font-display',
			'prioritize-lcp-image',
			'unsized-images',
		);
		$out = array();
		foreach ( $ids as $id ) {
			if ( empty( $audits[ $id ] ) || ! isset( $audits[ $id ]['score'] ) ) {
				continue;
			}
			if ( 1 === (int) $audits[ $id ]['score'] ) {
				continue;
			}
			$out[] = array(
				'id'          => $id,
				'title'       => (string) ( $audits[ $id ]['title'] ?? $id ),
				'score'       => $audits[ $id ]['score'],
				'description' => wp_strip_all_tags( (string) ( $audits[ $id ]['description'] ?? '' ) ),
			);
		}
		return $out;
	}

	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$s    = self::settings();
		$last = (array) get_option( self::LAST, array() );
		?>
		<div class="wrap wbsb-wrap">
			<div class="wbsb-hero">
				<div>
					<h1>گوگل · Core Web Vitals</h1>
					<p>داده میدانی (Field) همان منبع Search Console است — از طریق PageSpeed Insights API خوانده می‌شود. اتصال کامل OAuth سرچ‌کنسول نیاز به پروژه Google Cloud دارد؛ این روش سریع‌تر و برای CWV کافی است.</p>
				</div>
				<span class="wbsb-badge">CWV · v<?php echo esc_html( WBS_VERSION ); ?></span>
			</div>
			<p class="wbsb-navline">
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=webakery-speed' ) ); ?>">اولویت‌ها</a>
				<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=webakery-speed-cwv' ) ); ?>">گوگل / CWV</a>
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=webakery-speed-autofix' ) ); ?>">اصلاح خودکار</a>
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=webakery-speed-fonts' ) ); ?>">فونت سوییپ</a>
			</p>

			<?php if ( ! empty( $_GET['saved'] ) || ! empty( $_GET['fetched'] ) ) : // phpcs:ignore ?>
				<div class="wbsb-flash">ذخیره / دریافت انجام شد. مقادیر به پنل اولویت‌ها هم منتقل شد.</div>
			<?php endif; ?>
			<?php if ( ! empty( $_GET['err'] ) && ! empty( $last['error'] ) ) : // phpcs:ignore ?>
				<div class="wbsb-flash bad"><?php echo esc_html( $last['error'] ); ?></div>
			<?php endif; ?>

			<section class="wbsb-card">
				<h2>اتصال API</h2>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="wbsb-form">
					<?php wp_nonce_field( 'wbs_save_cwv' ); ?>
					<input type="hidden" name="action" value="wbs_save_cwv" />
					<div class="wbsb-grid-2">
						<label class="wbsb-span-2">کلید PageSpeed Insights API (اختیاری ولی توصیه‌شده)
							<input type="text" name="api_key" value="<?php echo esc_attr( $s['api_key'] ); ?>" placeholder="AIza..." autocomplete="off" />
							<small class="wbsb-hint">از Google Cloud Console → APIs → PageSpeed Insights API بساز. بدون کلید هم کار می‌کند ولی محدودیت نرخ دارد.</small>
						</label>
						<label>استراتژی
							<select name="strategy">
								<option value="mobile" <?php selected( $s['strategy'], 'mobile' ); ?>>موبایل</option>
								<option value="desktop" <?php selected( $s['strategy'], 'desktop' ); ?>>دسکتاپ</option>
							</select>
						</label>
					</div>
					<p class="wbsb-actions">
						<button class="button button-primary">ذخیره تنظیمات</button>
					</p>
				</form>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:12px">
					<?php wp_nonce_field( 'wbs_fetch_cwv' ); ?>
					<input type="hidden" name="action" value="wbs_fetch_cwv" />
					<button class="button button-hero button-primary">دریافت خودکار CWV از گوگل</button>
				</form>
			</section>

			<?php if ( ! empty( $last['fetched_at'] ) ) : ?>
				<section class="wbsb-card accent">
					<h2>آخرین نتیجه</h2>
					<small>زمان: <?php echo esc_html( wp_date( 'Y/m/d H:i', (int) $last['fetched_at'] ) ); ?> · <?php echo esc_html( (string) ( $last['strategy'] ?? '' ) ); ?></small>
					<?php if ( ! empty( $last['field']['available'] ) ) : ?>
						<div class="wbsb-metrics">
							<div><strong>LCP</strong><span><?php echo esc_html( $last['field']['lcp_s'] ); ?>s</span></div>
							<div><strong>INP</strong><span><?php echo esc_html( $last['field']['inp_ms'] ); ?>ms</span></div>
							<div><strong>CLS</strong><span><?php echo esc_html( $last['field']['cls'] ); ?></span></div>
							<div><strong>Field</strong><span><?php echo esc_html( $last['field']['overall'] ); ?></span></div>
							<?php if ( isset( $last['lab']['score'] ) ) : ?>
								<div><strong>Lab Score</strong><span><?php echo esc_html( (string) $last['lab']['score'] ); ?></span></div>
							<?php endif; ?>
						</div>
					<?php else : ?>
						<p>داده میدانی (CrUX) برای این URL هنوز کافی نیست — معمولاً برای سایت‌های کم‌ترافیک. Lab Score و فرصت‌ها هنوز قابل استفاده‌اند.</p>
					<?php endif; ?>

					<?php if ( ! empty( $last['opportunities'] ) ) : ?>
						<ul class="wbsb-issues">
							<?php foreach ( array_slice( $last['opportunities'], 0, 8 ) as $op ) : ?>
								<li><strong><?php echo esc_html( $op['title'] ); ?></strong> — <?php echo esc_html( wp_html_excerpt( $op['description'], 140, '…' ) ); ?></li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</section>
			<?php endif; ?>

			<section class="wbsb-card">
				<h2>Search Console کامل؟</h2>
				<p>برای خواندن مستقیم گزارش «Core Web Vitals» داخل Search Console باید OAuth با Google Cloud (Search Console API) راه‌اندازی شود. فعلاً با PSI/CrUX همان اعداد فیلد دیتا را می‌گیری و در پنل اولویت‌ها ذخیره می‌شود. لینک دستی GSC را هم می‌توانی در اولویت‌ها نگه داری.</p>
			</section>
		</div>
		<?php
	}
}
