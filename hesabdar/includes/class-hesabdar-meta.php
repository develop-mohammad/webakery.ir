<?php
/**
 * Product meta boxes and helpers.
 *
 * @package Hesabdar
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles product custom fields.
 */
class Hesabdar_Meta {

	/**
	 * Hook registrations.
	 */
	public static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_boxes' ) );
		add_action( 'save_post_hsb_product', array( __CLASS__, 'save' ), 10, 2 );
		add_filter( 'manage_hsb_product_posts_columns', array( __CLASS__, 'columns' ) );
		add_action( 'manage_hsb_product_posts_custom_column', array( __CLASS__, 'column_content' ), 10, 2 );
	}

	/**
	 * Register meta box.
	 */
	public static function add_meta_boxes() {
		add_meta_box(
			'hesabdar_product_details',
			__( 'جزئیات محصول', 'hesabdar' ),
			array( __CLASS__, 'render' ),
			'hsb_product',
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
		wp_nonce_field( 'hesabdar_save_product', 'hesabdar_product_nonce' );

		$price       = get_post_meta( $post->ID, '_hsb_price', true );
		$unit        = get_post_meta( $post->ID, '_hsb_unit', true );
		$available   = get_post_meta( $post->ID, '_hsb_available', true );
		$featured    = get_post_meta( $post->ID, '_hsb_featured', true );
		$prep_time   = get_post_meta( $post->ID, '_hsb_prep_time', true );

		if ( '' === $available ) {
			$available = '1';
		}
		?>
		<p>
			<label for="hsb_price"><strong><?php esc_html_e( 'قیمت', 'hesabdar' ); ?></strong></label><br />
			<input type="number" class="widefat" id="hsb_price" name="hsb_price" value="<?php echo esc_attr( $price ); ?>" min="0" step="1000" />
		</p>
		<p>
			<label for="hsb_unit"><strong><?php esc_html_e( 'واحد', 'hesabdar' ); ?></strong></label><br />
			<input type="text" class="widefat" id="hsb_unit" name="hsb_unit" value="<?php echo esc_attr( $unit ); ?>" placeholder="<?php esc_attr_e( 'عدد / کیلو / بسته', 'hesabdar' ); ?>" />
		</p>
		<p>
			<label for="hsb_prep_time"><strong><?php esc_html_e( 'زمان آماده‌سازی', 'hesabdar' ); ?></strong></label><br />
			<input type="text" class="widefat" id="hsb_prep_time" name="hsb_prep_time" value="<?php echo esc_attr( $prep_time ); ?>" placeholder="<?php esc_attr_e( 'مثلاً ۳۰ دقیقه', 'hesabdar' ); ?>" />
		</p>
		<p>
			<label>
				<input type="checkbox" name="hsb_available" value="1" <?php checked( $available, '1' ); ?> />
				<?php esc_html_e( 'موجود است', 'hesabdar' ); ?>
			</label>
		</p>
		<p>
			<label>
				<input type="checkbox" name="hsb_featured" value="1" <?php checked( $featured, '1' ); ?> />
				<?php esc_html_e( 'محصول ویژه', 'hesabdar' ); ?>
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
		if ( ! isset( $_POST['hesabdar_product_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['hesabdar_product_nonce'] ) ), 'hesabdar_save_product' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$price = isset( $_POST['hsb_price'] ) ? absint( $_POST['hsb_price'] ) : 0;
		$unit  = isset( $_POST['hsb_unit'] ) ? sanitize_text_field( wp_unslash( $_POST['hsb_unit'] ) ) : '';
		$prep  = isset( $_POST['hsb_prep_time'] ) ? sanitize_text_field( wp_unslash( $_POST['hsb_prep_time'] ) ) : '';

		update_post_meta( $post_id, '_hsb_price', $price );
		update_post_meta( $post_id, '_hsb_unit', $unit );
		update_post_meta( $post_id, '_hsb_prep_time', $prep );
		update_post_meta( $post_id, '_hsb_available', isset( $_POST['hsb_available'] ) ? '1' : '0' );
		update_post_meta( $post_id, '_hsb_featured', isset( $_POST['hsb_featured'] ) ? '1' : '0' );
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
				$new['hsb_price']     = __( 'قیمت', 'hesabdar' );
				$new['hsb_available'] = __( 'موجودی', 'hesabdar' );
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
		if ( 'hsb_price' === $column ) {
			echo esc_html( self::format_price( get_post_meta( $post_id, '_hsb_price', true ) ) );
		}

		if ( 'hsb_available' === $column ) {
			$available = get_post_meta( $post_id, '_hsb_available', true );
			echo '0' === (string) $available ? esc_html__( 'ناموجود', 'hesabdar' ) : esc_html__( 'موجود', 'hesabdar' );
		}
	}

	/**
	 * Format price with currency.
	 *
	 * @param mixed $price Raw price.
	 * @return string
	 */
	public static function format_price( $price ) {
		$settings = Hesabdar_Settings::get();
		$currency = isset( $settings['currency'] ) ? $settings['currency'] : 'تومان';
		$price    = absint( $price );

		if ( $price <= 0 ) {
			return __( 'تماس بگیرید', 'hesabdar' );
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
			'price'     => get_post_meta( $post_id, '_hsb_price', true ),
			'unit'      => get_post_meta( $post_id, '_hsb_unit', true ),
			'available' => get_post_meta( $post_id, '_hsb_available', true ),
			'featured'  => get_post_meta( $post_id, '_hsb_featured', true ),
			'prep_time' => get_post_meta( $post_id, '_hsb_prep_time', true ),
		);
	}
}
