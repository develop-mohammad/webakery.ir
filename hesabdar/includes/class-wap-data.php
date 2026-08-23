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
            'product_cat'  => absint( $src['product_cat'] ?? 0 ),
            'product_ids'  => self::parse_ids( $src['product_ids'] ?? array() ),
            'min_total'    => isset( $src['min_total'] ) && $src['min_total'] !== '' ? (float) $src['min_total'] : null,
            'max_total'    => isset( $src['max_total'] ) && $src['max_total'] !== '' ? (float) $src['max_total'] : null,
            'min_count'    => isset( $src['min_count'] ) && $src['min_count'] !== '' ? (int) $src['min_count'] : null,
            'max_count'    => isset( $src['max_count'] ) && $src['max_count'] !== '' ? (int) $src['max_count'] : null,
        );
    }

    /** پارس لیست شناسه از GET/POST (آرایه یا رشتهٔ جداشده با ویرگول). */
    public static function parse_ids( $raw ): array {
        if ( is_string( $raw ) ) {
            $raw = preg_split( '/[\s,]+/', $raw );
        }
        if ( ! is_array( $raw ) ) {
            return array();
        }
        $ids = array();
        foreach ( $raw as $v ) {
            $id = absint( $v );
            if ( $id > 0 ) {
                $ids[ $id ] = $id;
            }
        }
        return array_values( $ids );
    }

    /** دسته‌بندی‌های محصول برای فیلتر گزارش. */
    public static function get_product_categories(): array {
        if ( ! taxonomy_exists( 'product_cat' ) ) {
            return array();
        }
        $terms = get_terms( array(
            'taxonomy'   => 'product_cat',
            'hide_empty' => false,
            'orderby'    => 'name',
            'order'      => 'ASC',
        ) );
        if ( is_wp_error( $terms ) || empty( $terms ) ) {
            return array();
        }
        $out = array();
        foreach ( $terms as $term ) {
            $out[] = array(
                'id'   => (int) $term->term_id,
                'name' => $term->name,
                'slug' => $term->slug,
            );
        }
        return $out;
    }

    /** آیا محصول (یا والد ورییشن) عضو این دسته است؟ */
    public static function product_in_category( int $product_id, int $cat_id ): bool {
        if ( $cat_id <= 0 || $product_id <= 0 ) {
            return $cat_id <= 0;
        }
        $ids = array( $product_id );
        $product = wc_get_product( $product_id );
        if ( $product && $product->get_parent_id() ) {
            $ids[] = (int) $product->get_parent_id();
        }
        foreach ( $ids as $pid ) {
            $term_ids = wc_get_product_term_ids( $pid, 'product_cat' );
            if ( in_array( $cat_id, $term_ids, true ) ) {
                return true;
            }
        }
        return false;
    }

    /**
     * وقتی وضعیت مشخص انتخاب شده، همان وضعیت ملاک است؛
     * وقتی «همه»، فقط سفارش‌های موفق (مثل قبل).
     */
    public static function should_require_paid( array $f ): bool {
        return empty( $f['order_status'] );
    }

    /** آیا آیتم سفارش با یکی از شناسه‌های انتخاب‌شده (محصول یا ورییشن) جور است؟ */
    public static function line_matches_products( $item, array $product_ids ): bool {
        if ( empty( $product_ids ) ) {
            return false;
        }
        $pid = (int) $item->get_product_id();
        $vid = (int) $item->get_variation_id();
        return in_array( $pid, $product_ids, true ) || ( $vid > 0 && in_array( $vid, $product_ids, true ) );
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
    public static function get_product_sales( array $orders, bool $paid_only = true, int $product_cat = 0 ): array {
        $products = array();
        foreach ( $orders as $order ) {
            if ( $paid_only && ! self::is_paid_order( $order ) ) {
                continue;
            }
            foreach ( $order->get_items() as $item ) {
                $pid = (int) $item->get_product_id();
                if ( $product_cat > 0 && ! self::product_in_category( $pid, $product_cat ) ) {
                    continue;
                }
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

    public static function get_product_drilldown( array $orders, int $product_id, bool $paid_only = true ): array {
        $rows = array();
        foreach ( $orders as $order ) {
            if ( $paid_only && ! self::is_paid_order( $order ) ) {
                continue;
            }
            foreach ( $order->get_items() as $item ) {
                if ( (int) $item->get_product_id() === $product_id || (int) $item->get_variation_id() === $product_id ) {
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

    /**
     * مشتریان یکتایی که حداقل یکی از محصولات انتخاب‌شده را در سفارش‌های فیلترشده خریده‌اند.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function get_buyers_by_products( array $orders, array $product_ids, bool $paid_only = true ): array {
        $product_ids = self::parse_ids( $product_ids );
        if ( empty( $product_ids ) ) {
            return array();
        }

        $buyers = array();
        foreach ( $orders as $order ) {
            if ( $paid_only && ! self::is_paid_order( $order ) ) {
                continue;
            }

            $qty = 0;
            $revenue = 0.0;
            $matched = false;
            foreach ( $order->get_items() as $item ) {
                if ( ! self::line_matches_products( $item, $product_ids ) ) {
                    continue;
                }
                $matched = true;
                $qty     += (int) $item->get_quantity();
                $revenue += (float) $item->get_total();
            }
            if ( ! $matched ) {
                continue;
            }

            $customer_id = (int) $order->get_customer_id();
            $email       = strtolower( trim( (string) $order->get_billing_email() ) );
            $phone       = preg_replace( '/\D+/', '', (string) $order->get_billing_phone() );
            if ( $customer_id > 0 ) {
                $key = 'u:' . $customer_id;
            } elseif ( $email !== '' ) {
                $key = 'e:' . $email;
            } elseif ( $phone !== '' ) {
                $key = 'p:' . $phone;
            } else {
                $key = 'o:' . $order->get_id();
            }

            $created = $order->get_date_created();
            $ts      = $created ? $created->getTimestamp() : 0;

            if ( ! isset( $buyers[ $key ] ) ) {
                $buyers[ $key ] = array(
                    'key'          => $key,
                    'customer_id'  => $customer_id,
                    'name'         => trim( $order->get_formatted_billing_full_name() ),
                    'phone'        => (string) $order->get_billing_phone(),
                    'email'        => (string) $order->get_billing_email(),
                    'city'         => (string) $order->get_billing_city(),
                    'orders_count' => 0,
                    'qty'          => 0,
                    'revenue'      => 0.0,
                    'last_order_ts'=> 0,
                    'order_ids'    => array(),
                );
            }

            $buyers[ $key ]['orders_count']++;
            $buyers[ $key ]['qty']       += $qty;
            $buyers[ $key ]['revenue']   += $revenue;
            $buyers[ $key ]['order_ids'][] = $order->get_id();
            if ( $ts >= $buyers[ $key ]['last_order_ts'] ) {
                $buyers[ $key ]['last_order_ts'] = $ts;
                if ( $buyers[ $key ]['name'] === '' ) {
                    $buyers[ $key ]['name'] = trim( $order->get_formatted_billing_full_name() );
                }
                if ( $buyers[ $key ]['phone'] === '' ) {
                    $buyers[ $key ]['phone'] = (string) $order->get_billing_phone();
                }
                if ( $buyers[ $key ]['email'] === '' ) {
                    $buyers[ $key ]['email'] = (string) $order->get_billing_email();
                }
                if ( $buyers[ $key ]['city'] === '' ) {
                    $buyers[ $key ]['city'] = (string) $order->get_billing_city();
                }
            }
        }

        $list = array_values( $buyers );
        usort( $list, function( $a, $b ) {
            if ( $a['qty'] !== $b['qty'] ) {
                return $b['qty'] <=> $a['qty'];
            }
            return $b['last_order_ts'] <=> $a['last_order_ts'];
        } );
        return $list;
    }

    /** برچسب‌های نام محصول برای شناسه‌های انتخاب‌شده. */
    public static function product_labels( array $product_ids ): array {
        $out = array();
        foreach ( self::parse_ids( $product_ids ) as $pid ) {
            $product = wc_get_product( $pid );
            $out[ $pid ] = $product ? $product->get_name() : ( '#' . $pid );
        }
        return $out;
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
