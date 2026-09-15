<?php
defined( 'ABSPATH' ) || exit;

/**
 * صفحه ایجاد و ویرایش سفارش — مشابه پیشخوان ووکامرس، داخل Hesabdar.
 */
function wci_order_edit_page() {
	if ( ! wci_order_service_ready( 'can_manage' ) || ! WAP_Order_Service::can_manage() ) {
		wp_die( 'دسترسی ندارید یا لایسنس Hesabdar فعال نیست.' );
	}

	$order_id = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0;
	$message  = '';
	$msg_type = 'success';

	if ( isset( $_GET['wci_trash'], $_GET['_wpnonce'] ) && $order_id > 0 ) {
		if ( wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'wci_trash_order_' . $order_id ) ) {
			if ( WAP_Order_Service::trash_order( $order_id ) ) {
				wp_safe_redirect( add_query_arg( 'wci_trashed', '1', admin_url( 'admin.php?page=wci-orders' ) ) );
				exit;
			}
			$message  = 'حذف سفارش ناموفق بود.';
			$msg_type = 'error';
		}
	}

	if ( isset( $_POST['wci_save_order'] ) && check_admin_referer( 'wci_save_order' ) ) {
		$result = WAP_Order_Service::save_from_post( wp_unslash( $_POST ) );
		if ( ! empty( $result['success'] ) ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'      => 'wci-order-edit',
						'order_id'  => (int) $result['order_id'],
						'wci_saved' => '1',
					),
					admin_url( 'admin.php' )
				)
			);
			exit;
		}
		$message  = $result['message'] ?? 'خطا در ذخیره سفارش.';
		$msg_type = 'error';
	}

	if ( isset( $_GET['wci_saved'] ) ) {
		$message  = 'سفارش با موفقیت ذخیره شد.';
		$msg_type = 'success';
	}

	if ( isset( $_GET['wci_tracking_msg'] ) ) {
		$message  = sanitize_text_field( wp_unslash( $_GET['wci_tracking_msg'] ) );
		$msg_type = ( isset( $_GET['wci_tracking'] ) && '1' === $_GET['wci_tracking'] ) ? 'success' : 'error';
	}

	$order = $order_id ? wc_get_order( $order_id ) : null;
	if ( $order_id && ( ! $order || $order->get_type() !== 'shop_order' ) ) {
		wp_die( 'سفارش یافت نشد.' );
	}

	$form = $order ? WAP_Order_Service::order_to_form( $order ) : WAP_Order_Service::empty_form();
	if ( isset( $_POST['wci_save_order'] ) && $msg_type === 'error' ) {
		$form = wci_order_edit_form_from_post( wp_unslash( $_POST ), $form );
	}

	$gateways = WAP_Order_Service::get_gateways();
	$states   = WC()->countries->get_states( 'IR' ) ?: array();
	$notes    = $order_id ? wc_get_order_notes( array( 'order_id' => $order_id, 'type' => 'any', 'orderby' => 'date_created', 'order' => 'DESC' ) ) : array();
	$inv_url = $order_id ? WCI_Invoice::admin_view_url( $order_id ) : '';
	$inv_dl  = $order_id ? WCI_Invoice::admin_download_url( $order_id ) : '';

	$title = $order_id
		? 'ویرایش سفارش #' . esc_html( $form['order_number'] ?? $order_id )
		: 'افزودن سفارش جدید';

	echo '<div class="wrap wci-wrap wci-order-edit-wrap">';
	echo '<h1 class="wci-order-edit-title">' . $title;
	echo ' <a href="' . esc_url( admin_url( 'admin.php?page=wci-orders' ) ) . '" class="page-title-action">← بازگشت به لیست</a>';
	if ( ! $order_id ) {
		echo ' <a href="' . esc_url( WAP_Order_Service::edit_url( 0 ) ) . '" class="page-title-action">سفارش جدید</a>';
	}
	echo '</h1>';

	if ( $message !== '' ) {
		echo '<div class="notice notice-' . esc_attr( $msg_type ) . ' is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
	}

	echo '<form method="post" id="wci-order-form" class="wci-order-form">';
	wp_nonce_field( 'wci_save_order' );
	echo '<input type="hidden" name="wci_save_order" value="1">';
	echo '<input type="hidden" name="order_id" value="' . esc_attr( (string) $form['order_id'] ) . '">';
	echo '<input type="hidden" name="customer_id" id="wci_customer_id" value="' . esc_attr( (string) ( $form['customer_id'] ?? 0 ) ) . '">';

	echo '<div class="wci-order-layout">';

	// ─── ستون اصلی ───────────────────────────────────────────────
	echo '<div class="wci-order-main">';

	// خطوط سفارش
	echo '<div class="wci-order-panel">';
	echo '<h2>🛒 محصولات سفارش</h2>';
	echo '<div class="wci-product-search-wrap">';
	echo '<input type="text" id="wci_product_search" class="regular-text" placeholder="جستجوی محصول (نام، SKU یا شناسه)..." autocomplete="off">';
	echo '<div id="wci_product_results" class="wci-ac-results" style="display:none"></div>';
	echo '</div>';
	echo '<table class="widefat wci-table wci-line-items" id="wci_line_items_table">';
	echo '<thead><tr><th>محصول</th><th style="width:90px">تعداد</th><th style="width:130px">قیمت خط</th><th style="width:110px">جمع</th><th style="width:50px"></th></tr></thead>';
	echo '<tbody id="wci_line_items_body">';
	if ( ! empty( $form['line_items'] ) ) {
		foreach ( $form['line_items'] as $idx => $item ) {
			wci_order_edit_render_line_row( $idx, $item );
		}
	}
	echo '</tbody>';
	echo '<tfoot><tr><td colspan="5" class="wci-line-empty" id="wci_line_empty"' . ( ! empty( $form['line_items'] ) ? ' style="display:none"' : '' ) . '>هنوز محصولی اضافه نشده — از جستجو بالا محصول اضافه کنید.</td></tr></tfoot>';
	echo '</table>';
	if ( ! empty( $form['total_html'] ) && $order_id ) {
		echo '<div class="wci-order-total-preview">جمع کل فعلی: <strong>' . wp_kses_post( $form['total_html'] ) . '</strong> <span class="description">(پس از ذخیره به‌روز می‌شود)</span></div>';
	}
	echo '</div>';

	// صورتحساب
	echo '<div class="wci-order-panel">';
	echo '<h2>📋 آدرس صورتحساب</h2>';
	echo '<div class="wci-customer-search-wrap">';
	echo '<label>جستجوی مشتری</label>';
	echo '<input type="text" id="wci_customer_search" class="regular-text" placeholder="نام، ایمیل یا شماره تماس..." autocomplete="off">';
	echo '<div id="wci_customer_results" class="wci-ac-results" style="display:none"></div>';
	echo '</div>';
	wci_order_edit_render_address_fields( 'billing', $form['billing'] ?? array(), $states, true );
	echo '</div>';

	// ارسال
	echo '<div class="wci-order-panel">';
	echo '<h2>📦 آدرس ارسال</h2>';
	echo '<label class="wci-ship-same"><input type="checkbox" name="ship_to_billing" id="wci_ship_to_billing" value="1"' . checked( ! empty( $form['ship_to_billing'] ), true, false ) . '> ارسال به همان آدرس صورتحساب</label>';
	echo '<div id="wci_shipping_fields"' . ( ! empty( $form['ship_to_billing'] ) ? ' class="is-disabled"' : '' ) . '>';
	wci_order_edit_render_address_fields( 'shipping', $form['shipping'] ?? array(), $states, false );
	echo '</div>';
	echo '</div>';

	// یادداشت‌ها
	echo '<div class="wci-order-panel">';
	echo '<h2>📝 یادداشت‌ها</h2>';
	echo '<p><label for="wci_customer_note"><strong>یادداشت مشتری</strong> <span class="description">(قابل مشاهده برای مشتری)</span></label></p>';
	echo '<textarea name="customer_note" id="wci_customer_note" rows="3" class="large-text">' . esc_textarea( $form['customer_note'] ?? '' ) . '</textarea>';
	echo '<p style="margin-top:14px"><label for="wci_order_note"><strong>یادداشت خصوصی</strong> <span class="description">(فقط مدیر — با ذخیره اضافه می‌شود)</span></label></p>';
	echo '<textarea name="order_note" id="wci_order_note" rows="2" class="large-text" placeholder="یادداشت جدید..."></textarea>';
	if ( $notes ) {
		echo '<div class="wci-order-notes-list"><strong>یادداشت‌های قبلی:</strong><ul>';
		foreach ( $notes as $note ) {
			$text = is_object( $note ) && isset( $note->content ) ? $note->content : '';
			$date = is_object( $note ) && isset( $note->date_created ) ? $note->date_created->date( 'Y-m-d H:i' ) : '';
			echo '<li><span class="wci-note-date">' . esc_html( $date ) . '</span> ' . wp_kses_post( $text ) . '</li>';
		}
		echo '</ul></div>';
	}
	echo '</div>';

	// Baget
	if ( class_exists( 'WAP_Baget_Fields' ) ) {
		$baget_defs = WAP_Baget_Fields::get_field_definitions();
		$baget_keys = array();
		foreach ( $baget_defs as $key => $def ) {
			if ( in_array( $key, array(
				'billing_first_name', 'billing_last_name', 'billing_company',
				'billing_country', 'billing_address_1', 'billing_address_2',
				'billing_city', 'billing_state', 'billing_postcode',
				'billing_phone', 'billing_email',
			), true ) ) {
				continue;
			}
			$baget_keys[ $key ] = $def['label'] ?? $key;
		}
		if ( $baget_keys ) {
			echo '<div class="wci-order-panel">';
			echo '<h2>📌 فیلدهای سفارشی (Baget)</h2>';
			echo '<div class="wci-address-grid">';
			foreach ( $baget_keys as $key => $label ) {
				$val = $form['baget_fields'][ $key ] ?? '';
				echo '<div class="wci-field"><label>' . esc_html( $label ) . '</label>';
				echo '<input type="text" name="baget_fields[' . esc_attr( $key ) . ']" value="' . esc_attr( $val ) . '"></div>';
			}
			echo '</div></div>';
		}
	}

	echo '</div>'; // main

	// ─── سایدبار ─────────────────────────────────────────────────
	echo '<div class="wci-order-side">';

	echo '<div class="wci-order-panel wci-order-actions">';
	echo '<button type="submit" class="button button-primary button-hero wci-btn-save">💾 ذخیره سفارش</button>';
	if ( $order_id && $inv_url ) {
		echo '<a href="' . esc_url( $inv_url ) . '" class="button button-secondary" target="_blank" rel="noopener noreferrer" style="width:100%;text-align:center;margin-top:8px">🧾 مشاهده فاکتور</a>';
		echo '<a href="' . esc_url( $inv_dl ) . '" class="button button-secondary" style="width:100%;text-align:center;margin-top:8px">⬇ دانلود فاکتور</a>';
	}
	if ( $order_id ) {
		$trash_url = wp_nonce_url(
			add_query_arg( array( 'page' => 'wci-order-edit', 'order_id' => $order_id, 'wci_trash' => '1' ), admin_url( 'admin.php' ) ),
			'wci_trash_order_' . $order_id
		);
		echo '<a href="' . esc_url( $trash_url ) . '" class="button wci-btn-trash" onclick="return confirm(\'سفارش به سطل زباله منتقل شود؟\')">🗑 حذف سفارش</a>';
	}
	echo '</div>';

	echo '<div class="wci-order-panel">';
	echo '<h2>⚙️ وضعیت سفارش</h2>';
	echo '<select name="order_status" id="wci_order_status" class="widefat">';
	foreach ( wc_get_order_statuses() as $slug => $label ) {
		$val = str_replace( 'wc-', '', $slug );
		echo '<option value="' . esc_attr( $val ) . '" ' . selected( $form['status'] ?? 'pending', $val, false ) . '>' . esc_html( $label ) . '</option>';
	}
	echo '</select>';
	if ( ! empty( $form['created_display'] ) ) {
		echo '<p class="description" style="margin-top:10px">تاریخ ایجاد: <strong>' . esc_html( $form['created_display'] ) . '</strong></p>';
	}
	echo '</div>';

	echo '<div class="wci-order-panel">';
	echo '<h2>💳 پرداخت</h2>';
	echo '<label>درگاه / روش پرداخت</label>';
	echo '<select name="payment_method" class="widefat">';
	foreach ( $gateways as $gid => $gtitle ) {
		echo '<option value="' . esc_attr( $gid ) . '" ' . selected( $form['payment_method'] ?? '', $gid, false ) . '>' . esc_html( $gtitle ) . '</option>';
	}
	echo '</select>';
	echo '<label style="margin-top:10px;display:block">شناسه تراکنش</label>';
	echo '<input type="text" name="transaction_id" class="widefat" value="' . esc_attr( $form['transaction_id'] ?? '' ) . '" placeholder="اختیاری">';
	echo '</div>';

	echo '</div>'; // side
	echo '</div>'; // layout
	echo '</form>';

	// پنل کد رهگیری خارج از فرم اصلی — مثل متاباکس ووکامرس
	if ( $order_id && $order && class_exists( 'WCI_Tracking' ) ) {
		echo '<div class="wci-order-layout" style="margin-top:0">';
		echo '<div class="wci-order-main" style="max-width:520px">';
		WCI_Tracking::render_panel( $order );
		echo '</div></div>';
	}

	echo '</div>';
}

/**
 * @param array<string,mixed> $item
 */
function wci_order_edit_render_line_row( int $idx, array $item ): void {
	$name  = $item['name'] ?? '';
	$pid   = (int) ( $item['product_id'] ?? 0 );
	$vid   = (int) ( $item['variation_id'] ?? 0 );
	$qty   = (float) ( $item['qty'] ?? 1 );
	$total = isset( $item['total'] ) ? (float) $item['total'] : (float) ( $item['subtotal'] ?? 0 );
	$sku   = $item['sku'] ?? '';
	echo '<tr class="wci-line-row" data-idx="' . esc_attr( (string) $idx ) . '">';
	echo '<td>';
	echo '<input type="hidden" name="line_items[' . $idx . '][product_id]" value="' . esc_attr( (string) $pid ) . '">';
	echo '<input type="hidden" name="line_items[' . $idx . '][variation_id]" value="' . esc_attr( (string) $vid ) . '">';
	echo '<strong>' . esc_html( $name ) . '</strong>';
	if ( $sku !== '' ) {
		echo '<br><span class="description">SKU: ' . esc_html( $sku ) . '</span>';
	}
	echo '</td>';
	echo '<td><input type="number" name="line_items[' . $idx . '][qty]" class="small-text wci-line-qty" min="0" step="1" value="' . esc_attr( (string) $qty ) . '"></td>';
	echo '<td><input type="number" name="line_items[' . $idx . '][line_total]" class="small-text wci-line-total" min="0" step="1" value="' . esc_attr( (string) round( $total ) ) . '"></td>';
	echo '<td class="wci-line-sum">' . esc_html( number_format_i18n( $total ) ) . '</td>';
	echo '<td><button type="button" class="button-link wci-line-remove" title="حذف">&times;</button></td>';
	echo '</tr>';
}

/**
 * @param array<string,string> $data
 * @param array<string,string> $states
 */
function wci_order_edit_render_address_fields( string $prefix, array $data, array $states, bool $with_contact ): void {
	echo '<div class="wci-address-grid">';
	$fields = array(
		'first_name' => 'نام',
		'last_name'  => 'نام خانوادگی',
		'company'    => 'شرکت',
	);
	if ( $with_contact ) {
		$fields['email'] = 'ایمیل';
		$fields['phone'] = 'شماره تماس';
	}
	$fields['state']     = 'استان';
	$fields['city']      = 'شهر';
	$fields['address_1'] = 'آدرس';
	$fields['address_2'] = 'آدرس ۲';
	$fields['postcode']  = 'کد پستی';

	foreach ( $fields as $key => $label ) {
		$val = $data[ $key ] ?? '';
		echo '<div class="wci-field">';
		echo '<label>' . esc_html( $label ) . '</label>';
		if ( $key === 'state' && $states ) {
			echo '<select name="' . esc_attr( $prefix ) . '[' . esc_attr( $key ) . ']">';
			echo '<option value="">—</option>';
			foreach ( $states as $code => $sname ) {
				echo '<option value="' . esc_attr( $code ) . '" ' . selected( $val, $code, false ) . '>' . esc_html( $sname ) . '</option>';
			}
			echo '</select>';
		} else {
			$type = $key === 'email' ? 'email' : 'text';
			echo '<input type="' . esc_attr( $type ) . '" name="' . esc_attr( $prefix ) . '[' . esc_attr( $key ) . ']" value="' . esc_attr( $val ) . '">';
		}
		echo '</div>';
	}
	echo '<input type="hidden" name="' . esc_attr( $prefix ) . '[country]" value="IR">';
	echo '</div>';
}

/**
 * بازسازی فرم از POST پس از خطای ذخیره.
 *
 * @param array<string,mixed> $post
 * @param array<string,mixed> $fallback
 * @return array<string,mixed>
 */
function wci_order_edit_form_from_post( array $post, array $fallback ): array {
	$form = $fallback;
	$form['order_id']        = absint( $post['order_id'] ?? 0 );
	$form['status']          = sanitize_key( $post['order_status'] ?? 'pending' );
	$form['customer_id']     = absint( $post['customer_id'] ?? 0 );
	$form['payment_method']  = sanitize_text_field( $post['payment_method'] ?? '' );
	$form['transaction_id']  = sanitize_text_field( $post['transaction_id'] ?? '' );
	$form['customer_note']   = sanitize_textarea_field( $post['customer_note'] ?? '' );
	$form['ship_to_billing'] = ! empty( $post['ship_to_billing'] );
	$form['billing']         = is_array( $post['billing'] ?? null ) ? array_map( 'sanitize_text_field', $post['billing'] ) : $fallback['billing'];
	$form['shipping']        = is_array( $post['shipping'] ?? null ) ? array_map( 'sanitize_text_field', $post['shipping'] ) : $fallback['shipping'];
	$form['baget_fields']    = is_array( $post['baget_fields'] ?? null ) ? array_map( 'sanitize_text_field', $post['baget_fields'] ) : ( $fallback['baget_fields'] ?? array() );

	$line_items = array();
	if ( ! empty( $post['line_items'] ) && is_array( $post['line_items'] ) ) {
		foreach ( $post['line_items'] as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$pid = absint( $row['product_id'] ?? 0 );
			$qty = (float) ( $row['qty'] ?? 0 );
			if ( $pid <= 0 || $qty <= 0 ) {
				continue;
			}
			$product = wc_get_product( $pid );
			$line_items[] = array(
				'product_id'   => $pid,
				'variation_id' => absint( $row['variation_id'] ?? 0 ),
				'name'         => $product ? $product->get_name() : 'محصول #' . $pid,
				'sku'          => $product ? $product->get_sku() : '',
				'qty'          => $qty,
				'total'        => (float) ( $row['line_total'] ?? 0 ),
				'subtotal'     => (float) ( $row['line_total'] ?? 0 ),
			);
		}
	}
	$form['line_items'] = $line_items;
	return $form;
}
