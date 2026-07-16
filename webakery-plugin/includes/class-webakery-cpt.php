<?php
/**
 * Custom post types and taxonomies.
 *
 * @package Webakery
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers bakery product CPT and taxonomy.
 */
class Webakery_CPT {

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
			'name'               => __( 'محصولات', 'webakery' ),
			'singular_name'      => __( 'محصول', 'webakery' ),
			'menu_name'          => __( 'Webakery', 'webakery' ),
			'add_new'            => __( 'افزودن محصول', 'webakery' ),
			'add_new_item'       => __( 'افزودن محصول جدید', 'webakery' ),
			'edit_item'          => __( 'ویرایش محصول', 'webakery' ),
			'new_item'           => __( 'محصول جدید', 'webakery' ),
			'view_item'          => __( 'مشاهده محصول', 'webakery' ),
			'search_items'       => __( 'جستجوی محصولات', 'webakery' ),
			'not_found'          => __( 'محصولی یافت نشد.', 'webakery' ),
			'not_found_in_trash' => __( 'در زباله‌دان محصولی نیست.', 'webakery' ),
			'all_items'          => __( 'همه محصولات', 'webakery' ),
		);

		register_post_type(
			'wbk_product',
			array(
				'labels'              => $labels,
				'public'              => true,
				'has_archive'         => true,
				'rewrite'             => array( 'slug' => 'products' ),
				'menu_icon'           => 'dashicons-carrot',
				'supports'            => array( 'title', 'editor', 'thumbnail', 'excerpt' ),
				'show_in_rest'        => true,
				'exclude_from_search' => false,
			)
		);

		register_taxonomy(
			'wbk_product_cat',
			'wbk_product',
			array(
				'labels'       => array(
					'name'          => __( 'دسته‌بندی محصولات', 'webakery' ),
					'singular_name' => __( 'دسته‌بندی', 'webakery' ),
					'search_items'  => __( 'جستجوی دسته‌ها', 'webakery' ),
					'all_items'     => __( 'همه دسته‌ها', 'webakery' ),
					'edit_item'     => __( 'ویرایش دسته', 'webakery' ),
					'update_item'   => __( 'به‌روزرسانی دسته', 'webakery' ),
					'add_new_item'  => __( 'افزودن دسته جدید', 'webakery' ),
					'new_item_name' => __( 'نام دسته جدید', 'webakery' ),
					'menu_name'     => __( 'دسته‌بندی‌ها', 'webakery' ),
				),
				'hierarchical' => true,
				'public'       => true,
				'rewrite'      => array( 'slug' => 'product-category' ),
				'show_in_rest' => true,
			)
		);
	}
}
