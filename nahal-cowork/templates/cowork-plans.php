<?php
defined( 'ABSPATH' ) || exit;
?>
<div class="nck-field nck-field-wide">
	<span class="nck-label">نوع اشتراک</span>
	<div class="nck-plan-list" data-nck-packages>
		<?php foreach ( NCK_Shifts::packages() as $pkg ) : ?>
			<?php
			$price = NCK_Shifts::package_price( $pkg['id'] );
			?>
			<label class="nck-plan-card">
				<input
					type="radio"
					name="package"
					value="<?php echo esc_attr( $pkg['id'] ); ?>"
					required
					data-nck-price="<?php echo esc_attr( (string) $price ); ?>"
					data-nck-dual="<?php echo ! empty( $pkg['dual'] ) ? '1' : '0'; ?>"
				/>
				<span class="nck-plan-copy">
					<strong><?php echo esc_html( $pkg['label'] ); ?></strong>
				</span>
				<span class="nck-plan-price"><?php echo esc_html( NCK_Hall::format_money( $price ) ); ?></span>
			</label>
		<?php endforeach; ?>
	</div>
</div>
<div class="nck-field nck-field-wide" data-nck-shift-wrap hidden>
	<span class="nck-label">شیفت استفاده</span>
	<div class="nck-plans">
		<label class="nck-plan"><input type="radio" name="shift" value="morning" /> صبح</label>
		<label class="nck-plan"><input type="radio" name="shift" value="evening" /> عصر</label>
	</div>
	<p class="nck-form-help">برای اشتراک تک‌شیفت، صبح یا عصر را انتخاب کنید. اشتراک دو شیفت هر دو نوبت را پوشش می‌دهد.</p>
</div>
