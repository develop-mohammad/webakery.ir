<?php
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( '\Elementor\Widget_Base' ) ) {
	return;
}

/**
 * ویجت المنتور «صفحه‌های تخفیف» — همان شورت‌کد فهرست صفحه‌های تخفیف با تنظیمات بصری.
 */
class WDP_Elementor_Widget extends \Elementor\Widget_Base {

	public function get_name() {
		return 'wdp_discount_pages';
	}

	public function get_title() {
		return 'صفحه‌های تخفیف';
	}

	public function get_icon() {
		return 'eicon-price-table';
	}

	public function get_categories() {
		return array( 'general' );
	}

	public function get_keywords() {
		return array( 'discount', 'تخفیف', 'صفحه تخفیف', 'ووکامرس', 'webakery' );
	}

	protected function register_controls() {
		$this->start_controls_section(
			'wdp_content',
			array(
				'label' => 'تنظیمات فهرست صفحه‌های تخفیف',
			)
		);

		$this->add_control(
			'columns',
			array(
				'label'   => 'تعداد ستون',
				'type'    => \Elementor\Controls_Manager::NUMBER,
				'min'     => 1,
				'max'     => 6,
				'default' => 3,
			)
		);

		$this->add_control(
			'show_empty',
			array(
				'label'        => 'نمایش صفحه‌های بدون محصول',
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => 'بله',
				'label_off'    => 'خیر',
				'return_value' => 'yes',
				'default'      => '',
			)
		);

		$this->end_controls_section();
	}

	protected function render() {
		$s = $this->get_settings_for_display();
		echo do_shortcode(
			sprintf(
				'[%s columns="%d" show_empty="%d"]',
				WDP_Frontend::SHORTCODE,
				(int) ( $s['columns'] ?? 3 ),
				'yes' === ( $s['show_empty'] ?? '' ) ? 1 : 0
			)
		);
	}
}
