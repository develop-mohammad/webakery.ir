<?php
defined( 'ABSPATH' ) || exit;

class NM_Assets {

	public static function register() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'maybe_enqueue_front' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin' ) );
	}

	public static function maybe_enqueue_front() {
		global $post;
		$load = false;
		if ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'nobat_man' ) ) {
			$load = true;
		}
		$load = apply_filters( 'nm_enqueue_front', $load );
		if ( ! $load ) {
			return;
		}
		self::enqueue_front();
	}

	public static function enqueue_front() {
		wp_enqueue_style(
			'nm-vazirmatn',
			'https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css',
			array(),
			null
		);
		wp_enqueue_style( 'nm-front', NM_URL . 'assets/css/frontend.css', array( 'nm-vazirmatn' ), NM_VERSION );
		wp_enqueue_script( 'nm-jalali', NM_URL . 'assets/js/jalali-calendar.js', array(), NM_VERSION, true );
		wp_enqueue_script( 'nm-front', NM_URL . 'assets/js/frontend.js', array( 'nm-jalali' ), NM_VERSION, true );

		$s = NM_Settings::all();
		wp_localize_script( 'nm-front', 'NM_DATA', array(
			'ajax'     => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'nm_public' ),
			'today'    => NM_Jalali::today(),
			'settings' => array(
				'primary'      => $s['primary_color'],
				'accent'       => $s['accent_color'],
				'enable_photo' => (int) $s['enable_photo'],
				'enable_voice' => (int) $s['enable_voice'],
				'require_email'=> (int) $s['require_email'],
				'require_city' => (int) $s['require_city'],
				'require_gender'=> (int) $s['require_gender'],
				'currency'     => $s['currency_label'],
				'min_duration' => (int) $s['min_duration'],
				'max_duration' => (int) $s['max_duration'],
			),
			'i18n' => array(
				'loading'  => 'در حال بارگذاری…',
				'error'    => 'خطایی رخ داد.',
				'selectDay'=> 'یک روز را انتخاب کنید',
				'selectSlot'=> 'ساعت را انتخاب کنید',
				'pay'      => 'پرداخت و تایید نوبت',
			),
		) );
	}

	public static function enqueue_admin( $hook ) {
		if ( false === strpos( (string) $hook, 'nobat-man' ) ) {
			return;
		}
		wp_enqueue_style(
			'nm-vazirmatn',
			'https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css',
			array(),
			null
		);
		wp_enqueue_style( 'nm-admin', NM_URL . 'assets/css/admin.css', array( 'nm-vazirmatn' ), NM_VERSION );
		wp_enqueue_script( 'nm-jalali', NM_URL . 'assets/js/jalali-calendar.js', array(), NM_VERSION, true );
		wp_enqueue_script( 'nm-admin', NM_URL . 'assets/js/admin.js', array( 'nm-jalali' ), NM_VERSION, true );

		$local = array(
			'ajax'  => admin_url( 'admin-ajax.php' ),
			'nonce' => wp_create_nonce( 'nm_admin' ),
			'i18n'  => array(
				'saved'          => 'ذخیره شد',
				'saving'         => 'در حال ذخیره…',
				'error'          => 'خطا در ذخیره',
				'confirmDelete'  => 'این سوال حذف شود؟',
			),
		);

		$tab = sanitize_key( $_GET['tab'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'questions' === $tab ) {
			wp_enqueue_script( 'nm-admin-questions', NM_URL . 'assets/js/admin-questions.js', array(), NM_VERSION, true );
			$current = sanitize_text_field( wp_unslash( $_GET['nm_cat'] ?? '' ) ); // phpcs:ignore
			$cats    = NM_Questions::all_categories();
			if ( ! $current && ! empty( $cats ) ) {
				$current = (string) $cats[0];
			}
			if ( ! $current ) {
				$current = 'عمومی';
			}
			$board = NM_Questions::board_for_category( $current );
			$local['questions'] = array(
				'category' => $current,
				'active'   => $board['active'],
				'fields'   => $board['fields'],
			);
			$local['questionsBase'] = admin_url( 'admin.php?page=nobat-man&tab=questions' );
			wp_localize_script( 'nm-admin-questions', 'NM_ADMIN', $local );
		} else {
			wp_localize_script( 'nm-admin', 'NM_ADMIN', $local );
		}
	}
}
