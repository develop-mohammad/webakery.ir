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
