<?php
/**
 * Admin UI.
 *
 * @package Webakery
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings page and order details.
 */
class Webakery_Admin {

	/**
	 * Hook registrations.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'order_meta_box' ) );
		add_filter( 'manage_wbk_order_posts_columns', array( __CLASS__, 'order_columns' ) );
		add_action( 'manage_wbk_order_posts_custom_column', array( __CLASS__, 'order_column_content' ), 10, 2 );
	}

	/**
	 * Admin submenu.
	 */
	public static function menu() {
		add_submenu_page(
			'edit.php?post_type=wbk_product',
			__( 'تنظیمات Webakery', 'webakery' ),
			__( 'تنظیمات', 'webakery' ),
			'manage_options',
			'webakery-settings',
			array( __CLASS__, 'render_settings_page' )
		);
	}

	/**
	 * Admin assets.
	 *
	 * @param string $hook Current admin page.
	 */
	public static function assets( $hook ) {
		if ( false === strpos( $hook, 'webakery-settings' ) && 'wbk_product' !== get_post_type() && 'wbk_order' !== get_post_type() ) {
			return;
		}

		wp_enqueue_style(
			'webakery-admin',
			WEBAKERY_URL . 'admin/css/admin.css',
			array(),
			WEBAKERY_VERSION
		);
	}

	/**
	 * Settings page markup.
	 */
	public static function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings = Webakery_Settings::get();
		?>
		<div class="wrap wbk-admin" dir="rtl">
			<h1><?php esc_html_e( 'تنظیمات Webakery', 'webakery' ); ?></h1>
			<p class="wbk-admin__lead"><?php esc_html_e( 'اطلاعات فروشگاه، ساعات کاری و ایمیل دریافت سفارش را اینجا تنظیم کنید.', 'webakery' ); ?></p>

			<form method="post" action="options.php" class="wbk-admin__form">
				<?php settings_fields( 'webakery_settings_group' ); ?>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="wbk_store_name"><?php esc_html_e( 'نام فروشگاه', 'webakery' ); ?></label></th>
						<td><input type="text" class="regular-text" id="wbk_store_name" name="webakery_settings[store_name]" value="<?php echo esc_attr( $settings['store_name'] ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="wbk_intro"><?php esc_html_e( 'معرفی کوتاه', 'webakery' ); ?></label></th>
						<td><textarea class="large-text" rows="2" id="wbk_intro" name="webakery_settings[intro]"><?php echo esc_textarea( $settings['intro'] ); ?></textarea></td>
					</tr>
					<tr>
						<th scope="row"><label for="wbk_phone"><?php esc_html_e( 'تلفن', 'webakery' ); ?></label></th>
						<td><input type="text" class="regular-text" id="wbk_phone" name="webakery_settings[phone]" value="<?php echo esc_attr( $settings['phone'] ); ?>" dir="ltr" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="wbk_whatsapp"><?php esc_html_e( 'واتساپ (با کد کشور)', 'webakery' ); ?></label></th>
						<td><input type="text" class="regular-text" id="wbk_whatsapp" name="webakery_settings[whatsapp]" value="<?php echo esc_attr( $settings['whatsapp'] ); ?>" placeholder="98912..." dir="ltr" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="wbk_address"><?php esc_html_e( 'آدرس', 'webakery' ); ?></label></th>
						<td><textarea class="large-text" rows="2" id="wbk_address" name="webakery_settings[address]"><?php echo esc_textarea( $settings['address'] ); ?></textarea></td>
					</tr>
					<tr>
						<th scope="row"><label for="wbk_currency"><?php esc_html_e( 'واحد پول', 'webakery' ); ?></label></th>
						<td><input type="text" class="regular-text" id="wbk_currency" name="webakery_settings[currency]" value="<?php echo esc_attr( $settings['currency'] ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="wbk_hours_weekday"><?php esc_html_e( 'ساعات شنبه تا پنج‌شنبه', 'webakery' ); ?></label></th>
						<td><input type="text" class="regular-text" id="wbk_hours_weekday" name="webakery_settings[hours_weekday]" value="<?php echo esc_attr( $settings['hours_weekday'] ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="wbk_hours_friday"><?php esc_html_e( 'ساعات جمعه', 'webakery' ); ?></label></th>
						<td><input type="text" class="regular-text" id="wbk_hours_friday" name="webakery_settings[hours_friday]" value="<?php echo esc_attr( $settings['hours_friday'] ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="wbk_order_email"><?php esc_html_e( 'ایمیل دریافت سفارش', 'webakery' ); ?></label></th>
						<td><input type="email" class="regular-text" id="wbk_order_email" name="webakery_settings[order_email]" value="<?php echo esc_attr( $settings['order_email'] ); ?>" dir="ltr" /></td>
					</tr>
				</table>

				<?php submit_button( __( 'ذخیره تنظیمات', 'webakery' ) ); ?>
			</form>

			<div class="wbk-admin__shortcodes">
				<h2><?php esc_html_e( 'شورت‌کدها', 'webakery' ); ?></h2>
				<ul>
					<li><code>[webakery_products]</code> — <?php esc_html_e( 'نمایش محصولات', 'webakery' ); ?></li>
					<li><code>[webakery_products featured="1" limit="6"]</code> — <?php esc_html_e( 'محصولات ویژه', 'webakery' ); ?></li>
					<li><code>[webakery_hours]</code> — <?php esc_html_e( 'ساعات کاری', 'webakery' ); ?></li>
					<li><code>[webakery_info]</code> — <?php esc_html_e( 'اطلاعات فروشگاه', 'webakery' ); ?></li>
					<li><code>[webakery_order]</code> — <?php esc_html_e( 'فرم سفارش', 'webakery' ); ?></li>
				</ul>
			</div>
		</div>
		<?php
	}

	/**
	 * Order detail meta box.
	 */
	public static function order_meta_box() {
		add_meta_box(
			'webakery_order_details',
			__( 'جزئیات سفارش', 'webakery' ),
			array( __CLASS__, 'render_order_meta' ),
			'wbk_order',
			'normal',
			'high'
		);
	}

	/**
	 * Render order details (read-only).
	 *
	 * @param WP_Post $post Order post.
	 */
	public static function render_order_meta( $post ) {
		$fields = array(
			'_wbk_customer_name'  => __( 'نام مشتری', 'webakery' ),
			'_wbk_customer_phone' => __( 'تلفن', 'webakery' ),
			'_wbk_product'        => __( 'محصول', 'webakery' ),
			'_wbk_qty'            => __( 'تعداد', 'webakery' ),
			'_wbk_message'        => __( 'پیام', 'webakery' ),
		);

		echo '<table class="widefat striped"><tbody>';
		foreach ( $fields as $key => $label ) {
			$value = get_post_meta( $post->ID, $key, true );
			echo '<tr><th style="width:160px;">' . esc_html( $label ) . '</th><td>' . esc_html( $value ? $value : '—' ) . '</td></tr>';
		}
		echo '</tbody></table>';
	}

	/**
	 * Order list columns.
	 *
	 * @param array $columns Columns.
	 * @return array
	 */
	public static function order_columns( $columns ) {
		return array(
			'cb'         => $columns['cb'],
			'title'      => __( 'عنوان', 'webakery' ),
			'wbk_phone'  => __( 'تلفن', 'webakery' ),
			'wbk_product'=> __( 'محصول', 'webakery' ),
			'wbk_qty'    => __( 'تعداد', 'webakery' ),
			'date'       => __( 'تاریخ', 'webakery' ),
		);
	}

	/**
	 * Order column values.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Post ID.
	 */
	public static function order_column_content( $column, $post_id ) {
		$map = array(
			'wbk_phone'   => '_wbk_customer_phone',
			'wbk_product' => '_wbk_product',
			'wbk_qty'     => '_wbk_qty',
		);

		if ( isset( $map[ $column ] ) ) {
			$value = get_post_meta( $post_id, $map[ $column ], true );
			echo esc_html( $value ? $value : '—' );
		}
	}
}
