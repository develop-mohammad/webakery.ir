<?php
defined( 'ABSPATH' ) || exit;

/**
 * خروجی‌های CSV / XML / PDF (چاپ) گزارش فروش برای حسابدار.
 */
class WAP_Export {

    public static function csv( $groups ) {
        while ( ob_get_level() ) { ob_end_clean(); }
        header( 'Content-Type: text/csv; charset=UTF-8' );
        header( 'Content-Disposition: attachment; filename="sales-report-' . date( 'Y-m-d' ) . '.csv"' );
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

    // خروجی CSV لیست خام سفارش‌ها (تب «لیست سفارش‌ها»، نه گزارش دوره‌ای)
    public static function orders_csv( array $orders ) {
        while ( ob_get_level() ) { ob_end_clean(); }
        header( 'Content-Type: text/csv; charset=UTF-8' );
        header( 'Content-Disposition: attachment; filename="orders-' . date( 'Y-m-d' ) . '.csv"' );
        header( 'Pragma: no-cache' );

        $headers = array(
            'شماره سفارش', 'نام', 'نام خانوادگی', 'تلفن', 'شهر', 'محصول',
            'روش پرداخت', 'وضعیت', 'مبلغ کل', 'تاریخ',
        );
        $extra_cols = WAP_Baget_Fields::get_export_columns();
        $headers    = array_merge( $headers, array_values( $extra_cols ) );

        $fp = fopen( 'php://output', 'w' );
        fputs( $fp, "\xEF\xBB\xBF" );
        fputcsv( $fp, $headers );
        foreach ( $orders as $order ) {
            $row = array(
                $order->get_order_number(),
                $order->get_billing_first_name(),
                $order->get_billing_last_name(),
                $order->get_billing_phone(),
                $order->get_billing_city(),
                WAP_Data::order_products_summary( $order ),
                WAP_Data::payment_label( $order->get_payment_method() ),
                wc_get_order_status_name( $order->get_status() ),
                $order->get_total(),
                wc_format_datetime( $order->get_date_created() ),
            );
            foreach ( array_keys( $extra_cols ) as $meta_key ) {
                $row[] = WAP_Baget_Fields::get_order_field_value( $order, $meta_key );
            }
            fputcsv( $fp, $row );
        }
        fclose( $fp );
        exit;
    }

    /** خلاصه فروش محصولات (لیست همه محصولات) */
    public static function products_csv( array $orders, bool $paid_only = true, int $product_cat = 0 ) {
        while ( ob_get_level() ) { ob_end_clean(); }
        header( 'Content-Type: text/csv; charset=UTF-8' );
        header( 'Content-Disposition: attachment; filename="products-sales-' . date( 'Y-m-d' ) . '.csv"' );
        header( 'Pragma: no-cache' );

        $fp = fopen( 'php://output', 'w' );
        fputs( $fp, "\xEF\xBB\xBF" );
        fputcsv( $fp, array( 'نام محصول', 'SKU', 'تعداد فروخته‌شده', 'تعداد سفارشات', 'درآمد کل' ) );
        foreach ( WAP_Data::get_product_sales( $orders, $paid_only, $product_cat ) as $p ) {
            fputcsv( $fp, array( $p['name'], $p['sku'], $p['qty'], $p['orders'], $p['revenue'] ) );
        }
        fclose( $fp );
        exit;
    }

    /** سفارش‌های یک محصول (drill-down) */
    public static function product_orders_csv( array $orders, int $product_id, bool $paid_only = true ) {
        while ( ob_get_level() ) { ob_end_clean(); }

        $product_obj  = wc_get_product( $product_id );
        $product_name = $product_obj ? $product_obj->get_name() : 'product-' . $product_id;
        $slug         = sanitize_title( $product_name );
        if ( ! $slug ) {
            $slug = 'product-' . $product_id;
        }

        header( 'Content-Type: text/csv; charset=UTF-8' );
        header( 'Content-Disposition: attachment; filename="orders-' . $slug . '-' . date( 'Y-m-d' ) . '.csv"' );
        header( 'Pragma: no-cache' );

        $extra_cols = WAP_Baget_Fields::get_export_columns();
        $headers    = array( 'شماره سفارش', 'نام خریدار', 'تلفن', 'ایمیل', 'تاریخ', 'تعداد', 'مبلغ این محصول', 'وضعیت' );
        $headers    = array_merge( $headers, array_values( $extra_cols ) );

        $fp = fopen( 'php://output', 'w' );
        fputs( $fp, "\xEF\xBB\xBF" );
        fputcsv( $fp, $headers );

        foreach ( WAP_Data::get_product_drilldown( $orders, $product_id, $paid_only ) as $row ) {
            $order = $row['order'];
            $name  = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
            $line  = array(
                $order->get_order_number(),
                $name ?: $order->get_billing_email(),
                $order->get_billing_phone(),
                $order->get_billing_email(),
                wc_format_datetime( $order->get_date_created() ),
                $row['qty'],
                $row['revenue'],
                wc_get_order_status_name( $order->get_status() ),
            );
            foreach ( array_keys( $extra_cols ) as $meta_key ) {
                $line[] = WAP_Baget_Fields::get_order_field_value( $order, $meta_key );
            }
            fputcsv( $fp, $line );
        }

        fclose( $fp );
        exit;
    }

    /** لیست مشتریان خریدار محصولات انتخاب‌شده */
    public static function buyers_csv( array $buyers ) {
        while ( ob_get_level() ) { ob_end_clean(); }
        header( 'Content-Type: text/csv; charset=UTF-8' );
        header( 'Content-Disposition: attachment; filename="product-buyers-' . date( 'Y-m-d' ) . '.csv"' );
        header( 'Pragma: no-cache' );

        $fp = fopen( 'php://output', 'w' );
        fputs( $fp, "\xEF\xBB\xBF" );
        fputcsv( $fp, array( 'نام', 'تلفن', 'ایمیل', 'شهر', 'تعداد سفارش', 'وضعیت‌ها', 'تعداد اقلام', 'مبلغ محصولات انتخابی', 'آخرین خرید' ) );
        foreach ( $buyers as $b ) {
            $status_bits = array();
            foreach ( (array) ( $b['statuses'] ?? array() ) as $st => $cnt ) {
                $status_bits[] = ( function_exists( 'wc_get_order_status_name' ) ? wc_get_order_status_name( $st ) : $st ) . ' (' . (int) $cnt . ')';
            }
            fputcsv( $fp, array(
                $b['name'] ?: '—',
                $b['phone'],
                $b['email'],
                $b['city'],
                $b['orders_count'],
                implode( ' | ', $status_bits ),
                $b['qty'],
                $b['revenue'],
                ! empty( $b['last_order_ts'] ) ? date_i18n( 'Y/m/d H:i', $b['last_order_ts'] ) : '',
            ) );
        }
        fclose( $fp );
        exit;
    }

    public static function xml( $groups ) {
        while ( ob_get_level() ) { ob_end_clean(); }
        header( 'Content-Type: application/xml; charset=UTF-8' );
        header( 'Content-Disposition: attachment; filename="sales-report-' . date( 'Y-m-d' ) . '.xml"' );

        $xml = new SimpleXMLElement( '<?xml version="1.0" encoding="UTF-8"?><SalesReport></SalesReport>' );
        $xml->addAttribute( 'generated', date( 'Y-m-d H:i:s' ) );
        $total = 0.0; $count = 0;
        foreach ( $groups as $g ) {
            $row = $xml->addChild( 'Period' );
            $row->addChild( 'Label', htmlspecialchars( $g['label'], ENT_XML1, 'UTF-8' ) );
            $row->addChild( 'Count', (string) $g['count'] );
            $row->addChild( 'Total', (string) $g['total'] );
            $total += $g['total'];
            $count += $g['count'];
        }
        $summary = $xml->addChild( 'Summary' );
        $summary->addChild( 'TotalCount', (string) $count );
        $summary->addChild( 'TotalAmount', (string) $total );

        echo $xml->asXML();
        exit;
    }

    // خروجی چاپی/PDF — مرورگر با «چاپ / ذخیره به‌عنوان PDF» آن را به PDF تبدیل می‌کند
    public static function print_view( $groups, $filters_summary = '' ) {
        while ( ob_get_level() ) { ob_end_clean(); }
        header( 'Content-Type: text/html; charset=UTF-8' );
        $total = array_sum( array_column( $groups, 'total' ) );
        $count = array_sum( array_column( $groups, 'count' ) );
        ?>
        <!DOCTYPE html>
        <html dir="rtl" lang="fa">
        <head>
        <meta charset="UTF-8">
        <title>گزارش فروش</title>
        <style>
            body { font-family: Tahoma, Arial, sans-serif; font-size: 13px; direction: rtl; margin: 24px; }
            h1 { font-size: 18px; text-align: center; margin-bottom: 4px; }
            .meta { text-align: center; color: #666; margin-bottom: 18px; font-size: 12px; }
            table { width: 100%; border-collapse: collapse; }
            th { background: #1d7044; color: #fff; padding: 8px 10px; }
            td { border: 1px solid #ddd; padding: 6px 10px; }
            tr:nth-child(even) td { background: #f8f8f8; }
            tfoot td { font-weight: bold; background: #eef7f0; }
            @media print { @page { size: A4; margin: 15mm; } }
        </style>
        </head>
        <body>
        <h1>گزارش فروش</h1>
        <p class="meta"><?php echo esc_html( $filters_summary ); ?> — تاریخ چاپ: <?php echo esc_html( date( 'Y/m/d H:i' ) ); ?></p>
        <table>
            <thead><tr><th>دوره</th><th>تعداد فروش</th><th>مبلغ فروش</th></tr></thead>
            <tbody>
            <?php foreach ( $groups as $g ) : ?>
                <tr>
                    <td><?php echo esc_html( $g['label'] ); ?></td>
                    <td><?php echo esc_html( number_format( $g['count'] ) ); ?></td>
                    <td><?php echo esc_html( number_format( $g['total'] ) ); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot><tr><td>جمع کل</td><td><?php echo esc_html( number_format( $count ) ); ?></td><td><?php echo esc_html( number_format( $total ) ); ?></td></tr></tfoot>
        </table>
        <script>window.print();</script>
        </body></html>
        <?php
        exit;
    }
}
