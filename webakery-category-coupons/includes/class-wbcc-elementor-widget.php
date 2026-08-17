<?php
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( '\Elementor\Widget_Base' ) ) {
	return;
}

/**
 * ویجت المنتور «کد تخفیف دسته‌بندی» — همان شورت‌کد با تنظیمات بصری.
 */
class WBCC_Elementor_Widget extends \Elementor\Widget_Base {

	public function get_name() {
		return 'wbcc_coupon';
	}

	public function get_title() {
		return 'کد تخفیف دسته‌بندی';
	}

	public function get_icon() {
		return 'eicon-price-table';
	}

	public function get_categories() {
		return array( 'general' );
	}

	public function get_keywords() {
		return array( 'coupon', 'تخفیف', 'کد تخفیف', 'ووکامرس', 'webakery' );
	}

	protected function register_controls() {
		$options = array();
		if ( class_exists( 'WBCC_Campaigns' ) ) {
			foreach ( WBCC_Campaigns::all() as $campaign ) {
				$options[ $campaign['id'] ] = $campaign['name'] . ' (#' . $campaign['id'] . ')';
			}
		}

		$this->start_controls_section( 'wbcc_content', array(
			'label' => 'تنظیمات کد تخفیف',
		) );

		$this->add_control( 'campaign', array(
			'label'   => 'کمپین تخفیف',
			'type'    => \Elementor\Controls_Manager::SELECT,
			'options' => $options,
			'default' => $options ? (string) array_key_first( $options ) : '',
		) );

		$this->add_control( 'title', array(
			'label'       => 'عنوان (خالی = نام کمپین)',
			'type'        => \Elementor\Controls_Manager::TEXT,
			'default'     => '',
			'label_block' => true,
		) );

		$this->add_control( 'button', array(
			'label'   => 'متن دکمه',
			'type'    => \Elementor\Controls_Manager::TEXT,
			'default' => 'دریافت کد تخفیف',
		) );

		$this->add_control( 'note', array(
			'label' => 'توضیح کوتاه',
			'type'  => \Elementor\Controls_Manager::TEXTAREA,
		) );

		$this->end_controls_section();
	}

	protected function render() {
		$s = $this->get_settings_for_display();
		echo do_shortcode( sprintf(
			'[%s campaign="%d" title="%s" button="%s" note="%s"]',
			WBCC_Frontend::SHORTCODE,
			(int) ( $s['campaign'] ?? 0 ),
			esc_attr( $s['title'] ?? '' ),
			esc_attr( $s['button'] ?? 'دریافت کد تخفیف' ),
			esc_attr( $s['note'] ?? '' )
		) );
	}
}
