<?php
defined( 'ABSPATH' ) || exit;

class WBGS_Plugin {

	const OPTION = 'wbgs_settings';

	/** @var self|null */
	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public static function activate() {
		if ( ! get_option( self::OPTION ) ) {
			add_option( self::OPTION, self::defaults(), '', false );
		}
		require_once WBGS_PATH . 'includes/class-wbgs-frontend.php';
		WBGS_Frontend::instance()->add_rewrite();
		flush_rewrite_rules();
	}

	public static function deactivate() {
		flush_rewrite_rules();
	}

	public static function defaults() {
		return array(
			'hl'            => 'fa',
			'gl'            => 'ir',
			'delay_ms'      => 300,
			'front_enabled' => 1,
			'front_slug'    => 'sajest',
		);
	}

	public static function settings() {
		return wp_parse_args( (array) get_option( self::OPTION, array() ), self::defaults() );
	}

	public static function script_data( $nonce_action ) {
		$settings = self::settings();
		return array(
			'ajax'     => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( $nonce_action ),
			'delay'    => (int) $settings['delay_ms'],
			'licensed' => self::licensed(),
			'i18n'     => array(
				'empty'    => 'عبارت پایه را بنویسید.',
				'locked'   => 'برای استخراج، لایسنس را فعال کنید یا دوره آزمایشی را استفاده کنید.',
				'limited'  => 'گوگل درخواست‌ها را محدود کرد. کمی صبر کنید و دوباره تلاش کنید.',
				'network'  => 'ارتباط با سرور برقرار نشد.',
				'none'     => 'گوگل برای این عبارت پیشنهادی برنگرداند.',
				'done'     => 'استخراج تمام شد.',
				'copy_ok'  => 'کپی شد.',
				'copy_err' => 'کپی نشد؛ دستی انتخاب کنید.',
				'stopped'  => 'استخراج متوقف شد.',
			),
		);
	}

	private function __construct() {
		$this->boot_license();

		require_once WBGS_PATH . 'includes/class-wbgs-suggest.php';
		require_once WBGS_PATH . 'includes/class-wbgs-frontend.php';
		WBGS_Frontend::instance();

		if ( is_admin() ) {
			require_once WBGS_PATH . 'includes/class-wbgs-admin.php';
			WBGS_Admin::instance();
		}
	}

	private function boot_license() {
		require_once WBGS_PATH . 'includes/class-wb-license.php';
		WB_License::init(
			array(
				'product'    => WBGS_PRODUCT,
				'name'       => 'سجست‌یاب گوگل — webakery.ir',
				'price'      => '۱۹۹٬۰۰۰ تومان',
				'file'       => WBGS_FILE,
				'version'    => WBGS_VERSION,
				'trial_days' => 7,
				'page'       => 'admin.php?page=' . WBGS_MENU . '&tab=license',
				'features'   => array(
					'استخراج پیشنهادهای واقعی Autocomplete گوگل',
					'حرف‌گردانی الفبای فارسی و فاصله قبل/بعد',
					'صفحهٔ جدا روی سایت با ورود موبایل یا جیمیل',
					'خروجی CSV و کپی یکجا',
					'به‌روزرسانی خودکار از webakery.ir',
				),
			)
		);
	}

	/** آیا افزونه مجاز به استخراج است؟ (لایسنس معتبر یا دوره آزمایشی) */
	public static function licensed() {
		return class_exists( 'WB_License' ) && WB_License::is_active( WBGS_PRODUCT );
	}
}
