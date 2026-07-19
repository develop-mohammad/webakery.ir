<?php
defined( 'ABSPATH' ) || exit;

/**
 * وضعیت زنده اعمال افزونه روی صفحه اصلی.
 */
class WBS_Health {

	const OPTION = 'wbs_health_last';

	/** @var self|null */
	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_post_wbs_run_health', array( $this, 'run_action' ) );
	}

	public function run_action() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Forbidden' );
		}
		check_admin_referer( 'wbs_run_health' );
		update_option( self::OPTION, self::probe( home_url( '/' ) ), false );
		wp_safe_redirect( admin_url( 'admin.php?page=webakery-speed&health=1' ) );
		exit;
	}

	/**
	 * @param string $url
	 * @return array
	 */
	public static function probe( $url ) {
		$resp = wp_remote_get(
			add_query_arg( 'wbs_health', time(), $url ),
			array(
				'timeout' => 25,
				'headers' => array(
					'Accept'     => 'text/html',
					'User-Agent' => 'Mozilla/5.0 WebakerySpeedHealth/' . WBS_VERSION,
				),
			)
		);
		$out = array(
			'checked_at' => time(),
			'url'        => $url,
			'ok'         => false,
			'checks'     => array(),
			'errors'     => array(),
		);
		if ( is_wp_error( $resp ) ) {
			$out['errors'][] = $resp->get_error_message();
			return $out;
		}
		$html = (string) wp_remote_retrieve_body( $resp );
		$code = (int) wp_remote_retrieve_response_code( $resp );
		if ( $code >= 400 || strlen( $html ) < 100 ) {
			$out['errors'][] = 'خواندن صفحه ناموفق بود (HTTP ' . $code . ').';
			return $out;
		}

		$checks = array(
			'WBS_APPLIED'       => false !== stripos( $html, 'WBS_APPLIED=1' ),
			'WBS_FORCE_MODE'    => false !== stripos( $html, 'WBS_FORCE_MODE=1' ),
			'WBS_AUTOFIX'       => false !== stripos( $html, 'WBS_AUTOFIX=1' ),
			'wbfs-force-iransans'=> false !== stripos( $html, 'wbfs-force-iransans' ),
			'fetchpriority'     => false !== stripos( $html, 'fetchpriority' ),
			'icon_preload_gone' => ! preg_match( '#rel=[\'"]preload[\'"][^>]+(?:fa-|fontawesome|WooCommerce\.woff|tinvwl)#i', $html ),
			'iransans_missing'  => false !== stripos( $html, 'WBS_IRANSANS_MISSING=1' ),
		);

		// وضعیت فایل فونت روی دیسک.
		$faces = WBS_Fonts::instance()->resolve_iransans_public();
		$checks['iransans_file_on_disk'] = ! empty( $faces );

		// HTTP فونت از CSS استفاده‌شده.
		$font_404 = false;
		if ( preg_match( '#https?://[^"\']+IRANSansX[^"\']+\.woff2#i', $html, $fm ) ) {
			$fr = wp_remote_head( $fm[0], array( 'timeout' => 10 ) );
			$fc = is_wp_error( $fr ) ? 0 : (int) wp_remote_retrieve_response_code( $fr );
			$checks['iransans_http_ok'] = ( 200 === $fc );
			if ( 200 !== $fc ) {
				$font_404 = true;
				$out['errors'][] = 'فایل IRANSansX در URL عمومی در دسترس نیست (HTTP ' . $fc . '): ' . $fm[0];
			}
		} elseif ( preg_match( '#med-persian/assets/fonts/[^"\']+IRANSansX[^"\']+\.woff2#i', $html, $fm ) ) {
			$font_url = home_url( '/' . ltrim( $fm[0], '/' ) );
			$fr = wp_remote_head( $font_url, array( 'timeout' => 10 ) );
			$fc = is_wp_error( $fr ) ? 0 : (int) wp_remote_retrieve_response_code( $fr );
			$checks['iransans_http_ok'] = ( 200 === $fc );
			if ( 200 !== $fc ) {
				$font_404 = true;
				$out['errors'][] = 'مسیر med-persian برای IRANSansX روی سرور 404 است. افزونه فونت را ترمیم کن.';
			}
		} else {
			$checks['iransans_http_ok'] = false;
			if ( empty( $faces ) ) {
				$font_404 = true;
				$out['errors'][] = 'هیچ URL معتبری برای IRANSansX در HTML پیدا نشد و فایل روی دیسک هم نیست.';
			}
		}

		$out['checks'] = $checks;
		$out['ok']     = ! empty( $checks['WBS_APPLIED'] ) && ! empty( $checks['WBS_FORCE_MODE'] ) && ! $font_404;
		if ( $font_404 ) {
			$out['errors'][] = 'تا وقتی فایل فونت 404 است، PageSpeed برای فونت بهتر نمی‌شود — حتی اگر مارکر افزونه دیده شود.';
		}
		return $out;
	}

	/**
	 * @param array|null $health
	 */
	public static function render_box( $health = null ) {
		if ( null === $health ) {
			$health = (array) get_option( self::OPTION, array() );
		}
		?>
		<section class="wbsb-card">
			<h2>وضعیت اعمال روی سایت</h2>
			<p class="wbsb-hint">اگر چیزی «اعمال نشد»، اینجا دقیق می‌گوید کدام بخش روی HTML زنده هست و کجا فایل فونت 404 است.</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-bottom:12px">
				<?php wp_nonce_field( 'wbs_run_health' ); ?>
				<input type="hidden" name="action" value="wbs_run_health" />
				<button class="button button-primary">بررسی زنده صفحه اصلی</button>
			</form>
			<?php if ( empty( $health['checked_at'] ) ) : ?>
				<p class="wbsb-hint">هنوز بررسی نشده.</p>
			<?php else : ?>
				<small>آخرین بررسی: <?php echo esc_html( wp_date( 'Y/m/d H:i', (int) $health['checked_at'] ) ); ?></small>
				<ul class="wbsb-todo">
					<?php foreach ( (array) ( $health['checks'] ?? array() ) as $key => $val ) : ?>
						<li><?php echo $val ? '✅' : '❌'; ?> <?php echo esc_html( (string) $key ); ?></li>
					<?php endforeach; ?>
				</ul>
				<?php if ( ! empty( $health['errors'] ) ) : ?>
					<ul class="wbsb-issues">
						<?php foreach ( (array) $health['errors'] as $err ) : ?>
							<li><?php echo esc_html( $err ); ?></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			<?php endif; ?>
		</section>
		<?php
	}
}
