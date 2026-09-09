<?php
defined( 'ABSPATH' ) || exit;

class WBCN_Admin {

	/** @var self|null */
	private static $instance = null;

	const CAP = 'manage_options';

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'admin_post_wbcn_save_settings', array( $this, 'handle_save' ) );
		add_action( 'admin_post_wbcn_test', array( $this, 'handle_test' ) );
		add_action( 'admin_post_wbcn_send_idea', array( $this, 'handle_send_idea' ) );
		add_action( 'admin_post_wbcn_send_week', array( $this, 'handle_send_week' ) );
		add_action( 'admin_post_wbcn_webhook', array( $this, 'handle_webhook' ) );
		add_action( 'wp_ajax_wbcn_preview', array( $this, 'ajax_preview' ) );
		add_action( 'add_meta_boxes', array( $this, 'metabox' ) );
		add_action( 'save_post', array( $this, 'save_metabox' ), 40, 2 );
	}

	public function menu() {
		add_menu_page(
			'کانال‌یار',
			'کانال‌یار',
			self::CAP,
			WBCN_MENU,
			array( $this, 'render' ),
			'dashicons-megaphone',
			58
		);
	}

	public function assets( $hook ) {
		if ( 'toplevel_page_' . WBCN_MENU !== $hook ) {
			return;
		}
		wp_enqueue_style( 'wbcn-admin', WBCN_URL . 'assets/admin.css', array(), WBCN_VERSION );
		wp_enqueue_script( 'wbcn-admin', WBCN_URL . 'assets/admin.js', array(), WBCN_VERSION, true );
		wp_localize_script(
			'wbcn-admin',
			'wbcnAdmin',
			array(
				'ajax'  => admin_url( 'admin-ajax.php' ),
				'nonce' => wp_create_nonce( 'wbcn_preview' ),
			)
		);
	}

	public static function tabs() {
		return array(
			'ideas'    => 'ایده از محتوا',
			'growth'   => 'تقویم رشد',
			'settings' => 'ربات و کانال',
			'license'  => 'لایسنس',
		);
	}

	public function render() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( 'دسترسی غیرمجاز' );
		}
		$tabs = self::tabs();
		$tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'ideas';
		if ( ! isset( $tabs[ $tab ] ) ) {
			$tab = 'ideas';
		}
		include WBCN_PATH . 'includes/views/layout.php';
	}

	public static function notice() {
		if ( empty( $_GET['wbcn_msg'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		$ok  = ! empty( $_GET['wbcn_ok'] ); // phpcs:ignore
		$msg = sanitize_text_field( wp_unslash( $_GET['wbcn_msg'] ) ); // phpcs:ignore
		echo '<div class="notice ' . ( $ok ? 'notice-success' : 'notice-error' ) . ' is-dismissible"><p>' . esc_html( $msg ) . '</p></div>';
	}

	private function guard( $action ) {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( 'دسترسی غیرمجاز' );
		}
		check_admin_referer( $action );
	}

	private function redirect( array $args, $msg, $ok = true ) {
		$args['page']     = WBCN_MENU;
		$args['wbcn_msg'] = $msg;
		$args['wbcn_ok']  = $ok ? '1' : '0';
		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}

	public function handle_save() {
		$this->guard( 'wbcn_save_settings' );
		$s = WBCN_Settings::sanitize( wp_unslash( $_POST ) );
		update_option( WBCN_Settings::OPTION, $s, false );
		WBCN_Cron::sync();
		$this->redirect( array( 'tab' => 'settings' ), 'تنظیمات ذخیره شد.', true );
	}

	public function handle_test() {
		$this->guard( 'wbcn_test' );
		$s   = WBCN_Settings::get();
		$me  = WBCN_Telegram::get_me( $s );
		if ( is_wp_error( $me ) ) {
			$this->redirect( array( 'tab' => 'settings' ), $me->get_error_message(), false );
		}
		$text = "✅ تست کانال‌یار\nربات: @" . WBCN_Ideas::escape( $me['username'] ) . "\nسایت: " . home_url( '/' );
		$res  = WBCN_Telegram::send_text( $text, '', array(), $s );
		if ( is_wp_error( $res ) ) {
			$this->redirect( array( 'tab' => 'settings' ), 'ربات وصل شد (@' . $me['username'] . ') ولی ارسال به کانال نشد: ' . $res->get_error_message() . ' — ربات را ادمین کانال کنید.', false );
		}
		$this->redirect( array( 'tab' => 'settings' ), 'پیام تست به کانال ارسال شد. ربات: @' . $me['username'], true );
	}

	public function handle_send_idea() {
		$this->guard( 'wbcn_send_idea' );
		if ( ! WBCN_Plugin::licensed() ) {
			$this->redirect( array( 'tab' => 'ideas' ), 'برای ارسال، لایسنس یا دوره آزمایشی لازم است.', false );
		}
		$post_id = (int) ( $_POST['post_id'] ?? 0 );
		$format  = sanitize_key( wp_unslash( $_POST['format'] ?? 'tip' ) );
		$custom  = isset( $_POST['text'] ) ? wp_unslash( $_POST['text'] ) : '';
		$s       = WBCN_Settings::get();
		$chan    = ! empty( $s['signature'] ) ? WBCN_Settings::channel_username( $s ) : '';

		if ( $custom !== '' ) {
			$parts = preg_split( '/\n—{4,}\n/u', $custom ) ?: array( $custom );
			$res   = WBCN_Telegram::send_parts( $parts, '', $s );
		} else {
			$item = $post_id ? WBCN_Content::item_from_post( $post_id ) : null;
			if ( ! $item ) {
				$this->redirect( array( 'tab' => 'ideas' ), 'مطلب پیدا نشد.', false );
			}
			$built = WBCN_Ideas::build( $item, $format, $chan );
			$res   = WBCN_Telegram::send_parts( $built['parts'], '', $s );
		}
		if ( is_wp_error( $res ) ) {
			$this->redirect( array( 'tab' => 'ideas', 'post_id' => $post_id ), $res->get_error_message(), false );
		}
		if ( $post_id ) {
			update_post_meta( $post_id, '_wbcn_sent', time() );
		}
		$this->redirect( array( 'tab' => 'ideas', 'post_id' => $post_id ), 'به کانال ارسال شد.', true );
	}

	public function handle_send_week() {
		$this->guard( 'wbcn_send_week' );
		if ( ! WBCN_Plugin::licensed() ) {
			$this->redirect( array( 'tab' => 'growth' ), 'لایسنس فعال نیست.', false );
		}
		$day = sanitize_key( wp_unslash( $_POST['day_index'] ?? '0' ) );
		$idx = (int) $day;
		$s    = WBCN_Settings::get();
		$chan = ! empty( $s['signature'] ) ? WBCN_Settings::channel_username( $s ) : '';
		$plan = WBCN_Ideas::week_plan( WBCN_Content::recent_items( 7 ), $chan );
		if ( ! isset( $plan[ $idx ] ) ) {
			$this->redirect( array( 'tab' => 'growth' ), 'روز نامعتبر است.', false );
		}
		$res = WBCN_Telegram::send_text( $plan[ $idx ]['text'], '', array(), $s );
		if ( is_wp_error( $res ) ) {
			$this->redirect( array( 'tab' => 'growth' ), $res->get_error_message(), false );
		}
		$this->redirect( array( 'tab' => 'growth' ), 'پست «' . $plan[ $idx ]['day'] . '» ارسال شد.', true );
	}

	public function handle_webhook() {
		$this->guard( 'wbcn_webhook' );
		$s   = WBCN_Settings::get();
		$act = sanitize_key( wp_unslash( $_POST['webhook_act'] ?? 'set' ) );
		if ( 'delete' === $act ) {
			$res = WBCN_Telegram::delete_webhook( $s );
			$msg = is_wp_error( $res ) ? $res->get_error_message() : 'وب‌هوک حذف شد. اگر هاست ایران است، polling را روشن کنید.';
			$this->redirect( array( 'tab' => 'settings' ), $msg, ! is_wp_error( $res ) );
		}
		$res = WBCN_Telegram::set_webhook( WBCN_Settings::webhook_url( $s ), $s );
		$msg = is_wp_error( $res ) ? $res->get_error_message() : 'وب‌هوک ثبت شد.';
		$this->redirect( array( 'tab' => 'settings' ), $msg, ! is_wp_error( $res ) );
	}

	public function ajax_preview() {
		check_ajax_referer( 'wbcn_preview', 'nonce' );
		if ( ! current_user_can( self::CAP ) ) {
			wp_send_json_error( array( 'message' => 'دسترسی غیرمجاز' ) );
		}
		$post_id = (int) ( $_POST['post_id'] ?? 0 );
		$format  = sanitize_key( wp_unslash( $_POST['format'] ?? 'tip' ) );
		$s       = WBCN_Settings::get();
		$chan    = ! empty( $s['signature'] ) ? WBCN_Settings::channel_username( $s ) : '';
		$item    = $post_id ? WBCN_Content::item_from_post( $post_id ) : WBCN_Ideas::sample_item();
		if ( ! $item ) {
			wp_send_json_error( array( 'message' => 'مطلب پیدا نشد' ) );
		}
		$built = WBCN_Ideas::build( $item, $format, $chan );
		wp_send_json_success( $built );
	}

	public function metabox() {
		$s = WBCN_Settings::get();
		foreach ( (array) $s['post_types'] as $type ) {
			add_meta_box(
				'wbcn_box',
				'کانال‌یار — تلگرام',
				array( $this, 'render_metabox' ),
				$type,
				'side',
				'default'
			);
		}
	}

	public function render_metabox( $post ) {
		wp_nonce_field( 'wbcn_metabox', 'wbcn_metabox_nonce' );
		$sent = (int) get_post_meta( $post->ID, '_wbcn_sent', true );
		?>
		<p>
			<label>
				<input type="checkbox" name="wbcn_send_now" value="1" />
				همین الان به کانال بفرست (بعد از ذخیره)
			</label>
		</p>
		<?php if ( $sent ) : ?>
			<p class="description">قبلاً <?php echo esc_html( date_i18n( 'Y/m/d H:i', $sent ) ); ?> ارسال شده.</p>
		<?php else : ?>
			<p class="description">با انتشار، در صورت روشن بودن ارسال خودکار می‌رود.</p>
		<?php endif; ?>
		<?php
	}

	public function save_metabox( $post_id, $post ) {
		if ( ! isset( $_POST['wbcn_metabox_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wbcn_metabox_nonce'] ) ), 'wbcn_metabox' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		if ( empty( $_POST['wbcn_send_now'] ) || ! WBCN_Plugin::licensed() ) {
			return;
		}
		$s    = WBCN_Settings::get();
		$item = WBCN_Content::item_from_post( $post_id );
		if ( ! $item ) {
			return;
		}
		$chan = ! empty( $s['signature'] ) ? WBCN_Settings::channel_username( $s ) : '';
		$text = WBCN_Ideas::publish_post( $item, $chan, true );
		$res  = WBCN_Telegram::send_text( $text, '', array(), $s );
		if ( ! is_wp_error( $res ) ) {
			update_post_meta( $post_id, '_wbcn_sent', time() );
		}
	}
}
