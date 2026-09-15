<?php
/**
 * صفحه پرداخت مستقیم زیبال — license-server
 * PHP 7.4+
 *
 * پشتیبانی از کد تخفیف (CouponManager) به همراه نمایش قیمت قبل/بعد تخفیف.
 */

/* اگر صفحه سفید/۵۰۰ بود، موقتاً خطا را نشان بده (بعداً این ۳ خط را حذف کنید) */
if ( isset( $_GET['debug'] ) || isset( $_GET['diag'] ) ) {
	ini_set( 'display_errors', '1' );
	ini_set( 'display_startup_errors', '1' );
	error_reporting( E_ALL );
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/LicenseManager.php';
require_once __DIR__ . '/../includes/Mailer.php';

$MERCHANT = defined('ZIBAL_MERCHANT') ? ZIBAL_MERCHANT : 'zibal';

/** تخفیفِ زمان‌دارِ فعال برای این محصول، اگر هنوز منقضی نشده باشد */
function ls_active_promo_for( string $slug ): ?array {
    $promos = defined('LS_PROMOS') && is_array(LS_PROMOS) ? LS_PROMOS : [];
    if ( ! isset($promos[$slug]) ) return null;
    $promo = $promos[$slug];
    if ( ! isset($promo['price'], $promo['until']) ) return null;
    if ( time() >= (int) $promo['until'] ) return null; // تخفیف تمام شده — خودکار به قیمت اصلی برمی‌گردد
    return $promo;
}

/** قیمتی که همین الان باید گرفته شود — تخفیفِ فعال، وگرنه قیمتِ همیشگی از LS_PRICES */
function ls_price_for( string $slug ): int {
    $promo = ls_active_promo_for( $slug );
    if ( $promo ) return (int) $promo['price'];

    $prices = defined('LS_PRICES') && is_array(LS_PRICES) ? LS_PRICES : [];
    if ( isset($prices[$slug]) ) return (int) $prices[$slug];
    return defined('LS_AMOUNT') ? (int) LS_AMOUNT : 999990;
}

/** قیمتِ اصلی (بعد از پایانِ تخفیف) برای نمایش خط‌خورده — فقط وقتی تخفیف هنوز فعال است */
function ls_original_price_for( string $slug ): ?int {
    if ( ! ls_active_promo_for( $slug ) ) return null;
    $prices = defined('LS_PRICES') && is_array(LS_PRICES) ? LS_PRICES : [];
    return isset($prices[$slug]) ? (int) $prices[$slug] : null;
}

/** چند ساعت تا پایانِ تخفیف مانده — برای نمایش شمارش معکوس */
function ls_promo_hours_left( string $slug ): ?int {
    $promo = ls_active_promo_for( $slug );
    if ( ! $promo ) return null;
    return max( 1, (int) ceil( ( (int) $promo['until'] - time() ) / 3600 ) );
}

/** پلن‌های اشتراکی یک محصول (آرایه خالی = مادام‌العمر) */
function ls_plans_for( string $slug ): array {
    $all = defined( 'LS_PLANS' ) && is_array( LS_PLANS ) ? LS_PLANS : [];
    $plans = $all[ $slug ] ?? [];
    return is_array( $plans ) ? $plans : [];
}

function ls_has_plans( string $slug ): bool {
    return ! empty( ls_plans_for( $slug ) );
}

/** @return array{id:string,months:int,price:int,label:string,hint:string,badge:string}|null */
function ls_plan( string $slug, string $plan_id ): ?array {
    $plans = ls_plans_for( $slug );
    if ( ! isset( $plans[ $plan_id ] ) || ! is_array( $plans[ $plan_id ] ) ) {
        return null;
    }
    $p = $plans[ $plan_id ];
    return [
        'id'     => $plan_id,
        'months' => max( 0, (int) ( $p['months'] ?? 1 ) ), // 0 = دائمی
        'price'  => (int) ( $p['price'] ?? 0 ),
        'label'  => (string) ( $p['label'] ?? $plan_id ),
        'hint'   => (string) ( $p['hint'] ?? '' ),
        'badge'  => (string) ( $p['badge'] ?? '' ),
    ];
}

function ls_default_plan_id( string $slug ): string {
    $plans = ls_plans_for( $slug );
    if ( ! $plans ) {
        return '';
    }
    foreach ( $plans as $id => $p ) {
        if ( ! empty( $p['badge'] ) ) {
            return (string) $id;
        }
    }
    reset( $plans );
    return (string) key( $plans );
}

/** قیمت نهایی با احتساب پلن (و پرومو فقط برای محصولات بدون پلن) */
function ls_amount_for( string $slug, string $plan_id = '' ): int {
    if ( ls_has_plans( $slug ) ) {
        if ( $plan_id === '' ) {
            $plan_id = ls_default_plan_id( $slug );
        }
        $plan = ls_plan( $slug, $plan_id );
        if ( $plan && $plan['price'] > 0 ) {
            return $plan['price'];
        }
    }
    return ls_price_for( $slug );
}

// تشخیص base URL سرور
if ( defined('LS_BASE_URL') ) {
    $SERVER = rtrim(LS_BASE_URL, '/');
} else {
    $proto  = ( ! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ) ? 'https' : 'http';
    $SERVER = $proto . '://' . $_SERVER['HTTP_HOST'];
}

$SELF_URL = $SERVER . '/license-server/pay/';

// پارامترهای GET
$plugin     = preg_replace('/[^a-z0-9_-]/i', '', $_GET['plugin']  ?? 'wccp');
$domain_get = trim($_GET['domain']  ?? '');
$return_get = trim($_GET['return']  ?? '');
$plan_get   = preg_replace('/[^a-z0-9_-]/i', '', $_GET['plan'] ?? '');
if ( ls_has_plans( $plugin ) ) {
    if ( ! ls_plan( $plugin, $plan_get ) ) {
        $plan_get = ls_default_plan_id( $plugin );
    }
} else {
    $plan_get = '';
}
$BASE_PRICE = ls_amount_for( $plugin, $plan_get );

/* ─── کالبک زیبال ──────────────────────────────────────────────── */
if ( isset($_GET['zibal_cb']) ) {
    $track_id = trim($_GET['trackId'] ?? '');
    $success  = (int)($_GET['success'] ?? 0);

    if ( ! $track_id ) {
        show_error('خطا', 'شناسه تراکنش دریافت نشد.');
    }

    $pay = Database::payment_find($track_id);

    if ( ! $pay ) {
        show_error('خطا', 'اطلاعات پرداخت یافت نشد.');
    }

    // قبلاً پردازش شده
    if ( $pay['status'] === 'paid' && $pay['license_key'] ) {
        do_redirect($pay['return_url'], $pay['license_key']);
    }

    if ( $success !== 1 ) {
        Database::payment_update($track_id, ['status' => 'failed']);
        $retry = $SELF_URL . '?plugin=' . urlencode($pay['plugin'])
               . '&domain=' . urlencode($pay['domain'])
               . '&return=' . urlencode($pay['return_url'])
               . ( ! empty( $pay['plan'] ) ? '&plan=' . urlencode( (string) $pay['plan'] ) : '' );
        show_error('پرداخت ناموفق', 'پرداخت لغو شد یا با خطا مواجه شد.',
            '<a href="' . htmlspecialchars($retry) . '" class="btn">تلاش مجدد</a>');
    }

    // تأیید با زیبال
    $verify = zibal_post('https://gateway.zibal.ir/v1/verify', [
        'merchant' => $MERCHANT,
        'trackId'  => (int)$track_id,
    ]);

    $result_code = $verify['result'] ?? 0;
    if ( $result_code !== 100 && $result_code !== 201 ) {
        show_error('خطای تأیید', 'پرداخت تأیید نشد. کد: ' . $result_code);
    }

    $lic_months = max( 0, (int) ( $pay['months'] ?? 0 ) );
    $lic_plan   = (string) ( $pay['plan'] ?? '' );
    if ( $lic_months > 0 ) {
        $lic = LicenseManager::create_or_extend_subscription(
            $pay['email'],
            $pay['plugin'],
            $lic_months,
            $pay['domain'] ?? '',
            'اشتراک ' . ( $lic_plan ?: ( $lic_months . 'm' ) )
        );
    } elseif ( $lic_plan !== '' && ls_has_plans( (string) $pay['plugin'] ) ) {
        // پلن دائمی (months=0) برای محصولات دارای LS_PLANS
        $lic = LicenseManager::create_or_upgrade_lifetime(
            $pay['email'],
            $pay['plugin'],
            $pay['domain'] ?? '',
            'لایسنس دائمی (' . $lic_plan . ')'
        );
    } else {
        $lic = LicenseManager::create( $pay['email'], $pay['plugin'] );
        LicenseManager::activate( $lic['license_key'], $pay['domain'] );
    }

    Database::payment_update($track_id, ['status' => 'paid', 'license_key' => $lic['license_key']]);

    // ارسال ایمیل حاوی کلید لایسنس به خریدار
    Mailer::send_license_email( $lic, $pay['domain'] ?? '' );

    // ثبت استفاده از کوپن (اگر کد تخفیف استفاده شده بود)
    if ( ! empty( $pay['coupon_id'] ) ) {
        CouponManager::register_use( $pay['coupon_id'] );
    }

    // نمایش کلید + redirect خودکار به سایت مشتری
    $portal_url = rtrim($SERVER, '/') . '/license-server/portal/?email=' . urlencode($pay['email']);
    $exp_line = ! empty( $lic['expires_at'] )
        ? '<p style="color:#166534;font-size:13px;margin:8px 0">📅 اعتبار تا: <strong>' . htmlspecialchars( $lic['expires_at'] ) . '</strong></p>'
        : '<p style="color:#6b7280;font-size:13px;margin:8px 0">♾ لایسنس مادام‌العمر</p>';
    $key_html = '<div class="key-box">'
        . '<span id="lk">' . htmlspecialchars($lic['license_key']) . '</span>'
        . '<button onclick="navigator.clipboard.writeText(document.getElementById(\'lk\').innerText);this.textContent=\'✓\'" class="btn-copy">کپی</button>'
        . '</div>'
        . $exp_line
        . '<p style="color:#6b7280;font-size:13px">این کلید را در پلاگین سایت خود وارد کنید.</p>'
        . '<p style="color:#6b7280;font-size:12px;margin-top:8px">همچنین می‌توانید از <a href="' . htmlspecialchars($portal_url) . '">پورتال مشتری</a> به لایسنس‌های خود دسترسی داشته باشید.</p>';

    if ( $pay['return_url'] ) {
        $redirect_url = $pay['return_url'];
        $sep = strpos($redirect_url, '?') !== false ? '&' : '?';
        $redirect_url .= $sep . http_build_query([
            'wccp_activate' => '1',
            'wccp_key'      => $lic['license_key'],
            'wbl_product'   => $pay['plugin'],
        ]);
        $key_html .= '<p style="margin-top:14px;font-size:13px;color:#374151">⏳ در حال انتقال به سایت شما برای فعال‌سازی خودکار...</p>'
            . '<script>setTimeout(function(){ window.location="' . addslashes($redirect_url) . '"; }, 4000);</script>';
    }

    show_page('پرداخت موفق ✅', '<div class="card"><h2>لایسنس فعال شد ✅</h2>' . $key_html . '</div>');
}

/* ─── ارسال فرم → شروع پرداخت ──────────────────────────────────── */
$form_error = '';

if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
    $email_raw  = trim($_POST['email']      ?? '');
    $domain_raw = trim($_POST['domain']     ?? '');
    $plugin_p   = preg_replace('/[^a-z0-9_-]/i', '', $_POST['plugin']     ?? 'wccp');
    $return_p   = trim($_POST['return_url'] ?? '');
    $coupon_raw = trim($_POST['coupon_code'] ?? '');
    $plan_p     = preg_replace('/[^a-z0-9_-]/i', '', $_POST['plan'] ?? '');

    $plan_months = 0;
    $plan_label  = '';
    if ( ls_has_plans( $plugin_p ) ) {
        if ( ! ls_plan( $plugin_p, $plan_p ) ) {
            $plan_p = ls_default_plan_id( $plugin_p );
        }
        $sel_plan    = ls_plan( $plugin_p, $plan_p );
        $plan_months = $sel_plan ? (int) $sel_plan['months'] : 0;
        $plan_label  = $sel_plan ? (string) $sel_plan['label'] : '';
        $BASE_PRICE  = ls_amount_for( $plugin_p, $plan_p );
        $plan_get    = $plan_p;
    } else {
        $plan_p     = '';
        $BASE_PRICE = ls_price_for( $plugin_p );
    }

    $email_val  = filter_var($email_raw, FILTER_VALIDATE_EMAIL);

    if ( ! $email_val || ! $domain_raw ) {
        $form_error  = 'ایمیل یا دامنه معتبر نیست.';
        $domain_get  = $domain_raw;
    } else {
        $clean_domain = LicenseManager::clean_domain($domain_raw);
        // محصولات دارای پلن: تمدید/ارتقا مجاز است
        $is_plan_product = ls_has_plans( $plugin_p );

        $existing_email  = Database::license_find_by_email($email_val, $plugin_p);
        $existing_domain = Database::license_find_by_domain($clean_domain, $plugin_p);

        if ( ! $is_plan_product && $existing_email ) {
            $key_masked = substr($existing_email['license_key'], 0, 9) . '●●●●●●●●●';
            $form_error = 'این ایمیل قبلاً لایسنس فعال دارد (' . $key_masked . '). برای دریافت کلید با پشتیبانی تماس بگیرید.';
            $domain_get = $domain_raw;
        } elseif ( ! $is_plan_product && $existing_domain ) {
            $key_masked = substr($existing_domain['license_key'], 0, 9) . '●●●●●●●●●';
            $form_error = 'این دامنه قبلاً لایسنس فعال دارد (' . $key_masked . '). برای انتقال به دامنه دیگر با پشتیبانی تماس بگیرید.';
            $domain_get = $domain_raw;
        } else {
            // ─── اعتبارسنجی و اعمال کد تخفیف ───
            $coupon_info = null;
            $final_amount = $BASE_PRICE;
            if ( $coupon_raw !== '' ) {
                $coupon_info = CouponManager::validate( $coupon_raw, $plugin_p, $BASE_PRICE );
                if ( ! $coupon_info['valid'] ) {
                    $form_error = 'کد تخفیف: ' . $coupon_info['message'];
                    $domain_get = $domain_raw;
                } else {
                    $final_amount = (int) $coupon_info['final'];
                }
            }

            if ( ! $form_error ) {
                $cb   = $SELF_URL . '?zibal_cb=1&plugin=' . urlencode($plugin_p) . '&domain=' . urlencode($domain_raw)
                      . ( $plan_p !== '' ? '&plan=' . urlencode( $plan_p ) : '' );
                $desc = ( $is_plan_product ? 'پلن ' : 'لایسنس ' ) . $plugin_p
                      . ( $plan_label !== '' ? ' (' . $plan_label . ')' : '' )
                      . ' — ' . $domain_raw
                      . ( $coupon_info ? ' (کد تخفیف: ' . $coupon_info['coupon']['code'] . ')' : '' );
                $resp = zibal_post('https://gateway.zibal.ir/v1/request', [
                    'merchant'    => $MERCHANT,
                    'amount'      => $final_amount,
                    'callbackUrl' => $cb,
                    'description' => $desc,
                ]);

                $res_code = $resp['result'] ?? -1;
                if ( $res_code !== 100 ) {
                    $form_error = 'خطا در اتصال به درگاه. کد: ' . $res_code . ' — ' . ($resp['message'] ?? '');
                    $domain_get = $domain_raw;
                } else {
                    $track_id = (string)$resp['trackId'];
                    Database::payment_insert([
                        'track_id'       => $track_id,
                        'plugin'         => $plugin_p,
                        'email'          => $email_val,
                        'domain'         => LicenseManager::clean_domain($domain_raw),
                        'return_url'     => $return_p,
                        'amount'         => $final_amount,
                        'base_amount'    => $BASE_PRICE,
                        'plan'           => $plan_p !== '' ? $plan_p : null,
                        'months'         => $plan_months > 0 ? $plan_months : 0,
                        'coupon_id'      => $coupon_info ? $coupon_info['coupon']['id'] : null,
                        'coupon_code'    => $coupon_info ? $coupon_info['coupon']['code'] : null,
                        'coupon_discount'=> $coupon_info ? $coupon_info['discount'] : 0,
                        'status'         => 'pending',
                        'license_key'    => null,
                        'created_at'     => date('Y-m-d H:i:s'),
                    ]);

                    header('Location: https://gateway.zibal.ir/start/' . $track_id);
                    exit;
                }
            }
        }
    }
}

/* ─── نمایش فرم ─────────────────────────────────────────────────── */
$labels = [ 'wccp' => 'Custom Checkout Pages' ];
if ( defined('LS_PLUGIN_LABELS') && is_array(LS_PLUGIN_LABELS) ) {
    $labels = array_merge($labels, LS_PLUGIN_LABELS);
}
$plugin_label = $labels[$plugin] ?? $plugin;
$amount_toman = number_format((int)($BASE_PRICE / 10));
$has_plans    = ls_has_plans( $plugin );
$plans_list   = ls_plans_for( $plugin );

$original_amount = $has_plans ? null : ls_original_price_for($plugin);
$price_html = '<div class="price" id="header_price">' . $amount_toman . ' تومان</div>';
if ( $has_plans ) {
    $cur_plan = ls_plan( $plugin, $plan_get );
    $sub_lbl  = $cur_plan ? $cur_plan['label'] : 'اشتراکی';
    $price_html = '<div class="price" id="header_price">' . $amount_toman . ' تومان</div>'
        . '<div class="price-sub" id="header_plan_lbl">پلن ' . htmlspecialchars( $sub_lbl ) . '</div>';
} elseif ( $original_amount !== null && $original_amount > $BASE_PRICE ) {
    $original_toman = number_format((int)($original_amount / 10));
    $off_percent    = round( ( 1 - $BASE_PRICE / $original_amount ) * 100 );
    $hours_left     = ls_promo_hours_left($plugin);
    $price_html = '<div class="price-row">'
        . '<span class="price-original">' . $original_toman . ' تومان</span>'
        . '<span class="price" id="header_price">' . $amount_toman . ' تومان</span>'
        . '<span class="price-badge">' . $off_percent . '% تخفیف</span>'
        . '</div>';
    if ( $hours_left !== null ) {
        $price_html .= '<div class="price-countdown">⏳ این قیمت فقط تا ' . $hours_left . ' ساعت دیگر معتبر است — بعدش قیمت ' . $original_toman . ' تومان می‌شود.</div>';
    }
}

$error_html = $form_error
    ? '<div class="notice-error">' . htmlspecialchars($form_error) . '</div>'
    : '';

// انتخاب پلن اشتراکی
$plans_html = '';
$plans_js   = [];
if ( $has_plans ) {
    $plans_html = '<div class="plans" id="plans_box"><div class="plans-title">پلن مورد نظر را انتخاب کنید</div><div class="plans-grid">';
    foreach ( $plans_list as $pid => $pdata ) {
        $pinfo = ls_plan( $plugin, (string) $pid );
        if ( ! $pinfo ) continue;
        $checked = ( $plan_get === $pinfo['id'] ) ? ' checked' : '';
        $active  = ( $plan_get === $pinfo['id'] ) ? ' is-active' : '';
        $badge   = $pinfo['badge'] !== '' ? '<span class="plan-badge">' . htmlspecialchars( $pinfo['badge'] ) . '</span>' : '';
        $toman   = number_format( (int) ( $pinfo['price'] / 10 ) );
        $plans_html .= '<label class="plan-card' . $active . '">'
            . '<input type="radio" name="plan" value="' . htmlspecialchars( $pinfo['id'] ) . '"' . $checked . '>'
            . $badge
            . '<div class="plan-label">' . htmlspecialchars( $pinfo['label'] ) . '</div>'
            . '<div class="plan-price">' . $toman . ' <small>تومان</small></div>'
            . ( $pinfo['hint'] !== '' ? '<div class="plan-hint">' . htmlspecialchars( $pinfo['hint'] ) . '</div>' : '' )
            . '</label>';
        $plans_js[ $pinfo['id'] ] = [
            'price' => (int) $pinfo['price'],
            'label' => $pinfo['label'],
            'toman' => (int) ( $pinfo['price'] / 10 ),
        ];
    }
    $plans_html .= '</div></div>';
}

// فیلد کد تخفیف — AJAX برای پیش‌نمایش
$coupon_html = '
<div class="field">
    <label>🎟️ کد تخفیف (اختیاری)</label>
    <div style="display:flex;gap:8px">
        <input type="text" name="coupon_code" id="coupon_code" value="' . htmlspecialchars(trim($_POST['coupon_code'] ?? '')) . '"
               placeholder="مثلاً SUMMER20" style="direction:ltr;text-align:left;text-transform:uppercase">
        <button type="button" id="coupon_check" class="btn-check" style="white-space:nowrap">بررسی</button>
    </div>
    <span class="hint" id="coupon_hint">اگر کد تخفیف دارید وارد کنید و «بررسی» را بزنید.</span>
</div>';

$current_meta  = ( defined('LS_PLUGIN_META') && is_array(LS_PLUGIN_META) ) ? ( LS_PLUGIN_META[ $plugin ] ?? [] ) : [];
$current_icon  = $current_meta['icon'] ?? '🔑';

$features_html = $has_plans
    ? '<span>✅ ماهانه / ۳ ماهه / دائمی</span><span>✅ تمدید و ارتقا</span><span>✅ آپدیت</span><span>✅ پشتیبانی</span>'
    : '<span>✅ لایسنس مادام‌العمر</span><span>✅ آپدیت خودکار</span><span>✅ پشتیبانی ۶ ماهه</span>';

$form_html = '
<div class="card">
    <div class="product-header">
        <div class="icon">' . $current_icon . '</div>
        <div>
            <h2 style="margin:0 0 4px">' . htmlspecialchars($plugin_label) . '</h2>
            ' . $price_html . '
        </div>
    </div>
    ' . $error_html . '
    <form method="post" autocomplete="on" id="pay_form">
        <input type="hidden" name="plugin"     value="' . htmlspecialchars($plugin) . '">
        <input type="hidden" name="return_url" value="' . htmlspecialchars($return_get) . '">
        ' . $plans_html . '
        <div class="field">
            <label>ایمیل شما</label>
            <input type="email" name="email" placeholder="name@example.com" required autofocus>
        </div>
        <div class="field">
            <label>آدرس سایت (دامنه‌ای که پلاگین روی آن نصب است)</label>
            <input type="text" name="domain" value="' . htmlspecialchars($domain_get) . '" placeholder="example.com" required>
            <span class="hint">بدون https:// — مثال: myshop.ir</span>
        </div>
        ' . $coupon_html . '
        <div id="final_price_box" style="display:none;background:#f0fdf4;border:1.5px solid #86efac;border-radius:10px;padding:12px 14px;margin:14px 0">
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px">
                <span style="font-size:13px;color:#166534">مبلغ نهایی قابل پرداخت:</span>
                <span id="final_price" style="font-size:18px;font-weight:bold;color:#16a34a">—</span>
            </div>
            <div id="discount_detail" style="font-size:11px;color:#16a34a;margin-top:4px"></div>
        </div>
        <button type="submit" class="btn-pay" id="pay_btn">پرداخت با زیبال 💳</button>
    </form>
    <div class="features">' . $features_html . '</div>
    <p class="login-hint" style="margin-top:12px">
        🧪 قبل از خرید تست کنید:
        <a href="' . htmlspecialchars( rtrim( $SERVER, '/' ) . '/license-server/demos/?plugin=' . rawurlencode( $plugin ) ) . '" target="_blank" rel="noopener">دانلود نسخه دمو رایگان</a>
    </p>
    <p class="login-hint">لایسنس دارید؟ <a href="' . htmlspecialchars(rtrim($SERVER, '/') . '/license-server/portal/') . '">وارد شوید</a></p>
</div>

<script>
(function(){
    var checkBtn   = document.getElementById("coupon_check");
    var codeInput  = document.getElementById("coupon_code");
    var hint       = document.getElementById("coupon_hint");
    var finalBox   = document.getElementById("final_price_box");
    var finalPrice = document.getElementById("final_price");
    var discountEl = document.getElementById("discount_detail");
    var payBtn     = document.getElementById("pay_btn");
    var headerPrice = document.getElementById("header_price");
    var headerPlan  = document.getElementById("header_plan_lbl");
    var baseToman  = ' . (int)($BASE_PRICE/10) . ';
    var pluginSlug = ' . json_encode($plugin) . ';
    var plansMap   = ' . json_encode( $plans_js, JSON_UNESCAPED_UNICODE ) . ';

    function fmt(n){ return n.toLocaleString("fa-IR"); }

    function currentAmountRial(){
        var sel = document.querySelector("input[name=plan]:checked");
        if ( sel && plansMap && plansMap[sel.value] ) {
            return plansMap[sel.value].price;
        }
        return baseToman * 10;
    }

    function syncPlanUI(){
        var sel = document.querySelector("input[name=plan]:checked");
        document.querySelectorAll(".plan-card").forEach(function(c){
            c.classList.toggle("is-active", c.contains(sel));
        });
        if ( sel && plansMap && plansMap[sel.value] ) {
            var p = plansMap[sel.value];
            baseToman = p.toman;
            if ( headerPrice ) headerPrice.textContent = fmt(p.toman) + " تومان";
            if ( headerPlan ) headerPlan.textContent = "پلن " + p.label;
            payBtn.textContent = "پرداخت " + fmt(p.toman) + " تومان با زیبال 💳";
        }
        finalBox.style.display = "none";
    }

    document.querySelectorAll("input[name=plan]").forEach(function(r){
        r.addEventListener("change", syncPlanUI);
    });
    if ( Object.keys(plansMap || {}).length ) syncPlanUI();

    checkBtn.addEventListener("click", function(){
        var code = codeInput.value.trim();
        if ( ! code ) {
            hint.textContent = "اول کد را وارد کنید.";
            hint.style.color = "#dc2626";
            finalBox.style.display = "none";
            return;
        }
        hint.textContent = "در حال بررسی...";
        hint.style.color = "#6b7280";
        checkBtn.disabled = true;
        checkBtn.textContent = "صبر کنید...";

        var amountRial = currentAmountRial();
        fetch("' . htmlspecialchars($SELF_URL) . 'check-coupon.php", {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: "code=" + encodeURIComponent(code) + "&plugin=" + encodeURIComponent(pluginSlug) + "&amount=" + encodeURIComponent(amountRial)
        })
        .then(function(r){ return r.json(); })
        .then(function(data){
            checkBtn.disabled = false;
            checkBtn.textContent = "بررسی";
            if ( data.valid ) {
                hint.innerHTML = "✅ " + data.message;
                hint.style.color = "#16a34a";
                finalBox.style.display = "block";
                finalPrice.textContent = fmt(data.final_toman) + " تومان";
                var line = "قیمت اصلی: " + fmt(Math.round(amountRial/10)) + " تومان";
                if ( data.discount_toman > 0 ) {
                    line += " — تخفیف: " + fmt(data.discount_toman) + " تومان";
                }
                discountEl.textContent = line;
                payBtn.textContent = "پرداخت " + fmt(data.final_toman) + " تومان با زیبال 💳";
            } else {
                hint.innerHTML = "❌ " + data.message;
                hint.style.color = "#dc2626";
                finalBox.style.display = "none";
                payBtn.textContent = "پرداخت با زیبال 💳";
            }
        })
        .catch(function(){
            checkBtn.disabled = false;
            checkBtn.textContent = "بررسی";
            hint.textContent = "خطا در ارتباط با سرور. دوباره تلاش کنید.";
            hint.style.color = "#dc2626";
            finalBox.style.display = "none";
        });
    });

    codeInput.addEventListener("input", function(){
        finalBox.style.display = "none";
        if ( Object.keys(plansMap || {}).length ) syncPlanUI();
        else payBtn.textContent = "پرداخت با زیبال 💳";
        if ( codeInput.value.trim() === "" ) {
            hint.textContent = "اگر کد تخفیف دارید وارد کنید و «بررسی» را بزنید.";
            hint.style.color = "#9ca3af";
        }
    });
})();
</script>';

// ─── سایر محصولات (cross-sell) ───────────────────────────────────
$plugin_meta = defined('LS_PLUGIN_META') && is_array(LS_PLUGIN_META) ? LS_PLUGIN_META : [];
$all_slugs   = array_unique( array_merge(
    array_keys( $labels ),
    defined('LS_PRICES') && is_array(LS_PRICES) ? array_keys(LS_PRICES) : []
) );
$other_cards = '';
foreach ( $all_slugs as $slug ) {
    if ( $slug === $plugin ) continue;
    $price = ls_amount_for( $slug, ls_has_plans( $slug ) ? ls_default_plan_id( $slug ) : '' );
    if ( $price <= 0 ) continue;

    $meta      = $plugin_meta[ $slug ] ?? [];
    $icon      = $meta['icon'] ?? '🔌';
    $desc      = $meta['desc'] ?? '';
    $name      = $labels[ $slug ] ?? $slug;
    $toman     = number_format( (int)( $price / 10 ) );
    $orig      = ls_has_plans( $slug ) ? null : ls_original_price_for( $slug );
    $price_tag = ( ls_has_plans( $slug ) ? 'از ' : '' ) . $toman . ' تومان';
    if ( $orig !== null && $orig > $price ) {
        $price_tag = '<span class="oc-price-old">' . number_format( (int)( $orig / 10 ) ) . '</span> ' . $toman . ' تومان';
    }
    $href = $SELF_URL . '?plugin=' . urlencode( $slug )
          . ( $domain_get !== '' ? '&domain=' . urlencode( $domain_get ) : '' )
          . ( $return_get !== '' ? '&return=' . urlencode( $return_get ) : '' );

    $other_cards .= '<a href="' . htmlspecialchars( $href ) . '" class="other-card">'
        . '<div class="other-card__icon">' . $icon . '</div>'
        . '<div class="other-card__body">'
        . '<div class="other-card__name">' . htmlspecialchars( $name ) . '</div>'
        . ( $desc !== '' ? '<div class="other-card__desc">' . htmlspecialchars( $desc ) . '</div>' : '' )
        . '<div class="other-card__price">' . $price_tag . '</div>'
        . '</div>'
        . '<span class="other-card__arrow">←</span>'
        . '</a>';
}

$others_html = '';
if ( $other_cards !== '' ) {
    $others_html = '<div class="others-wrap">'
        . '<h3 class="others-title">سایر افزونه‌های Webakery</h3>'
        . '<p class="others-sub">شاید به این محصولات هم نیاز داشته باشید</p>'
        . '<div class="others-grid">' . $other_cards . '</div>'
        . '</div>';
}

show_page('خرید لایسنس — ' . $plugin_label, $form_html . $others_html);

/* ─── توابع ─────────────────────────────────────────────────────── */
function zibal_post($url, $data) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST,           true);
    curl_setopt($ch, CURLOPT_POSTFIELDS,     json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER,     ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT,        15);
    $body = curl_exec($ch);
    curl_close($ch);
    $decoded = json_decode($body ?: '{}', true);
    return is_array($decoded) ? $decoded : [];
}

function do_redirect($return_url, $key) {
    $sep = strpos($return_url, '?') !== false ? '&' : '?';
    $url = $return_url . $sep . http_build_query(['wccp_activate' => '1', 'wccp_key' => $key]);
    header('Location: ' . $url);
    exit;
}

function show_error($title, $msg, $extra = '') {
    show_page($title, '<div class="card"><h2>' . htmlspecialchars($title) . '</h2>'
        . '<p style="color:#374151;margin:12px 0">' . htmlspecialchars($msg) . '</p>' . $extra . '</div>');
}

function show_page($title, $body) {
    echo '<!DOCTYPE html><html lang="fa" dir="rtl"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>' . htmlspecialchars($title) . '</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:"Vazirmatn",Tahoma,sans-serif;background:linear-gradient(160deg,#f8fafc 0%,#eef2ff 45%,#f3f4f6 100%);min-height:100vh;display:flex;align-items:flex-start;justify-content:center;padding:28px 20px 40px}
.page-shell{width:100%;max-width:520px}
.card{background:#fff;border-radius:16px;box-shadow:0 4px 24px rgba(0,0,0,.09);padding:32px;width:100%}
.product-header{display:flex;align-items:center;gap:16px;padding-bottom:20px;border-bottom:1px solid #f3f4f6;margin-bottom:20px}
.icon{font-size:40px}.price{font-size:22px;font-weight:bold;color:#6c63ff}
.price-sub{font-size:12px;color:#6b7280;margin-top:4px}
.plans{margin:0 0 18px}
.plans-title{font-size:13px;font-weight:700;color:#374151;margin-bottom:10px}
.plans-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:10px}
.plan-card{position:relative;display:block;border:1.5px solid #e5e7eb;border-radius:12px;padding:14px 12px;cursor:pointer;background:#fafafa;transition:border-color .15s,box-shadow .15s,background .15s}
.plan-card input{position:absolute;opacity:0;pointer-events:none}
.plan-card:hover{border-color:#c4b5fd;background:#f5f3ff}
.plan-card.is-active{border-color:#6c63ff;background:#f5f3ff;box-shadow:0 0 0 3px rgba(108,99,255,.12)}
.plan-badge{position:absolute;top:-9px;left:10px;font-size:10px;font-weight:800;color:#fff;background:#ef4444;border-radius:20px;padding:2px 8px}
.plan-label{font-size:14px;font-weight:800;color:#111827;margin-bottom:6px}
.plan-price{font-size:18px;font-weight:800;color:#6c63ff}
.plan-price small{font-size:11px;font-weight:600;color:#6b7280}
.plan-hint{font-size:11px;color:#6b7280;margin-top:6px;line-height:1.5}
.price-row{display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.price-original{font-size:14px;color:#9ca3af;text-decoration:line-through}
.price-badge{font-size:11px;font-weight:bold;color:#fff;background:#ef4444;border-radius:20px;padding:2px 9px}
.price-countdown{width:100%;font-size:11.5px;color:#b45309;background:#fffbeb;border:1px solid #fde68a;border-radius:8px;padding:6px 10px;margin-top:8px}
h2{font-size:18px;color:#111827;margin-bottom:16px}
.field{margin-bottom:14px}
label{display:block;font-size:13px;color:#374151;margin-bottom:5px;font-weight:600}
input{width:100%;padding:10px 14px;border:1.5px solid #e5e7eb;border-radius:8px;font-size:14px;font-family:inherit;direction:ltr;text-align:left}
input:focus{outline:none;border-color:#6c63ff;box-shadow:0 0 0 3px rgba(108,99,255,.1)}
.hint{font-size:11px;color:#9ca3af;margin-top:3px;display:block}
.btn-pay{width:100%;padding:14px;background:#6c63ff;color:#fff;border:none;border-radius:10px;font-size:16px;font-family:inherit;font-weight:bold;cursor:pointer;margin-top:6px}
.btn-pay:hover{background:#5753d4}
.btn-check{padding:10px 16px;background:#ede9fe;color:#6c63ff;border:1.5px solid #c4b5fd;border-radius:8px;font-family:inherit;font-size:13px;font-weight:bold;cursor:pointer}
.btn-check:hover{background:#ddd6fe}
.btn-check:disabled{opacity:.6;cursor:not-allowed}
.features{display:flex;gap:8px;margin-top:18px;padding-top:14px;border-top:1px solid #f3f4f6;flex-wrap:wrap;font-size:12px;color:#6b7280}
.login-hint{text-align:center;font-size:12.5px;color:#6b7280;margin-top:14px}
.login-hint a{color:#6c63ff;font-weight:bold;text-decoration:none}
.login-hint a:hover{text-decoration:underline}
.notice-error{background:#fef2f2;color:#dc2626;border:1px solid #fecaca;border-radius:8px;padding:10px 14px;margin-bottom:14px;font-size:13px}
.btn{display:inline-block;padding:10px 20px;background:#6c63ff;color:#fff;border-radius:8px;text-decoration:none;font-size:14px;margin-top:10px}
.key-box{background:#f0fdf4;border:1.5px solid #86efac;border-radius:10px;padding:12px 16px;margin:14px 0;display:flex;align-items:center;gap:10px;direction:ltr;justify-content:space-between}
#lk{font-family:monospace;font-size:13px;color:#166534;font-weight:bold;word-break:break-all}
.btn-copy{background:#16a34a;color:#fff;border:none;border-radius:6px;padding:5px 12px;font-family:inherit;cursor:pointer;font-size:12px}
.others-wrap{margin-top:22px}
.others-title{font-size:15px;font-weight:800;color:#1e293b;text-align:center;margin-bottom:4px}
.others-sub{font-size:12px;color:#94a3b8;text-align:center;margin-bottom:14px}
.others-grid{display:flex;flex-direction:column;gap:10px}
.other-card{display:flex;align-items:center;gap:14px;background:#fff;border:1.5px solid #e8ecf4;border-radius:14px;padding:14px 16px;text-decoration:none;color:inherit;
  box-shadow:0 2px 10px rgba(15,23,42,.05);transition:transform .15s,box-shadow .15s,border-color .15s}
.other-card:hover{transform:translateY(-2px);box-shadow:0 8px 22px rgba(108,99,255,.14);border-color:#c4b5fd}
.other-card__icon{font-size:28px;flex-shrink:0;width:48px;height:48px;display:flex;align-items:center;justify-content:center;background:#f5f3ff;border-radius:12px}
.other-card__body{flex:1;min-width:0}
.other-card__name{font-size:13px;font-weight:700;color:#1e293b;line-height:1.5;margin-bottom:3px}
.other-card__desc{font-size:11px;color:#64748b;line-height:1.6;margin-bottom:5px}
.other-card__price{font-size:13px;font-weight:800;color:#6c63ff}
.oc-price-old{font-size:11px;color:#9ca3af;text-decoration:line-through;font-weight:400;margin-left:4px}
.other-card__arrow{color:#c4b5fd;font-size:18px;font-weight:bold;flex-shrink:0}
</style></head><body><div class="page-shell">' . $body . '</div></body></html>';
    exit;
}
