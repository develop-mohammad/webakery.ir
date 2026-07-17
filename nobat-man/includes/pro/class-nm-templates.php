<?php
defined( 'ABSPATH' ) || exit;

class NM_Templates {

	public static function presets() {
		return array(
			'psychology' => array(
				'label' => 'مشاوره روانشناسی مثبت',
				'fields' => array( 'name', 'phone', 'email', 'city', 'gender', 'category', 'description', 'photo', 'voice' ),
				'questions' => array(
					array( 'اضطراب و استرس', 'شدت مشکل؟', 'select', array( 'کم', 'متوسط', 'زیاد' ) ),
					array( 'رشد فردی', 'هدف جلسه؟', 'textarea', array() ),
				),
			),
			'villa' => array(
				'label' => 'رزرو ویلا / خانه',
				'fields' => array( 'name', 'phone', 'email', 'city', 'description' ),
				'questions' => array(
					array( 'اقامت', 'تعداد نفرات؟', 'text', array() ),
					array( 'اقامت', 'تاریخ ورود/خروج مدنظر؟', 'text', array() ),
				),
			),
			'clinic' => array(
				'label' => 'کلینیک / ویزیت',
				'fields' => array( 'name', 'phone', 'email', 'gender', 'description', 'photo' ),
				'questions' => array(
					array( 'ویزیت', 'علائم اصلی؟', 'textarea', array() ),
				),
			),
		);
	}

	public static function apply( $key ) {
		if ( ! NM_Pro::is_active() ) return NM_Pro::require_pro();
		$presets = self::presets();
		if ( empty( $presets[ $key ] ) ) return new WP_Error( 'tpl', 'قالب یافت نشد' );
		$p = $presets[ $key ];
		foreach ( $p['questions'] as $i => $q ) {
			NM_Questions::save( array(
				'category' => $q[0],
				'question' => $q[1],
				'type'     => $q[2],
				'options'  => $q[3],
				'sort_order' => ( $i + 1 ) * 10,
				'is_required' => 1,
				'is_active' => 1,
			) );
		}
		NM_Settings::update( array( 'business_name' => $p['label'] ) );
		return true;
	}
}
