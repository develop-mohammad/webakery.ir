<?php
require_once __DIR__ . '/Database.php';

class LicenseManager {

    public static function generate_key( string $product = 'WCCP' ): string {
        $prefix = strtoupper( preg_replace('/[^A-Z0-9]/i', '', $product) );
        $prefix = substr($prefix ?: 'LIC', 0, 6);
        $chars  = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $seg    = function() use ($chars) {
            $s = '';
            for ($i=0;$i<4;$i++) $s .= $chars[random_int(0, strlen($chars)-1)];
            return $s;
        };
        return $prefix . '-' . $seg() . '-' . $seg() . '-' . $seg() . '-' . $seg();
    }

    /**
     * ایجاد لایسنس جدید.
     *
     * @param string      $email       ایمیل خریدار
     * @param string      $product     شناسه محصول (slug)
     * @param string      $note        یادداشت داخلی
     * @param string|null $expires_at  تاریخ انقضا (YYYY-MM-DD) یا null برای مادام‌العمر
     * @param string      $domain      اگر پر باشد، لایسنس همینجا روی این دامنه فعال می‌شود
     * @return array
     */
    public static function create( string $email, string $product = 'wccp', string $note = '', $expires_at = null, string $domain = '' ): array {
        $key = self::generate_key($product);
        while ( Database::license_find($key) ) { $key = self::generate_key($product); }

        $lic = [
            'id'          => uniqid(),
            'license_key' => $key,
            'email'       => $email,
            'product'     => strtolower($product),
            'note'        => $note,
            'status'      => 'active',
            'expires_at'  => $expires_at,
            'created_at'  => date('Y-m-d H:i:s'),
        ];
        Database::license_insert($lic);

        // اگر دامنه هم وارد شده، همینجا روی دامنه فعالش کن
        if ( $domain !== '' ) {
            $clean = self::clean_domain($domain);
            if ( $clean !== '' ) {
                self::activate( $key, $clean, '' );
            }
        }
        return $lic;
    }

    /**
     * تمدید اشتراک موجود یا ساخت لایسنس جدید با تاریخ انقضا.
     *
     * @param int $months تعداد ماه
     */
    public static function create_or_extend_subscription(
        string $email,
        string $product,
        int $months,
        string $domain = '',
        string $note = ''
    ): array {
        $months  = max( 1, $months );
        $product = strtolower( trim( $product ) );
        $email   = strtolower( trim( $email ) );
        $domain  = $domain !== '' ? self::clean_domain( $domain ) : '';

        $existing = Database::license_find_by_email( $email, $product );
        if ( ! $existing && $domain !== '' ) {
            $existing = Database::license_find_by_domain( $domain, $product );
        }

        if ( $existing && ( $existing['status'] ?? '' ) === 'active' ) {
            $base_ts = time();
            if ( ! empty( $existing['expires_at'] ) ) {
                $exp_ts = strtotime( $existing['expires_at'] . ' 23:59:59' );
                if ( $exp_ts && $exp_ts > $base_ts ) {
                    $base_ts = $exp_ts;
                }
            }
            $new_exp = date( 'Y-m-d', strtotime( '+' . $months . ' months', $base_ts ) );
            Database::license_update(
                $existing['license_key'],
                array(
                    'expires_at' => $new_exp,
                    'status'     => 'active',
                    'note'       => trim( ( $existing['note'] ?? '' ) . ' | تمدید ' . $months . 'ماهه ' . date( 'Y-m-d' ) ),
                )
            );
            if ( $domain !== '' ) {
                self::activate( $existing['license_key'], $domain, '' );
            }
            $fresh = Database::license_find( $existing['license_key'] );
            return is_array( $fresh ) ? $fresh : $existing;
        }

        $expires = date( 'Y-m-d', strtotime( '+' . $months . ' months' ) );
        $note    = $note !== '' ? $note : ( 'اشتراک ' . $months . ' ماهه' );
        return self::create( $email, $product, $note, $expires, $domain );
    }

    public static function find( string $key ): ?array {
        return Database::license_find($key);
    }

    public static function all( int $limit = 100, int $offset = 0 ): array {
        $all = Database::licenses_all($limit, $offset);
        foreach ($all as &$lic) {
            $lic['activation_count'] = Database::activation_count($lic['license_key']);
        }
        return $all;
    }

    public static function total(): int {
        return Database::licenses_total();
    }

    public static function revoke( string $key ): void {
        Database::license_update($key, ['status' => 'revoked']);
    }

    public static function restore( string $key ): void {
        Database::license_update($key, ['status' => 'active']);
    }

    public static function delete( string $key ): void {
        Database::activation_delete($key, '');
        Database::license_delete($key);
    }

    public static function activate( string $key, string $domain, string $ip = '' ): array {
        $lic = self::find($key);
        if ( ! $lic ) return [ 'success' => false, 'error' => 'license_not_found', 'message' => 'کلید لایسنس یافت نشد.' ];
        if ( $lic['status'] !== 'active' ) return [ 'success' => false, 'error' => 'license_revoked', 'message' => 'این لایسنس غیرفعال است.' ];
        if ( $lic['expires_at'] && strtotime($lic['expires_at']) < time() ) return [ 'success' => false, 'error' => 'license_expired', 'message' => 'اشتراک منقضی شده.' ];

        $domain = self::clean_domain($domain);

        if ( Database::activation_find($key, $domain) ) {
            return [ 'success' => true, 'message' => 'لایسنس قبلاً برای این دامنه فعال بود.', 'domain' => $domain ];
        }

        $other = Database::activation_find_other($key, $domain);
        if ( $other ) {
            return [ 'success' => false, 'error' => 'already_activated', 'message' => 'این لایسنس روی دامنه دیگری فعال است: ' . $other['domain'] ];
        }

        Database::activation_insert([
            'license_key'  => $key,
            'domain'       => $domain,
            'ip'           => $ip,
            'activated_at' => date('Y-m-d H:i:s'),
        ]);
        return [ 'success' => true, 'message' => 'لایسنس با موفقیت فعال شد.', 'domain' => $domain ];
    }

    public static function validate( string $key, string $domain, string $product = '' ): array {
        $lic = self::find($key);
        if ( ! $lic ) return [ 'valid' => false, 'error' => 'license_not_found' ];
        if ( $lic['status'] !== 'active' ) return [ 'valid' => false, 'error' => 'license_revoked' ];
        if ( $lic['expires_at'] && strtotime($lic['expires_at']) < time() ) return [ 'valid' => false, 'error' => 'license_expired' ];
        if ( $product && strtolower($product) !== strtolower($lic['product']) ) return [ 'valid' => false, 'error' => 'product_mismatch' ];

        $domain = self::clean_domain($domain);
        if ( ! Database::activation_find($key, $domain) ) return [ 'valid' => false, 'error' => 'domain_not_activated' ];

        return [ 'valid' => true, 'email' => $lic['email'], 'product' => $lic['product'], 'expires_at' => $lic['expires_at'] ];
    }

    public static function deactivate_domain( string $key, string $domain ): array {
        Database::activation_delete($key, self::clean_domain($domain));
        return [ 'success' => true, 'message' => 'لایسنس از این دامنه حذف شد.' ];
    }

    public static function activations_of( string $key ): array {
        return Database::activations_of($key);
    }

    public static function clean_domain( string $url ): string {
        $url = preg_replace('#^https?://#', '', trim($url));
        $url = explode('/', $url)[0];
        $url = explode(':', $url)[0];
        $url = preg_replace('/^www\./i', '', $url);
        return strtolower($url);
    }
}

/**
 * CouponManager — مدیریت کدهای تخفیف
 *
 * ساختار یک کوپن:
 *   id          => شناسه یکتا
 *   code        => کد قابل نمایش (مثلاً SUMMER20)
 *   type        => 'percent' | 'fixed'
 *   value       => درصد (۱-۱۰۰) یا مبلغ ثابت به ریال
 *   product     => 'all' یا slug محصول خاص
 *   max_uses    => حداکثر استفاده (۰ = نامحدود)
 *   used_count  => تعداد استفاده تا الان
 *   expires_at  => YYYY-MM-DD یا null
 *   status      => 'active' | 'disabled'
 *   note        => یادداشت
 *   created_at  => زمان ساخت
 *   min_amount  => حداقل مبلغ سفارش برای اعمال (ریال) — ۰ یعنی بدون شرط
 */
class CouponManager {

    public const TYPE_PERCENT = 'percent';
    public const TYPE_FIXED   = 'fixed';

    /** ساخت کوپن جدید با کد یکتا */
    public static function create( array $data ): array {
        $code = strtoupper( trim( $data['code'] ?? '' ) );
        if ( $code === '' ) {
            return [ 'success' => false, 'message' => 'کد تخفیف نمی‌تواند خالی باشد.' ];
        }
        if ( Database::coupon_find( $code ) ) {
            return [ 'success' => false, 'message' => 'این کد قبلاً ثبت شده.' ];
        }

        $type  = ( $data['type'] ?? self::TYPE_PERCENT ) === self::TYPE_FIXED ? self::TYPE_FIXED : self::TYPE_PERCENT;
        $value = max( 0, (int)( $data['value'] ?? 0 ) );

        if ( $type === self::TYPE_PERCENT && ( $value < 1 || $value > 100 ) ) {
            return [ 'success' => false, 'message' => 'درصد باید بین ۱ و ۱۰۰ باشد.' ];
        }
        if ( $type === self::TYPE_FIXED && $value <= 0 ) {
            return [ 'success' => false, 'message' => 'مبلغ تخفیف باید بیشتر از صفر باشد.' ];
        }

        $coupon = [
            'id'         => uniqid(),
            'code'       => $code,
            'type'       => $type,
            'value'      => $value,
            'product'    => trim( $data['product'] ?? 'all' ) ?: 'all',
            'max_uses'   => max( 0, (int)( $data['max_uses'] ?? 0 ) ),
            'used_count' => 0,
            'expires_at' => trim( $data['expires_at'] ?? '' ) ?: null,
            'status'     => ( $data['status'] ?? 'active' ) === 'disabled' ? 'disabled' : 'active',
            'note'       => trim( $data['note'] ?? '' ),
            'min_amount' => max( 0, (int)( $data['min_amount'] ?? 0 ) ),
            'created_at' => date('Y-m-d H:i:s'),
        ];
        Database::coupon_insert( $coupon );
        return [ 'success' => true, 'coupon' => $coupon, 'message' => 'کد تخفیف ساخته شد.' ];
    }

    public static function update( string $id, array $changes ): array {
        $coupon = Database::coupon_find_by_id( $id );
        if ( ! $coupon ) return [ 'success' => false, 'message' => 'کوپن یافت نشد.' ];

        // اگر کد عوض شد، یکتایی بررسی شود
        if ( isset( $changes['code'] ) ) {
            $new_code = strtoupper( trim( $changes['code'] ) );
            if ( $new_code === '' ) return [ 'success' => false, 'message' => 'کد نمی‌تواند خالی باشد.' ];
            $existing = Database::coupon_find( $new_code );
            if ( $existing && $existing['id'] !== $id ) {
                return [ 'success' => false, 'message' => 'این کد قبلاً ثبت شده.' ];
            }
            $changes['code'] = $new_code;
        }
        if ( isset( $changes['type'] ) ) {
            $changes['type'] = $changes['type'] === self::TYPE_FIXED ? self::TYPE_FIXED : self::TYPE_PERCENT;
        }
        if ( isset( $changes['value'] ) ) {
            $changes['value'] = max( 0, (int) $changes['value'] );
        }
        if ( isset( $changes['max_uses'] ) ) {
            $changes['max_uses'] = max( 0, (int) $changes['max_uses'] );
        }
        if ( isset( $changes['min_amount'] ) ) {
            $changes['min_amount'] = max( 0, (int) $changes['min_amount'] );
        }
        if ( isset( $changes['expires_at'] ) ) {
            $changes['expires_at'] = trim( $changes['expires_at'] ) ?: null;
        }
        if ( isset( $changes['status'] ) ) {
            $changes['status'] = $changes['status'] === 'disabled' ? 'disabled' : 'active';
        }

        Database::coupon_update( $id, $changes );
        return [ 'success' => true, 'message' => 'کوپن به‌روزرسانی شد.' ];
    }

    public static function delete( string $id ): array {
        Database::coupon_delete( $id );
        return [ 'success' => true, 'message' => 'کوپن حذف شد.' ];
    }

    public static function toggle( string $id ): array {
        $coupon = Database::coupon_find_by_id( $id );
        if ( ! $coupon ) return [ 'success' => false, 'message' => 'کوپن یافت نشد.' ];
        $new_status = ( $coupon['status'] ?? 'active' ) === 'active' ? 'disabled' : 'active';
        Database::coupon_update( $id, [ 'status' => $new_status ] );
        return [ 'success' => true, 'status' => $new_status, 'message' => 'وضعیت تغییر کرد.' ];
    }

    /**
     * بررسی اعتبار کوپن برای محصول و مبلغ مشخص.
     *
     * @param string $code
     * @param string $product
     * @param int    $base_amount مبلغ اصلی به ریال
     * @return array [
     *   'valid'    => bool,
     *   'message'  => string,
     *   'coupon'   => array|null,
     *   'discount' => int,   // مبلغ تخفیف به ریال
     *   'final'    => int,   // مبلغ نهایی به ریال
     * ]
     */
    public static function validate( string $code, string $product, int $base_amount ): array {
        $coupon = Database::coupon_find( $code );
        if ( ! $coupon ) {
            return [ 'valid' => false, 'message' => 'کد تخفیف یافت نشد.', 'discount' => 0, 'final' => $base_amount ];
        }
        if ( ( $coupon['status'] ?? 'active' ) !== 'active' ) {
            return [ 'valid' => false, 'message' => 'این کد غیرفعال است.', 'discount' => 0, 'final' => $base_amount, 'coupon' => $coupon ];
        }
        if ( ! empty( $coupon['expires_at'] ) && strtotime( $coupon['expires_at'] ) < time() ) {
            return [ 'valid' => false, 'message' => 'این کد منقضی شده.', 'discount' => 0, 'final' => $base_amount, 'coupon' => $coupon ];
        }
        if ( ( $coupon['product'] ?? 'all' ) !== 'all'
             && strtolower( $coupon['product'] ) !== strtolower( $product ) ) {
            return [ 'valid' => false, 'message' => 'این کد برای این محصول قابل استفاده نیست.', 'discount' => 0, 'final' => $base_amount, 'coupon' => $coupon ];
        }
        $max_uses = (int)( $coupon['max_uses'] ?? 0 );
        if ( $max_uses > 0 && (int)( $coupon['used_count'] ?? 0 ) >= $max_uses ) {
            return [ 'valid' => false, 'message' => 'سقف استفاده از این کد تکمیل شده.', 'discount' => 0, 'final' => $base_amount, 'coupon' => $coupon ];
        }
        $min_amount = (int)( $coupon['min_amount'] ?? 0 );
        if ( $min_amount > 0 && $base_amount < $min_amount ) {
            return [ 'valid' => false, 'message' => 'حداقل مبلغ سفارش برای این کد: ' . number_format( $min_amount / 10 ) . ' تومان.', 'discount' => 0, 'final' => $base_amount, 'coupon' => $coupon ];
        }

        // محاسبه تخفیف
        $discount = 0;
        if ( ( $coupon['type'] ?? self::TYPE_PERCENT ) === self::TYPE_PERCENT ) {
            $discount = (int) round( $base_amount * (int) $coupon['value'] / 100 );
        } else {
            $discount = (int) $coupon['value'];
        }
        if ( $discount > $base_amount ) $discount = $base_amount;
        $final = $base_amount - $discount;

        return [
            'valid'    => true,
            'message'  => 'کد تخفیف اعمال شد.',
            'coupon'   => $coupon,
            'discount' => $discount,
            'final'    => $final,
        ];
    }

    /** ثبت استفاده از کوپن پس از پرداخت موفق */
    public static function register_use( string $id ): void {
        Database::coupon_increment_usage( $id );
    }

    /** ساخت کد تصادفی برای پیشنهاد به ادمین */
    public static function generate_code( int $length = 8 ): string {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $s = '';
        for ( $i = 0; $i < $length; $i++ ) {
            $s .= $chars[ random_int( 0, strlen( $chars ) - 1 ) ];
        }
        return $s;
    }
}
