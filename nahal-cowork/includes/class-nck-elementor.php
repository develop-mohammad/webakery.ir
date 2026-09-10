<?php
defined( 'ABSPATH' ) || exit;

class NCK_Elementor {

	public static function hooks() {
		add_action( 'elementor/widgets/register', array( __CLASS__, 'register_widget' ) );
		add_action( 'elementor/widgets/widgets_registered', array( __CLASS__, 'register_widget_legacy' ) );
		add_action( 'elementor/elements/categories_registered', array( __CLASS__, 'category' ) );
		add_action( 'elementor/editor/after_enqueue_styles', array( 'NCK_Frontend', 'enqueue' ) );
		add_action( 'elementor/preview/enqueue_styles', array( 'NCK_Frontend', 'enqueue' ) );
		add_action( 'elementor/frontend/after_enqueue_scripts', array( __CLASS__, 'maybe_enqueue_front' ) );
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
		require_once NCK_PATH . 'includes/elementor/class-nck-widgets.php';
		$widgets_manager->register( new NCK_Contract_Widget() );
		$widgets_manager->register( new NCK_Hall_Widget() );
		$widgets_manager->register( new NCK_Portal_Widget() );
	}

	public static function register_widget_legacy( $widgets_manager ) {
		if ( ! class_exists( 'NCK_Contract_Widget' ) ) {
			require_once NCK_PATH . 'includes/elementor/class-nck-widgets.php';
		}
		if ( method_exists( $widgets_manager, 'register' ) ) {
			return;
		}
		if ( method_exists( $widgets_manager, 'register_widget_type' ) ) {
			$widgets_manager->register_widget_type( new NCK_Contract_Widget() );
			$widgets_manager->register_widget_type( new NCK_Hall_Widget() );
			$widgets_manager->register_widget_type( new NCK_Portal_Widget() );
		}
	}

	public static function maybe_enqueue_front() {
		if ( ! class_exists( '\Elementor\Plugin' ) ) {
			return;
		}
		if ( \Elementor\Plugin::$instance->preview->is_preview_mode()
			|| \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
			NCK_Frontend::enqueue();
		}
	}
}
