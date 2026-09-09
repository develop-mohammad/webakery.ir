<?php
defined( 'ABSPATH' ) || exit;

/**
 * نمودارهای سبک Google Search Console (خطی/ناحیه‌ای + مقایسه).
 */
class WAP_Chart {

    /**
     * ردیف‌های دو سری دوره‌ای را بر اساس ترتیب اندیس هم‌تراز می‌کند (مثل Compare در GSC).
     *
     * @param array $primary  خروجی build_rows (کلیددار)
     * @param array $compare  خروجی build_rows بازه دوم
     * @return array{labels:string[],a:float[],b:float[],a_count:int[],b_count:int[],rows:array}
     */
    public static function align_period_series( array $primary, array $compare ): array {
        $a = array_values( $primary );
        $b = array_values( $compare );
        $n = max( count( $a ), count( $b ) );
        $labels = array();
        $va = array();
        $vb = array();
        $ca = array();
        $cb = array();
        $rows = array();
        for ( $i = 0; $i < $n; $i++ ) {
            $ra = $a[ $i ] ?? null;
            $rb = $b[ $i ] ?? null;
            $label_a = $ra['label'] ?? '—';
            $label_b = $rb['label'] ?? '—';
            $labels[] = $label_a === $label_b ? $label_a : ( $label_a . ' / ' . $label_b );
            $ta = $ra ? (float) $ra['total'] : 0.0;
            $tb = $rb ? (float) $rb['total'] : 0.0;
            $na = $ra ? (int) $ra['count'] : 0;
            $nb = $rb ? (int) $rb['count'] : 0;
            $va[] = $ta;
            $vb[] = $tb;
            $ca[] = $na;
            $cb[] = $nb;
            $delta = $ta - $tb;
            $pct   = $tb > 0 ? ( $delta / $tb ) * 100 : ( $ta > 0 ? 100.0 : 0.0 );
            $rows[] = array(
                'label'       => $labels[ $i ],
                'label_a'     => $label_a,
                'label_b'     => $label_b,
                'total_a'     => $ta,
                'total_b'     => $tb,
                'count_a'     => $na,
                'count_b'     => $nb,
                'delta_total' => $delta,
                'delta_pct'   => $pct,
            );
        }
        return array(
            'labels'  => $labels,
            'a'       => $va,
            'b'       => $vb,
            'a_count' => $ca,
            'b_count' => $cb,
            'rows'    => $rows,
        );
    }

    /**
     * مقایسه فروش محصولات دو بازه بر اساس pid.
     *
     * @param array $primary get_product_sales بازه اول
     * @param array $compare get_product_sales بازه دوم
     */
    public static function align_product_series( array $primary, array $compare, int $limit = 15 ): array {
        $map_a = array();
        foreach ( $primary as $p ) {
            $map_a[ (int) $p['pid'] ] = $p;
        }
        $map_b = array();
        foreach ( $compare as $p ) {
            $map_b[ (int) $p['pid'] ] = $p;
        }
        $pids = array_unique( array_merge( array_keys( $map_a ), array_keys( $map_b ) ) );
        $rows = array();
        foreach ( $pids as $pid ) {
            $pa = $map_a[ $pid ] ?? null;
            $pb = $map_b[ $pid ] ?? null;
            $rev_a = $pa ? (float) $pa['revenue'] : 0.0;
            $rev_b = $pb ? (float) $pb['revenue'] : 0.0;
            $qty_a = $pa ? (int) $pa['qty'] : 0;
            $qty_b = $pb ? (int) $pb['qty'] : 0;
            $name  = $pa['name'] ?? ( $pb['name'] ?? ( '#' . $pid ) );
            $delta = $rev_a - $rev_b;
            $pct   = $rev_b > 0 ? ( $delta / $rev_b ) * 100 : ( $rev_a > 0 ? 100.0 : 0.0 );
            $rows[] = array(
                'pid'         => $pid,
                'name'        => $name,
                'sku'         => $pa['sku'] ?? ( $pb['sku'] ?? '' ),
                'revenue_a'   => $rev_a,
                'revenue_b'   => $rev_b,
                'qty_a'       => $qty_a,
                'qty_b'       => $qty_b,
                'delta'       => $delta,
                'delta_pct'   => $pct,
                'sort'        => max( $rev_a, $rev_b ),
            );
        }
        usort( $rows, function( $x, $y ) {
            return $y['sort'] <=> $x['sort'];
        } );
        $rows = array_slice( $rows, 0, max( 1, $limit ) );
        return array(
            'rows'   => $rows,
            'labels' => array_column( $rows, 'name' ),
            'a'      => array_map( 'floatval', array_column( $rows, 'revenue_a' ) ),
            'b'      => array_map( 'floatval', array_column( $rows, 'revenue_b' ) ),
        );
    }

    /**
     * سری روزانه هم‌تراز برای مقایسه روی‌هم (دقیق مثل Compare در Search Console).
     * محور X = روز نسبی بازه (روز ۱، ۲، …) تا دو خط روی هم دیده شوند.
     *
     * @param array<int,\WC_Order> $orders_a
     * @param array<int,\WC_Order> $orders_b
     * @return array{labels:string[],a:float[],b:float[],a_count:int[],b_count:int[],total_a:float,total_b:float,count_a:int,count_b:int,delta_pct:float,label_dates_a:string[],label_dates_b:string[]}
     */
    public static function build_date_overlay( array $orders_a, array $orders_b, string $from_a, string $to_a, string $from_b, string $to_b ): array {
        $seq_a = self::daily_sequence( $orders_a, $from_a, $to_a );
        $seq_b = self::daily_sequence( $orders_b, $from_b, $to_b );
        $n     = max( count( $seq_a ), count( $seq_b ), 1 );
        $labels = array();
        $a = array();
        $b = array();
        $ca = array();
        $cb = array();
        $dates_a = array();
        $dates_b = array();
        $total_a = 0.0;
        $total_b = 0.0;
        $count_a = 0;
        $count_b = 0;
        for ( $i = 0; $i < $n; $i++ ) {
            $ra = $seq_a[ $i ] ?? array( 'label' => 'روز ' . ( $i + 1 ), 'date' => '', 'total' => 0.0, 'count' => 0 );
            $rb = $seq_b[ $i ] ?? array( 'label' => 'روز ' . ( $i + 1 ), 'date' => '', 'total' => 0.0, 'count' => 0 );
            // برچسب محور: تاریخ بازه فعلی (مثل GSC) و در صورت نیاز شماره روز
            $labels[]  = $ra['date'] !== '' ? $ra['date'] : ( 'روز ' . ( $i + 1 ) );
            $a[]       = (float) $ra['total'];
            $b[]       = (float) $rb['total'];
            $ca[]      = (int) $ra['count'];
            $cb[]      = (int) $rb['count'];
            $dates_a[] = $ra['date'];
            $dates_b[] = $rb['date'];
            $total_a  += (float) $ra['total'];
            $total_b  += (float) $rb['total'];
            $count_a  += (int) $ra['count'];
            $count_b  += (int) $rb['count'];
        }
        $delta_pct = $total_b > 0 ? ( ( $total_a - $total_b ) / $total_b ) * 100 : ( $total_a > 0 ? 100.0 : 0.0 );
        return array(
            'labels'        => $labels,
            'a'             => $a,
            'b'             => $b,
            'a_count'       => $ca,
            'b_count'       => $cb,
            'total_a'       => $total_a,
            'total_b'       => $total_b,
            'count_a'       => $count_a,
            'count_b'       => $count_b,
            'delta_pct'     => $delta_pct,
            'label_dates_a' => $dates_a,
            'label_dates_b' => $dates_b,
        );
    }

    /**
     * مجموع فروش روزانه از from تا to (روزهای بدون سفارش = صفر).
     *
     * @param array<int,\WC_Order> $orders
     * @return array<int,array{label:string,date:string,total:float,count:int}>
     */
    public static function daily_sequence( array $orders, string $from, string $to ): array {
        $ts_from = WAP_Jalali::str_to_timestamp( $from, false );
        $ts_to   = WAP_Jalali::str_to_timestamp( $to, true );
        if ( ! $ts_from || ! $ts_to || $ts_from > $ts_to ) {
            return array();
        }
        $map = array();
        foreach ( $orders as $order ) {
            if ( ! WAP_Data::is_paid_order( $order ) ) {
                continue;
            }
            $created = $order->get_date_created();
            if ( ! $created ) {
                continue;
            }
            $ts = $created->getTimestamp();
            if ( $ts < $ts_from || $ts > $ts_to ) {
                continue;
            }
            list( $jy, $jm, $jd ) = WAP_Jalali::to_jalali(
                (int) $created->date( 'Y' ),
                (int) $created->date( 'n' ),
                (int) $created->date( 'j' )
            );
            $key = sprintf( '%04d-%02d-%02d', $jy, $jm, $jd );
            if ( ! isset( $map[ $key ] ) ) {
                $map[ $key ] = array(
                    'total' => 0.0,
                    'count' => 0,
                    'y'     => $jy,
                    'm'     => $jm,
                    'd'     => $jd,
                );
            }
            $map[ $key ]['total'] += (float) $order->get_total();
            $map[ $key ]['count']++;
        }

        $out = array();
        $cursor = $ts_from;
        // گام روزانه تا انتهای بازه
        $end_day = strtotime( date( 'Y-m-d', $ts_to ) . ' 00:00:00' );
        $guard = 0;
        while ( $cursor <= $ts_to + 1 && $guard < 400 ) {
            $guard++;
            $gy = (int) date( 'Y', $cursor );
            $gm = (int) date( 'n', $cursor );
            $gd = (int) date( 'j', $cursor );
            list( $jy, $jm, $jd ) = WAP_Jalali::to_jalali( $gy, $gm, $gd );
            $key  = sprintf( '%04d-%02d-%02d', $jy, $jm, $jd );
            $date = WAP_Jalali::format( $jy, $jm, $jd );
            $row  = $map[ $key ] ?? null;
            $out[] = array(
                'label' => $date,
                'date'  => $date,
                'total' => $row ? (float) $row['total'] : 0.0,
                'count' => $row ? (int) $row['count'] : 0,
            );
            $next = strtotime( '+1 day', strtotime( date( 'Y-m-d', $cursor ) . ' 12:00:00' ) );
            if ( $next === false || $next <= $cursor ) {
                break;
            }
            // توقف وقتی از روز پایانی گذشتیم
            if ( strtotime( date( 'Y-m-d', $next ) . ' 00:00:00' ) > $end_day ) {
                break;
            }
            $cursor = $next;
        }
        return $out;
    }

    /**
     * نمودار خطی/ناحیه‌ای شبیه Search Console.
     *
     * @param array $series ['a'=>float[], 'b'=>float[]|null, 'labels'=>string[], total_a?, total_b?, delta_pct?]
     * @param array $opts   title, legend_a, legend_b, height, dual, show_metrics
     */
    public static function render_gsc_line( array $series, array $opts = array() ): void {
        $labels   = array_values( $series['labels'] ?? array() );
        $a        = array_map( 'floatval', array_values( $series['a'] ?? array() ) );
        $b        = isset( $series['b'] ) ? array_map( 'floatval', array_values( $series['b'] ) ) : null;
        $dual     = $b !== null && ! empty( $opts['dual'] );
        $n        = max( count( $a ), $dual ? count( $b ) : 0 );
        if ( $n < 1 ) {
            echo '<div class="wap-empty">داده‌ای برای نمودار نیست.</div>';
            return;
        }
        // هم‌طول کردن سری‌ها
        while ( count( $a ) < $n ) {
            $a[] = 0.0;
            $labels[] = $labels[ count( $labels ) - 1 ] ?? '';
        }
        if ( $dual ) {
            while ( count( $b ) < $n ) {
                $b[] = 0.0;
            }
        }
        $title    = $opts['title'] ?? 'روند فروش';
        $leg_a    = $opts['legend_a'] ?? 'بازه فعلی';
        $leg_b    = $opts['legend_b'] ?? 'بازه مقایسه';
        $height   = (int) ( $opts['height'] ?? ( $dual ? 340 : 280 ) );
        $show_met = ! empty( $opts['show_metrics'] ) || $dual;
        $width    = 960;
        $pad_l    = 56;
        $pad_r    = 18;
        $pad_t    = 28;
        $pad_b    = 48;
        $plot_w   = $width - $pad_l - $pad_r;
        $plot_h   = $height - $pad_t - $pad_b;
        $max_v    = max( 1.0, max( $a ), $dual ? max( $b ) : 0 );
        $max_v   *= 1.12;

        $pts_a = array();
        $pts_b = array();
        for ( $i = 0; $i < $n; $i++ ) {
            $x = $pad_l + ( $n === 1 ? $plot_w / 2 : ( $i / ( $n - 1 ) ) * $plot_w );
            $ya = $pad_t + $plot_h - ( $a[ $i ] / $max_v ) * $plot_h;
            $pts_a[] = array( $x, $ya, $a[ $i ], $labels[ $i ] ?? '' );
            if ( $dual ) {
                $yb = $pad_t + $plot_h - ( $b[ $i ] / $max_v ) * $plot_h;
                $pts_b[] = array( $x, $yb, $b[ $i ], $labels[ $i ] ?? '' );
            }
        }

        $line_a = self::polyline( $pts_a );
        $area_a = self::area_path( $pts_a, $pad_t + $plot_h );
        $line_b = $dual ? self::polyline( $pts_b ) : '';
        $area_b = $dual ? self::area_path( $pts_b, $pad_t + $plot_h ) : '';
        $uid    = 'wapgsc' . substr( md5( $title . wp_json_encode( $labels ) . microtime( true ) ), 0, 8 );

        $total_a = isset( $series['total_a'] ) ? (float) $series['total_a'] : array_sum( $a );
        $total_b = isset( $series['total_b'] ) ? (float) $series['total_b'] : ( $dual ? array_sum( $b ) : 0.0 );
        $delta_pct = isset( $series['delta_pct'] )
            ? (float) $series['delta_pct']
            : ( $total_b > 0 ? ( ( $total_a - $total_b ) / $total_b ) * 100 : ( $total_a > 0 ? 100.0 : 0.0 ) );
        $up = $delta_pct >= 0;

        $grid = array();
        for ( $g = 0; $g <= 4; $g++ ) {
            $gy = $pad_t + ( $g / 4 ) * $plot_h;
            $gv = $max_v * ( 1 - $g / 4 );
            $grid[] = array( 'y' => $gy, 'v' => $gv );
        }
        ?>
        <div class="wap-gsc-card<?php echo $dual ? ' is-dual' : ''; ?>" data-wap-capture-part="chart">
            <div class="wap-gsc-head">
                <h3 class="wap-gsc-title"><?php echo esc_html( $title ); ?></h3>
                <div class="wap-gsc-legend">
                    <span class="wap-gsc-leg wap-gsc-leg-a"><i></i><?php echo esc_html( $leg_a ); ?></span>
                    <?php if ( $dual ) : ?>
                        <span class="wap-gsc-leg wap-gsc-leg-b"><i></i><?php echo esc_html( $leg_b ); ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <?php if ( $show_met ) : ?>
            <div class="wap-gsc-metrics">
                <div class="wap-gsc-metric">
                    <span class="wap-gsc-metric__label"><?php echo esc_html( $leg_a ); ?></span>
                    <strong class="wap-gsc-metric__value is-a"><?php echo esc_html( number_format( $total_a ) ); ?></strong>
                </div>
                <?php if ( $dual ) : ?>
                <div class="wap-gsc-metric">
                    <span class="wap-gsc-metric__label"><?php echo esc_html( $leg_b ); ?></span>
                    <strong class="wap-gsc-metric__value is-b"><?php echo esc_html( number_format( $total_b ) ); ?></strong>
                </div>
                <div class="wap-gsc-metric">
                    <span class="wap-gsc-metric__label">تغییر</span>
                    <strong class="wap-gsc-metric__value <?php echo $up ? 'is-up' : 'is-down'; ?>"><?php echo esc_html( ( $up ? '+' : '' ) . number_format( $delta_pct, 1 ) . '%' ); ?></strong>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            <div class="wap-gsc-chart-wrap">
                <svg class="wap-gsc-svg" viewBox="0 0 <?php echo (int) $width; ?> <?php echo (int) $height; ?>" role="img" aria-label="<?php echo esc_attr( $title ); ?>" preserveAspectRatio="xMidYMid meet">
                    <defs>
                        <linearGradient id="<?php echo esc_attr( $uid ); ?>-fill-a" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stop-color="#1a73e8" stop-opacity="0.32"/>
                            <stop offset="100%" stop-color="#1a73e8" stop-opacity="0.02"/>
                        </linearGradient>
                        <linearGradient id="<?php echo esc_attr( $uid ); ?>-fill-b" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stop-color="#fa7b17" stop-opacity="0.22"/>
                            <stop offset="100%" stop-color="#fa7b17" stop-opacity="0.02"/>
                        </linearGradient>
                    </defs>
                    <?php foreach ( $grid as $g ) : ?>
                        <line class="wap-gsc-grid" x1="<?php echo $pad_l; ?>" x2="<?php echo $width - $pad_r; ?>" y1="<?php echo esc_attr( number_format( $g['y'], 2, '.', '' ) ); ?>" y2="<?php echo esc_attr( number_format( $g['y'], 2, '.', '' ) ); ?>"/>
                        <text class="wap-gsc-axis" x="<?php echo $pad_l - 8; ?>" y="<?php echo esc_attr( number_format( $g['y'] + 4, 2, '.', '' ) ); ?>" text-anchor="end"><?php echo esc_html( self::short_num( $g['v'] ) ); ?></text>
                    <?php endforeach; ?>
                    <line class="wap-gsc-axis-line" x1="<?php echo $pad_l; ?>" x2="<?php echo $width - $pad_r; ?>" y1="<?php echo $pad_t + $plot_h; ?>" y2="<?php echo $pad_t + $plot_h; ?>"/>
                    <path class="wap-gsc-area wap-gsc-area-a" d="<?php echo esc_attr( $area_a ); ?>" fill="url(#<?php echo esc_attr( $uid ); ?>-fill-a)"/>
                    <?php if ( $dual ) : ?>
                        <path class="wap-gsc-area wap-gsc-area-b" d="<?php echo esc_attr( $area_b ); ?>" fill="url(#<?php echo esc_attr( $uid ); ?>-fill-b)"/>
                    <?php endif; ?>
                    <path class="wap-gsc-line wap-gsc-line-a" d="<?php echo esc_attr( $line_a ); ?>" fill="none"/>
                    <?php if ( $dual ) : ?>
                        <path class="wap-gsc-line wap-gsc-line-b" d="<?php echo esc_attr( $line_b ); ?>" fill="none"/>
                    <?php endif; ?>
                    <?php foreach ( $pts_a as $i => $p ) : ?>
                        <circle class="wap-gsc-dot wap-gsc-dot-a" cx="<?php echo esc_attr( number_format( $p[0], 2, '.', '' ) ); ?>" cy="<?php echo esc_attr( number_format( $p[1], 2, '.', '' ) ); ?>" r="<?php echo $n <= 14 ? '4.5' : '3.2'; ?>">
                            <title><?php
                            $tip = ( $labels[ $i ] ?? '' ) . ' — فعلی: ' . number_format( $p[2] );
                            if ( $dual ) {
                                $tip .= ' | مقایسه: ' . number_format( $pts_b[ $i ][2] ?? 0 );
                            }
                            echo esc_html( $tip );
                            ?></title>
                        </circle>
                    <?php endforeach; ?>
                    <?php if ( $dual ) : foreach ( $pts_b as $p ) : ?>
                        <circle class="wap-gsc-dot wap-gsc-dot-b" cx="<?php echo esc_attr( number_format( $p[0], 2, '.', '' ) ); ?>" cy="<?php echo esc_attr( number_format( $p[1], 2, '.', '' ) ); ?>" r="<?php echo $n <= 14 ? '4.5' : '3.2'; ?>">
                            <title><?php echo esc_html( $p[3] . ': ' . number_format( $p[2] ) ); ?></title>
                        </circle>
                    <?php endforeach; endif; ?>
                    <?php
                    $step = max( 1, (int) ceil( $n / 8 ) );
                    for ( $i = 0; $i < $n; $i += $step ) :
                        $p = $pts_a[ $i ];
                        ?>
                        <text class="wap-gsc-xlabel" x="<?php echo esc_attr( number_format( $p[0], 2, '.', '' ) ); ?>" y="<?php echo $height - 14; ?>" text-anchor="middle"><?php echo esc_html( self::trim_label( $labels[ $i ] ?? '' ) ); ?></text>
                    <?php endfor; ?>
                </svg>
            </div>
            <?php if ( $dual ) : ?>
                <p class="wap-gsc-footnote">دو خط روی یک محور هم‌تراز شده‌اند (روز ۱ با روز ۱) — مثل Compare در Google Search Console. آبی = بازه فعلی، نارنجی = بازه مقایسه.</p>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * میله‌های افقی مقایسه محصول (سبک GSC Performance).
     */
    public static function render_gsc_product_compare( array $aligned, array $opts = array() ): void {
        $rows  = $aligned['rows'] ?? array();
        $leg_a = $opts['legend_a'] ?? 'بازه فعلی';
        $leg_b = $opts['legend_b'] ?? 'بازه مقایسه';
        $title = $opts['title'] ?? 'مقایسه فروش محصولات';
        if ( empty( $rows ) ) {
            echo '<div class="wap-empty">محصولی برای مقایسه نیست.</div>';
            return;
        }
        $max = 1.0;
        foreach ( $rows as $r ) {
            $max = max( $max, (float) $r['revenue_a'], (float) $r['revenue_b'] );
        }
        ?>
        <div class="wap-gsc-card" data-wap-capture-part="chart">
            <div class="wap-gsc-head">
                <h3 class="wap-gsc-title"><?php echo esc_html( $title ); ?></h3>
                <div class="wap-gsc-legend">
                    <span class="wap-gsc-leg wap-gsc-leg-a"><i></i><?php echo esc_html( $leg_a ); ?></span>
                    <span class="wap-gsc-leg wap-gsc-leg-b"><i></i><?php echo esc_html( $leg_b ); ?></span>
                </div>
            </div>
            <div class="wap-gsc-prod-list">
                <?php foreach ( $rows as $r ) :
                    $wa = $max > 0 ? ( $r['revenue_a'] / $max ) * 100 : 0;
                    $wb = $max > 0 ? ( $r['revenue_b'] / $max ) * 100 : 0;
                    $up = $r['delta'] >= 0;
                    ?>
                    <div class="wap-gsc-prod-row">
                        <div class="wap-gsc-prod-name" title="<?php echo esc_attr( $r['name'] ); ?>"><?php echo esc_html( self::trim_label( $r['name'], 42 ) ); ?></div>
                        <div class="wap-gsc-prod-bars">
                            <div class="wap-gsc-prod-track"><div class="wap-gsc-prod-fill wap-gsc-prod-a" style="width:<?php echo esc_attr( number_format( $wa, 2, '.', '' ) ); ?>%"></div></div>
                            <div class="wap-gsc-prod-track"><div class="wap-gsc-prod-fill wap-gsc-prod-b" style="width:<?php echo esc_attr( number_format( $wb, 2, '.', '' ) ); ?>%"></div></div>
                        </div>
                        <div class="wap-gsc-prod-vals">
                            <span><?php echo esc_html( number_format( $r['revenue_a'] ) ); ?></span>
                            <span class="wap-gsc-delta <?php echo $up ? 'is-up' : 'is-down'; ?>"><?php echo esc_html( ( $up ? '+' : '' ) . number_format( $r['delta_pct'], 1 ) . '%' ); ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }

    private static function polyline( array $pts ): string {
        if ( empty( $pts ) ) {
            return '';
        }
        $d = 'M';
        foreach ( $pts as $i => $p ) {
            $d .= ( $i === 0 ? '' : ' L' ) . number_format( $p[0], 2, '.', '' ) . ' ' . number_format( $p[1], 2, '.', '' );
        }
        return $d;
    }

    private static function area_path( array $pts, float $base_y ): string {
        if ( empty( $pts ) ) {
            return '';
        }
        $d = self::polyline( $pts );
        $last = $pts[ count( $pts ) - 1 ];
        $first = $pts[0];
        $d .= ' L' . number_format( $last[0], 2, '.', '' ) . ' ' . number_format( $base_y, 2, '.', '' );
        $d .= ' L' . number_format( $first[0], 2, '.', '' ) . ' ' . number_format( $base_y, 2, '.', '' );
        $d .= ' Z';
        return $d;
    }

    private static function short_num( float $v ): string {
        if ( $v >= 1000000000 ) {
            return number_format( $v / 1000000000, 1 ) . 'B';
        }
        if ( $v >= 1000000 ) {
            return number_format( $v / 1000000, 1 ) . 'M';
        }
        if ( $v >= 1000 ) {
            return number_format( $v / 1000, 1 ) . 'K';
        }
        return number_format( $v, 0 );
    }

    private static function trim_label( string $s, int $max = 14 ): string {
        if ( function_exists( 'mb_strimwidth' ) ) {
            return mb_strimwidth( $s, 0, $max, '…' );
        }
        return strlen( $s ) > $max ? substr( $s, 0, $max - 1 ) . '…' : $s;
    }
}
