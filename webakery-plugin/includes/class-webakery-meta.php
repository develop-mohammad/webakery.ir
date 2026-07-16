<?php
/**
 * Product meta boxes and helpers.
 *
 * @package Webakery
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles product custom fields.
 */
class Webakery_Meta {

	/**
	 * Hook registrations.
	 */
	public static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_boxes' ) );
		add_action( 'save_post_wbk_product', array( __CLASS__, 'save' ), 10, 2 );
		add_filter( 'manage_wbk_product_posts_columns', array( __CLASS__, 'columns' ) );
		add_action( 'manage_wbk_product_posts_custom_column', array( __CLASS__, 'column_content' ), 10, 2 );
	}

	/**
	 * Register meta box.
	 */
	public static function add_meta_boxes() {
		add_meta_box(
			'webakery_product_details',
			__( 'جزئیات محصول', 'webakery' ),
			array( __CLASS__, 'render' ),
			'wbk_product',
			'side',
			'high'
		);
	}

	/**
	 * Render meta box fields.
	 *
	 * @param WP_Post $post Current post.
	 */
	public static function render( $post ) {
		wp_nonce_field( 'webakery_save_product', 'webakery_product_nonce' );

		$price       = get_post_meta( $post->ID, '_wbk_price', true );
		$unit        = get_post_meta( $post->ID, '_wbk_unit', true );
		$available   = get_post_meta( $post->ID, '_wbk_available', true );
		$featured    = get_post_meta( $post->ID, '_wbk_featured', true );
		$prep_time   = get_post_meta( $post->ID, '_wbk_prep_time', true );

		if ( '' === $available ) {
			$available = '1';
		}
		?>
		<p>
			<label for="wbk_price"><strong><?php esc_html_e( 'قیمت', 'webakery' ); ?></strong></label><br />
			<input type="number" class="widefat" id="wbk_price" name="wbk_price" value="<?php echo esc_attr( $price ); ?>" min="0" step="1000" />
		</p>
		<p>
			<label for="wbk_unit"><strong><?php esc_html_e( 'واحد', 'webakery' ); ?></strong></label><br />
			<input type="text" class="widefat" id="wbk_unit" name="wbk_unit" value="<?php echo esc_attr( $unit ); ?>" placeholder="<?php esc_attr_e( 'عدد / کیلو / بسته', 'webakery' ); ?>" />
		</p>
		<p>
			<label for="wbk_prep_time"><strong><?php esc_html_e( 'زمان آماده‌سازی', 'webakery' ); ?></strong></label><br />
			<input type="text" class="widefat" id="wbk_prep_time" name="wbk_prep_time" value="<?php echo esc_attr( $prep_time ); ?>" placeholder="<?php esc_attr_e( 'مثلاً ۳۰ دقیقه', 'webakery' ); ?>" />
		</p>
		<p>
			<label>
				<input type="checkbox" name="wbk_available" value="1" <?php checked( $available, '1' ); ?> />
				<?php esc_html_e( 'موجود است', 'webakery' ); ?>
			</label>
		</p>
		<p>
			<label>
				<input type="checkbox" name="wbk_featured" value="1" <?php checked( $featured, '1' ); ?> />
				<?php esc_html_e( 'محصول ویژه', 'webakery' ); ?>
			</label>
		</p>
		<?php
	}

	/**
	 * Save meta fields.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 */
	public static function save( $post_id, $post ) {
		if ( ! isset( $_POST['webakery_product_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['webakery_product_nonce'] ) ), 'webakery_save_product' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$price = isset( $_POST['wbk_price'] ) ? absint( $_POST['wbk_price'] ) : 0;
		$unit  = isset( $_POST['wbk_unit'] ) ? sanitize_text_field( wp_unslash( $_POST['wbk_unit'] ) ) : '';
		$prep  = isset( $_POST['wbk_prep_time'] ) ? sanitize_text_field( wp_unslash( $_POST['wbk_prep_time'] ) ) : '';

		update_post_meta( $post_id, '_wbk_price', $price );
		update_post_meta( $post_id, '_wbk_unit', $unit );
		update_post_meta( $post_id, '_wbk_prep_time', $prep );
		update_post_meta( $post_id, '_wbk_available', isset( $_POST['wbk_available'] ) ? '1' : '0' );
		update_post_meta( $post_id, '_wbk_featured', isset( $_POST['wbk_featured'] ) ? '1' : '0' );
	}

	/**
	 * Admin list columns.
	 *
	 * @param array $columns Columns.
	 * @return array
	 */
	public static function columns( $columns ) {
		$new = array();
		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'title' === $key ) {
				$new['wbk_price']     = __( 'قیمت', 'webakery' );
				$new['wbk_available'] = __( 'موجودی', 'webakery' );
			}
		}
		return $new;
	}

	/**
	 * Admin list column content.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Post ID.
	 */
	public static function column_content( $column, $post_id ) {
		if ( 'wbk_price' === $column ) {
			echo esc_html( self::format_price( get_post_meta( $post_id, '_wbk_price', true ) ) );
		}

		if ( 'wbk_available' === $column ) {
			$available = get_post_meta( $post_id, '_wbk_available', true );
			echo '0' === (string) $available ? esc_html__( 'ناموجود', 'webakery' ) : esc_html__( 'موجود', 'webakery' );
		}
	}

	/**
	 * Format price with currency.
	 *
	 * @param mixed $price Raw price.
	 * @return string
	 */
	public static function format_price( $price ) {
		$settings = Webakery_Settings::get();
		$currency = isset( $settings['currency'] ) ? $settings['currency'] : 'تومان';
		$price    = absint( $price );

		if ( $price <= 0 ) {
			return __( 'تماس بگیرید', 'webakery' );
		}

		return number_format_i18n( $price ) . ' ' . $currency;
	}

	/**
	 * Get product meta bundle.
	 *
	 * @param int $post_id Post ID.
	 * @return array
	 */
	public static function get_product_meta( $post_id ) {
		return array(
			'price'     => get_post_meta( $post_id, '_wbk_price', true ),
			'unit'      => get_post_meta( $post_id, '_wbk_unit', true ),
			'available' => get_post_meta( $post_id, '_wbk_available', true ),
			'featured'  => get_post_meta( $post_id, '_wbk_featured', true ),
			'prep_time' => get_post_meta( $post_id, '_wbk_prep_time', true ),
		);
	}
}
