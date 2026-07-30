<?php
defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;
use Elementor\Widget_Base;

/**
 * ویجت فرم ورود برای Elementor.
 */
class WBL_Login_Widget extends Widget_Base {

	public function get_name() {
		return 'wbl-login';
	}

	public function get_title() {
		return 'ورود آسان';
	}

	public function get_icon() {
		return 'eicon-lock-user';
	}

	public function get_categories() {
		return array( 'webakery', 'general' );
	}

	public function get_keywords() {
		return array( 'login', 'otp', 'sms', 'google', 'ورود', 'پیامک', 'جیمیل' );
	}

	public function get_style_depends() {
		return array( 'wbl-frontend' );
	}

	public function get_script_depends() {
		return array( 'wbl-frontend' );
	}

	protected function register_controls() {
		$this->start_controls_section(
			'section_content',
			array(
				'label' => 'محتوا',
			)
		);

		$this->add_control(
			'show_title',
			array(
				'label'        => 'نمایش عنوان افزونه',
				'type'         => Controls_Manager::SWITCHER,
				'label_on'     => 'بله',
				'label_off'    => 'خیر',
				'return_value' => 'yes',
				'default'      => 'no',
				'description'  => 'اگر عنوان را با ویجت Heading المنتور می‌گذارید، خاموش بگذارید.',
			)
		);

		$this->add_control(
			'title',
			array(
				'label'     => 'عنوان',
				'type'      => Controls_Manager::TEXT,
				'default'   => '',
				'condition' => array( 'show_title' => 'yes' ),
			)
		);

		$this->add_control(
			'subtitle',
			array(
				'label'     => 'زیرعنوان',
				'type'      => Controls_Manager::TEXT,
				'default'   => '',
				'condition' => array( 'show_title' => 'yes' ),
			)
		);

		$this->add_control(
			'redirect',
			array(
				'label'       => 'آدرس بعد از ورود',
				'type'        => Controls_Manager::URL,
				'placeholder' => home_url( '/' ),
				'default'     => array( 'url' => '' ),
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_style',
			array(
				'label' => 'استایل مینیمال',
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'accent',
			array(
				'label'     => 'رنگ دکمه',
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .wbl-box' => '--wbl-primary: {{VALUE}}; --wbl-accent: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'text_color',
			array(
				'label'     => 'رنگ متن',
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .wbl-box' => '--wbl-text: {{VALUE}}; color: {{VALUE}};',
				),
			)
		);

		$this->add_responsive_control(
			'max_width',
			array(
				'label'      => 'حداکثر عرض',
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%', 'em' ),
				'range'      => array(
					'px' => array( 'min' => 240, 'max' => 720 ),
					'%'  => array( 'min' => 40, 'max' => 100 ),
				),
				'selectors'  => array(
					'{{WRAPPER}} .wbl-box' => 'max-width: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'align',
			array(
				'label'     => 'تراز افقی فرم',
				'type'      => Controls_Manager::CHOOSE,
				'options'   => array(
					'flex-start' => array(
						'title' => 'راست',
						'icon'  => 'eicon-h-align-right',
					),
					'center'     => array(
						'title' => 'وسط',
						'icon'  => 'eicon-h-align-center',
					),
					'flex-end'   => array(
						'title' => 'چپ',
						'icon'  => 'eicon-h-align-left',
					),
				),
				'selectors' => array(
					'{{WRAPPER}} .elementor-widget-container' => 'display:flex; justify-content: {{VALUE}};',
				),
			)
		);

		$this->end_controls_section();
	}

	protected function render() {
		WBL_Frontend::enqueue();

		$settings   = $this->get_settings_for_display();
		$show_title = ( 'yes' === ( $settings['show_title'] ?? '' ) );
		$redirect   = '';
		if ( ! empty( $settings['redirect']['url'] ) ) {
			$redirect = esc_url_raw( $settings['redirect']['url'] );
		}

		$atts = array(
			'redirect'   => $redirect,
			'show_title' => $show_title ? '1' : '0',
		);
		if ( $show_title && ! empty( $settings['title'] ) ) {
			$atts['title'] = $settings['title'];
		}
		if ( $show_title && ! empty( $settings['subtitle'] ) ) {
			$atts['subtitle'] = $settings['subtitle'];
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo WBL_Frontend::shortcode( $atts );
	}
}
