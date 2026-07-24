<?php
defined( 'ABSPATH' ) || exit;

class WBCB_Admin {

	/** @var self|null */
	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'admin_bar_menu', array( $this, 'admin_bar' ), 100 );
	}

	public function menu() {
		$unread = WBCB_Conversations::unread_count();
		$badge  = $unread ? ' <span class="awaiting-mod update-plugins count-' . (int) $unread . '"><span class="pending-count">' . (int) $unread . '</span></span>' : '';

		add_menu_page(
			'چت باکس',
			'چت باکس' . $badge,
			'edit_posts',
			'webakery-chat-box',
			array( $this, 'render_inbox' ),
			'dashicons-format-chat',
			58
		);

		add_submenu_page(
			'webakery-chat-box',
			'صندوق پیام',
			'صندوق پیام',
			'edit_posts',
			'webakery-chat-box',
			array( $this, 'render_inbox' )
		);

		add_submenu_page(
			'webakery-chat-box',
			'تنظیمات چت',
			'تنظیمات',
			'manage_options',
			'webakery-chat-box-settings',
			array( $this, 'render_settings' )
		);

		add_submenu_page(
			'webakery-chat-box',
			'خرید و لایسنس',
			'خرید و لایسنس',
			'manage_options',
			'webakery-chat-box-license',
			array( $this, 'render_license' )
		);
	}

	public function register_settings() {
		register_setting(
			'wbcb_group',
			WBCB_Settings::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( 'WBCB_Settings', 'sanitize' ),
				'default'           => WBCB_Settings::defaults(),
			)
		);
	}

	public function assets( $hook ) {
		if ( false === strpos( (string) $hook, 'webakery-chat-box' ) ) {
			return;
		}
		wp_enqueue_style( 'wbcb-admin', WBCB_URL . 'assets/css/admin.css', array(), WBCB_VERSION );

		$is_settings = false !== strpos( (string) $hook, 'webakery-chat-box-settings' );
		if ( $is_settings ) {
			wp_enqueue_script( 'wbcb-settings', WBCB_URL . 'assets/js/admin-settings.js', array(), WBCB_VERSION, true );
			wp_localize_script(
				'wbcb-settings',
				'WBCB_ADMIN',
				array(
					'ajax'  => admin_url( 'admin-ajax.php' ),
					'nonce' => wp_create_nonce( 'wbcb_admin' ),
					'i18n'  => array(
						'testing' => 'در حال ارسال…',
						'error'   => 'خطا',
					),
				)
			);
			return;
		}

		wp_enqueue_script( 'wbcb-admin', WBCB_URL . 'assets/js/admin-inbox.js', array(), WBCB_VERSION, true );

		$conv_id = isset( $_GET['conv'] ) ? (int) $_GET['conv'] : 0; // phpcs:ignore

		wp_localize_script(
			'wbcb-admin',
			'WBCB_ADMIN',
			array(
				'ajax'   => admin_url( 'admin-ajax.php' ),
				'nonce'  => wp_create_nonce( 'wbcb_admin' ),
				'convId' => $conv_id,
				'unread' => WBCB_Conversations::unread_count(),
				'pollMs' => 4000,
				'i18n'   => array(
					'send'   => 'ارسال پاسخ',
					'close'  => 'بستن گفتگو',
					'search' => 'جستجو…',
					'empty'  => 'هنوز پیامی نیست',
					'error'  => 'خطا',
					'open'   => 'باز',
					'closed' => 'بسته',
				),
			)
		);
	}

	public function admin_bar( $bar ) {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}
		$n = WBCB_Conversations::unread_count();
		$bar->add_node(
			array(
				'id'    => 'wbcb-chat',
				'title' => 'چت' . ( $n ? ' (' . $n . ')' : '' ),
				'href'  => admin_url( 'admin.php?page=webakery-chat-box' ),
				'meta'  => array( 'class' => 'wbcb-ab-item' ),
			)
		);
	}

	public function render_inbox() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( 'دسترسی غیرمجاز' );
		}
		include WBCB_PATH . 'templates/admin-inbox.php';
	}

	public function render_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'دسترسی غیرمجاز' );
		}
		include WBCB_PATH . 'templates/admin-settings.php';
	}

	public function render_license() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'دسترسی غیرمجاز' );
		}
		include WBCB_PATH . 'templates/admin-license.php';
	}
}
