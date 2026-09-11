<?php
defined( 'ABSPATH' ) || exit;
$nck_pay_id       = isset( $nck_pay_id ) ? $nck_pay_id : 'nck-pay';
$nck_pay_amount   = isset( $nck_pay_amount ) ? (string) $nck_pay_amount : '';
$nck_pay_required = ! empty( $nck_pay_required );
$nck_pay_from     = isset( $nck_pay_from ) ? (string) $nck_pay_from : '';
$nck_logged       = class_exists( 'NCK_Pay' ) ? NCK_Pay::is_logged_in() : false;
$nck_wc           = class_exists( 'NCK_Pay' ) ? NCK_Pay::wc_ready() : false;
$nck_login_url    = class_exists( 'NCK_Pay' ) ? NCK_Pay::login_url() : '';
?>
<section class="nck-step" data-nck-step data-nck-step-label="پرداخت" data-nck-pay-site hidden>
	<input type="hidden" name="payment" value="site" />
	<div class="nck-pay-site">
		<p class="nck-pay-site-kicker">پرداخت از درگاه سایت انجام می‌شود؛ مثل خرید محصولات ووکامرس.</p>
		<div class="nck-pay-gate" data-nck-login-needed<?php echo $nck_logged ? ' hidden' : ''; ?>>
			<p>برای رفتن به صفحه پرداخت باید وارد حساب کاربری سایت شوید.</p>
			<p>
				<a class="nck-btn" href="<?php echo esc_url( $nck_login_url ); ?>" data-nck-login-link>ورود به سایت</a>
			</p>
		</div>
		<div class="nck-pay-gate" data-nck-login-ok<?php echo ( $nck_logged && $nck_wc ) ? '' : ' hidden'; ?>>
			<p>پس از ثبت، مبلغ قرارداد به سبد خرید اضافه می‌شود و به صفحه تسویه / درگاه سایت می‌روید.</p>
		</div>
		<div class="nck-pay-gate" data-nck-wc-off<?php echo ( $nck_logged && ! $nck_wc ) ? '' : ' hidden'; ?>>
			<p>ووکامرس فعال نیست؛ پرداخت از درگاه سایت ممکن نیست.</p>
		</div>
		<div class="nck-field">
			<label for="<?php echo esc_attr( $nck_pay_id ); ?>-amount">مبلغ قابل پرداخت (تومان)</label>
			<input
				id="<?php echo esc_attr( $nck_pay_id ); ?>-amount"
				name="pay_amount"
				type="text"
				dir="ltr"
				readonly
				value="<?php echo esc_attr( $nck_pay_amount ); ?>"
				<?php echo $nck_pay_required ? 'required' : ''; ?>
				<?php echo $nck_pay_from !== '' ? 'data-nck-from="' . esc_attr( $nck_pay_from ) . '"' : ''; ?>
			/>
		</div>
		<p class="nck-note">شماره پیگیری پس از ثبت، به‌صورت خودکار صادر می‌شود.</p>
	</div>
</section>
