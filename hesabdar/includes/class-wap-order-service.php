<?php
defined( 'ABSPATH' ) || exit;

/**
 * ایجاد و ویرایش سفارش ووکامرس از داخل Hesabdar — بدون ورود به پیشخوان WC.
 */
class WAP_Order_Service {

	public static function can_manage(): bool {
		return ( current_user_can( 'manage_options' ) || current_user_can( 'manage_woocommerce' ) )
			&& function_exists( 'wap_is_active' ) && wap_is_active();
	}

	/** تغییر وضعیت از لیست سفارش‌ها (مدیر، مدیر فروشگاه، یا حسابدار پرتال) */
	public static function can_change_status(): bool {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return false;
		}
		if ( function_exists( 'wap_is_active' ) && ! wap_is_active() ) {
			return false;
		}
		if ( current_user_can( 'manage_options' ) || current_user_can( 'manage_woocommerce' ) ) {
			return true;
		}
		$portal_cap = class_exists( 'WAP_Portal' ) ? WAP_Portal::CAP : 'wap_view_reports';
		return current_user_can( $portal_cap );
	}

	/**
	 * نمایش وضعیت — فقط badge رنگی (بدون کرکره).
	 *
	 * @param WC_Order $order
	 */
	public static function render_status_cell( $order, string $context = 'wci' ): void {
		self::render_status_badge( $order, $context );
	}

	/**
	 * @param WC_Order $order
	 */
	public static function render_status_badge( $order, string $context = 'wci' ): void {
		if ( ! $order || ! is_a( $order, 'WC_Order' ) ) {
			echo '—';
			return;
		}

		$status = $order->get_status();
		$label  = wc_get_order_status_name( $status );
		$prefix = $context === 'wap' ? 'wap' : 'wci';
		$mod    = $prefix === 'wap' ? $prefix . '-status-' . $status : $prefix . '-status--' . $status;

		echo '<span class="' . esc_attr( $prefix ) . '-status ' . esc_attr( $mod ) . '">' . esc_html( $label ) . '</span>';
	}

	/** گزینه‌های کارهای دسته‌جمعی — مثل لیست سفارش‌های ووکامرس */
	public static function bulk_action_options(): array {
		$options = array(
			''                          => 'کارهای دسته‌جمعی',
			'download_invoices'         => '⬇ دانلود فاکتورهای انتخاب‌شده (ZIP — بدون محدودیت)',
			'download_invoices_filtered'=> '⬇ دانلود همه فاکتورهای فیلتر فعلی (ZIP — بدون محدودیت)',
			'print_invoices'            => '🖨 چاپ فاکتورهای انتخاب‌شده (بدون محدودیت)',
			'print_invoices_filtered'   => '🖨 چاپ همه فاکتورهای فیلتر فعلی (بدون محدودیت)',
			'trash'                     => 'انتقال به زباله‌دان',
		);
		foreach ( wc_get_order_statuses() as $slug => $label ) {
			$val = str_replace( 'wc-', '', $slug );
			$options[ 'status_' . $val ] = 'تغییر وضعیت به ' . $label;
		}
		return $options;
	}

	/**
	 * خواندن عملیات bulk از POST — اگر هر دو select (بالا/پایین) ارسال شده، مقدار غیرخالی را بگیر.
	 *
	 * @param array<string,mixed>|null $src
	 */
	public static function bulk_action_from_request( $src = null ): string {
		$src = is_array( $src ) ? $src : wp_unslash( $_POST );
		$a1  = sanitize_key( (string) ( $src['wci_bulk_action'] ?? '' ) );
		$a2  = sanitize_key( (string) ( $src['wci_bulk_action2'] ?? '' ) );
		return $a2 !== '' ? $a2 : $a1;
	}

	/**
	 * @param string        $action
	 * @param array<int>    $order_ids
	 * @return array{ok:bool,message:string,count?:int,redirect?:string}
	 */
	public static function process_bulk_action( string $action, array $order_ids ): array {
		$action    = sanitize_key( $action );
		$order_ids = array_values( array_filter( array_map( 'absint', $order_ids ) ) );

		if ( $action === '' ) {
			return array( 'ok' => false, 'message' => 'یک عملیات دسته‌جمعی انتخاب کنید.' );
		}

		$invoice_actions = array(
			'print_invoices',
			'print_invoices_filtered',
			'download_invoices',
			'download_invoices_filtered',
		);

		// چاپ / دانلود فاکتور — بدون سقف تعداد
		if ( in_array( $action, $invoice_actions, true ) ) {
			if ( ! self::can_change_status() ) {
				return array( 'ok' => false, 'message' => 'دسترسی فاکتور ندارید.' );
			}
			if ( empty( $order_ids ) ) {
				$is_filtered = false !== strpos( $action, '_filtered' );
				return array(
					'ok'      => false,
					'message' => $is_filtered
						? 'با فیلتر فعلی سفارشی برای فاکتور نیست.'
						: 'حداقل یک سفارش را تیک بزنید، یا گزینه «همه فاکتورهای فیلتر» را انتخاب کنید.',
				);
			}
			if ( ! class_exists( 'WCI_Bulk_Invoice' ) ) {
				return array( 'ok' => false, 'message' => 'ماژول فاکتور دسته‌جمعی بارگذاری نشده.' );
			}
			$mode = ( 0 === strpos( $action, 'download_' ) ) ? 'download' : 'print';
			return WCI_Bulk_Invoice::start( $order_ids, $mode );
		}

		if ( empty( $order_ids ) ) {
			return array( 'ok' => false, 'message' => 'حداقل یک سفارش انتخاب کنید.' );
		}

		if ( $action === 'trash' ) {
			if ( ! self::can_manage() ) {
				return array( 'ok' => false, 'message' => 'دسترسی حذف ندارید.' );
			}
			$done = 0;
			foreach ( $order_ids as $order_id ) {
				if ( self::trash_order( $order_id ) ) {
					$done++;
				}
			}
			return array(
				'ok'      => $done > 0,
				'message' => $done > 0 ? $done . ' سفارش به زباله‌دان منتقل شد.' : 'هیچ سفارشی حذف نشد.',
				'count'   => $done,
			);
		}

		if ( strpos( $action, 'status_' ) === 0 ) {
			if ( ! self::can_change_status() ) {
				return array( 'ok' => false, 'message' => 'دسترسی تغییر وضعیت ندارید.' );
			}
			$status = substr( $action, 7 );
			$done   = 0;
			$last   = '';
			foreach ( $order_ids as $order_id ) {
				$result = self::update_status( $order_id, $status );
				if ( ! empty( $result['success'] ) ) {
					$done++;
					$last = $result['label'] ?? '';
				}
			}
			if ( $done === 0 ) {
				return array( 'ok' => false, 'message' => 'وضعیت سفارش‌ها تغییر نکرد.' );
			}
			return array(
				'ok'      => true,
				'message' => $done . ' سفارش به «' . $last . '» تغییر وضعیت داد.',
				'count'   => $done,
			);
		}

		return array( 'ok' => false, 'message' => 'عملیات نامعتبر است.' );
	}

	/**
	 * نوار کارهای دسته‌جمعی (بالا/پایین جدول).
	 *
	 * @param string $position top|bottom
	 */
	public static function render_bulk_actions_bar( string $position = 'top', string $select_id = '' ): void {
		if ( ! self::can_change_status() ) {
			return;
		}
		$field     = $position === 'bottom' ? 'wci_bulk_action2' : 'wci_bulk_action';
		$select_id = $select_id ?: ( 'wci-bulk-action-' . $position );
		echo '<div class="tablenav ' . esc_attr( $position ) . ' wci-tablenav">';
		echo '<div class="alignleft actions bulkactions">';
		echo '<label class="screen-reader-text" for="' . esc_attr( $select_id ) . '">کارهای دسته‌جمعی</label>';
		echo '<select name="' . esc_attr( $field ) . '" id="' . esc_attr( $select_id ) . '">';
		foreach ( self::bulk_action_options() as $value => $label ) {
			echo '<option value="' . esc_attr( $value ) . '">' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
		echo '<input type="submit" name="wci_bulk_apply" class="button action" value="اجرا">';
		echo '</div></div>';
	}

	/**
	 * @return array{success:bool,message:string,status?:string,label?:string}
	 */
	public static function update_status( int $order_id, string $status ): array {
		if ( ! self::can_change_status() ) {
			return array( 'success' => false, 'message' => 'دسترسی ندارید.' );
		}

		$order_id = absint( $order_id );
		$status   = sanitize_key( $status );
		$status   = str_replace( 'wc-', '', $status );

		$valid = array_map(
			function( $s ) {
				return str_replace( 'wc-', '', $s );
			},
			array_keys( wc_get_order_statuses() )
		);
		if ( ! in_array( $status, $valid, true ) ) {
			return array( 'success' => false, 'message' => 'وضعیت نامعتبر است.' );
		}

		$order = wc_get_order( $order_id );
		if ( ! $order || $order->get_type() !== 'shop_order' ) {
			return array( 'success' => false, 'message' => 'سفارش یافت نشد.' );
		}

		if ( $order->get_status() === $status ) {
			return array(
				'success' => true,
				'message' => 'وضعیت تغییری نکرد.',
				'status'  => $status,
				'label'   => wc_get_order_status_name( $status ),
			);
		}

		$order->update_status( $status, 'تغییر وضعیت از Hesabdar', true );
		$order->save();

		return array(
			'success' => true,
			'message' => 'وضعیت سفارش به‌روزرسانی شد.',
			'status'  => $status,
			'label'   => wc_get_order_status_name( $status ),
		);
	}

	public static function edit_url( int $order_id = 0 ): string {
		$args = array( 'page' => 'wci-order-edit' );
		if ( $order_id > 0 ) {
			$args['order_id'] = $order_id;
		}
		return admin_url( 'admin.php?' . http_build_query( $args ) );
	}

	/**
	 * @return array<string,mixed>
	 */
	public static function empty_form(): array {
		$country = 'IR';
		return array(
			'order_id'        => 0,
			'status'          => 'pending',
			'customer_id'     => 0,
			'payment_method'  => '',
			'transaction_id'  => '',
			'customer_note'   => '',
			'order_note'      => '',
			'billing'         => self::empty_address(),
			'shipping'        => self::empty_address(),
			'ship_to_billing' => true,
			'line_items'      => array(),
			'baget_fields'    => array(),
			'created_display' => '',
		);
	}

	/**
	 * @return array<string,string>
	 */
	private static function empty_address(): array {
		return array(
			'first_name' => '',
			'last_name'  => '',
			'company'    => '',
			'email'      => '',
			'phone'      => '',
			'country'    => 'IR',
			'state'      => '',
			'city'       => '',
			'address_1'  => '',
			'address_2'  => '',
			'postcode'   => '',
		);
	}

	/**
	 * @param WC_Order $order
	 * @return array<string,mixed>
	 */
	public static function order_to_form( $order ): array {
		if ( ! $order || ! is_a( $order, 'WC_Order' ) ) {
			return self::empty_form();
		}

		$line_items = array();
		foreach ( $order->get_items( 'line_item' ) as $item_id => $item ) {
			$product = $item->get_product();
			$line_items[] = array(
				'item_id'    => $item_id,
				'product_id' => $item->get_product_id(),
				'variation_id' => $item->get_variation_id(),
				'name'       => $item->get_name(),
				'sku'        => $product ? $product->get_sku() : '',
				'qty'        => $item->get_quantity(),
				'unit_price' => $item->get_quantity() > 0 ? (float) $item->get_subtotal() / $item->get_quantity() : 0,
				'subtotal'   => (float) $item->get_subtotal(),
				'total'      => (float) $item->get_total(),
			);
		}

		$baget = array();
		if ( class_exists( 'WAP_Baget_Fields' ) ) {
			foreach ( WAP_Baget_Fields::get_field_definitions() as $key => $def ) {
				if ( in_array( $key, self::wc_native_keys(), true ) ) {
					continue;
				}
				$baget[ $key ] = WAP_Baget_Fields::get_order_field_value( $order, $key );
			}
		}

		$billing  = self::extract_address( $order, 'billing' );
		$shipping = self::extract_address( $order, 'shipping' );

		return array(
			'order_id'        => $order->get_id(),
			'order_number'    => $order->get_order_number(),
			'status'          => $order->get_status(),
			'customer_id'     => $order->get_customer_id(),
			'payment_method'  => $order->get_payment_method(),
			'transaction_id'  => $order->get_transaction_id(),
			'customer_note'   => $order->get_customer_note(),
			'order_note'      => '',
			'billing'         => $billing,
			'shipping'        => $shipping,
			'ship_to_billing' => self::addresses_match( $billing, $shipping ),
			'line_items'      => $line_items,
			'baget_fields'    => $baget,
			'created_display' => $order->get_date_created() ? wc_format_datetime( $order->get_date_created() ) : '',
			'total_html'      => $order->get_formatted_order_total(),
		);
	}

	/**
	 * @return string[]
	 */
	private static function wc_native_keys(): array {
		return array(
			'billing_first_name', 'billing_last_name', 'billing_company',
			'billing_country', 'billing_address_1', 'billing_address_2',
			'billing_city', 'billing_state', 'billing_postcode',
			'billing_phone', 'billing_email',
		);
	}

	/**
	 * @param WC_Order $order
	 * @return array<string,string>
	 */
	private static function extract_address( $order, string $type ): array {
		$getter = function( $field ) use ( $order, $type ) {
			$method = 'get_' . $type . '_' . $field;
			return method_exists( $order, $method ) ? (string) $order->$method() : '';
		};
		return array(
			'first_name' => $getter( 'first_name' ),
			'last_name'  => $getter( 'last_name' ),
			'company'    => $getter( 'company' ),
			'email'      => $type === 'billing' ? $getter( 'email' ) : '',
			'phone'      => $type === 'billing' ? $getter( 'phone' ) : '',
			'country'    => $getter( 'country' ) ?: 'IR',
			'state'      => $getter( 'state' ),
			'city'       => $getter( 'city' ),
			'address_1'  => $getter( 'address_1' ),
			'address_2'  => $getter( 'address_2' ),
			'postcode'   => $getter( 'postcode' ),
		);
	}

	/**
	 * @param array<string,string> $billing
	 * @param array<string,string> $shipping
	 */
	private static function addresses_match( array $billing, array $shipping ): bool {
		$keys = array( 'first_name', 'last_name', 'company', 'country', 'state', 'city', 'address_1', 'address_2', 'postcode' );
		foreach ( $keys as $key ) {
			if ( ( $billing[ $key ] ?? '' ) !== ( $shipping[ $key ] ?? '' ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * @param array<string,mixed> $post
	 * @return array{success:bool,order_id?:int,message:string}
	 */
	public static function save_from_post( array $post ): array {
		if ( ! self::can_manage() ) {
			return array( 'success' => false, 'message' => 'دسترسی ندارید.' );
		}
		if ( ! class_exists( 'WooCommerce' ) ) {
			return array( 'success' => false, 'message' => 'ووکامرس فعال نیست.' );
		}

		$order_id = absint( $post['order_id'] ?? 0 );
		$order    = $order_id ? wc_get_order( $order_id ) : null;

		if ( $order_id && ( ! $order || $order->get_type() !== 'shop_order' ) ) {
			return array( 'success' => false, 'message' => 'سفارش یافت نشد.' );
		}

		if ( ! $order ) {
			$order = wc_create_order( array( 'status' => 'pending' ) );
			if ( is_wp_error( $order ) ) {
				return array( 'success' => false, 'message' => $order->get_error_message() );
			}
		}

		$status = sanitize_key( $post['order_status'] ?? 'pending' );
		$status = str_replace( 'wc-', '', $status );
		$valid  = array_keys( wc_get_order_statuses() );
		$valid  = array_map( function( $s ) {
			return str_replace( 'wc-', '', $s );
		}, $valid );
		if ( ! in_array( $status, $valid, true ) ) {
			$status = 'pending';
		}
		$order->set_status( $status );

		$customer_id = absint( $post['customer_id'] ?? 0 );
		$order->set_customer_id( $customer_id );

		$billing  = self::sanitize_address( $post['billing'] ?? array(), true );
		$shipping = self::sanitize_address( $post['shipping'] ?? array(), false );

		if ( ! empty( $post['ship_to_billing'] ) ) {
			$shipping = $billing;
			unset( $shipping['email'], $shipping['phone'] );
		}

		self::apply_address( $order, 'billing', $billing );
		self::apply_address( $order, 'shipping', $shipping );

		$payment_method = sanitize_text_field( $post['payment_method'] ?? '' );
		$order->set_payment_method( $payment_method );
		$gateways = self::get_gateways();
		$order->set_payment_method_title( $gateways[ $payment_method ] ?? $payment_method );

		$order->set_transaction_id( sanitize_text_field( $post['transaction_id'] ?? '' ) );
		$order->set_customer_note( sanitize_textarea_field( $post['customer_note'] ?? '' ) );

		// خطوط سفارش — حذف قبلی و افزودن مجدد
		foreach ( $order->get_items( 'line_item' ) as $item_id => $item ) {
			$order->remove_item( $item_id );
		}

		$items = isset( $post['line_items'] ) && is_array( $post['line_items'] ) ? $post['line_items'] : array();
		foreach ( $items as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$product_id   = absint( $row['product_id'] ?? 0 );
			$variation_id = absint( $row['variation_id'] ?? 0 );
			$qty          = max( 0, (float) ( $row['qty'] ?? 0 ) );
			if ( $product_id <= 0 || $qty <= 0 ) {
				continue;
			}

			$product = $variation_id ? wc_get_product( $variation_id ) : wc_get_product( $product_id );
			if ( ! $product ) {
				continue;
			}

			$item_id = $order->add_product( $product, $qty );
			if ( ! $item_id ) {
				continue;
			}

			$line_total = isset( $row['line_total'] ) ? (float) wc_format_decimal( $row['line_total'] ) : null;
			if ( $line_total !== null && $line_total >= 0 ) {
				$item = $order->get_item( $item_id );
				if ( $item ) {
					$item->set_subtotal( $line_total );
					$item->set_total( $line_total );
					$item->save();
				}
			}
		}

		// فیلدهای Baget
		if ( class_exists( 'WAP_Baget_Fields' ) && ! empty( $post['baget_fields'] ) && is_array( $post['baget_fields'] ) ) {
			foreach ( WAP_Baget_Fields::get_field_definitions() as $key => $def ) {
				if ( in_array( $key, self::wc_native_keys(), true ) ) {
					continue;
				}
				if ( ! isset( $post['baget_fields'][ $key ] ) ) {
					continue;
				}
				$value = sanitize_text_field( wp_unslash( $post['baget_fields'][ $key ] ) );
				$order->update_meta_data( $key, $value );
				$order->update_meta_data( '_' . $key, $value );
			}
		}

		$order->calculate_totals( false );
		$order->save();

		$note = sanitize_textarea_field( $post['order_note'] ?? '' );
		if ( $note !== '' ) {
			$order->add_order_note( $note, false, true );
		}

		return array(
			'success'  => true,
			'order_id' => $order->get_id(),
			'message'  => $order_id ? 'سفارش به‌روزرسانی شد.' : 'سفارش جدید ایجاد شد.',
		);
	}

	/**
	 * @param array<string,mixed> $raw
	 * @return array<string,string>
	 */
	private static function sanitize_address( array $raw, bool $with_contact ): array {
		$out = array(
			'first_name' => sanitize_text_field( $raw['first_name'] ?? '' ),
			'last_name'  => sanitize_text_field( $raw['last_name'] ?? '' ),
			'company'    => sanitize_text_field( $raw['company'] ?? '' ),
			'country'    => sanitize_text_field( $raw['country'] ?? 'IR' ),
			'state'      => sanitize_text_field( $raw['state'] ?? '' ),
			'city'       => sanitize_text_field( $raw['city'] ?? '' ),
			'address_1'  => sanitize_textarea_field( $raw['address_1'] ?? '' ),
			'address_2'  => sanitize_textarea_field( $raw['address_2'] ?? '' ),
			'postcode'   => sanitize_text_field( $raw['postcode'] ?? '' ),
		);
		if ( $with_contact ) {
			$out['email'] = sanitize_email( $raw['email'] ?? '' );
			$out['phone'] = sanitize_text_field( $raw['phone'] ?? '' );
		}
		return $out;
	}

	/**
	 * @param WC_Order $order
	 * @param array<string,string> $data
	 */
	private static function apply_address( $order, string $type, array $data ): void {
		$map = array(
			'first_name', 'last_name', 'company', 'country', 'state',
			'city', 'address_1', 'address_2', 'postcode',
		);
		foreach ( $map as $field ) {
			$setter = 'set_' . $type . '_' . $field;
			if ( method_exists( $order, $setter ) && isset( $data[ $field ] ) ) {
				$order->$setter( $data[ $field ] );
			}
		}
		if ( $type === 'billing' ) {
			if ( method_exists( $order, 'set_billing_email' ) ) {
				$order->set_billing_email( $data['email'] ?? '' );
			}
			if ( method_exists( $order, 'set_billing_phone' ) ) {
				$order->set_billing_phone( $data['phone'] ?? '' );
			}
		}
	}

	/**
	 * @return array<string,string> gateway_id => title
	 */
	public static function get_gateways(): array {
		if ( ! class_exists( 'WooCommerce' ) || ! WC()->payment_gateways() ) {
			return array();
		}
		$out = array( '' => '— بدون درگاه —' );
		foreach ( WC()->payment_gateways()->payment_gateways() as $id => $gateway ) {
			if ( $gateway->enabled === 'yes' || current_user_can( 'manage_options' ) ) {
				$out[ $id ] = $gateway->get_title();
			}
		}
		return $out;
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public static function search_products( string $term, int $limit = 20 ): array {
		$term = trim( $term );
		if ( $term === '' || ! class_exists( 'WooCommerce' ) ) {
			return array();
		}

		$args = array(
			'status' => array( 'publish' ),
			'limit'  => $limit,
			'return' => 'objects',
		);

		if ( is_numeric( $term ) ) {
			$args['include'] = array( absint( $term ) );
		} else {
			$args['s'] = $term;
			$args['sku'] = $term;
		}

		$products = wc_get_products( $args );
		$out      = array();
		$seen     = array();

		foreach ( $products as $product ) {
			if ( ! $product ) {
				continue;
			}
			$out[] = self::product_payload( $product );
			$seen[ $product->get_id() ] = true;
		}

		// جستجوی SKU جداگانه
		if ( ! is_numeric( $term ) ) {
			$by_sku = wc_get_products( array(
				'status' => 'publish',
				'sku'    => $term,
				'limit'  => $limit,
				'return' => 'objects',
			) );
			foreach ( $by_sku as $product ) {
				if ( isset( $seen[ $product->get_id() ] ) ) {
					continue;
				}
				$out[] = self::product_payload( $product );
			}
		}

		return array_slice( $out, 0, $limit );
	}

	/**
	 * @param WC_Product $product
	 * @return array<string,mixed>
	 */
	private static function product_payload( $product ): array {
		return array(
			'id'           => $product->get_id(),
			'parent_id'    => $product->get_parent_id(),
			'name'         => $product->get_name(),
			'sku'          => $product->get_sku(),
			'price'        => (float) $product->get_price(),
			'price_html'   => wp_strip_all_tags( wc_price( $product->get_price() ) ),
			'type'         => $product->get_type(),
			'stock_status' => $product->get_stock_status(),
		);
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public static function search_customers( string $term, int $limit = 15 ): array {
		$term = trim( $term );
		if ( $term === '' ) {
			return array();
		}

		$out  = array();
		$seen = array();

		$users = get_users( array(
			'number'         => $limit,
			'search'         => '*' . esc_attr( $term ) . '*',
			'search_columns' => array( 'user_login', 'user_email', 'display_name' ),
			'role__in'       => array( 'customer', 'subscriber', 'administrator', 'shop_manager' ),
		) );

		foreach ( $users as $user ) {
			$seen[ strtolower( $user->user_email ) ] = true;
			$out[] = self::customer_payload_from_user( $user );
		}

		// جستجو در سفارش‌های اخیر
		if ( class_exists( 'WooCommerce' ) && count( $out ) < $limit ) {
			$orders = wc_get_orders( array(
				'limit'   => 30,
				'return'  => 'objects',
				'type'    => 'shop_order',
				'orderby' => 'date',
				'order'   => 'DESC',
			) );
			$needle = mb_strtolower( $term );
			foreach ( $orders as $order ) {
				if ( count( $out ) >= $limit ) {
					break;
				}
				$email = strtolower( $order->get_billing_email() );
				if ( $email && isset( $seen[ $email ] ) ) {
					continue;
				}
				$hay = mb_strtolower(
					$order->get_billing_first_name() . ' '
					. $order->get_billing_last_name() . ' '
					. $order->get_billing_email() . ' '
					. $order->get_billing_phone()
				);
				if ( strpos( $hay, $needle ) === false ) {
					continue;
				}
				if ( $email ) {
					$seen[ $email ] = true;
				}
				$out[] = self::customer_payload_from_order( $order );
			}
		}

		return array_slice( $out, 0, $limit );
	}

	/**
	 * @param WP_User $user
	 * @return array<string,mixed>
	 */
	private static function customer_payload_from_user( WP_User $user ): array {
		return array(
			'id'         => $user->ID,
			'label'      => trim( $user->display_name ) ?: $user->user_login,
			'email'      => $user->user_email,
			'phone'      => get_user_meta( $user->ID, 'billing_phone', true ),
			'first_name' => get_user_meta( $user->ID, 'billing_first_name', true ),
			'last_name'  => get_user_meta( $user->ID, 'billing_last_name', true ),
			'city'       => get_user_meta( $user->ID, 'billing_city', true ),
			'state'      => get_user_meta( $user->ID, 'billing_state', true ),
			'address_1'  => get_user_meta( $user->ID, 'billing_address_1', true ),
			'postcode'   => get_user_meta( $user->ID, 'billing_postcode', true ),
		);
	}

	/**
	 * @param WC_Order $order
	 * @return array<string,mixed>
	 */
	private static function customer_payload_from_order( $order ): array {
		return array(
			'id'         => $order->get_customer_id(),
			'label'      => $order->get_formatted_billing_full_name(),
			'email'      => $order->get_billing_email(),
			'phone'      => $order->get_billing_phone(),
			'first_name' => $order->get_billing_first_name(),
			'last_name'  => $order->get_billing_last_name(),
			'city'       => $order->get_billing_city(),
			'state'      => $order->get_billing_state(),
			'address_1'  => $order->get_billing_address_1(),
			'postcode'   => $order->get_billing_postcode(),
		);
	}

	public static function trash_order( int $order_id ): bool {
		if ( ! self::can_manage() || $order_id <= 0 ) {
			return false;
		}
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return false;
		}
		$order->delete( false );
		return true;
	}

	public static function init_ajax(): void {
		add_action( 'wp_ajax_wci_search_products', array( __CLASS__, 'ajax_search_products' ) );
		add_action( 'wp_ajax_wci_search_customers', array( __CLASS__, 'ajax_search_customers' ) );
		add_action( 'wp_ajax_wci_update_order_status', array( __CLASS__, 'ajax_update_order_status' ) );
	}

	public static function ajax_search_products(): void {
		if ( ! self::can_manage() || ! check_ajax_referer( 'wci_order_edit', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ), 403 );
		}
		$term = sanitize_text_field( wp_unslash( $_GET['term'] ?? $_POST['term'] ?? '' ) );
		wp_send_json_success( self::search_products( $term ) );
	}

	public static function ajax_search_customers(): void {
		if ( ! self::can_manage() || ! check_ajax_referer( 'wci_order_edit', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ), 403 );
		}
		$term = sanitize_text_field( wp_unslash( $_GET['term'] ?? $_POST['term'] ?? '' ) );
		wp_send_json_success( self::search_customers( $term ) );
	}

	public static function ajax_update_order_status(): void {
		if ( ! self::can_change_status() || ! check_ajax_referer( 'wci_order_status', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => 'دسترسی ندارید.' ), 403 );
		}

		$order_id = absint( $_POST['order_id'] ?? 0 );
		$status   = sanitize_key( wp_unslash( $_POST['status'] ?? '' ) );
		$result   = self::update_status( $order_id, $status );

		if ( empty( $result['success'] ) ) {
			wp_send_json_error( array( 'message' => $result['message'] ?? 'خطا' ) );
		}

		wp_send_json_success( $result );
	}
}
