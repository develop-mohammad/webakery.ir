<?php
/**
 * License Server — ذخیره‌سازی بر پایه فایل JSON (بدون دیتابیس)
 */
class Database {

    private static $data = null;
    private static $file = null;

    private static function file(): string {
        if ( self::$file ) return self::$file;
        $dir = __DIR__ . '/../data/';
        if ( ! is_dir($dir) ) mkdir($dir, 0755, true);
        self::$file = $dir . 'licenses.json';
        return self::$file;
    }

    private static function load(): array {
        if ( self::$data !== null ) return self::$data;
        $f = self::file();
        if ( ! file_exists($f) ) {
            self::$data = [ 'licenses' => [], 'activations' => [], 'payments' => [], 'coupons' => [] ];
        } else {
            self::$data = json_decode( file_get_contents($f), true ) ?: [ 'licenses' => [], 'activations' => [], 'payments' => [], 'coupons' => [] ];
            // اطمینان از وجود کلید coupons در فایل‌های قدیمی
            if ( ! isset( self::$data['coupons'] ) || ! is_array( self::$data['coupons'] ) ) {
                self::$data['coupons'] = [];
            }
        }
        return self::$data;
    }

    private static function save(): void {
        file_put_contents( self::file(), json_encode(self::$data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX );
    }

    /* ─── licenses ──────────────────────────────────────────────── */

    public static function license_find( string $key ): ?array {
        foreach ( self::load()['licenses'] as $lic ) {
            if ( $lic['license_key'] === $key ) return $lic;
        }
        return null;
    }

    public static function license_insert( array $lic ): void {
        self::load();
        self::$data['licenses'][] = $lic;
        self::save();
    }

    public static function license_update( string $key, array $changes ): void {
        self::load();
        foreach ( self::$data['licenses'] as &$lic ) {
            if ( $lic['license_key'] === $key ) {
                $lic = array_merge($lic, $changes);
                break;
            }
        }
        self::save();
    }

    public static function license_delete( string $key ): void {
        self::load();
        self::$data['licenses'] = array_values(array_filter(
            self::$data['licenses'], fn($l) => $l['license_key'] !== $key
        ));
        self::save();
    }

    public static function licenses_all( int $limit = 100, int $offset = 0 ): array {
        $all = array_reverse( self::load()['licenses'] );
        return array_slice($all, $offset, $limit);
    }

    public static function licenses_total(): int {
        return count( self::load()['licenses'] );
    }

    public static function license_find_by_email( string $email, string $product ): ?array {
        $email   = strtolower(trim($email));
        $product = strtolower(trim($product));
        foreach ( self::load()['licenses'] as $lic ) {
            if ( strtolower($lic['email']) === $email
                 && strtolower($lic['product']) === $product
                 && $lic['status'] === 'active' ) {
                return $lic;
            }
        }
        return null;
    }

    /** آیا حداقل یک لایسنس (هر وضعیتی) برای این ایمیل وجود دارد؟ */
    public static function license_exists_for_email( string $email ): bool {
        $email = strtolower(trim($email));
        foreach ( self::load()['licenses'] as $lic ) {
            if ( strtolower($lic['email']) === $email ) return true;
        }
        return false;
    }

    /** همه لایسنس‌های یک ایمیل (هر وضعیتی) */
    public static function licenses_by_email( string $email ): array {
        $email = strtolower(trim($email));
        $out = [];
        foreach ( self::load()['licenses'] as $lic ) {
            if ( strtolower($lic['email']) === $email ) $out[] = $lic;
        }
        return $out;
    }

    /** آیا حداقل یک پرداخت موفق برای این ایمیل وجود دارد؟ */
    public static function payment_paid_exists_for_email( string $email ): bool {
        $email = strtolower(trim($email));
        foreach ( self::load()['payments'] as $p ) {
            if ( strtolower($p['email'] ?? '') === $email && ( $p['status'] ?? '' ) === 'paid' ) {
                return true;
            }
        }
        return false;
    }

    public static function license_find_by_domain( string $domain, string $product ): ?array {
        $domain  = strtolower(trim($domain));
        $product = strtolower(trim($product));
        foreach ( self::load()['activations'] as $act ) {
            if ( $act['domain'] === $domain ) {
                $lic = self::license_find( $act['license_key'] );
                if ( $lic && strtolower($lic['product']) === $product && $lic['status'] === 'active' ) {
                    return $lic;
                }
            }
        }
        return null;
    }

    /* ─── activations ───────────────────────────────────────────── */

    public static function activation_find( string $key, string $domain ): ?array {
        foreach ( self::load()['activations'] as $a ) {
            if ( $a['license_key'] === $key && $a['domain'] === $domain ) return $a;
        }
        return null;
    }

    public static function activation_find_other( string $key, string $domain ): ?array {
        foreach ( self::load()['activations'] as $a ) {
            if ( $a['license_key'] === $key && $a['domain'] !== $domain ) return $a;
        }
        return null;
    }

    public static function activation_insert( array $act ): void {
        self::load();
        self::$data['activations'][] = $act;
        self::save();
    }

    public static function activation_delete( string $key, string $domain ): void {
        self::load();
        self::$data['activations'] = array_values(array_filter(
            self::$data['activations'],
            fn($a) => !( $a['license_key'] === $key && $a['domain'] === $domain )
        ));
        self::save();
    }

    public static function activations_of( string $key ): array {
        return array_values(array_filter(
            self::load()['activations'], fn($a) => $a['license_key'] === $key
        ));
    }

    public static function activation_count( string $key ): int {
        return count( self::activations_of($key) );
    }

    public static function all_data(): array {
        return self::load();
    }

    /* ─── payments ──────────────────────────────────────────────── */

    public static function payment_find( string $track_id ): ?array {
        foreach ( self::load()['payments'] as $p ) {
            if ( $p['track_id'] === $track_id ) return $p;
        }
        return null;
    }

    public static function payment_insert( array $pay ): void {
        self::load();
        self::$data['payments'][] = $pay;
        self::save();
    }

    public static function payment_update( string $track_id, array $changes ): void {
        self::load();
        foreach ( self::$data['payments'] as &$p ) {
            if ( $p['track_id'] === $track_id ) {
                $p = array_merge($p, $changes);
                break;
            }
        }
        self::save();
    }

    /* ─── coupons (کد تخفیف) ────────────────────────────────────── */

    /** یافتن کوپن بر اساس کد (case-insensitive) */
    public static function coupon_find( string $code ): ?array {
        $code = strtoupper( trim( $code ) );
        if ( $code === '' ) return null;
        foreach ( self::load()['coupons'] as $c ) {
            if ( strtoupper( $c['code'] ?? '' ) === $code ) return $c;
        }
        return null;
    }

    /** یافتن کوپن بر اساس id */
    public static function coupon_find_by_id( string $id ): ?array {
        foreach ( self::load()['coupons'] as $c ) {
            if ( ( $c['id'] ?? '' ) === $id ) return $c;
        }
        return null;
    }

    public static function coupon_insert( array $coupon ): void {
        self::load();
        self::$data['coupons'][] = $coupon;
        self::save();
    }

    public static function coupon_update( string $id, array $changes ): void {
        self::load();
        foreach ( self::$data['coupons'] as &$c ) {
            if ( ( $c['id'] ?? '' ) === $id ) {
                $c = array_merge($c, $changes);
                break;
            }
        }
        self::save();
    }

    public static function coupon_delete( string $id ): void {
        self::load();
        self::$data['coupons'] = array_values(array_filter(
            self::$data['coupons'], fn($c) => ( $c['id'] ?? '' ) !== $id
        ));
        self::save();
    }

    public static function coupon_all(): array {
        return array_reverse( self::load()['coupons'] );
    }

    public static function coupon_count(): int {
        return count( self::load()['coupons'] );
    }

    /** افزایش شمارنده‌ی استفاده از کوپن */
    public static function coupon_increment_usage( string $id ): void {
        self::load();
        foreach ( self::$data['coupons'] as &$c ) {
            if ( ( $c['id'] ?? '' ) === $id ) {
                $c['used_count'] = (int)( $c['used_count'] ?? 0 ) + 1;
                break;
            }
        }
        self::save();
    }
}
