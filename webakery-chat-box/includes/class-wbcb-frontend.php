<?php
defined( 'ABSPATH' ) || exit;

class WBCB_Frontend {

	/** @var self|null */
	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'wp_footer', array( $this, 'render_widget' ), 50 );
	}

	public function assets() {
		if ( ! WBCB_Settings::should_show_widget() ) {
			return;
		}
		wp_enqueue_style( 'wbcb-widget', WBCB_URL . 'assets/css/widget.css', array(), WBCB_VERSION );
		wp_enqueue_script( 'wbcb-widget', WBCB_URL . 'assets/js/widget.js', array(), WBCB_VERSION, true );

		$s = WBCB_Settings::get();
		wp_localize_script(
			'wbcb-widget',
			'WBCB',
			array(
				'ajax'    => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'wbcb_visitor' ),
				'online'  => WBCB_Settings::is_online(),
				'settings' => array(
					'title'       => $s['title'],
					'subtitle'    => $s['subtitle'],
					'placeholder' => $s['placeholder'],
					'askName'     => ! empty( $s['ask_name'] ),
					'askEmail'    => ! empty( $s['ask_email'] ),
					'primary'     => $s['primary'],
					'position'    => $s['position'],
					'offlineNote' => $s['offline_note'],
					'whatsapp'    => $s['whatsapp'],
					'telegram'    => $s['telegram'],
				),
				'i18n'    => array(
					'send'     => 'ارسال',
					'close'    => 'بستن',
					'typing'   => 'در حال نوشتن…',
					'error'    => 'خطا — دوباره تلاش کنید',
					'name'     => 'نام شما',
					'email'    => 'ایمیل (اختیاری)',
					'start'    => 'شروع گفتگو',
				),
			)
		);
	}

	public function render_widget() {
		if ( ! WBCB_Settings::should_show_widget() ) {
			return;
		}
		$s    = WBCB_Settings::get();
		$pos  = ( 'right' === $s['position'] ) ? 'is-right' : 'is-left';
		$prim = esc_attr( $s['primary'] );
		?>
		<div id="wbcb-root" class="wbcb-root <?php echo esc_attr( $pos ); ?>" style="--wbcb-primary:<?php echo $prim; ?>;" dir="rtl" lang="fa">
			<button type="button" class="wbcb-launcher" id="wbcb-launcher" aria-expanded="false" aria-controls="wbcb-panel">
				<span class="wbcb-launcher-icon" aria-hidden="true">💬</span>
				<span class="wbcb-launcher-label"><?php echo esc_html( $s['title'] ); ?></span>
			</button>
			<div class="wbcb-panel" id="wbcb-panel" hidden>
				<header class="wbcb-head">
					<div>
						<strong class="wbcb-head-title"><?php echo esc_html( $s['title'] ); ?></strong>
						<span class="wbcb-head-sub"><?php echo esc_html( $s['subtitle'] ); ?></span>
					</div>
					<span class="wbcb-status-dot" data-online="<?php echo WBCB_Settings::is_online() ? '1' : '0'; ?>"></span>
					<button type="button" class="wbcb-icon-btn" id="wbcb-close" aria-label="بستن">×</button>
				</header>
				<div class="wbcb-intro" id="wbcb-intro" hidden>
					<label class="wbcb-field">
						<span><?php esc_html_e( 'نام', 'webakery-chat-box' ); ?></span>
						<input type="text" id="wbcb-name" autocomplete="name" />
					</label>
					<label class="wbcb-field" id="wbcb-email-wrap" hidden>
						<span><?php esc_html_e( 'ایمیل', 'webakery-chat-box' ); ?></span>
						<input type="email" id="wbcb-email" autocomplete="email" />
					</label>
					<button type="button" class="wbcb-btn-primary" id="wbcb-start"><?php esc_html_e( 'شروع گفتگو', 'webakery-chat-box' ); ?></button>
				</div>
				<div class="wbcb-messages" id="wbcb-messages" role="log" aria-live="polite"></div>
				<footer class="wbcb-foot">
					<form id="wbcb-form" class="wbcb-form">
						<textarea id="wbcb-input" rows="1" placeholder="<?php echo esc_attr( $s['placeholder'] ); ?>"></textarea>
						<button type="submit" class="wbcb-send" id="wbcb-send" aria-label="ارسال">➤</button>
					</form>
					<div class="wbcb-links" id="wbcb-links"></div>
				</footer>
			</div>
		</div>
		<?php
	}
}
