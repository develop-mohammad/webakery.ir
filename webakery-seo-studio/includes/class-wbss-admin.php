<?php
defined( 'ABSPATH' ) || exit;

class WBSS_Admin {

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
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
	}

	public function menu() {
		add_menu_page(
			'سئو استودیو',
			'سئو استودیو',
			'manage_options',
			'webakery-seo-studio',
			array( $this, 'render' ),
			'dashicons-chart-area',
			56
		);
	}

	public function assets( $hook ) {
		if ( false === strpos( (string) $hook, 'webakery-seo-studio' ) ) {
			return;
		}
		wp_enqueue_style( 'wbss-admin', WBSS_URL . 'assets/css/admin.css', array(), WBSS_VERSION );
		wp_enqueue_script( 'wbss-charts', WBSS_URL . 'assets/js/charts.js', array(), WBSS_VERSION, true );
		wp_enqueue_script( 'wbss-admin', WBSS_URL . 'assets/js/admin.js', array( 'wbss-charts' ), WBSS_VERSION, true );
		wp_localize_script(
			'wbss-admin',
			'WBSS',
			array(
				'ajax'    => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'wbss_admin' ),
				'today'   => WBSS_Jalali::today_g(),
				'todayFa' => WBSS_Jalali::today_label(),
				'i18n'    => self::i18n(),
			)
		);
	}

	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		include WBSS_PATH . 'includes/views/studio.php';
	}

	public static function i18n() {
		return array(
			'modules'     => array(
				'dashboard'  => 'نمای کلی',
				'keywords'   => 'کیورد و رتبه',
				'content'    => 'تولید محتوا',
				'technical'  => 'سئو تکنیکال',
				'backlinks'  => 'بک‌لینک',
				'press'      => 'رپورتاژ',
				'activity'   => 'گزارش اقدامات',
				'settings'   => 'پروژه‌ها',
			),
			'intent'      => array(
				'informational'  => 'اطلاعاتی',
				'transactional'  => 'تراکنشی',
				'commercial'     => 'تجاری',
				'navigational'   => 'ناوبری',
			),
			'kw_status'   => array(
				'active'   => 'فعال',
				'paused'   => 'متوقف',
				'archived' => 'بایگانی',
			),
			'content_st'  => array(
				'draft'     => 'پیش‌نویس',
				'published' => 'منتشرشده',
				'updated'   => 'به‌روزشده',
			),
			'tech_cat'    => array(
				'speed'    => 'سرعت',
				'index'    => 'ایندکس',
				'schema'   => 'اسکیما',
				'mobile'   => 'موبایل',
				'security' => 'امنیت',
				'crawl'    => 'خزش',
				'other'    => 'سایر',
			),
			'severity'    => array(
				'low'      => 'کم',
				'medium'   => 'متوسط',
				'high'     => 'زیاد',
				'critical' => 'بحرانی',
			),
			'tech_st'     => array(
				'open'        => 'باز',
				'in_progress' => 'در حال انجام',
				'done'        => 'انجام‌شده',
			),
			'rel'         => array(
				'dofollow'  => 'Dofollow',
				'nofollow'  => 'Nofollow',
				'ugc'       => 'UGC',
				'sponsored' => 'Sponsored',
			),
			'bl_st'       => array(
				'pending' => 'در انتظار',
				'live'    => 'زنده',
				'lost'    => 'از دست رفته',
			),
			'press_st'    => array(
				'planned'   => 'برنامه‌ریزی',
				'published' => 'منتشرشده',
				'lost'      => 'حذف‌شده',
			),
			'mod_label'   => array(
				'keywords'  => 'کیورد',
				'rank'      => 'رتبه',
				'content'   => 'محتوا',
				'technical' => 'تکنیکال',
				'backlinks' => 'بک‌لینک',
				'press'     => 'رپورتاژ',
				'project'   => 'پروژه',
			),
			'act_label'   => array(
				'created' => 'ثبت',
				'updated' => 'ویرایش',
				'deleted' => 'حذف',
				'checked' => 'چک رتبه',
			),
			'save'        => 'ذخیره',
			'cancel'      => 'انصراف',
			'delete'      => 'حذف',
			'edit'        => 'ویرایش',
			'rank'        => 'ثبت رتبه',
			'empty'       => 'هنوز موردی ثبت نشده.',
			'error'       => 'خطا در ارتباط',
			'confirm_del' => 'این مورد حذف شود؟',
			'ok'          => 'ذخیره شد.',
		);
	}
}
