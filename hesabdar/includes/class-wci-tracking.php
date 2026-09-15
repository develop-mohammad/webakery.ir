<?php
defined( 'ABSPATH' ) || exit;

/**
 * درج و همگام‌سازی کد رهگیری پستی با سفارش ووکامرس — داخل Hesabdar.
 */
class WCI_Tracking {

	const META_CODE     = '_hesabdar_tracking_code';
	const META_PROVIDER = '_hesabdar_tracking_provider';
	const META_SENT_AT  = '_hesabdar_tracking_sent_at';

	/** کلیدهای رایج ووکامرس / افزونه‌های رهگیری */
	const WC_META_CODE     = '_tracking_number';
	const WC_META_PROVIDER = '_tracking_provider';

	/**
	 * @return array<string,string>
	 */
	public static function providers(): array {
		return array(
			'post'     => 'شرکت ملی پست',
			'tipax'    => 'تیپاکس',
			'dekapost' => 'دکاپست',
			'mahex'    => 'ماهکس',
			'chapar'   => 'چاپار',
			'other'    => 'سایر',
		);
	}

	public static function init(): void {
		add_action( 'admin_post_wci_save_tracking', array( __CLASS__, 'handle_save' ) );
	}

	/**
	 * @return array{code:string,provider:string,provider_label:string,sent_at:string}
	 */
	public static function get_for_order( $order ): array {
		if ( ! $order || ! is_a( $order, 'WC_Order' ) ) {
			return array(
				'code'            => '',
				'provider'        => 'post',
				'provider_label'  => self::providers()['post'],
				'sent_at'         => '',
			);
		}

		$code = (string) $order->get_meta( self::META_CODE );
		if ( $code === '' ) {
			$code = (string) $order->get_meta( self::WC_META_CODE );
		}

		// سازگاری با WooCommerce Shipment Tracking
		if ( $code === '' ) {
			$items = $order->get_meta( '_wc_shipment_tracking_items' );
			if ( is_array( $items ) && ! empty( $items[0]['tracking_number'] ) ) {
				$code = (string) $items[0]['tracking_number'];
			}
		}

		$provider = (string) $order->get_meta( self::META_PROVIDER );
		if ( $provider === '' ) {
			$provider = (string) $order->get_meta( self::WC_META_PROVIDER );
		}
		if ( $provider === '' || ! isset( self::providers()[ $provider ] ) ) {
			$provider = 'post';
		}

		return array(
			'code'           => $code,
			'provider'       => $provider,
			'provider_label' => self::providers()[ $provider ],
			'sent_at'        => (string) $order->get_meta( self::META_SENT_AT ),
		);
	}

	/**
	 * ذخیره روی سفارش ووکامرس + همگام‌سازی متای رایج.
	 */
	public static function save( WC_Order $order, string $code, string $provider, bool $send_sms = false ): array {
		$code     = trim( $code );
		$provider = sanitize_key( $provider );
		if ( ! isset( self::providers()[ $provider ] ) ) {
			$provider = 'post';
		}

		if ( $code === '' ) {
			return array( 'success' => false, 'message' => 'کد رهگیری را وارد کنید.' );
		}

		$label = self::providers()[ $provider ];

		$order->update_meta_data( self::META_CODE, $code );
		$order->update_meta_data( self::META_PROVIDER, $provider );
		$order->update_meta_data( self::WC_META_CODE, $code );
		$order->update_meta_data( self::WC_META_PROVIDER, $label );

		// فرمت افزونه WooCommerce Shipment Tracking (در صورت استفاده)
		$tracking_item = array(
			'tracking_provider'        => '',
			'custom_tracking_provider' => $label,
			'custom_tracking_link'     => '',
			'tracking_number'          => $code,
			'date_shipped'             => gmdate( 'Y-m-d' ),
			'tracking_id'              => md5( $code . $provider ),
		);
		$order->update_meta_data( '_wc_shipment_tracking_items', array( $tracking_item ) );

		$note = sprintf(
			'کد رهگیری پستی ثبت شد — ارائه‌دهنده: %s — کد: %s',
			$label,
			$code
		);
		$order->add_order_note( $note, false, true );

		$sms_msg = '';
		if ( $send_sms ) {
			$sms_result = self::send_customer_sms( $order, $code, $label );
			$sms_msg    = $sms_result['message'];
			if ( ! empty( $sms_result['success'] ) ) {
				$order->update_meta_data( self::META_SENT_AT, current_time( 'mysql' ) );
				$order->add_order_note(
					sprintf( 'پیامک کد رهگیری برای مشتری ارسال شد: %s', $code ),
					true,
					true
				);
			}
		}

		$order->save();

		$message = 'کد رهگیری ذخیره و با ووکامرس همگام شد.';
		if ( $sms_msg !== '' ) {
			$message .= ' ' . $sms_msg;
		}

		return array( 'success' => true, 'message' => $message );
	}

	/**
	 * ارسال پیامک — از هوک‌ها/افزونه‌های رایج، در غیر این صورت یادداشت مشتری + ایمیل ووکامرس.
	 *
	 * @return array{success:bool,message:string}
	 */
	public static function send_customer_sms( WC_Order $order, string $code, string $provider_label ): array {
		$phone = preg_replace( '/\D+/', '', (string) $order->get_billing_phone() );
		if ( $phone === '' ) {
			return array( 'success' => false, 'message' => 'شماره تماس مشتری خالی است.' );
		}

		$text = sprintf(
			'سفارش #%1$s از فروشگاه %2$s با %3$s ارسال شد. کد رهگیری: %4$s',
			$order->get_order_number(),
			wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
			$provider_label,
			$code
		);

		/**
		 * افزونه‌های پیامک می‌توانند این هوک را هندل کنند و true برگردانند.
		 *
		 * @param bool|null $sent
		 * @param string    $phone
		 * @param string    $text
		 * @param WC_Order  $order
		 */
		$sent = apply_filters( 'hesabdar_send_tracking_sms', null, $phone, $text, $order );

		if ( null === $sent ) {
			// تلاش با چند الگوی رایج افزونه‌های پیامک فارسی
			if ( function_exists( 'PWooSMS' ) ) {
				try {
					PWooSMS()->send( array( $phone ), $text );
					$sent = true;
				} catch ( Exception $e ) {
					$sent = false;
				}
			} elseif ( function_exists( 'woocommercesms_send_sms' ) ) {
				$sent = (bool) woocommercesms_send_sms( $phone, $text );
			} elseif ( has_action( 'woocommerce_iran_sms_send' ) ) {
				do_action( 'woocommerce_iran_sms_send', $phone, $text, $order );
				$sent = true;
			} else {
				do_action( 'hesabdar_tracking_sms_fallback', $phone, $text, $order );
				// یادداشت مشتری → ایمیل اعلان ووکامرس برای مشتری
				$order->add_order_note( $text, 1, true );
				$sent = true;
				return array(
					'success' => true,
					'message' => 'پیامک مستقیم در دسترس نبود؛ یادداشت مشتری/ایمیل ووکامرس ثبت شد.',
				);
			}
		}

		if ( $sent ) {
			return array( 'success' => true, 'message' => 'پیامک کد رهگیری ارسال شد.' );
		}

		return array( 'success' => false, 'message' => 'ارسال پیامک ناموفق بود.' );
	}

	public static function handle_save(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'manage_options' ) ) {
			wp_die( 'دسترسی ندارید.' );
		}

		$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
		check_admin_referer( 'wci_save_tracking_' . $order_id );

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			wp_die( 'سفارش یافت نشد.' );
		}

		$code     = isset( $_POST['tracking_code'] ) ? sanitize_textarea_field( wp_unslash( $_POST['tracking_code'] ) ) : '';
		$provider = isset( $_POST['tracking_provider'] ) ? sanitize_key( wp_unslash( $_POST['tracking_provider'] ) ) : 'post';
		$send_sms = ! empty( $_POST['send_sms'] );

		$result = self::save( $order, $code, $provider, $send_sms );

		$redirect = add_query_arg(
			array(
				'page'            => 'wci-order-edit',
				'order_id'        => $order_id,
				'wci_tracking'    => $result['success'] ? '1' : '0',
				'wci_tracking_msg'=> rawurlencode( $result['message'] ),
			),
			admin_url( 'admin.php' )
		);
		wp_safe_redirect( $redirect );
		exit;
	}

	public static function render_panel( WC_Order $order ): void {
		$data      = self::get_for_order( $order );
		$providers = self::providers();
		$action    = admin_url( 'admin-post.php' );
		?>
		<div class="wci-order-panel wci-tracking-panel" style="margin-top:16px">
			<h2>📦 ارسال کد رهگیری پستی</h2>
			<p class="description" style="margin-bottom:12px">مستقیماً روی سفارش ووکامرس ذخیره می‌شود — نیازی به ورود به صفحه سفارش WC نیست.</p>
			<form method="post" action="<?php echo esc_url( $action ); ?>">
				<input type="hidden" name="action" value="wci_save_tracking">
				<input type="hidden" name="order_id" value="<?php echo esc_attr( (string) $order->get_id() ); ?>">
				<?php wp_nonce_field( 'wci_save_tracking_' . $order->get_id() ); ?>

				<label for="wci_tracking_code"><strong>کد رهگیری</strong></label>
				<textarea id="wci_tracking_code" name="tracking_code" class="widefat" rows="3" placeholder="کد رهگیری پستی را وارد کنید" style="margin:6px 0 12px"><?php echo esc_textarea( $data['code'] ); ?></textarea>

				<label for="wci_tracking_provider"><strong>ارائه‌دهنده خدمات پست</strong></label>
				<select id="wci_tracking_provider" name="tracking_provider" class="widefat" style="margin:6px 0 14px">
					<?php foreach ( $providers as $key => $label ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $data['provider'], $key ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>

				<div style="display:flex;gap:8px;flex-wrap:wrap">
					<button type="submit" name="send_sms" value="0" class="button button-secondary" style="flex:1">💾 ذخیره در ووکامرس</button>
					<button type="submit" name="send_sms" value="1" class="button button-primary" style="flex:1">📱 ارسال پیامک</button>
				</div>

				<?php if ( $data['code'] !== '' ) : ?>
					<p class="description" style="margin-top:12px">
						کد فعلی: <code style="direction:ltr;display:inline-block"><?php echo esc_html( $data['code'] ); ?></code>
						— <?php echo esc_html( $data['provider_label'] ); ?>
						<?php if ( $data['sent_at'] !== '' ) : ?>
							<br>آخرین ارسال پیامک: <?php echo esc_html( $data['sent_at'] ); ?>
						<?php endif; ?>
					</p>
				<?php endif; ?>
			</form>
		</div>
		<?php
	}
}
