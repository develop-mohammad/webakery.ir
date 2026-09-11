<?php
defined( 'ABSPATH' ) || exit;
$nck_pay_id       = isset( $nck_pay_id ) ? $nck_pay_id : 'nck-pay';
$nck_pay_amount   = isset( $nck_pay_amount ) ? (string) $nck_pay_amount : '';
$nck_pay_required = ! empty( $nck_pay_required );
$nck_pay_from     = isset( $nck_pay_from ) ? (string) $nck_pay_from : '';
?>
<section class="nck-step" data-nck-step data-nck-step-label="پرداخت" hidden>
	<fieldset class="nck-fieldset">
		<legend>وضعیت پرداخت</legend>
		<div class="nck-chips">
			<?php
			$i = 0;
			foreach ( NCK_Learner::payment_options() as $k => $label ) :
				?>
				<label class="nck-chip">
					<input type="radio" name="payment" value="<?php echo esc_attr( $k ); ?>"<?php echo 0 === $i ? ' required' : ''; ?> />
					<?php echo esc_html( $label ); ?>
				</label>
				<?php
				$i++;
			endforeach;
			?>
		</div>
		<div class="nck-grid nck-grid-hall" style="margin-top:12px">
			<div class="nck-field">
				<label for="<?php echo esc_attr( $nck_pay_id ); ?>-amount">مبلغ پرداخت (تومان)</label>
				<input
					id="<?php echo esc_attr( $nck_pay_id ); ?>-amount"
					name="pay_amount"
					type="text"
					dir="ltr"
					placeholder="مثلاً 2500000"
					value="<?php echo esc_attr( $nck_pay_amount ); ?>"
					<?php echo $nck_pay_required ? 'required' : ''; ?>
					<?php echo $nck_pay_from !== '' ? 'data-nck-from="' . esc_attr( $nck_pay_from ) . '"' : ''; ?>
				/>
				<p class="nck-note">با وارد کردن مبلغ، سفارش در ووکامرس و حسابدار هم ثبت می‌شود.</p>
			</div>
			<div class="nck-field">
				<label for="<?php echo esc_attr( $nck_pay_id ); ?>-date">تاریخ پرداخت</label>
				<input id="<?php echo esc_attr( $nck_pay_id ); ?>-date" name="pay_date" type="text" dir="ltr" placeholder="1404/06/20" />
			</div>
			<div class="nck-field">
				<label for="<?php echo esc_attr( $nck_pay_id ); ?>-ref">شماره پیگیری</label>
				<input id="<?php echo esc_attr( $nck_pay_id ); ?>-ref" name="pay_ref" type="text" dir="ltr" />
			</div>
		</div>
	</fieldset>
</section>
