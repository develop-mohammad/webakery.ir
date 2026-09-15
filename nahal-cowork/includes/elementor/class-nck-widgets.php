<?php
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( '\Elementor\Widget_Base' ) ) {
	return;
}

class NCK_Contract_Widget extends \Elementor\Widget_Base {

	public function get_name() {
		return 'nck-contract';
	}

	public function get_title() {
		return 'قرارداد فضای کار نهال';
	}

	public function get_icon() {
		return 'eicon-document-file';
	}

	public function get_categories() {
		return array( 'webakery', 'general' );
	}

	public function get_keywords() {
		return array( 'نهال', 'قرارداد', 'فضای کار', 'cowork' );
	}

	public function get_style_depends() {
		return array( 'nck-frontend' );
	}

	public function get_script_depends() {
		return array( 'nck-frontend' );
	}

	protected function render() {
		echo NCK_Frontend::shortcode_contract(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}

class NCK_Hall_Widget extends \Elementor\Widget_Base {

	public function get_name() {
		return 'nck-hall';
	}

	public function get_title() {
		return 'قرارداد اجاره سالن نهال';
	}

	public function get_icon() {
		return 'eicon-banner';
	}

	public function get_categories() {
		return array( 'webakery', 'general' );
	}

	public function get_keywords() {
		return array( 'نهال', 'سالن', 'اجاره', 'مراسم' );
	}

	public function get_style_depends() {
		return array( 'nck-frontend' );
	}

	public function get_script_depends() {
		return array( 'nck-frontend' );
	}

	protected function render() {
		echo NCK_Frontend::shortcode_hall(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}

class NCK_Learner_Widget extends \Elementor\Widget_Base {

	public function get_name() {
		return 'nck-learner';
	}

	public function get_title() {
		return 'فرم پذیرش فراگیر نهال';
	}

	public function get_icon() {
		return 'eicon-form-horizontal';
	}

	public function get_categories() {
		return array( 'webakery', 'general' );
	}

	public function get_keywords() {
		return array( 'نهال', 'پذیرش', 'فراگیر', 'ثبت‌نام' );
	}

	public function get_style_depends() {
		return array( 'nck-frontend' );
	}

	public function get_script_depends() {
		return array( 'nck-frontend' );
	}

	protected function render() {
		echo NCK_Frontend::shortcode_learner(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}

class NCK_Portal_Widget extends \Elementor\Widget_Base {

	public function get_name() {
		return 'nck-portal';
	}

	public function get_title() {
		return 'پورتال عضو نهال';
	}

	public function get_icon() {
		return 'eicon-person';
	}

	public function get_categories() {
		return array( 'webakery', 'general' );
	}

	public function get_keywords() {
		return array( 'نهال', 'حضور', 'شیفت', 'cowork' );
	}

	public function get_style_depends() {
		return array( 'nck-frontend' );
	}

	public function get_script_depends() {
		return array( 'nck-frontend' );
	}

	protected function render() {
		echo NCK_Frontend::shortcode_portal(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}

class NCK_Form_Widget extends \Elementor\Widget_Base {

	public function get_name() {
		return 'nck-form';
	}

	public function get_title() {
		return 'فرم سفارشی نهال';
	}

	public function get_icon() {
		return 'eicon-plus-square-o';
	}

	public function get_categories() {
		return array( 'webakery', 'general' );
	}

	public function get_keywords() {
		return array( 'نهال', 'فرم', 'ثبت‌نام', 'پرداخت' );
	}

	public function get_style_depends() {
		return array( 'nck-frontend' );
	}

	public function get_script_depends() {
		return array( 'nck-frontend' );
	}

	protected function register_controls() {
		$options = array( '' => 'انتخاب فرم' );
		if ( class_exists( 'NCK_Forms' ) ) {
			foreach ( NCK_Forms::published() as $form ) {
				$options[ $form['slug'] ] = $form['title'];
			}
		}
		$this->start_controls_section(
			'nck_form',
			array( 'label' => 'فرم' )
		);
		$this->add_control(
			'slug',
			array(
				'label'   => 'فرم',
				'type'    => \Elementor\Controls_Manager::SELECT,
				'options' => $options,
				'default' => '',
			)
		);
		$this->end_controls_section();
	}

	protected function render() {
		$slug = '';
		if ( method_exists( $this, 'get_settings_for_display' ) ) {
			$slug = (string) $this->get_settings_for_display( 'slug' );
		}
		echo NCK_Frontend::shortcode_form( array( 'slug' => $slug ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}

