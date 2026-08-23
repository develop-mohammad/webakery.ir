<?php
defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'hesabdar_user_can_wci' ) ) {
	function hesabdar_user_can_wci() {
		return current_user_can( 'manage_options' ) || current_user_can( 'manage_woocommerce' );
	}
}

/* اگر WCI قدیمی یا نسخهٔ دیگر همین توابع را تعریف کرده، از fatal error جلوگیری می‌شود. */
if ( function_exists( 'wci_orders_page' ) ) {
	return;
}

/* توابع صفحات مدیریت مشتریان/سفارش‌ها/محصولات/گزارش مالی ووکامرس —
   منتقل‌شده از افزونه‌ی WooCommerce Customer Info Pro (ادغام در Hesabdar) */

function wci_order_service_ready( $method = '' ) {
	if ( ! class_exists( 'WAP_Order_Service' ) ) {
		return false;
	}
	if ( $method !== '' && ! method_exists( 'WAP_Order_Service', $method ) ) {
		return false;
	}
	return true;
}

function wci_orders_page() {
    if ( ! function_exists( 'wc_get_orders' ) ) {
        echo '<div class="wrap wci-wrap"><div class="notice notice-error"><p><strong>Hesabdar:</strong> ووکامرس فعال نیست یا هنوز بارگذاری نشده است.</p></div></div>';
        return;
    }

    try {
        wci_orders_page_render();
    } catch ( Throwable $e ) {
        echo '<div class="wrap wci-wrap"><div class="notice notice-error"><p><strong>خطا در لیست سفارش‌ها:</strong> '
            . esc_html( $e->getMessage() ) . '</p>';
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            echo '<pre style="direction:ltr;text-align:left;overflow:auto">' . esc_html( $e->getFile() . ':' . $e->getLine() ) . '</pre>';
        }
        echo '<p>پوشه <code>wp-content/plugins/hesabdar</code> را حذف کنید و ZIP کامل Hesabdar را دوباره آپلود کنید.</p></div></div>';
    }
}

function wci_orders_page_render() {
    $filters            = wci_get_current_filters();
    $bulk_msg           = '';
    $bulk_msg_type      = '';

    if (
        class_exists( 'WAP_Order_Service' )
        && method_exists( 'WAP_Order_Service', 'process_bulk_action' )
        && isset( $_POST['wci_bulk_apply'] )
        && check_admin_referer( 'wci_bulk_orders' )
    ) {
        $bulk_action = WAP_Order_Service::bulk_action_from_request();
        $bulk_ids    = class_exists( 'WCI_Bulk_Invoice' )
            ? WCI_Bulk_Invoice::parse_order_ids( wp_unslash( $_POST ) )
            : array_map( 'absint', (array) ( $_POST['order_ids'] ?? array() ) );

        // چاپ/دانلود همه نتایج فیلتر فعلی — بدون محدودیت تعداد
        if ( in_array( $bulk_action, array( 'print_invoices_filtered', 'download_invoices_filtered' ), true ) ) {
            list( $all_filtered ) = wci_get_filtered_orders( true );
            $bulk_ids = array();
            foreach ( (array) $all_filtered as $o ) {
                if ( is_object( $o ) && method_exists( $o, 'get_id' ) ) {
                    $bulk_ids[] = (int) $o->get_id();
                }
            }
        }

        $result = WAP_Order_Service::process_bulk_action( $bulk_action, $bulk_ids );
        // دانلود/چاپ فاکتور را همان‌جا سرو کن تا صفحه سفید / ریدایرکت شکسته نشود
        if (
            ! empty( $result['ok'] )
            && ! empty( $result['order_ids'] )
            && ! empty( $result['mode'] )
            && class_exists( 'WCI_Bulk_Invoice' )
        ) {
            WCI_Bulk_Invoice::serve( (array) $result['order_ids'], (string) $result['mode'] );
        }
        if ( ! empty( $result['redirect'] ) && ! headers_sent() ) {
            wp_safe_redirect( $result['redirect'] );
            exit;
        }
        $bulk_msg      = $result['message'] ?? '';
        $bulk_msg_type = ! empty( $result['ok'] ) ? 'success' : 'error';
    }

    $wci_result = wci_get_filtered_orders();
    $orders = $wci_result[0];
    $total  = $wci_result[1];
    $per_page           = $filters['per_page'];
    $paged              = $filters['paged'];
    $total_pages        = $per_page > 0 ? (int) ceil( $total / $per_page ) : 1;

    // Payment methods list — HPOS compatible
    global $wpdb;
    if ( wci_is_hpos() ) {
        $methods = $wpdb->get_col( "SELECT DISTINCT payment_method FROM {$wpdb->prefix}wc_orders WHERE type='shop_order' AND payment_method != '' ORDER BY payment_method" );
    } else {
        $methods = $wpdb->get_col( "SELECT DISTINCT meta_value FROM {$wpdb->postmeta} WHERE meta_key='_payment_method' AND meta_value != '' ORDER BY meta_value" );
    }

    echo '<div class="wrap wci-wrap">';
    if ( isset( $_GET['wci_trashed'] ) ) {
        echo '<div class="notice notice-success is-dismissible"><p>سفارش به سطل زباله منتقل شد.</p></div>';
    }
    if ( $bulk_msg !== '' ) {
        $cls = $bulk_msg_type === 'success' ? 'notice-success' : 'notice-error';
        echo '<div class="notice ' . esc_attr( $cls ) . ' is-dismissible"><p>' . esc_html( $bulk_msg ) . '</p></div>';
    }
    echo '<h1>لیست سفارش‌ها';
    if ( wci_order_service_ready( 'can_manage' ) && WAP_Order_Service::can_manage() ) {
        echo ' <a href="' . esc_url( WAP_Order_Service::edit_url( 0 ) ) . '" class="page-title-action">+ افزودن سفارش</a>';
    }
    echo '</h1>';

    // ── Filter bar ──
    echo '<form method="get" class="wci-filter-bar">';
    echo '<input type="hidden" name="page" value="wci-orders">';
    echo '<input type="text" name="s" value="' . esc_attr( $filters['s'] ) . '" placeholder="جستجو: نام، ایمیل، شماره تماس..." class="regular-text">';

    echo '<select name="payment_method">';
    echo '<option value="">همه روش‌های پرداخت</option>';
    foreach ( $methods as $m ) {
        echo '<option value="' . esc_attr( $m ) . '" ' . selected( $filters['payment_method'], $m, false ) . '>' . esc_html( wci_payment_label( $m ) ) . '</option>';
    }
    echo '</select>';

    echo '<select name="order_status">';
    echo '<option value="">همه وضعیت‌ها</option>';
    foreach ( wc_get_order_statuses() as $slug => $label ) {
        $val = str_replace( 'wc-', '', $slug );
        echo '<option value="' . esc_attr( $val ) . '" ' . selected( $filters['order_status'], $val, false ) . '>' . esc_html( $label ) . '</option>';
    }
    echo '</select>';

    echo '<span class="wci-filter-label">🔃 مرتب‌سازی:</span>';
    echo '<select name="orderby">';
    foreach ( [
        'date'         => 'تاریخ سفارش',
        'order_number' => 'شماره سفارش',
        'total'        => 'مبلغ کل',
        'first_name'   => 'نام',
        'last_name'    => 'نام خانوادگی',
        'phone'        => 'شماره تماس',
        'city'         => 'شهر',
        'state'        => 'استان',
        'order_count'  => 'تعداد سفارش',
    ] as $val => $lbl ) {
        echo '<option value="' . $val . '" ' . selected( $filters['orderby'], $val, false ) . '>' . $lbl . '</option>';
    }
    echo '</select>';

    echo '<select name="order">';
    echo '<option value="DESC" ' . selected( $filters['order'], 'DESC', false ) . '>نزولی ↓</option>';
    echo '<option value="ASC"  ' . selected( $filters['order'], 'ASC',  false ) . '>صعودی ↑</option>';
    echo '</select>';

    echo '<span class="wci-filter-label" style="margin-right:8px">📄 هر صفحه:</span>';
    echo '<select name="per_page" id="wci_per_page">';
    foreach ( [ 10, 25, 50, 100, 200 ] as $n ) {
        echo '<option value="' . $n . '" ' . selected( $per_page, $n, false ) . '>' . $n . ' مورد</option>';
    }
    echo '</select>';

    // Date range filter
    $is_jalali = wci_is_jalali();
    $date_placeholder_from = $is_jalali ? '۱۴۰۳/۰۱/۰۱' : 'YYYY-MM-DD';
    $date_placeholder_to   = $is_jalali ? '۱۴۰۳/۱۲/۲۹' : 'YYYY-MM-DD';
    $date_label = $is_jalali ? 'بازه شمسی' : 'بازه میلادی';
    echo '<span class="wci-filter-label" style="margin-right:8px">📅 ' . $date_label . ':</span>';
    if ( $is_jalali ) {
        echo '<input type="text" name="date_from" id="wci_date_from" value="' . esc_attr( $filters['date_from'] ) . '" placeholder="' . $date_placeholder_from . '" class="wci-date-input" style="width:110px;direction:ltr" autocomplete="off">';
        echo '<span style="margin:0 4px">تا</span>';
        echo '<input type="text" name="date_to" id="wci_date_to" value="' . esc_attr( $filters['date_to'] ) . '" placeholder="' . $date_placeholder_to . '" class="wci-date-input" style="width:110px;direction:ltr" autocomplete="off">';
    } else {
        echo '<input type="date" name="date_from" id="wci_date_from" value="' . esc_attr( $filters['date_from'] ) . '" class="wci-date-input">';
        echo '<span style="margin:0 4px">تا</span>';
        echo '<input type="date" name="date_to" id="wci_date_to" value="' . esc_attr( $filters['date_to'] ) . '" class="wci-date-input">';
    }

    echo '<button type="submit" class="button button-primary">اعمال فیلتر</button>';
    echo '<a href="' . esc_url( admin_url( 'admin.php?page=wci-orders' ) ) . '" class="button">پاک کردن</a>';
    echo '</form>';

    // Jalali date picker helper
    if ( $is_jalali ) { wci_print_jalali_picker_script(); }

    // ── Export buttons ──
    $export_params = array_filter( array_diff_key( $filters, [ 'paged' => 1 ] ) );
    $csv_url = esc_url( add_query_arg( array_merge( $export_params, [ 'action' => 'wci_export_csv' ] ), admin_url( 'admin-post.php' ) ) );
    $pdf_url = esc_url( add_query_arg( array_merge( $export_params, [ 'action' => 'wci_export_pdf' ] ), admin_url( 'admin-post.php' ) ) );

    echo '<div class="wci-export-bar">';
    echo '<strong>خروجی:</strong> ';
    echo '<button type="button" class="button wci-btn-excel" id="wci_open_csv_modal" data-url="' . $csv_url . '">📊 Excel / Google Sheets (CSV)</button> ';
    echo '<a href="' . $pdf_url . '" class="button wci-btn-pdf" target="_blank">🖨 PDF</a>';
    echo '<span class="wci-export-hint">⬆ خروجی با همان سورت و فیلتر فعلی دانلود می‌شود</span>';
    if ( ! empty( WAP_Baget_Fields::get_export_columns() ) ) {
        echo '<span class="wci-export-hint"> — فیلدهای Baget (کد ملی و سفارشی) در ستون‌های CSV موجود است</span>';
    }
    echo '</div>';

    // ── CSV column modal ──
    $all_cols = WAP_Baget_Fields::get_merged_export_columns();
    ?>
    <div id="wci-csv-modal" style="display:none">
        <div id="wci-csv-overlay"></div>
        <div id="wci-csv-box">
            <h2>📊 انتخاب ستون‌های خروجی Excel</h2>
            <p class="wci-modal-hint">ستون‌هایی که می‌خواهید در فایل CSV باشند را انتخاب کنید:</p>
            <div class="wci-col-grid">
                <?php foreach ( $all_cols as $key => $label ) : ?>
                <label class="wci-col-item">
                    <input type="checkbox" name="wci_col[]" value="<?php echo esc_attr( $key ); ?>" checked>
                    <span><?php echo esc_html( $label ); ?></span>
                </label>
                <?php endforeach; ?>
            </div>
            <div class="wci-modal-actions">
                <button type="button" id="wci_col_all" class="button">✓ همه</button>
                <button type="button" id="wci_col_none" class="button">✗ هیچ‌کدام</button>
                <button type="button" id="wci_csv_download" class="button wci-btn-excel" style="margin-right:auto">📥 دانلود CSV</button>
                <button type="button" id="wci_close_modal" class="button">انصراف</button>
            </div>
        </div>
    </div>
    <script>
    jQuery(function($){
        var baseUrl = $('#wci_open_csv_modal').data('url');
        $('#wci_open_csv_modal').on('click', function(){ $('#wci-csv-modal').fadeIn(150); });
        $('#wci_close_modal, #wci-csv-overlay').on('click', function(){ $('#wci-csv-modal').fadeOut(150); });
        $('#wci_col_all').on('click', function(){ $('input[name="wci_col[]"]').prop('checked', true); });
        $('#wci_col_none').on('click', function(){ $('input[name="wci_col[]"]').prop('checked', false); });
        $('#wci_csv_download').on('click', function(){
            var cols = $('input[name="wci_col[]"]:checked').map(function(){ return this.value; }).get().join(',');
            if (!cols) { alert('حداقل یک ستون انتخاب کنید.'); return; }
            window.location.href = baseUrl + '&wci_cols=' + encodeURIComponent(cols);
            $('#wci-csv-modal').fadeOut(150);
        });
    });
    </script>
    <?php

    // ── Table + bulk actions ──
    $baget_cols     = WAP_Baget_Fields::table_column_count();
    $can_bulk       = wci_order_service_ready( 'can_change_status' ) && WAP_Order_Service::can_change_status();
    $col_count      = 9 + $baget_cols + ( $can_bulk ? 1 : 0 );
    $is_jalali_date = wci_is_jalali();

    echo '<form method="post" id="wci-orders-bulk-form" action="' . esc_url( admin_url( 'admin.php?page=wci-orders' ) ) . '">';
    wp_nonce_field( 'wci_bulk_orders' );
    foreach ( $filters as $fk => $fv ) {
        echo '<input type="hidden" name="' . esc_attr( $fk ) . '" value="' . esc_attr( (string) $fv ) . '">';
    }
    echo '<input type="hidden" name="page" value="wci-orders">';

    if ( $can_bulk && method_exists( 'WAP_Order_Service', 'render_bulk_actions_bar' ) ) {
        WAP_Order_Service::render_bulk_actions_bar( 'top', 'wci-bulk-action-top' );
    }

    echo '<table class="widefat wci-table wci-orders-table">';
    echo '<thead><tr>';
    if ( $can_bulk ) {
        echo '<td class="manage-column column-cb check-column"><input type="checkbox" id="wci-cb-select-all"></td>';
    }
    echo '<th>' . wci_sort_link( 'شماره سفارش', 'order_number', $filters ) . '</th>
        <th>' . wci_sort_link( 'نام', 'first_name', $filters ) . ' / ' . wci_sort_link( 'نام خانوادگی', 'last_name', $filters ) . '</th>
        <th>' . wci_sort_link( 'شماره تماس', 'phone', $filters ) . '</th>
        <th>' . wci_sort_link( 'شهر', 'city', $filters ) . '</th>
        <th>محصول</th>
        <th>وضعیت</th>
        <th>' . wci_sort_link( 'مبلغ کل', 'total', $filters ) . '</th>
        <th>' . wci_sort_link( 'تاریخ', 'date', $filters ) . '</th>';
    WAP_Baget_Fields::render_table_headers();
    echo '<th>عملیات</th>
    </tr></thead><tbody>';

    if ( empty( $orders ) ) {
        echo '<tr><td colspan="' . esc_attr( (string) $col_count ) . '" style="text-align:center;padding:30px">سفارشی یافت نشد.</td></tr>';
    }

    foreach ( $orders as $order ) {
        $inv_url      = WCI_Invoice::admin_view_url( $order->get_id() );
        $inv_dl_url   = WCI_Invoice::admin_download_url( $order->get_id() );

        $edit_url = wci_order_service_ready( 'can_manage' ) && WAP_Order_Service::can_manage()
            ? WAP_Order_Service::edit_url( $order->get_id() )
            : $order->get_edit_order_url();

        echo '<tr>';
        if ( $can_bulk ) {
            echo '<th scope="row" class="check-column"><input type="checkbox" name="order_ids[]" value="' . esc_attr( (string) $order->get_id() ) . '"></th>';
        }
        echo '<td><a href="' . esc_url( $edit_url ) . '">#' . esc_html( $order->get_order_number() ) . '</a></td>';
        echo '<td>' . esc_html( $order->get_formatted_billing_full_name() ) . '</td>';
        echo '<td style="direction:ltr;text-align:right">' . esc_html( $order->get_billing_phone() ) . '</td>';
        echo '<td>' . esc_html( $order->get_billing_city() ) . '</td>';
        echo '<td class="wci-products-cell">';
        if ( class_exists( 'WAP_Data' ) && method_exists( 'WAP_Data', 'order_products_lines' ) ) {
            foreach ( WAP_Data::order_products_lines( $order ) as $line ) {
                echo '<div class="wci-product-line">' . esc_html( $line ) . '</div>';
            }
        } elseif ( class_exists( 'WAP_Data' ) && method_exists( 'WAP_Data', 'order_products_summary' ) ) {
            echo esc_html( WAP_Data::order_products_summary( $order ) );
        } else {
            echo '—';
        }
        echo '</td>';
        echo '<td>';
        if ( class_exists( 'WAP_Order_Service' ) && method_exists( 'WAP_Order_Service', 'render_status_badge' ) ) {
            WAP_Order_Service::render_status_badge( $order, 'wci' );
        } elseif ( class_exists( 'WAP_Order_Service' ) && method_exists( 'WAP_Order_Service', 'render_status_cell' ) ) {
            WAP_Order_Service::render_status_cell( $order, 'wci' );
        } else {
            echo '<span class="wci-status wci-status--' . esc_attr( $order->get_status() ) . '">' . esc_html( wc_get_order_status_name( $order->get_status() ) ) . '</span>';
        }
        echo '</td>';
        echo '<td><strong>' . wp_kses_post( $order->get_formatted_order_total() ) . '</strong></td>';
        echo '<td class="wci-date-cell">';
        if ( function_exists( 'wci_order_date_cell' ) ) {
            echo wci_order_date_cell( $order->get_date_created(), $is_jalali_date );
        } else {
            echo esc_html( wc_format_datetime( $order->get_date_created() ) );
        }
        echo '</td>';
        WAP_Baget_Fields::render_table_cells( $order );
        echo '<td class="wci-order-actions">';
        echo '<a href="' . esc_url( $inv_url ) . '" class="button button-small" target="_blank" rel="noopener noreferrer">مشاهده فاکتور</a> ';
        echo '<a href="' . esc_url( $inv_dl_url ) . '" class="button button-small">دانلود فاکتور</a> ';
        echo '<a href="' . esc_url( $edit_url ) . '" class="button button-small">ویرایش</a>';
        echo '</td>';
        echo '</tr>';
    }

    echo '</tbody></table>';

    if ( $can_bulk && method_exists( 'WAP_Order_Service', 'render_bulk_actions_bar' ) ) {
        WAP_Order_Service::render_bulk_actions_bar( 'bottom', 'wci-bulk-action-bottom' );
    }
    echo '</form>';
    ?>
    <script>
    jQuery(function($){
        $('#wci-cb-select-all').on('change', function(){
            $('#wci-orders-bulk-form input[name="order_ids[]"]').prop('checked', this.checked);
        });
        // بسته‌بندی همه شناسه‌ها در یک فیلد JSON تا محدودیت max_input_vars مانع چاپ زیاد نشود
        $('#wci-orders-bulk-form').on('submit', function(){
            var ids = [];
            $(this).find('input[name="order_ids[]"]:checked').each(function(){ ids.push(this.value); });
            var $h = $('#wci-order-ids-json');
            if (!$h.length) {
                $h = $('<input type="hidden" name="order_ids_json" id="wci-order-ids-json">');
                $(this).append($h);
            }
            $h.val(JSON.stringify(ids));
            var $a1 = $(this).find('select[name="wci_bulk_action"]');
            var $a2 = $(this).find('select[name="wci_bulk_action2"]');
            if ($a2.val()) { $a1.prop('disabled', true); }
            else if ($a1.val()) { $a2.prop('disabled', true); }
            var act = ($a2.val() || $a1.val() || '');
            // دانلود/چاپ فاکتور در تب جدید تا صفحه لیست سفید نشود
            if (String(act).indexOf('download_invoices') === 0 || String(act).indexOf('print_invoices') === 0) {
                this.target = '_blank';
            } else {
                this.target = '';
            }
        });
    });
    </script>
    <?php

    // ── Pagination ──
    $from = $total > 0 ? ( $paged - 1 ) * $per_page + 1 : 0;
    $to   = min( $total, $paged * $per_page );
    echo '<div class="wci-pagination-bar">';
    echo '<span class="wci-count">نمایش <strong>' . $from . '–' . $to . '</strong> از <strong>' . $total . '</strong> سفارش</span>';

    if ( $total_pages > 1 ) {
        echo '<div class="wci-pages">';
        $base_pg = array_merge( $filters, [ 'page' => 'wci-orders' ] );
        if ( $paged > 1 ) {
            echo '<a href="' . esc_url( add_query_arg( array_merge( $base_pg, [ 'paged' => $paged - 1 ] ), admin_url( 'admin.php' ) ) ) . '" class="button">→ قبلی</a>';
        }
        $start = max( 1, $paged - 3 );
        $end   = min( $total_pages, $paged + 3 );
        if ( $start > 1 ) echo '<span class="wci-pg-dots">…</span>';
        for ( $i = $start; $i <= $end; $i++ ) {
            $cls = $i === $paged ? 'button button-primary wci-pg-active' : 'button';
            echo '<a href="' . esc_url( add_query_arg( array_merge( $base_pg, [ 'paged' => $i ] ), admin_url( 'admin.php' ) ) ) . '" class="' . $cls . '">' . $i . '</a>';
        }
        if ( $end < $total_pages ) echo '<span class="wci-pg-dots">…</span>';
        if ( $paged < $total_pages ) {
            echo '<a href="' . esc_url( add_query_arg( array_merge( $base_pg, [ 'paged' => $paged + 1 ] ), admin_url( 'admin.php' ) ) ) . '" class="button">بعدی ←</a>';
        }
        echo '</div>';
    }
    echo '</div></div>';
}

// ─── Settings page ────────────────────────────────────────────────────────────
add_action( 'admin_enqueue_scripts', function( $hook ) {
    if ( strpos( $hook, 'wci-settings' ) === false ) return;
    wp_enqueue_media();
} );

function wci_settings_page() {
    if ( ! hesabdar_user_can_wci() ) {
        wp_die( 'Unauthorized' );
    }
    if ( isset( $_POST['wci_save_settings'] ) && check_admin_referer( 'wci_settings' ) ) {
        update_option( 'wci_invoice_settings', [
            'company_name'    => sanitize_text_field( $_POST['company_name'] ),
            'company_address' => sanitize_textarea_field( $_POST['company_address'] ),
            'company_phone'   => sanitize_text_field( $_POST['company_phone'] ),
            'footer_text'     => sanitize_textarea_field( $_POST['footer_text'] ),
            'logo_url'        => esc_url_raw( $_POST['logo_url'] ),
            'logo_id'         => absint( $_POST['logo_id'] ?? 0 ),
            'primary_color'   => sanitize_hex_color( $_POST['primary_color'] ?? '#2271b1' ),
            'show_signature'  => isset( $_POST['show_signature'] ) ? 1 : 0,
        ] );
        echo '<div class="notice notice-success"><p>تنظیمات ذخیره شد.</p></div>';
    }
    $s      = get_option( 'wci_invoice_settings', [] );
    $logo   = $s['logo_url'] ?? '';
    $logo_id = $s['logo_id'] ?? 0;

    echo '<div class="wrap wci-wrap"><h1>تنظیمات فاکتور</h1>';
    echo '<form method="post" enctype="multipart/form-data">';
    wp_nonce_field( 'wci_settings' );
    echo '<table class="form-table wci-settings-table"><tbody>';

    $wci_fields = array(
        'company_name'    => array( 'text',     'نام شرکت / فروشگاه' ),
        'company_address' => array( 'textarea', 'آدرس' ),
        'company_phone'   => array( 'text',     'شماره تماس فروشگاه' ),
        'footer_text'     => array( 'textarea', 'متن پاورقی فاکتور' ),
        'primary_color'   => array( 'color',    'رنگ اصلی فاکتور' ),
    );
    foreach ( $wci_fields as $key => $field_info ) {
        $type  = $field_info[0];
        $label = $field_info[1];
        $val   = isset( $s[ $key ] ) ? $s[ $key ] : '';
        echo '<tr><th><label for="' . $key . '">' . $label . '</label></th><td>';
        if ( $type === 'textarea' ) {
            echo '<textarea name="' . $key . '" id="' . $key . '" rows="3" class="large-text">' . esc_textarea( $val ) . '</textarea>';
        } elseif ( $type === 'color' ) {
            echo '<input type="color" name="' . $key . '" id="' . $key . '" value="' . ( $val ? $val : '#2271b1' ) . '">';
        } else {
            echo '<input type="text" name="' . $key . '" id="' . $key . '" value="' . esc_attr( $val ) . '" class="regular-text">';
        }
        echo '</td></tr>';
    }

    echo '<tr><th><label>لوگوی فاکتور</label></th><td>';
    echo '<input type="hidden" name="logo_url" id="wci_logo_url" value="' . esc_attr( $logo ) . '">';
    echo '<input type="hidden" name="logo_id"  id="wci_logo_id"  value="' . esc_attr( $logo_id ) . '">';
    echo '<div id="wci_logo_preview" style="margin-bottom:8px">' . ( $logo ? '<img src="' . esc_url( $logo ) . '" style="max-height:80px;border:1px solid #ddd;border-radius:4px;padding:4px">' : '' ) . '</div>';
    echo '<button type="button" id="wci_upload_logo" class="button">📁 انتخاب از رسانه‌ها</button>';
    if ( $logo ) echo ' <button type="button" id="wci_remove_logo" class="button">✕ حذف لوگو</button>';
    echo '<p class="description" style="margin-top:6px">فرمت‌های پشتیبانی: PNG، JPG، SVG</p></td></tr>';

    echo '<tr><th>امضا / مهر</th><td><label><input type="checkbox" name="show_signature" value="1" ' . checked( $s['show_signature'] ?? 0, 1, false ) . '> نمایش محل امضا در فاکتور</label></td></tr>';
    echo '</tbody></table>';
    echo '<p class="submit"><button type="submit" name="wci_save_settings" class="button button-primary">ذخیره تنظیمات</button></p>';

    $sample = wc_get_orders( [ 'limit' => 1 ] );
    if ( $sample ) {
        $url = add_query_arg( [ 'page' => 'wci-orders', 'wci_invoice' => 1, 'order_id' => $sample[0]->get_id() ], admin_url( 'admin.php' ) );
        echo '<p><a href="' . esc_url( $url ) . '" target="_blank" class="button">👁 پیش‌نمایش فاکتور</a></p>';
    }
    echo '</form>';
    ?>
    <script>
    jQuery(function($){
        var frame;
        $('#wci_upload_logo').on('click', function(e){
            e.preventDefault();
            if ( frame ) { frame.open(); return; }
            frame = wp.media({ title: 'انتخاب لوگو', button: { text: 'انتخاب' }, multiple: false, library: { type: 'image' } });
            frame.on('select', function(){
                var att = frame.state().get('selection').first().toJSON();
                $('#wci_logo_url').val( att.url );
                $('#wci_logo_id').val( att.id );
                $('#wci_logo_preview').html('<img src="'+att.url+'" style="max-height:80px;border:1px solid #ddd;border-radius:4px;padding:4px">');
                if ( !$('#wci_remove_logo').length ) {
                    $('#wci_upload_logo').after(' <button type="button" id="wci_remove_logo" class="button">✕ حذف لوگو</button>');
                    bindRemove();
                }
            });
            frame.open();
        });
        function bindRemove(){
            $(document).on('click','#wci_remove_logo', function(e){
                e.preventDefault();
                $('#wci_logo_url').val('');
                $('#wci_logo_id').val('0');
                $('#wci_logo_preview').html('');
                $(this).remove();
            });
        }
        bindRemove();
    });
    </script>
    <?php
    echo '</div>';
}

// ─── Helpers ──────────────────────────────────────────────────────────────────
function wci_is_hpos() {
    return class_exists( 'Automattic\WooCommerce\Utilities\OrderUtil' )
        && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
}

function wci_is_jalali() {
    $locale = get_locale();
    return strpos( $locale, 'fa' ) === 0;
}

// تبدیل تاریخ شمسی به میلادی
function wci_jalali_to_gregorian( $jy, $jm, $jd ) {
    $jy  = (int) $jy;
    $jm  = (int) $jm;
    $jd  = (int) $jd;
    $jy += 1595;
    $days = -355668 + ( 365 * $jy ) + ( (int) floor( $jy / 33 ) * 8 ) + (int) floor( ( ( $jy % 33 ) + 3 ) / 4 ) + $jd
            + ( $jm < 7 ? ( $jm - 1 ) * 31 : ( ( $jm - 7 ) * 30 ) + 186 );
    $gy   = 400 * (int) floor( $days / 146097 );
    $days = $days % 146097;
    if ( $days > 36524 ) {
        $gy  += 100 * (int) floor( --$days / 36524 );
        $days = $days % 36524;
        if ( $days >= 365 ) { $days++; }
    }
    $gy  += 4 * (int) floor( $days / 1461 );
    $days = $days % 1461;
    if ( $days > 364 ) {
        $gy  += (int) floor( ( $days - 1 ) / 365 );
        $days = ( $days - 1 ) % 365;
    }
    $gd    = $days + 1;
    $leap  = ( $gy % 4 === 0 && ( $gy % 100 !== 0 || $gy % 400 === 0 ) );
    $days_in_month = array( 0, 31, $leap ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31 );
    $gm = 0;
    for ( $i = 1; $gd > $days_in_month[ $i ]; $i++ ) {
        $gd -= $days_in_month[ $i ];
    }
    $gm = $i;
    return array( $gy, $gm, $gd );
}

// تبدیل تاریخ میلادی به شمسی
function wci_gregorian_to_jalali( $gy, $gm, $gd ) {
    $gy  = (int) $gy;
    $gm  = (int) $gm;
    $gd  = (int) $gd;
    // Cumulative days before each month (non-leap year)
    $g_y_m = array( 0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334 );
    $jy  = ( $gy <= 1600 ) ? 0 : 979;
    $gy -= ( $gy <= 1600 ) ? 621 : 1600;
    $gy2 = ( $gm > 2 ) ? ( $gy + 1 ) : $gy;
    $days = ( 365 * $gy )
          + (int) ( ( $gy2 + 3 ) / 4 )
          - (int) ( ( $gy2 + 99 ) / 100 )
          + (int) ( ( $gy2 + 399 ) / 400 )
          - 80 + $gd + $g_y_m[ $gm - 1 ];
    $jy  += 33 * (int) ( $days / 12053 );
    $days = $days % 12053;
    $jy  += 4 * (int) ( $days / 1461 );
    $days = $days % 1461;
    if ( $days > 365 ) {
        $jy  += (int) ( ( $days - 1 ) / 365 );
        $days = ( $days - 1 ) % 365;
    }
    $jm_days = array( 31, 31, 31, 31, 31, 31, 30, 30, 30, 30, 30, 29 );
    for ( $i = 0; $i < 11 && $days >= $jm_days[ $i ]; $i++ ) {
        $days -= $jm_days[ $i ];
    }
    return array( $jy, $i + 1, $days + 1 );
}

// فرمت تاریخ با توجه به زبان سایت
function wci_format_date( $date_obj, $is_jalali = false ) {
    if ( ! $date_obj ) return '—';
    $y = (int)$date_obj->date( 'Y' );
    $m = (int)$date_obj->date( 'n' );
    $d = (int)$date_obj->date( 'j' );
    if ( $is_jalali ) {
        $j = wci_gregorian_to_jalali( $y, $m, $d );
        return sprintf( '%d/%02d/%02d', $j[0], $j[1], $j[2] );
    }
    return sprintf( '%04d-%02d-%02d', $y, $m, $d );
}

/** تاریخ چندخطی برای جدول سفارش‌ها (مثل: ۲۴ / تیر / ۱۴۰۵) */
function wci_order_date_cell( $date_obj, $is_jalali = false ) {
    if ( ! $date_obj ) {
        return '—';
    }
    if ( $is_jalali && function_exists( 'wci_gregorian_to_jalali' ) ) {
        $y   = (int) $date_obj->date( 'Y' );
        $m   = (int) $date_obj->date( 'n' );
        $d   = (int) $date_obj->date( 'j' );
        $j   = wci_gregorian_to_jalali( $y, $m, $d );
        $months = array( 'فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند' );
        $jm  = (int) ( $j[1] ?? 1 );
        $name = $months[ $jm - 1 ] ?? '';
        return '<span class="wci-date-pretty"><span class="wci-date-day">' . esc_html( (string) ( $j[2] ?? '' ) ) . '</span>'
            . '<span class="wci-date-month">' . esc_html( $name ) . '</span>'
            . '<span class="wci-date-year">' . esc_html( (string) ( $j[0] ?? '' ) ) . '</span></span>';
    }
    return esc_html( wc_format_datetime( $date_obj ) );
}

// تبدیل رشته تاریخ (شمسی یا میلادی) به timestamp
function wci_date_to_timestamp( $date_str, $end_of_day = false ) {
    $date_str = trim( $date_str );
    if ( empty( $date_str ) ) return 0;
    // شمسی: 1403/06/01 یا ۱۴۰۳/۰۶/۰۱
    $normalized = strtr( $date_str, array(
        '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
        '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
    ) );
    if ( preg_match( '/^(\d{4})\/(\d{1,2})\/(\d{1,2})$/', $normalized, $m ) && (int) $m[1] > 1200 ) {
        $greg = wci_jalali_to_gregorian( $m[1], $m[2], $m[3] );
        $h = $end_of_day ? 23 : 0;
        $i = $end_of_day ? 59 : 0;
        $s = $end_of_day ? 59 : 0;
        return mktime( $h, $i, $s, $greg[1], $greg[2], $greg[0] );
    }
    // میلادی: 2024-01-15
    $ts = strtotime( $date_str );
    if ( $end_of_day && $ts ) {
        $ts = mktime( 23, 59, 59, (int) date( 'n', $ts ), (int) date( 'j', $ts ), (int) date( 'Y', $ts ) );
    }
    return $ts ?: 0;
}

// فیلتر قطعی بازه تاریخ روی نتایج، مستقل از رفتار پارامتر date_created در wc_get_orders()
// (که در برخی نصب‌ها/نسخه‌های ووکامرس به‌درستی بازه را محدود نمی‌کند) — تضمین می‌کند
// خروجی همیشه دقیقاً داخل بازه انتخاب‌شده باشد.
function wci_filter_orders_by_date_range( array $orders, int $ts_from, int $ts_to ): array {
    if ( ! $ts_from && ! $ts_to ) return $orders;
    return array_values( array_filter( $orders, function( $order ) use ( $ts_from, $ts_to ) {
        $created = $order->get_date_created();
        if ( ! $created ) return false;
        $ts = $created->getTimestamp();
        if ( $ts_from && $ts < $ts_from ) return false;
        if ( $ts_to && $ts > $ts_to ) return false;
        return true;
    } ) );
}

// تاریخ شمسی دقیق امروز — بر اساس ساعت/منطقه زمانی واقعی سرور وردپرس (نه تخمین)
function wci_get_today_jalali() {
    $now = current_time( 'timestamp' ); // با احتساب تنظیمات منطقه زمانی سایت
    list( $jy, $jm, $jd ) = wci_gregorian_to_jalali( (int) date( 'Y', $now ), (int) date( 'n', $now ), (int) date( 'j', $now ) );
    return array( 'y' => $jy, 'm' => $jm, 'd' => $jd );
}

function wci_jalali_month_length( $jy, $jm ) {
    if ( $jm <= 6 ) return 31;
    if ( $jm <= 11 ) return 30;
    // اسفند: ۲۹ یا ۳۰ (سال کبیسه) — تشخیص با تبدیل رفت‌وبرگشت
    $g1 = wci_jalali_to_gregorian( $jy, 12, 30 );
    $back = wci_gregorian_to_jalali( $g1[0], $g1[1], $g1[2] );
    return ( $back[1] === 12 && $back[2] === 30 ) ? 30 : 29;
}

// پیکر Jalali date picker (inline CSS/JS ساده) — تاریخ «امروز» دقیقاً از سرور (PHP) محاسبه می‌شود
function wci_print_jalali_picker_script() {
    $today = wci_get_today_jalali();
    $jy    = $today['y'];
    $jm    = $today['m'];
    $jd    = $today['d'];

    // بازه هفته جاری (شنبه تا جمعه) — با محاسبه دقیق روز هفته میلادی معادل
    $now_ts     = current_time( 'timestamp' );
    $dow        = (int) date( 'w', $now_ts ); // 0=یکشنبه ... 6=شنبه در PHP است، اما اینجا از تقویم میلادی محلی استفاده می‌کنیم
    // در PHP: 0=Sunday..6=Saturday. هفته فارسی از شنبه شروع می‌شود.
    $days_since_saturday = ( $dow + 1 ) % 7; // شنبه=0
    $week_start_ts = $now_ts - $days_since_saturday * DAY_IN_SECONDS;
    $week_end_ts   = $week_start_ts + 6 * DAY_IN_SECONDS;
    list( $wsy, $wsm, $wsd ) = wci_gregorian_to_jalali( (int) date( 'Y', $week_start_ts ), (int) date( 'n', $week_start_ts ), (int) date( 'j', $week_start_ts ) );
    list( $wey, $wem, $wed ) = wci_gregorian_to_jalali( (int) date( 'Y', $week_end_ts ), (int) date( 'n', $week_end_ts ), (int) date( 'j', $week_end_ts ) );

    // فصل جاری
    $season_start_m = intdiv( $jm - 1, 3 ) * 3 + 1;
    $season_end_m   = $season_start_m + 2;
    $season_end_d   = wci_jalali_month_length( $jy, $season_end_m );

    // دیروز
    $yesterday_ts = $now_ts - DAY_IN_SECONDS;
    list( $yy, $ym, $yd ) = wci_gregorian_to_jalali( (int) date( 'Y', $yesterday_ts ), (int) date( 'n', $yesterday_ts ), (int) date( 'j', $yesterday_ts ) );

    // ۷ روز اخیر
    $seven_ago_ts = $now_ts - 6 * DAY_IN_SECONDS;
    list( $sy, $sm, $sd ) = wci_gregorian_to_jalali( (int) date( 'Y', $seven_ago_ts ), (int) date( 'n', $seven_ago_ts ), (int) date( 'j', $seven_ago_ts ) );

    $fmt = function( $y, $m, $d ) { return sprintf( '%d/%02d/%02d', $y, $m, $d ); };

    $presets = array(
        'امروز'       => array( $fmt( $jy, $jm, $jd ), $fmt( $jy, $jm, $jd ) ),
        'دیروز'       => array( $fmt( $yy, $ym, $yd ), $fmt( $yy, $ym, $yd ) ),
        '۷ روز اخیر'  => array( $fmt( $sy, $sm, $sd ), $fmt( $jy, $jm, $jd ) ),
        'این هفته'    => array( $fmt( $wsy, $wsm, $wsd ), $fmt( $wey, $wem, $wed ) ),
        'این ماه'     => array( $fmt( $jy, $jm, 1 ), $fmt( $jy, $jm, wci_jalali_month_length( $jy, $jm ) ) ),
        'این فصل'     => array( $fmt( $jy, $season_start_m, 1 ), $fmt( $jy, $season_end_m, $season_end_d ) ),
        'امسال'       => array( $fmt( $jy, 1, 1 ), $fmt( $jy, 12, wci_jalali_month_length( $jy, 12 ) ) ),
        'سال گذشته'   => array( $fmt( $jy - 1, 1, 1 ), $fmt( $jy - 1, 12, wci_jalali_month_length( $jy - 1, 12 ) ) ),
    );
    ?>
    <style>
    .wci-date-input { direction: ltr; font-family: monospace; }
    </style>
    <script>
    jQuery(function($){
        var monthNames = ['فروردین','اردیبهشت','خرداد','تیر','مرداد','شهریور','مهر','آبان','آذر','دی','بهمن','اسفند'];
        var jYear = <?php echo (int) $jy; ?>; // سال شمسی جاری — محاسبه دقیق سمت سرور
        var $bar = $('.wci-filter-bar');
        var $presets = $('<div class="wci-date-presets" style="width:100%;margin-top:4px;display:flex;flex-wrap:wrap;gap:4px"></div>');

        var quick = <?php echo wp_json_encode( $presets ); ?>;
        $.each(quick, function(label, range){
            var $btn = $('<button type="button" class="button" style="font-size:11px;padding:2px 6px;background:#f0f6fc">' + label + '</button>');
            $btn.on('click', function(){
                $('#wci_date_from').val(range[0]);
                $('#wci_date_to').val(range[1]);
            });
            $presets.append($btn);
        });

        for (var m = 1; m <= 12; m++) {
            (function(month){
                var endDay  = month <= 6 ? 31 : (month <= 11 ? 30 : 29);
                var from = jYear + '/' + (month < 10 ? '0'+month : month) + '/01';
                var to   = jYear + '/' + (month < 10 ? '0'+month : month) + '/' + endDay;
                var $btn = $('<button type="button" class="button" style="font-size:11px;padding:2px 6px">' + monthNames[month-1] + '</button>');
                $btn.on('click', function(){
                    $('#wci_date_from').val(from);
                    $('#wci_date_to').val(to);
                });
                $presets.append($btn);
            })(m);
        }
        $bar.after($presets);

        var todayJ = { y: <?php echo (int) $jy; ?>, m: <?php echo (int) $jm; ?>, d: <?php echo (int) $jd; ?> };
        if ( window.attachJalaliDatePicker ) {
            document.querySelectorAll('#wci_date_from, #wci_date_to').forEach(function(el){
                attachJalaliDatePicker(el, { today: todayJ });
            });
        }
    });
    </script>
    <?php
}

function wci_get_current_filters() {
    $src = ( isset( $_POST['wci_bulk_apply'], $_POST['_wpnonce'] )
        && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'wci_bulk_orders' ) ) ? $_POST : $_GET;
    return array(
        's'              => sanitize_text_field( isset( $src['s'] ) ? $src['s'] : '' ),
        'payment_method' => sanitize_text_field( isset( $src['payment_method'] ) ? $src['payment_method'] : '' ),
        'order_status'   => sanitize_text_field( isset( $src['order_status'] ) ? $src['order_status'] : '' ),
        'orderby'        => sanitize_text_field( isset( $src['orderby'] ) ? $src['orderby'] : 'date' ),
        'order'          => in_array( isset( $src['order'] ) ? $src['order'] : '', array( 'ASC', 'DESC' ) ) ? $src['order'] : 'DESC',
        'per_page'       => absint( isset( $src['per_page'] ) ? $src['per_page'] : 25 ),
        'paged'          => max( 1, absint( isset( $src['paged'] ) ? $src['paged'] : 1 ) ),
        'date_from'      => sanitize_text_field( isset( $src['date_from'] ) ? $src['date_from'] : '' ),
        'date_to'        => sanitize_text_field( isset( $src['date_to'] ) ? $src['date_to'] : '' ),
    );
}

function wci_get_filtered_orders( $all = false ) {
    $f = wci_get_current_filters();

    // همه وضعیت‌ها بدون پیشوند wc- برای سازگاری با HPOS
    $all_statuses = array_map( function( $s ) {
        return str_replace( 'wc-', '', $s );
    }, array_keys( wc_get_order_statuses() ) );

    $args = array(
        'limit'   => -1,
        'orderby' => 'date',
        'order'   => $f['order'],
        'return'  => 'objects',
        'type'    => 'shop_order',
        'status'  => $all_statuses,
    );

    if ( $f['order_status'] ) {
        $args['status'] = array( $f['order_status'] );
    }

    if ( $f['payment_method'] ) {
        $args['payment_method'] = $f['payment_method'];
    }

    // Date range filter
    $ts_from = $f['date_from'] ? wci_date_to_timestamp( $f['date_from'], false ) : 0;
    $ts_to   = $f['date_to']   ? wci_date_to_timestamp( $f['date_to'],   true  ) : 0;
    if ( $ts_from && $ts_to ) {
        $args['date_created'] = $ts_from . '...' . $ts_to;
    } elseif ( $ts_from ) {
        $args['date_created'] = '>=' . $ts_from;
    } elseif ( $ts_to ) {
        $args['date_created'] = '<=' . $ts_to;
    }

    if ( $f['s'] ) {
        global $wpdb;
        $like = '%' . $wpdb->esc_like( $f['s'] ) . '%';
        if ( wci_is_hpos() ) {
            // HPOS: جستجو در جدول wc_orders
            $ids = $wpdb->get_col( $wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}wc_orders
                 WHERE type = 'shop_order'
                 AND ( billing_first_name LIKE %s OR billing_last_name LIKE %s
                       OR billing_email LIKE %s OR billing_phone LIKE %s
                       OR billing_city LIKE %s )",
                $like, $like, $like, $like, $like
            ) );
        } else {
            // Classic: جستجو در postmeta
            $ids = $wpdb->get_col( $wpdb->prepare(
                "SELECT DISTINCT p.ID FROM {$wpdb->posts} p
                 INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID
                 WHERE p.post_type = 'shop_order'
                 AND pm.meta_key IN ('_billing_first_name','_billing_last_name','_billing_phone','_billing_email','_billing_city')
                 AND pm.meta_value LIKE %s",
                $like
            ) );
        }
        if ( empty( $ids ) ) return array( array(), 0 );
        $args['include'] = $ids;
    }

    $orders = wc_get_orders( $args );
    $orders = wci_filter_orders_by_date_range( $orders, $ts_from, $ts_to );

    // Sort by billing meta fields
    $asc = $f['order'] === 'ASC';
    switch ( $f['orderby'] ) {
        case 'total':
            usort( $orders, function( $a, $b ) use ( $asc ) {
                return $asc ? $a->get_total() - $b->get_total() : $b->get_total() - $a->get_total();
            } );
            break;
        case 'order_number':
            usort( $orders, function( $a, $b ) use ( $asc ) {
                return $asc ? (int)$a->get_order_number() - (int)$b->get_order_number() : (int)$b->get_order_number() - (int)$a->get_order_number();
            } );
            break;
        case 'first_name':
            usort( $orders, function( $a, $b ) use ( $asc ) {
                return $asc ? strcmp( $a->get_billing_first_name(), $b->get_billing_first_name() ) : strcmp( $b->get_billing_first_name(), $a->get_billing_first_name() );
            } );
            break;
        case 'last_name':
            usort( $orders, function( $a, $b ) use ( $asc ) {
                return $asc ? strcmp( $a->get_billing_last_name(), $b->get_billing_last_name() ) : strcmp( $b->get_billing_last_name(), $a->get_billing_last_name() );
            } );
            break;
        case 'phone':
            usort( $orders, function( $a, $b ) use ( $asc ) {
                return $asc ? strcmp( $a->get_billing_phone(), $b->get_billing_phone() ) : strcmp( $b->get_billing_phone(), $a->get_billing_phone() );
            } );
            break;
        case 'city':
            usort( $orders, function( $a, $b ) use ( $asc ) {
                return $asc ? strcmp( $a->get_billing_city(), $b->get_billing_city() ) : strcmp( $b->get_billing_city(), $a->get_billing_city() );
            } );
            break;
        case 'state':
            usort( $orders, function( $a, $b ) use ( $asc ) {
                return $asc ? strcmp( $a->get_billing_state(), $b->get_billing_state() ) : strcmp( $b->get_billing_state(), $a->get_billing_state() );
            } );
            break;
        case 'order_count':
            usort( $orders, function( $a, $b ) use ( $asc ) {
                $ca = wc_get_customer_order_count( $a->get_customer_id() );
                $cb = wc_get_customer_order_count( $b->get_customer_id() );
                return $asc ? $ca - $cb : $cb - $ca;
            } );
            break;
    }

    $total = count( $orders );
    if ( $all ) return array( $orders, $total );

    $slice = array_slice( $orders, ( $f['paged'] - 1 ) * $f['per_page'], $f['per_page'] );
    return array( $slice, $total );
}

function wci_sort_link( $label, $col, $f ) {
    $current  = $f['orderby'] === $col;
    $neworder = ( $current && $f['order'] === 'DESC' ) ? 'ASC' : 'DESC';
    $arrow    = $current ? ( $f['order'] === 'DESC' ? ' ↓' : ' ↑' ) : '';
    $url      = add_query_arg( array_merge( $f, [ 'orderby' => $col, 'order' => $neworder ] ), admin_url( 'admin.php?page=wci-orders' ) );
    return '<a href="' . esc_url( $url ) . '" class="wci-sort' . ( $current ? ' active' : '' ) . '">' . $label . $arrow . '</a>';
}

function wci_payment_label( $method ) {
    $labels = [
        'zarinpal' => 'زرین‌پال', 'wc_zpal' => 'زرین‌پال', 'WC_ZPal' => 'زرین‌پال',
        'wc-zarinpal' => 'زرین‌پال', 'zarinpal-pg' => 'زرین‌پال',
        'snapppay' => 'اسنپ‌پی', 'snapp_pay' => 'اسنپ‌پی',
        'WC_Gateway_SnappPay' => 'اسنپ‌پی', 'wc_gateway_snapppay' => 'اسنپ‌پی',
        'WC_Gateway_TorobPay' => 'ترب‌پی', 'torobpay' => 'ترب‌پی', 'wc_gateway_torobpay' => 'ترب‌پی',
        'wc_zibal' => 'زیبال', 'WC_Zibal' => 'زیبال', 'zibal' => 'زیبال',
        'idpay' => 'آیدی‌پی', 'nextpay' => 'نکست‌پی', 'aqayepay' => 'آقای‌پی',
        'wcdigipay' => 'دیجی‌پی', 'WCDigiPay' => 'دیجی‌پی', 'digipay' => 'دیجی‌پی',
        'wc_digipay' => 'دیجی‌پی', 'wc-digipay' => 'دیجی‌پی',
        'cod' => 'پرداخت در محل', 'bacs' => 'انتقال بانکی', 'cheque' => 'چک', '' => '—',
    ];
    return isset( $labels[ $method ] ) ? $labels[ $method ] : $method;
}

// ─── Products sales page ──────────────────────────────────────────────────────
function wci_products_page() {
    if ( ! hesabdar_user_can_wci() ) {
        wp_die( 'Unauthorized' );
    }

    $is_jalali     = wci_is_jalali();
    $date_from     = sanitize_text_field( isset( $_GET['date_from'] ) ? $_GET['date_from'] : '' );
    $date_to       = sanitize_text_field( isset( $_GET['date_to'] )   ? $_GET['date_to']   : '' );
    $product_id    = intval( isset( $_GET['product_id'] ) ? $_GET['product_id'] : 0 );
    $order_status  = sanitize_text_field( isset( $_GET['order_status'] ) ? $_GET['order_status'] : '' );
    $product_cat   = absint( isset( $_GET['product_cat'] ) ? $_GET['product_cat'] : 0 );
    $orderby_p     = sanitize_text_field( isset( $_GET['orderby'] )   ? $_GET['orderby']   : 'revenue' );
    $order_dir     = ( isset( $_GET['order'] ) && $_GET['order'] === 'ASC' ) ? 'ASC' : 'DESC';
    $paid_only     = ( $order_status === '__paid__' );
    $categories    = class_exists( 'WAP_Data' ) ? WAP_Data::get_product_categories() : array();

    // CSV export (handles both summary and drilldown)
    if ( isset( $_GET['action'] ) && $_GET['action'] === 'wci_export_products_csv' ) {
        check_admin_referer( 'wci_products_export' );
        wci_export_products_csv( $date_from, $date_to, $product_id, $order_status, $product_cat );
        exit;
    }

    // Build order args
    $all_statuses = array_map( function( $s ) { return str_replace( 'wc-', '', $s ); }, array_keys( wc_get_order_statuses() ) );
    $args = array( 'limit' => -1, 'return' => 'objects', 'type' => 'shop_order', 'status' => $all_statuses );
    if ( $order_status !== '' && $order_status !== '__paid__' ) {
        $args['status'] = array( $order_status );
    }
    $ts_from = $date_from ? wci_date_to_timestamp( $date_from, false ) : 0;
    $ts_to   = $date_to   ? wci_date_to_timestamp( $date_to,   true  ) : 0;
    if ( $ts_from && $ts_to ) { $args['date_created'] = $ts_from . '...' . $ts_to; }
    elseif ( $ts_from ) { $args['date_created'] = '>=' . $ts_from; }
    elseif ( $ts_to )   { $args['date_created'] = '<=' . $ts_to; }

    $orders = wc_get_orders( $args );
    $orders = wci_filter_orders_by_date_range( $orders, $ts_from, $ts_to );

    echo '<div class="wrap wci-wrap">';

    // ── Drilldown mode ────────────────────────────────────────────────────────
    if ( $product_id ) {
        $product_obj  = wc_get_product( $product_id );
        $product_name = $product_obj ? $product_obj->get_name() : '#' . $product_id;

        $back_url = add_query_arg( array(
            'page'         => 'wci-products',
            'date_from'    => $date_from,
            'date_to'      => $date_to,
            'order_status' => $order_status,
            'product_cat'  => $product_cat,
        ), admin_url( 'admin.php' ) );

        echo '<h1>سفارشات محصول: ' . esc_html( $product_name ) . '  <a href="' . esc_url( $back_url ) . '" class="button button-secondary" style="font-size:13px;vertical-align:middle">← بازگشت به لیست محصولات</a></h1>';

        // Filter bar (date range stays active)
        $date_ph_from = $is_jalali ? '۱۴۰۳/۰۱/۰۱' : 'YYYY-MM-DD';
        $date_ph_to   = $is_jalali ? '۱۴۰۳/۱۲/۲۹' : 'YYYY-MM-DD';
        echo '<form method="get" class="wci-filter-bar">';
        echo '<input type="hidden" name="page" value="wci-products">';
        echo '<input type="hidden" name="product_id" value="' . esc_attr( $product_id ) . '">';
        echo '<span class="wci-filter-label">📅 ' . ( $is_jalali ? 'بازه شمسی' : 'بازه میلادی' ) . ':</span>';
        if ( $is_jalali ) {
            echo '<input type="text" name="date_from" id="wci_date_from" value="' . esc_attr( $date_from ) . '" placeholder="' . $date_ph_from . '" class="wci-date-input" style="width:110px;direction:ltr" autocomplete="off">';
            echo '<span style="margin:0 4px">تا</span>';
            echo '<input type="text" name="date_to" id="wci_date_to" value="' . esc_attr( $date_to ) . '" placeholder="' . $date_ph_to . '" class="wci-date-input" style="width:110px;direction:ltr" autocomplete="off">';
        } else {
            echo '<input type="date" name="date_from" id="wci_date_from" value="' . esc_attr( $date_from ) . '">';
            echo '<span style="margin:0 4px">تا</span>';
            echo '<input type="date" name="date_to" id="wci_date_to" value="' . esc_attr( $date_to ) . '">';
        }
        echo '<select name="order_status"><option value="">همه وضعیت‌ها</option>';
        echo '<option value="__paid__"' . selected( $order_status, '__paid__', false ) . '>فقط موفق</option>';
        foreach ( wc_get_order_statuses() as $slug => $label ) {
            $val = str_replace( 'wc-', '', $slug );
            echo '<option value="' . esc_attr( $val ) . '"' . selected( $order_status, $val, false ) . '>' . esc_html( $label ) . '</option>';
        }
        echo '</select>';
        echo '<button type="submit" class="button button-primary">نمایش</button>';
        $exp_url = add_query_arg( array(
            'page'         => 'wci-products',
            'action'       => 'wci_export_products_csv',
            'product_id'   => $product_id,
            'date_from'    => $date_from,
            'date_to'      => $date_to,
            'order_status' => $order_status,
            'product_cat'  => $product_cat,
            '_wpnonce'     => wp_create_nonce( 'wci_products_export' ),
        ), admin_url( 'admin.php' ) );
        echo '<a href="' . esc_url( $exp_url ) . '" class="button wci-btn-excel" style="margin-right:auto">📊 خروجی Excel</a>';
        echo '</form>';
        if ( $is_jalali ) { wci_print_jalali_picker_script(); }

        $drilldown_orders = WAP_Data::get_product_drilldown( $orders, $product_id, $paid_only );
        $total_qty     = 0;
        $total_revenue = 0;
        foreach ( $drilldown_orders as $row ) {
            $total_qty     += $row['qty'];
            $total_revenue += $row['revenue'];
        }

        echo '<div class="wci-export-bar" style="margin-bottom:12px">';
        echo '<span>سفارشات: <strong>' . count( $drilldown_orders ) . '</strong></span>';
        echo '<span style="margin-right:20px">تعداد فروخته‌شده: <strong>' . number_format( $total_qty ) . '</strong></span>';
        echo '<span style="margin-right:20px">درآمد کل: <strong>' . wc_price( $total_revenue ) . '</strong></span>';
        echo '</div>';

        $baget_cols = WAP_Baget_Fields::table_column_count();
        echo '<table class="widefat wci-table" style="margin-top:0">';
        echo '<thead><tr><th>#</th><th>سفارش</th><th>نام خریدار</th><th>تلفن</th><th>ایمیل</th><th>تاریخ</th><th>تعداد</th><th>مبلغ</th><th>وضعیت</th>';
        WAP_Baget_Fields::render_table_headers();
        echo '</tr></thead><tbody>';

        if ( empty( $drilldown_orders ) ) {
            echo '<tr><td colspan="' . esc_attr( 9 + $baget_cols ) . '" style="text-align:center;padding:30px">سفارشی برای این محصول یافت نشد.</td></tr>';
        }

        $i = 1;
        foreach ( $drilldown_orders as $row ) {
            $o      = $row['order'];
            $name   = trim( $o->get_billing_first_name() . ' ' . $o->get_billing_last_name() );
            $phone  = $o->get_billing_phone();
            $email  = $o->get_billing_email();
            $date   = wci_format_date( $o->get_date_created(), $is_jalali );
            $status = $o->get_status();
            $edit   = admin_url( 'post.php?post=' . $o->get_id() . '&action=edit' );
            echo '<tr>';
            echo '<td>' . esc_html( $i++ ) . '</td>';
            echo '<td><a href="' . esc_url( $edit ) . '" target="_blank">#' . esc_html( $o->get_order_number() ) . '</a></td>';
            echo '<td>' . esc_html( $name ?: '—' ) . '</td>';
            echo '<td style="direction:ltr;text-align:right">' . esc_html( $phone ?: '—' ) . '</td>';
            echo '<td>' . esc_html( $email ?: '—' ) . '</td>';
            echo '<td style="direction:ltr;text-align:right">' . esc_html( $date ) . '</td>';
            echo '<td><strong>' . esc_html( number_format( $row['qty'] ) ) . '</strong></td>';
            echo '<td><strong>' . wp_kses_post( wc_price( $row['revenue'] ) ) . '</strong></td>';
            echo '<td>';
            if ( class_exists( 'WAP_Order_Service' ) ) {
                WAP_Order_Service::render_status_cell( $o, 'wci' );
            } else {
                echo '<span class="wci-status wci-status--' . esc_attr( $status ) . '">' . esc_html( wc_get_order_status_name( $status ) ) . '</span>';
            }
            echo '</td>';
            WAP_Baget_Fields::render_table_cells( $o );
            echo '</tr>';
        }
        echo '</tbody></table></div>';
        return;
    }

    // ── Summary mode ──────────────────────────────────────────────────────────
    echo '<h1>فروش محصولات</h1>';

    $products_with_pid = WAP_Data::get_product_sales( $orders, $paid_only, $product_cat );

    // Sort
    $asc = $order_dir === 'ASC';
    usort( $products_with_pid, function( $a, $b ) use ( $orderby_p, $asc ) {
        $va = isset( $a[ $orderby_p ] ) ? $a[ $orderby_p ] : 0;
        $vb = isset( $b[ $orderby_p ] ) ? $b[ $orderby_p ] : 0;
        if ( is_numeric( $va ) ) {
            return $asc ? $va - $vb : $vb - $va;
        }
        return $asc ? strcmp( $va, $vb ) : strcmp( $vb, $va );
    } );

    // Filter bar
    $date_ph_from = $is_jalali ? '۱۴۰۳/۰۱/۰۱' : 'YYYY-MM-DD';
    $date_ph_to   = $is_jalali ? '۱۴۰۳/۱۲/۲۹' : 'YYYY-MM-DD';
    echo '<form method="get" class="wci-filter-bar">';
    echo '<input type="hidden" name="page" value="wci-products">';
    echo '<span class="wci-filter-label">📅 ' . ( $is_jalali ? 'بازه شمسی' : 'بازه میلادی' ) . ':</span>';
    if ( $is_jalali ) {
        echo '<input type="text" name="date_from" id="wci_date_from" value="' . esc_attr( $date_from ) . '" placeholder="' . $date_ph_from . '" class="wci-date-input" style="width:110px;direction:ltr" autocomplete="off">';
        echo '<span style="margin:0 4px">تا</span>';
        echo '<input type="text" name="date_to" id="wci_date_to" value="' . esc_attr( $date_to ) . '" placeholder="' . $date_ph_to . '" class="wci-date-input" style="width:110px;direction:ltr" autocomplete="off">';
    } else {
        echo '<input type="date" name="date_from" id="wci_date_from" value="' . esc_attr( $date_from ) . '">';
        echo '<span style="margin:0 4px">تا</span>';
        echo '<input type="date" name="date_to" id="wci_date_to" value="' . esc_attr( $date_to ) . '">';
    }
    echo '<select name="order_status"><option value="">همه وضعیت‌ها</option>';
    echo '<option value="__paid__"' . selected( $order_status, '__paid__', false ) . '>فقط موفق</option>';
    foreach ( wc_get_order_statuses() as $slug => $label ) {
        $val = str_replace( 'wc-', '', $slug );
        echo '<option value="' . esc_attr( $val ) . '"' . selected( $order_status, $val, false ) . '>' . esc_html( $label ) . '</option>';
    }
    echo '</select>';
    echo '<select name="product_cat"><option value="0">همه دسته‌ها</option>';
    foreach ( $categories as $cat ) {
        echo '<option value="' . esc_attr( $cat['id'] ) . '"' . selected( $product_cat, $cat['id'], false ) . '>' . esc_html( $cat['name'] ) . '</option>';
    }
    echo '</select>';
    echo '<button type="submit" class="button button-primary">نمایش</button>';
    echo '<a href="' . esc_url( admin_url( 'admin.php?page=wci-products' ) ) . '" class="button">پاک کردن</a>';
    $exp_url = add_query_arg( array(
        'page'         => 'wci-products',
        'action'       => 'wci_export_products_csv',
        'date_from'    => $date_from,
        'date_to'      => $date_to,
        'order_status' => $order_status,
        'product_cat'  => $product_cat,
        '_wpnonce'     => wp_create_nonce( 'wci_products_export' ),
    ), admin_url( 'admin.php' ) );
    echo '<a href="' . esc_url( $exp_url ) . '" class="button wci-btn-excel" style="margin-right:auto">📊 خروجی Excel</a>';
    echo '</form>';

    if ( $is_jalali ) { wci_print_jalali_picker_script(); }

    // Summary card
    $total_revenue = array_sum( array_column( $products_with_pid, 'revenue' ) );
    $total_qty     = array_sum( array_column( $products_with_pid, 'qty' ) );
    echo '<div class="wci-export-bar" style="margin-bottom:12px">';
    echo '<span>تعداد محصولات: <strong>' . count( $products_with_pid ) . '</strong></span>';
    echo '<span style="margin-right:20px">مجموع فروش: <strong>' . wc_price( $total_revenue ) . '</strong></span>';
    echo '<span style="margin-right:20px">تعداد کل اقلام: <strong>' . $total_qty . '</strong></span>';
    echo '</div>';

    // Sort links helper
    $sf = array(
        'page'         => 'wci-products',
        'date_from'    => $date_from,
        'date_to'      => $date_to,
        'order_status' => $order_status,
        'product_cat'  => $product_cat,
    );
    $sort_th = function( $label, $col ) use ( $sf, $orderby_p, $order_dir ) {
        $nd  = ( $orderby_p === $col && $order_dir === 'DESC' ) ? 'ASC' : 'DESC';
        $arr = $orderby_p === $col ? ( $order_dir === 'DESC' ? ' ↓' : ' ↑' ) : '';
        $url = add_query_arg( array_merge( $sf, array( 'orderby' => $col, 'order' => $nd ) ), admin_url( 'admin.php' ) );
        return '<th><a href="' . esc_url( $url ) . '" class="wci-sort' . ( $orderby_p === $col ? ' active' : '' ) . '">' . $label . $arr . '</a></th>';
    };

    // Bar chart visual (top 20 by revenue)
    if ( ! empty( $products_with_pid ) ) {
        $max_rev = max( array_column( $products_with_pid, 'revenue' ) );
        echo '<div class="wci-chart-wrap">';
        echo '<h3 style="margin:0 0 12px">📊 نمودار درآمد محصولات (برتر)</h3>';
        $top = array_slice( $products_with_pid, 0, 20 );
        foreach ( $top as $p ) {
            $pct      = $max_rev > 0 ? round( $p['revenue'] / $max_rev * 100 ) : 0;
            $drill    = add_query_arg( array_merge( $sf, array( 'product_id' => $p['pid'] ) ), admin_url( 'admin.php' ) );
            printf(
                '<div class="wci-bar-row">
                    <div class="wci-bar-label"><a href="%s" title="%s" style="color:inherit;text-decoration:none">%s</a></div>
                    <div class="wci-bar-track">
                        <div class="wci-bar-fill" style="width:%d%%"></div>
                        <span class="wci-bar-val">%s — %s عدد</span>
                    </div>
                </div>',
                esc_url( $drill ),
                esc_attr( $p['name'] ),
                esc_html( mb_strimwidth( $p['name'], 0, 35, '…' ) ),
                $pct,
                wp_kses_post( wc_price( $p['revenue'] ) ),
                esc_html( number_format( $p['qty'] ) )
            );
        }
        echo '</div>';
    }

    echo '<table class="widefat wci-table" style="margin-top:20px">';
    echo '<thead><tr>';
    echo '<th>#</th>';
    echo $sort_th( 'نام محصول', 'name' );
    echo '<th>SKU</th>';
    echo $sort_th( 'تعداد فروخته‌شده', 'qty' );
    echo $sort_th( 'تعداد سفارشات', 'orders' );
    echo $sort_th( 'درآمد کل', 'revenue' );
    echo '<th>میانگین قیمت واحد</th>';
    echo '</tr></thead><tbody>';

    if ( empty( $products_with_pid ) ) {
        echo '<tr><td colspan="7" style="text-align:center;padding:30px">محصولی یافت نشد.</td></tr>';
    }

    $i = 1;
    foreach ( $products_with_pid as $p ) {
        $avg      = $p['qty'] > 0 ? $p['revenue'] / $p['qty'] : 0;
        $drill    = add_query_arg( array_merge( $sf, array( 'product_id' => $p['pid'] ) ), admin_url( 'admin.php' ) );
        printf(
            '<tr><td>%d</td><td><a href="%s" style="font-weight:bold">%s</a></td><td>%s</td><td><strong>%s</strong></td><td>%s</td><td><strong>%s</strong></td><td>%s</td></tr>',
            $i++,
            esc_url( $drill ),
            esc_html( $p['name'] ),
            esc_html( $p['sku'] ),
            esc_html( number_format( $p['qty'] ) ),
            esc_html( $p['orders'] ),
            wp_kses_post( wc_price( $p['revenue'] ) ),
            wp_kses_post( wc_price( $avg ) )
        );
    }
    echo '</tbody></table></div>';
}

function wci_export_products_csv( $date_from = '', $date_to = '', $product_id = 0, $order_status = '', $product_cat = 0 ) {
    while ( ob_get_level() ) { ob_end_clean(); }

    if ( $date_from === '' )   $date_from    = sanitize_text_field( isset( $_GET['date_from'] ) ? $_GET['date_from'] : '' );
    if ( $date_to === '' )     $date_to      = sanitize_text_field( isset( $_GET['date_to'] ) ? $_GET['date_to'] : '' );
    if ( ! $product_id )       $product_id   = intval( isset( $_GET['product_id'] ) ? $_GET['product_id'] : 0 );
    if ( $order_status === '' ) $order_status = sanitize_text_field( isset( $_GET['order_status'] ) ? $_GET['order_status'] : '' );
    if ( ! $product_cat )      $product_cat  = absint( isset( $_GET['product_cat'] ) ? $_GET['product_cat'] : 0 );

    $paid_only    = ( $order_status === '__paid__' );
    $all_statuses = array_map( function( $s ) { return str_replace( 'wc-', '', $s ); }, array_keys( wc_get_order_statuses() ) );
    $args = array( 'limit' => -1, 'return' => 'objects', 'type' => 'shop_order', 'status' => $all_statuses );
    if ( $order_status !== '' && $order_status !== '__paid__' ) {
        $args['status'] = array( $order_status );
    }
    $ts_from = $date_from ? wci_date_to_timestamp( $date_from, false ) : 0;
    $ts_to   = $date_to   ? wci_date_to_timestamp( $date_to,   true  ) : 0;
    if ( $ts_from && $ts_to ) { $args['date_created'] = $ts_from . '...' . $ts_to; }
    elseif ( $ts_from ) { $args['date_created'] = '>=' . $ts_from; }
    elseif ( $ts_to )   { $args['date_created'] = '<=' . $ts_to; }

    $orders = wc_get_orders( $args );
    $orders = wci_filter_orders_by_date_range( $orders, $ts_from, $ts_to );

    header( 'Content-Type: text/csv; charset=UTF-8' );
    header( 'Pragma: no-cache' );

    $fp = fopen( 'php://output', 'w' );
    fputs( $fp, "\xEF\xBB\xBF" );

    if ( $product_id ) {
        $product_obj  = wc_get_product( $product_id );
        $product_name = $product_obj ? $product_obj->get_name() : 'product-' . $product_id;
        header( 'Content-Disposition: attachment; filename="orders-' . sanitize_title( $product_name ) . '-' . date( 'Y-m-d' ) . '.csv"' );
        $is_jalali_csv = wci_is_jalali();
        $extra_cols    = WAP_Baget_Fields::get_export_columns();
        $header        = array( 'شماره سفارش', 'نام خریدار', 'تلفن', 'ایمیل', 'تاریخ', 'تعداد', 'مبلغ این محصول', 'وضعیت' );
        $header        = array_merge( $header, array_values( $extra_cols ) );
        fputcsv( $fp, $header );
        foreach ( WAP_Data::get_product_drilldown( $orders, $product_id, $paid_only ) as $row ) {
            $order = $row['order'];
            $name  = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
            $line  = array(
                $order->get_order_number(),
                $name ?: $order->get_billing_email(),
                $order->get_billing_phone(),
                $order->get_billing_email(),
                wci_format_date( $order->get_date_created(), $is_jalali_csv ),
                $row['qty'],
                $row['revenue'],
                wc_get_order_status_name( $order->get_status() ),
            );
            foreach ( array_keys( $extra_cols ) as $meta_key ) {
                $line[] = WAP_Baget_Fields::get_order_field_value( $order, $meta_key );
            }
            fputcsv( $fp, $line );
        }
    } else {
        header( 'Content-Disposition: attachment; filename="products-sales-' . date( 'Y-m-d' ) . '.csv"' );
        fputcsv( $fp, array( 'نام محصول', 'SKU', 'تعداد فروخته‌شده', 'تعداد سفارشات', 'درآمد کل' ) );
        foreach ( WAP_Data::get_product_sales( $orders, $paid_only, $product_cat ) as $p ) {
            fputcsv( $fp, array( $p['name'], $p['sku'], $p['qty'], $p['orders'], $p['revenue'] ) );
        }
    }

    fclose( $fp );
    exit;
}

/**
 * گزارش مشتریان خریدار یک یا چند محصول انتخابی.
 */
function wci_product_buyers_page() {
    if ( ! hesabdar_user_can_wci() ) {
        wp_die( 'Unauthorized' );
    }

    $is_jalali    = wci_is_jalali();
    $date_from    = sanitize_text_field( $_GET['date_from'] ?? '' );
    $date_to      = sanitize_text_field( $_GET['date_to'] ?? '' );
    $order_status = sanitize_text_field( $_GET['order_status'] ?? '' );
    $product_ids  = WAP_Data::parse_ids( $_GET['product_ids'] ?? array() );
    $paid_only    = ( $order_status === '__paid__' );
    $labels       = WAP_Data::product_labels( $product_ids );

    if ( isset( $_GET['action'] ) && $_GET['action'] === 'wci_export_buyers_csv' ) {
        check_admin_referer( 'wci_buyers_export' );
        $all_statuses = array_map( function( $s ) { return str_replace( 'wc-', '', $s ); }, array_keys( wc_get_order_statuses() ) );
        $args = array( 'limit' => -1, 'return' => 'objects', 'type' => 'shop_order', 'status' => $all_statuses );
        if ( $order_status !== '' && $order_status !== '__paid__' ) {
            $args['status'] = array( $order_status );
        }
        $ts_from = $date_from ? wci_date_to_timestamp( $date_from, false ) : 0;
        $ts_to   = $date_to   ? wci_date_to_timestamp( $date_to, true ) : 0;
        if ( $ts_from && $ts_to ) { $args['date_created'] = $ts_from . '...' . $ts_to; }
        elseif ( $ts_from ) { $args['date_created'] = '>=' . $ts_from; }
        elseif ( $ts_to )   { $args['date_created'] = '<=' . $ts_to; }
        $orders = wci_filter_orders_by_date_range( wc_get_orders( $args ), $ts_from, $ts_to );
        $buyers = WAP_Data::get_buyers_by_products( $orders, $product_ids, $paid_only );
        WAP_Export::buyers_csv( $buyers );
    }

    $buyers = array();
    if ( ! empty( $product_ids ) ) {
        $all_statuses = array_map( function( $s ) { return str_replace( 'wc-', '', $s ); }, array_keys( wc_get_order_statuses() ) );
        $args = array( 'limit' => -1, 'return' => 'objects', 'type' => 'shop_order', 'status' => $all_statuses );
        if ( $order_status !== '' && $order_status !== '__paid__' ) {
            $args['status'] = array( $order_status );
        }
        $ts_from = $date_from ? wci_date_to_timestamp( $date_from, false ) : 0;
        $ts_to   = $date_to   ? wci_date_to_timestamp( $date_to, true ) : 0;
        if ( $ts_from && $ts_to ) { $args['date_created'] = $ts_from . '...' . $ts_to; }
        elseif ( $ts_from ) { $args['date_created'] = '>=' . $ts_from; }
        elseif ( $ts_to )   { $args['date_created'] = '<=' . $ts_to; }
        $orders = wci_filter_orders_by_date_range( wc_get_orders( $args ), $ts_from, $ts_to );
        $buyers = WAP_Data::get_buyers_by_products( $orders, $product_ids, $paid_only );
    }

    $date_ph_from = $is_jalali ? '۱۴۰۳/۰۱/۰۱' : 'YYYY-MM-DD';
    $date_ph_to   = $is_jalali ? '۱۴۰۳/۱۲/۲۹' : 'YYYY-MM-DD';

    echo '<div class="wrap wci-wrap">';
    echo '<h1>خریداران محصول</h1>';
    echo '<p class="description">مشتریانی که حداقل یکی از محصولات انتخاب‌شده را در بازه و وضعیت مشخص خریده‌اند.</p>';

    echo '<form method="get" class="wci-filter-bar" style="align-items:flex-start;flex-wrap:wrap">';
    echo '<input type="hidden" name="page" value="wci-product-buyers">';
    echo '<span class="wci-filter-label">📅 ' . ( $is_jalali ? 'بازه شمسی' : 'بازه میلادی' ) . ':</span>';
    if ( $is_jalali ) {
        echo '<input type="text" name="date_from" id="wci_date_from" value="' . esc_attr( $date_from ) . '" placeholder="' . esc_attr( $date_ph_from ) . '" class="wci-date-input" style="width:110px;direction:ltr" autocomplete="off">';
        echo '<span style="margin:0 4px">تا</span>';
        echo '<input type="text" name="date_to" id="wci_date_to" value="' . esc_attr( $date_to ) . '" placeholder="' . esc_attr( $date_ph_to ) . '" class="wci-date-input" style="width:110px;direction:ltr" autocomplete="off">';
    } else {
        echo '<input type="date" name="date_from" id="wci_date_from" value="' . esc_attr( $date_from ) . '">';
        echo '<span style="margin:0 4px">تا</span>';
        echo '<input type="date" name="date_to" id="wci_date_to" value="' . esc_attr( $date_to ) . '">';
    }
    echo '<select name="order_status"><option value="">همه وضعیت‌ها</option>';
    echo '<option value="__paid__"' . selected( $order_status, '__paid__', false ) . '>فقط موفق</option>';
    foreach ( wc_get_order_statuses() as $slug => $label ) {
        $val = str_replace( 'wc-', '', $slug );
        echo '<option value="' . esc_attr( $val ) . '"' . selected( $order_status, $val, false ) . '>' . esc_html( $label ) . '</option>';
    }
    echo '</select>';

    // انتخاب چندمحصولی ساده برای ادمین
    $all_products = wc_get_products( array( 'status' => 'publish', 'limit' => 300, 'orderby' => 'title', 'order' => 'ASC', 'return' => 'objects' ) );
    echo '<label style="display:flex;flex-direction:column;gap:4px;min-width:260px">محصولات';
    echo '<select name="product_ids[]" multiple size="8" style="min-width:260px;min-height:140px">';
    foreach ( $all_products as $product ) {
        $pid = $product->get_id();
        echo '<option value="' . esc_attr( $pid ) . '"' . selected( in_array( $pid, $product_ids, true ), true, false ) . '>'
            . esc_html( $product->get_name() ) . ' (#' . esc_html( (string) $pid ) . ')</option>';
    }
    echo '</select><span class="description">با Ctrl/Cmd چند محصول انتخاب کنید.</span></label>';

    echo '<button type="submit" class="button button-primary">نمایش</button>';
    echo '<a href="' . esc_url( admin_url( 'admin.php?page=wci-product-buyers' ) ) . '" class="button">پاک کردن</a>';
    if ( ! empty( $product_ids ) ) {
        $exp_url = add_query_arg( array(
            'page'         => 'wci-product-buyers',
            'action'       => 'wci_export_buyers_csv',
            'date_from'    => $date_from,
            'date_to'      => $date_to,
            'order_status' => $order_status,
            'product_ids'  => $product_ids,
            '_wpnonce'     => wp_create_nonce( 'wci_buyers_export' ),
        ), admin_url( 'admin.php' ) );
        echo '<a href="' . esc_url( $exp_url ) . '" class="button wci-btn-excel" style="margin-right:auto">📊 خروجی Excel</a>';
    }
    echo '</form>';
    if ( $is_jalali ) { wci_print_jalali_picker_script(); }

    if ( empty( $product_ids ) ) {
        echo '<div class="notice notice-info inline"><p>حداقل یک محصول انتخاب کنید.</p></div></div>';
        return;
    }

    if ( ! empty( $labels ) ) {
        echo '<p>محصولات انتخابی: <strong>' . esc_html( implode( '، ', array_values( $labels ) ) ) . '</strong></p>';
    }

    $total_qty     = array_sum( array_column( $buyers, 'qty' ) );
    $total_revenue = array_sum( array_column( $buyers, 'revenue' ) );
    echo '<div class="wci-export-bar" style="margin-bottom:12px">';
    echo '<span>تعداد مشتریان: <strong>' . number_format( count( $buyers ) ) . '</strong></span>';
    echo '<span style="margin-right:20px">مجموع اقلام: <strong>' . number_format( $total_qty ) . '</strong></span>';
    echo '<span style="margin-right:20px">مبلغ: <strong>' . wp_kses_post( wc_price( $total_revenue ) ) . '</strong></span>';
    echo '</div>';

    echo '<table class="widefat wci-table"><thead><tr>';
    echo '<th>#</th><th>نام</th><th>تلفن</th><th>ایمیل</th><th>شهر</th><th>تعداد سفارش</th><th>تعداد اقلام</th><th>مبلغ</th><th>آخرین خرید</th>';
    echo '</tr></thead><tbody>';
    if ( empty( $buyers ) ) {
        echo '<tr><td colspan="9" style="text-align:center;padding:30px">مشتری‌ای یافت نشد.</td></tr>';
    } else {
        $i = 1;
        foreach ( $buyers as $b ) {
            echo '<tr>';
            echo '<td>' . esc_html( (string) $i++ ) . '</td>';
            echo '<td><strong>' . esc_html( $b['name'] !== '' ? $b['name'] : '—' ) . '</strong></td>';
            echo '<td style="direction:ltr;text-align:right">' . esc_html( $b['phone'] !== '' ? $b['phone'] : '—' ) . '</td>';
            echo '<td>' . esc_html( $b['email'] !== '' ? $b['email'] : '—' ) . '</td>';
            echo '<td>' . esc_html( $b['city'] !== '' ? $b['city'] : '—' ) . '</td>';
            echo '<td>' . esc_html( number_format( $b['orders_count'] ) ) . '</td>';
            echo '<td><strong>' . esc_html( number_format( $b['qty'] ) ) . '</strong></td>';
            echo '<td><strong>' . wp_kses_post( wc_price( $b['revenue'] ) ) . '</strong></td>';
            echo '<td>' . esc_html( $b['last_order_ts'] ? date_i18n( 'Y/m/d H:i', $b['last_order_ts'] ) : '—' ) . '</td>';
            echo '</tr>';
        }
    }
    echo '</tbody></table></div>';
}

// ─── گزارش مالی (خروجی کلی بر اساس بازه شمسی) ─────────────────────────────────
function wci_reports_get_filters() {
    return array(
        'date_from'  => sanitize_text_field( isset( $_GET['date_from'] ) ? $_GET['date_from'] : '' ),
        'date_to'    => sanitize_text_field( isset( $_GET['date_to'] )   ? $_GET['date_to']   : '' ),
        'period'     => in_array( isset( $_GET['period'] ) ? $_GET['period'] : '', array( 'day', 'week', 'month', 'quarter', 'year' ), true )
                            ? $_GET['period'] : 'month',
        'order_status' => sanitize_text_field( isset( $_GET['order_status'] ) ? $_GET['order_status'] : '' ),
        'min_total'  => isset( $_GET['min_total'] ) && $_GET['min_total'] !== '' ? (float) $_GET['min_total'] : null,
        'max_total'  => isset( $_GET['max_total'] ) && $_GET['max_total'] !== '' ? (float) $_GET['max_total'] : null,
        'min_count'  => isset( $_GET['min_count'] ) && $_GET['min_count'] !== '' ? (int) $_GET['min_count'] : null,
        'max_count'  => isset( $_GET['max_count'] ) && $_GET['max_count'] !== '' ? (int) $_GET['max_count'] : null,
    );
}

// سفارشات را بر اساس بازه و وضعیت واکشی می‌کند
function wci_reports_get_orders( $f ) {
    $all_statuses = array_map( function( $s ) { return str_replace( 'wc-', '', $s ); }, array_keys( wc_get_order_statuses() ) );
    $args = array( 'limit' => -1, 'return' => 'objects', 'type' => 'shop_order', 'status' => $all_statuses );
    if ( $f['order_status'] ) { $args['status'] = array( $f['order_status'] ); }
    $ts_from = $f['date_from'] ? wci_date_to_timestamp( $f['date_from'], false ) : 0;
    $ts_to   = $f['date_to']   ? wci_date_to_timestamp( $f['date_to'],   true  ) : 0;
    if ( $ts_from && $ts_to ) { $args['date_created'] = $ts_from . '...' . $ts_to; }
    elseif ( $ts_from ) { $args['date_created'] = '>=' . $ts_from; }
    elseif ( $ts_to )   { $args['date_created'] = '<=' . $ts_to; }
    return wci_filter_orders_by_date_range( wc_get_orders( $args ), $ts_from, $ts_to );
}

// کلید و برچسب دوره شمسی برای گروه‌بندی (روز/هفته/ماه/فصل/سال)
function wci_jalali_period_key( $jy, $jm, $jd, $period ) {
    $season_names = array( 'بهار', 'تابستان', 'پاییز', 'زمستان' );
    $month_names  = array( 'فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند' );
    switch ( $period ) {
        case 'day':
            $key = sprintf( '%04d%02d%02d', $jy, $jm, $jd );
            $lbl = sprintf( '%d/%02d/%02d', $jy, $jm, $jd );
            break;
        case 'week':
            $month_days = array( 31, 31, 31, 31, 31, 31, 30, 30, 30, 30, 30, 30 );
            $doy = $jd;
            for ( $i = 0; $i < $jm - 1; $i++ ) { $doy += $month_days[ $i ]; }
            $week_no = (int) ceil( $doy / 7 );
            $key = sprintf( '%04d-W%02d', $jy, $week_no );
            $lbl = 'هفته ' . $week_no . ' سال ' . $jy;
            break;
        case 'quarter':
            $q = intdiv( $jm - 1, 3 );
            $key = sprintf( '%04d-Q%d', $jy, $q + 1 );
            $lbl = $season_names[ $q ] . ' ' . $jy;
            break;
        case 'year':
            $key = (string) $jy;
            $lbl = 'سال ' . $jy;
            break;
        case 'month':
        default:
            $key = sprintf( '%04d%02d', $jy, $jm );
            $lbl = $month_names[ $jm - 1 ] . ' ' . $jy;
            break;
    }
    return array( $key, $lbl );
}

function wci_reports_build_rows( $orders, $period ) {
    $groups = array(); // key => [ label, count, total ]
    foreach ( $orders as $order ) {
        if ( ! WAP_Data::is_paid_order( $order ) ) {
            continue;
        }
        $created = $order->get_date_created();
        if ( ! $created ) continue;
        list( $jy, $jm, $jd ) = wci_gregorian_to_jalali( (int) $created->date( 'Y' ), (int) $created->date( 'n' ), (int) $created->date( 'j' ) );
        list( $key, $lbl ) = wci_jalali_period_key( $jy, $jm, $jd, $period );
        if ( ! isset( $groups[ $key ] ) ) {
            $groups[ $key ] = array( 'label' => $lbl, 'count' => 0, 'total' => 0.0 );
        }
        $groups[ $key ]['count']++;
        $groups[ $key ]['total'] += (float) $order->get_total();
    }
    ksort( $groups );
    return $groups;
}

function wci_reports_apply_amount_count_filter( $groups, $f ) {
    return array_filter( $groups, function( $g ) use ( $f ) {
        if ( $f['min_total'] !== null && $g['total'] < $f['min_total'] ) return false;
        if ( $f['max_total'] !== null && $g['total'] > $f['max_total'] ) return false;
        if ( $f['min_count'] !== null && $g['count'] < $f['min_count'] ) return false;
        if ( $f['max_count'] !== null && $g['count'] > $f['max_count'] ) return false;
        return true;
    } );
}

function wci_reports_page() {
    if ( ! hesabdar_user_can_wci() ) {
        wp_die( 'Unauthorized' );
    }

    $f          = wci_reports_get_filters();
    $is_jalali  = wci_is_jalali();
    $orders     = wci_reports_get_orders( $f );
    $groups_all = wci_reports_build_rows( $orders, $f['period'] );
    $groups     = wci_reports_apply_amount_count_filter( $groups_all, $f );

    $overall_total = 0.0;
    $overall_count = 0;
    foreach ( $orders as $o ) {
        if ( ! WAP_Data::is_paid_order( $o ) ) {
            continue;
        }
        $overall_total += (float) $o->get_total();
        $overall_count++;
    }
    $filtered_total = array_sum( array_column( $groups, 'total' ) );
    $filtered_count = array_sum( array_column( $groups, 'count' ) );

    echo '<div class="wrap wci-wrap">';
    echo '<h1>گزارش مالی</h1>';

    // ── فرم فیلتر ──
    echo '<form method="get" class="wci-filter-bar">';
    echo '<input type="hidden" name="page" value="wci-reports">';

    echo '<span class="wci-filter-label">📆 گروه‌بندی:</span>';
    echo '<select name="period">';
    foreach ( array( 'day' => 'روزانه', 'week' => 'هفتگی', 'month' => 'ماهانه', 'quarter' => 'فصلی', 'year' => 'سالانه' ) as $val => $lbl ) {
        echo '<option value="' . esc_attr( $val ) . '" ' . selected( $f['period'], $val, false ) . '>' . esc_html( $lbl ) . '</option>';
    }
    echo '</select>';

    echo '<select name="order_status">';
    echo '<option value="">همه وضعیت‌ها</option>';
    foreach ( wc_get_order_statuses() as $slug => $label ) {
        $val = str_replace( 'wc-', '', $slug );
        echo '<option value="' . esc_attr( $val ) . '" ' . selected( $f['order_status'], $val, false ) . '>' . esc_html( $label ) . '</option>';
    }
    echo '</select>';

    $date_ph_from = $is_jalali ? '۱۴۰۳/۰۱/۰۱' : 'YYYY-MM-DD';
    $date_ph_to   = $is_jalali ? '۱۴۰۳/۱۲/۲۹' : 'YYYY-MM-DD';
    echo '<span class="wci-filter-label">📅 ' . ( $is_jalali ? 'بازه شمسی' : 'بازه میلادی' ) . ':</span>';
    if ( $is_jalali ) {
        echo '<input type="text" name="date_from" id="wci_date_from" value="' . esc_attr( $f['date_from'] ) . '" placeholder="' . $date_ph_from . '" class="wci-date-input" style="width:110px;direction:ltr" autocomplete="off">';
        echo '<span style="margin:0 4px">تا</span>';
        echo '<input type="text" name="date_to" id="wci_date_to" value="' . esc_attr( $f['date_to'] ) . '" placeholder="' . $date_ph_to . '" class="wci-date-input" style="width:110px;direction:ltr" autocomplete="off">';
    } else {
        echo '<input type="date" name="date_from" id="wci_date_from" value="' . esc_attr( $f['date_from'] ) . '">';
        echo '<span style="margin:0 4px">تا</span>';
        echo '<input type="date" name="date_to" id="wci_date_to" value="' . esc_attr( $f['date_to'] ) . '">';
    }

    echo '<span class="wci-filter-label" style="margin-right:8px">💰 مبلغ فروش دوره:</span>';
    echo '<input type="number" name="min_total" value="' . esc_attr( $f['min_total'] ?? '' ) . '" placeholder="حداقل" style="width:100px" step="any">';
    echo '<span>تا</span>';
    echo '<input type="number" name="max_total" value="' . esc_attr( $f['max_total'] ?? '' ) . '" placeholder="حداکثر" style="width:100px" step="any">';

    echo '<span class="wci-filter-label" style="margin-right:8px">🔢 تعداد فروش دوره:</span>';
    echo '<input type="number" name="min_count" value="' . esc_attr( $f['min_count'] ?? '' ) . '" placeholder="حداقل" style="width:80px">';
    echo '<span>تا</span>';
    echo '<input type="number" name="max_count" value="' . esc_attr( $f['max_count'] ?? '' ) . '" placeholder="حداکثر" style="width:80px">';

    echo '<button type="submit" class="button button-primary">اعمال فیلتر</button>';
    echo '<a href="' . esc_url( admin_url( 'admin.php?page=wci-reports' ) ) . '" class="button">پاک کردن</a>';
    echo '</form>';

    if ( $is_jalali ) { wci_print_jalali_picker_script(); }

    // ── خروجی ──
    $export_params = array_filter( $f, function( $v ) { return $v !== null && $v !== ''; } );
    $csv_url = esc_url( add_query_arg( array_merge( $export_params, array(
        'action'   => 'wci_export_report_csv',
        '_wpnonce' => wp_create_nonce( 'wci_reports_export' ),
    ) ), admin_url( 'admin-post.php' ) ) );
    echo '<div class="wci-export-bar">';
    echo '<strong>خروجی:</strong> <a href="' . $csv_url . '" class="button wci-btn-excel">📊 خروجی Excel/CSV گزارش</a>';
    echo '<span class="wci-export-hint">⬆ خروجی شامل همان بازه، گروه‌بندی و فیلترهای فعلی است</span>';
    echo '</div>';

    // ── خلاصه کلی بازه (بدون فیلتر مبلغ/تعداد) ──
    echo '<div class="wci-export-bar" style="margin-bottom:12px">';
    echo '<span>📦 سفارش‌های موفق بازه: <strong>' . number_format( $overall_count ) . '</strong></span>';
    echo '<span style="margin-right:20px">💵 مجموع فروش (موفق): <strong>' . wc_price( $overall_total ) . '</strong></span>';
    echo '<span style="margin-right:20px">📊 میانگین سفارش موفق: <strong>' . wc_price( $overall_count > 0 ? $overall_total / $overall_count : 0 ) . '</strong></span>';
    echo '</div>';

    if ( $groups_all && count( $groups ) !== count( $groups_all ) ) {
        echo '<div class="wci-export-bar" style="background:#fff8e1;border-color:#ffe082;margin-bottom:12px">';
        echo '<span>🔍 پس از اعمال فیلتر مبلغ/تعداد: <strong>' . count( $groups ) . '</strong> از <strong>' . count( $groups_all ) . '</strong> دوره — جمع فروش فیلترشده: <strong>' . wc_price( $filtered_total ) . '</strong> ، تعداد: <strong>' . number_format( $filtered_count ) . '</strong></span>';
        echo '</div>';
    }

    // ── نمودار میله‌ای ──
    if ( ! empty( $groups ) ) {
        $max_total = max( array_column( $groups, 'total' ) );
        echo '<div class="wci-chart-wrap">';
        echo '<h3 style="margin:0 0 12px">📊 نمودار فروش بر اساس دوره</h3>';
        foreach ( $groups as $g ) {
            $pct = $max_total > 0 ? round( $g['total'] / $max_total * 100 ) : 0;
            printf(
                '<div class="wci-bar-row">
                    <div class="wci-bar-label">%s</div>
                    <div class="wci-bar-track">
                        <div class="wci-bar-fill" style="width:%d%%"></div>
                        <span class="wci-bar-val">%s — %s سفارش</span>
                    </div>
                </div>',
                esc_html( $g['label'] ),
                $pct,
                wp_kses_post( wc_price( $g['total'] ) ),
                esc_html( number_format( $g['count'] ) )
            );
        }
        echo '</div>';
    }

    // ── جدول ──
    echo '<table class="widefat wci-table" style="margin-top:20px">';
    echo '<thead><tr><th>دوره</th><th>تعداد فروش</th><th>مبلغ فروش</th><th>میانگین هر سفارش</th></tr></thead><tbody>';
    if ( empty( $groups ) ) {
        echo '<tr><td colspan="4" style="text-align:center;padding:30px">داده‌ای برای این بازه/فیلتر یافت نشد.</td></tr>';
    }
    foreach ( $groups as $g ) {
        $avg = $g['count'] > 0 ? $g['total'] / $g['count'] : 0;
        printf(
            '<tr><td><strong>%s</strong></td><td>%s</td><td><strong>%s</strong></td><td>%s</td></tr>',
            esc_html( $g['label'] ),
            esc_html( number_format( $g['count'] ) ),
            wp_kses_post( wc_price( $g['total'] ) ),
            wp_kses_post( wc_price( $avg ) )
        );
    }
    echo '</tbody></table></div>';
}

function wci_export_report_csv() {
    while ( ob_get_level() ) { ob_end_clean(); }
    $f          = wci_reports_get_filters();
    $orders     = wci_reports_get_orders( $f );
    $groups_all = wci_reports_build_rows( $orders, $f['period'] );
    $groups     = wci_reports_apply_amount_count_filter( $groups_all, $f );

    header( 'Content-Type: text/csv; charset=UTF-8' );
    header( 'Content-Disposition: attachment; filename="wci-financial-report-' . date( 'Y-m-d' ) . '.csv"' );
    header( 'Pragma: no-cache' );

    $fp = fopen( 'php://output', 'w' );
    fputs( $fp, "\xEF\xBB\xBF" );
    fputcsv( $fp, array( 'دوره', 'تعداد فروش', 'مبلغ فروش' ) );
    foreach ( $groups as $g ) {
        fputcsv( $fp, array( $g['label'], $g['count'], $g['total'] ) );
    }
    $total = array_sum( array_column( $groups, 'total' ) );
    $count = array_sum( array_column( $groups, 'count' ) );
    fputcsv( $fp, array( 'جمع کل', $count, $total ) );
    fclose( $fp );
    exit;
}

// ─── Shortcode [wci_my_info] ──────────────────────────────────────────────────
add_shortcode( 'wci_my_info', function() {
    if ( ! class_exists( 'WooCommerce' ) ) {
        return '<p>این بخش نیاز به ووکامرس دارد.</p>';
    }
    if ( ! is_user_logged_in() ) {
        return '<p>برای مشاهده اطلاعات خود ابتدا <a href="' . esc_url( wc_get_page_permalink( 'myaccount' ) ) . '">وارد شوید</a>.</p>';
    }
    $uid = get_current_user_id();
    ob_start();
    echo '<div class="wci-frontend"><h3>اطلاعات من</h3><dl class="wci-dl">';
    foreach ( [ 'billing_first_name' => 'نام', 'billing_last_name' => 'نام خانوادگی', 'billing_phone' => 'شماره تماس', 'billing_city' => 'شهر', 'billing_address_1' => 'آدرس', 'billing_postcode' => 'کد پستی' ] as $k => $l ) {
        $v = get_user_meta( $uid, $k, true );
        if ( $v ) echo '<dt>' . esc_html( $l ) . '</dt><dd>' . esc_html( $v ) . '</dd>';
    }
    echo '</dl>';
    $orders = wc_get_orders( [ 'customer' => $uid, 'limit' => 10, 'status' => array_keys( wc_get_order_statuses() ), 'orderby' => 'date', 'order' => 'DESC' ] );
    if ( $orders ) {
        echo '<h3>سفارش‌های من</h3><ul class="wci-orders">';
        foreach ( $orders as $o ) {
            printf( '<li><a href="%s">#%s</a> — <span class="wci-status wci-status--%s">%s</span> — %s</li>',
                esc_url( $o->get_view_order_url() ), esc_html( $o->get_order_number() ),
                esc_attr( $o->get_status() ), esc_html( wc_get_order_status_name( $o->get_status() ) ),
                wp_kses_post( $o->get_formatted_order_total() ) );
        }
        echo '</ul>';
    }
    echo '</div>';
    return ob_get_clean();
} );
