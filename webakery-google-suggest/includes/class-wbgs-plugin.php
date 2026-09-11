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
		$current = get_option( self::OPTION );
		if ( ! $current ) {
			add_option( self::OPTION, self::defaults(), '', false );
		} else {
			update_option( self::OPTION, wp_parse_args( (array) $current, self::defaults() ), false );
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
			'hl'                   => 'fa',
			'gl'                   => 'ir',
			'delay_ms'             => 300,
			'front_enabled'        => 1,
			'front_slug'           => 'sajest',
			'front_public'         => 1,
			'ads_developer_token'  => '',
			'ads_client_id'        => '',
			'ads_client_secret'    => '',
			'ads_refresh_token'    => '',
			'ads_customer_id'      => '',
			'ads_login_customer_id'=> '',
			'guest_daily_cap'      => 400,
		);
	}

	public static function settings() {
		return wp_parse_args( (array) get_option( self::OPTION, array() ), self::defaults() );
	}

	public static function script_data( $nonce_action ) {
		$settings = self::settings();
		$ajax     = admin_url( 'admin-ajax.php' );
		$path     = wp_parse_url( $ajax, PHP_URL_PATH );
		return array(
			'ajax'      => $path ? $path : $ajax,
			'nonce'     => wp_create_nonce( $nonce_action ),
			'delay'     => (int) $settings['delay_ms'],
			'licensed'  => self::licensed(),
			'ads'       => WBGS_Ads::configured(),
			'intents'   => WBGS_Intent::labels(),
			'entities'  => WBGS_Entity::labels(),
			'lexicon'   => WBGS_Entity::public_lexicon(),
			'lengths'   => WBGS_Taxonomy::length_labels(),
			'extras'    => WBGS_Taxonomy::extra_labels(),
			'axes'      => WBGS_Taxonomy::axis_titles(),
			'geo'       => WBGS_Taxonomy::geo_markers(),
			'seasonal'  => WBGS_Taxonomy::seasonal_markers(),
			'brands'    => WBGS_Taxonomy::brand_markers(),
			'affixes'   => WBGS_Affixes::public_map(),
			'sources'   => WBGS_Suggest::sources(),
			'i18n'      => array(
				'empty'    => 'عبارت پایه را بنویسید.',
				'locked'   => 'برای استخراج، لایسنس را فعال کنید یا دوره آزمایشی را استفاده کنید.',
				'limited'  => 'گوگل درخواست‌ها را محدود کرد. کمی صبر کنید و دوباره تلاش کنید.',
				'network'  => 'ارتباط با سرور برقرار نشد.',
				'none'     => 'گوگل برای این عبارت پیشنهادی برنگرداند.',
				'done'     => 'استخراج تمام شد.',
				'copy_ok'  => 'کپی شد.',
				'copy_one' => 'کپی',
				'copy_err' => 'کپی نشد؛ دستی انتخاب کنید.',
				'stopped'  => 'استخراج متوقف شد.',
				'vol_wait' => 'در حال گرفتن میزان سرچ ماهانه از گوگل ادز…',
				'vol_off'  => 'میزان سرچ ماهانه فقط با اتصال Keyword Planner نشان داده می‌شود.',
				'longtail' => 'در حال گرفتن لانگ‌تیل از سجست گوگل…',
				'saved'    => 'گزارش ذخیره شد.',
				'loaded'   => 'گزارش باز شد.',
				'compare'  => 'مقایسه آماده است.',
				'comp_b'   => 'در حال استخراج عبارت دوم برای مقایسه…',
				'comp_note'=> 'امتیاز رقابت نسبی از سجست است؛ KD اهرفس نیست.',
				'need_b'   => 'گزارش دوم را از تاریخچه انتخاب کنید یا عبارت دوم را استخراج کنید.',
				'usage'    => 'امروز %s درخواست به گوگل',
				'trends'   => 'در حال خواندن ترند گوگل برای ایران…',
				'trends_off' => 'ترند ایران از فید رسمی گوگل خوانده نشد. از لینک ترند باز کنید.',
				'xmind'    => 'در حال ساخت فایل XMind…',
				'xmind_ok' => 'فایل XMind آماده شد.',
				'xmind_err'=> 'ساخت XMind نشد.',
			),
			'longtailCap' => 50,
			'canSave'     => function_exists( 'is_user_logged_in' ) && is_user_logged_in(),
			'reportList'  => class_exists( 'WBGS_Reports' ) ? WBGS_Reports::list_for() : array(),
			'usage'       => class_exists( 'WBGS_Reports' ) ? WBGS_Reports::usage_snapshot() : array( 'used' => 0, 'cap' => 400, 'admin' => false ),
		);
	}

	private function __construct() {
		$this->boot_license();

		require_once WBGS_PATH . 'includes/class-wbgs-suggest.php';
		require_once WBGS_PATH . 'includes/class-wbgs-affixes.php';
		require_once WBGS_PATH . 'includes/class-wbgs-entity.php';
		require_once WBGS_PATH . 'includes/class-wbgs-tree.php';
		require_once WBGS_PATH . 'includes/class-wbgs-intent.php';
		require_once WBGS_PATH . 'includes/class-wbgs-taxonomy.php';
		require_once WBGS_PATH . 'includes/class-wbgs-work.php';
		require_once WBGS_PATH . 'includes/class-wbgs-reports.php';
		require_once WBGS_PATH . 'includes/class-wbgs-ads.php';
		require_once WBGS_PATH . 'includes/class-wbgs-trends.php';
		require_once WBGS_PATH . 'includes/class-wbgs-export.php';
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
					'لانگ‌تیل از ادامهٔ همان سجست گوگل',
					'حرف‌گردانی الفبای فارسی و فاصله قبل/بعد',
					'شورت‌کد [webakery_suggest] برای قرار دادن در هر برگه',
					'صفحهٔ جدا روی سایت با ورود موبایل یا جیمیل',
					'دسته‌بندی دسته محصول / محصول / برند جدا از اینتنت',
					'قفسه کالا، ماتریس اینتنت×موجودیت و فهرست پرسش FAQ از عبارت واقعی',
					'دسته‌بندی پنج‌محوره به‌همراه پیشوند و پسوند رایج (خرید، قیمت، چیست، ترین، شهر…)',
					'درخت محتوا، اینتنت و پیلار کلاستر',
					'فیلتر سوالی، بریف محتوا و عنوان/متا از عبارت‌های واقعی',
					'امتیاز رقابت نسبی (نه KD ساختگی)',
					'ترند روزانه گوگل برای ایران (فید رسمی RSS، geo=IR)',
					'خروجی XMind، تقویم محتوا و گزارش HTML از عبارت واقعی',
					'سجست گوگل و یوتیوب',
					'تاریخچه گزارش، مقایسه دو عبارت، CSV اکسل',
					'میزان سرچ ماهانه از Google Ads Keyword Planner',
					'خروجی CSV و کپی یکجا یا کپی تک‌تک عبارت‌ها',
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
