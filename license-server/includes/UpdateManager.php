<?php
/**
 * مدیریت به‌روزرسانی افزونه‌ها — LS_UPDATES + اعتبارسنجی لایسنس.
 */
class UpdateManager {

    private static function slug( string $value ): string {
        $value = strtolower( trim( $value ) );
        return (string) preg_replace( '/[^a-z0-9_\-]/', '', $value );
    }

    /**
     * @param array<string,mixed> $params product, version, license_key, domain
     * @return array<string,mixed>
     */
    public static function get_update_payload( array $params ): array {
        $product     = self::slug( $params['product'] ?? '' );
        $client_ver  = trim( (string) ( $params['version'] ?? '' ) );
        $license_key = trim( (string) ( $params['license_key'] ?? '' ) );
        $domain      = self::clean_domain( (string) ( $params['domain'] ?? '' ) );

        if ( $product === '' ) {
            return array( 'success' => false, 'message' => 'پارامتر product الزامی است.' );
        }

        $updates = defined( 'LS_UPDATES' ) && is_array( LS_UPDATES ) ? LS_UPDATES : array();
        if ( ! isset( $updates[ $product ] ) || ! is_array( $updates[ $product ] ) ) {
            return array( 'success' => false, 'message' => 'به‌روزرسانی برای این محصول تعریف نشده است.' );
        }

        if ( $license_key !== '' ) {
            if ( $domain === '' ) {
                return array( 'success' => false, 'message' => 'پارامتر domain الزامی است.' );
            }
            $val = LicenseManager::validate( $license_key, $domain, $product );
            if ( empty( $val['valid'] ) ) {
                return array(
                    'success' => false,
                    'message' => self::license_error_fa( $val['error'] ?? '' ),
                );
            }
        }

        $meta       = $updates[ $product ];
        $server_ver = trim( (string) ( $meta['version'] ?? '' ) );
        if ( $server_ver === '' ) {
            return array( 'success' => false, 'message' => 'نسخهٔ سرور تنظیم نشده است.' );
        }

        $base_url = defined( 'LS_BASE_URL' ) ? rtrim( LS_BASE_URL, '/' ) : '';
        $homepage = $meta['homepage'] ?? ( $base_url ?: 'https://webakery.ir' );
        // لینک مستقیم ZIP — لایسنس همین‌جا چک شده؛ نیازی به download.php جدا نیست
        $package  = trim( (string) ( $meta['package'] ?? '' ) );
        if ( $package === '' && $base_url !== '' ) {
            $package = $base_url . '/license-server/updates/' . $product . '.zip';
        }

        $labels = defined( 'LS_PLUGIN_LABELS' ) && is_array( LS_PLUGIN_LABELS ) ? LS_PLUGIN_LABELS : array();

        return array(
            'success'          => true,
            'message'          => 'اطلاعات به‌روزرسانی',
            'product'          => $product,
            'version'          => $server_ver,
            'package'          => $package,
            'download_url'     => $package,
            'url'              => $homepage,
            'homepage'         => $homepage,
            'requires'         => $meta['requires']     ?? '5.8',
            'tested'           => $meta['tested']       ?? '6.6',
            'requires_php'     => $meta['requires_php'] ?? '7.4',
            'changelog'        => $meta['changelog']    ?? '',
            'name'             => $labels[ $product ] ?? $product,
            'client_version'   => $client_ver,
            'update_available' => $client_ver !== '' && version_compare( $server_ver, $client_ver, '>' ),
        );
    }

    public static function package_url( string $product, string $license_key, string $domain, string $fallback = '' ): string {
        $base = defined( 'LS_BASE_URL' ) ? rtrim( LS_BASE_URL, '/' ) : '';
        if ( $license_key !== '' && $domain !== '' && $base !== '' ) {
            return $base . '/license-server/updates/download.php?' . http_build_query( array(
                'product'     => $product,
                'license_key' => $license_key,
                'domain'      => $domain,
            ) );
        }
        return $fallback;
    }

    public static function zip_path( string $product ): ?string {
        $map = array(
            'wccp'             => 'baget.zip',
            'hesabdar'         => 'hesabdar.zip',
            'access-levels'    => 'access-levels.zip',
            'sokhte-jet'       => 'sokhte-jet.zip',
            'gateway-pricing'  => 'webakery-gateway-pricing.zip',
            'nobat-man'        => 'nobat-man.zip',
            'webakery-chat'    => 'webakery-chat-box.zip',
        );
        if ( ! isset( $map[ $product ] ) ) {
            return null;
        }
        $path = __DIR__ . '/../updates/' . $map[ $product ];
        return is_readable( $path ) ? $path : null;
    }

    public static function clean_domain( string $domain ): string {
        $domain = trim( $domain );
        $domain = preg_replace( '#^https?://#i', '', $domain );
        $domain = explode( '/', $domain )[0];
        $domain = explode( ':', $domain )[0];
        $domain = preg_replace( '/^www\./i', '', $domain );
        return strtolower( $domain );
    }

    private static function license_error_fa( string $code ): string {
        $map = array(
            'license_not_found'    => 'کلید لایسنس یافت نشد.',
            'license_revoked'      => 'لایسنس غیرفعال شده است.',
            'license_expired'      => 'اعتبار لایسنس منقضی شده است.',
            'product_mismatch'     => 'این لایسنس برای این افزونه نیست.',
            'domain_not_activated' => 'لایسنس روی این دامنه فعال نشده است.',
        );
        return $map[ $code ] ?? 'لایسنس نامعتبر است.';
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    public static function all_products_summary(): array {
        $updates = defined( 'LS_UPDATES' ) && is_array( LS_UPDATES ) ? LS_UPDATES : array();
        $labels  = defined( 'LS_PLUGIN_LABELS' ) && is_array( LS_PLUGIN_LABELS ) ? LS_PLUGIN_LABELS : array();
        $out     = array();
        foreach ( $updates as $slug => $meta ) {
            $zip = self::zip_path( $slug );
            $out[ $slug ] = array(
                'name'    => $labels[ $slug ] ?? $slug,
                'version' => $meta['version'] ?? '—',
                'package' => $meta['package'] ?? '',
                'zip_ok'  => $zip !== null,
                'zip_file'=> $zip ? basename( $zip ) : ( $slug . '.zip' ),
            );
        }
        return $out;
    }
}
