<?php
defined( 'ABSPATH' ) || exit;

class RZM_Frontend {

	public static function hooks() {
		add_shortcode( 'roozam', array( __CLASS__, 'shortcode' ) );
		add_shortcode( 'rzm_planner', array( __CLASS__, 'shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'maybe_enqueue' ) );
	}

	public static function maybe_enqueue() {
		if ( ! self::should_load() ) {
			return;
		}
		self::enqueue();
	}

	public static function enqueue() {
		wp_enqueue_style(
			'rzm-fonts',
			'https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;600;700;800&display=swap',
			array(),
			null
		);
		wp_enqueue_style(
			'rzm-frontend',
			RZM_URL . 'assets/css/frontend.css',
			array( 'rzm-fonts' ),
			RZM_VERSION
		);
		wp_enqueue_script(
			'rzm-jalali',
			RZM_URL . 'assets/js/jalali.js',
			array(),
			RZM_VERSION,
			true
		);
		wp_enqueue_script(
			'rzm-frontend',
			RZM_URL . 'assets/js/frontend.js',
			array( 'rzm-jalali' ),
			RZM_VERSION,
			true
		);

		$user_id = get_current_user_id();
		$date    = gmdate( 'Y-m-d', current_time( 'timestamp' ) );
		$boot    = array(
			'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
			'nonce'      => wp_create_nonce( 'rzm_nonce' ),
			'loggedIn'   => is_user_logged_in(),
			'usable'     => RZM_Plugin::is_usable(),
			'today'      => $date,
			'prefs'      => RZM_Settings::user_prefs( $user_id ),
			'day'        => $user_id ? RZM_Planner::get_day( $user_id, $date ) : array(
				'date'  => $date,
				'tasks' => array(),
				'note'  => '',
			),
			'routines'   => $user_id ? RZM_Planner::get_routines( $user_id ) : RZM_Planner::default_routines(),
			'i18n'       => self::i18n(),
			'pageTitle'  => RZM_Settings::get()['page_title'],
			'loginUrl'   => wp_login_url( get_permalink() ),
		);
		wp_localize_script( 'rzm-frontend', 'RZM', $boot );
	}

	public static function shortcode( $atts = array() ) {
		if ( ! RZM_Plugin::is_usable() ) {
			if ( current_user_can( 'manage_options' ) ) {
				return '<div class="rzm-locked">لایسنس روزم فعال نیست. از منوی لایسنس فعال کنید.</div>';
			}
			return '';
		}

		self::enqueue();

		$atts = shortcode_atts(
			array(
				'title' => RZM_Settings::get()['page_title'],
			),
			$atts,
			'roozam'
		);

		ob_start();
		$title = $atts['title'];
		include RZM_PATH . 'templates/app.php';
		return (string) ob_get_clean();
	}

	private static function should_load() {
		if ( ! is_singular() ) {
			return false;
		}
		global $post;
		if ( ! $post instanceof WP_Post ) {
			return false;
		}
		return has_shortcode( $post->post_content, 'roozam' )
			|| has_shortcode( $post->post_content, 'rzm_planner' );
	}

	private static function i18n() {
		return array(
			'planDay'       => 'برنامه‌ریزی امروز',
			'addTask'       => 'افزودن کار',
			'save'          => 'ذخیره',
			'saved'         => 'ذخیره شد',
			'empty'         => 'هنوز کاری برای امروز ندارید.',
			'loginHint'     => 'برای ذخیره روی سرور وارد شوید؛ فعلاً روی همین دستگاه نگه داشته می‌شود.',
			'done'          => 'انجام شد',
			'undone'        => 'برگردان',
			'delete'        => 'حذف',
			'routines'      => 'عادت‌های روزانه',
			'settings'      => 'تنظیمات روز',
			'unscheduled'   => 'بدون زمان',
			'priorityHigh'  => 'مهم',
			'priorityMed'   => 'عادی',
			'priorityLow'   => 'کم‌اهمیت',
			'progress'      => 'پیشرفت امروز',
			'wake'          => 'بیدار شدن',
			'sleep'         => 'خواب',
			'break'         => 'استراحت بین کارها (دقیقه)',
			'note'          => 'یادداشت روز',
			'planDone'      => 'برنامه امروز چیده شد',
			'error'         => 'مشکلی پیش آمد. دوباره تلاش کنید.',
		);
	}
}
