<?php
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WCI_Invoice' ) ) :

class WCI_Invoice {

    private $order_id;

    public function __construct( $order_id ) {
        $this->order_id = absint( $order_id );
    }

    /**
     * لینک مشاهده فاکتور در پیشخوان.
     */
    public static function admin_view_url( $order_id ): string {
        return add_query_arg(
            array(
                'page'        => 'wci-orders',
                'wci_invoice' => 1,
                'order_id'    => absint( $order_id ),
            ),
            admin_url( 'admin.php' )
        );
    }

    /**
     * لینک دانلود فاکتور در پیشخوان.
     */
    public static function admin_download_url( $order_id ): string {
        return add_query_arg(
            array(
                'page'                 => 'wci-orders',
                'wci_invoice'          => 1,
                'wci_invoice_download' => 1,
                'order_id'             => absint( $order_id ),
            ),
            admin_url( 'admin.php' )
        );
    }

    /**
     * لینک مشاهده/دانلود از پرتال حسابدار یا مدیر.
     */
    public static function portal_url( $order_id, $download = false ): string {
        $args = array(
            'action'   => 'wap_invoice',
            'order_id' => absint( $order_id ),
        );
        if ( $download ) {
            $args['download'] = 1;
        }
        return wp_nonce_url( add_query_arg( $args, admin_url( 'admin-post.php' ) ), 'wap_invoice_' . absint( $order_id ) );
    }

    /**
     * توضیح محصول از ووکامرس (کوتاه، در صورت نبود کامل).
     */
    private static function product_description( $product ): string {
        if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
            return '';
        }

        $desc = $product->get_short_description();
        if ( $desc === '' || $desc === null ) {
            $desc = $product->get_description();
        }

        $desc = wp_strip_all_tags( (string) $desc );
        $desc = preg_replace( '/\s+/u', ' ', trim( $desc ) );

        if ( $desc === '' ) {
            return '';
        }

        if ( function_exists( 'mb_strlen' ) && mb_strlen( $desc ) > 400 ) {
            $desc = mb_substr( $desc, 0, 400 ) . '…';
        } elseif ( strlen( $desc ) > 400 ) {
            $desc = substr( $desc, 0, 400 ) . '…';
        }

        return $desc;
    }

    public function download(): void {
        $this->render( true );
    }

    public function render( $as_download = false ): void {
        $order = wc_get_order( $this->order_id );
        if ( ! $order ) {
            wp_die( 'سفارش یافت نشد.' );
        }

        $s     = get_option( 'wci_invoice_settings', [] );
        $color = $s['primary_color'] ?? '#2271b1';
        $logo  = $s['logo_url'] ?? '';

        $state_code = $order->get_billing_state();
        $country    = $order->get_billing_country() ?: 'IR';
        $states     = WC()->countries->get_states( $country ) ?? [];
        $state_name = $states[ $state_code ] ?? $state_code;

        $company_name    = $s['company_name'] ?? get_bloginfo( 'name' );
        $company_address = $s['company_address'] ?? '';
        $company_phone   = $s['company_phone'] ?? '';

        $download_url = self::admin_download_url( $this->order_id );
        if ( isset( $_GET['action'] ) && 'wap_invoice' === sanitize_key( wp_unslash( $_GET['action'] ) ) ) {
            $download_url = self::portal_url( $this->order_id, true );
        }

        if ( $as_download ) {
            $filename = 'hesabdar-invoice-' . $order->get_order_number() . '.html';
            nocache_headers();
            header( 'Content-Type: text/html; charset=utf-8' );
            header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        }

        ?>
        <!DOCTYPE html>
        <html dir="rtl" lang="fa">
        <head>
            <meta charset="UTF-8">
            <title>فاکتور سفارش #<?php echo esc_html( $order->get_order_number() ); ?></title>
            <style>
                * { box-sizing: border-box; margin: 0; padding: 0; }
                body { font-family: Tahoma, Arial, sans-serif; font-size: 13px; color: #333; direction: rtl; background: #f5f5f5; }
                .invoice-wrap { max-width: 820px; margin: 30px auto; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 20px rgba(0,0,0,.12); }
                .inv-header { background: <?php echo esc_attr( $color ); ?>; color: #fff; padding: 28px 36px; display: flex; justify-content: space-between; align-items: center; }
                .inv-header h1 { font-size: 26px; font-weight: bold; }
                .inv-header .logo img { max-height: 70px; max-width: 180px; }
                .inv-meta { padding: 16px 36px; background: #f8f8f8; border-bottom: 1px solid #e5e5e5; display: flex; gap: 40px; flex-wrap: wrap; }
                .inv-meta dl { display: grid; grid-template-columns: auto 1fr; gap: 4px 12px; }
                .inv-meta dt { font-weight: bold; color: #555; }
                .inv-body { padding: 24px 36px; }

                /* فرستنده بالا-چپ | گیرنده پایین‌تر-راست (در صفحه RTL) */
                .parties { display: flex; flex-direction: column; gap: 18px; margin: 8px 0 8px; }
                .party-box { max-width: 48%; padding: 14px 16px; border: 1px solid #e5e5e5; border-radius: 8px; background: #fafafa; }
                .party-box h3 { font-size: 13px; color: <?php echo esc_attr( $color ); ?>; margin-bottom: 8px; border-bottom: 1px solid #e5e5e5; padding-bottom: 6px; }
                .party-box p { margin: 0 0 4px; line-height: 1.7; }
                .party-box .muted { color: #777; }
                .party-sender { align-self: flex-end; text-align: left; direction: rtl; } /* بصری: چپ */
                .party-receiver { align-self: flex-start; text-align: right; margin-top: 4px; } /* بصری: راست و پایین‌تر */

                .inv-section-title { font-size: 14px; font-weight: bold; color: <?php echo esc_attr( $color ); ?>; border-bottom: 2px solid <?php echo esc_attr( $color ); ?>; padding-bottom: 6px; margin: 20px 0 12px; }
                table.items { width: 100%; border-collapse: collapse; margin-top: 8px; }
                table.items th { background: <?php echo esc_attr( $color ); ?>; color: #fff; padding: 9px 12px; text-align: right; font-size: 12px; }
                table.items td { padding: 8px 12px; border-bottom: 1px solid #eee; vertical-align: top; }
                table.items tr:nth-child(even) td { background: #f9f9f9; }
                .item-name { font-weight: bold; }
                .item-desc { margin-top: 6px; color: #555; font-size: 12px; line-height: 1.7; white-space: pre-wrap; }
                .item-sku { color: #999; font-size: 11px; }
                .item-meta { margin-top: 4px; color: #666; font-size: 11.5px; }
                .totals { display: flex; justify-content: flex-start; margin-top: 16px; }
                .totals table { border: 1px solid #e5e5e5; border-radius: 6px; overflow: hidden; min-width: 260px; }
                .totals td { padding: 7px 14px; }
                .totals tr:last-child td { font-weight: bold; background: <?php echo esc_attr( $color ); ?>; color: #fff; font-size: 14px; }
                .inv-footer { background: #f8f8f8; border-top: 1px solid #e5e5e5; padding: 16px 36px; text-align: center; color: #888; font-size: 12px; }
                .signature-box { display: flex; justify-content: flex-end; margin-top: 40px; }
                .signature-inner { border: 1px solid #ccc; border-radius: 6px; padding: 16px 24px; text-align: center; min-width: 180px; }
                .signature-inner p { color: #888; font-size: 11px; margin-top: 50px; }
                @media print {
                    body { background: #fff; }
                    .invoice-wrap { box-shadow: none; margin: 0; }
                    .no-print { display: none; }
                }
                @media (max-width: 640px) {
                    .party-box { max-width: 100%; }
                    .party-sender, .party-receiver { align-self: stretch; text-align: right; }
                }
            </style>
        </head>
        <body>
        <div class="invoice-wrap">
            <div class="inv-header">
                <div>
                    <h1>فاکتور سفارش</h1>
                    <div style="margin-top:6px;font-size:14px">#<?php echo esc_html( $order->get_order_number() ); ?></div>
                </div>
                <div class="logo">
                    <?php if ( $logo ) : ?>
                        <img src="<?php echo esc_url( $logo ); ?>" alt="لوگو">
                    <?php else : ?>
                        <div style="font-size:20px;font-weight:bold"><?php echo esc_html( $company_name ); ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="inv-meta">
                <dl>
                    <dt>تاریخ:</dt>
                    <dd><?php echo esc_html( wc_format_datetime( $order->get_date_created() ) ); ?></dd>
                    <dt>وضعیت:</dt>
                    <dd><?php echo esc_html( wc_get_order_status_name( $order->get_status() ) ); ?></dd>
                    <dt>روش پرداخت:</dt>
                    <dd><?php echo esc_html( function_exists( 'wci_payment_label' ) ? wci_payment_label( $order->get_payment_method() ) : $order->get_payment_method_title() ); ?></dd>
                    <?php if ( $order->get_transaction_id() ) : ?>
                    <dt>کد پیگیری:</dt>
                    <dd><?php echo esc_html( $order->get_transaction_id() ); ?></dd>
                    <?php endif; ?>
                </dl>
            </div>

            <div class="inv-body">

                <div class="parties">
                    <div class="party-box party-sender">
                        <h3>فرستنده</h3>
                        <p><strong><?php echo esc_html( $company_name ); ?></strong></p>
                        <?php if ( $company_phone ) : ?>
                            <p><span class="muted">تلفن:</span> <?php echo esc_html( $company_phone ); ?></p>
                        <?php endif; ?>
                        <?php if ( $company_address ) : ?>
                            <p><span class="muted">آدرس:</span> <?php echo nl2br( esc_html( $company_address ) ); ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="party-box party-receiver">
                        <h3>گیرنده</h3>
                        <p><strong><?php echo esc_html( $order->get_formatted_billing_full_name() ); ?></strong></p>
                        <?php if ( $order->get_billing_phone() ) : ?>
                            <p><span class="muted">تلفن:</span> <?php echo esc_html( $order->get_billing_phone() ); ?></p>
                        <?php endif; ?>
                        <?php if ( $order->get_billing_email() ) : ?>
                            <p><span class="muted">ایمیل:</span> <?php echo esc_html( $order->get_billing_email() ); ?></p>
                        <?php endif; ?>
                        <p>
                            <span class="muted">آدرس:</span>
                            <?php
                            echo esc_html(
                                trim(
                                    $order->get_billing_address_1()
                                    . ( $order->get_billing_address_2() ? ' ' . $order->get_billing_address_2() : '' )
                                    . ( $order->get_billing_city() ? ' — ' . $order->get_billing_city() : '' )
                                    . ( $state_name ? '، ' . $state_name : '' )
                                    . ( $order->get_billing_postcode() ? '، کدپستی ' . $order->get_billing_postcode() : '' )
                                )
                            );
                            ?>
                        </p>
                        <?php foreach ( WAP_Baget_Fields::get_invoice_fields( $order ) as $label => $value ) : ?>
                            <p><span class="muted"><?php echo esc_html( $label ); ?>:</span> <?php echo esc_html( $value ); ?></p>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="inv-section-title">اقلام سفارش</div>
                <table class="items">
                    <thead>
                        <tr>
                            <th style="width:48px">ردیف</th>
                            <th>محصول / توضیحات</th>
                            <th style="width:70px">تعداد</th>
                            <th style="width:110px">قیمت واحد</th>
                            <th style="width:110px">جمع</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $i = 1;
                        foreach ( $order->get_items() as $item ) :
                            $product    = $item->get_product();
                            $unit_price = $item->get_total() / max( 1, $item->get_quantity() );
                            $desc       = self::product_description( $product );
                            ?>
                        <tr>
                            <td><?php echo $i++; ?></td>
                            <td>
                                <div class="item-name"><?php echo esc_html( $item->get_name() ); ?></div>
                                <?php if ( $product && $product->get_sku() ) : ?>
                                    <div class="item-sku">SKU: <?php echo esc_html( $product->get_sku() ); ?></div>
                                <?php endif; ?>
                                <?php
                                $meta = $item->get_formatted_meta_data( '' );
                                if ( ! empty( $meta ) ) :
                                    echo '<div class="item-meta">';
                                    foreach ( $meta as $meta_item ) {
                                        echo '<div>' . esc_html( wp_strip_all_tags( (string) $meta_item->display_key ) ) . ': '
                                            . esc_html( wp_strip_all_tags( (string) $meta_item->display_value ) ) . '</div>';
                                    }
                                    echo '</div>';
                                endif;
                                ?>
                                <?php if ( $desc !== '' ) : ?>
                                    <div class="item-desc"><?php echo esc_html( $desc ); ?></div>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html( (string) $item->get_quantity() ); ?></td>
                            <td><?php echo wp_kses_post( wc_price( $unit_price ) ); ?></td>
                            <td><?php echo wp_kses_post( wc_price( $item->get_total() ) ); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <div class="totals">
                    <table>
                        <tr><td>جمع کل محصولات:</td><td><?php echo wp_kses_post( wc_price( $order->get_subtotal() ) ); ?></td></tr>
                        <?php if ( $order->get_shipping_total() > 0 ) : ?>
                        <tr><td>هزینه ارسال:</td><td><?php echo wp_kses_post( wc_price( $order->get_shipping_total() ) ); ?></td></tr>
                        <?php endif; ?>
                        <?php if ( $order->get_discount_total() > 0 ) : ?>
                        <tr><td>تخفیف:</td><td>-<?php echo wp_kses_post( wc_price( $order->get_discount_total() ) ); ?></td></tr>
                        <?php endif; ?>
                        <?php if ( $order->get_total_tax() > 0 ) : ?>
                        <tr><td>مالیات:</td><td><?php echo wp_kses_post( wc_price( $order->get_total_tax() ) ); ?></td></tr>
                        <?php endif; ?>
                        <tr><td>مبلغ نهایی:</td><td><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></td></tr>
                    </table>
                </div>

                <?php if ( $order->get_customer_note() ) : ?>
                <div class="inv-section-title">یادداشت مشتری</div>
                <p><?php echo nl2br( esc_html( $order->get_customer_note() ) ); ?></p>
                <?php endif; ?>

                <?php if ( ! empty( $s['show_signature'] ) ) : ?>
                <div class="signature-box">
                    <div class="signature-inner">
                        <div style="font-weight:bold"><?php echo esc_html( $company_name ); ?></div>
                        <p>مهر و امضا</p>
                    </div>
                </div>
                <?php endif; ?>

            </div>

            <div class="inv-footer">
                <?php echo nl2br( esc_html( $s['footer_text'] ?? 'با تشکر از خرید شما' ) ); ?>
            </div>
        </div>

        <?php if ( ! $as_download ) : ?>
        <div class="no-print" style="text-align:center;margin:20px">
            <button onclick="window.print()" style="padding:10px 30px;background:<?php echo esc_attr( $color ); ?>;color:#fff;border:none;border-radius:4px;cursor:pointer;font-size:14px">🖨 چاپ / ذخیره PDF</button>
            <a href="<?php echo esc_url( $download_url ); ?>" style="display:inline-block;padding:10px 24px;background:#1d4ed8;color:#fff;border:none;border-radius:4px;cursor:pointer;font-size:14px;margin-right:8px;text-decoration:none">⬇ دانلود فاکتور</a>
            <button onclick="window.close()" style="padding:10px 20px;background:#888;color:#fff;border:none;border-radius:4px;cursor:pointer;font-size:14px;margin-right:8px">بستن</button>
        </div>
        <?php endif; ?>
        </body>
        </html>
        <?php
        if ( $as_download ) {
            exit;
        }
    }
}

endif;
