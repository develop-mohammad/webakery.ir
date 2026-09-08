<?php
defined( 'ABSPATH' ) || exit;

/**
 * خروجی تصویری پیشرفته گزارش‌ها:
 * آرشیو مدیا، ارسال روزانه تلگرام/ایمیل، قفل بازه، محدودیت نقش.
 */
class WAP_Report_Image {

    const OPTION   = 'wap_report_image_settings';
    const CRON     = 'wap_report_image_daily';
    const NONCE    = 'wap_report_image';

    public static function init() {
        add_action( 'wp_ajax_wap_archive_report_image', array( __CLASS__, 'ajax_archive' ) );
        add_action( 'wp_ajax_wap_compare_report_metrics', array( __CLASS__, 'ajax_compare_metrics' ) );
        add_action( self::CRON, array( __CLASS__, 'run_daily_digest' ) );
        add_action( 'admin_init', array( __CLASS__, 'maybe_schedule' ) );
    }

    public static function defaults(): array {
        return array(
            'telegram_enabled'       => 0,
            'telegram_bot_token'     => '',
            'telegram_chat_id'       => '',
            'email_enabled'          => 0,
            'email_to'               => '',
            'daily_enabled'          => 0,
            'daily_hour'             => 20,
            'date_lock_enabled'      => 0,
            'date_lock_from'         => '',
            'date_lock_to'           => '',
            'restrict_accountant'    => 1,
            'accountant_tabs'        => array( 'sales', 'orders' ),
            'accountant_download_only' => 1,
        );
    }

    public static function settings(): array {
        $s = get_option( self::OPTION, array() );
        if ( ! is_array( $s ) ) {
            $s = array();
        }
        $out = array_merge( self::defaults(), $s );
        if ( ! is_array( $out['accountant_tabs'] ) ) {
            $out['accountant_tabs'] = array( 'sales', 'orders' );
        }
        return $out;
    }

    public static function save_settings( array $raw ): void {
        $cur = self::settings();
        $next = array(
            'telegram_enabled'         => empty( $raw['telegram_enabled'] ) ? 0 : 1,
            'telegram_bot_token'       => sanitize_text_field( $raw['telegram_bot_token'] ?? '' ),
            'telegram_chat_id'         => sanitize_text_field( $raw['telegram_chat_id'] ?? '' ),
            'email_enabled'            => empty( $raw['email_enabled'] ) ? 0 : 1,
            'email_to'                 => sanitize_text_field( $raw['email_to'] ?? '' ),
            'daily_enabled'            => empty( $raw['daily_enabled'] ) ? 0 : 1,
            'daily_hour'               => max( 0, min( 23, (int) ( $raw['daily_hour'] ?? 20 ) ) ),
            'date_lock_enabled'        => empty( $raw['date_lock_enabled'] ) ? 0 : 1,
            'date_lock_from'           => sanitize_text_field( $raw['date_lock_from'] ?? '' ),
            'date_lock_to'             => sanitize_text_field( $raw['date_lock_to'] ?? '' ),
            'restrict_accountant'      => empty( $raw['restrict_accountant'] ) ? 0 : 1,
            'accountant_download_only' => empty( $raw['accountant_download_only'] ) ? 0 : 1,
            'accountant_tabs'          => array_values( array_intersect(
                array( 'sales', 'orders', 'products', 'shaparak', 'analytics' ),
                array_map( 'sanitize_key', (array) ( $raw['accountant_tabs'] ?? array() ) )
            ) ),
        );
        if ( $next['telegram_bot_token'] === '' ) {
            $next['telegram_bot_token'] = $cur['telegram_bot_token'];
        }
        if ( empty( $next['accountant_tabs'] ) ) {
            $next['accountant_tabs'] = array( 'sales', 'orders' );
        }
        update_option( self::OPTION, $next, false );
        self::reschedule();
    }

    public static function maybe_schedule() {
        if ( ! self::settings()['daily_enabled'] ) {
            return;
        }
        if ( ! wp_next_scheduled( self::CRON ) ) {
            self::reschedule();
        }
    }

    public static function reschedule() {
        $ts = wp_next_scheduled( self::CRON );
        if ( $ts ) {
            wp_unschedule_event( $ts, self::CRON );
        }
        $s = self::settings();
        if ( empty( $s['daily_enabled'] ) ) {
            return;
        }
        $hour = (int) $s['daily_hour'];
        $when = strtotime( 'today ' . sprintf( '%02d:00:00', $hour ) );
        if ( $when <= time() ) {
            $when = strtotime( 'tomorrow ' . sprintf( '%02d:00:00', $hour ) );
        }
        wp_schedule_event( $when, 'daily', self::CRON );
    }

    public static function is_date_locked(): bool {
        $s = self::settings();
        return ! empty( $s['date_lock_enabled'] ) && $s['date_lock_from'] !== '' && $s['date_lock_to'] !== '';
    }

    public static function locked_range(): array {
        $s = self::settings();
        return array(
            'date_from' => (string) $s['date_lock_from'],
            'date_to'   => (string) $s['date_lock_to'],
        );
    }

    /** آیا کاربر فعلی فقط حسابدار (بدون دسترسی مدیر) است؟ */
    public static function is_accountant_only( $user = null ): bool {
        $user = $user ?: wp_get_current_user();
        if ( ! $user || ! $user->ID ) {
            return false;
        }
        if ( user_can( $user, 'manage_options' ) || WAP_Portal::user_has_manager_access( $user ) ) {
            return false;
        }
        return in_array( WAP_Portal::ROLE, (array) $user->roles, true );
    }

    public static function allowed_tabs_for_user( $user = null ): array {
        $all = array( 'sales', 'orders', 'products', 'shaparak', 'analytics' );
        $s   = self::settings();
        if ( empty( $s['restrict_accountant'] ) || ! self::is_accountant_only( $user ) ) {
            return $all;
        }
        $tabs = array_values( array_intersect( $all, (array) $s['accountant_tabs'] ) );
        return $tabs ?: array( 'sales', 'orders' );
    }

    public static function can_view_tab( string $view, $user = null ): bool {
        return in_array( $view, self::allowed_tabs_for_user( $user ), true );
    }

    public static function can_archive( $user = null ): bool {
        $s = self::settings();
        if ( self::is_accountant_only( $user ) && ! empty( $s['accountant_download_only'] ) ) {
            return false;
        }
        return current_user_can( 'upload_files' ) || WAP_Portal::user_has_access( $user );
    }

    public static function label_fa( string $label ): string {
        $map = array(
            'sales'     => 'گزارش-مالی',
            'orders'    => 'سفارش‌ها',
            'products'  => 'محصولات',
            'shaparak'  => 'شاپرک',
            'analytics' => 'داشبورد',
            'preview'   => 'پیش‌نمایش',
            'report'    => 'گزارش',
        );
        return $map[ $label ] ?? $label;
    }

    public static function client_config(): array {
        $f = class_exists( 'WAP_Data' ) ? WAP_Data::get_filters() : array( 'date_from' => '', 'date_to' => '' );
        $s = self::settings();
        $view = class_exists( 'WAP_Portal' ) ? WAP_Portal::current_view_public() : 'sales';
        return array(
            'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
            'nonce'       => wp_create_nonce( self::NONCE ),
            'canArchive'  => self::can_archive(),
            'dateFrom'    => $f['date_from'] ?? '',
            'dateTo'      => $f['date_to'] ?? '',
            'dateLocked'  => self::is_date_locked(),
            'view'        => $view,
            'labelFa'     => self::label_fa( $view ),
            'multipageMax'=> 3200,
            'compareFrom' => sanitize_text_field( wp_unslash( $_GET['compare_from'] ?? '' ) ),
            'compareTo'   => sanitize_text_field( wp_unslash( $_GET['compare_to'] ?? '' ) ),
            'restrictNote'=> self::is_accountant_only() && ! empty( $s['accountant_download_only'] )
                ? 'حسابدار فقط مجاز به دانلود تصویر است (آرشیو و ارسال خودکار برای مدیر).'
                : '',
        );
    }

    public static function ajax_archive() {
        if ( ! check_ajax_referer( self::NONCE, 'nonce', false ) ) {
            wp_send_json_error( array( 'message' => 'نشست نامعتبر است.' ), 403 );
        }
        if ( ! is_user_logged_in() || ! WAP_Portal::user_has_access() ) {
            wp_send_json_error( array( 'message' => 'دسترسی ندارید.' ), 403 );
        }
        if ( ! self::can_archive() ) {
            wp_send_json_error( array( 'message' => 'آرشیو تصویر برای نقش شما مجاز نیست.' ), 403 );
        }
        $data_url = isset( $_POST['data_url'] ) ? (string) wp_unslash( $_POST['data_url'] ) : '';
        $label    = sanitize_key( $_POST['label'] ?? 'report' );
        $note     = sanitize_textarea_field( wp_unslash( $_POST['note'] ?? '' ) );
        if ( ! preg_match( '#^data:image/(png|jpeg);base64,#', $data_url, $m ) ) {
            wp_send_json_error( array( 'message' => 'داده تصویر نامعتبر است.' ) );
        }
        $ext  = $m[1] === 'jpeg' ? 'jpg' : 'png';
        $b64  = substr( $data_url, strpos( $data_url, ',' ) + 1 );
        $bin  = base64_decode( $b64 );
        if ( ! $bin || strlen( $bin ) < 100 ) {
            wp_send_json_error( array( 'message' => 'تصویر خالی است.' ) );
        }
        if ( ! function_exists( 'wp_upload_bits' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
            require_once ABSPATH . 'wp-admin/includes/image.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
        }
        $name = 'hesabdar-' . self::label_fa( $label ) . '-' . gmdate( 'Ymd-His' ) . '.' . $ext;
        $upload = wp_upload_bits( $name, null, $bin );
        if ( ! empty( $upload['error'] ) ) {
            wp_send_json_error( array( 'message' => $upload['error'] ) );
        }
        $filetype = wp_check_filetype( $upload['file'] );
        $attach_id = wp_insert_attachment( array(
            'post_mime_type' => $filetype['type'] ?: ( 'image/' . ( $ext === 'jpg' ? 'jpeg' : 'png' ) ),
            'post_title'     => 'گزارش حسابدار — ' . self::label_fa( $label ),
            'post_content'   => $note,
            'post_status'    => 'inherit',
        ), $upload['file'] );
        if ( is_wp_error( $attach_id ) || ! $attach_id ) {
            wp_send_json_error( array( 'message' => 'ثبت در کتابخانه رسانه ناموفق بود.' ) );
        }
        $meta = wp_generate_attachment_metadata( $attach_id, $upload['file'] );
        wp_update_attachment_metadata( $attach_id, $meta );
        update_post_meta( $attach_id, '_wap_report_image', 1 );
        update_post_meta( $attach_id, '_wap_report_label', $label );
        update_post_meta( $attach_id, '_wap_report_note', $note );
        $ids = get_option( 'wap_report_image_archive_ids', array() );
        if ( ! is_array( $ids ) ) {
            $ids = array();
        }
        array_unshift( $ids, (int) $attach_id );
        $ids = array_slice( array_values( array_unique( array_map( 'intval', $ids ) ) ), 0, 50 );
        update_option( 'wap_report_image_archive_ids', $ids, false );

        wp_send_json_success( array(
            'id'  => (int) $attach_id,
            'url' => wp_get_attachment_url( $attach_id ),
            'message' => 'در کتابخانه رسانه ذخیره شد.',
        ) );
    }

    public static function ajax_compare_metrics() {
        if ( ! check_ajax_referer( self::NONCE, 'nonce', false ) ) {
            wp_send_json_error( array( 'message' => 'نشست نامعتبر' ), 403 );
        }
        if ( ! is_user_logged_in() || ! WAP_Portal::user_has_access() ) {
            wp_send_json_error( array( 'message' => 'دسترسی ندارید.' ), 403 );
        }
        $from = sanitize_text_field( wp_unslash( $_POST['compare_from'] ?? '' ) );
        $to   = sanitize_text_field( wp_unslash( $_POST['compare_to'] ?? '' ) );
        if ( $from === '' || $to === '' ) {
            wp_send_json_error( array( 'message' => 'بازه مقایسه را کامل کنید.' ) );
        }
        $f = array(
            'date_from'    => $from,
            'date_to'      => $to,
            'period'       => 'month',
            'order_status' => '',
            'min_total'    => null,
            'max_total'    => null,
            'min_count'    => null,
            'max_count'    => null,
        );
        $orders = WAP_Data::get_orders( $f );
        $gn     = WAP_Data::gross_vs_net( $orders );
        wp_send_json_success( array(
            'date_from'   => $from,
            'date_to'     => $to,
            'gross_total' => $gn['gross_total'],
            'gross_count' => $gn['gross_count'],
            'net_total'   => $gn['net_total'],
            'net_count'   => $gn['net_count'],
        ) );
    }

    /** ساخت تصویر ساده روزانه با GD */
    public static function build_digest_png(): ?string {
        if ( ! function_exists( 'imagecreatetruecolor' ) ) {
            return null;
        }
        $today = class_exists( 'WAP_Jalali' ) ? WAP_Jalali::today() : null;
        $from  = $today ? sprintf( '%d/%02d/%02d', $today['y'], $today['m'], $today['d'] ) : '';
        $to    = $from;
        // گزارش امروز + خلاصه ماه جاری
        $month_from = $today ? sprintf( '%d/%02d/01', $today['y'], $today['m'] ) : '';
        $f = array(
            'date_from' => $month_from ?: $from,
            'date_to'   => $to,
            'period'    => 'day',
            'order_status' => '',
            'min_total' => null, 'max_total' => null, 'min_count' => null, 'max_count' => null,
        );
        $orders = class_exists( 'WAP_Data' ) ? WAP_Data::get_orders( $f ) : array();
        $gn     = class_exists( 'WAP_Data' ) ? WAP_Data::gross_vs_net( $orders ) : array( 'gross_total' => 0, 'net_total' => 0, 'gross_count' => 0, 'net_count' => 0 );

        $w = 900;
        $h = 520;
        $im = imagecreatetruecolor( $w, $h );
        $bg = imagecolorallocate( $im, 248, 250, 252 );
        $card = imagecolorallocate( $im, 255, 255, 255 );
        $green = imagecolorallocate( $im, 5, 150, 105 );
        $text = imagecolorallocate( $im, 30, 41, 59 );
        $muted = imagecolorallocate( $im, 100, 116, 139 );
        imagefilledrectangle( $im, 0, 0, $w, $h, $bg );
        imagefilledrectangle( $im, 30, 30, $w - 30, $h - 30, $card );

        $lines = array(
            'Hesabdar daily digest',
            'Range: ' . $f['date_from'] . ' - ' . $f['date_to'],
            'Gross: ' . number_format( (float) $gn['gross_total'] ) . '  (' . (int) $gn['gross_count'] . ' orders)',
            'Net: ' . number_format( (float) $gn['net_total'] ) . '  (' . (int) $gn['net_count'] . ' paid)',
            'Generated: ' . gmdate( 'Y-m-d H:i' ) . ' UTC',
        );
        $y = 70;
        foreach ( $lines as $i => $line ) {
            $color = $i === 0 ? $green : ( $i === 1 ? $muted : $text );
            imagestring( $im, 5, 60, $y, $line, $color );
            $y += 48;
        }
        // نوار تزئینی
        imagefilledrectangle( $im, 30, 30, 38, $h - 30, $green );

        $tmp = wp_tempnam( 'wap-digest.png' );
        if ( ! $tmp ) {
            imagedestroy( $im );
            return null;
        }
        imagepng( $im, $tmp );
        imagedestroy( $im );
        return $tmp;
    }

    public static function send_telegram_photo( string $file_path, string $caption ): bool {
        $s = self::settings();
        $token = trim( (string) $s['telegram_bot_token'] );
        $chat  = trim( (string) $s['telegram_chat_id'] );
        if ( $token === '' || $chat === '' || ! is_readable( $file_path ) ) {
            return false;
        }
        $url = 'https://api.telegram.org/bot' . $token . '/sendPhoto';
        if ( function_exists( 'curl_init' ) ) {
            $ch = curl_init( $url );
            $cfile = class_exists( 'CURLFile' ) ? new CURLFile( $file_path, 'image/png', 'report.png' ) : '@' . $file_path;
            curl_setopt_array( $ch, array(
                CURLOPT_POST           => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 45,
                CURLOPT_POSTFIELDS     => array(
                    'chat_id' => $chat,
                    'caption' => mb_substr( $caption, 0, 900 ),
                    'photo'   => $cfile,
                ),
            ) );
            $res = curl_exec( $ch );
            $code = (int) curl_getinfo( $ch, CURLINFO_HTTP_CODE );
            curl_close( $ch );
            return $code >= 200 && $code < 300 && is_string( $res );
        }
        return false;
    }

    public static function send_email_with_image( string $file_path, string $subject, string $body ): bool {
        $s = self::settings();
        $to = trim( (string) $s['email_to'] );
        if ( $to === '' ) {
            $to = get_option( 'admin_email' );
        }
        if ( $to === '' || ! is_readable( $file_path ) ) {
            return false;
        }
        $headers = array( 'Content-Type: text/html; charset=UTF-8' );
        return (bool) wp_mail( $to, $subject, nl2br( esc_html( $body ) ), $headers, array( $file_path ) );
    }

    public static function run_daily_digest() {
        $s = self::settings();
        if ( empty( $s['daily_enabled'] ) ) {
            return;
        }
        $png = self::build_digest_png();
        $caption = "گزارش روزانه حسابدار\nبازه ماه جاری تا امروز";
        $ok_tg = false;
        $ok_mail = false;
        if ( $png && ! empty( $s['telegram_enabled'] ) ) {
            $ok_tg = self::send_telegram_photo( $png, $caption );
        }
        if ( $png && ! empty( $s['email_enabled'] ) ) {
            $ok_mail = self::send_email_with_image( $png, 'گزارش روزانه حسابدار', $caption );
        }
        // اگر تصویر کلاینتی آرشیو شده باشد، آخرین را هم بفرست
        $ids = get_option( 'wap_report_image_archive_ids', array() );
        if ( is_array( $ids ) && ! empty( $ids[0] ) ) {
            $path = get_attached_file( (int) $ids[0] );
            if ( $path && is_readable( $path ) && ! empty( $s['telegram_enabled'] ) ) {
                self::send_telegram_photo( $path, "آخرین گزارش تصویری آرشیو شده\n" . $caption );
            }
        }
        if ( $png && is_file( $png ) ) {
            @unlink( $png );
        }
        update_option( 'wap_report_image_last_digest', array(
            'at'    => time(),
            'tg'    => $ok_tg,
            'email' => $ok_mail,
        ), false );
    }
}
