<?php
defined( 'ABSPATH' ) || exit;

/**
 * ویجت Elementor برای فرم ورود.
 */
class WBL_Elementor {

	public static function hooks() {
		add_action( 'elementor/widgets/register', array( __CLASS__, 'register_widget' ) );
		add_action( 'elementor/widgets/widgets_registered', array( __CLASS__, 'register_widget_legacy' ) );
		add_action( 'elementor/elements/categories_registered', array( __CLASS__, 'category' ) );
		// فقط در ادیتور/پیش‌نمایش المنتور — نه کل فرانت سایت.
		add_action( 'elementor/editor/after_enqueue_styles', array( 'WBL_Frontend', 'enqueue' ) );
		add_action( 'elementor/preview/enqueue_styles', array( 'WBL_Frontend', 'enqueue' ) );
	}

	public static function category( $elements_manager ) {
		$elements_manager->add_category(
			'webakery',
			array(
				'title' => 'وب‌آکری',
				'icon'  => 'fa fa-plug',
			)
		);
	}

	public static function register_widget( $widgets_manager ) {
		require_once WBL_PATH . 'includes/elementor/class-wbl-login-widget.php';
		$widgets_manager->register( new WBL_Login_Widget() );
	}

	/** سازگاری با Elementor قدیمی‌تر از 3.5 */
	public static function register_widget_legacy( $widgets_manager ) {
		if ( ! class_exists( 'WBL_Login_Widget' ) ) {
			require_once WBL_PATH . 'includes/elementor/class-wbl-login-widget.php';
		}
		if ( method_exists( $widgets_manager, 'register' ) ) {
			return;
		}
		if ( method_exists( $widgets_manager, 'register_widget_type' ) ) {
			$widgets_manager->register_widget_type( new WBL_Login_Widget() );
		}
	}
}
