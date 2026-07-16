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
		add_action( 'admin_init', array( __CLASS__, 'maybe_export_orders' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'order_meta_box' ) );
		add_filter( 'manage_wbk_order_posts_columns', array( __CLASS__, 'order_columns' ) );
		add_action( 'manage_wbk_order_posts_custom_column', array( __CLASS__, 'order_column_content' ), 10, 2 );
		add_action( 'restrict_manage_posts', array( __CLASS__, 'orders_export_button' ) );
		add_filter( 'post_row_actions', array( __CLASS__, 'order_row_actions' ), 10, 2 );
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
	 * Show export button on orders list.
	 *
	 * @param string $post_type Current post type.
	 */
	public static function orders_export_button( $post_type ) {
		if ( 'wbk_order' !== $post_type || ! current_user_can( 'edit_posts' ) ) {
			return;
		}

		$url = wp_nonce_url(
			admin_url( 'edit.php?post_type=wbk_order&webakery_export_orders=1' ),
			'webakery_export_orders'
		);
		?>
		<a class="button button-secondary" href="<?php echo esc_url( $url ); ?>">
			<?php esc_html_e( 'دانلود سفارش‌ها روی لپ‌تاپ (CSV)', 'webakery' ); ?>
		</a>
		<?php
	}

	/**
	 * Stream orders CSV for local laptop save.
	 */
	public static function maybe_export_orders() {
		if ( ! isset( $_GET['webakery_export_orders'] ) || '1' !== $_GET['webakery_export_orders'] ) {
			return;
		}

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'اجازه دسترسی ندارید.', 'webakery' ) );
		}

		check_admin_referer( 'webakery_export_orders' );

		$orders = get_posts(
			array(
				'post_type'      => 'wbk_order',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);

		$filename = 'webakery-orders-' . gmdate( 'Y-m-d-His' ) . '.csv';

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=' . $filename );

		$output = fopen( 'php://output', 'w' );
		fprintf( $output, chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ) );

		fputcsv(
			$output,
			array(
				'ID',
				__( 'تاریخ', 'webakery' ),
				__( 'نام', 'webakery' ),
				__( 'تلفن', 'webakery' ),
				__( 'محصول', 'webakery' ),
				__( 'تعداد', 'webakery' ),
				__( 'پیام', 'webakery' ),
			)
		);

		foreach ( $orders as $order ) {
			fputcsv(
				$output,
				array(
					$order->ID,
					get_the_date( 'Y-m-d H:i', $order ),
					get_post_meta( $order->ID, '_wbk_customer_name', true ),
					get_post_meta( $order->ID, '_wbk_customer_phone', true ),
					get_post_meta( $order->ID, '_wbk_product', true ),
					get_post_meta( $order->ID, '_wbk_qty', true ),
					get_post_meta( $order->ID, '_wbk_message', true ),
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
					<tr>
						<th scope="row"><label for="wbk_invoice_prefix"><?php esc_html_e( 'پیشوند شماره فاکتور', 'webakery' ); ?></label></th>
						<td><input type="text" class="regular-text" id="wbk_invoice_prefix" name="webakery_settings[invoice_prefix]" value="<?php echo esc_attr( $settings['invoice_prefix'] ); ?>" placeholder="WBK" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="wbk_invoice_note"><?php esc_html_e( 'یادداشت پایین فاکتور', 'webakery' ); ?></label></th>
						<td><textarea class="large-text" rows="2" id="wbk_invoice_note" name="webakery_settings[invoice_note]"><?php echo esc_textarea( $settings['invoice_note'] ); ?></textarea></td>
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

		$invoice = Webakery_Invoice::get_invoice_data( $post->ID );

		echo '<table class="widefat striped"><tbody>';
		foreach ( $fields as $key => $label ) {
			$value = get_post_meta( $post->ID, $key, true );
			echo '<tr><th style="width:160px;">' . esc_html( $label ) . '</th><td>' . esc_html( $value ? $value : '—' ) . '</td></tr>';
		}
		echo '<tr><th>' . esc_html__( 'مبلغ فاکتور', 'webakery' ) . '</th><td>' . esc_html( Webakery_Invoice::format_money( $invoice['total'], $invoice['currency'] ) ) . '</td></tr>';
		echo '</tbody></table>';

		$view_url     = Webakery_Invoice::get_url( $post->ID, 'view' );
		$download_url = Webakery_Invoice::get_url( $post->ID, 'download' );
		?>
		<p class="wbk-order-invoice-actions">
			<a class="button button-primary" href="<?php echo esc_url( $view_url ); ?>" target="_blank" rel="noopener noreferrer">
				<?php esc_html_e( 'مشاهده فاکتور', 'webakery' ); ?>
			</a>
			<a class="button" href="<?php echo esc_url( $download_url ); ?>">
				<?php esc_html_e( 'دانلود فاکتور', 'webakery' ); ?>
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
			'title'       => __( 'عنوان', 'webakery' ),
			'wbk_phone'   => __( 'تلفن', 'webakery' ),
			'wbk_product' => __( 'محصول', 'webakery' ),
			'wbk_qty'     => __( 'تعداد', 'webakery' ),
			'wbk_invoice' => __( 'فاکتور', 'webakery' ),
			'date'        => __( 'تاریخ', 'webakery' ),
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
			return;
		}

		if ( 'wbk_invoice' === $column ) {
			$view_url     = Webakery_Invoice::get_url( $post_id, 'view' );
			$download_url = Webakery_Invoice::get_url( $post_id, 'download' );
			?>
			<span class="wbk-invoice-links">
				<a href="<?php echo esc_url( $view_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'مشاهده', 'webakery' ); ?></a>
				<span aria-hidden="true"> | </span>
				<a href="<?php echo esc_url( $download_url ); ?>"><?php esc_html_e( 'دانلود', 'webakery' ); ?></a>
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
		if ( 'wbk_order' !== $post->post_type || ! current_user_can( 'edit_post', $post->ID ) ) {
			return $actions;
		}

		$actions['webakery_view_invoice'] = sprintf(
			'<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
			esc_url( Webakery_Invoice::get_url( $post->ID, 'view' ) ),
			esc_html__( 'مشاهده فاکتور', 'webakery' )
		);
		$actions['webakery_download_invoice'] = sprintf(
			'<a href="%s">%s</a>',
			esc_url( Webakery_Invoice::get_url( $post->ID, 'download' ) ),
			esc_html__( 'دانلود فاکتور', 'webakery' )
		);

		return $actions;
	}
}
