<?php
namespace WCCP;

defined( 'ABSPATH' ) || exit;

class OnlineProducts {

	/** @var self|null */
	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'init', array( __CLASS__, 'register_cpt' ) );
		add_shortcode( 'baget_pay', array( $this, 'shortcode_pay' ) );
		add_action( 'template_redirect', array( $this, 'maybe_render_link' ) );
	}

	public static function register_cpt() {
		register_post_type(
			'wccp_product',
			array(
				'labels'       => array(
					'name'          => 'محصولات آنلاین',
					'singular_name' => 'محصول آنلاین',
					'add_new_item'  => 'افزودن محصول آنلاین',
					'edit_item'     => 'ویرایش محصول آنلاین',
				),
				'public'       => true,
				'show_ui'      => true,
				'show_in_menu' => false,
				'supports'     => array( 'title', 'editor', 'thumbnail' ),
				'has_archive'  => false,
				'rewrite'      => array( 'slug' => 'pay' ),
			)
		);
	}

	/** @return string[] */
	public static function product_active_fields( $product_id ) {
		$active = get_post_meta( $product_id, '_wccp_active_fields', true );
		if ( ! is_array( $active ) || empty( $active ) ) {
			return Fields::get_active_keys();
		}
		return array_values( array_map( 'strval', $active ) );
	}

	public function shortcode_pay( $atts ) {
		$atts = shortcode_atts( array( 'id' => 0 ), $atts );
		$id   = (int) $atts['id'];
		if ( ! $id ) {
			return '';
		}
		return $this->render_product_form( $id );
	}

	public function maybe_render_link() {
		if ( ! is_singular( 'wccp_product' ) ) {
			return;
		}
		$post = get_queried_object();
		if ( ! $post ) {
			return;
		}
		$product_id = (int) $post->ID;
		$tpl_key    = Templates::product_template_key( $product_id );
		$css        = Templates::css_for( $tpl_key );

		status_header( 200 );
		nocache_headers();
		echo '<!DOCTYPE html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
		echo '<title>' . esc_html( get_the_title( $post ) ) . '</title>';
		echo '<link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet">';
		echo '<style>' . $css . '</style>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '</head><body class="wccp-tpl-' . esc_attr( $tpl_key ) . '"><div class="card">';
		echo '<h1>' . esc_html( get_the_title( $post ) ) . '</h1>';
		echo wpautop( wp_kses_post( $post->post_content ) );
		echo $this->render_product_form( $product_id ); // phpcs:ignore
		echo '</div></body></html>';
		exit;
	}

	private function render_product_form( $product_id ) {
		$defs   = CustomFields::merged_with_defaults();
		$active = self::product_active_fields( $product_id );
		$price  = (int) get_post_meta( $product_id, '_wccp_price', true );
		ob_start();
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="wccp-pay-form">';
		echo '<input type="hidden" name="action" value="wccp_pay_link" />';
		echo '<input type="hidden" name="product_id" value="' . esc_attr( (string) $product_id ) . '" />';
		wp_nonce_field( 'wccp_pay_' . $product_id );
		foreach ( $active as $key ) {
			if ( empty( $defs[ $key ] ) ) {
				continue;
			}
			Fields::render_standalone_field( $key, $defs[ $key ] );
		}
		if ( $price > 0 ) {
			echo '<p class="wccp-price">مبلغ: ' . esc_html( number_format_i18n( $price ) ) . ' تومان</p>';
		}
		echo '<button type="submit" class="wccp-pay-btn">ادامه و پرداخت</button></form>';
		return ob_get_clean();
	}
}
