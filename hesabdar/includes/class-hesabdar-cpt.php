<?php
/**
 * Custom post types and taxonomies.
 *
 * @package Hesabdar
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers bakery product CPT and taxonomy.
 */
class Hesabdar_CPT {

	/**
	 * Hook registrations.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
	}

	/**
	 * Register CPT and taxonomy.
	 */
	public static function register() {
		$labels = array(
			'name'               => __( 'محصولات', 'hesabdar' ),
			'singular_name'      => __( 'محصول', 'hesabdar' ),
			'menu_name'          => __( 'حسابدار', 'hesabdar' ),
			'add_new'            => __( 'افزودن محصول', 'hesabdar' ),
			'add_new_item'       => __( 'افزودن محصول جدید', 'hesabdar' ),
			'edit_item'          => __( 'ویرایش محصول', 'hesabdar' ),
			'new_item'           => __( 'محصول جدید', 'hesabdar' ),
			'view_item'          => __( 'مشاهده محصول', 'hesabdar' ),
			'search_items'       => __( 'جستجوی محصولات', 'hesabdar' ),
			'not_found'          => __( 'محصولی یافت نشد.', 'hesabdar' ),
			'not_found_in_trash' => __( 'در زباله‌دان محصولی نیست.', 'hesabdar' ),
			'all_items'          => __( 'همه محصولات', 'hesabdar' ),
		);

		register_post_type(
			'hsb_product',
			array(
				'labels'              => $labels,
				'public'              => true,
				'has_archive'         => true,
				'rewrite'             => array( 'slug' => 'products' ),
				'menu_icon'           => 'dashicons-media-spreadsheet',
				'supports'            => array( 'title', 'editor', 'thumbnail', 'excerpt' ),
				'show_in_rest'        => true,
				'exclude_from_search' => false,
			)
		);

		register_taxonomy(
			'hsb_product_cat',
			'hsb_product',
			array(
				'labels'       => array(
					'name'          => __( 'دسته‌بندی محصولات', 'hesabdar' ),
					'singular_name' => __( 'دسته‌بندی', 'hesabdar' ),
					'search_items'  => __( 'جستجوی دسته‌ها', 'hesabdar' ),
					'all_items'     => __( 'همه دسته‌ها', 'hesabdar' ),
					'edit_item'     => __( 'ویرایش دسته', 'hesabdar' ),
					'update_item'   => __( 'به‌روزرسانی دسته', 'hesabdar' ),
					'add_new_item'  => __( 'افزودن دسته جدید', 'hesabdar' ),
					'new_item_name' => __( 'نام دسته جدید', 'hesabdar' ),
					'menu_name'     => __( 'دسته‌بندی‌ها', 'hesabdar' ),
				),
				'hierarchical' => true,
				'public'       => true,
				'rewrite'      => array( 'slug' => 'product-category' ),
				'show_in_rest' => true,
			)
		);
	}
}
