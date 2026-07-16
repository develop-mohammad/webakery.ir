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
 * Settings page, order details, and invoice management.
 */
class Webakery_Admin {

	/**
	 * Hook registrations.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_init', array( __CLASS__, 'maybe_export_orders' ) );
		add_action( 'admin_init', array( __CLASS__, 'maybe_export_invoices' ) );
		add_action( 'admin_init', array( __CLASS__, 'maybe_create_invoice_from_order' ) );
		add_action( 'admin_init', array( __CLASS__, 'maybe_print_invoice' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'order_meta_box' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'invoice_meta_box' ) );
		add_action( 'save_post_wbk_invoice', array( __CLASS__, 'save_invoice' ), 10, 2 );
		add_filter( 'manage_wbk_order_posts_columns', array( __CLASS__, 'order_columns' ) );
		add_action( 'manage_wbk_order_posts_custom_column', array( __CLASS__, 'order_column_content' ), 10, 2 );
		add_filter( 'manage_wbk_invoice_posts_columns', array( __CLASS__, 'invoice_columns' ) );
		add_action( 'manage_wbk_invoice_posts_custom_column', array( __CLASS__, 'invoice_column_content' ), 10, 2 );
		add_action( 'restrict_manage_posts', array( __CLASS__, 'list_export_buttons' ) );
		add_filter( 'post_row_actions', array( __CLASS__, 'order_row_actions' ), 10, 2 );
		add_filter( 'post_row_actions', array( __CLASS__, 'invoice_row_actions' ), 10, 2 );
		add_action( 'admin_notices', array( __CLASS__, 'invoice_notices' ) );
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
	 * Export buttons on orders/invoices lists.
	 *
	 * @param string $post_type Current post type.
	 */
	public static function list_export_buttons( $post_type ) {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}

		if ( 'wbk_order' === $post_type ) {
			$url = wp_nonce_url(
				admin_url( 'edit.php?post_type=wbk_order&webakery_export_orders=1' ),
				'webakery_export_orders'
			);
			?>
			<a class="button button-secondary" href="<?php echo esc_url( $url ); ?>">
				<?php esc_html_e( 'دانلود سفارش‌ها (CSV)', 'webakery' ); ?>
			</a>
			<?php
		}

		if ( 'wbk_invoice' === $post_type ) {
			$url = wp_nonce_url(
				admin_url( 'edit.php?post_type=wbk_invoice&webakery_export_invoices=1' ),
				'webakery_export_invoices'
			);
			?>
			<a class="button button-secondary" href="<?php echo esc_url( $url ); ?>">
				<?php esc_html_e( 'دانلود فاکتورها (CSV)', 'webakery' ); ?>
			</a>
			<?php
		}
	}

	/**
	 * Stream orders CSV.
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
				__( 'فاکتور', 'webakery' ),
			)
		);

		foreach ( $orders as $order ) {
			$invoice_id = absint( get_post_meta( $order->ID, '_wbk_invoice_id', true ) );
			if ( ! $invoice_id ) {
				$invoice_id = Webakery_Invoices::find_for_order( $order->ID );
			}
			$invoice_number = $invoice_id ? get_post_meta( $invoice_id, '_wbk_invoice_number', true ) : '';

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
					$invoice_number,
				)
			);
		}

		fclose( $output );
		exit;
	}

	/**
	 * Stream invoices CSV.
	 */
	public static function maybe_export_invoices() {
		if ( ! isset( $_GET['webakery_export_invoices'] ) || '1' !== $_GET['webakery_export_invoices'] ) {
			return;
		}

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'اجازه دسترسی ندارید.', 'webakery' ) );
		}

		check_admin_referer( 'webakery_export_invoices' );

		$invoices = get_posts(
			array(
				'post_type'      => 'wbk_invoice',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);

		$statuses = Webakery_Invoices::statuses();
		$filename = 'webakery-invoices-' . gmdate( 'Y-m-d-His' ) . '.csv';

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=' . $filename );

		$output = fopen( 'php://output', 'w' );
		fprintf( $output, chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ) );

		fputcsv(
			$output,
			array(
				'ID',
				__( 'شماره فاکتور', 'webakery' ),
				__( 'تاریخ', 'webakery' ),
				__( 'نام', 'webakery' ),
				__( 'تلفن', 'webakery' ),
				__( 'محصول', 'webakery' ),
				__( 'تعداد', 'webakery' ),
				__( 'قیمت واحد', 'webakery' ),
				__( 'جمع', 'webakery' ),
				__( 'وضعیت', 'webakery' ),
				__( 'سفارش', 'webakery' ),
			)
		);

		foreach ( $invoices as $invoice ) {
			$meta   = Webakery_Invoices::get_meta( $invoice->ID );
			$status = isset( $statuses[ $meta['status'] ] ) ? $statuses[ $meta['status'] ] : $meta['status'];

			fputcsv(
				$output,
				array(
					$invoice->ID,
					$meta['number'],
					$meta['issue_date'] ? $meta['issue_date'] : get_the_date( 'Y-m-d', $invoice ),
					$meta['customer_name'],
					$meta['customer_phone'],
					$meta['product'],
					$meta['qty'],
					$meta['unit_price'],
					$meta['total'],
					$status,
					$meta['order_id'],
				)
			);
		}

		fclose( $output );
		exit;
	}

	/**
	 * Create invoice from order action.
	 */
	public static function maybe_create_invoice_from_order() {
		if ( ! isset( $_GET['webakery_create_invoice'] ) ) {
			return;
		}

		$order_id = absint( $_GET['webakery_create_invoice'] );
		if ( ! $order_id || ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'اجازه دسترسی ندارید.', 'webakery' ) );
		}

		check_admin_referer( 'webakery_create_invoice_' . $order_id );

		$result = Webakery_Invoices::create_from_order( $order_id );
		if ( is_wp_error( $result ) ) {
			wp_die( esc_html( $result->get_error_message() ) );
		}

		$redirect = add_query_arg(
			array(
				'post'               => $result,
				'action'             => 'edit',
				'wbk_invoice_created' => '1',
			),
			admin_url( 'post.php' )
		);
		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Printable invoice view.
	 */
	public static function maybe_print_invoice() {
		if ( ! isset( $_GET['webakery_print_invoice'] ) ) {
			return;
		}

		$invoice_id = absint( $_GET['webakery_print_invoice'] );
		if ( ! $invoice_id || ! current_user_can( 'edit_post', $invoice_id ) ) {
			wp_die( esc_html__( 'اجازه دسترسی ندارید.', 'webakery' ) );
		}

		check_admin_referer( 'webakery_print_invoice_' . $invoice_id );

		$post = get_post( $invoice_id );
		if ( ! $post || 'wbk_invoice' !== $post->post_type ) {
			wp_die( esc_html__( 'فاکتور یافت نشد.', 'webakery' ) );
		}

		self::render_print_invoice( $invoice_id );
		exit;
	}

	/**
	 * Success notice after invoice creation.
	 */
	public static function invoice_notices() {
		if ( empty( $_GET['wbk_invoice_created'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		echo '<div class="notice notice-success is-dismissible"><p>';
		esc_html_e( 'فاکتور از روی سفارش ایجاد شد. در صورت نیاز قیمت و وضعیت را ویرایش کنید.', 'webakery' );
		echo '</p></div>';
	}

	/**
	 * Admin assets.
	 *
	 * @param string $hook Current admin page.
	 */
	public static function assets( $hook ) {
		$post_type = get_post_type();
		$screen_ok = false !== strpos( $hook, 'webakery-settings' )
			|| false !== strpos( $hook, 'webakery-panel' )
			|| in_array( $post_type, array( 'wbk_product', 'wbk_order', 'wbk_invoice' ), true );

		if ( ! $screen_ok ) {
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

			<div class="wbk-admin__shortcodes">
				<h2><?php esc_html_e( 'نقش‌های پنل', 'webakery' ); ?></h2>
				<p><?php esc_html_e( 'از کاربران وردپرس، نقش «مدیر فروشگاه» یا «حسابدار» را به کاربر بدهید تا پنل سفارش‌ها و فاکتورها را ببیند.', 'webakery' ); ?></p>
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

		add_meta_box(
			'webakery_order_invoice',
			__( 'فاکتور', 'webakery' ),
			array( __CLASS__, 'render_order_invoice_box' ),
			'wbk_order',
			'side',
			'high'
		);
	}

	/**
	 * Invoice meta boxes.
	 */
	public static function invoice_meta_box() {
		add_meta_box(
			'webakery_invoice_details',
			__( 'جزئیات فاکتور', 'webakery' ),
			array( __CLASS__, 'render_invoice_meta' ),
			'wbk_invoice',
			'normal',
			'high'
		);

		add_meta_box(
			'webakery_invoice_actions',
			__( 'چاپ فاکتور', 'webakery' ),
			array( __CLASS__, 'render_invoice_actions' ),
			'wbk_invoice',
			'side',
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
	 * Side box: create/open invoice for order.
	 *
	 * @param WP_Post $post Order post.
	 */
	public static function render_order_invoice_box( $post ) {
		$invoice_id = absint( get_post_meta( $post->ID, '_wbk_invoice_id', true ) );
		if ( ! $invoice_id ) {
			$invoice_id = Webakery_Invoices::find_for_order( $post->ID );
		}

		if ( $invoice_id ) {
			$number = get_post_meta( $invoice_id, '_wbk_invoice_number', true );
			$edit   = get_edit_post_link( $invoice_id, 'raw' );
			$print  = wp_nonce_url(
				admin_url( 'edit.php?post_type=wbk_invoice&webakery_print_invoice=' . $invoice_id ),
				'webakery_print_invoice_' . $invoice_id
			);
			?>
			<p>
				<strong><?php esc_html_e( 'فاکتور مرتبط:', 'webakery' ); ?></strong><br />
				<?php echo esc_html( $number ? $number : '#' . $invoice_id ); ?>
			</p>
			<p>
				<a class="button button-primary" href="<?php echo esc_url( $edit ); ?>"><?php esc_html_e( 'مشاهده فاکتور', 'webakery' ); ?></a>
			</p>
			<p>
				<a class="button" href="<?php echo esc_url( $print ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'چاپ فاکتور', 'webakery' ); ?></a>
			</p>
			<?php
			return;
		}

		$url = wp_nonce_url(
			admin_url( 'post.php?post=' . $post->ID . '&action=edit&webakery_create_invoice=' . $post->ID ),
			'webakery_create_invoice_' . $post->ID
		);
		?>
		<p><?php esc_html_e( 'هنوز فاکتوری برای این سفارش صادر نشده است.', 'webakery' ); ?></p>
		<p>
			<a class="button button-primary" href="<?php echo esc_url( $url ); ?>">
				<?php esc_html_e( 'ایجاد فاکتور', 'webakery' ); ?>
			</a>
		</p>
		<?php
	}

	/**
	 * Editable invoice fields.
	 *
	 * @param WP_Post $post Invoice post.
	 */
	public static function render_invoice_meta( $post ) {
		wp_nonce_field( 'webakery_save_invoice', 'webakery_invoice_nonce' );

		$meta     = Webakery_Invoices::get_meta( $post->ID );
		$statuses = Webakery_Invoices::statuses();
		$number   = $meta['number'] ? $meta['number'] : '';
		$status   = $meta['status'] ? $meta['status'] : 'draft';
		?>
		<table class="form-table wbk-invoice-form" role="presentation">
			<tr>
				<th><label for="wbk_invoice_number"><?php esc_html_e( 'شماره فاکتور', 'webakery' ); ?></label></th>
				<td><input type="text" class="regular-text" id="wbk_invoice_number" name="wbk_invoice_number" value="<?php echo esc_attr( $number ); ?>" /></td>
			</tr>
			<tr>
				<th><label for="wbk_issue_date"><?php esc_html_e( 'تاریخ صدور', 'webakery' ); ?></label></th>
				<td><input type="date" id="wbk_issue_date" name="wbk_issue_date" value="<?php echo esc_attr( $meta['issue_date'] ); ?>" dir="ltr" /></td>
			</tr>
			<tr>
				<th><label for="wbk_customer_name"><?php esc_html_e( 'نام مشتری', 'webakery' ); ?></label></th>
				<td><input type="text" class="regular-text" id="wbk_customer_name" name="wbk_customer_name" value="<?php echo esc_attr( $meta['customer_name'] ); ?>" /></td>
			</tr>
			<tr>
				<th><label for="wbk_customer_phone"><?php esc_html_e( 'تلفن', 'webakery' ); ?></label></th>
				<td><input type="text" class="regular-text" id="wbk_customer_phone" name="wbk_customer_phone" value="<?php echo esc_attr( $meta['customer_phone'] ); ?>" dir="ltr" /></td>
			</tr>
			<tr>
				<th><label for="wbk_product"><?php esc_html_e( 'محصول', 'webakery' ); ?></label></th>
				<td><input type="text" class="regular-text" id="wbk_product" name="wbk_product" value="<?php echo esc_attr( $meta['product'] ); ?>" /></td>
			</tr>
			<tr>
				<th><label for="wbk_qty"><?php esc_html_e( 'تعداد', 'webakery' ); ?></label></th>
				<td><input type="number" min="1" id="wbk_qty" name="wbk_qty" value="<?php echo esc_attr( (string) max( 1, $meta['qty'] ) ); ?>" /></td>
			</tr>
			<tr>
				<th><label for="wbk_unit_price"><?php esc_html_e( 'قیمت واحد', 'webakery' ); ?></label></th>
				<td><input type="number" min="0" step="1000" id="wbk_unit_price" name="wbk_unit_price" value="<?php echo esc_attr( (string) $meta['unit_price'] ); ?>" /></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'جمع کل', 'webakery' ); ?></th>
				<td><strong><?php echo esc_html( Webakery_Meta::format_price( $meta['total'] ) ); ?></strong></td>
			</tr>
			<tr>
				<th><label for="wbk_status"><?php esc_html_e( 'وضعیت', 'webakery' ); ?></label></th>
				<td>
					<select id="wbk_status" name="wbk_status">
						<?php foreach ( $statuses as $key => $label ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $status, $key ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th><label for="wbk_order_id"><?php esc_html_e( 'شناسه سفارش', 'webakery' ); ?></label></th>
				<td>
					<input type="number" min="0" id="wbk_order_id" name="wbk_order_id" value="<?php echo esc_attr( (string) $meta['order_id'] ); ?>" />
					<?php if ( $meta['order_id'] ) : ?>
						<a href="<?php echo esc_url( get_edit_post_link( $meta['order_id'], 'raw' ) ); ?>"><?php esc_html_e( 'مشاهده سفارش', 'webakery' ); ?></a>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th><label for="wbk_notes"><?php esc_html_e( 'یادداشت', 'webakery' ); ?></label></th>
				<td><textarea class="large-text" rows="3" id="wbk_notes" name="wbk_notes"><?php echo esc_textarea( $meta['notes'] ); ?></textarea></td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Print button on invoice.
	 *
	 * @param WP_Post $post Invoice post.
	 */
	public static function render_invoice_actions( $post ) {
		$print = wp_nonce_url(
			admin_url( 'edit.php?post_type=wbk_invoice&webakery_print_invoice=' . $post->ID ),
			'webakery_print_invoice_' . $post->ID
		);
		?>
		<p>
			<a class="button button-primary" href="<?php echo esc_url( $print ); ?>" target="_blank" rel="noopener noreferrer">
				<?php esc_html_e( 'چاپ / دانلود PDF مرورگر', 'webakery' ); ?>
			</a>
		</p>
		<p class="description"><?php esc_html_e( 'در صفحه چاپ، از منوی مرورگر گزینه Save as PDF را انتخاب کنید.', 'webakery' ); ?></p>
		<?php
	}

	/**
	 * Save invoice meta.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 */
	public static function save_invoice( $post_id, $post ) {
		if ( ! isset( $_POST['webakery_invoice_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['webakery_invoice_nonce'] ) ), 'webakery_save_invoice' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$number = isset( $_POST['wbk_invoice_number'] ) ? sanitize_text_field( wp_unslash( $_POST['wbk_invoice_number'] ) ) : '';
		if ( '' === $number ) {
			$number = Webakery_Invoices::next_number();
		}

		$qty        = isset( $_POST['wbk_qty'] ) ? max( 1, absint( $_POST['wbk_qty'] ) ) : 1;
		$unit_price = isset( $_POST['wbk_unit_price'] ) ? absint( $_POST['wbk_unit_price'] ) : 0;
		$order_id   = isset( $_POST['wbk_order_id'] ) ? absint( $_POST['wbk_order_id'] ) : 0;
		$status     = isset( $_POST['wbk_status'] ) ? sanitize_key( wp_unslash( $_POST['wbk_status'] ) ) : 'draft';
		$statuses   = Webakery_Invoices::statuses();
		if ( ! isset( $statuses[ $status ] ) ) {
			$status = 'draft';
		}

		update_post_meta( $post_id, '_wbk_invoice_number', $number );
		update_post_meta( $post_id, '_wbk_issue_date', isset( $_POST['wbk_issue_date'] ) ? sanitize_text_field( wp_unslash( $_POST['wbk_issue_date'] ) ) : '' );
		update_post_meta( $post_id, '_wbk_customer_name', isset( $_POST['wbk_customer_name'] ) ? sanitize_text_field( wp_unslash( $_POST['wbk_customer_name'] ) ) : '' );
		update_post_meta( $post_id, '_wbk_customer_phone', isset( $_POST['wbk_customer_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['wbk_customer_phone'] ) ) : '' );
		update_post_meta( $post_id, '_wbk_product', isset( $_POST['wbk_product'] ) ? sanitize_text_field( wp_unslash( $_POST['wbk_product'] ) ) : '' );
		update_post_meta( $post_id, '_wbk_qty', $qty );
		update_post_meta( $post_id, '_wbk_unit_price', $unit_price );
		update_post_meta( $post_id, '_wbk_total', $qty * $unit_price );
		update_post_meta( $post_id, '_wbk_status', $status );
		update_post_meta( $post_id, '_wbk_order_id', $order_id );
		update_post_meta( $post_id, '_wbk_notes', isset( $_POST['wbk_notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['wbk_notes'] ) ) : '' );

		if ( $order_id ) {
			update_post_meta( $order_id, '_wbk_invoice_id', $post_id );
		}
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
			$invoice_id = absint( get_post_meta( $post_id, '_wbk_invoice_id', true ) );
			if ( ! $invoice_id ) {
				$invoice_id = Webakery_Invoices::find_for_order( $post_id );
			}
			if ( $invoice_id ) {
				$number = get_post_meta( $invoice_id, '_wbk_invoice_number', true );
				echo '<a href="' . esc_url( get_edit_post_link( $invoice_id, 'raw' ) ) . '">' . esc_html( $number ? $number : '#' . $invoice_id ) . '</a>';
			} else {
				esc_html_e( '—', 'webakery' );
			}
		}
	}

	/**
	 * Invoice list columns.
	 *
	 * @param array $columns Columns.
	 * @return array
	 */
	public static function invoice_columns( $columns ) {
		return array(
			'cb'           => $columns['cb'],
			'title'        => __( 'عنوان', 'webakery' ),
			'wbk_number'   => __( 'شماره', 'webakery' ),
			'wbk_customer' => __( 'مشتری', 'webakery' ),
			'wbk_total'    => __( 'جمع', 'webakery' ),
			'wbk_status'   => __( 'وضعیت', 'webakery' ),
			'date'         => __( 'تاریخ', 'webakery' ),
		);
	}

	/**
	 * Invoice column values.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Post ID.
	 */
	public static function invoice_column_content( $column, $post_id ) {
		$meta     = Webakery_Invoices::get_meta( $post_id );
		$statuses = Webakery_Invoices::statuses();

		if ( 'wbk_number' === $column ) {
			echo esc_html( $meta['number'] ? $meta['number'] : '—' );
		}

		if ( 'wbk_customer' === $column ) {
			echo esc_html( $meta['customer_name'] ? $meta['customer_name'] : '—' );
		}

		if ( 'wbk_total' === $column ) {
			echo esc_html( Webakery_Meta::format_price( $meta['total'] ) );
		}

		if ( 'wbk_status' === $column ) {
			$label = isset( $statuses[ $meta['status'] ] ) ? $statuses[ $meta['status'] ] : $meta['status'];
			echo '<span class="wbk-status wbk-status--' . esc_attr( $meta['status'] ? $meta['status'] : 'draft' ) . '">' . esc_html( $label ? $label : '—' ) . '</span>';
		}
	}

	/**
	 * Row actions on orders.
	 *
	 * @param array   $actions Actions.
	 * @param WP_Post $post    Post.
	 * @return array
	 */
	public static function order_row_actions( $actions, $post ) {
		if ( 'wbk_order' !== $post->post_type || ! current_user_can( 'edit_posts' ) ) {
			return $actions;
		}

		$invoice_id = absint( get_post_meta( $post->ID, '_wbk_invoice_id', true ) );
		if ( ! $invoice_id ) {
			$invoice_id = Webakery_Invoices::find_for_order( $post->ID );
		}

		if ( $invoice_id ) {
			$actions['wbk_view_invoice'] = '<a href="' . esc_url( get_edit_post_link( $invoice_id, 'raw' ) ) . '">' . esc_html__( 'مشاهده فاکتور', 'webakery' ) . '</a>';
		} else {
			$url = wp_nonce_url(
				admin_url( 'edit.php?post_type=wbk_order&webakery_create_invoice=' . $post->ID ),
				'webakery_create_invoice_' . $post->ID
			);
			$actions['wbk_create_invoice'] = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'ایجاد فاکتور', 'webakery' ) . '</a>';
		}

		return $actions;
	}

	/**
	 * Row actions on invoices.
	 *
	 * @param array   $actions Actions.
	 * @param WP_Post $post    Post.
	 * @return array
	 */
	public static function invoice_row_actions( $actions, $post ) {
		if ( 'wbk_invoice' !== $post->post_type || ! current_user_can( 'edit_post', $post->ID ) ) {
			return $actions;
		}

		$print = wp_nonce_url(
			admin_url( 'edit.php?post_type=wbk_invoice&webakery_print_invoice=' . $post->ID ),
			'webakery_print_invoice_' . $post->ID
		);
		$actions['wbk_print'] = '<a href="' . esc_url( $print ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'چاپ', 'webakery' ) . '</a>';

		return $actions;
	}

	/**
	 * Printable invoice HTML.
	 *
	 * @param int $invoice_id Invoice ID.
	 */
	public static function render_print_invoice( $invoice_id ) {
		$settings = Webakery_Settings::get();
		$meta     = Webakery_Invoices::get_meta( $invoice_id );
		$statuses = Webakery_Invoices::statuses();
		$status   = isset( $statuses[ $meta['status'] ] ) ? $statuses[ $meta['status'] ] : $meta['status'];
		?>
		<!DOCTYPE html>
		<html <?php language_attributes(); ?> dir="rtl">
		<head>
			<meta charset="<?php bloginfo( 'charset' ); ?>" />
			<title><?php echo esc_html( $meta['number'] ? $meta['number'] : __( 'فاکتور', 'webakery' ) ); ?></title>
			<style>
				body { font-family: Tahoma, "Segoe UI", sans-serif; margin: 0; padding: 32px; background: #f6f1ea; color: #2c241c; }
				.sheet { max-width: 800px; margin: 0 auto; background: #fff; border: 1px solid #d8c8b4; padding: 32px; }
				h1 { margin: 0 0 8px; font-size: 28px; }
				.meta { color: #6a5848; margin-bottom: 24px; }
				table { width: 100%; border-collapse: collapse; margin-top: 18px; }
				th, td { border: 1px solid #e2d6c8; padding: 10px 12px; text-align: right; }
				th { background: #fff8ef; }
				.total { font-size: 18px; font-weight: 700; }
				.actions { margin: 0 auto 16px; max-width: 800px; display: flex; gap: 8px; }
				.actions button { padding: 8px 14px; cursor: pointer; }
				@media print {
					body { background: #fff; padding: 0; }
					.actions { display: none; }
					.sheet { border: 0; max-width: none; }
				}
			</style>
		</head>
		<body>
			<div class="actions">
				<button type="button" onclick="window.print()"><?php esc_html_e( 'چاپ / ذخیره PDF', 'webakery' ); ?></button>
				<button type="button" onclick="window.close()"><?php esc_html_e( 'بستن', 'webakery' ); ?></button>
			</div>
			<div class="sheet">
				<h1><?php echo esc_html( $settings['store_name'] ); ?></h1>
				<div class="meta">
					<div><?php esc_html_e( 'فاکتور فروش', 'webakery' ); ?> — <?php echo esc_html( $meta['number'] ? $meta['number'] : '#' . $invoice_id ); ?></div>
					<div><?php esc_html_e( 'تاریخ:', 'webakery' ); ?> <?php echo esc_html( $meta['issue_date'] ? $meta['issue_date'] : get_the_date( 'Y-m-d', $invoice_id ) ); ?></div>
					<div><?php esc_html_e( 'وضعیت:', 'webakery' ); ?> <?php echo esc_html( $status ? $status : '—' ); ?></div>
					<?php if ( ! empty( $settings['phone'] ) ) : ?>
						<div><?php esc_html_e( 'تلفن فروشگاه:', 'webakery' ); ?> <?php echo esc_html( $settings['phone'] ); ?></div>
					<?php endif; ?>
					<?php if ( ! empty( $settings['address'] ) ) : ?>
						<div><?php esc_html_e( 'آدرس:', 'webakery' ); ?> <?php echo esc_html( $settings['address'] ); ?></div>
					<?php endif; ?>
				</div>

				<table>
					<tbody>
						<tr>
							<th><?php esc_html_e( 'نام مشتری', 'webakery' ); ?></th>
							<td><?php echo esc_html( $meta['customer_name'] ? $meta['customer_name'] : '—' ); ?></td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'تلفن مشتری', 'webakery' ); ?></th>
							<td><?php echo esc_html( $meta['customer_phone'] ? $meta['customer_phone'] : '—' ); ?></td>
						</tr>
					</tbody>
				</table>

				<table>
					<thead>
						<tr>
							<th><?php esc_html_e( 'محصول', 'webakery' ); ?></th>
							<th><?php esc_html_e( 'تعداد', 'webakery' ); ?></th>
							<th><?php esc_html_e( 'قیمت واحد', 'webakery' ); ?></th>
							<th><?php esc_html_e( 'جمع', 'webakery' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<tr>
							<td><?php echo esc_html( $meta['product'] ? $meta['product'] : '—' ); ?></td>
							<td><?php echo esc_html( (string) max( 1, $meta['qty'] ) ); ?></td>
							<td><?php echo esc_html( Webakery_Meta::format_price( $meta['unit_price'] ) ); ?></td>
							<td class="total"><?php echo esc_html( Webakery_Meta::format_price( $meta['total'] ) ); ?></td>
						</tr>
					</tbody>
				</table>

				<?php if ( ! empty( $meta['notes'] ) ) : ?>
					<p><strong><?php esc_html_e( 'یادداشت:', 'webakery' ); ?></strong> <?php echo esc_html( $meta['notes'] ); ?></p>
				<?php endif; ?>
			</div>
		</body>
		</html>
		<?php
	}
}
