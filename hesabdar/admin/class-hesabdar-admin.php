<?php
/**
 * Admin UI.
 *
 * @package Hesabdar
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings page and order details.
 */
class Hesabdar_Admin {

	/**
	 * Hook registrations.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_init', array( __CLASS__, 'maybe_export_orders' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'order_meta_box' ) );
		add_filter( 'manage_hsb_order_posts_columns', array( __CLASS__, 'order_columns' ) );
		add_action( 'manage_hsb_order_posts_custom_column', array( __CLASS__, 'order_column_content' ), 10, 2 );
		add_action( 'restrict_manage_posts', array( __CLASS__, 'orders_export_button' ) );
		add_filter( 'post_row_actions', array( __CLASS__, 'order_row_actions' ), 10, 2 );
	}

	/**
	 * Admin submenu.
	 */
	public static function menu() {
		add_submenu_page(
			'edit.php?post_type=hsb_product',
			__( 'تنظیمات حسابدار', 'hesabdar' ),
			__( 'تنظیمات', 'hesabdar' ),
			'manage_options',
			'hesabdar-settings',
			array( __CLASS__, 'render_settings_page' )
		);
	}

	/**
	 * Show export button on orders list.
	 *
	 * @param string $post_type Current post type.
	 */
	public static function orders_export_button( $post_type ) {
		if ( 'hsb_order' !== $post_type || ! current_user_can( 'edit_posts' ) ) {
			return;
		}

		$url = wp_nonce_url(
			admin_url( 'edit.php?post_type=hsb_order&hesabdar_export_orders=1' ),
			'hesabdar_export_orders'
		);
		?>
		<a class="button button-secondary" href="<?php echo esc_url( $url ); ?>">
			<?php esc_html_e( 'دانلود سفارش‌ها روی لپ‌تاپ (CSV)', 'hesabdar' ); ?>
		</a>
		<?php
	}

	/**
	 * Stream orders CSV for local laptop save.
	 */
	public static function maybe_export_orders() {
		if ( ! isset( $_GET['hesabdar_export_orders'] ) || '1' !== $_GET['hesabdar_export_orders'] ) {
			return;
		}

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'اجازه دسترسی ندارید.', 'hesabdar' ) );
		}

		check_admin_referer( 'hesabdar_export_orders' );

		$orders = get_posts(
			array(
				'post_type'      => 'hsb_order',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);

		$filename = 'hesabdar-orders-' . gmdate( 'Y-m-d-His' ) . '.csv';

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=' . $filename );

		$output = fopen( 'php://output', 'w' );
		fprintf( $output, chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ) );

		fputcsv(
			$output,
			array(
				'ID',
				__( 'تاریخ', 'hesabdar' ),
				__( 'نام', 'hesabdar' ),
				__( 'تلفن', 'hesabdar' ),
				__( 'محصول', 'hesabdar' ),
				__( 'تعداد', 'hesabdar' ),
				__( 'پیام', 'hesabdar' ),
			)
		);

		foreach ( $orders as $order ) {
			fputcsv(
				$output,
				array(
					$order->ID,
					get_the_date( 'Y-m-d H:i', $order ),
					get_post_meta( $order->ID, '_hsb_customer_name', true ),
					get_post_meta( $order->ID, '_hsb_customer_phone', true ),
					get_post_meta( $order->ID, '_hsb_product', true ),
					get_post_meta( $order->ID, '_hsb_qty', true ),
					get_post_meta( $order->ID, '_hsb_message', true ),
				)
			);
		}

		fclose( $output );
		exit;
	}

	/**
	 * Admin assets.
	 *
	 * @param string $hook Current admin page.
	 */
	public static function assets( $hook ) {
		if ( false === strpos( $hook, 'hesabdar-settings' ) && 'hsb_product' !== get_post_type() && 'hsb_order' !== get_post_type() ) {
			return;
		}

		wp_enqueue_style(
			'hesabdar-admin',
			HESABDAR_URL . 'admin/css/admin.css',
			array(),
			HESABDAR_VERSION
		);
	}

	/**
	 * Settings page markup.
	 */
	public static function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings = Hesabdar_Settings::get();
		?>
		<div class="wrap hsb-admin" dir="rtl">
			<h1><?php esc_html_e( 'تنظیمات حسابدار', 'hesabdar' ); ?></h1>
			<p class="hsb-admin__lead"><?php esc_html_e( 'اطلاعات فروشگاه، ساعات کاری و ایمیل دریافت سفارش را اینجا تنظیم کنید.', 'hesabdar' ); ?></p>

			<form method="post" action="options.php" class="hsb-admin__form">
				<?php settings_fields( 'hesabdar_settings_group' ); ?>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="hsb_store_name"><?php esc_html_e( 'نام فروشگاه', 'hesabdar' ); ?></label></th>
						<td><input type="text" class="regular-text" id="hsb_store_name" name="hesabdar_settings[store_name]" value="<?php echo esc_attr( $settings['store_name'] ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="hsb_intro"><?php esc_html_e( 'معرفی کوتاه', 'hesabdar' ); ?></label></th>
						<td><textarea class="large-text" rows="2" id="hsb_intro" name="hesabdar_settings[intro]"><?php echo esc_textarea( $settings['intro'] ); ?></textarea></td>
					</tr>
					<tr>
						<th scope="row"><label for="hsb_phone"><?php esc_html_e( 'تلفن', 'hesabdar' ); ?></label></th>
						<td><input type="text" class="regular-text" id="hsb_phone" name="hesabdar_settings[phone]" value="<?php echo esc_attr( $settings['phone'] ); ?>" dir="ltr" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="hsb_whatsapp"><?php esc_html_e( 'واتساپ (با کد کشور)', 'hesabdar' ); ?></label></th>
						<td><input type="text" class="regular-text" id="hsb_whatsapp" name="hesabdar_settings[whatsapp]" value="<?php echo esc_attr( $settings['whatsapp'] ); ?>" placeholder="98912..." dir="ltr" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="hsb_address"><?php esc_html_e( 'آدرس', 'hesabdar' ); ?></label></th>
						<td><textarea class="large-text" rows="2" id="hsb_address" name="hesabdar_settings[address]"><?php echo esc_textarea( $settings['address'] ); ?></textarea></td>
					</tr>
					<tr>
						<th scope="row"><label for="hsb_currency"><?php esc_html_e( 'واحد پول', 'hesabdar' ); ?></label></th>
						<td><input type="text" class="regular-text" id="hsb_currency" name="hesabdar_settings[currency]" value="<?php echo esc_attr( $settings['currency'] ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="hsb_hours_weekday"><?php esc_html_e( 'ساعات شنبه تا پنج‌شنبه', 'hesabdar' ); ?></label></th>
						<td><input type="text" class="regular-text" id="hsb_hours_weekday" name="hesabdar_settings[hours_weekday]" value="<?php echo esc_attr( $settings['hours_weekday'] ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="hsb_hours_friday"><?php esc_html_e( 'ساعات جمعه', 'hesabdar' ); ?></label></th>
						<td><input type="text" class="regular-text" id="hsb_hours_friday" name="hesabdar_settings[hours_friday]" value="<?php echo esc_attr( $settings['hours_friday'] ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="hsb_order_email"><?php esc_html_e( 'ایمیل دریافت سفارش', 'hesabdar' ); ?></label></th>
						<td><input type="email" class="regular-text" id="hsb_order_email" name="hesabdar_settings[order_email]" value="<?php echo esc_attr( $settings['order_email'] ); ?>" dir="ltr" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="hsb_invoice_prefix"><?php esc_html_e( 'پیشوند شماره فاکتور', 'hesabdar' ); ?></label></th>
						<td><input type="text" class="regular-text" id="hsb_invoice_prefix" name="hesabdar_settings[invoice_prefix]" value="<?php echo esc_attr( $settings['invoice_prefix'] ); ?>" placeholder="HSB" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="hsb_invoice_note"><?php esc_html_e( 'یادداشت پایین فاکتور', 'hesabdar' ); ?></label></th>
						<td><textarea class="large-text" rows="2" id="hsb_invoice_note" name="hesabdar_settings[invoice_note]"><?php echo esc_textarea( $settings['invoice_note'] ); ?></textarea></td>
					</tr>
				</table>

				<?php submit_button( __( 'ذخیره تنظیمات', 'hesabdar' ) ); ?>
			</form>

			<div class="hsb-admin__shortcodes">
				<h2><?php esc_html_e( 'شورت‌کدها', 'hesabdar' ); ?></h2>
				<ul>
					<li><code>[hesabdar_products]</code> — <?php esc_html_e( 'نمایش محصولات', 'hesabdar' ); ?></li>
					<li><code>[hesabdar_products featured="1" limit="6"]</code> — <?php esc_html_e( 'محصولات ویژه', 'hesabdar' ); ?></li>
					<li><code>[hesabdar_hours]</code> — <?php esc_html_e( 'ساعات کاری', 'hesabdar' ); ?></li>
					<li><code>[hesabdar_info]</code> — <?php esc_html_e( 'اطلاعات فروشگاه', 'hesabdar' ); ?></li>
					<li><code>[hesabdar_order]</code> — <?php esc_html_e( 'فرم سفارش', 'hesabdar' ); ?></li>
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
			'hesabdar_order_details',
			__( 'جزئیات سفارش', 'hesabdar' ),
			array( __CLASS__, 'render_order_meta' ),
			'hsb_order',
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
			'_hsb_customer_name'  => __( 'نام مشتری', 'hesabdar' ),
			'_hsb_customer_phone' => __( 'تلفن', 'hesabdar' ),
			'_hsb_product'        => __( 'محصول', 'hesabdar' ),
			'_hsb_qty'            => __( 'تعداد', 'hesabdar' ),
			'_hsb_message'        => __( 'پیام', 'hesabdar' ),
		);

		$invoice = Hesabdar_Invoice::get_invoice_data( $post->ID );

		echo '<table class="widefat striped"><tbody>';
		foreach ( $fields as $key => $label ) {
			$value = get_post_meta( $post->ID, $key, true );
			echo '<tr><th style="width:160px;">' . esc_html( $label ) . '</th><td>' . esc_html( $value ? $value : '—' ) . '</td></tr>';
		}
		echo '<tr><th>' . esc_html__( 'مبلغ فاکتور', 'hesabdar' ) . '</th><td>' . esc_html( Hesabdar_Invoice::format_money( $invoice['total'], $invoice['currency'] ) ) . '</td></tr>';
		echo '</tbody></table>';

		$view_url     = Hesabdar_Invoice::get_url( $post->ID, 'view' );
		$download_url = Hesabdar_Invoice::get_url( $post->ID, 'download' );
		?>
		<p class="hsb-order-invoice-actions">
			<a class="button button-primary" href="<?php echo esc_url( $view_url ); ?>" target="_blank" rel="noopener noreferrer">
				<?php esc_html_e( 'مشاهده فاکتور', 'hesabdar' ); ?>
			</a>
			<a class="button" href="<?php echo esc_url( $download_url ); ?>">
				<?php esc_html_e( 'دانلود فاکتور', 'hesabdar' ); ?>
			</a>
		</p>
		<?php
	}

	/**
	 * Order list columns.
	 *
	 * @param array $columns Columns.
	 * @return array
	 */
	public static function order_columns( $columns ) {
		return array(
			'cb'          => $columns['cb'],
			'title'       => __( 'عنوان', 'hesabdar' ),
			'hsb_phone'   => __( 'تلفن', 'hesabdar' ),
			'hsb_product' => __( 'محصول', 'hesabdar' ),
			'hsb_qty'     => __( 'تعداد', 'hesabdar' ),
			'hsb_invoice' => __( 'فاکتور', 'hesabdar' ),
			'date'        => __( 'تاریخ', 'hesabdar' ),
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
			'hsb_phone'   => '_hsb_customer_phone',
			'hsb_product' => '_hsb_product',
			'hsb_qty'     => '_hsb_qty',
		);

		if ( isset( $map[ $column ] ) ) {
			$value = get_post_meta( $post_id, $map[ $column ], true );
			echo esc_html( $value ? $value : '—' );
			return;
		}

		if ( 'hsb_invoice' === $column ) {
			$view_url     = Hesabdar_Invoice::get_url( $post_id, 'view' );
			$download_url = Hesabdar_Invoice::get_url( $post_id, 'download' );
			?>
			<span class="hsb-invoice-links">
				<a href="<?php echo esc_url( $view_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'مشاهده', 'hesabdar' ); ?></a>
				<span aria-hidden="true"> | </span>
				<a href="<?php echo esc_url( $download_url ); ?>"><?php esc_html_e( 'دانلود', 'hesabdar' ); ?></a>
			</span>
			<?php
		}
	}

	/**
	 * Row actions for invoice shortcuts.
	 *
	 * @param array   $actions Actions.
	 * @param WP_Post $post    Post.
	 * @return array
	 */
	public static function order_row_actions( $actions, $post ) {
		if ( 'hsb_order' !== $post->post_type || ! current_user_can( 'edit_post', $post->ID ) ) {
			return $actions;
		}

		$actions['hesabdar_view_invoice'] = sprintf(
			'<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
			esc_url( Hesabdar_Invoice::get_url( $post->ID, 'view' ) ),
			esc_html__( 'مشاهده فاکتور', 'hesabdar' )
		);
		$actions['hesabdar_download_invoice'] = sprintf(
			'<a href="%s">%s</a>',
			esc_url( Hesabdar_Invoice::get_url( $post->ID, 'download' ) ),
			esc_html__( 'دانلود فاکتور', 'hesabdar' )
		);

		return $actions;
	}
}
