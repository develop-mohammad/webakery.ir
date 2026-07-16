<?php
defined( 'ABSPATH' ) || exit;

/**
 * واکشی و گروه‌بندی داده‌های فروش ووکامرس برای پرتال حسابدار.
 */
class WAP_Data {

    public static function get_filters( ?array $src = null ) {
        if ( $src === null ) {
            $src = $_GET;
        }
        $date_from = sanitize_text_field( $src['date_from'] ?? '' );
        $date_to   = sanitize_text_field( $src['date_to'] ?? '' );

        // بدون بازه → پیش‌فرض امسال (جلوگیری از واکشی همه سفارش‌ها و OOM)
        if ( $date_from === '' && $date_to === '' ) {
            $today     = WAP_Jalali::today();
            $date_from = sprintf( '%d/01/01', $today['y'] );
            $date_to   = sprintf( '%d/%02d/%02d', $today['y'], $today['m'], $today['d'] );
        }

        return array(
            'date_from'    => $date_from,
            'date_to'      => $date_to,
            'period'       => in_array( $src['period'] ?? '', array( 'day', 'week', 'month', 'quarter', 'year' ), true ) ? $src['period'] : 'month',
            'order_status' => sanitize_text_field( $src['order_status'] ?? '' ),
            'min_total'    => isset( $src['min_total'] ) && $src['min_total'] !== '' ? (float) $src['min_total'] : null,
            'max_total'    => isset( $src['max_total'] ) && $src['max_total'] !== '' ? (float) $src['max_total'] : null,
            'min_count'    => isset( $src['min_count'] ) && $src['min_count'] !== '' ? (int) $src['min_count'] : null,
            'max_count'    => isset( $src['max_count'] ) && $src['max_count'] !== '' ? (int) $src['max_count'] : null,
        );
    }

    // وضعیت‌هایی که در مجموع فروش لحاظ نمی‌شوند (ناموفق، لغوشده، مسترد، در انتظار پرداخت)
    const NET_EXCLUDED_STATUSES = array( 'pending', 'failed', 'cancelled', 'refunded', 'checkout-draft', 'draft', 'trash' );

    /** آیا سفارش موفق/پرداخت‌شده است و باید در جمع فروش بیاید؟ */
    public static function is_paid_order( $order ): bool {
        if ( ! $order || ! is_object( $order ) || ! method_exists( $order, 'get_status' ) ) {
            return false;
        }
        $excluded = apply_filters( 'hesabdar_excluded_order_statuses', self::NET_EXCLUDED_STATUSES );
        return ! in_array( $order->get_status(), $excluded, true );
    }

    /** فقط سفارش‌های موفق را برمی‌گرداند. */
    public static function filter_paid_orders( array $orders ): array {
        return array_values( array_filter( $orders, array( __CLASS__, 'is_paid_order' ) ) );
    }

    public static function get_orders( $f ) {
        if ( ! class_exists( 'WooCommerce' ) ) return array();
        $all_statuses = array_map( function( $s ) { return str_replace( 'wc-', '', $s ); }, array_keys( wc_get_order_statuses() ) );
        $args = array( 'limit' => -1, 'return' => 'objects', 'type' => 'shop_order', 'status' => $all_statuses );
        if ( ! empty( $f['order_status'] ) ) { $args['status'] = array( $f['order_status'] ); }
        $ts_from = ! empty( $f['date_from'] ) ? WAP_Jalali::str_to_timestamp( $f['date_from'], false ) : 0;
        $ts_to   = ! empty( $f['date_to'] )   ? WAP_Jalali::str_to_timestamp( $f['date_to'],   true  ) : 0;
        if ( $ts_from && $ts_to ) { $args['date_created'] = $ts_from . '...' . $ts_to; }
        elseif ( $ts_from ) { $args['date_created'] = '>=' . $ts_from; }
        elseif ( $ts_to )   { $args['date_created'] = '<=' . $ts_to; }
        $orders = wc_get_orders( $args );
        // اطمینان اضافه: بعضی نسخه‌های ووکامرس پارامتر date_created را به‌درستی محدود
        // نمی‌کنند؛ اینجا دوباره روی timestamp واقعی هر سفارش فیلتر قطعی اعمال می‌شود.
        if ( $ts_from || $ts_to ) {
            $orders = array_values( array_filter( $orders, function( $order ) use ( $ts_from, $ts_to ) {
                $created = $order->get_date_created();
                if ( ! $created ) return false;
                $ts = $created->getTimestamp();
                if ( $ts_from && $ts < $ts_from ) return false;
                if ( $ts_to && $ts > $ts_to ) return false;
                return true;
            } ) );
        }
        return $orders;
    }

    /**
     * فروش ناخالص (همه سفارشات بازه) در برابر فروش خالص (بدون لغوشده/مسترد/ناموفق/در انتظار).
     */
    public static function gross_vs_net( array $orders ): array {
        $gross_total = 0.0; $gross_count = 0;
        $net_total   = 0.0; $net_count   = 0;
        foreach ( $orders as $order ) {
            $total = (float) $order->get_total();
            $gross_total += $total;
            $gross_count++;
            if ( ! in_array( $order->get_status(), self::NET_EXCLUDED_STATUSES, true ) ) {
                $net_total += $total;
                $net_count++;
            }
        }
        return array(
            'gross_total' => $gross_total,
            'gross_count' => $gross_count,
            'net_total'   => $net_total,
            'net_count'   => $net_count,
        );
    }

    public static function build_rows( $orders, $period ) {
        $groups = array();
        foreach ( $orders as $order ) {
            if ( ! self::is_paid_order( $order ) ) {
                continue;
            }
            $created = $order->get_date_created();
            if ( ! $created ) continue;
            list( $jy, $jm, $jd ) = WAP_Jalali::to_jalali( (int) $created->date( 'Y' ), (int) $created->date( 'n' ), (int) $created->date( 'j' ) );
            list( $key, $lbl ) = WAP_Jalali::period_key( $jy, $jm, $jd, $period );
            if ( ! isset( $groups[ $key ] ) ) {
                $groups[ $key ] = array( 'label' => $lbl, 'count' => 0, 'total' => 0.0 );
            }
            $groups[ $key ]['count']++;
            $groups[ $key ]['total'] += (float) $order->get_total();
        }
        ksort( $groups );
        return $groups;
    }

    public static function apply_filter( $groups, $f ) {
        return array_filter( $groups, function( $g ) use ( $f ) {
            if ( $f['min_total'] !== null && $g['total'] < $f['min_total'] ) return false;
            if ( $f['max_total'] !== null && $g['total'] > $f['max_total'] ) return false;
            if ( $f['min_count'] !== null && $g['count'] < $f['min_count'] ) return false;
            if ( $f['max_count'] !== null && $g['count'] > $f['max_count'] ) return false;
            return true;
        } );
    }

    public static function quick_presets() {
        $today  = WAP_Jalali::today();
        $jy = $today['y']; $jm = $today['m']; $jd = $today['d'];
        $now_ts = current_time( 'timestamp' );

        $dow = (int) date( 'w', $now_ts );
        $days_since_saturday = ( $dow + 1 ) % 7;
        $week_start_ts = $now_ts - $days_since_saturday * DAY_IN_SECONDS;
        $week_end_ts   = $week_start_ts + 6 * DAY_IN_SECONDS;
        list( $wsy, $wsm, $wsd ) = WAP_Jalali::to_jalali( (int) date( 'Y', $week_start_ts ), (int) date( 'n', $week_start_ts ), (int) date( 'j', $week_start_ts ) );
        list( $wey, $wem, $wed ) = WAP_Jalali::to_jalali( (int) date( 'Y', $week_end_ts ), (int) date( 'n', $week_end_ts ), (int) date( 'j', $week_end_ts ) );

        $season_start_m = intdiv( $jm - 1, 3 ) * 3 + 1;
        $season_end_m   = $season_start_m + 2;
        $season_end_d   = WAP_Jalali::month_length( $jy, $season_end_m );

        $yesterday_ts = $now_ts - DAY_IN_SECONDS;
        list( $yy, $ym, $yd ) = WAP_Jalali::to_jalali( (int) date( 'Y', $yesterday_ts ), (int) date( 'n', $yesterday_ts ), (int) date( 'j', $yesterday_ts ) );

        $seven_ago_ts = $now_ts - 6 * DAY_IN_SECONDS;
        list( $sy, $sm, $sd ) = WAP_Jalali::to_jalali( (int) date( 'Y', $seven_ago_ts ), (int) date( 'n', $seven_ago_ts ), (int) date( 'j', $seven_ago_ts ) );

        $fmt = function( $y, $m, $d ) { return sprintf( '%d/%02d/%02d', $y, $m, $d ); };

        return array(
            'امروز'      => array( $fmt( $jy, $jm, $jd ), $fmt( $jy, $jm, $jd ) ),
            'دیروز'      => array( $fmt( $yy, $ym, $yd ), $fmt( $yy, $ym, $yd ) ),
            '۷ روز اخیر' => array( $fmt( $sy, $sm, $sd ), $fmt( $jy, $jm, $jd ) ),
            'این هفته'   => array( $fmt( $wsy, $wsm, $wsd ), $fmt( $wey, $wem, $wed ) ),
            'این ماه'    => array( $fmt( $jy, $jm, 1 ), $fmt( $jy, $jm, WAP_Jalali::month_length( $jy, $jm ) ) ),
            'این فصل'    => array( $fmt( $jy, $season_start_m, 1 ), $fmt( $jy, $season_end_m, $season_end_d ) ),
            'امسال'      => array( $fmt( $jy, 1, 1 ), $fmt( $jy, 12, WAP_Jalali::month_length( $jy, 12 ) ) ),
            'سال گذشته'  => array( $fmt( $jy - 1, 1, 1 ), $fmt( $jy - 1, 12, WAP_Jalali::month_length( $jy - 1, 12 ) ) ),
        );
    }

    public static function is_hpos(): bool {
        return class_exists( 'Automattic\WooCommerce\Utilities\OrderUtil' )
            && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
    }

    public static function payment_label( $method ) {
        $labels = array(
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
        );
        return isset( $labels[ $method ] ) ? $labels[ $method ] : $method;
    }

    // خلاصه محصولات یک سفارش
    public static function order_products_summary( $order ): string {
        $parts = array();
        foreach ( $order->get_items() as $item ) {
            $parts[] = $item->get_name() . ' x' . $item->get_quantity();
        }
        return implode( ' | ', $parts );
    }

    /** خلاصه محصولات برای نمایش چندخطی در جدول */
    public static function order_products_lines( $order ): array {
        $parts = array();
        foreach ( $order->get_items() as $item ) {
            $parts[] = $item->get_name() . ' x' . $item->get_quantity();
        }
        return $parts;
    }

    public static function get_payment_methods(): array {
        global $wpdb;
        if ( self::is_hpos() ) {
            return $wpdb->get_col( "SELECT DISTINCT payment_method FROM {$wpdb->prefix}wc_orders WHERE type='shop_order' AND payment_method != '' ORDER BY payment_method" );
        }
        return $wpdb->get_col( "SELECT DISTINCT meta_value FROM {$wpdb->postmeta} WHERE meta_key='_payment_method' AND meta_value != '' ORDER BY meta_value" );
    }

    // ── لیست سفارش‌ها (جستجو/فیلتر/مرتب‌سازی/صفحه‌بندی) ──────────────────────────
    public static function get_order_list_filters() {
        $src = ( isset( $_POST['wci_bulk_apply'] ) && isset( $_POST['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'wci_bulk_orders' ) ) ? $_POST : $_GET;
        return array_merge( self::get_filters( $src ), array(
            's'              => sanitize_text_field( $src['s'] ?? '' ),
            'payment_method' => sanitize_text_field( $src['payment_method'] ?? '' ),
            'orderby'        => sanitize_text_field( $src['orderby'] ?? 'date' ),
            'order'          => in_array( $src['order'] ?? '', array( 'ASC', 'DESC' ), true ) ? $src['order'] : 'DESC',
            'per_page'       => absint( $src['per_page'] ?? 25 ),
            'paged'          => max( 1, absint( $src['paged'] ?? 1 ) ),
        ) );
    }

    public static function get_filtered_order_list( array $f ): array {
        $empty = array( array(), 0, array() );
        if ( ! class_exists( 'WooCommerce' ) || ! function_exists( 'wc_get_orders' ) ) {
            return $empty;
        }

        $all_statuses = array_map( function( $s ) { return str_replace( 'wc-', '', $s ); }, array_keys( wc_get_order_statuses() ) );
        $args = array( 'limit' => -1, 'orderby' => 'date', 'order' => $f['order'], 'return' => 'objects', 'type' => 'shop_order', 'status' => $all_statuses );
        if ( ! empty( $f['order_status'] ) ) { $args['status'] = array( $f['order_status'] ); }
        if ( ! empty( $f['payment_method'] ) ) { $args['payment_method'] = $f['payment_method']; }

        $ts_from = ! empty( $f['date_from'] ) ? WAP_Jalali::str_to_timestamp( $f['date_from'], false ) : 0;
        $ts_to   = ! empty( $f['date_to'] )   ? WAP_Jalali::str_to_timestamp( $f['date_to'],   true  ) : 0;
        if ( $ts_from && $ts_to ) { $args['date_created'] = $ts_from . '...' . $ts_to; }
        elseif ( $ts_from ) { $args['date_created'] = '>=' . $ts_from; }
        elseif ( $ts_to )   { $args['date_created'] = '<=' . $ts_to; }

        if ( ! empty( $f['s'] ) ) {
            global $wpdb;
            $like = '%' . $wpdb->esc_like( $f['s'] ) . '%';
            if ( self::is_hpos() ) {
                $ids = $wpdb->get_col( $wpdb->prepare(
                    "SELECT id FROM {$wpdb->prefix}wc_orders
                     WHERE type = 'shop_order'
                     AND ( billing_first_name LIKE %s OR billing_last_name LIKE %s
                           OR billing_email LIKE %s OR billing_phone LIKE %s
                           OR billing_city LIKE %s )",
                    $like, $like, $like, $like, $like
                ) );
            } else {
                $ids = $wpdb->get_col( $wpdb->prepare(
                    "SELECT DISTINCT p.ID FROM {$wpdb->posts} p
                     INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID
                     WHERE p.post_type = 'shop_order'
                     AND pm.meta_key IN ('_billing_first_name','_billing_last_name','_billing_phone','_billing_email','_billing_city')
                     AND pm.meta_value LIKE %s",
                    $like
                ) );
            }
            if ( empty( $ids ) ) {
                return $empty;
            }
            $args['include'] = $ids;
        }

        $orders = wc_get_orders( $args );
        if ( $ts_from || $ts_to ) {
            $orders = array_values( array_filter( $orders, function( $order ) use ( $ts_from, $ts_to ) {
                $created = $order->get_date_created();
                if ( ! $created ) return false;
                $ts = $created->getTimestamp();
                if ( $ts_from && $ts < $ts_from ) return false;
                if ( $ts_to && $ts > $ts_to ) return false;
                return true;
            } ) );
        }

        $asc = $f['order'] === 'ASC';
        switch ( $f['orderby'] ) {
            case 'total':
                usort( $orders, function( $a, $b ) use ( $asc ) { return $asc ? $a->get_total() - $b->get_total() : $b->get_total() - $a->get_total(); } );
                break;
            case 'first_name':
                usort( $orders, function( $a, $b ) use ( $asc ) { return $asc ? strcmp( $a->get_billing_first_name(), $b->get_billing_first_name() ) : strcmp( $b->get_billing_first_name(), $a->get_billing_first_name() ); } );
                break;
            case 'city':
                usort( $orders, function( $a, $b ) use ( $asc ) { return $asc ? strcmp( $a->get_billing_city(), $b->get_billing_city() ) : strcmp( $b->get_billing_city(), $a->get_billing_city() ); } );
                break;
        }

        $total = count( $orders );
        $slice = array_slice( $orders, ( $f['paged'] - 1 ) * $f['per_page'], $f['per_page'] );
        return array( $slice, $total, $orders );
    }

    // ── فروش محصولات ─────────────────────────────────────────────────────────────
    public static function get_product_sales( array $orders ): array {
        $products = array();
        foreach ( $orders as $order ) {
            if ( ! self::is_paid_order( $order ) ) {
                continue;
            }
            foreach ( $order->get_items() as $item ) {
                $pid = $item->get_product_id();
                if ( ! isset( $products[ $pid ] ) ) {
                    $products[ $pid ] = array(
                        'pid'     => $pid,
                        'name'    => $item->get_name(),
                        'sku'     => ( $item->get_product() && $item->get_product()->get_sku() ) ? $item->get_product()->get_sku() : '',
                        'qty'     => 0,
                        'revenue' => 0.0,
                        'orders'  => 0,
                    );
                }
                $products[ $pid ]['qty']     += $item->get_quantity();
                $products[ $pid ]['revenue'] += (float) $item->get_total();
                $products[ $pid ]['orders']++;
            }
        }
        usort( $products, function( $a, $b ) { return $b['revenue'] <=> $a['revenue']; } );
        return array_values( $products );
    }

    public static function get_product_drilldown( array $orders, int $product_id ): array {
        $rows = array();
        foreach ( $orders as $order ) {
            if ( ! self::is_paid_order( $order ) ) {
                continue;
            }
            foreach ( $order->get_items() as $item ) {
                if ( (int) $item->get_product_id() === $product_id ) {
                    $rows[] = array( 'order' => $order, 'qty' => $item->get_quantity(), 'revenue' => (float) $item->get_total() );
                    break;
                }
            }
        }
        usort( $rows, function( $a, $b ) {
            $ta = $a['order']->get_date_created() ? $a['order']->get_date_created()->getTimestamp() : 0;
            $tb = $b['order']->get_date_created() ? $b['order']->get_date_created()->getTimestamp() : 0;
            return $tb <=> $ta;
        } );
        return $rows;
    }

    // ── نمودار دایره‌ای وضعیت سفارش‌ها ─────────────────────────────────────────────
    public static function status_breakdown( array $orders ): array {
        $counts = array();
        foreach ( $orders as $order ) {
            $status = $order->get_status();
            if ( ! isset( $counts[ $status ] ) ) { $counts[ $status ] = 0; }
            $counts[ $status ]++;
        }
        arsort( $counts );
        return $counts;
    }
}
