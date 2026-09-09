<?php
defined( 'ABSPATH' ) || exit;

class WBCN_Plugin {

	/** @var self|null */
	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public static function activate() {
		require_once WBCN_PATH . 'includes/class-wbcn-settings.php';
		require_once WBCN_PATH . 'includes/class-wbcn-cron.php';
		$s = wp_parse_args( (array) get_option( WBCN_Settings::OPTION, array() ), WBCN_Settings::defaults() );
		if ( empty( $s['webhook_secret'] ) ) {
			$s['webhook_secret'] = wp_generate_password( 20, false, false );
		}
		update_option( WBCN_Settings::OPTION, $s, false );
		if ( ! get_option( 'wbl_' . WBCN_PRODUCT . '_install_time' ) ) {
			add_option( 'wbl_' . WBCN_PRODUCT . '_install_time', time(), '', false );
		}
		WBCN_Cron::sync();
	}

	public static function deactivate() {
		require_once WBCN_PATH . 'includes/class-wbcn-cron.php';
		WBCN_Cron::unschedule( WBCN_Cron::DAILY );
		WBCN_Cron::unschedule( WBCN_Cron::POLL );
	}

	private function __construct() {
		require_once WBCN_PATH . 'includes/class-wbcn-settings.php';
		require_once WBCN_PATH . 'includes/class-wbcn-ideas.php';
		require_once WBCN_PATH . 'includes/class-wbcn-telegram.php';
		require_once WBCN_PATH . 'includes/class-wbcn-content.php';
		require_once WBCN_PATH . 'includes/class-wbcn-cron.php';
		require_once WBCN_PATH . 'includes/class-wbcn-webhook.php';
		require_once WBCN_PATH . 'includes/class-wbcn-frontend.php';

		$this->boot_license();
		WBCN_Cron::register();
		WBCN_Webhook::register();
		WBCN_Frontend::register();

		add_action( 'transition_post_status', array( $this, 'on_publish' ), 20, 3 );
		add_action( 'updated_option', array( $this, 'on_settings' ), 10, 1 );

		if ( is_admin() ) {
			require_once WBCN_PATH . 'includes/class-wbcn-admin.php';
			WBCN_Admin::instance();
		}

		add_filter( 'plugin_action_links_' . plugin_basename( WBCN_FILE ), array( $this, 'action_links' ) );
		add_filter( 'plugin_row_meta', array( $this, 'row_meta' ), 10, 2 );
	}

	public function on_settings( $option ) {
		if ( WBCN_Settings::OPTION === $option ) {
			WBCN_Cron::sync();
		}
	}

	private function boot_license() {
		require_once WBCN_PATH . 'includes/class-wb-license.php';
		WB_License::init(
			array(
				'product'    => WBCN_PRODUCT,
				'name'       => 'کانال‌یار — ربات تلگرام کانال',
				'price'      => '۲۴۹,۰۰۰ تومان',
				'file'       => WBCN_FILE,
				'version'    => WBCN_VERSION,
				'trial_days' => 7,
				'page'       => 'admin.php?page=' . WBCN_MENU . '&tab=license',
				'features'   => array(
					'ارسال خودکار نوشته و محصول به کانال تلگرام',
					'تولید ایده رشد و محتوای ارزشمند از مطالب سایت',
					'تقویم هفتگی کانال + ایده روزانه زمان‌بندی‌شده',
					'ربات عضویت، جستجو و دستور مدیر',
					'پروکسی برای هاست ایران',
					'به‌روزرسانی خودکار از webakery.ir',
				),
			)
		);
	}

	public static function licensed() {
		return class_exists( 'WB_License' ) && WB_License::is_active( WBCN_PRODUCT );
	}

	/**
	 * @param string  $new
	 * @param string  $old
	 * @param WP_Post $post
	 */
	public function on_publish( $new, $old, $post ) {
		if ( 'publish' !== $new || 'publish' === $old ) {
			return;
		}
		if ( ! $post instanceof WP_Post ) {
			return;
		}
		if ( ! self::licensed() ) {
			return;
		}
		$s = WBCN_Settings::get();
		if ( empty( $s['auto_post'] ) ) {
			return;
		}
		if ( ! in_array( $post->post_type, (array) $s['post_types'], true ) ) {
			return;
		}
		if ( wp_is_post_revision( $post ) || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) ) {
			return;
		}
		if ( get_post_meta( $post->ID, '_wbcn_sent', true ) ) {
			return;
		}

		$item = WBCN_Content::item_from_post( $post );
		if ( ! $item ) {
			return;
		}
		$chan = ! empty( $s['signature'] ) ? WBCN_Settings::channel_username( $s ) : '';
		$text = WBCN_Ideas::publish_post( $item, $chan, ! empty( $s['auto_excerpt'] ) );

		$res = new WP_Error( 'skip', '' );
		if ( ! empty( $s['auto_photo'] ) && ! empty( $item['image'] ) ) {
			$res = WBCN_Telegram::send_photo( $item['image'], $text, '', $s );
		} else {
			$res = WBCN_Telegram::send_text( $text, '', array(), $s );
		}

		if ( ! is_wp_error( $res ) ) {
			update_post_meta( $post->ID, '_wbcn_sent', time() );
			WBCN_Cron::log( 'ارسال خودکار: ' . $item['title'] );
		} else {
			WBCN_Cron::log( 'خطای ارسال «' . $item['title'] . '»: ' . $res->get_error_message() );
		}
	}

	public function action_links( $links ) {
		$custom = array(
			'<a href="' . esc_url( admin_url( 'admin.php?page=' . WBCN_MENU . '&tab=ideas' ) ) . '">ایده‌ها</a>',
			'<a href="' . esc_url( admin_url( 'admin.php?page=' . WBCN_MENU . '&tab=settings' ) ) . '">تنظیمات</a>',
		);
		return array_merge( $custom, $links );
	}

	public function row_meta( $links, $file ) {
		if ( plugin_basename( WBCN_FILE ) !== $file ) {
			return $links;
		}
		$links[] = '<span>نسخه ' . esc_html( WBCN_VERSION ) . '</span>';
		$links[] = '<a href="https://webakery.ir" target="_blank" rel="noopener">سازنده: webakery.ir</a>';
		return $links;
	}
}
