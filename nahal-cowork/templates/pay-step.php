<?php
defined( 'ABSPATH' ) || exit;
$nck_pay_id       = isset( $nck_pay_id ) ? $nck_pay_id : 'nck-pay';
$nck_pay_amount   = isset( $nck_pay_amount ) ? (string) $nck_pay_amount : '';
$nck_pay_required = ! empty( $nck_pay_required );
$nck_pay_from     = isset( $nck_pay_from ) ? (string) $nck_pay_from : '';
?>
<section class="nck-step" data-nck-step data-nck-step-label="پرداخت" hidden>
	<input type="hidden" name="payment" value="site" />
	<div class="nck-pay-site">
		<p class="nck-pay-site-kicker">پرداخت فقط از طریق سایت انجام می‌شود.</p>
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
