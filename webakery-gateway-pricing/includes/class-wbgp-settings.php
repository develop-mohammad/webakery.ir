<?php
defined( 'ABSPATH' ) || exit;

/**
 * تنظیمات ادمین.
 */
class WBGP_Settings {

	const OPTION = 'wbgp_settings';
	const PAGE   = 'wbgp-settings';

	public static function defaults() {
		return array(
			'cash_gateways'       => "wc_zibal\nWC_ZPal",
			'installment_enabled' => 1,
			'installment_type'    => 'percent', // percent | fixed
			'installment_amount'  => 15,
			'installment_label'   => 'کارمزد خرید اقساطی',
			'cash_discount_enabled' => 0,
			'cash_discount_type'    => 'percent', // percent | fixed
			'cash_discount_amount'  => 0,
			'cash_discount_label'   => 'تخفیف پرداخت نقدی',
			'taxable'               => 1,
		);
	}

	public static function get( $key = null, $default = null ) {
		$opts = wp_parse_args( (array) get_option( self::OPTION, array() ), self::defaults() );
		if ( null === $key ) {
			return $opts;
		}
		return array_key_exists( $key, $opts ) ? $opts[ $key ] : $default;
	}

	/** @return string[] */
	public static function cash_gateway_ids() {
		$raw = (string) self::get( 'cash_gateways', '' );
		$ids = preg_split( '/[\s,]+/', $raw );
		$ids = array_filter( array_map( 'trim', (array) $ids ) );
		return array_values( array_unique( $ids ) );
	}

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
	}

	public static function menu() {
		add_submenu_page(
			'woocommerce',
			'قیمت‌گذاری درگاه',
			'قیمت‌گذاری درگاه',
			'manage_woocommerce',
			self::PAGE,
			array( __CLASS__, 'render' )
		);
	}

	public static function register() {
		register_setting(
			'wbgp_settings_group',
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => self::defaults(),
			)
		);
	}

	public static function sanitize( $input ) {
		$d   = self::defaults();
		$out = array();

		$out['cash_gateways'] = isset( $input['cash_gateways'] )
			? sanitize_textarea_field( $input['cash_gateways'] )
			: $d['cash_gateways'];

		$out['installment_enabled'] = empty( $input['installment_enabled'] ) ? 0 : 1;
		$out['installment_type']    = ( isset( $input['installment_type'] ) && 'fixed' === $input['installment_type'] ) ? 'fixed' : 'percent';
		$out['installment_amount']  = isset( $input['installment_amount'] ) ? (float) $input['installment_amount'] : 0;
		$out['installment_label']   = isset( $input['installment_label'] ) ? sanitize_text_field( $input['installment_label'] ) : $d['installment_label'];

		$out['cash_discount_enabled'] = empty( $input['cash_discount_enabled'] ) ? 0 : 1;
		$out['cash_discount_type']    = ( isset( $input['cash_discount_type'] ) && 'fixed' === $input['cash_discount_type'] ) ? 'fixed' : 'percent';
		$out['cash_discount_amount']  = isset( $input['cash_discount_amount'] ) ? (float) $input['cash_discount_amount'] : 0;
		$out['cash_discount_label']   = isset( $input['cash_discount_label'] ) ? sanitize_text_field( $input['cash_discount_label'] ) : $d['cash_discount_label'];

		$out['taxable'] = empty( $input['taxable'] ) ? 0 : 1;

		if ( $out['installment_amount'] < 0 ) {
			$out['installment_amount'] = 0;
		}
		if ( $out['cash_discount_amount'] < 0 ) {
			$out['cash_discount_amount'] = 0;
		}

		return $out;
	}

	public static function render() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$o        = self::get();
		$gateways = array();
		if ( function_exists( 'WC' ) && WC()->payment_gateways() ) {
			foreach ( WC()->payment_gateways()->payment_gateways() as $id => $gw ) {
				$gateways[ $id ] = $gw->get_title() . ' (' . $id . ') — ' . ( 'yes' === $gw->enabled ? 'فعال' : 'غیرفعال' );
			}
		}
		?>
		<div class="wrap" dir="rtl">
			<h1>قیمت‌گذاری بر اساس درگاه پرداخت</h1>
			<p style="max-width:720px;line-height:1.8;color:#475569">
				هر درگاهی که در لیست <strong>نقدی</strong> نباشد، <strong>قسطی</strong> حساب می‌شود و می‌تواند کارمزد بگیرد.
				برای درگاه‌های نقدی می‌توانید تخفیف درصدی یا مبلغ ثابت بگذارید.
			</p>

			<?php if ( $gateways ) : ?>
				<details style="margin:12px 0 20px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:12px 14px;max-width:720px">
					<summary style="cursor:pointer;font-weight:700">شناسه درگاه‌های نصب‌شده</summary>
					<ul style="margin:10px 0 0;padding-right:18px;line-height:1.9;font-size:13px">
						<?php foreach ( $gateways as $id => $label ) : ?>
							<li><code dir="ltr"><?php echo esc_html( $id ); ?></code> — <?php echo esc_html( $label ); ?></li>
						<?php endforeach; ?>
					</ul>
				</details>
			<?php endif; ?>

			<form method="post" action="options.php" style="max-width:720px">
				<?php settings_fields( 'wbgp_settings_group' ); ?>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="wbgp_cash_gateways">درگاه‌های نقدی (معاف از کارمزد)</label></th>
						<td>
							<textarea name="<?php echo esc_attr( self::OPTION ); ?>[cash_gateways]" id="wbgp_cash_gateways" rows="4" class="large-text code" dir="ltr"><?php echo esc_textarea( $o['cash_gateways'] ); ?></textarea>
							<p class="description">هر خط یک شناسه — پیش‌فرض: <code>wc_zibal</code> و <code>WC_ZPal</code>. هر چیز دیگر قسطی محسوب می‌شود.</p>
						</td>
					</tr>

					<tr><th colspan="2"><h2 style="margin:8px 0 0">کارمزد درگاه قسطی</h2></th></tr>
					<tr>
						<th scope="row">فعال باشد؟</th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[installment_enabled]" value="1" <?php checked( ! empty( $o['installment_enabled'] ) ); ?> />
								اعمال کارمزد روی همه درگاه‌ها به‌جز نقدی‌ها
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row">نوع کارمزد</th>
						<td>
							<label style="margin-left:16px">
								<input type="radio" name="<?php echo esc_attr( self::OPTION ); ?>[installment_type]" value="percent" <?php checked( $o['installment_type'], 'percent' ); ?> />
								درصدی
							</label>
							<label>
								<input type="radio" name="<?php echo esc_attr( self::OPTION ); ?>[installment_type]" value="fixed" <?php checked( $o['installment_type'], 'fixed' ); ?> />
								مبلغ ثابت
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wbgp_inst_amount">مقدار</label></th>
						<td>
							<input type="number" step="0.01" min="0" name="<?php echo esc_attr( self::OPTION ); ?>[installment_amount]" id="wbgp_inst_amount" value="<?php echo esc_attr( $o['installment_amount'] ); ?>" class="regular-text" />
							<p class="description">اگر درصدی است مثلاً <code>15</code> یعنی ۱۵٪. اگر ثابت است مبلغ به واحد پول فروشگاه (تومان/ریال).</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wbgp_inst_label">عنوان در سبد</label></th>
						<td>
							<input type="text" name="<?php echo esc_attr( self::OPTION ); ?>[installment_label]" id="wbgp_inst_label" value="<?php echo esc_attr( $o['installment_label'] ); ?>" class="regular-text" />
						</td>
					</tr>

					<tr><th colspan="2"><h2 style="margin:8px 0 0">تخفیف درگاه نقدی</h2></th></tr>
					<tr>
						<th scope="row">فعال باشد؟</th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[cash_discount_enabled]" value="1" <?php checked( ! empty( $o['cash_discount_enabled'] ) ); ?> />
								اعمال تخفیف فقط وقتی زیبال / زرین‌پال (یا سایر نقدی‌های تعریف‌شده) انتخاب شود
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row">نوع تخفیف</th>
						<td>
							<label style="margin-left:16px">
								<input type="radio" name="<?php echo esc_attr( self::OPTION ); ?>[cash_discount_type]" value="percent" <?php checked( $o['cash_discount_type'], 'percent' ); ?> />
								درصدی
							</label>
							<label>
								<input type="radio" name="<?php echo esc_attr( self::OPTION ); ?>[cash_discount_type]" value="fixed" <?php checked( $o['cash_discount_type'], 'fixed' ); ?> />
								مبلغ ثابت
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wbgp_cash_amount">مقدار تخفیف</label></th>
						<td>
							<input type="number" step="0.01" min="0" name="<?php echo esc_attr( self::OPTION ); ?>[cash_discount_amount]" id="wbgp_cash_amount" value="<?php echo esc_attr( $o['cash_discount_amount'] ); ?>" class="regular-text" />
							<p class="description">مثلاً <code>5</code> درصد یا مبلغ ثابت. روی جمع جزء سبد اعمال می‌شود.</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wbgp_cash_label">عنوان تخفیف</label></th>
						<td>
							<input type="text" name="<?php echo esc_attr( self::OPTION ); ?>[cash_discount_label]" id="wbgp_cash_label" value="<?php echo esc_attr( $o['cash_discount_label'] ); ?>" class="regular-text" />
						</td>
					</tr>

					<tr>
						<th scope="row">مالیات روی کارمزد/تخفیف</th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[taxable]" value="1" <?php checked( ! empty( $o['taxable'] ) ); ?> />
								قابل محاسبه مالیات باشد
							</label>
						</td>
					</tr>
				</table>

				<?php submit_button( 'ذخیره تنظیمات' ); ?>
			</form>
		</div>
		<?php
	}
}
