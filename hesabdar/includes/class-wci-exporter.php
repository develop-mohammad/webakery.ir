<?php
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WCI_Exporter' ) ) :

class WCI_Exporter {

    private $orders;

    public function __construct( $orders ) {
        $this->orders = $orders;
    }

    public function export_csv(): void {
        // Clean any buffered output before sending file headers
        while ( ob_get_level() ) {
            ob_end_clean();
        }

        $selected = isset( $_GET['wci_cols'] ) ? explode( ',', sanitize_text_field( $_GET['wci_cols'] ) ) : [];

        $all_cols = WAP_Baget_Fields::get_merged_export_columns();

        $cols = ! empty( $selected )
            ? array_intersect_key( $all_cols, array_flip( $selected ) )
            : $all_cols;

        $orderby  = sanitize_text_field( $_GET['orderby'] ?? 'date' );
        $order_dir = sanitize_text_field( $_GET['order'] ?? 'DESC' );

        $filename = 'woocommerce-orders-' . date( 'Y-m-d' ) . '.csv';

        header( 'Content-Type: text/csv; charset=UTF-8' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'Pragma: no-cache' );
        header( 'Expires: 0' );

        $fp = fopen( 'php://output', 'w' );

        // UTF-8 BOM for Excel
        fputs( $fp, "\xEF\xBB\xBF" );

        // Sort info row
        $sort_labels = [
            'date'         => 'تاریخ سفارش',
            'order_number' => 'شماره سفارش',
            'total'        => 'مبلغ کل',
            'first_name'   => 'نام',
            'last_name'    => 'نام خانوادگی',
            'phone'        => 'شماره تماس',
            'city'         => 'شهر',
            'state'        => 'استان',
        ];
        $sort_lbl = $sort_labels[ $orderby ] ?? $orderby;
        $dir_lbl  = $order_dir === 'ASC' ? 'صعودی' : 'نزولی';
        fputcsv( $fp, [ 'مرتب‌سازی: ' . $sort_lbl . ' (' . $dir_lbl . ')', 'تعداد: ' . count( $this->orders ) ] );

        // Header row
        fputcsv( $fp, array_values( $cols ) );

        // Data rows
        foreach ( $this->orders as $order ) {
            $state_code = $order->get_billing_state();
            $country    = $order->get_billing_country() ?: 'IR';
            $states     = WC()->countries->get_states( $country ) ?? [];
            $state_name = $states[ $state_code ] ?? $state_code;

            $product_parts = array();
            foreach ( $order->get_items() as $item ) {
                $product_parts[] = $item->get_name() . ' x' . $item->get_quantity();
            }
            $products = implode( ' | ', $product_parts );

            $row_data = [
                'order_number'  => $order->get_order_number(),
                'first_name'    => $order->get_billing_first_name(),
                'last_name'     => $order->get_billing_last_name(),
                'email'         => $order->get_billing_email(),
                'phone'         => $order->get_billing_phone(),
                'city'          => $order->get_billing_city(),
                'state'         => $state_name,
                'address'       => $order->get_billing_address_1() . ( $order->get_billing_address_2() ? ' ' . $order->get_billing_address_2() : '' ),
                'postcode'      => $order->get_billing_postcode(),
                'payment'       => wci_payment_label( $order->get_payment_method() ),
                'status'        => wc_get_order_status_name( $order->get_status() ),
                'products'      => $products,
                'total'         => $order->get_total(),
                'date'          => wc_format_datetime( $order->get_date_created() ),
                'transaction'   => $order->get_transaction_id(),
                'customer_note' => $order->get_customer_note(),
            ];
            $row_data = WAP_Baget_Fields::merge_row_data( $row_data, $order );

            $row = [];
            foreach ( array_keys( $cols ) as $key ) {
                $row[] = $row_data[ $key ] ?? '';
            }
            fputcsv( $fp, $row );
        }

        fclose( $fp );
        exit;
    }

    public function export_pdf(): void {
        while ( ob_get_level() ) {
            ob_end_clean();
        }

        $orderby   = sanitize_text_field( $_GET['orderby'] ?? 'date' );
        $order_dir = sanitize_text_field( $_GET['order'] ?? 'DESC' );

        $sort_labels = [
            'date' => 'تاریخ سفارش', 'order_number' => 'شماره سفارش',
            'total' => 'مبلغ کل', 'first_name' => 'نام', 'last_name' => 'نام خانوادگی',
            'phone' => 'شماره تماس', 'city' => 'شهر', 'state' => 'استان',
        ];
        $sort_lbl = $sort_labels[ $orderby ] ?? $orderby;
        $dir_lbl  = $order_dir === 'ASC' ? 'صعودی' : 'نزولی';

        header( 'Content-Type: text/html; charset=UTF-8' );

        echo '<!DOCTYPE html><html dir="rtl" lang="fa"><head><meta charset="UTF-8">';
        echo '<title>لیست سفارشات</title>';
        echo '<style>
            body { font-family: Tahoma, Arial, sans-serif; font-size: 12px; direction: rtl; }
            h1 { font-size: 18px; text-align: center; margin-bottom: 4px; }
            .meta { text-align: center; color: #666; margin-bottom: 16px; font-size: 11px; }
            table { width: 100%; border-collapse: collapse; }
            th { background: #2271b1; color: #fff; padding: 6px 8px; }
            td { border: 1px solid #ddd; padding: 5px 8px; }
            tr:nth-child(even) td { background: #f9f9f9; }
            @media print {
                body { margin: 0; }
                @page { size: A4 landscape; margin: 15mm; }
            }
        </style></head><body>';

        echo '<h1>لیست سفارشات</h1>';
        echo '<p class="meta">مرتب‌سازی: ' . esc_html( $sort_lbl ) . ' (' . esc_html( $dir_lbl ) . ') — تعداد: ' . count( $this->orders ) . ' سفارش — تاریخ: ' . date( 'Y/m/d' ) . '</p>';

        echo '<table><thead><tr>
            <th>#</th>
            <th>شماره سفارش</th>
            <th>نام و نام خانوادگی</th>
            <th>شماره تماس</th>
            <th>شهر</th>
            <th>استان</th>
            <th>روش پرداخت</th>
            <th>وضعیت</th>
            <th>مبلغ کل</th>
            <th>تاریخ</th>
        </tr></thead><tbody>';

        $i = 1;
        foreach ( $this->orders as $order ) {
            $state_code = $order->get_billing_state();
            $country    = $order->get_billing_country() ?: 'IR';
            $states     = WC()->countries->get_states( $country ) ?? [];
            $state_name = $states[ $state_code ] ?? $state_code;

            printf(
                '<tr>
                    <td>%d</td>
                    <td>#%s</td>
                    <td>%s</td>
                    <td>%s</td>
                    <td>%s</td>
                    <td>%s</td>
                    <td>%s</td>
                    <td>%s</td>
                    <td>%s</td>
                    <td>%s</td>
                </tr>',
                $i++,
                esc_html( $order->get_order_number() ),
                esc_html( $order->get_formatted_billing_full_name() ),
                esc_html( $order->get_billing_phone() ),
                esc_html( $order->get_billing_city() ),
                esc_html( $state_name ),
                esc_html( wci_payment_label( $order->get_payment_method() ) ),
                esc_html( wc_get_order_status_name( $order->get_status() ) ),
                esc_html( $order->get_total() ),
                esc_html( wc_format_datetime( $order->get_date_created() ) )
            );
        }

        echo '</tbody></table>';
        echo '<script>window.print();</script>';
        echo '</body></html>';
        exit;
    }
}

endif;
